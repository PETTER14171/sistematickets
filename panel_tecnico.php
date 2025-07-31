<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Técnico</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 30px;
            background-color: #f7f7f7;
        }

        h2 {
            color: #222;
        }

        .opciones {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }

        .opciones a {
            text-decoration: none;
            padding: 12px 20px;
            background-color: #007bff;
            color: white;
            font-weight: bold;
            border-radius: 6px;
            transition: background-color 0.2s ease-in-out;
            max-width: 400px;
        }

        .opciones a:hover {
            background-color: #0056b3;
        }

        .logout {
            background-color: #dc3545 !important;
        }

        .logout:hover {
            background-color: #c82333 !important;
        }
    </style>
</head>
<body>

    <h2>🔧 Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?> (Área de TI)</h2>

    <div class="opciones">
        <a href="admin_tickets.php">📋 Ver y administrar todos los tickets</a>
        <a href="fallas_comunes_admin.php">📚 Subir y editar guías de fallas comunes</a>
        <a href="crear_usuario.php">👥 Crear nuevos usuarios</a>
        <a href="resetear_contraseña.php">🔐 Resetear contraseñas de usuarios</a>
        <a href="logout.php" class="logout">🚪 Cerrar sesión</a>
    </div>

</body>
</html>
