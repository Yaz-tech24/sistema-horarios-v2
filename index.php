<?php
require_once __DIR__ . '/includes/auth.php';

if (utilizadorAutenticado()) {
    header('Location: ' . BASE_URL . '/painel.php');
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
