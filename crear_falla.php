<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $pasos = trim($_POST['pasos_solucion']);
    $categoria = trim($_POST['categoria']);
    $palabras_clave = trim($_POST['palabras_clave']);
    $archivo_nombre = null;

    if (isset($_FILES['multimedia']) && $_FILES['multimedia']['error'] === UPLOAD_ERR_OK) {
        $nombre_original = $_FILES['multimedia']['name'];
        $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'mov'];

        if (!in_array($ext, $tipos_permitidos)) {
            $mensaje = "⚠️ Tipo de archivo no permitido ($ext).";
        } else {
            $nombre_unico = uniqid('media_') . '.' . $ext;
            $ruta_destino = __DIR__ . '/fallamultimedia/' . $nombre_unico;

            if (!is_dir(dirname($ruta_destino))) {
                mkdir(dirname($ruta_destino), 0755, true); // intenta crear si no existe
            }

            if (move_uploaded_file($_FILES['multimedia']['tmp_name'], $ruta_destino)) {
                $archivo_nombre = $nombre_unico;
            } else {
                $mensaje = "❌ No se pudo mover el archivo al destino: $ruta_destino";
            }
        }
    } elseif (isset($_FILES['multimedia']) && $_FILES['multimedia']['error'] !== UPLOAD_ERR_NO_FILE) {
        $mensaje = "❌ Error al subir archivo: código " . $_FILES['multimedia']['error'];
    }


    if ($mensaje === "" && $titulo && $descripcion && $pasos && $categoria && $palabras_clave) {
        $stmt = $conn->prepare("
            INSERT INTO fallas_comunes 
            (titulo, descripcion, pasos_solucion, categoria, palabras_clave, creado_por, multimedia) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssis", $titulo, $descripcion, $pasos, $categoria, $palabras_clave, $_SESSION['usuario_id'], $archivo_nombre);

        if ($stmt->execute()) {
            header("Location: fallas_comunes_admin.php?exito=1");
        } else {
            $mensaje = "❌ Error al registrar la falla.";
        }
    } elseif ($mensaje === "") {
        $mensaje = "⚠️ Todos los campos son obligatorios (esxcepto multimedia).";
    }
}
?>

<?php
require 'includes/funciones.php';
incluirTemplate ('header');
?>

<main>
    <h2>➕ Registrar nueva guía de falla común <a href="/fallas_comunes_admin.php" class="volver">Volver</a></h2>

    <?php if ($mensaje): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <section class="contenido-bloque">
        <form method="POST" enctype="multipart/form-data">
            <label>Título:</label>
            <input type="text" name="titulo" required>

            <label>Descripción del problema:</label>
            <textarea name="descripcion" rows="4" required></textarea>

            <label>Pasos para solucionarlo:</label>
            <textarea name="pasos_solucion" rows="5" required></textarea>

            <label>Categoría:</label>
            <input type="text" name="categoria" required>

            <label>Palabras clave (separadas por coma):</label>
            <input type="text" name="palabras_clave" required>

            <label>Archivo multimedia (imagen o video):</label>
            <input type="file" name="multimedia" accept="image/*,video/*">

            <button type="submit">Guardar guía</button>
        </form>
    </section>
</main>

<?php 
incluirTemplate('footer');
?>
