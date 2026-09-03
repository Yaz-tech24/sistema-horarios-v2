<?php
/* Mensagens "flash": sobrevivem exatamente a um redirect (padrão
   Post-Redirect-Get), para as ações de guardar/eliminar poderem fazer
   header('Location: ...') sem perder a mensagem de sucesso/erro e sem
   arriscar reenvio do formulário ao atualizar a página. */

function definirFlash(string $tipo, string $texto): void {
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function lerFlash(): ?array {
    if (empty($_SESSION['flash'])) { return null; }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

/* Pedidos "eliminar"/"ativar" disparados por fetch() (ver data-ajax-remover
   e data-ajax-alternar em assets/js/script.js) levam este cabeçalho para
   pedir uma resposta JSON em vez do Post-Redirect-Get normal — assim a
   página reage na hora, sem recarregar inteira. */
function ehPedidoAjax(): bool {
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

/* $extra leva dados que o JS precise para atualizar a página sem a
   recarregar — ex.: o novo estado 'ativo' depois de um alternar (ver
   admin/utilizadores.php), que o JS não tem como adivinhar sozinho. */
function responderAjax(bool $sucesso, string $mensagem, array $extra = []): void {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['sucesso' => $sucesso, 'mensagem' => $mensagem] + $extra);
    exit;
}
