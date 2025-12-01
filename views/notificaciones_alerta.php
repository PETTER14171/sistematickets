<?php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    exit;
}

$resultado = $conn->query("
    SELECT prioridad, mensaje, creado_en 
    FROM notificaciones 
    WHERE leido = FALSE 
    ORDER BY creado_en DESC
");

$alertas = [];

if ($resultado && $resultado->num_rows > 0) {
    while ($fila = $resultado->fetch_assoc()) {
        $alertas[] = [
            'mensaje' => $fila['mensaje'],
            'prioridad' => $fila['prioridad'],
            'creado_en' => $fila['creado_en']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($alertas);