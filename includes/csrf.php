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

/* Chamar no início de cada bloco que processa um POST. Termina o pedido
   se o token não bater certo (sessão expirada, ou pedido forjado). */
function validarCSRF(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die('Pedido inválido ou sessão expirada. Volta atrás, atualiza a página e tenta novamente.');
    }
}
