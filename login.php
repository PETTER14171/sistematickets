<?php
session_start();

// Si ya hay sesión iniciada, redirige automáticamente al panel correcto
if (isset($_SESSION['rol'])) {
    switch ($_SESSION['rol']) {
        case 'admin':
            header("Location: panel_admin.php");
            exit;
        case 'tecnico':
            header("Location: panel_tecnico.php");
            exit;
        case 'agente':
            header("Location: panel_agente.php");
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
</head>
<body>
    <h2>Iniciar Sesión</h2>

    <?php if (isset($_GET['error'])): ?>
        <p style="color:red;"><?= htmlspecialchars($_GET['error']) ?></p>
    <?php endif; ?>

    <form action="procesar_login.php" method="POST">
        <label>Correo:</label><br>
        <input type="email" name="correo" required><br><br>

        <label>Contraseña:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Iniciar sesión</button>
    </form>
</body>
</html>
