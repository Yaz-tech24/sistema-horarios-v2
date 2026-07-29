<?php
require 'includes/auth.php';

if (utilizadorAutenticado()) {
    switch ($_SESSION['perfil']) {
        case 'Administrador':
            header('Location: admin/cursos.php'); break;
        case 'Coordenador':
            header('Location: coordenador/escolher_curso.php'); break;
        case 'Docente':
            header('Location: docente/meu_horario.php'); break;
    }
} else {
    header('Location: auth/login.php');
}
exit;
