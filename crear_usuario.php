<?php
include __DIR__ . '/includes/config/conexion.php';
$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $password = $_POST['password'];
    $rol = $_POST['rol'];

    if (empty($nombre) || empty($correo) || empty($password) || empty($rol)) {
        $mensaje = "Todos los campos son obligatorios.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $mensaje = "El correo ya está registrado.";
        } else {
            $password_segura = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO usuarios (nombre, correo, contraseña, rol, activo) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("ssss", $nombre, $correo, $password_segura, $rol);

            if ($stmt->execute()) {
                $mensaje = "✅ Usuario creado exitosamente.";
            } else {
                $mensaje = "❌ Error al crear usuario.";
            }
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Usuario</title>
</head>
<body>
    <h2>Crear nuevo usuario</h2>

    <?php if ($mensaje): ?>
        <p><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>

    <form action="" method="POST">
        <label>Nombre:</label><br>
        <input type="text" name="nombre" required><br><br>

        <label>Correo:</label><br>
        <input type="email" name="correo" required><br><br>

        <label>Contraseña:</label><br>
        <input type="password" name="password" required><br><br>

        <label>Rol:</label><br>
        <select name="rol" required>
            <option value="">Seleccionar rol</option>
            <option value="agente">Agente</option>
            <option value="tecnico">Técnico</option>
            <option value="admin">Administrador</option>
        </select><br><br>

        <button type="submit">Crear Usuario</button>
    </form>
</body>
</html>
