<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$stmt = $conn->prepare("
    SELECT f.*, u.nombre AS autor
    FROM fallas_comunes f
    JOIN usuarios u ON f.creado_por = u.id
    ORDER BY f.creado_en DESC
");
$stmt->execute();
$fallas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Fallas Comunes</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 25px; }
        h2 { color: #333; }
        .crear-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 16px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            vertical-align: top;
        }

        th { background-color: #e8f1ff; }

        .acciones a {
            margin-right: 10px;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 4px;
            color: white;
        }

        .editar { background-color: #007bff; }
        .eliminar { background-color: #dc3545; }
    </style>
</head>
<body>

<h2>📚 Gestión de Fallas Comunes</h2>

<a href="crear_falla.php" class="crear-btn">➕ Nueva Falla Común</a>

<?php if (count($fallas) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Título</th>
                <th>Categoría</th>
                <th>Palabras clave</th>
                <th>Descripción</th>
                <th>Pasos de solución</th>
                <th>Autor</th>
                <th>Creado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fallas as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['titulo']) ?></td>
                    <td><?= htmlspecialchars($f['categoria']) ?></td>
                    <td><?= htmlspecialchars($f['palabras_clave']) ?></td>
                    <td><?= nl2br(htmlspecialchars($f['descripcion'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($f['pasos_solucion'])) ?></td>
                    <td><?= htmlspecialchars($f['autor']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($f['creado_en'])) ?></td>
                    <td class="acciones">
                        <a href="editar_falla.php?id=<?= $f['id'] ?>" class="editar">✏️ Editar</a>
                        <a href="eliminar_falla.php?id=<?= $f['id'] ?>" class="eliminar" onclick="return confirm('¿Eliminar esta guía?')">🗑 Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No hay guías registradas aún.</p>
<?php endif; ?>

</body>
</html>
