<?php
// Carrega a configuração (BASE_URL + ligação $pdo + includes/seguranca.php)
// ANTES de iniciar a sessão — precisamos de BASE_URL para restringir o
// cookie de sessão ao caminho do projeto. require_once garante que só
// corre uma vez por pedido.
require_once __DIR__ . '/../config/db.php';

enviarCabecalhosSeguranca();

if (session_status() === PHP_SESSION_NONE) {
    // Só aceita IDs de sessão criados pelo servidor (um ID inventado por um
    // atacante e "plantado" no browser da vítima é recusado) e só via cookie
    // — nunca pelo URL, onde ficaria em históricos e logs.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)(SESSAO_DURACAO_MAX_HORAS * 3600));
    session_name('HORARIOS_SESSAO');
    // Cookie de sessão reforçado: HttpOnly (JS não lhe toca, mitiga XSS a
    // roubar a sessão), SameSite=Lax (bloqueia a maior parte do CSRF
    // cross-site) e Secure sempre que o site é servido por HTTPS — incluindo
    // quando o HTTPS termina num proxy à frente (ver ehHttps()).
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => ehHttps(),
    ]);
    session_start();
}

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash.php';

/* Termina a sessão atual por completo (dados no servidor + cookie no
   browser) e abre uma nova, vazia — é nela que fica o aviso que a página
   de login mostra a seguir ("sessão expirou", etc.). */
function terminarSessao(?string $aviso = null): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    if ($aviso !== null) { $_SESSION['aviso_login'] = $aviso; }
}

/* Abre a sessão de um utilizador acabado de autenticar. Novo ID de sessão
   a cada login — evita fixação de sessão. */
function iniciarSessaoUtilizador(array $utilizador): void {
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['utilizador_id']    = (int)$utilizador['id'];
    $_SESSION['nome']             = $utilizador['nome'];
    $_SESSION['perfil']           = $utilizador['perfil'];
    $_SESSION['senha_marca']      = marcaDaSenha($utilizador['password_hash']);
    $_SESSION['deve_mudar_senha'] = !empty($utilizador['deve_mudar_senha']);
    $_SESSION['login_em']         = time();
    $_SESSION['ultima_atividade'] = time();
}

/* Corre uma vez por pedido, para quem traz sessão: confirma que a sessão
   ainda devia existir. Antes, a sessão só era verificada no login — uma
   conta desativada pelo Administrador, ou com a palavra-passe mudada por
   suspeita de roubo, continuava com acesso total até fechar o browser. */
function validarSessaoAtual(PDO $pdo): void {
    if (!isset($_SESSION['utilizador_id'])) { return; }

    $agora = time();
    if ($agora - (int)($_SESSION['ultima_atividade'] ?? 0) > SESSAO_INATIVIDADE_MINUTOS * 60) {
        terminarSessao('A tua sessão terminou por inatividade. Entra novamente.');
        return;
    }
    if ($agora - (int)($_SESSION['login_em'] ?? 0) > SESSAO_DURACAO_MAX_HORAS * 3600) {
        terminarSessao('A tua sessão expirou. Entra novamente.');
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE id = ?");
    $stmt->execute([(int)$_SESSION['utilizador_id']]);
    $u = $stmt->fetch();

    if (!$u || !$u['ativo']) {
        terminarSessao('A tua conta foi desativada. Contacta o Administrador.');
        return;
    }
    if (!hash_equals((string)($_SESSION['senha_marca'] ?? ''), marcaDaSenha($u['password_hash']))) {
        terminarSessao('A palavra-passe desta conta foi alterada. Entra com a nova.');
        return;
    }

    // Nome e perfil vêm sempre da base de dados: se o Administrador mudar o
    // perfil de alguém, a mudança vale no pedido seguinte, não só no
    // próximo login.
    if ($_SESSION['perfil'] !== $u['perfil']) {
        // O contexto de coordenador (curso/turma/horário abertos) não pode
        // sobreviver a uma mudança de perfil.
        unset($_SESSION['curso_id_atual'], $_SESSION['turma_id_atual'],
              $_SESSION['horario_id_atual'], $_SESSION['curso_sigla_atual']);
    }
    $_SESSION['nome']             = $u['nome'];
    $_SESSION['perfil']           = $u['perfil'];
    $_SESSION['deve_mudar_senha'] = !empty($u['deve_mudar_senha']);
    $_SESSION['ultima_atividade'] = $agora;
}

validarSessaoAtual($pdo);

function utilizadorAutenticado() {
    return isset($_SESSION['utilizador_id']);
}

function exigirLogin() {
    if (!utilizadorAutenticado()) {
        // Um fetch() (eliminar/ativar sem recarregar) seguia o redirect e
        // recebia o HTML do login em vez de JSON — responde-se em JSON.
        if (ehPedidoAjax()) {
            http_response_code(401);
            responderAjax(false, 'A tua sessão terminou. Atualiza a página e entra novamente.');
        }
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }

    // Palavra-passe provisória (conta de origem, ou definida pelo
    // Administrador): até a pessoa escolher a sua, a única página a que
    // chega é "A minha conta".
    if (!empty($_SESSION['deve_mudar_senha'])) {
        $paginaAtual = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
        $paginaConta = realpath(__DIR__ . '/../conta.php');
        if ($paginaAtual === false || $paginaAtual !== $paginaConta) {
            if (ehPedidoAjax()) {
                http_response_code(403);
                responderAjax(false, 'Define primeiro uma nova palavra-passe em "A minha conta".');
            }
            header('Location: ' . BASE_URL . '/conta.php');
            exit;
        }
    }
}

function exigirPerfil($perfisPermitidos) {
    exigirLogin();
    if (!in_array($_SESSION['perfil'], $perfisPermitidos, true)) {
        global $pdo; // header.php consulta notificações por ler — precisa de $pdo, que não é visto aqui sem isto
        http_response_code(403);
        if (ehPedidoAjax()) { responderAjax(false, 'Esta ação não está disponível para o teu perfil.'); }
        $pageTitle = 'Acesso não autorizado';
        $pageDescription = 'Esta página não está disponível para o teu perfil (' . $_SESSION['perfil'] . ').';
        require_once __DIR__ . '/header.php';
        ?>
        <div class="card">
            <div class="card-body">
                <p style="margin:0 0 16px;color:var(--ink-soft)">
                    Se achas que isto é um engano, contacta o Administrador do sistema.</p>
                <a class="btn btn-primary" style="display:inline-flex;width:auto;padding-inline:2rem;"
                   href="<?= BASE_URL ?>/painel.php">Voltar ao painel</a>
            </div>
        </div>
        <?php
        require_once __DIR__ . '/footer.php';
        exit;
    }
}
