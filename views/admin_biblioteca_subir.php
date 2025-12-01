<?php
require __DIR__ . '/../includes/config/verificar_sesion.php';
require __DIR__ . '/../includes/config/conexion.php';
require __DIR__ . '/../includes/funciones.php';

// 1) Solo admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'tecnico') {
    header("Location: ../index.php?error=Acceso denegado");
    exit;
}

$errores = [];
$exito   = "";

// 2) Cargar libros existentes para el selector "subir nueva versión"
$libros_existentes = [];
$res = $conn->query("SELECT id, titulo FROM libros ORDER BY creado_en DESC");
if ($res) {
    $libros_existentes = $res->fetch_all(MYSQLI_ASSOC);
}

// 3) Procesamiento del POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Modo: 'nuevo' (crear libro) o 'existente' (añadir versión)
    $modo = $_POST['modo'] ?? 'nuevo';

    // Campos comunes (si se crea libro nuevo)
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $autor       = trim($_POST['autor'] ?? '');
    $categoria   = trim($_POST['categoria'] ?? '');

    // Libro existente
    $libro_id_ex = isset($_POST['libro_id']) && ctype_digit($_POST['libro_id']) ? (int)$_POST['libro_id'] : null;

    // Validaciones básicas
    if ($modo === 'nuevo') {
        if ($titulo === '')      $errores[] = "El título es obligatorio.";
        if ($descripcion === '') $errores[] = "La descripción es obligatoria.";
    } else { // existente
        if (!$libro_id_ex)       $errores[] = "Debes seleccionar un libro existente.";
    }

    // Archivo PDF obligatorio
    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Debes seleccionar un PDF válido.";
    } else {
        // Validar MIME real y extensión
        $tmp   = $_FILES['pdf']['tmp_name'];
        $orig  = $_FILES['pdf']['name'];
        $size  = (int)$_FILES['pdf']['size'];
        $ext   = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

        // Tamaño máximo (ajusta si quieres): 50MB
        $max_bytes = 50 * 1024 * 1024;
        if ($size <= 0 || $size > $max_bytes) {
            $errores[] = "El archivo supera el tamaño permitido (máx 50MB).";
        }

        // Verificación MIME real
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmp);
        if ($mime !== 'application/pdf') {
            $errores[] = "El archivo no es un PDF válido (MIME detectado: $mime).";
        }

        // Extensión
        if ($ext !== 'pdf') {
            $errores[] = "La extensión del archivo debe ser .pdf";
        }
    }

    if (empty($errores)) {
        // 4) Preparar guardado seguro
        $baseDir = __DIR__ . '/../biblioteca';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }
        if (!is_writable($baseDir)) {
            $errores[] = "La carpeta /biblioteca no es escribible.";
        }

        // Nombre interno seguro
        $random   = bin2hex(random_bytes(8));
        $safeOrig = preg_replace('/[^A-Za-z0-9\.\-_ ]/', '_', $orig);
        $safeOrig = trim($safeOrig) === '' ? ('archivo.pdf') : $safeOrig;
        $filename = $random . '_' . $safeOrig; // e.g., a1b2c3d4_Mi_libro.pdf
        $dest     = $baseDir . '/' . $filename;

        if (empty($errores)) {
            // 5) Mover archivo
            if (!move_uploaded_file($tmp, $dest)) {
                $errores[] = "No se pudo mover el archivo subido.";
            } else {
                // 6) Guardar en DB (transacción)
                $conn->begin_transaction();
                try {
                    $usuario_id = (int)$_SESSION['usuario_id'];
                    $libro_id   = null;

                    if ($modo === 'nuevo') {
                        // Insert libro
                        $sqlLibro = "INSERT INTO libros (titulo, descripcion, categoria, autor, creado_por)
                                     VALUES (?, ?, ?, ?, ?)";
                        $st = $conn->prepare($sqlLibro);
                        $st->bind_param("ssssi", $titulo, $descripcion, $categoria, $autor, $usuario_id);
                        $st->execute();
                        if ($st->affected_rows <= 0) {
                            throw new Exception("No se pudo crear el registro del libro.");
                        }
                        $libro_id = $st->insert_id;
                    } else {
                        $libro_id = $libro_id_ex;
                    }

                    // Desactivar versiones anteriores activas (si quieres una única activa)
                    $sqlOff = "UPDATE libro_archivos SET activo = 0 WHERE libro_id = ?";
                    $stOff  = $conn->prepare($sqlOff);
                    $stOff->bind_param("i", $libro_id);
                    $stOff->execute();

                    // Insert archivo activo
                      $sqlArc = "INSERT INTO libro_archivos
                                (libro_id, nombre_archivo, nombre_original, mime_type, tamanio_bytes, subido_por, activo)
                                VALUES (?, ?, ?, ?, ?, ?, 1)";
                      $stA = $conn->prepare($sqlArc);
                      $mime_to_db = 'application/pdf';
                      $size_to_db = (int)filesize($dest);
                      $stA->bind_param("isssii", $libro_id, $filename, $orig, $mime_to_db, $size_to_db, $usuario_id);
                      $stA->execute();
                      if ($stA->affected_rows <= 0) {
                          throw new Exception("No se pudo registrar el archivo del libro.");
                      }
                    
                      $conn->commit();
                      $archivo_id = $conn->insert_id;
                      $ver_url = "/ver_pdf.php?id=".$archivo_id;

                      // PRG:
                      header("Location: admin_biblioteca_subir.php?ok=1&file=".$archivo_id);
                      exit;

                    // Marca de éxito para JS (no mostramos alerta HTML)
                    $exito = true;  
                } catch (Throwable $e) {
                    $conn->rollback();
                    // En caso de error, elimina el archivo movido
                    if (is_file($dest)) { @unlink($dest); }
                    $errores[] = "Error al guardar en la base de datos: " . $e->getMessage();
                }
            }
        }
    }
}

// Interfaz
    require_once __DIR__ . '/../includes/funciones.php';
    incluirTemplate('head', [
        'page_title' => 'Admin Bilioteca',
        'page_desc'  => 'Panel para administrar biblioteca'
    ]);
    incluirTemplate('header');

?>
<main class="biblioteca-admin">
    <div class="centrat-titulo_boton">
        <h3>📚 Panel de Biblioteca — Subir libros (PDF)</h3>
        <a href="panel_tecnico.php" class="btn-1 btn-volver">← Volver</a>
    </div>

  <?php if (!empty($errores)): ?>
    <section class="contenido-bloque">
      <h2 class="section-title">Problemas al subir</h2>
      <ul>
        <?php foreach ($errores as $e): ?>
          <li style="color:#ffb9b9;"><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <!-- Formulario -->
  <form id="form-biblio" class="form-falla" method="POST" enctype="multipart/form-data">
    <!-- Selector de modo -->
    <section class="contenido-bloque modo-falla">
      <div class="field">
        <select id="modo" name="modo" class="field__input field__select">
          <option value="nuevo"     <?= (($_POST['modo'] ?? 'nuevo') === 'nuevo') ? 'selected' : '' ?>>Crear libro nuevo</option>
          <option value="existente" <?= (($_POST['modo'] ?? '') === 'existente') ? 'selected' : '' ?>>Subir nueva versión a un libro existente</option>
        </select>
        <label for="modo" class="field__label">Modo de subida</label>
      </div>
    </section>

    <!-- Libro existente -->
    <section class="contenido-bloque libroexistente-falla" id="bloque-libro-existente" style="display:none;">
      <div class="field">
        <select id="libro_id" name="libro_id" class="field__input field__select">
          <option value="">Selecciona un libro</option>
          <?php foreach ($libros_existentes as $lx): ?>
            <option value="<?= (int)$lx['id'] ?>" <?= (isset($_POST['libro_id']) && (int)$_POST['libro_id']===(int)$lx['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($lx['titulo']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <label for="libro_id" class="field__label">Libro existente</label>
      </div>
    </section>

    <!-- Datos del libro nuevo -->
    <section class="contenido-bloque titulo-falla" id="bloque-libro-nuevo">
      <div class="field">
        <input id="titulo" class="field__input" type="text" name="titulo" placeholder=" "
               value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
        <label for="titulo" class="field__label">Título</label>
      </div>
    </section>

    <section class="contenido-bloque autor-falla" id="bloque-autor">
      <div class="field">
        <input id="autor" class="field__input" type="text" name="autor" placeholder=" "
               value="<?= htmlspecialchars($_POST['autor'] ?? '') ?>">
        <label for="autor" class="field__label">Autor (opcional)</label>
      </div>
    </section>

    <section class="contenido-bloque categoria-falla" id="bloque-categoria">
      <div class="field">
        <input id="categoria" class="field__input" type="text" name="categoria" placeholder=" "
               value="<?= htmlspecialchars($_POST['categoria'] ?? '') ?>">
        <label for="categoria" class="field__label">Categoría (opcional)</label>
      </div>
    </section>

    <section class="contenido-bloque descripcion-falla" id="bloque-descripcion">
      <div class="field">
        <textarea id="descripcion" class="field__input field__textarea" name="descripcion" rows="4" placeholder=" "><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
        <label for="descripcion" class="field__label">Descripción</label>
      </div>
    </section>

    <!-- Uploader -->
    <section class="contenido-bloque pdf-falla">
      <div class="uploader">
        <input id="pdf" class="uploader__input" type="file" name="pdf" accept="application/pdf">
        <label for="pdf" class="uploader__label">
          <span class="uploader__title">Archivo PDF</span>
          <span class="uploader__hint">Selecciona un archivo .pdf (máx 50 MB)</span>
        </label>
      </div>
      <p id="pdf-info" class="muted" style="margin-top:6px;"></p>
    </section>

    <div class="form-biblio__actions">
        <button class="btn-secondary-2" type="submit">Subir</button>
        <button class="btn-secondary-2" type="button" onclick="window.history.back()">Cancelar</button>
    </div>
  </form>
</main>

<!-- Alerta al subir libro -->
 <script>
  // Función para limpiar el formulario y volver a "Crear libro nuevo"
  function resetFormularioBiblio() {
    const form = document.getElementById('form-biblio');
    if (!form) return;
    form.reset();

    // Volver el modo a "nuevo" y refrescar visibilidad de bloques
    const modoSel = document.getElementById('modo');
    if (modoSel) {
      modoSel.value = 'nuevo';
      if (typeof aplicarModo === 'function') aplicarModo();
      // Si no tienes aplicarModo en este archivo, añade la lógica mínima:
      const bloqueExist = document.getElementById('bloque-libro-existente');
      const bloqueNuevo  = document.getElementById('bloque-libro-nuevo');
      const bloqueAutor  = document.getElementById('bloque-autor');
      const bloqueCat    = document.getElementById('bloque-categoria');
      const bloqueDesc   = document.getElementById('bloque-descripcion');
      if (bloqueExist && bloqueNuevo && bloqueAutor && bloqueCat && bloqueDesc) {
        bloqueExist.style.display = 'none';
        bloqueNuevo.style.display = 'block';
        bloqueAutor.style.display = 'block';
        bloqueCat.style.display   = 'block';
        bloqueDesc.style.display  = 'block';
      }
    }

    // Limpiar preview del archivo (si muestras nombre/tamaño)
    const infoPdf = document.getElementById('pdf-info');
    if (infoPdf) infoPdf.textContent = '';
  }

  // Dispara el SweetAlert si hubo éxito en el PHP
(function(){
  const exito  = <?= isset($exito) && $exito ? 'true' : 'false' ?>;

  if (!exito) return;

  Swal.fire({
    title: '¡Listo!',
    html: 'El libro se subió correctamente.',
    icon: 'success',
    confirmButtonText: 'Aceptar',
    allowOutsideClick: false,
    allowEscapeKey: true,
    customClass: {
      confirmButton: 'swal2-confirm-custom'
    }
  }).then(() => {
    resetFormularioBiblio();   // ← limpia el formulario para subir otro
  });
})();
</script>

<style>
/* Botones del SweetAlert acordes a tu tema (oscuro/verde) */
.swal2-confirm-custom{
  background: rgba(76,217,100,.92) !important; 
  color:#08140c !important; 
  font-weight:800 !important; 
  border-radius:12px !important;
}

</style>

<?php incluirTemplate('footer'); ?>
