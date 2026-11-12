<?php
/* Proteção CSRF — um token por sessão, obrigatório em todos os POST
   que alteram estado (criar, editar, eliminar, publicar, etc.).
   GET nunca deve alterar estado, por isso nunca precisa de token. */

function tokenCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/* Imprime o campo escondido a incluir dentro de <form method="post">. */
function campoCSRF(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(tokenCSRF()) . '">';
}

/* Verdadeiro se o POST atual traz o token desta sessão. Para as páginas
   públicas (login, registo, recuperação), onde um token expirado é normal
   — o separador ficou aberto de um dia para o outro — e a resposta certa
   é voltar a mostrar o formulário com um aviso, não uma página de erro. */
function csrfValido(): bool {
    $token = $_POST['csrf_token'] ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $esperado !== '' && hash_equals($esperado, $token);
}

/* Chamar no início de cada bloco que processa um POST. Termina o pedido
   se o token não bater certo (sessão expirada, ou pedido forjado). */
function validarCSRF(): void {
    if (csrfValido()) { return; }
    $mensagem = 'Pedido inválido ou sessão expirada. Volta atrás, atualiza a página e tenta novamente.';
    http_response_code(419);
    // Os pedidos por fetch() esperam JSON (assets/js/script.js) — texto
    // simples fazia o toast dizer "não foi possível ligar ao servidor".
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        header('Content-Type: application/json; charset=UTF-8');
        die(json_encode(['sucesso' => false, 'mensagem' => $mensagem]));
    }
    header('Content-Type: text/plain; charset=UTF-8');
    die($mensagem);
}
