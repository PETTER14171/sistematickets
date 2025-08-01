<?php
include 'verificar_sesion.php';
include 'includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$mensaje = "";

// Si se envió el formulario de reseteo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = intval($_POST['usuario_id']);
    $nueva_contraseña = $_POST['nueva_contraseña'];

    if ($usuario_id && $nueva_contraseña) {
        $hash = password_hash($nueva_contraseña, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET contraseña = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $usuario_id);

        if ($stmt->execute()) {
            $mensaje = "✅ Contraseña actualizada correctamente.";
        } else {
            $mensaje = "❌ Error al actualizar la contraseña.";
        }
    } else {
        $mensaje = "⚠️ Debes seleccionar un usuario y escribir una nueva contraseña.";
    }
}

// Obtener todos los usuarios activos
$usuarios = $conn->query("SELECT id, nombre, correo, rol, activo FROM usuarios ORDER BY nombre ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resetear Contraseña de Usuario</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; }
        h2 { margin-bottom: 20px; }
        form {
            max-width: 500px;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }
        select, input {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
        }
        button {
            background: #ffc107;
            color: #000;
            border: none;
            padding: 10px 16px;
            border-radius: 4px;
            font-weight: bold;
        }
        .mensaje {
            margin-bottom: 15px;
            background: #e2f7e2;
            color: #155724;
            padding: 10px;
            border: 1px solid #c3e6cb;
            border-left: 5px solid #28a745;
            border-radius: 5px;
        }
        .form-label {
            font-weight: bold;
        }
    </style>
</head>
<body>

<h2>🔐 Resetear contraseña de usuario</h2>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form method="POST">
    <label class="form-label">Seleccionar usuario:</label>
    <select name="usuario_id" required>
        <option value="">-- Selecciona un usuario --</option>
        <?php foreach ($usuarios as $u): ?>
            <option value="<?= $u['id'] ?>">
                <?= htmlspecialchars($u['nombre']) ?> (<?= $u['correo'] ?>) - <?= ucfirst($u['rol']) ?> <?= $u['activo'] ? '' : '[Inactivo]' ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="form-label">Nueva contraseña:</label>
    <input type="password" name="nueva_contraseña" required>

    <button type="submit">Actualizar contraseña</button>
</form>

</body>
</html>
