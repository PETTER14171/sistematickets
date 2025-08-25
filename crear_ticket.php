<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'agente') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$titulo = $descripcion = $categoria = "";
$referencia_falla = isset($_GET['referencia']) ? intval($_GET['referencia']) : null;
$mensaje = "";
$usuario_id = $_SESSION['usuario_id'];

// Obtener campaña del usuario
$stmt = $conn->prepare("SELECT campana FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$campana_usuario = $usuario['campana'];
$stmt->close();

// Obtener categorías válidas según la campaña
$stmt = $conn->prepare("SELECT DISTINCT categoria FROM incidencias_prioridad WHERE campana = ?");
$stmt->bind_param("s", $campana_usuario);
$stmt->execute();
$result = $stmt->get_result();
$categorias_disponibles = [];
while ($row = $result->fetch_assoc()) {
    $categorias_disponibles[] = $row['categoria'];
}
$stmt->close();

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $categoria = trim($_POST['categoria']);
    $referencia_falla = !empty($_POST['referencia_falla']) ? intval($_POST['referencia_falla']) : null;

    if ($titulo && $descripcion && $categoria) {
        // Obtener prioridad automáticamente
        $stmt = $conn->prepare("SELECT prioridad FROM incidencias_prioridad WHERE campana = ? AND categoria = ? LIMIT 1");
        $stmt->bind_param("ss", $campana_usuario, $categoria);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $mensaje = "⚠️ No se encontró prioridad para la categoría seleccionada.";
        } else {
            $prioridad = $row['prioridad'];

            $stmt = $conn->prepare("
                INSERT INTO tickets (id_usuario, titulo, descripcion, categoria, prioridad, estado, referencia_falla) 
                VALUES (?, ?, ?, ?, ?, 'abierto', ?)
            ");
            $stmt->bind_param("issssi", $usuario_id, $titulo, $descripcion, $categoria, $prioridad, $referencia_falla);

            if ($stmt->execute()) {
                $ticket_id = $conn->insert_id;
                $mensaje_notif = "Nuevo ticket creado: $titulo";

                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO notificaciones (ticket_id, mensaje, prioridad) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $ticket_id, $mensaje_notif, $prioridad);
                $stmt->execute();
                $stmt->close();

                $mensaje = "✅ Ticket creado correctamente.";
                $titulo = $descripcion = $categoria = "";
            } else {
                $mensaje = "❌ Error al crear el ticket.";
                $stmt->close();
            }
        }
    } else {
        $mensaje = "⚠️ Todos los campos son obligatorios.";
    }
}
?>

<?php
require 'includes/funciones.php';
incluirTemplate ('header');
?>

<main>
    <section class="">
        <h1>📝 Generar nuevo ticket</h1>

        <?php if ($mensaje): ?>
            <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <div class="margin-contenido">
            <form class="formulario-ticket contenido-bloque" method="POST">
                <label for="titulo">Título del problema:</label>
                <input type="text" name="titulo" id="titulo" value="<?= htmlspecialchars($titulo) ?>" required>

                <label for="descripcion">Descripción detallada:</label>
                <textarea name="descripcion" id="descripcion" rows="5" required><?= htmlspecialchars($descripcion) ?></textarea>

                <label for="categoria">Categoría:</label>
                <select name="categoria" id="categoria" required>
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($categorias_disponibles as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $categoria ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($referencia_falla): ?>
                    <input type="hidden" name="referencia_falla" value="<?= $referencia_falla ?>">
                    <p><em>Este ticket está relacionado con la falla común ID #<?= $referencia_falla ?></em></p>
                <?php endif; ?>

                <button class="button-enviar" type="submit">Enviar Ticket</button>
            </form>
        </div>
    </section>
     <a href="/fallas_comunes_admin.php" class="btn-volver btn-1">Volver</a>
</main>

<?php 
incluirTemplate('footer');
?>
