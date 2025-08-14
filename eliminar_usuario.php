<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

// Verificar que sea un administrador
if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

// Verificar si viene el ID
$id_usuario = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_usuario === 0) {
    header("Location: usuarios.php?error=ID inválido");
    exit;
}

// Prevenir que un admin se elimine a sí mismo
if ($id_usuario === $_SESSION['usuario_id']) {
    header("Location: usuarios.php?error=No puedes eliminar tu propio usuario");
    exit;
}

// Verificar si el usuario existe
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    header("Location: usuarios.php?error=Usuario no encontrado");
    exit;
}
$stmt->close();

// Eliminar el usuario
$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id_usuario);

if ($stmt->execute()) {
    header("Location: usuarios.php?success=Usuario eliminado correctamente");
} else {
    header("Location: usuarios.php?error=Error al eliminar el usuario");
}
?>
