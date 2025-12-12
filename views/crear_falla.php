<?php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: index.php?error=Acceso denegado");
    exit;
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo         = trim($_POST['titulo'] ?? '');
    $descripcion    = trim($_POST['descripcion'] ?? '');
    $pasos          = trim($_POST['pasos_solucion'] ?? '');
    $categoria      = trim($_POST['categoria'] ?? '');
    $palabras_clave = trim($_POST['palabras_clave'] ?? '');
    $archivo_nombre = null;

    // Manejo de archivo multimedia (opcional)
    if (isset($_FILES['multimedia']) && $_FILES['multimedia']['error'] === UPLOAD_ERR_OK) {
        $nombre_original = $_FILES['multimedia']['name'];
        $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'mov'];

        if (!in_array($ext, $tipos_permitidos)) {
            $mensaje = "⚠️ Tipo de archivo no permitido ($ext).";
        } else {
            $nombre_unico  = uniqid('media_') . '.' . $ext;
            $ruta_destino  = __DIR__ . '/fallamultimedia/' . $nombre_unico;

            if (!is_dir(dirname($ruta_destino))) {
                mkdir(dirname($ruta_destino), 0755, true);
            }

            if (move_uploaded_file($_FILES['multimedia']['tmp_name'], $ruta_destino)) {
                $archivo_nombre = $nombre_unico;
            } else {
                $mensaje = "❌ No se pudo mover el archivo al destino.";
            }
        }
    } elseif (isset($_FILES['multimedia']) && $_FILES['multimedia']['error'] !== UPLOAD_ERR_NO_FILE) {
        $mensaje = "❌ Error al subir archivo: código " . $_FILES['multimedia']['error'];
    }

    // Validación de campos requeridos
    if ($mensaje === "" && $titulo && $descripcion && $pasos && $categoria && $palabras_clave) {
        $stmt = $conn->prepare("
            INSERT INTO fallas_comunes 
            (titulo, descripcion, pasos_solucion, categoria, palabras_clave, creado_por, multimedia) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssis",
            $titulo,
            $descripcion,
            $pasos,
            $categoria,
            $palabras_clave,
            $_SESSION['usuario_id'],
            $archivo_nombre
        );

        if ($stmt->execute()) {
            header("Location: fallas_comunes_admin.php?exito=1");
            exit;
        } else {
            $mensaje = "❌ Error al registrar la falla.";
        }
    } elseif ($mensaje === "") {
        $mensaje = "⚠️ Todos los campos son obligatorios (excepto multimedia).";
    }
}

require_once __DIR__ . '/../includes/funciones.php';
incluirTemplate('head', [
    'page_title' => 'Crear Falla',
    'page_desc'  => 'Panel para que el Tecnico documente una falla'
]);
incluirTemplate('header');
?>

<main class="admin-tickets-page falla-edit-page">
    <a href="fallas_comunes_admin.php" class="btn-1 btn-volver ticket-detail__back">← Volver</a>
    <section class="admin-tickets__inner">
        <!-- Header -->
        <header class="admin-tickets__header">
            <div class="admin-tickets__title-group">
                <h1 class="admin-tickets__title">Registrar nueva guía de </h1>
                <p class="admin-tickets__subtitle">
                    Documenta paso a paso la solución para que los agentes puedan consultarla en autoservicio.
                </p>
            </div>
        </header>

        <section class="admin-tickets-card">
            <?php if ($mensaje): ?>
                <div class="mensaje mensaje--inline">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>

            <form class="form-falla" method="POST" enctype="multipart/form-data">
                <!-- Título -->
                <section class="contenido-bloque titulo-falla form-falla__block">
                    <div class="field">
                        <input
                            id="titulo"
                            class="field__input"
                            type="text"
                            name="titulo"
                            placeholder=" "
                            required
                        >
                        <label for="titulo" class="field__label">Título</label>
                    </div>
                </section>

                <!-- Descripción -->
                <section class="contenido-bloque descripcion-falla form-falla__block">
                    <div class="field">
                        <textarea
                            id="descripcion"
                            class="field__input field__textarea"
                            name="descripcion"
                            rows="4"
                            placeholder=" "
                            required
                        ></textarea>
                        <label for="descripcion" class="field__label">Descripción del problema</label>
                    </div>
                </section>

                <!-- Pasos para solucionarlo -->
                <section class="contenido-bloque solucion-falla form-falla__block">
                    <div class="field">
                        <textarea
                            id="pasos_solucion"
                            class="field__input field__textarea"
                            name="pasos_solucion"
                            rows="5"
                            placeholder=" "
                            required
                        ></textarea>
                        <label for="pasos_solucion" class="field__label">Pasos para solucionarlo</label>
                    </div>
                </section>

                <!-- Categoría + Palabras clave en 2 columnas -->
                <section class="form-falla__grid-2">
                    <section class="contenido-bloque categoria-falla form-falla__block">
                        <div class="field">
                            <input
                                id="categoria"
                                class="field__input"
                                type="text"
                                name="categoria"
                                placeholder=" "
                                required
                            >
                            <label for="categoria" class="field__label">Categoría</label>
                        </div>
                    </section>

                    <section class="contenido-bloque palabraclave-falla form-falla__block">
                        <div class="field">
                            <input
                                id="palabras_clave"
                                class="field__input"
                                type="text"
                                name="palabras_clave"
                                placeholder=" "
                                required
                            >
                            <label for="palabras_clave" class="field__label">
                                Palabras clave (separadas por coma)
                            </label>
                        </div>
                    </section>
                </section>

                <!-- Multimedia opcional -->
                <section class="contenido-bloque multimedia-falla form-falla__block">
                    <div class="uploader">
                        <input
                            id="multimedia"
                            class="uploader__input"
                            type="file"
                            name="multimedia"
                            accept="image/*,video/*"
                        >
                        <label for="multimedia" class="uploader__label">
                            <span class="uploader__title">Archivo multimedia (opcional)</span>
                            <span class="uploader__hint">
                                Imagen o video (arrastrar y soltar / clic para seleccionar)
                            </span>
                        </label>
                    </div>
                </section>

                <!-- Botón -->
                <div class="form-falla__actions">
                    <button class="btn-primary" type="submit">
                        Guardar guía
                    </button>
                </div>
            </form>
        </section>
    </section>
</main>

<?php 
incluirTemplate('footer');
?>
