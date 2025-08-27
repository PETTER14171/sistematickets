<?php
// ver_pdf.php
require __DIR__ . '/includes/config/verificar_sesion.php';
require __DIR__ . '/includes/config/conexion.php';
require __DIR__ . '/includes/funciones.php';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
  http_response_code(400); exit('ID inválido');
}
$id = (int)$_GET['id'];

/* === Consulta de metadatos (archivo activo + datos del libro) === */
$sql = "SELECT 
          a.id, a.libro_id, a.nombre_archivo, a.nombre_original, a.mime_type, a.tamanio_bytes, a.creado_en AS subido_en,
          l.titulo, l.autor, l.categoria, l.descripcion, l.creado_en
        FROM libro_archivos a
        INNER JOIN libros l ON l.id = a.libro_id
        WHERE a.id = ? AND a.activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$file = $res->fetch_assoc();
if (!$file) { http_response_code(404); exit('Archivo no encontrado'); }

/* === Valida acceso por rol si tu biblioteca restringe (ejemplo: solo usuarios logueados) === */
// if ($_SESSION['rol'] === 'invitado') { http_response_code(403); exit('Sin permiso'); }

/* === Ruta física segura === */
$baseDir = __DIR__ . '/biblioteca';
$filename = basename($file['nombre_archivo']);   // evita path traversal
$path = $baseDir . '/' . $filename;
if (!is_file($path)) { http_response_code(404); exit('No existe el archivo'); }

/* === MODO STREAM: entrega bytes con inline + rangos === */
if (isset($_GET['stream']) && $_GET['stream'] === '1') {
  $mime = $file['mime_type'] ?: 'application/pdf';
  $size = (int)filesize($path);
  $fp = fopen($path, 'rb');
  if (!$fp) { http_response_code(500); exit('No se pudo abrir el archivo'); }

  // Cabeceras de seguridad/visualización
  header('X-Content-Type-Options: nosniff');
  header("Content-Security-Policy: default-src 'none'; img-src 'self' blob: data:; media-src 'self'; frame-ancestors 'self';");
  header('Referrer-Policy: same-origin');

  // Rangos
  $start = 0; $end = $size - 1; $length = $size;
  if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $start = (int)$m[1];
    if ($m[2] !== '') $end = (int)$m[2];
    if ($end >= $size) $end = $size - 1;
    $length = ($end - $start) + 1;
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$size");
    fseek($fp, $start);
  }

  header("Content-Type: $mime");
  // inline para visualizar (no attachment)
  header('Content-Disposition: inline; filename="'.addslashes($file['nombre_original']).'"');
  header('Accept-Ranges: bytes');
  header('Content-Length: ' . $length);
  header('Cache-Control: private, max-age=3600');

  $buffer = 8192;
  while (!feof($fp) && $length > 0) {
    $read = ($length > $buffer) ? $buffer : $length;
    echo fread($fp, $read);
    $length -= $read;
    @ob_flush(); flush();
  }
  fclose($fp);
  exit;
}

/* === MODO PÁGINA: interface con visor embebido === */
incluirTemplate('header');

// Utilidad de formato
function human_size($bytes) {
  $u = ['B','KB','MB','GB','TB']; $i=0;
  while ($bytes >= 1024 && $i < count($u)-1) { $bytes/=1024; $i++; }
  return number_format($bytes, $i?2:0) . ' ' . $u[$i];
}

// URL de stream interno + parámetros de UI del visor
$src = '/ver_pdf.php?id='.(int)$file['id'].'&stream=1#toolbar=0&navpanes=0&scrollbar=0&zoom=page-width';
?>
<main class="contenido-bloque pdf-view-page">
  <header class="pdf-view-header">
    <div class="pdf-breadcrumb">
      <a href="/biblioteca.php" class="btn-ghost">← Volver a la biblioteca</a>
    </div>
    <h1 class="pdf-title"><?= htmlspecialchars($file['titulo']) ?></h1>
    <div class="pdf-meta">
      <?php if (!empty($file['autor'])): ?>
        <span class="muted">Autor: <strong><?= htmlspecialchars($file['autor']) ?></strong></span>
      <?php endif; ?>

      <span class="muted">Tamaño: <?= human_size((int)$file['tamanio_bytes']) ?></span>
    </div>
  </header>

  <section class="pdf-shell contenido-bloque">
    <!-- Visor embebido (misma página) -->
    <iframe class="pdf-frame" src="<?= htmlspecialchars($src) ?>" title="Lector de PDF" loading="eager"></iframe>
  </section>
</main>

<?php incluirTemplate('footer'); ?>
