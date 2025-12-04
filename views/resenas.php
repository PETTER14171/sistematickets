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

<main class="review-page">
  <section class="review-shell">
    <!-- HEADER SUPERIOR -->
    <header class="review-header">
      <div class="review-header__titles">
        <h1 class="review-title">Califica y reseña el libro</h1>
        <p class="review-subtitle"><?= htmlspecialchars($libro['titulo']) ?></p>

        <div class="review-tags">
          <?php if (!empty($libro['categoria'])): ?>
            <span class="review-pill"><?= htmlspecialchars($libro['categoria']) ?></span>
          <?php endif; ?>
          <!-- pill “Networking” fijo como ejemplo, puedes mapear más tarde -->
          <span class="review-pill review-pill--soft">Biblioteca</span>
        </div>
      </div>

      <div class="review-header__actions">
        <a class="review-link" href="biblioteca.php">← Volver a la biblioteca</a>

        <?php if (!empty($libro['archivo_id'])): ?>
          <a class="review-link" href="ver_pdf.php?id=<?= (int)$libro['archivo_id'] ?>" target="_blank" rel="noopener">
            📄 Ver PDF
          </a>
        <?php endif; ?>
      </div>
    </header>

    <!-- GRID PRINCIPAL: LIBRO (IZQ) + PANEL RESEÑA (DER) -->
    <section class="review-main">
      <!-- Columna izquierda: tarjeta del libro -->
      <aside class="review-book">
        <div class="review-book__cover">
          <!-- placeholder de portada -->
          <div class="review-book__cover-inner">
            <div class="review-book__thumb"></div>
          </div>
        </div>

        <div class="review-book__content">
          <h2 class="review-book__title"><?= htmlspecialchars($libro['titulo']) ?></h2>

          <?php if (!empty($libro['autor'])): ?>
            <p class="review-book__author"><?= htmlspecialchars($libro['autor']) ?></p>
          <?php endif; ?>

          <?php if (!empty($libro['descripcion'])): ?>
            <p class="review-book__desc">
              <?= nl2br(htmlspecialchars($libro['descripcion'])) ?>
            </p>
          <?php endif; ?>

          <div class="review-book__meta">
            <?php if (!empty($libro['categoria'])): ?>
              <span class="review-pill review-pill--soft">
                <?= htmlspecialchars($libro['categoria']) ?>
              </span>
            <?php endif; ?>

            <?php if (!empty($libro['tamanio_bytes'])): ?>
              <span class="review-book__size">
                <?= human_size((int)$libro['tamanio_bytes']) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </aside>

      <!-- Columna derecha: panel para crear/editar reseña -->
      <section class="review-panel">
        <?php if (isset($_GET['ok'])): ?>
          <div class="review-alert review-alert--success">
            ¡Tu reseña se guardó correctamente!
          </div>
        <?php endif; ?>

        <?php if (!empty($errores)): ?>
          <div class="review-alert review-alert--error">
            <?php foreach ($errores as $e): ?>
              <p><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="review-panel__card">
          <h2 class="review-panel__title">Selecciona tu calificación</h2>

          <form class="review-form" method="POST" enctype="multipart/form-data">
            <!-- estrellas + nota numérica -->
            <div class="review-stars-row">
              <fieldset class="review-stars">
                <?php
                  $myScore = (int)($miResena['calificacion'] ?? 0);
                  for ($i = 5; $i >= 1; $i--):
                ?>
                  <input
                    type="radio"
                    id="star<?= $i ?>"
                    name="calificacion"
                    value="<?= $i ?>"
                    <?= $myScore === $i ? 'checked' : '' ?>
                  >
                  <label for="star<?= $i ?>" title="<?= $i ?> estrellas">★</label>
                <?php endfor; ?>
              </fieldset>

              <div class="review-score">
                <?php
                  $scoreToShow = $myScore > 0 ? $myScore : (float)$rating['promedio'];
                ?>
                <span class="review-score__value">
                  <?= number_format($scoreToShow, 1) ?>
                </span>
              </div>
            </div>

            <!-- textarea -->
            <div class="review-form__field">
              <label class="review-form__label" for="comentario">Escribe tu reseña</label>
              <textarea
                class="review-form__textarea"
                id="comentario"
                name="comentario"
                rows="4"
                placeholder="Escribe tu opinión sobre este libro…"
              ><?= htmlspecialchars($miResena['comentario'] ?? '') ?></textarea>
            </div>

            <!-- botón -->
            <?php if ($puede_comentar): ?>
              <div class="review-form__actions">
                <button class="review-submit" type="submit">
                  <?= $miResena ? 'Actualizar reseña' : 'Enviar reseña' ?>
                </button>
              </div>
            <?php else: ?>
              <p class="review-form__note">
                Los administradores no pueden publicar reseñas.
              </p>
            <?php endif; ?>
          </form>
        </div>
      </section>
    </section>

    <!-- LISTA DE RESEÑAS -->
    <section class="review-list-section">
      <h2 class="review-list__title">Reseñas</h2>

      <?php if ($total_resenas === 0): ?>
        <p class="review-empty">Aún no hay reseñas. ¡Sé el primero en opinar!</p>
      <?php else: ?>
        <ul class="review-list">
          <?php foreach ($resenas as $r): ?>
            <li class="review-item">
              <div class="review-item__avatar">
                <span><?= strtoupper(mb_substr($r['nombre'], 0, 1)) ?></span>
              </div>

              <div class="review-item__body">
                <div class="review-item__header">
                  <span class="review-item__author">
                    <?= htmlspecialchars($r['nombre']) ?>
                  </span>
                  <span class="review-item__stars">
                    <?= str_repeat('★', (int)$r['calificacion']) . str_repeat('☆', 5 - (int)$r['calificacion']) ?>
                  </span>
                  <time class="review-item__date">
                    <?= date('M d, Y', strtotime($r['creado_en'])) ?>
                  </time>
                </div>

                <?php if (!empty($r['comentario'])): ?>
                  <p class="review-item__text">
                    <?= nl2br(htmlspecialchars($r['comentario'])) ?>
                  </p>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($paginas > 1): ?>
          <nav class="review-pagination">
            <?php for ($p=1; $p <= $paginas; $p++): ?>
              <a
                href="?libro_id=<?= (int)$libro_id ?>&p=<?= $p ?>"
                class="review-pagination__page <?= $p === $pag ? 'is-active' : '' ?>"
              >
                <?= $p ?>
              </a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </section>
</main>

<?php 
    incluirTemplate('footer');
?>
