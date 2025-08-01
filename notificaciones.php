<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    exit("Acceso denegado");
}

// Marcar como leídas (respuesta JSON para AJAX)
if (isset($_GET['marcar']) && $_GET['marcar'] == 1) {
    $conn->query("UPDATE notificaciones SET leido = TRUE WHERE leido = FALSE");
    echo json_encode(['success' => true]);
    exit;
}

// Traer todas
$notifs = $conn->query("SELECT * FROM notificaciones ORDER BY creado_en DESC")->fetch_all(MYSQLI_ASSOC);
?>

<h2>🔔 Historial de notificaciones</h2>
<button onclick="marcarNotificacionesLeidas()" style="background:#ffc107; border:none; padding:6px 10px; border-radius:4px; margin-bottom:10px;">✅ Marcar todas como leídas</button>

<table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr>
            <th style="border:1px solid #ccc; padding:8px;">Mensaje</th>
            <th style="border:1px solid #ccc; padding:8px;">Prioridad</th>
            <th style="border:1px solid #ccc; padding:8px;">Fecha</th>
            <th style="border:1px solid #ccc; padding:8px;">Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($notifs as $n): ?>
            <tr style="background:<?= $n['leido'] ? '#f9f9f9' : '#fff3cd' ?>">
                <td style="border:1px solid #ccc; padding:8px;"><?= htmlspecialchars($n['mensaje']) ?></td>
                <td style="border:1px solid #ccc; padding:8px;"><?= ucfirst($n['prioridad']) ?></td>
                <td style="border:1px solid #ccc; padding:8px;"><?= date('d/m/Y H:i', strtotime($n['creado_en'])) ?></td>
                <td style="border:1px solid #ccc; padding:8px;"><?= $n['leido'] ? 'Leído' : 'No leído' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
