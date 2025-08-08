<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';


if ($_SESSION['rol'] !== 'agente') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

// Obtener los tickets creados por el usuario actual
$id_usuario = $_SESSION['usuario_id'];

$stmt = $conn->prepare("
    SELECT t.*, f.titulo AS titulo_falla
    FROM tickets t
    LEFT JOIN fallas_comunes f ON t.referencia_falla = f.id
    WHERE t.id_usuario = ?
    ORDER BY t.creado_en DESC
");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Tickets</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h2 { color: #333; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }
        th { background-color: #f2f2f2; }
        .estado-abierto { color: green; font-weight: bold; }
        .estado-en_proceso { color: orange; font-weight: bold; }
        .estado-resuelto { color: blue; font-weight: bold; }
        .estado-cerrado { color: gray; font-weight: bold; }
        
        .boton_volver {
            background-color: #0056b3;
            color: white;
            padding: 6px 10px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 17px;
        }

        .boton_volver:hover {
            background-color: #dc3545;
        }
    </style>
</head>
<body>

<h2>📋 Mis tickets generados <a href="/fallas_comunes_admin.php" class="boton_volver">Volver</a></h2>

<?php if (count($tickets) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Título</th>
                <th>Categoría</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Relacionado con falla común</th>
                <th>Creado en</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?= htmlspecialchars($ticket['titulo']) ?></td>
                    <td><?= htmlspecialchars($ticket['categoria']) ?></td>
                    <td><?= ucfirst($ticket['prioridad']) ?></td>
                    <td class="estado-<?= $ticket['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $ticket['estado'])) ?></td>
                    <td>
                        <?php if ($ticket['referencia_falla']): ?>
                            <?= htmlspecialchars($ticket['titulo_falla']) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($ticket['creado_en'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No has generado ningún ticket aún.</p>
<?php endif; ?>

</body>
</html> 