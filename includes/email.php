<?php
/* Cliente SMTP nativo (sem bibliotecas externas, como todo o resto do
   projeto) para enviar aos docentes um email quando o horário deles fica
   disponível. Configuração via .env (ver .env.example):
     SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM, SMTP_FROM_NAME,
     SMTP_SECURE (ssl|tls — por omissão infere-se da porta).
   Sem SMTP_HOST definido, smtpConfigurado() devolve false e o sistema
   continua a funcionar normalmente só com as notificações internas
   (tabela `notificacoes`) — o envio de email é sempre um extra, nunca um
   requisito para publicar um horário.

   Suporta os dois esquemas de TLS mais comuns:
     - implícito ("SMTPS" propriamente dito — tipicamente porta 465): a
       ligação já nasce cifrada.
     - STARTTLS (tipicamente porta 587): a ligação começa em texto simples
       e só depois pede para cifrar. */

class SmtpException extends Exception {}

function smtpConfigurado(): bool {
    return (bool) getenv('SMTP_HOST');
}

/* A recuperação de palavra-passe por e-mail só existe se houver como
   enviar o e-mail (SMTP) E um endereço público de confiança para pôr no
   link (APP_URL em produção — ver urlPublica() em includes/seguranca.php).
   Sem as duas coisas, o link "Esqueci-me da palavra-passe" nem aparece e
   quem se esquecer continua a pedir ao Administrador, como antes. */
function recuperacaoDisponivel(): bool {
    return smtpConfigurado() && urlPublica() !== null;
}

function smtpLerResposta($socket): string {
    $resposta = '';
    while (($linha = fgets($socket, 515)) !== false) {
        $resposta .= $linha;
        // A linha final de uma resposta multi-linha tem espaço na 4ª
        // posição (ex.: "250 OK"); linhas intermédias têm hífen
        // (ex.: "250-STARTTLS").
        if (strlen($linha) < 4 || $linha[3] === ' ') { break; }
    }
    return $resposta;
}

function smtpComando($socket, string $comando, string $codigoEsperado): string {
    fwrite($socket, $comando . "\r\n");
    $resposta = smtpLerResposta($socket);
    if (substr($resposta, 0, 3) !== $codigoEsperado) {
        throw new SmtpException("Resposta inesperada a \"$comando\": " . trim($resposta));
    }
    return $resposta;
}

/* Assunto e nomes podem ter acentos — sem os codificar (RFC 2047), o
   Outlook/Gmail mostram cabeçalhos corrompidos. */
function codificarCabecalhoEmail(string $texto): string {
    return '=?UTF-8?B?' . base64_encode($texto) . '?=';
}

/* Envia um email de texto simples a um destinatário. Nunca lança exceção
   para fora — devolve true/false, e regista o motivo da falha no log de
   erros do servidor (nunca aos olhos do coordenador, que só vê "X
   docente(s) notificado(s) por email"). */
/* Remove quebras de linha de qualquer valor que vá para dentro de um
   comando SMTP ou de um cabeçalho de email — nome/email vêm de
   utilizadores.nome/email (texto livre, preenchido pelo Admin em
   admin/utilizadores.php sem validação de formato) e um "\r\n" aí dentro
   permitiria injetar comandos SMTP extra ou cabeçalhos de email falsos
   (ex.: um Bcc escondido). Nunca confiar que quem preencheu esse campo o
   fez com boa formatação. */
function semQuebrasDeLinha(string $texto): string {
    return trim(str_replace(["\r", "\n"], ' ', $texto));
}

function enviarEmailSmtp(string $paraEmail, string $paraNome, string $assunto, string $corpoTexto): bool {
    $host = getenv('SMTP_HOST');
    if (!$host) { return false; }
    $paraEmail = semQuebrasDeLinha($paraEmail);
    $paraNome  = semQuebrasDeLinha($paraNome);
    $assunto   = semQuebrasDeLinha($assunto);
    // Validação completa do endereço, não só "tem um @": o valor vai para
    // dentro de RCPT TO:<...>, e um ">" ou um espaço ali no meio permitia
    // acrescentar parâmetros ao comando.
    if (!filter_var($paraEmail, FILTER_VALIDATE_EMAIL)) { return false; }
    $porta = getenv('SMTP_PORT') !== false ? (int)getenv('SMTP_PORT') : 465;
    $utilizador = getenv('SMTP_USER') ?: '';
    $palavraPasse = getenv('SMTP_PASS') ?: '';
    $deEmail = semQuebrasDeLinha(getenv('SMTP_FROM') ?: $utilizador);
    $deNome = semQuebrasDeLinha(getenv('SMTP_FROM_NAME') ?: 'Sistema de Horarios FAGRENM');
    $seguranca = getenv('SMTP_SECURE') ?: ($porta === 587 ? 'tls' : 'ssl');

    $endereco = ($seguranca === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $porta;
    // peer_name explícito: no caminho STARTTLS a ligação nasce como tcp://
    // e só depois é cifrada — assim o certificado é sempre conferido contra
    // o nome do servidor que configurámos, não contra o que ele disser.
    $contexto = stream_context_create(['ssl' => [
        'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host,
    ]]);
    // Se o servidor de e-mail já falhou a ligação neste pedido, não volta a
    // tentar: ao publicar, cada docente era uma tentativa nova de 15
    // segundos, e com o SMTP em baixo a publicação de um horário com 20
    // docentes demorava 5 minutos e acabava cortada pelo limite de tempo do
    // PHP a meio das notificações.
    static $servidorEmBaixo = false;
    if ($servidorEmBaixo) { return false; }

    $socket = @stream_socket_client($endereco, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $contexto);
    if (!$socket) {
        $servidorEmBaixo = true;
        error_log("SMTP: falha a ligar a $host:$porta — $errstr (os restantes e-mails deste pedido não são tentados)");
        return false;
    }
    stream_set_timeout($socket, 15);

    // SERVER_NAME pode vir do cabeçalho Host do pedido (depende da
    // configuração do Apache) — só letras, algarismos, pontos e hífenes
    // chegam ao comando EHLO.
    $dominioLocal = preg_replace('/[^A-Za-z0-9.\-]/', '', $_SERVER['SERVER_NAME'] ?? '') ?: 'localhost';
    try {
        smtpLerResposta($socket); // saudação 220
        smtpComando($socket, "EHLO $dominioLocal", '250');
        if ($seguranca === 'tls') {
            smtpComando($socket, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new SmtpException('Falha ao ativar TLS (STARTTLS).');
            }
            smtpComando($socket, "EHLO $dominioLocal", '250');
        }
        if ($utilizador !== '') {
            smtpComando($socket, 'AUTH LOGIN', '334');
            smtpComando($socket, base64_encode($utilizador), '334');
            smtpComando($socket, base64_encode($palavraPasse), '235');
        }
        smtpComando($socket, "MAIL FROM:<$deEmail>", '250');
        smtpComando($socket, "RCPT TO:<$paraEmail>", '250');
        smtpComando($socket, 'DATA', '354');

        $cabecalhos = [
            'Date: ' . date('r'),
            'From: ' . codificarCabecalhoEmail($deNome) . " <$deEmail>",
            'To: ' . codificarCabecalhoEmail($paraNome) . " <$paraEmail>",
            'Subject: ' . codificarCabecalhoEmail($assunto),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        // Corpo em base64 evita ter de escapar linhas que comecem por "."
        // (terminador do comando DATA, RFC 5321) e o limite de 998 bytes
        // por linha do protocolo SMTP.
        $mensagem = implode("\r\n", $cabecalhos) . "\r\n\r\n" . chunk_split(base64_encode($corpoTexto));
        fwrite($socket, $mensagem . "\r\n.\r\n");
        $resposta = smtpLerResposta($socket);
        if (substr($resposta, 0, 3) !== '250') {
            throw new SmtpException('Resposta inesperada ao terminar DATA: ' . trim($resposta));
        }
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    } catch (SmtpException $e) {
        error_log('SMTP: ' . $e->getMessage());
        if (is_resource($socket)) { fclose($socket); }
        return false;
    }
}
