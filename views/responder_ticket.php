<?php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';


if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: index.php?error=Acceso denegado");
    exit;
}

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mensaje = "";

// Obtener información del ticket
$stmt = $conn->prepare("
    SELECT t.*, u.nombre AS nombre_agente, f.titulo AS titulo_falla
    FROM tickets t
    JOIN usuarios u ON t.id_usuario = u.id
    LEFT JOIN fallas_comunes f ON t.referencia_falla = f.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();
$ticket = $result->fetch_assoc();

if (!$ticket) {
    die("Ticket no encontrado.");
}

// Procesar nueva respuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respuesta = isset($_POST['respuesta']) ? trim($_POST['respuesta']) : '';
    $nuevo_estado = isset($_POST['estado']) ? $_POST['estado'] : '';

    if ($respuesta && $nuevo_estado) {
        $archivo = null;

        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $archivo_nombre = basename($_FILES['archivo']['name']);
            $archivo_ruta = "adjuntos/" . uniqid() . "_" . $archivo_nombre;
            move_uploaded_file($_FILES['archivo']['tmp_name'], $archivo_ruta);
            $archivo = $archivo_ruta;
        }

        // Guardar respuesta
        $stmt = $conn->prepare("
            INSERT INTO respuestas_ticket (ticket_id, usuario_id, mensaje, archivo_adjunto)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiss", $ticket_id, $_SESSION['usuario_id'], $respuesta, $archivo);
        $stmt->execute();

        // Actualizar estado del ticket
        $stmt = $conn->prepare("UPDATE tickets SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $ticket_id);
        $stmt->execute();

        // Marcar notificaciones relacionadas como leídas
        $stmt = $conn->prepare("UPDATE notificaciones SET leido = TRUE WHERE ticket_id = ?");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();

        // Redireccionar con mensaje de éxito
        header("Location: admin_tickets.php?exito=1");
        exit;
    } else {
        $mensaje = "⚠️ Debes escribir una respuesta y elegir un estado.";
    }
}


// Obtener historial de respuestas
$stmt = $conn->prepare("
    SELECT r.*, u.nombre AS nombre_usuario
    FROM respuestas_ticket r
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.ticket_id = ?
    ORDER BY r.creado_en ASC
");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$respuestas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php
    require_once __DIR__ . '/../includes/funciones.php';
    incluirTemplate('head', [
        'page_title' => 'Responder Ticket',
        'page_desc'  => 'Panel para que el Tecnico responda ticket'
    ]);
    incluirTemplate('header');
?>


<div class="centrat-titulo_boton">
    <h3>🛠 Ticket #<?= $ticket['id'] ?>: <?= htmlspecialchars($ticket['titulo']) ?></h3>
    <a href="admin_tickets.php" class="btn-1 btn-volver">← Volver</a>
</div>

<main>
    <?php if ($mensaje): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
<div class="ticket-grid">
  <!-- Columna izquierda: Detalles -->
  <section class="contenido-bloque detalle-falla">
    <h2 class="section-title">Detalles del ticket</h2>

    <div class="detalle-kv">
      <div><span>Agente</span><strong><?= htmlspecialchars($ticket['nombre_agente']) ?></strong></div>
      <div><span>Categoría</span><strong><?= htmlspecialchars($ticket['categoria']) ?></strong></div>
      <div><span>Prioridad</span>
        <span class="chip chip--prio-<?= strtolower($ticket['prioridad']) ?>"><?= ucfirst($ticket['prioridad']) ?></span>
      </div>
      <div><span>Estado</span>
        <span class="chip chip--estado-<?= strtolower($ticket['estado']) ?>"><?= ucfirst(str_replace('_',' ',$ticket['estado'])) ?></span>
      </div>
      <div class="full"><span>Falla común</span><strong><?= $ticket['titulo_falla'] ?: '-' ?></strong></div>
    </div>

    <div class="detalle-desc">
      <label>Descripción</label>
      <div class="desc-box"><?= nl2br(htmlspecialchars($ticket['descripcion'])) ?></div>
    </div>
  </section>

  <!-- Columna derecha: Historial -->
  <section class="contenido-bloque historial-falla">
    <h2 class="section-title">💬 Historial de respuestas</h2>

    <?php if ($respuestas): ?>
      <ul class="chat">
        <?php foreach ($respuestas as $r): ?>
          <li class="chat__msg <?= strtolower($r['nombre_usuario']) === strtolower($ticket['nombre_agente']) ? 'is-agent' : 'is-tech' ?>">
            <div class="chat__bubble">
              <div class="chat__meta">
                <strong class="chat__author"><?= htmlspecialchars($r['nombre_usuario']) ?></strong>
                <time class="chat__time"><?= date('d/m/Y H:i', strtotime($r['creado_en'])) ?></time>
              </div>
              <div class="chat__text"><?= nl2br(htmlspecialchars($r['mensaje'])) ?></div>

              <?php if ($r['archivo_adjunto']): ?>
                <a class="chat__attach" href="<?= htmlspecialchars($r['archivo_adjunto']) ?>" target="_blank">📎 Ver archivo adjunto</a>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted">No hay respuestas registradas.</p>
    <?php endif; ?>
  </section>
</div>

<!-- Composer -->
<section class="contenido-bloque composer-falla">
  <h2 class="section-title">📝 Escribir nueva respuesta</h2>

  <form class="composer" method="POST" enctype="multipart/form-data">
    <div class="composer__left">
      <div class="field">
        <textarea id="respuesta" class="field__input field__textarea_2 allow-tabs" name="respuesta" rows="4" placeholder=" " required></textarea>
        <label for="respuesta" class="field__label">Mensaje</label>
      </div>
    </div>

    <div class="composer__right">
      <div class="uploader">
        <input id="archivo" class="uploader__input" type="file" name="archivo" />
        <label for="archivo" class="uploader__label">
          <span class="uploader__title">Adjuntar archivo (opcional)</span>
          <span class="uploader__hint">Imagen / video / PDF</span>
        </label>
      </div>

      <div class="field">
        <select id="estado" name="estado" class="field__input field__select" required>
          <option value="" disabled selected>Seleccionar estado</option>
          <option value="en_proceso">En proceso</option>
          <option value="resuelto">Resuelto</option>
          <option value="cerrado">Cerrado</option>
        </select>
        <label for="estado" class="field__label">Cambiar estado del ticket</label>
      </div>

      <div class="composer__actions">
        <button class="btn-primary" type="submit">Enviar</button>
      </div>
    </div>
  </form>
</section>


</main>
<?php 
incluirTemplate('footer');
?>
