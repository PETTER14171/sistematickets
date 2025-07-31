<?php
session_start();

// Si no hay sesión iniciada
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit;
}

// Puedes agregar validaciones específicas por rol
// por ejemplo:
// if ($_SESSION['rol'] !== 'admin') {
//     header("Location: login.php?error=Acceso denegado");
//     exit;
// }
