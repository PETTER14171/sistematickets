<?php
require 'includes/funciones.php';
incluirTemplate ('header');
?>

<main>
    <h2>Bienvenido al Sistema de Gestión de Tickets</h2>
    <p>Accede al portal de atención para agentes, técnicos y administradores. Gestiona reportes, consulta guías de solución o da seguimiento a tus incidencias con eficiencia y rapidez.</p>
    <a href="login.php" class="btn-login">Iniciar sesión</a>
</main>

<footer>
    &copy; <?= date('Y') ?> TalkHub. Todos los derechos reservados.
</footer>

</body>
</html>
