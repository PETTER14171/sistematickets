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
    </style>
</head>
<body>

<h2>🔍 Buscar solución a una falla común</h2>

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

            <a href="crear_ticket.php?referencia=<?= $falla['id'] ?>" class="crear-ticket">🛠 No resolvió mi problema</a>
        </div>
    <?php endforeach; ?>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <p>No se encontraron coincidencias. Puedes <a href="crear_ticket.php">crear un ticket</a>.</p>
<?php endif; ?>

</body>
</html>
