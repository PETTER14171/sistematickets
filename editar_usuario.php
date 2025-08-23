<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$id_usuario = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mensaje = "";

// Obtener usuario actual
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

if (!$usuario) {
    die("❌ Usuario no encontrado.");
}

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $rol = $_POST['rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    $campana = trim($_POST['campana']);
    $puesto = trim($_POST['puesto']);
    $estacion = trim($_POST['estacion']);

    // Validar correo único (excepto si es el mismo)
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE correo = ? AND id != ?");
    $stmt->bind_param("si", $correo, $id_usuario);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $mensaje = "⚠️ Ya existe otro usuario con este correo.";
    } else {
        $stmt = $conn->prepare("UPDATE usuarios 
            SET nombre = ?, correo = ?, rol = ?, activo = ?, campana = ?, puesto = ?, estacion = ?
            WHERE id = ?");

        $stmt->bind_param("sssisssi", $nombre, $correo, $rol, $activo, $campana, $puesto, $estacion, $id_usuario);

        if ($stmt->execute()) {
            $mensaje = "✅ Usuario actualizado correctamente.";
            // Actualizar datos locales
            $usuario = [
                'nombre' => $nombre,
                'correo' => $correo,
                'rol' => $rol,
                'activo' => $activo,
                'campana' => $campana,
                'puesto' => $puesto,
                'estacion' => $estacion
            ];
        } else {
            $mensaje = "❌ Error al actualizar el usuario.";
        }
    }
}
?>

<?php
require 'includes/funciones.php';
incluirTemplate ('header');
?>

<h2>✏️ Editar Usuario  <a href="/usuarios.php" class="volver">Volver</a></h2>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form class="form-falla" method="POST">
  <!-- Nombre -->
  <section class="contenido-bloque nombre-falla">
    <div class="field">
      <input
        id="nombre"
        class="field__input"
        type="text"
        name="nombre"
        placeholder=" "
        value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>"
        required
      >
      <label for="nombre" class="field__label">Nombre</label>
    </div>
  </section>

  <!-- Correo -->
  <section class="contenido-bloque correo-falla">
    <div class="field">
      <input
        id="correo"
        class="field__input"
        type="email"
        name="correo"
        placeholder=" "
        value="<?= htmlspecialchars($usuario['correo'] ?? '') ?>"
        required
      >
      <label for="correo" class="field__label">Correo electrónico</label>
    </div>
  </section>

  <!-- Rol -->
  <section class="contenido-bloque rol-falla">
    <div class="field">
      <select
        id="rol"
        class="field__input field__select"
        name="rol"
        required
      >
        <option value="" disabled>Selecciona un rol</option>
        <option value="agente"  <?= ($usuario['rol'] ?? '') === 'agente'  ? 'selected' : '' ?>>Agente</option>
        <option value="tecnico" <?= ($usuario['rol'] ?? '') === 'tecnico' ? 'selected' : '' ?>>Técnico</option>
        <option value="admin"   <?= ($usuario['rol'] ?? '') === 'admin'   ? 'selected' : '' ?>>Administrador</option>
      </select>
      <label for="rol" class="field__label">Rol</label>
    </div>
  </section>

  <!-- Campaña -->
  <section class="contenido-bloque campana-falla">
    <div class="field">
      <input
        id="campana"
        class="field__input"
        type="text"
        name="campana"
        placeholder=" "
        value="<?= htmlspecialchars($usuario['campana'] ?? '') ?>"
      >
      <label for="campana" class="field__label">Campaña</label>
    </div>
  </section>

  <!-- Puesto -->
  <section class="contenido-bloque puesto-falla">
    <div class="field">
      <input
        id="puesto"
        class="field__input"
        type="text"
        name="puesto"
        placeholder=" "
        value="<?= htmlspecialchars($usuario['puesto'] ?? '') ?>"
      >
      <label for="puesto" class="field__label">Puesto</label>
    </div>
  </section>

  <!-- Estación -->
  <section class="contenido-bloque estacion-falla">
    <div class="field">
      <input
        id="estacion"
        class="field__input"
        type="text"
        name="estacion"
        placeholder=" "
        value="<?= htmlspecialchars($usuario['estacion'] ?? '') ?>"
      >
      <label for="estacion" class="field__label">Estación</label>
    </div>
  </section>

  <!-- Usuario activo (switch) -->
  <section class="contenido-bloque activo-falla">
    <div class="switch">
      <input
        id="activo"
        class="switch__input"
        type="checkbox"
        name="activo"
        <?= !empty($usuario['activo']) ? 'checked' : '' ?>
      >
      <label for="activo" class="switch__label">
        <span class="switch__title">Usuario activo</span>
      </label>
    </div>
  </section>

  <!-- Botón -->
  <div class="form-falla__actions">
    <button class="btn-primary" type="submit">Guardar cambios</button>
  </div>
</form>


<?php 
incluirTemplate('footer');
?>
