<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}
?>

<?php
require 'includes/funciones.php';
incluirTemplate ('header');
?>


<?php
    $alerta = $conn->query("
        SELECT prioridad, mensaje, creado_en 
        FROM notificaciones 
        WHERE leido = FALSE 
        ORDER BY creado_en DESC
    ")->fetch_assoc();
?>

<main>
    <h2>🔧 Bienvenido <?= htmlspecialchars($_SESSION['nombre']) ?> (Área de TI)</h2>

    <!-- Alerta dinámica -->
    <div id="alertaDinamica"></div>

    <div class="panel-opciones">
    <a href="#" onclick="abrirModalNotificaciones()" class="btn-opcion verde">🔔 Ver notificaciones</a>
        <!-- Modal -->
        <div id="modalNotificaciones" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#00000066; z-index:9999; justify-content:center; align-items:center;">
            <div style="background:#fff; width:90%; max-width:800px; padding:20px; border-radius:8px; position:relative;">
                <button onclick="cerrarModal()" style="cursor: pointer; position:absolute; top:10px; right:15px; background:#dc3545; color:white; border:none; padding:5px 10px; border-radius:4px;">Cerrar ✖</button>
                <div id="contenidoNotificaciones">Cargando notificaciones...</div>
            </div>
        </div>

        <a href="admin_tickets.php" class="btn-opcion">📋 Ver y administrar los tickets</a>
        <a href="fallas_comunes_admin.php" class="btn-opcion">📚 Subir y editar fallas comunes</a>
        <a href="crear_usuario.php" class="btn-opcion">👤 Crear nuevos usuarios</a>
        <a href="usuarios.php" class="btn-opcion">👥 Usuarios</a>
        <a href="resetear_contraseña.php" class="btn-opcion">🔓 Cambiar contraseña</a>
        <a href="admin_biblioteca_subir.php" class="btn-opcion">📚 Administrar Biblioteca</a>
        <a href="logout.php" class="btn-opcion rojo">🚪 Cerrar sesión</a>
    </div>
</main>

<style>
@keyframes parpadeo {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
</style>

<?php 
incluirTemplate('footer');
?>
