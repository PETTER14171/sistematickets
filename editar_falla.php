<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
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
            $ruta_destino = __DIR__ . '/../fallamultimedia/' . $nombre_unico;

            if (move_uploaded_file($_FILES['multimedia']['tmp_name'], $ruta_destino)) {
                $nuevo_archivo = $nombre_unico;

                // Eliminar archivo anterior si existía
                if ($multimedia_actual && file_exists(__DIR__ . '/../fallamultimedia/' . $multimedia_actual)) {
                    unlink(__DIR__ . '/../fallamultimedia/' . $multimedia_actual);
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
            $mensaje = "✅ Guía actualizada correctamente.";
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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Falla Común</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 25px; }
        form { max-width: 700px; background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #ccc; }
        textarea, input {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 4px;
        }
        .mensaje {
            padding: 10px;
            background: #e2f7e2;
            border: 1px solid #a0d6a0;
            margin-bottom: 15px;
            color: #2d662d;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<h2>✏️ Editar Guía de Falla Común</h2>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <label>Título:</label>
    <input type="text" name="titulo" value="<?= htmlspecialchars($falla['titulo']) ?>" required>

    <label>Descripción:</label>
    <textarea name="descripcion" rows="4" required><?= htmlspecialchars($falla['descripcion']) ?></textarea>

    <label>Pasos para solucionarlo:</label>
    <textarea name="pasos_solucion" rows="5" required><?= htmlspecialchars($falla['pasos_solucion']) ?></textarea>

    <label>Categoría:</label>
    <input type="text" name="categoria" value="<?= htmlspecialchars($falla['categoria']) ?>" required>

    <label>Palabras clave:</label>
    <input type="text" name="palabras_clave" value="<?= htmlspecialchars($falla['palabras_clave']) ?>" required>

    <?php if ($falla['multimedia']): ?>
        <p>Archivo actual: <strong><?= htmlspecialchars($falla['multimedia']) ?></strong></p>
        <?php
        $ext = pathinfo($falla['multimedia'], PATHINFO_EXTENSION);
        $isVideo = in_array(strtolower($ext), ['mp4', 'webm', 'mov']);
        ?>
        <?php if ($isVideo): ?>
            <video src="../fallamultimedia/<?= $falla['multimedia'] ?>" controls width="320"></video>
        <?php else: ?>
            <img src="../fallamultimedia/<?= $falla['multimedia'] ?>" width="200" alt="Media actual">
        <?php endif; ?>
    <?php endif; ?>

    <label>Reemplazar multimedia (opcional):</label>
    <input type="file" name="multimedia" accept="image/*,video/*">

    <button type="submit">Guardar cambios</button>
</form>

</body>
</html>
