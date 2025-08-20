<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';


if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
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
require 'includes/funciones.php';
incluirTemplate ('header');
?>

<h2>🛠 Ticket #<?= $ticket['id'] ?>: <?= htmlspecialchars($ticket['titulo']) ?> <a href="/panel_tecnico.php" class="volver">Volver</a></h2>

<main>
    <?php if ($mensaje): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <div class="grid-respuesta">
        <section class="contenido-bloque">
            <div class="detalle">
                <h1>Detalles del ticket</h1>
                <p><strong>Agente:</strong> <?= htmlspecialchars($ticket['nombre_agente']) ?></p>
                <p><strong>Categoría:</strong> <?= htmlspecialchars($ticket['categoria']) ?></p>
                <p><strong>Prioridad:</strong> <?= ucfirst($ticket['prioridad']) ?></p>
                <p><strong>Estado actual:</strong> <?= ucfirst(str_replace('_', ' ', $ticket['estado'])) ?></p>
                <p><strong>Falla común asociada:</strong> <?= $ticket['titulo_falla'] ?: '-' ?></p>
                <p><strong>Descripción:</strong><br><?= nl2br(htmlspecialchars($ticket['descripcion'])) ?></p>
            </div>
        </section>
        <section class="contenido-bloque">
            <div class="historial">
                <h1>💬 Historial de respuestas</h1>
                <?php if ($respuestas): ?>
                    <?php foreach ($respuestas as $r): ?>
                        <div class="respuesta">
                            <strong><?= htmlspecialchars($r['nombre_usuario']) ?>:</strong>
                            <p><?= nl2br(htmlspecialchars($r['mensaje'])) ?></p>
                            <?php if ($r['archivo_adjunto']): ?>
                                <a href="<?= htmlspecialchars($r['archivo_adjunto']) ?>" target="_blank">📎 Ver archivo adjunto</a>
                            <?php endif; ?>
                            <p style="font-size: small; color: gray;"><?= date('d/m/Y H:i', strtotime($r['creado_en'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No hay respuestas registradas.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <section class="contenido-bloque">
        <div class="respuesta-form">
            <h1>📝 Escribir nueva respuesta</h1>
            <form method="POST" enctype="multipart/form-data">
                <label>Respuesta:</label>
                <textarea name="respuesta" rows="5" required></textarea>

                <label>Adjuntar archivo (opcional):</label>
                <input type="file" name="archivo">

                <label>Cambiar estado del ticket:</label>
                <select name="estado" required>
                    <option value="">Seleccionar estado</option>
                    <option value="en_proceso">En proceso</option>
                    <option value="resuelto">Resuelto</option>
                    <option value="cerrado">Cerrado</option>
                </select>

                <button type="submit">Enviar respuesta</button>
            </form>
        </div>
    </section>
</main>
<?php 
incluirTemplate('footer');
?>
