<?php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'tecnico') {
    header("Location: index.php?error=Acceso denegado");
    exit;
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: usuarios.php?msg=invalid");
    exit;
}

$id_usuario = (int)$_GET['id'];

// (Opcional) Evitar que el técnico se “elimine” a sí mismo
if ((int)$_SESSION['usuario_id'] === $id_usuario) {
    header("Location: usuarios.php?msg=self_delete_block");
    exit;
}

$conn->begin_transaction();

try {
    // 1) Insertar en usuarios_eliminados si no existe ya
    // (evita duplicados aunque hagan clic varias veces)
    $sqlCheck = "SELECT 1 FROM usuarios_eliminados WHERE id_usuario = ? LIMIT 1";
    $stmt = $conn->prepare($sqlCheck);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row();
    $stmt->close();

    if (!$exists) {
        $sqlIns = "INSERT INTO usuarios_eliminados (id_usuario) VALUES (?)";
        $stmt = $conn->prepare($sqlIns);
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $stmt->close();
    }

    // 2) Desactivar usuario
    $sqlUpd = "UPDATE usuarios SET activo = 0 WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sqlUpd);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    header("Location: usuarios.php?msg=deleted_ok");
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    header("Location: usuarios.php?msg=deleted_error");
    exit;
}
