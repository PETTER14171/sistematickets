<?php
require 'includes/funciones.php';
incluirTemplate ('header');
?>

<main class="contenedor">

    <section class="contenido-bloque contenido-1">
        <h1>Bienvenido al Sistema de Gestión de Tickets</h1>
        <p>Accede al portal de atención para agentes, técnicos y administradores. Gestiona reportes, consulta guías de solución o da seguimiento a tus incidencias con eficiencia y rapidez.</p>
        <a href="login.php" class="btn-1">Iniciar sesión</a>
    </section>

</main>

<footer>
    &copy; <?= date('Y') ?> TalkHub. Todos los derechos reservados.
</footer>

</body>
</html>
