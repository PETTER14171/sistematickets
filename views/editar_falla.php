<?php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: index.php?error=Acceso denegado");
    exit;
}

$id_falla = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mensaje = "";

// Obtener datos actuales
$stmt = $conn->prepare("SELECT * FROM fallas_comunes WHERE id = ?");
$stmt->bind_param("i", $id_falla);
$stmt->execute();
$resultado = $stmt->get_result();
$falla = $resultado->fetch_assoc();

if (!$falla) {
    die("Falla no encontrada.");
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $pasos = trim($_POST['pasos_solucion']);
    $categoria = trim($_POST['categoria']);
    $palabras_clave = trim($_POST['palabras_clave']);
    $multimedia_actual = $falla['multimedia'];
    $nuevo_archivo = $multimedia_actual;

    // Validar y procesar nuevo archivo si se cargó
    if (!empty($_FILES['multimedia']['name'])) {
        $nombre_original = $_FILES['multimedia']['name'];
        $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'mov'];

        if (!in_array($ext, $tipos_permitidos)) {
            $mensaje = "⚠️ Tipo de archivo no permitido.";
        } else {
            $nombre_unico = uniqid('media_') . '.' . $ext;
            $ruta_destino = __DIR__ . '/fallamultimedia/' . $nombre_unico; // RUTA CORRECTA ✅

            if (!is_dir(dirname($ruta_destino))) {
                mkdir(dirname($ruta_destino), 0755, true);
            }

            if (move_uploaded_file($_FILES['multimedia']['tmp_name'], $ruta_destino)) {
                $nuevo_archivo = $nombre_unico;

                // Eliminar archivo anterior si existía
                $ruta_anterior = __DIR__ . '/fallamultimedia/' . $multimedia_actual;
                if ($multimedia_actual && file_exists($ruta_anterior)) {
                    unlink($ruta_anterior);
                }
            } else {
                $mensaje = "⚠️ Error al subir el nuevo archivo.";
            }
        }
    }


    if ($mensaje === "" && $titulo && $descripcion && $pasos && $categoria && $palabras_clave) {
        $stmt = $conn->prepare("
            UPDATE fallas_comunes
            SET titulo = ?, descripcion = ?, pasos_solucion = ?, categoria = ?, palabras_clave = ?, multimedia = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssssi", $titulo, $descripcion, $pasos, $categoria, $palabras_clave, $nuevo_archivo, $id_falla);

        if ($stmt->execute()) {
            header("Location: fallas_comunes_admin.php?editado=1");
            // Refrescar datos actualizados
            $falla = [
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'pasos_solucion' => $pasos,
                'categoria' => $categoria,
                'palabras_clave' => $palabras_clave,
                'multimedia' => $nuevo_archivo
            ];
        } else {
            $mensaje = "❌ Error al actualizar.";
        }
    } elseif ($mensaje === "") {
        $mensaje = "⚠️ Todos los campos son obligatorios.";
    }
}
?>

<?php
    require_once __DIR__ . '/../includes/funciones.php';
    incluirTemplate('head', [
        'page_title' => 'Editar Falla',
        'page_desc'  => 'Panel para que el Tecnico edite una documentacion'
    ]);
    incluirTemplate('header');
?>
<main class="admin-tickets-page falla-edit-page">
  <a href="fallas_comunes_admin.php" class="btn-1 btn-volver ticket-detail__back">← Volver</a>
    <section class="admin-tickets__inner">
        <!-- Header -->
        <header class="admin-tickets__header">
            <div class="admin-tickets__title-group">
                <h1 class="admin-tickets__title">Editar guía de falla común</h1>
                <p class="admin-tickets__subtitle">
                    Actualiza la descripción, pasos y recursos multimedia de esta guía.
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
                            value="<?= htmlspecialchars($falla['titulo'] ?? '') ?>"
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
                        ><?= htmlspecialchars($falla['descripcion'] ?? '') ?></textarea>
                        <label for="descripcion" class="field__label">Descripción</label>
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
                        ><?= htmlspecialchars($falla['pasos_solucion'] ?? '') ?></textarea>
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
                                value="<?= htmlspecialchars($falla['categoria'] ?? '') ?>"
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
                                value="<?= htmlspecialchars($falla['palabras_clave'] ?? '') ?>"
                                required
                            >
                            <label for="palabras_clave" class="field__label">
                                Palabras clave (separadas por coma)
                            </label>
                        </div>
                    </section>
                </section>

                <!-- Vista del archivo actual (si existe) -->
                <?php if (!empty($falla['multimedia'])): ?>
                    <?php
                        $ext = strtolower(pathinfo($falla['multimedia'], PATHINFO_EXTENSION));
                        $isVideo = in_array($ext, ['mp4', 'webm', 'mov']);
                        $src = "../fallamultimedia/" . $falla['multimedia'];
                    ?>
                    <section class="contenido-bloque multimedia-falla form-falla__block">
                        <article class="media-card">
                            <header class="media-card__header">
                                <h2 class="media-card__title">Archivo actual</h2>
                                <p class="media-card__subtitle">
                                    Puedes mantener este archivo o reemplazarlo por uno nuevo.
                                </p>
                            </header>
                            <div class="media-card__preview">
                                <?php if ($isVideo): ?>
                                    <video
                                        src="<?= htmlspecialchars($src) ?>"
                                        controls
                                        class="media-card__video"
                                    ></video>
                                <?php else: ?>
                                    <img
                                        src="<?= htmlspecialchars($src) ?>"
                                        alt="Media actual"
                                        class="media-card__image"
                                    >
                                <?php endif; ?>
                            </div>
                            <footer class="media-card__footer">
                                <span class="media-card__filename">
                                    <?= htmlspecialchars($falla['multimedia']) ?>
                                </span>
                            </footer>
                        </article>
                    </section>
                <?php endif; ?>

                <!-- Reemplazar multimedia -->
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
                            <span class="uploader__title">Reemplazar multimedia (opcional)</span>
                            <span class="uploader__hint">
                                Arrastra una imagen o video aquí, o haz clic para seleccionar.
                            </span>
                        </label>
                    </div>
                </section>

                <!-- Botón -->
                <div class="form-falla__actions">
                    <button class="btn-primary" type="submit">
                        Guardar cambios
                    </button>
                </div>
            </form>
        </section>
    </section>
</main>

<?php 
incluirTemplate('footer');
?>
