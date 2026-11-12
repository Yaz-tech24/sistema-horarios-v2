<?php
/* ============================================================
   Núcleo de segurança — tudo o que não é específico de uma página:
     - deteção de HTTPS / IP do cliente (com ou sem proxy à frente);
     - cabeçalhos HTTP de segurança (CSP com nonce, HSTS, etc.);
     - política de palavras-passe (uma só, usada em todo o lado);
     - registo de auditoria e limitação de tentativas por IP;
     - tratamento global de erros (nunca mostrar detalhes em produção).
   Carregado por includes/auth.php, logo depois de config/db.php.
   ============================================================ */

/* ---- Limites (num só sítio, para serem fáceis de ajustar) ---- */

const SENHA_MIN = 8;
// O bcrypt (PASSWORD_DEFAULT) ignora em silêncio tudo o que passe dos 72
// bytes — duas passwords diferentes só depois desse ponto seriam aceites
// como iguais. Recusar acima disso é mais honesto do que truncar.
const SENHA_MAX_BYTES = 72;

const SESSAO_INATIVIDADE_MINUTOS = 60;  // sem atividade → sessão termina
const SESSAO_DURACAO_MAX_HORAS   = 12;  // mesmo com atividade → novo login

const LOGIN_MAX_TENTATIVAS   = 5;       // por conta (ou e-mail), antes do bloqueio
const LOGIN_BLOQUEIO_MINUTOS = 5;
// Por IP o limite é bem mais largo: numa faculdade é normal dezenas de
// pessoas saírem pelo mesmo IP público, e um limite apertado bloqueava
// toda a gente por causa de um colega distraído.
const IP_MAX_FALHAS      = 30;
const IP_JANELA_MINUTOS  = 15;

/* ---- Pedido atual ---- */

/* Atrás de um proxy que termina o TLS (Traefik, no docker-compose.yml), o
   Apache do contentor só vê HTTP simples — $_SERVER['HTTPS'] fica vazio e o
   cookie de sessão saía sem a flag Secure. PROXY_CONFIAVEL=1 diz que os
   cabeçalhos X-Forwarded-* são postos por um proxy nosso e podem ser lidos;
   sem essa variável são ignorados, porque qualquer cliente os pode forjar. */
function proxyConfiavel(): bool {
    return getenv('PROXY_CONFIAVEL') === '1';
}

function ehHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') { return true; }
    if (proxyConfiavel() && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') { return true; }
    return getenv('FORCAR_HTTPS') === '1';
}

/* IP de quem faz o pedido. Com proxy confiável é a ÚLTIMA entrada de
   X-Forwarded-For (a que o nosso proxy acrescentou) — as anteriores vêm do
   cliente e não valem nada. */
function ipCliente(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (proxyConfiavel() && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $partes = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $ultimo = end($partes);
        if (filter_var($ultimo, FILTER_VALIDATE_IP)) { $ip = $ultimo; }
    }
    return substr($ip, 0, 45);
}

/* Endereço público da aplicação (sem barra final), para links enviados por
   e-mail. Em produção vem SEMPRE de APP_URL — construí-lo a partir do
   cabeçalho Host deixava um atacante pedir a recuperação da conta de outra
   pessoa com "Host: site-dele.com" e receber o token no servidor dele. Fora
   de produção aceita-se o Host, para funcionar em localhost sem configurar. */
function urlPublica(): ?string {
    $url = rtrim((string)getenv('APP_URL'), '/');
    if ($url !== '' && preg_match('#^https?://#i', $url)) { return $url; }
    if (getenv('APP_ENV') === 'producao') { return null; }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '' || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) { return null; }
    return (ehHttps() ? 'https' : 'http') . '://' . $host . BASE_URL;
}

/* Um campo de texto do POST, já aparado e cortado ao tamanho da coluna
   onde vai ser gravado. Devolve '' se o campo não vier ou vier como array
   (nome[]=x) — sem isto, trim() de um array rebentava com um erro 500, e
   um texto maior do que a coluna fazia o MySQL recusar o INSERT. */
function postTexto(string $campo, int $max = 255): string {
    $valor = $_POST[$campo] ?? '';
    if (!is_string($valor)) { return ''; }
    return mb_substr(trim($valor), 0, $max);
}

/* Igual, mas sem aparar nem cortar — para palavras-passe, onde cada
   caractere conta (o limite de tamanho é validarPalavraPasse() que impõe). */
function postSenha(string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? $valor : '';
}

/* ---- Cabeçalhos de segurança ---- */

/* Um nonce por pedido: os <script> inline das páginas levam
   nonce="<?= nonceCSP() ?>" e a CSP só deixa correr esses — um <script>
   injetado por XSS não tem o nonce e fica bloqueado pelo browser. */
function nonceCSP(): string {
    static $nonce = null;
    if ($nonce === null) { $nonce = base64_encode(random_bytes(16)); }
    return $nonce;
}

function enviarCabecalhosSeguranca(): void {
    if (headers_sent()) { return; }
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    // style-src mantém 'unsafe-inline' porque as páginas usam atributos
    // style="" — o risco de XSS vem de scripts, e esses ficam só com nonce.
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'nonce-" . nonceCSP() . "'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data:; connect-src 'self'; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    if (ehHttps()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/* ---- Palavras-passe ---- */

/* Devolve null se a palavra-passe serve, ou a frase a mostrar se não.
   Comprimento + lista de proibidas (as óbvias e as das contas de teste,
   que estão escritas no README) — sem regras de "tem de ter um símbolo",
   que só empurram as pessoas para Password1!. */
function validarPalavraPasse(string $senha, string $email = ''): ?string {
    if (mb_strlen($senha) < SENHA_MIN) {
        return 'A palavra-passe tem de ter pelo menos ' . SENHA_MIN . ' caracteres.';
    }
    if (strlen($senha) > SENHA_MAX_BYTES) {
        return 'A palavra-passe é demasiado longa (máximo ' . SENHA_MAX_BYTES . ' caracteres).';
    }
    $minuscula = mb_strtolower($senha);
    $proibidas = [
        'admin123', 'coord123', 'docente123', 'password', 'password1', 'palavra-passe',
        'palavrapasse', '12345678', '123456789', '1234567890', '87654321', 'qwertyui',
        'qwerty123', 'abc12345', 'abcd1234', 'iloveyou', 'fagrenm123', 'ucm12345',
        'mocambique', 'administrador',
    ];
    if (in_array($minuscula, $proibidas, true)) {
        return 'Essa palavra-passe é demasiado comum — escolhe outra.';
    }
    if (count(array_unique(mb_str_split($senha))) < 4) {
        return 'A palavra-passe tem de ter caracteres mais variados.';
    }
    $email = mb_strtolower(trim($email));
    if ($email !== '') {
        $local = explode('@', $email)[0];
        if ($minuscula === $email || $minuscula === $local) {
            return 'A palavra-passe não pode ser igual ao teu e-mail.';
        }
    }
    return null;
}

/* "Impressão digital" do hash atual da palavra-passe, guardada na sessão.
   Quando a palavra-passe muda (pelo próprio noutro browser, ou pelo
   Administrador), a impressão na base de dados deixa de bater com a da
   sessão e essa sessão termina — ver validarSessaoAtual() em auth.php. */
function marcaDaSenha(string $passwordHash): string {
    return substr(hash('sha256', $passwordHash), 0, 32);
}

/* Liga/desliga a obrigação de mudar a palavra-passe no próximo acesso.
   Numa instrução à parte e tolerante a falhas: se a base de dados ainda
   não tiver a coluna (migração por correr), o resto da ação — criar a
   conta, mudar a palavra-passe — não deve falhar por causa disto. */
function definirMudancaObrigatoria(PDO $pdo, int $utilizadorId, bool $obrigatoria): void {
    try {
        $pdo->prepare("UPDATE utilizadores SET deve_mudar_senha = ? WHERE id = ?")
            ->execute([$obrigatoria ? 1 : 0, $utilizadorId]);
    } catch (PDOException $e) {
        error_log('deve_mudar_senha indisponível (falta correr sql/migracao_seguranca_v2.sql?): ' . $e->getMessage());
    }
}

/* Contas inativas que nunca entraram no sistema = registos feitos em
   auth/registar.php à espera de o Administrador os ativar. (Uma conta que
   já entrou e foi desativada depois não conta — essa foi uma decisão do
   Administrador, não um pedido pendente.) Tolerante à coluna ultimo_login
   ainda não existir. */
function contasPorAtivar(PDO $pdo): int {
    try {
        return (int)$pdo->query(
            "SELECT COUNT(*) FROM utilizadores WHERE ativo = 0 AND ultimo_login IS NULL")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/* ---- Auditoria ---- */

/* Regista um acontecimento relevante (entradas, falhas, alterações de
   contas, publicações). Nunca lança exceção: uma falha a gravar o registo
   — incluindo a tabela ainda não existir — nunca pode impedir a ação em si.
   $utilizadorId: por omissão, quem tem sessão; passar explicitamente quando
   ainda não há sessão (ex.: login) ou quando o alvo é outra conta. */
function registarAuditoria(PDO $pdo, string $acao, ?string $detalhe = null, ?int $utilizadorId = null): void {
    try {
        if ($utilizadorId === null && isset($_SESSION['utilizador_id'])) {
            $utilizadorId = (int)$_SESSION['utilizador_id'];
        }
        $pdo->prepare("INSERT INTO registo_auditoria (utilizador_id, acao, detalhe, ip) VALUES (?,?,?,?)")
            ->execute([
                $utilizadorId,
                mb_substr($acao, 0, 40),
                $detalhe !== null ? mb_substr($detalhe, 0, 255) : null,
                ipCliente(),
            ]);
        // Limpeza ocasional (1 em cada ~200 registos) do que tem mais de um
        // ano — o registo serve para investigar, não para guardar para sempre.
        if (random_int(1, 200) === 1) {
            $pdo->exec("DELETE FROM registo_auditoria WHERE criado_em < DATE_SUB(NOW(), INTERVAL 365 DAY)");
        }
    } catch (Throwable $e) {
        error_log('Auditoria indisponível (' . $acao . '): ' . $e->getMessage());
    }
}

/* Quantas vezes este IP fez uma das $acoes nos últimos $minutos — a base
   da limitação por IP (login, registo, recuperação de palavra-passe). */
function acoesRecentesDoIp(PDO $pdo, array $acoes, int $minutos): int {
    if (!$acoes) { return 0; }
    try {
        $marcas = implode(',', array_fill(0, count($acoes), '?'));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM registo_auditoria
             WHERE acao IN ($marcas) AND ip = ? AND criado_em > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute([...$acoes, ipCliente(), $minutos]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0; // sem tabela de auditoria não há limitação por IP, mas o site funciona
    }
}

/* Falhas de login recentes para um e-mail que NÃO corresponde a nenhuma
   conta. Serve para a página de login responder a um e-mail inexistente
   exatamente como responde a uma conta real (bloqueio ao fim de 5
   tentativas) — senão, a mensagem de bloqueio só aparecer para contas que
   existem denunciava quais os e-mails registados. */
function falhasRecentesDoEmail(PDO $pdo, string $email, int $minutos): int {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM registo_auditoria
             WHERE acao = 'login_falhado' AND detalhe = ? AND criado_em > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute([mb_substr($email, 0, 255), $minutos]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/* ---- Erros ---- */

/* Qualquer exceção que nenhuma página apanhe (uma chave estrangeira violada
   por um pedido forjado, a base de dados em baixo a meio do pedido, etc.)
   acaba aqui: vai para o log do servidor com todo o detalhe, e quem está no
   browser vê só uma frase neutra — nunca a query, o caminho do ficheiro ou
   o stack trace. Fora de produção mostra-se o erro, para se poder depurar. */
function instalarTratamentoDeErros(bool $producao): void {
    set_exception_handler(function (Throwable $e) use ($producao) {
        error_log(sprintf('Exceção não tratada: %s em %s:%d — %s',
            get_class($e), $e->getFile(), $e->getLine(), $e->getMessage()));

        // Coluna ou tabela inexistente = código novo sobre base de dados antiga.
        if ($e instanceof PDOException && in_array($e->getCode(), ['42S22', '42S02'], true)) {
            error_log('A base de dados parece desatualizada — corre sql/atualizar_producao_vps.sql (ver README).');
        }

        $mensagem = 'Ocorreu um erro inesperado. Volta atrás e tenta novamente; se continuar, contacta o Administrador.';
        if (!headers_sent()) { http_response_code(500); }

        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            if (!headers_sent()) { header('Content-Type: application/json; charset=UTF-8'); }
            echo json_encode(['sucesso' => false, 'mensagem' => $mensagem]);
            exit;
        }

        echo '<div style="max-width:560px;margin:48px auto;padding:20px 22px;border:1px solid #c0392b;'
           . 'border-left-width:5px;background:#FBEAEA;color:#7a1f1f;font:15px/1.5 system-ui,sans-serif">'
           . '<strong>Algo correu mal.</strong><br>' . htmlspecialchars($mensagem);
        if (!$producao) {
            echo '<pre style="margin:14px 0 0;white-space:pre-wrap;font-size:12px;color:#333">'
               . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine())
               . '</pre>';
        }
        echo '</div>';
        exit;
    });
}
