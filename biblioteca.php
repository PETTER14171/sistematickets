<?php
require __DIR__ . '/includes/config/verificar_sesion.php';
require __DIR__ . '/includes/config/conexion.php';
require __DIR__ . '/includes/funciones.php';
incluirTemplate('header');

// Consultar flag de acceso
$stmt = $conn->prepare("SELECT acceso_biblioteca FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$res = $stmt->get_result();
$u = $res->fetch_assoc();
$stmt->close();

// Validar acceso
if (!$u || intval($u['acceso_biblioteca']) !== 1) {
  echo '
  <main>
      <div class="centrat-titulo_boton">
          <h3>⚠️ No tienes acceso a la biblioteca</h3>
          <a href="/panel_agente.php" class="btn-1 btn-volver">← Volver</a>
      </div>
  </main>
  ';
  incluirTemplate('footer');
  exit;

}




/** Utilidad: tamaño legible */
function human_size($bytes) {
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return number_format($bytes, $i === 0 ? 0 : 2) . ' ' . $units[$i];
}

/** Filtros */
$busqueda  = isset($_GET['q']) ? trim($_GET['q']) : '';
$categoria = isset($_GET['cat']) ? trim($_GET['cat']) : '';

/** Categorías disponibles (derivadas de libros) */
$cats = [];
$res = $conn->query("SELECT DISTINCT categoria FROM libros WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria ASC");
if ($res) { while ($row = $res->fetch_assoc()) { $cats[] = $row['categoria']; } }

/** SQL base: libros con archivo activo + rating */
$sql = "
SELECT
  l.id,
  l.titulo,
  l.descripcion,
  l.categoria,
  l.autor,
  l.creado_en,
  a.id AS archivo_id,
  a.nombre_original,
  a.tamanio_bytes,
  COALESCE(ROUND(AVG(r.calificacion), 2), 0)   AS rating_promedio,
  COUNT(r.id)                                   AS total_resenas
FROM libros l
LEFT JOIN libro_archivos a
  ON a.libro_id = l.id AND a.activo = 1
LEFT JOIN reseñas_libros r
  ON r.libro_id = l.id
WHERE 1 = 1
";

/** Filtros dinámicos */
$params = [];
$types  = '';

if ($busqueda !== '') {
  $sql .= " AND (l.titulo LIKE CONCAT('%', ?, '%') OR l.descripcion LIKE CONCAT('%', ?, '%') OR l.autor LIKE CONCAT('%', ?, '%'))";
  $params[] = $busqueda; $params[] = $busqueda; $params[] = $busqueda;
  $types .= 'sss';
}
if ($categoria !== '') {
  $sql .= " AND l.categoria = ?";
  $params[] = $categoria;
  $types .= 's';
}

$sql .= "
GROUP BY l.id, a.id
ORDER BY l.creado_en DESC
LIMIT 200
";

$stmt = $conn->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
$libros = $result->fetch_all(MYSQLI_ASSOC);
?>

<main >
  <header>
        <div class="centrat-titulo_boton">
          <h3>📚 Biblioteca </h3>
          <a href="/panel_agente.php" class="btn-1 btn-volver">← Volver</a>
        </div>
        <!-- Filtros -->
        <form class="margin-contenido biblioteca__filters" method="GET" action="">
            <section class=" filtro-busqueda-falla">
                <div class="field">
                <input id="q" class="field__input" type="text" name="q" value="<?= htmlspecialchars($busqueda) ?>" placeholder=" " />
                <label for="q" class="field__label">Buscar por título, autor o descripción</label>
                </div>
            </section>

            <section class=" filtro-categoria-falla">
                <div class="field">
                <select id="cat" name="cat" class="field__input field__select">
                    <option value="" <?= $categoria===''?'selected':''; ?>>Todas las categorías</option>
                    <?php foreach ($cats as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $categoria===$c?'selected':''; ?>>
                        <?= htmlspecialchars($c) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                </div>
            </section>

            <div class="form-falla__actions">
                <button class="btn-secondary" type="submit">Filtrar</button>
            </div>
        </form>
  </header>

  <!-- GRID de libros -->
  <?php if (!empty($libros)): ?>
    <section class="biblioteca__grid">
      <?php foreach ($libros as $l): ?>
        <article class="contenido-bloque libro-card">
          <div class="libro-card__header">
            <h2 class="libro-card__title"><?= htmlspecialchars($l['titulo']) ?></h2>
            <?php if (!empty($l['categoria'])): ?>
              <span class="chip chip--estado-en_proceso"><?= htmlspecialchars($l['categoria']) ?></span>
            <?php endif; ?>
          </div>

          <?php if (!empty($l['autor'])): ?>
            <p class="libro-card__autor muted">Autor: <?= htmlspecialchars($l['autor']) ?></p>
          <?php endif; ?>

          <?php if (!empty($l['descripcion'])): ?>
            <p class="libro-card__desc"><?= nl2br(htmlspecialchars($l['descripcion'])) ?></p>
          <?php endif; ?>

          <div class="libro-card__meta">
            <div class="rating">
              <?php
                $stars = (float)$l['rating_promedio'];
                $total = (int)$l['total_resenas'];
              ?>
              <span class="rating__stars" aria-label="Calificación promedio">
                <?= number_format($stars, 2) ?> ★
              </span>
              <span class="rating__count muted">(<?= $total ?> reseñas)</span>
            </div>

            <?php if (!empty($l['tamanio_bytes'])): ?>
              <span class="muted"><?= human_size((int)$l['tamanio_bytes']) ?></span>
            <?php endif; ?>
          </div>

          <div class="libro-card__actions">
            <?php if (!empty($l['archivo_id'])): ?>
              <a class="btn-primary-2" href="/ver_pdf.php?id=<?= (int)$l['archivo_id'] ?>" target="_blank" rel="noopener">
                Leer en línea
              </a>
            <?php else: ?>
              <button class="btn-primary" type="button" disabled>No disponible</button>
            <?php endif; ?>

            <!-- Enlace opcional a reseñas del libro -->
            <a class="btn-ghost" href="/resenas.php?libro_id=<?= (int)$l['id'] ?>">
              Reseñas
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php else: ?>
    <p class="muted">No se encontraron libros con los filtros aplicados.</p>
  <?php endif; ?>
</main>

<?php incluirTemplate('footer'); ?>
