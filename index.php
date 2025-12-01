<?php 
    session_start();


    require 'includes/funciones.php';

    incluirTemplate('head', [
        'page_title' => 'Inicio Secion',
        'page_desc'  => 'Inicio de sesion para ingresar a tickets'
    ]);

    incluirTemplate('header');
?>


<?php
// Si ya hay sesión iniciada, redirige automáticamente al panel correcto
if (isset($_SESSION['rol'])) {
    switch ($_SESSION['rol']) {
        case 'admin':
            header("Location: views/panel_admin.php");
            exit;
        case 'tecnico':
            header("Location: views/panel_tecnico.php");
            exit;
        case 'agente':
            header("Location: views/panel_agente.php");
            exit;
    }
}
?>


    <?php if (isset($_GET['error'])): ?>
        <p style="color:red;"><?= htmlspecialchars($_GET['error']) ?></p>
    <?php endif; ?>

<main>
    <h2>Iniciar Sesión</h2>

    <section class="login seccion">
        <form action="views/procesar_login.php" method="POST">
            <label>Correo:</label>
            <input type="email" name="correo" required>

            <label>Contraseña:</label>
            <input type="password" name="password" required>

            <button type="submit">Iniciar sesión</button>
        </form>
    </section>
</main>

<?php 
incluirTemplate('footer');
?>
