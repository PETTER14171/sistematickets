<?php
require __DIR__ . '/../includes/config/verificar_sesion.php';
require __DIR__ . '/../includes/config/conexion.php';
require __DIR__ . '/../includes/funciones.php';

if (!isset($_GET['libro_id']) || !ctype_digit($_GET['libro_id'])) {
  header("Location: biblioteca.php?error=Libro inválido");
  exit;
}
$libro_id = (int)$_GET['libro_id'];

$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$rol        = $_SESSION['rol'] ?? 'agente';
$puede_comentar = ($rol !== 'admin'); // regla: no-admins pueden reseñar

// Mensajes
$errores = [];
$exito   = "";

/* ========= Consulta del libro + archivo activo (para "Ver en línea") ========= */
$sqlLibro = "
  SELECT l.*, a.id AS archivo_id, a.tamanio_bytes, a.nombre_original
  FROM libros l
  LEFT JOIN libro_archivos a ON a.libro_id = l.id AND a.activo = 1
  WHERE l.id = ?
";
$stL = $conn->prepare($sqlLibro);
$stL->bind_param('i', $libro_id);
$stL->execute();
$libro = $stL->get_result()->fetch_assoc();
if (!$libro) {
  header("Location: biblioteca.php?error=Libro no encontrado");
  exit;
}

/* ========= Crear/actualizar reseña (POST) ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede_comentar) {
  $calificacion = isset($_POST['calificacion']) ? (int)$_POST['calificacion'] : 0;
  $comentario   = trim($_POST['comentario'] ?? '');

  if ($calificacion < 1 || $calificacion > 5) {
    $errores[] = "Selecciona una calificación entre 1 y 5.";
  }
  if (strlen($comentario) > 5000) {
    $errores[] = "El comentario es demasiado largo.";
  }

  if (empty($errores)) {
    // Intento de inserción; si ya existe (clave única libro_id+usuario_id), actualizamos
    // Opción 1: ON DUPLICATE KEY UPDATE (si definiste UNIQUE (libro_id, usuario_id))
    $sqlUpsert = "
      INSERT INTO `reseñas_libros` (libro_id, usuario_id, calificacion, comentario)
      VALUES (?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion), comentario = VALUES(comentario), creado_en = CURRENT_TIMESTAMP
    ";
    $stU = $conn->prepare($sqlUpsert);
    $stU->bind_param('iiis', $libro_id, $usuario_id, $calificacion, $comentario);
    if ($stU->execute()) {
      // Redirección PRG para evitar re-envío de formulario
      header("Location: resenas.php?libro_id={$libro_id}&ok=1");
      exit;
    } else {
      $errores[] = "No se pudo guardar tu reseña. Inténtalo más tarde.";
    }
  }
}

/* ========= Traer calificación promedio y conteo ========= */
$sqlRating = "
  SELECT COALESCE(ROUND(AVG(calificacion),2),0) AS promedio, COUNT(*) AS total
  FROM `reseñas_libros`
  WHERE libro_id = ?
";
$stR = $conn->prepare($sqlRating);
$stR->bind_param('i', $libro_id);
$stR->execute();
$rating = $stR->get_result()->fetch_assoc() ?: ['promedio'=>0,'total'=>0];

/* ========= Traer reseña propia (si existe) para prellenar ========= */
$miResena = null;
if ($usuario_id) {
  $sqlMine = "SELECT calificacion, comentario FROM `reseñas_libros` WHERE libro_id = ? AND usuario_id = ?";
  $stM = $conn->prepare($sqlMine);
  $stM->bind_param('ii', $libro_id, $usuario_id);
  $stM->execute();
  $miResena = $stM->get_result()->fetch_assoc();
}

/* ========= Paginación de reseñas ========= */
$por_pagina = 10;
$pag = isset($_GET['p']) && ctype_digit($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($pag - 1) * $por_pagina;

// total
$stC = $conn->prepare("SELECT COUNT(*) AS c FROM `reseñas_libros` WHERE libro_id = ?");
$stC->bind_param('i', $libro_id);
$stC->execute();
$total_resenas = (int)($stC->get_result()->fetch_assoc()['c'] ?? 0);
$paginas = max(1, (int)ceil($total_resenas / $por_pagina));

// list
$sqlList = "
  SELECT r.calificacion, r.comentario, r.creado_en, u.nombre
  FROM `reseñas_libros` r
  JOIN usuarios u ON u.id = r.usuario_id
  WHERE r.libro_id = ?
  ORDER BY r.creado_en DESC
  LIMIT ? OFFSET ?
";
$stL2 = $conn->prepare($sqlList);
$stL2->bind_param('iii', $libro_id, $por_pagina, $offset);
$stL2->execute();
$resenas = $stL2->get_result()->fetch_all(MYSQLI_ASSOC);

/* ========= Utilidad ========= */
function human_size($bytes) {
  $u = ['B','KB','MB','GB','TB']; $i=0;
  while ($bytes >= 1024 && $i < count($u)-1) { $bytes/=1024; $i++; }
  return number_format($bytes, $i?2:0) . ' ' . $u[$i];
}

    require_once __DIR__ . '/../includes/funciones.php';
    incluirTemplate('head', [
        'page_title' => 'Reseñas',
        'page_desc'  => 'Panel para visualizar las reseñas de los libros de la biblioteca'
    ]);
    incluirTemplate('header');
?>

<main class="resenas-page">
  <header class="resenas-header">
    <a class="btn-ghost" href="biblioteca.php">← Volver a la biblioteca</a>
    <h1 class="ticket-title"><?= htmlspecialchars($libro['titulo']) ?></h1>
    <div class="libro-meta">
      <?php if (!empty($libro['autor'])): ?>
        <span class="muted">Autor: <strong><?= htmlspecialchars($libro['autor']) ?></strong></span>
      <?php endif; ?>
      <?php if (!empty($libro['categoria'])): ?>
        <span class="chip chip--estado-en_proceso"><?= htmlspecialchars($libro['categoria']) ?></span>
      <?php endif; ?>
      <?php if (!empty($libro['tamanio_bytes'])): ?>
        <span class="muted"><?= human_size((int)$libro['tamanio_bytes']) ?></span>
      <?php endif; ?>
      <?php if (!empty($libro['archivo_id'])): ?>
        <a class="btn-primary" href="ver_pdf.php?id=<?= (int)$libro['archivo_id'] ?>" target="_blank" rel="noopener">Ver en línea</a>
      <?php endif; ?>
    </div>

    <div class="rating-summary contenido-bloque">
      <div class="rating-summary__value">
        <span class="rating-big"><?= number_format((float)$rating['promedio'], 2) ?></span> ★
      </div>
      <div class="rating-summary__meta">
        <span><?= (int)$rating['total'] ?> reseñas</span>
      </div>
    </div>
  </header>

  <?php if (isset($_GET['ok'])): ?>
    <section class="contenido-bloque" style="border-color: rgba(var(--primary-rgb), .6)">
      <p style="color:var(--text)"><strong>¡Tu reseña se guardó correctamente!</strong></p>
    </section>
  <?php endif; ?>

  <?php if (!empty($errores)): ?>
    <section class="contenido-bloque">
      <h2 class="section-title">Problemas al guardar</h2>
      <ul>
        <?php foreach ($errores as $e): ?>
          <li style="color:#ffb9b9;"><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <div class="resenas-grid">
    <!-- Formulario de reseña -->
    <section class="contenido-bloque resena-form">
      <h2 class="section-title">Escribe tu reseña</h2>

      <?php if ($puede_comentar): ?>
        <form class="form-falla" method="POST">
          <section class="contenido-bloque">
            <!-- Calificación (estrellas) -->
            <fieldset class="stars">
              <legend class="muted">Calificación</legend>
              <?php
                $value = (int)($miResena['calificacion'] ?? 0);
                for ($i=5; $i>=1; $i--):
              ?>
                <input type="radio" id="star<?= $i ?>" name="calificacion" value="<?= $i ?>" <?= $value===$i?'checked':''; ?>>
                <label for="star<?= $i ?>" title="<?= $i ?> estrellas">★</label>
              <?php endfor; ?>
            </fieldset>
          </section>

          <section class="contenido-bloque">
            <div class="field">
              <textarea class="field__input field__textarea" name="comentario" rows="4" placeholder=" "><?= htmlspecialchars($miResena['comentario'] ?? '') ?></textarea>
              <label class="field__label">Comentario (opcional)</label>
            </div>
          </section>

          <div class="form-falla__actions">
            <button class="btn-primary-2" type="submit">
              <?= $miResena ? 'Actualizar reseña' : 'Publicar reseña' ?>
            </button>
          </div>
        </form>
      <?php else: ?>
        <p class="muted">Los administradores no pueden publicar reseñas.</p>
      <?php endif; ?>
    </section>

    <!-- Listado de reseñas -->
    <section class="contenido-bloque resena-lista">
      <h2 class="section-title">Reseñas de la comunidad</h2>

      <?php if ($total_resenas === 0): ?>
        <p class="muted">Aún no hay reseñas. ¡Sé el primero en opinar!</p>
      <?php else: ?>
        <ul class="resenas">
          <?php foreach ($resenas as $r): ?>
            <li class="resena">
              <div class="resena__header">
                <strong class="autor"><?= htmlspecialchars($r['nombre']) ?></strong>
                <span class="stars-inline"><?= str_repeat('★', (int)$r['calificacion']) . str_repeat('☆', 5 - (int)$r['calificacion']) ?></span>
                <time class="fecha muted"><?= date('d/m/Y H:i', strtotime($r['creado_en'])) ?></time>
              </div>
              <?php if (!empty($r['comentario'])): ?>
                <p class="resena__text"><?= nl2br(htmlspecialchars($r['comentario'])) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($paginas > 1): ?>
          <nav class="paginacion">
            <?php for ($p=1; $p <= $paginas; $p++): ?>
              <a class="page <?= $p===$pag?'is-active':'' ?>" href="?libro_id=<?= (int)$libro_id ?>&p=<?= $p ?>">
                <?= $p ?>
              </a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</main>


<?php incluirTemplate('footer'); ?>
