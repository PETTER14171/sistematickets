<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'agente') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$busqueda = '';
$resultados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $busqueda = trim($_POST['busqueda']);

    $query = "SELECT * FROM fallas_comunes 
              WHERE titulo LIKE ? OR categoria LIKE ? OR palabras_clave LIKE ?
              ORDER BY creado_en DESC";

    $stmt = $conn->prepare($query);
    $like = "%$busqueda%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}else {
    // Si no hay búsqueda, mostrar todas
    $query = "SELECT * FROM fallas_comunes ORDER BY id DESC";
    $resultados = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Centro de Autoservicio</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h2 { color: #333; }
        form { margin-bottom: 20px; }
        .falla {
            border: 1px solid #ccc;
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        .falla h3 { margin-top: 0; }
        .crear-ticket {
            display: inline-block;
            margin-top: 10px;
            background: #ffc107;
            color: #000;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
        }
        .crear-ticket:hover {
            background: #e0a800;
        }

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

<h2>🔍 Buscar solución a una falla común <a href="/fallas_comunes_admin.php" class="boton_volver">Volver</a></h2>

<form method="POST">
    <input type="text" name="busqueda" placeholder="Ej: impresora, VPN, Outlook..." value="<?= htmlspecialchars($busqueda) ?>" required>
    <button type="submit">Buscar</button>
</form>

<?php if ($resultados): ?>
    <h3>Resultados encontrados:</h3>
    <?php foreach ($resultados as $falla): ?>
        <div class="falla">
            <h3><?= htmlspecialchars($falla['titulo']) ?></h3>
            <strong>Categoría:</strong> <?= htmlspecialchars($falla['categoria']) ?><br><br>
            
            <strong>Descripción:</strong>
            <p><?= nl2br(htmlspecialchars($falla['descripcion'])) ?></p>

            <strong>Pasos para solucionarlo:</strong>
            <p><?= nl2br(htmlspecialchars($falla['pasos_solucion'])) ?></p>

            <?php if (!empty($falla['multimedia'])): ?>
                <?php
                    $archivo = $falla['multimedia'];
                    $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
                    $ruta = 'fallamultimedia/' . $archivo;
                ?>
                <div style="margin-top: 10px;">
                    <?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <img src="<?= $ruta ?>" alt="Multimedia de la falla" style="max-width: 100%; border-radius: 5px; margin-top: 10px;">
                    <?php elseif (in_array($extension, ['mp4', 'webm', 'ogg'])): ?>
                        <video controls style="width: 100%; border-radius: 5px; margin-top: 10px;">
                            <source src="<?= $ruta ?>" type="video/<?= $extension ?>">
                            Tu navegador no soporta videos HTML5.
                        </video>
                    <?php else: ?>
                        <p>🔗 <a href="<?= $ruta ?>" target="_blank">Ver archivo adjunto</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <a href="crear_ticket.php?referencia=<?= $falla['id'] ?>" class="crear-ticket">🛠 No resolvió mi problema</a>
        </div>
    <?php endforeach; ?>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <p>No se encontraron coincidencias. Puedes <a href="crear_ticket.php">crear un ticket</a>.</p>
<?php endif; ?>

</body>
</html>
