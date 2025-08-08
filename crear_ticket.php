crear_ticket.php

<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'agente') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$titulo = $descripcion = $categoria = $prioridad = "";
$referencia_falla = isset($_GET['referencia']) ? intval($_GET['referencia']) : null;
$mensaje = "";

// ✅ Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $categoria = trim($_POST['categoria']);
    $prioridad = $_POST['prioridad'];
    $referencia_falla = !empty($_POST['referencia_falla']) ? intval($_POST['referencia_falla']) : null;

    if ($titulo && $descripcion && $categoria && $prioridad) {
        $stmt = $conn->prepare("
            INSERT INTO tickets (id_usuario, titulo, descripcion, categoria, prioridad, estado, referencia_falla) 
            VALUES (?, ?, ?, ?, ?, 'abierto', ?)
        ");
        $stmt->bind_param("issssi", $_SESSION['usuario_id'], $titulo, $descripcion, $categoria, $prioridad, $referencia_falla);

        if ($stmt->execute()) {
            $ticket_id = $conn->insert_id;
            $mensaje_notif = "Nuevo ticket creado: $titulo";

            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO notificaciones (ticket_id, mensaje, prioridad) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $ticket_id, $mensaje_notif, $prioridad);
            $stmt->execute();
            $stmt->close();

            $mensaje = "✅ Ticket creado correctamente.";
            $titulo = $descripcion = $categoria = $prioridad = "";
        } else {
            $mensaje = "❌ Error al crear el ticket.";
            $stmt->close();
        }
    } else {
        $mensaje = "⚠️ Todos los campos son obligatorios.";
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Ticket</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        form { max-width: 600px; }
        label { font-weight: bold; }
        textarea, input, select {
            width: 100%;
            padding: 8px;
            margin-bottom: 12px;
        }
        button {
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #218838;
        }
        .mensaje {
            margin-bottom: 15px;
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            border-radius: 5px;
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

<h2>📝 Generar nuevo ticket <a href="/fallas_comunes_admin.php" class="boton_volver">Volver</a></h2>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form method="POST">
    <label for="titulo">Título del problema:</label>
    <input type="text" name="titulo" id="titulo" value="<?= htmlspecialchars($titulo) ?>" required>

    <label for="descripcion">Descripción detallada:</label>
    <textarea name="descripcion" id="descripcion" rows="5" required><?= htmlspecialchars($descripcion) ?></textarea>

    <label for="categoria">Categoría:</label>
    <input type="text" name="categoria" id="categoria" value="<?= htmlspecialchars($categoria) ?>" required>

    <label for="prioridad">Prioridad:</label>
    <select name="prioridad" required>
        <option value="">Selecciona</option>
        <option value="baja" <?= $prioridad === 'baja' ? 'selected' : '' ?>>Baja</option>
        <option value="media" <?= $prioridad === 'media' ? 'selected' : '' ?>>Media</option>
        <option value="alta" <?= $prioridad === 'alta' ? 'selected' : '' ?>>Alta</option>
    </select>

    <?php if ($referencia_falla): ?>
        <input type="hidden" name="referencia_falla" value="<?= $referencia_falla ?>">
        <p><em>Este ticket está relacionado con la falla común ID #<?= $referencia_falla ?></em></p>
    <?php endif; ?>

    <button type="submit">Enviar Ticket</button>
</form>

</body>
</html>