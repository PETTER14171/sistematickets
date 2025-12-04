<?php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

if ($_SESSION['rol'] !== 'agente') {
    header("Location: index.php?error=Acceso denegado");
    exit;
}

$busqueda = '';
$resultados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $busqueda = trim($_POST['busqueda']);

    $query = "SELECT * FROM fallas_comunes 
              WHERE titulo LIKE ? OR categoria LIKE ? OR palabras_clave LIKE ?
              ORDER BY creado_en DESC";

    $stmt = $conn->prepare($query);
    $like = "%$busqueda%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}else {
    // Si no hay búsqueda, mostrar todas
    $query = "SELECT * FROM fallas_comunes ORDER BY id DESC";
    $resultados = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}
?>

<?php
    require_once __DIR__ . '/../includes/funciones.php';
    incluirTemplate('head', [
        'page_title' => 'Autos Servicio',
        'page_desc'  => 'Listado de soluciones para los usuarios'
    ]);
    incluirTemplate('header');
?>

<main class="kb-page">
    <section class="kb-layout">
        <!-- Header + buscador -->
        <header class="kb-header">
            <div class="kb-header__titles">
                <h1 class="kb-title">Centro de ayuda / Autoservicio</h1>
                <p class="kb-subtitle">
                    Guías y soluciones a las fallas más comunes dentro de la compañía.
                </p>
            </div>

            <form method="POST" class="kb-search">
                    <input
                        class="tickets-toolbar__search-input"
                        type="text"
                        name="busqueda"
                        placeholder="Buscar por asunto, categoría o palabra clave…"
                        value="<?= htmlspecialchars($busqueda) ?>"
                        required
                    >
            </form>
        </header>

        <?php if ($resultados): ?>
            <section class="kb-results">
                <?php foreach ($resultados as $i => $falla): ?>
                    <?php
                        $archivo   = $falla['multimedia'];
                        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
                        $ruta      = '/../fallamultimedia/' . $archivo;

                        // tags simples: categoría + palabras_clave (si quieres algo más elaborado luego lo vemos)
                        $tags = [];
                        if (!empty($falla['categoria'])) {
                            $tags[] = $falla['categoria'];
                        }
                        if (!empty($falla['palabras_clave'])) {
                            $tags[] = $falla['palabras_clave'];
                        }

                        $isFeatured = ($i === 0); // primera card grande
                    ?>

                    <article
                        class="kb-card <?= $isFeatured ? 'kb-card--featured' : '' ?>"
                        onclick="abrirModalFalla(<?= (int)$falla['id'] ?>)"
                    >
                        <div class="kb-card__media">
                            <?php if (!empty($archivo) && in_array($extension, ['jpg','jpeg','png','gif'])): ?>
                                <img src="<?= $ruta ?>" alt="Imagen de la falla">
                            <?php else: ?>
                                <!-- icono simple si no hay imagen -->
                                <span class="kb-card__media-icon">🖥️</span>
                            <?php endif; ?>
                        </div>

                        <div class="kb-card__content">
                            <?php if (!empty($tags)): ?>
                                <div class="kb-card__tags">
                                    <?php foreach ($tags as $tag): ?>
                                        <span class="kb-pill">
                                            <?= htmlspecialchars($tag) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <h2 class="kb-card__title">
                                <?= htmlspecialchars($falla['titulo']) ?>
                            </h2>

                            <p class="kb-card__excerpt">
                                <?= htmlspecialchars(mb_strimwidth($falla['descripcion'] ?? '', 0, 120, '…')) ?>
                            </p>

                            <div class="kb-card__meta">
                                <span class="kb-pill kb-pill--type">
                                    <?= htmlspecialchars($falla['categoria'] ?: 'General') ?>
                                </span>

                                <?php if (!empty($falla['creado_en'])): ?>
                                    <span class="kb-card__date">
                                        <?= (new DateTime($falla['creado_en']))->format('M j, Y') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>

                    <!-- Modal original (lo dejamos tal cual, solo movido abajo del card) -->
                    <div id="modal-falla-<?= $falla['id'] ?>" class="modal-falla">
                        <div class="modal-contenido">
                            <button class="cerrar-modal" onclick="cerrarModalFalla(<?= $falla['id'] ?>)">✖</button>

                            <?php if (!empty($archivo)): ?>
                                <?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <div class="media-modal">
                                        <img src="<?= $ruta ?>" alt="Imagen de la falla">
                                    </div>
                                <?php elseif (in_array($extension, ['mp4', 'webm', 'ogg'])): ?>
                                    <div class="media-modal">
                                        <video controls>
                                            <source src="<?= $ruta ?>" type="video/<?= $extension ?>">
                                        </video>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <h3><?= htmlspecialchars($falla['titulo']) ?></h3>
                            <p><strong>Categoría:</strong> <?= htmlspecialchars($falla['categoria']) ?></p>
                            <p><strong>Descripción:</strong> <?= nl2br(htmlspecialchars($falla['descripcion'])) ?></p>
                            <p class="pasos-solucion"><strong>Pasos:</strong> <?= nl2br(htmlspecialchars($falla['pasos_solucion'])) ?></p>
                            <a href="crear_ticket.php?referencia=<?= $falla['id'] ?>" class="crear-ticket">
                                🛠 No resolvió mi problema
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <section class="kb-empty">
                <p>No se encontraron coincidencias. Puedes <a href="crear_ticket.php">crear un ticket</a>.</p>
            </section>
        <?php endif; ?>
    </section>
</main>

<?php 
incluirTemplate('footer');
?>
