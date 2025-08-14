<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$id_usuario = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mensaje = "";

// Obtener usuario actual
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

if (!$usuario) {
    die("❌ Usuario no encontrado.");
}

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $rol = $_POST['rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    $campana = trim($_POST['campana']);
    $puesto = trim($_POST['puesto']);
    $estacion = trim($_POST['estacion']);

    // Validar correo único (excepto si es el mismo)
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo = ? AND id != ?");
    $stmt->bind_param("si", $correo, $id_usuario);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $mensaje = "⚠️ Ya existe otro usuario con este correo.";
    } else {
        $stmt = $conn->prepare("UPDATE usuarios 
            SET nombre = ?, correo = ?, rol = ?, activo = ?, campana = ?, puesto = ?, estacion = ?
            WHERE id = ?");

        $stmt->bind_param("sssisssi", $nombre, $correo, $rol, $activo, $campana, $puesto, $estacion, $id_usuario);

        if ($stmt->execute()) {
            $mensaje = "✅ Usuario actualizado correctamente.";
            // Actualizar datos locales
            $usuario = [
                'nombre' => $nombre,
                'correo' => $correo,
                'rol' => $rol,
                'activo' => $activo,
                'campana' => $campana,
                'puesto' => $puesto,
                'estacion' => $estacion
            ];
        } else {
            $mensaje = "❌ Error al actualizar el usuario.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 25px;
        }

        form {
            max-width: 600px;
            margin-top: 20px;
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }

        input, select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
        }

        button {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
        }

        .mensaje {
            padding: 10px;
            background: #e2f7e2;
            border: 1px solid #a0d6a0;
            margin-bottom: 15px;
            color: #2d662d;
            border-radius: 5px;
        }

        .volver {
            background-color: #343a40;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
        }

        .volver:hover {
            background-color: #000;
        }
    </style>
</head>
<body>

<h2>✏️ Editar Usuario</h2>
<a href="usuarios.php" class="volver">← Volver a Usuarios</a>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form method="POST">
    <label>Nombre:</label>
    <input type="text" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>

    <label>Correo electrónico:</label>
    <input type="email" name="correo" value="<?= htmlspecialchars($usuario['correo']) ?>" required>

    <label>Rol:</label>
    <select name="rol" required>
        <option value="agente" <?= $usuario['rol'] === 'agente' ? 'selected' : '' ?>>Agente</option>
        <option value="tecnico" <?= $usuario['rol'] === 'tecnico' ? 'selected' : '' ?>>Técnico</option>
        <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
    </select>

    <label>Campaña:</label>
    <input type="text" name="campana" value="<?= htmlspecialchars($usuario['campana']) ?>">

    <label>Puesto:</label>
    <input type="text" name="puesto" value="<?= htmlspecialchars($usuario['puesto']) ?>">

    <label>Estación:</label>
    <input type="text" name="estacion" value="<?= htmlspecialchars($usuario['estacion']) ?>">

    <label><input type="checkbox" name="activo" <?= $usuario['activo'] ? 'checked' : '' ?>> Usuario activo</label><br><br>

    <button type="submit">Guardar cambios</button>
</form>

</body>
</html>
