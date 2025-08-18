<?php
include __DIR__ . '/includes/config/verificar_sesion.php';

if ($_SESSION['rol'] !== 'agente') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}
?>
<?php
require 'includes/funciones.php';
incluirTemplate ('header');
?>

<main>
    <h2>👋 Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?> (Agente)</h2>

    <div class="panel-opciones">
        <a href="/autoservicio.php"  class="btn-opcion">🔍 Consultar soluciones comunes</a>
        <a href="crear_ticket.php"  class="btn-opcion">📝 Generar nuevo ticket</a>
        <a href="mis_tickets.php"  class="btn-opcion">📋 Ver mis tickets</a>
        <a href="logout.php"  class="btn-opcion rojo">🚪 Cerrar sesión</a>
    </div>
</main>
<?php 
incluirTemplate('footer');
?>
