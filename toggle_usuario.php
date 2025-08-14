<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$id_usuario = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_usuario === 0 || $id_usuario === $_SESSION['usuario_id']) {
    header("Location: usuarios.php?error=ID inválido o no puedes modificar tu propia cuenta");
    exit;
}

// Obtener estado actual
$stmt = $conn->prepare("SELECT activo FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    header("Location: usuarios.php?error=Usuario no encontrado");
    exit;
}

$usuario = $resultado->fetch_assoc();
$activo_actual = (bool)$usuario['activo'];
$nuevo_estado = $activo_actual ? 0 : 1;

$stmt->close();

// Actualizar estado
$stmt = $conn->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
$stmt->bind_param("ii", $nuevo_estado, $id_usuario);

if ($stmt->execute()) {
    $accion = $nuevo_estado ? "activado" : "desactivado";
    header("Location: usuarios.php?success=Usuario $accion correctamente");
} else {
    header("Location: usuarios.php?error=Error al actualizar el estado del usuario");
}
?>
