<?php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';


if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: index.php?error=Acceso denegado");
    exit;
}

$mensaje = "";

// Si se envió el formulario de reseteo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = intval($_POST['usuario_id']);
    $nueva_contraseña = $_POST['nueva_contraseña'];

    if ($usuario_id && $nueva_contraseña) {
        $hash = password_hash($nueva_contraseña, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET contraseña = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $usuario_id);

        if ($stmt->execute()) {
            $mensaje = "✅ Contraseña actualizada correctamente.";
        } else {
            $mensaje = "❌ Error al actualizar la contraseña.";
        }
    } else {
        $mensaje = "⚠️ Debes seleccionar un usuario y escribir una nueva contraseña.";
    }
}

// Obtener todos los usuarios activos
$usuarios = $conn->query("SELECT id, nombre, correo, rol, activo FROM usuarios ORDER BY nombre ASC")->fetch_all(MYSQLI_ASSOC);
?>

<?php
    require_once __DIR__ . '/../includes/funciones.php';
    incluirTemplate('head', [
        'page_title' => 'Cambiar contraseña',
        'page_desc'  => 'Panel para que el Tecnico cambie contraseñas'
    ]);
    incluirTemplate('header');
?>
<main class="admin-tickets-page">
    <a href="panel_tecnico.php" class="btn-1 btn-volver ticket-detail__back">← Volver</a>
    <?php if ($mensaje): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <section class="seccion  bloque-resetear">
        <form class="form-resetear" method="POST">
            <label class="form-label">Seleccionar usuario:</label>
            <select class="select-resetear" name="usuario_id" required>
                <option value="">-- Selecciona un usuario --</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?= $u['id'] ?>">
                        <?= htmlspecialchars($u['nombre']) ?> (<?= $u['correo'] ?>) - <?= ucfirst($u['rol']) ?> <?= $u['activo'] ? '' : '[Inactivo]' ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="form-label">Nueva contraseña:</label>
            <input class="input-resetear" type="password" name="nueva_contraseña" required>

            <button class="button-resetear" type="submit">Actualizar contraseña</button>
        </form>
    </section>
</main>
<?php 
incluirTemplate('footer');
?>
