<?php
include __DIR__ . '/../includes/config/conexion.php';
$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $campana = trim($_POST['campana']);
    $puesto = trim($_POST['puesto']);
    $estacion = trim($_POST['estacion']);
    $correo = trim($_POST['correo']);
    $password = $_POST['password'];
    $rol = $_POST['rol'];

    if (empty($nombre) || empty($correo) || empty($password) || empty($rol) || empty($campana) || empty($puesto) || empty($puesto)) {
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
                INSERT INTO usuarios (nombre, correo, campana, puesto, estacion, contraseña, rol, activo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("sssssss", $nombre, $campana, $puesto, $estacion, $correo, $password_segura, $rol);

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
<?php
    require_once __DIR__ . '/../includes/funciones.php';
    incluirTemplate('head', [
        'page_title' => 'Crear Ususario',
        'page_desc'  => 'Panel para la creacion de Usuarios'
    ]);
    incluirTemplate('header');
?>

<main>
    <div class="centrat-titulo_boton">
        <h3>👤 Crear nuevo usuario</h3>
        <a href="panel_tecnico.php" class="btn-1 btn-volver">← Volver</a>
    </div>
        <?php if ($mensaje): ?>
            <p><?= htmlspecialchars($mensaje) ?></p>
        <?php endif; ?>

    <section class="login seccion">
        <form action="" method="POST">
            <label>Nombre:</label>
            <input type="text" name="nombre" required>

            <label>Campaña:</label>
            <input type="text" name="campana" required>

            <label>Puesto:</label>
            <input type="text" name="puesto" required>

            <label>Estacion:</label>
            <input type="text" name="estacion" required>

            <label>Correo:</label>
            <input type="email" name="correo" required>

            <label>Contraseña:</label>
            <input type="password" name="password" required>

            <label>Rol:</label>
            <select name="rol" required>
                <option value="">Seleccionar rol</option>
                <option value="agente">Agente</option>
                <option value="tecnico">Técnico</option>
                <option value="admin">Administrador</option>
            </select>

            <button type="submit">Crear Usuario</button>
        </form>
    </section>
</main>
<?php 
incluirTemplate('footer');
?>
