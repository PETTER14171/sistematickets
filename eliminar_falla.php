<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$id_falla = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_falla <= 0) {
    die("ID de falla inválido.");
}

// Buscar la guía y su archivo multimedia
$stmt = $conn->prepare("SELECT multimedia FROM fallas_comunes WHERE id = ?");
$stmt->bind_param("i", $id_falla);
$stmt->execute();
$result = $stmt->get_result();
$falla = $result->fetch_assoc();

if (!$falla) {
    die("Falla no encontrada.");
}

// Eliminar archivo multimedia si existe
if ($falla['multimedia']) {
    $ruta_archivo = __DIR__ . '/../fallamultimedia/' . $falla['multimedia'];
    if (file_exists($ruta_archivo)) {
        unlink($ruta_archivo);
    }
}

// Eliminar el registro de la base de datos
$stmt = $conn->prepare("DELETE FROM fallas_comunes WHERE id = ?");
$stmt->bind_param("i", $id_falla);

if ($stmt->execute()) {
    header("Location: fallas_comunes_admin.php?msg=eliminado");
    exit;
} else {
    die("Error al eliminar la falla.");
}
