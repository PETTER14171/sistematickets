<?php

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php?error=Debes+iniciar+sesion");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// -------------------------------------
// 1. Consultar tickets del usuario
// -------------------------------------
$sql = "
    SELECT 
        id,
        titulo,
        descripcion,
        estado,
        prioridad,
        creado_en,
        actualizado_en
    FROM tickets
    WHERE id_usuario = ?
    ORDER BY actualizado_en DESC, creado_en DESC
    LIMIT 50
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

// -------------------------------------
// 2. Transformar resultados al formato
//    que usa el HTML ($tickets)
// -------------------------------------
$tickets = [];

while ($row = $result->fetch_assoc()) {
    // Mapear estado de BD → texto e icono
    $estado_bd = strtolower($row['estado']);
    $status      = 'Open';
    $status_key  = 'open';

    // Ajusta estos valores según tus estados reales en la tabla
    switch ($estado_bd) {
        case 'en_proceso':
        case 'en proceso':
        case 'in_progress':
            $status = 'In Progress';
            $status_key = 'in-progress';
            break;
        case 'cerrado':
        case 'resuelto':
        case 'resuelto_ok':
            $status = 'Resolved';
            $status_key = 'resolved';
            break;
        case 'abierto':
        default:
            $status = 'Open';
            $status_key = 'open';
            break;
    }

    // Título
    $title = $row['titulo'];

    // Preview: un resumen cortito de la descripción
    $preview = $row['descripcion'] ?? '';
    $preview = trim($preview);
    if (mb_strlen($preview) > 70) {
        $preview = mb_substr($preview, 0, 70) . '…';
    }

    // Fecha "bonita"
    $fecha_ref = $row['actualizado_en'] ?: $row['creado_en'];
    $date_label = '';
    if ($fecha_ref) {
        $dt = new DateTime($fecha_ref);
        $hoy = new DateTime('today');

        if ($dt->format('Y-m-d') === $hoy->format('Y-m-d')) {
            $date_label = 'Today';
        } else {
            // Formato tipo "Apr 20" (en inglés). Si quieres en español, podemos ajustar.
            $date_label = $dt->format('M d');
        }
    }

    $tickets[] = [
        'id'         => (int)$row['id'],
        'status'     => $status,
        'status_key' => $status_key,
        'title'      => $title,
        'preview'    => $preview,
        'date'       => $date_label,
    ];
}

$stmt->close();
?>

<aside class="ticket-sidebar" aria-label="Lista de tickets">
    <div class="ticket-sidebar__header">
        <h2 class="ticket-sidebar__title">Mis Tickets</h2>
    </div>

    <ul class="ticket-list">
        <?php foreach ($tickets as $ticket): ?>
            <li class="ticket-item" tabindex="0" aria-label="Ticket <?= htmlspecialchars($ticket['status']) ?>">
                <div class="ticket-item__status">
                    <span class="status-dot status-dot--<?= htmlspecialchars($ticket['status_key']) ?>"></span>
                    <span class="ticket-item__status-text">
                        <?= htmlspecialchars($ticket['status']) ?>
                    </span>
                </div>
                <div class="ticket-item__body">
                    <p class="ticket-item__title">
                        <?= htmlspecialchars($ticket['title']) ?>
                    </p>
                    <p class="ticket-item__preview">
                        <?= htmlspecialchars($ticket['preview']) ?>
                    </p>
                </div>
                <div class="ticket-item__meta">
                    <span class="ticket-item__date">
                        <?= htmlspecialchars($ticket['date']) ?>
                    </span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>