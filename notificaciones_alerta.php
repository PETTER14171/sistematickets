<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    exit;
}

$alerta = $conn->query("
    SELECT prioridad, mensaje, creado_en 
    FROM notificaciones 
    WHERE leido = FALSE 
    ORDER BY creado_en DESC 
    LIMIT 1
")->fetch_assoc();

if ($alerta) {
    echo json_encode([
        'mensaje' => $alerta['mensaje'],
        'prioridad' => $alerta['prioridad'],
        'creado_en' => $alerta['creado_en']
    ]);
} else {
    echo json_encode(null);
}
