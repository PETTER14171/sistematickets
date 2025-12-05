<?php

$titulo          = "";
$descripcion     = "";
$categoria       = "";
$mensaje         = "";
$referencia_falla = isset($_GET['referencia']) ? intval($_GET['referencia']) : null;

// Asegúrate de tener usuario_id en la sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php?error=Debes+iniciar+sesion");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// =============================
// OBTENER CAMPAÑA DEL USUARIO
// =============================
$stmt = $conn->prepare("SELECT campana FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

$campana_usuario = $usuario['campana'] ?? '';

/**
 * Obtener categorías válidas según la campaña
 */
$sqlCat = "
    SELECT DISTINCT categoria
    FROM incidencias_prioridad
    WHERE (campana = ?)
       OR (LOWER(campana) IN ('general','campana general'))
    ORDER BY categoria ASC
";
$stmt = $conn->prepare($sqlCat);
$stmt->bind_param("s", $campana_usuario);
$stmt->execute();
$result = $stmt->get_result();

$categorias_disponibles = [];
while ($row = $result->fetch_assoc()) {
    $categorias_disponibles[] = $row['categoria'];
}
$stmt->close();

// =============================
// PROCESAR FORMULARIO
// =============================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Puedes mantener el name="titulo" o si quieres usar name="asunto", aquí lo conciliamos:
    $titulo        = trim($_POST['titulo'] ?? $_POST['asunto'] ?? '');
    $descripcion   = trim($_POST['descripcion'] ?? '');
    $categoria     = trim($_POST['categoria'] ?? '');
    $referencia_falla = !empty($_POST['referencia_falla']) ? intval($_POST['referencia_falla']) : $referencia_falla;

    if ($titulo && $descripcion && $categoria) {
        // Obtener prioridad automáticamente según campaña + categoría
        $sqlPrio = "
            SELECT prioridad
            FROM incidencias_prioridad
            WHERE categoria = ?
              AND (
                    campana = ?
                 OR LOWER(campana) IN ('general','campana general')
              )
            ORDER BY (campana = ?) DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sqlPrio);
        $campana_lower_fallback = $campana_usuario;
        $stmt->bind_param("sss", $categoria, $campana_usuario, $campana_lower_fallback);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $mensaje = "⚠️ No se encontró prioridad para la categoría seleccionada en tu campaña ni en general.";
        } else {
            $prioridad = $row['prioridad'];

            // Insertar ticket
            $stmt = $conn->prepare("
                INSERT INTO tickets (id_usuario, titulo, descripcion, categoria, prioridad, estado, referencia_falla) 
                VALUES (?, ?, ?, ?, ?, 'abierto', ?)
            ");
            $stmt->bind_param(
                "issssi",
                $usuario_id,
                $titulo,
                $descripcion,
                $categoria,
                $prioridad,
                $referencia_falla
            );

            if ($stmt->execute()) {
                $ticket_id = $conn->insert_id;
                $stmt->close();

                // Crear notificación para admins / mesa de ayuda
                $mensaje_notif = "Nuevo ticket creado: $titulo";
                $stmt = $conn->prepare("
                    INSERT INTO notificaciones (ticket_id, mensaje, prioridad)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iss", $ticket_id, $mensaje_notif, $prioridad);
                $stmt->execute();
                $stmt->close();

                $mensaje = "✅ Ticket creado correctamente. Tu número de ticket es el #$ticket_id.";
                // Limpiar campos del formulario
                $titulo = $descripcion = $categoria = "";
                // Si quieres también limpiar referencia_falla:
                // $referencia_falla = null;
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

<!-- =============================
     UI / FORMATO NUEVO
============================== -->
<header class="ticket-main__header">
    <div class="ticket-main__title-group">
        <h1 class="ticket-main__title">Crea Nuevo Ticket</h1>
    </div>

    <nav class="ticket-main__nav" aria-label="Navegación de panel">
        <button class="nav-pill nav-pill--active" type="button">
            <span>Tickets</span>
        </button>
        <button class="nav-pill" type="button" onclick="window.location.href='biblioteca.php'">
            <span>Librería</span>
        </button>
    </nav>
</header>

<section class="ticket-main__content">

    <?php if ($mensaje): ?>
        <div class="ticket-alert">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form class="ticket-form" method="POST" enctype="multipart/form-data" novalidate>
        <!-- Categoría -->
        <div class="form-field">
            <label class="form-label" for="categoria">Categoría</label>
            <div class="form-control-wrapper">
                <select class="form-control" name="categoria" id="categoria" required>
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($categorias_disponibles as $cat): ?>
                        <option
                            value="<?= htmlspecialchars($cat) ?>"
                            <?= ($cat === $categoria) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-control__chevron">▾</span>
            </div>
            <p class="form-help">Selecciona la categoría que mejor describa el problema.</p>
        </div>

        <!-- Título / Asunto -->
        <div class="form-field">
            <label class="form-label" for="titulo">Título del problema</label>
            <input
                class="form-control"
                type="text"
                id="titulo"
                name="titulo"
                value="<?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="Ej. No puedo acceder a mi correo corporativo"
                required
            >
        </div>

        <!-- Descripción -->
        <div class="form-field">
            <label class="form-label" for="descripcion">Descripción detallada</label>
            <textarea
                class="form-control form-control--textarea"
                id="descripcion"
                name="descripcion"
                rows="5"
                placeholder="Describe qué ocurre, cuándo empezó, qué has intentado y mensajes de error si los hay."
                required
            ><?= htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8') ?></textarea>
            <p class="form-help">
                Mientras más contexto proporciones, más rápido podremos ayudarte.
            </p>
        </div>

        <?php if ($referencia_falla): ?>
            <input type="hidden" name="referencia_falla" value="<?= (int)$referencia_falla ?>">
            <p class="form-help">
                Este ticket está relacionado con la falla común ID #<?= (int)$referencia_falla ?>.
            </p>
        <?php endif; ?>

        <!-- Adjuntos (opcional, aún no se procesa en la lógica vieja) -->
        <div class="form-field">
            <span class="form-label">Adjunto (opcional)</span>
            <label class="file-input" for="archivo">
                <span class="file-input__icon">📎</span>
                <span class="file-input__text">Adjuntar Archivo</span>
                <input
                    type="file"
                    id="archivo"
                    name="archivo"
                    class="file-input__native"
                >
            </label>
            <p class="form-help">
                Puedes adjuntar capturas de pantalla, documentos o logs relevantes.
            </p>
        </div>

        <!-- Submit -->
        <div class="form-actions">
            <button class="btn-primary" type="submit">
                Enviar Ticket
            </button>
        </div>
    </form>
</section>
