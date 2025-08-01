<?php
include 'verificar_sesion.php';
include 'includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php");
    exit;
}

// Marcar como leídas
if (isset($_GET['marcar'])) {
    $conn->query("UPDATE notificaciones SET leido = TRUE WHERE leido = FALSE");
    header("Location: notificaciones.php");
    exit;
}

// Traer todas
$notifs = $conn->query("SELECT * FROM notificaciones ORDER BY creado_en DESC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Notificaciones</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ccc; }
        .no-leido { background: #ffeeba; }
        .prioridad-alta { color: #dc3545; }
        .prioridad-media { color: #ffc107; }
        .prioridad-baja { color: #17a2b8; }
    </style>
</head>
<body>
    <h2>🔔 Historial de notificaciones</h2>
    <a href="?marcar=1">✅ Marcar todas como leídas</a>

    <table>
        <thead>
            <tr>
                <th>Mensaje</th>
                <th>Prioridad</th>
                <th>Fecha</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notifs as $n): ?>
                <tr class="<?= $n['leido'] ? '' : 'no-leido' ?>">
                    <td><?= htmlspecialchars($n['mensaje']) ?></td>
                    <td class="prioridad-<?= $n['prioridad'] ?>"><?= ucfirst($n['prioridad']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($n['creado_en'])) ?></td>
                    <td><?= $n['leido'] ? 'Leído' : 'No leído' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
