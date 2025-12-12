<?php
// views/detalle_ticket.php

include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php?error=Debes+iniciar+sesion");
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$rol        = $_SESSION['rol'] ?? 'agente';

// ----------------------------
// Validar ID de ticket
// ----------------------------
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: ver_mis_tickets.php?error=Ticket+invalido");
    exit;
}

$ticket_id = (int)$_GET['id'];

// ----------------------------
// Cargar ticket y verificar permisos
// ----------------------------

if ($rol === 'agente') {
    $sqlTicket = "
        SELECT t.*, COALESCE(t.actualizado_en, t.creado_en) AS ultima
        FROM tickets t
        WHERE t.id = ? AND t.id_usuario = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlTicket);
    $stmt->bind_param("ii", $ticket_id, $usuario_id);
} else {
    $sqlTicket = "
        SELECT t.*, COALESCE(t.actualizado_en, t.creado_en) AS ultima
        FROM tickets t
        WHERE t.id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlTicket);
    $stmt->bind_param("i", $ticket_id);
}

$stmt->execute();
$resTicket = $stmt->get_result();
$ticket = $resTicket->fetch_assoc();
$stmt->close();

if (!$ticket) {
    incluirTemplate('head', [
        'page_title' => 'Ticket no encontrado',
        'page_desc'  => 'El ticket no existe o no tienes permiso para verlo'
    ]);
    incluirTemplate('header');
    ?>
    <main class="tickets-page">
        <section class="tickets-page__inner">
            <section class="tickets-table-card contenido-bloque">
                <p>No se encontró el ticket o no tienes permiso para verlo.</p>
                <a href="ver_mis_tickets.php" class="btn-1 btn-volver">← Volver a mis tickets</a>
            </section>
        </section>
    </main>
    <?php
    incluirTemplate('footer');
    exit;
}

// ----------------------------
// Acciones POST (mensaje nuevo / apelar)
// ----------------------------
$alert_msg  = '';
$alert_type = 'success'; // success | error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion  = $_POST['accion'] ?? '';
        // <<< NUEVO: detectar si es AJAX >>>
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Ruta física para archivos adjuntos
    $uploadDir = dirname(__DIR__) . '/adjuntos/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    if ($accion === 'mensaje_nuevo') {
        $mensaje = trim($_POST['mensaje'] ?? '');
        $archivo_nombre = null;

        if ($mensaje === '') {
            $alert_msg  = '⚠️ El mensaje no puede estar vacío.';
            $alert_type = 'error';
        } else {
            // Procesar archivo adjunto opcional
            if (!empty($_FILES['archivo_adjunto']['name']) && is_uploaded_file($_FILES['archivo_adjunto']['tmp_name'])) {
                $original = $_FILES['archivo_adjunto']['name'];
                $ext = pathinfo($original, PATHINFO_EXTENSION);
                $safeExt = $ext ? ('.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext)) : '';
                $archivo_nombre = 'resp_' . $ticket_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . $safeExt;

                $destino = $uploadDir . $archivo_nombre;
                if (!move_uploaded_file($_FILES['archivo_adjunto']['tmp_name'], $destino)) {
                    $archivo_nombre = null; // no guardar nombre si falló
                }
            }

            // Insertar mensaje en respuestas_ticket
            $sqlMsg = "
                INSERT INTO respuestas_ticket (ticket_id, usuario_id, mensaje, archivo_adjunto, creado_en)
                VALUES (?, ?, ?, ?, NOW())
            ";
            $stmt = $conn->prepare($sqlMsg);
            $stmt->bind_param("iiss", $ticket_id, $usuario_id, $mensaje, $archivo_nombre);

            if ($stmt->execute()) {
                $insert_id = $stmt->insert_id;
                $stmt->close();

                // Actualizar última fecha del ticket
                $sqlUpd = "UPDATE tickets SET actualizado_en = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sqlUpd);
                $stmt->bind_param("i", $ticket_id);
                $stmt->execute();
                $stmt->close();

                // ===========================
                //  RESPUESTA AJAX
                // ===========================
                if ($isAjax) {
                    // Construir el mismo HTML que usas en el hilo
                    $es_mio = true;
                    $autor_label = ($rol === 'tecnico' || $rol === 'admin') ? 'Soporte' : 'Tú';
                    $fecha_msg = (new DateTime())->format('M j, Y H:i');
                    $has_file  = !empty($archivo_nombre);
                    $file_url  = $has_file 
                        ? '/adjuntos/' . rawurlencode(basename($archivo_nombre))
                        : '';

                    $body_html = nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));

                    $msg_class = $es_mio ? 'ticket-msg--mine' : 'ticket-msg--other';

                    $attachment_html = '';
                    if ($has_file) {
                        $attachment_html = '
                            <p class="ticket-msg__attachment">
                                📎 <a href="' . $file_url . '" download>'
                                    . htmlspecialchars($archivo_nombre, ENT_QUOTES, 'UTF-8') .
                                '</a>
                            </p>';
                    }
                    $html = '
                            <article class="ticket-msg ' . $msg_class . '">
                                <header class="ticket-msg__meta">
                                    <span class="ticket-msg__author">'
                                        . htmlspecialchars($autor_label, ENT_QUOTES, 'UTF-8') .
                                    '</span>
                                    <span class="ticket-msg__time">'
                                        . htmlspecialchars($fecha_msg, ENT_QUOTES, 'UTF-8') .
                                    '</span>
                                </header>
                                <p class="ticket-msg__body">'
                                    . $body_html .
                                '</p>'
                                . $attachment_html .
                            '</article>';
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode([
                        'ok'      => true,
                        'html'    => $html,
                        'last_id' => $insert_id,
                    ]);
                    exit;
                }

                // ===========================
                //  FLUJO NORMAL (no AJAX)
                // ===========================
                header("Location: detalle_ticket.php?id={$ticket_id}&msg=mensaje_ok");
                exit;

                exit;
            } else {
                $alert_msg  = '❌ Error al guardar el mensaje.';
                $alert_type = 'error';
                $stmt->close();
            }
        }
    }

    if ($accion === 'apelar') {
        $estado_bd = strtolower(trim($ticket['estado']));

        if (!in_array($estado_bd, ['resuelto', 'cerrado'], true)) {
            $alert_msg  = '⚠️ Solo puedes apelar tickets resueltos o cerrados.';
            $alert_type = 'error';
        } else {
            // Reabrir ticket
            $sqlUpd = "
                UPDATE tickets
                SET estado = 'abierto', actualizado_en = NOW()
                WHERE id = ?
            ";
            $stmt = $conn->prepare($sqlUpd);
            $stmt->bind_param("i", $ticket_id);

            if ($stmt->execute()) {
                $stmt->close();

                $mensaje_apelacion = 'El usuario ha apelado el ticket y solicita revisión nuevamente.';

                $sqlMsg = "
                    INSERT INTO respuestas_ticket (ticket_id, usuario_id, mensaje, archivo_adjunto, creado_en)
                    VALUES (?, ?, ?, NULL, NOW())
                ";
                $stmt = $conn->prepare($sqlMsg);
                $stmt->bind_param("iis", $ticket_id, $usuario_id, $mensaje_apelacion);
                $stmt->execute();
                $stmt->close();

                header("Location: detalle_ticket.php?id={$ticket_id}&msg=apelar_ok");
                exit;
            } else {
                $alert_msg  = '❌ Error al apelar el ticket.';
                $alert_type = 'error';
                $stmt->close();
            }
        }
    }
}

// Mensajes GET (PRG)
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'mensaje_ok') {
        $alert_msg  = '✅ Mensaje enviado correctamente.';
        $alert_type = 'success';
    } elseif ($_GET['msg'] === 'apelar_ok') {
        $alert_msg  = '✅ Tu apelación ha sido registrada y el ticket se ha reabierto.';
        $alert_type = 'success';
    }
}

// ----------------------------
// Volver a cargar ticket (por si cambió estado)
// ----------------------------
if ($rol === 'agente') {
    $sqlTicket = "
        SELECT t.*, COALESCE(t.actualizado_en, t.creado_en) AS ultima
        FROM tickets t
        WHERE t.id = ? AND t.id_usuario = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlTicket);
    $stmt->bind_param("ii", $ticket_id, $usuario_id);
} else {
    $sqlTicket = "
        SELECT t.*, COALESCE(t.actualizado_en, t.creado_en) AS ultima
        FROM tickets t
        WHERE t.id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sqlTicket);
    $stmt->bind_param("i", $ticket_id);
}
$stmt->execute();
$resTicket = $stmt->get_result();
$ticket = $resTicket->fetch_assoc();
$stmt->close();

// ----------------------------
// Mapear estado y prioridad
// ----------------------------
$estado_bd    = strtolower(trim($ticket['estado']));
$status_label = 'Abierto';
$status_key   = 'open';

switch ($estado_bd) {
    case 'en_proceso':
        $status_label = 'En proceso';
        $status_key   = 'in-progress';
        break;
    case 'resuelto':
        $status_label = 'Resuelto';
        $status_key   = 'resolved';
        break;
    case 'cerrado':
        $status_label = 'Cerrado';
        $status_key   = 'closed';
        break;
    case 'abierto':
    default:
        $status_label = 'Abierto';
        $status_key   = 'open';
        break;
}

$prio_bd        = strtolower(trim($ticket['prioridad'] ?? ''));
$priority_label = 'Media';
$priority_key   = 'medium';


$fecha_creado = $ticket['creado_en'] ? (new DateTime($ticket['creado_en']))->format('M j, Y H:i') : '';
$fecha_ultima = $ticket['ultima']    ? (new DateTime($ticket['ultima']))   ->format('M j, Y H:i') : '';

// ----------------------------
// Evidencia inicial del ticket (campo multimedia)
// ----------------------------
$evidencia_inicial = null;
if (!empty($ticket['multimedia'])) {
    $evidencia_inicial = basename($ticket['multimedia']);
}

// ----------------------------
// Cargar respuestas (mensajes) + adjuntos
// ----------------------------
$sqlMsgs = "
    SELECT r.id, r.usuario_id, r.mensaje, r.archivo_adjunto, r.creado_en,
           u.nombre, u.rol
    FROM respuestas_ticket r
    INNER JOIN usuarios u ON u.id = r.usuario_id
    WHERE r.ticket_id = ?
    ORDER BY r.creado_en ASC
";
$stmt = $conn->prepare($sqlMsgs);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$resMsgs = $stmt->get_result();
$mensajes = $resMsgs->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// calcular último ID actual
$last_msg_id = 0;
foreach ($mensajes as $m) {
    if ($m['id'] > $last_msg_id) {
        $last_msg_id = (int)$m['id'];
    }
}

// =====================================
//  AJAX GET: obtener mensajes nuevos
//  /detalle_ticket.php?id=XX&ajax=1&action=list&last_id=YY
// =====================================
if (
    isset($_GET['ajax']) && $_GET['ajax'] === '1' &&
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    ($_GET['action'] ?? '') === 'list'
) {
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

    $html = '';
    $new_last_id = $last_id;

    foreach ($mensajes as $msg) {
        if ((int)$msg['id'] <= $last_id) {
            continue;
        }

        $es_mio = ((int)$msg['usuario_id'] === $usuario_id);
        $autor_label = $es_mio
            ? 'Tú'
            : ($msg['rol'] === 'tecnico' || $msg['rol'] === 'admin' ? 'Soporte' : $msg['nombre']);
        $fecha_msg = (new DateTime($msg['creado_en']))->format('M j, Y H:i');
        $has_file  = !empty($msg['archivo_adjunto']);
        $file_url  = $has_file ? '/adjuntos/' . rawurlencode(basename($msg['archivo_adjunto'])) : '';

        $body_html = nl2br(htmlspecialchars($msg['mensaje'], ENT_QUOTES, 'UTF-8'));
        $msg_class = $es_mio ? 'ticket-msg--mine' : 'ticket-msg--other';

        $attachment_html = '';
        if ($has_file) {
            $attachment_html = '
                <p class="ticket-msg__attachment">
                    📎 <a href="' . $file_url . '" download>'
                        . htmlspecialchars($msg['archivo_adjunto'], ENT_QUOTES, 'UTF-8') .
                    '</a>
                </p>';
        }

        $html .= '
<article class="ticket-msg ' . $msg_class . '">
    <header class="ticket-msg__meta">
        <span class="ticket-msg__author">'
            . htmlspecialchars($autor_label, ENT_QUOTES, 'UTF-8') .
        '</span>
        <span class="ticket-msg__time">'
            . htmlspecialchars($fecha_msg, ENT_QUOTES, 'UTF-8') .
        '</span>
    </header>
    <p class="ticket-msg__body">'
        . $body_html .
    '</p>'
    . $attachment_html .
'</article>';

        if ((int)$msg['id'] > $new_last_id) {
            $new_last_id = (int)$msg['id'];
        }
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok'      => true,
        'html'    => $html,
        'last_id' => $new_last_id,
    ]);
    exit;
}


// Evidencias separadas por rol
$evidencias_tecnico = [];
$evidencias_usuario = [];

foreach ($mensajes as $msg) {
    if (empty($msg['archivo_adjunto'])) {
        continue;
    }

    $item = [
        'archivo' => basename($msg['archivo_adjunto']),
        'nombre'  => $msg['archivo_adjunto'],
        'creado'  => $msg['creado_en'],
        'autor'   => $msg['nombre'],
        'rol'     => $msg['rol'],
    ];

    if (in_array($msg['rol'], ['tecnico', 'admin'], true)) {
        $evidencias_tecnico[] = $item;
    } else {
        // agente / usuario
        $evidencias_usuario[] = $item;
    }
}


// ----------------------------
// Render
// ----------------------------
incluirTemplate('head', [
    'page_title' => 'Detalle Ticket #' . $ticket_id,
    'page_desc'  => 'Detalle e interacción del ticket'
]);

incluirTemplate('header');
?>

<main class="tickets-page">
    <section class="tickets-page__inner ticket-detail">

        <a href="ver_mis_tickets.php" class="btn-1 btn-volver ticket-detail__back">← Volver a mis tickets</a>

        <section class="ticket-detail__layout">
            <!-- INFO TICKET -->
            <article class="ticket-detail__info contenido-bloque">
                <header class="ticket-detail__header">
                    <div>
                        <h1 class="ticket-detail__title">Titulo:
                            <br>
                            <span class="ticket-detail__meta">
                                <?= htmlspecialchars($ticket['titulo'], ENT_QUOTES, 'UTF-8') ?>    
                            </span>
                        </h1>
                        <h1 class="ticket-detail__title">Id:
                            <span class="ticket-detail__meta"> #<?= (int)$ticket_id ?></span>
                        </h1>
                        <h1 class="ticket-detail__title top_margin">Creado:
                            <br>
                            <span class="ticket-detail__meta">
                                <?= htmlspecialchars($fecha_creado, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </h1> 
                        <h1 class="ticket-detail__title">Actualización:
                            <br>
                            <span class="ticket-detail__meta">
                                <?php if ($fecha_ultima && $fecha_ultima !== $fecha_creado): ?>
                                    <?= htmlspecialchars($fecha_ultima, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </span>
                        </h1>
                    </div>
                    <div class="ticket-detail__pills">
                        <span class="status-pill status-pill--<?= htmlspecialchars($status_key, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </header>

                <section class="ticket-detail__grid">
                    <div class="ticket-detail__section">
                        <h2 class="ticket-detail__section-title">Descripción</h2>
                        <p class="ticket-detail__description">
                            <?= nl2br(htmlspecialchars($ticket['descripcion'], ENT_QUOTES, 'UTF-8')) ?>
                        </p>
                    </div>

                    <div class="ticket-detail__section ticket-detail__section--meta">
                        <h2 class="ticket-detail__section-title">Información</h2>
                        <dl class="ticket-detail__meta-list">
                            <div>
                                <dt>Categoría</dt>
                                <dd><?= htmlspecialchars($ticket['categoria'], ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                            <div>
                                <dt>Estatus</dt>
                                <dd><?= htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') ?></dd>
                            </div>
                            <?php if (!empty($ticket['referencia_falla'])): ?>
                                <div>
                                    <dt>Referencia de falla</dt>
                                    <dd>#<?= (int)$ticket['referencia_falla'] ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <?php if (in_array($status_key, ['resolved', 'closed'], true)): ?>
                            <form method="POST" class="ticket-detail__appeal-form">
                                <input type="hidden" name="accion" value="apelar">
                                <button type="submit" class="btn-1 btn-apelar">
                                    🔄 Apelar / Reabrir ticket
                                </button>
                                <p class="form-help">
                                    Usa esta opción si consideras que el problema no ha sido resuelto correctamente.
                                </p>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- EVIDENCIAS -->
                <section class="ticket-detail__section">
                    <h2 class="ticket-detail__section-title">Evidencias del Técnico</h2>

                    <?php if (empty($evidencias_tecnico)): ?>
                        <p class="ticket-detail__empty">Aún no hay evidencias del técnico.</p>
                    <?php else: ?>
                        <ul class="ticket-detail__files">
                            <?php foreach ($evidencias_tecnico as $file): ?>
                                <?php
                                    $url = '/adjuntos/' . rawurlencode($file['archivo']);
                                    $fechaEv = $file['creado']
                                        ? (new DateTime($file['creado']))->format('M j, Y H:i')
                                        : '';
                                ?>
                                <li class="ticket-file">
                                    <div class="ticket-file__icon">📎</div>
                                    <div class="ticket-file__body">
                                        <p class="ticket-file__name">
                                            <?= htmlspecialchars($file['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <p class="ticket-file__meta">
                                            Subido por <?= htmlspecialchars($file['autor'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($fechaEv): ?>
                                                · <?= htmlspecialchars($fechaEv, ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="ticket-file__actions">
                                        <a href="<?= $url ?>" class="btn-ghost-small" download>
                                            Descargar
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="ticket-detail__section">
                    <h2 class="ticket-detail__section-title">Evidencias del Usuario</h2>

                    <?php if (!$evidencia_inicial && empty($evidencias_usuario)): ?>
                        <p class="ticket-detail__empty">Aún no hay evidencias adjuntas.</p>
                    <?php else: ?>
                        <ul class="ticket-detail__files">
                            <?php if ($evidencia_inicial): ?>
                                <?php $path_ini = '/adjuntos/' . rawurlencode($evidencia_inicial); ?>
                                <li class="ticket-file">
                                    <div class="ticket-file__icon">📎</div>
                                    <div class="ticket-file__body">
                                        <p class="ticket-file__name">
                                            <?= htmlspecialchars($evidencia_inicial, ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <p class="ticket-file__meta">
                                            Evidencia inicial del ticket
                                        </p>
                                    </div>
                                    <div class="ticket-file__actions">
                                        <a href="<?= $path_ini ?>" class="btn-ghost-small" download>
                                            Descargar
                                        </a>
                                    </div>
                                </li>
                            <?php endif; ?>

                            <?php foreach ($evidencias_usuario as $file): ?>
                                <?php
                                    $url = '/adjuntos/' . rawurlencode($file['archivo']);
                                    $fechaEv = $file['creado']
                                        ? (new DateTime($file['creado']))->format('M j, Y H:i')
                                        : '';
                                ?>
                                <li class="ticket-file">
                                    <div class="ticket-file__icon">📎</div>
                                    <div class="ticket-file__body">
                                        <p class="ticket-file__name">
                                            <?= htmlspecialchars($file['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                        <p class="ticket-file__meta">
                                            Subido por <?= htmlspecialchars($file['autor'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($fechaEv): ?>
                                                · <?= htmlspecialchars($fechaEv, ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="ticket-file__actions">
                                        <a href="<?= $url ?>" class="btn-ghost-small" download>
                                            Descargar
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

            </article>

            <!-- HILO DE MENSAJES -->
            <aside class="ticket-detail__thread contenido-bloque">
                <header class="ticket-detail__thread-header">
                    <h2 class="ticket-detail__section-title">Mensajes</h2>
                </header>

                <div class="ticket-thread__messages" id="ticketMessages" data-last-id="<?= (int)$last_msg_id ?>">
                    <?php if (empty($mensajes)): ?>
                        <p class="ticket-detail__empty">
                            Aún no hay mensajes. Usa el formulario de abajo para escribir el primero.
                        </p>
                    <?php else: ?>
                        <?php foreach ($mensajes as $msg): ?>
                            <?php
                                $es_mio = ((int)$msg['usuario_id'] === $usuario_id);
                                $autor_label = $es_mio ? 'Tú' : ($msg['rol'] === 'tecnico' || $msg['rol'] === 'admin' ? 'Soporte' : $msg['nombre']);
                                $fecha_msg = (new DateTime($msg['creado_en']))->format('M j, Y H:i');
                                $has_file  = !empty($msg['archivo_adjunto']);
                                $file_url  = $has_file ? '/adjuntos/' . rawurlencode(basename($msg['archivo_adjunto'])) : '';
                            ?>
                            <article class="ticket-msg <?= $es_mio ? 'ticket-msg--mine' : 'ticket-msg--other' ?>">
                                <header class="ticket-msg__meta">
                                    <span class="ticket-msg__author">
                                        <?= htmlspecialchars($autor_label, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="ticket-msg__time">
                                        <?= htmlspecialchars($fecha_msg, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </header>
                                <p class="ticket-msg__body">
                                    <?= nl2br(htmlspecialchars($msg['mensaje'], ENT_QUOTES, 'UTF-8')) ?>
                                </p>
                                <?php if ($has_file): ?>
                                    <p class="ticket-msg__attachment">
                                        📎 <a href="<?= $file_url ?>" download>
                                            <?= htmlspecialchars($msg['archivo_adjunto'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <footer class="ticket-thread__footer">
                    <?php if ($alert_msg): ?>
                        <div class="ticket-alert ticket-alert--<?= $alert_type === 'error' ? 'error' : 'success' ?>">
                            <?= htmlspecialchars($alert_msg, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="ticket-thread__form" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="mensaje_nuevo">
                        <label class="form-label" for="mensaje">Escribe un mensaje</label>
                        <textarea
                            id="mensaje"
                            name="mensaje"
                            class="form-control form-control--textarea"
                            rows="3"
                            placeholder="Describe actualizaciones, dudas o comentarios sobre este ticket…"
                            required
                        ></textarea>

                        <div class="form-field">
                            <span class="form-label">Adjuntar archivo (opcional)</span>
                            <label class="file-input" for="archivo_adjunto">
                                <span class="file-input__icon">📎</span>
                                <span class="file-input__text">Elegir archivo</span>
                                <input
                                    type="file"
                                    id="archivo_adjunto"
                                    name="archivo_adjunto"
                                    class="file-input__native"
                                >
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                Enviar mensaje
                            </button>
                        </div>
                    </form>
                </footer>
            </aside>
        </section>
    </section>
</main>
<?php 
    incluirTemplate('footer');
?>