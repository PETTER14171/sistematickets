<?php
// views/notificaciones_alerta.php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'tecnico') {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

// Total no leídas
$sqlCount = "SELECT COUNT(*) AS c FROM notificaciones WHERE leido = 0";
$row = $conn->query($sqlCount)?->fetch_assoc();
$unread = (int)($row['c'] ?? 0);

if ($unread <= 0) {
    echo json_encode([
        'ok' => true,
        'unread' => 0,
        'top_priority' => null,
        'latest' => null
    ]);
    exit;
}

// Prioridad más alta (alta > media > baja)
$sqlTop = "
    SELECT prioridad
    FROM notificaciones
    WHERE leido = 0
    ORDER BY FIELD(prioridad, 'alta','media','baja') ASC
    LIMIT 1
";
$top = $conn->query($sqlTop)?->fetch_assoc();
$topPriority = $top['prioridad'] ?? 'media';

// Última notificación NO leída (para detectar novedades / mensaje)
$sqlLatest = "
    SELECT id, ticket_id, mensaje, prioridad, creado_en
    FROM notificaciones
    WHERE leido = 0
    ORDER BY id DESC
    LIMIT 1
";
$latest = $conn->query($sqlLatest)?->fetch_assoc();

echo json_encode([
    'ok' => true,
    'unread' => $unread,
    'top_priority' => $topPriority,
    'latest' => $latest ? [
        'id' => (int)$latest['id'],
        'ticket_id' => (int)($latest['ticket_id'] ?? 0),
        'mensaje' => $latest['mensaje'] ?? '',
        'prioridad' => $latest['prioridad'] ?? 'media',
        'creado_en' => $latest['creado_en'] ?? null,
    ] : null,
]);
