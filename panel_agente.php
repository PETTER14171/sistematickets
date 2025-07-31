<?php
include __DIR__ . '/includes/config/verificar_sesion.php';

if ($_SESSION['rol'] !== 'agente') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Agente</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h2 { color: #333; }
        .opciones a {
            display: block;
            margin: 10px 0;
            padding: 10px;
            background-color: #e9f1ff;
            border: 1px solid #ccc;
            text-decoration: none;
            color: #115;
            border-radius: 6px;
            width: fit-content;
        }
        .opciones a:hover {
            background-color: #cde4ff;
        }
    </style>
</head>
<body>
    <h2>👋 Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?> (Agente)</h2>

    <div class="opciones">
        <a href="/autoservicio.php">🔍 Consultar soluciones comunes</a>
        <a href="crear_ticket.php">📝 Generar nuevo ticket</a>
        <a href="mis_tickets.php">📋 Ver mis tickets</a>
        <a href="logout.php">🚪 Cerrar sesión</a>
    </div>
</body>
</html>
