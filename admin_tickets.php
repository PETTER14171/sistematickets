<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$stmt = $conn->prepare("
    SELECT t.*, u.nombre AS nombre_agente, f.titulo AS titulo_falla
    FROM tickets t
    JOIN usuarios u ON t.id_usuario = u.id
    LEFT JOIN fallas_comunes f ON t.referencia_falla = f.id
    ORDER BY t.creado_en DESC
");
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Tickets</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5; }
        h2 { color: #333; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }

        th { background-color: #e8f1ff; }
        tr:hover { background-color: #f1f1f1; }

        .estado-abierto { color: green; font-weight: bold; }
        .estado-en_proceso { color: orange; font-weight: bold; }
        .estado-resuelto { color: blue; font-weight: bold; }
        .estado-cerrado { color: gray; font-weight: bold; }

        .boton {
            background-color: #007bff;
            color: white;
            padding: 6px 10px;
            text-decoration: none;
            border-radius: 4px;
        }

        .boton:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

<h2>📋 Administración de Tickets</h2>

<?php if (count($tickets) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Agente</th>
                <th>Categoría</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Falla relacionada</th>
                <th>Creado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td>#<?= $t['id'] ?></td>
                    <td><?= htmlspecialchars($t['titulo']) ?></td>
                    <td><?= htmlspecialchars($t['nombre_agente']) ?></td>
                    <td><?= htmlspecialchars($t['categoria']) ?></td>
                    <td><?= ucfirst($t['prioridad']) ?></td>
                    <td class="estado-<?= $t['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $t['estado'])) ?></td>
                    <td><?= $t['titulo_falla'] ? htmlspecialchars($t['titulo_falla']) : '-' ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($t['creado_en'])) ?></td>
                    <td>
                        <a href="responder_ticket.php?id=<?= $t['id'] ?>" class="boton">Responder</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No hay tickets registrados aún.</p>
<?php endif; ?>

</body>
</html>
