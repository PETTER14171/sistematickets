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

<?php
require 'includes/funciones.php';
incluirTemplate ('header');
?>

    <?php if (isset($_GET['error'])): ?>
        <p style="color:red;"><?= htmlspecialchars($_GET['error']) ?></p>
    <?php endif; ?>

    <h2>Iniciar Sesión</h2>

    <section class="login seccion">
        <form action="procesar_login.php" method="POST">
            <label>Correo:</label>
            <input type="email" name="correo" required>

            <label>Contraseña:</label>
            <input type="password" name="password" required>

            <button type="submit">Iniciar sesión</button>
        </form>
    </section>


<?php 
incluirTemplate('footer');
?>
