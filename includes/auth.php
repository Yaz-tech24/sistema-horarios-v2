<?php
// Carrega a configuração (BASE_URL + ligação $pdo) ANTES de iniciar a
// sessão — precisamos de BASE_URL para restringir o cookie de sessão ao
// caminho do projeto. require_once garante que só corre uma vez por pedido.
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    // Cookie de sessão reforçado: HttpOnly (JS não lhe toca, mitiga XSS a
    // roubar a sessão), SameSite=Lax (bloqueia a maior parte do CSRF
    // cross-site) e Secure automático se o pedido já vier por HTTPS.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash.php';

function utilizadorAutenticado() {
    return isset($_SESSION['utilizador_id']);
}

function exigirLogin() {
    if (!utilizadorAutenticado()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function exigirPerfil($perfisPermitidos) {
    exigirLogin();
    if (!in_array($_SESSION['perfil'], $perfisPermitidos)) {
        global $pdo; // header.php consulta notificações por ler — precisa de $pdo, que não é visto aqui sem isto
        http_response_code(403);
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
