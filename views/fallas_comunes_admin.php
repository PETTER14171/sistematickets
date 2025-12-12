<?php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: ../index.php?error=Acceso denegado");
    exit;
}

// ---------------------------------
// Paginación
// ---------------------------------
$perPage = 10;
$page    = (isset($_GET['page']) && ctype_digit($_GET['page']))
    ? max(1, (int)$_GET['page'])
    : 1;
$offset  = ($page - 1) * $perPage;

// 1) Conteo total
$sqlCount = "
    SELECT COUNT(*) AS total
    FROM fallas_comunes f
    JOIN usuarios u ON f.creado_por = u.id
";
$stmt = $conn->prepare($sqlCount);
$stmt->execute();
$totalRows = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

// 2) Consulta paginada
$sqlData = "
    SELECT f.*, u.nombre AS autor
    FROM fallas_comunes f
    JOIN usuarios u ON f.creado_por = u.id
    ORDER BY f.creado_en DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sqlData);
$stmt->bind_param('ii', $perPage, $offset);
$stmt->execute();
$fallas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once __DIR__ . '/../includes/funciones.php';
incluirTemplate('head', [
    'page_title' => 'Fallas Comunes',
    'page_desc'  => 'Panel para que el Técnico administre las guías de fallas comunes'
]);
incluirTemplate('header');
?>

<main class="admin-tickets-page">
    <a href="panel_tecnico.php" class="btn-1 btn-volver ticket-detail__back">← Volver</a>
    <section class="admin-tickets__inner">
        <!-- HEADER -->
        <header class="admin-tickets__header">
            <div class="admin-tickets__title-group">
                <h1 class="admin-tickets__title">Guías De Fallas Comunes</h1>
                <p class="admin-tickets__subtitle">
                    Administra las guías de solución que usan los agentes en la sección de autoservicio.
                </p>
            </div>

                <a href="crear_falla.php" class="btn-primary">
                    Nueva Guía
                </a>
            </div>
        </header>

        <!-- CARD PRINCIPAL -->
        <section class="admin-tickets-card">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado'): ?>
                <div class="ticket-alert ticket-alert--success">
                    ✅ Guía eliminada correctamente.
                </div>
            <?php endif; ?>

            <div class="admin-tickets-table__wrapper">
                <table class="admin-tickets-table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Descripción</th>
                            <th>Autor</th>
                            <th>Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($fallas) === 0): ?>
                            <tr>
                                <td colspan="5" class="admin-tickets-table__empty">
                                    No hay guías registradas aún.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fallas as $f): ?>
                                <tr
                                    class="admin-tickets-table__row admin-tickets-table__row--clickable"
                                    onclick="window.location='editar_falla.php?id=<?= (int)$f['id'] ?>';"
                                >
                                    <td>
                                        <?= htmlspecialchars($f['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($f['categoria'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <?php
                                            $txt = htmlspecialchars($f['descripcion'], ENT_QUOTES, 'UTF-8');
                                            echo (strlen($txt) > 50)
                                                ? nl2br(substr($txt, 0, 50)) . '...'
                                                : nl2br($txt);
                                        ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($f['autor'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($f['creado_en'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <nav class="admin-tickets__pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php
                        $qs = http_build_query([
                            'page' => $p
                        ]);
                        ?>
                        <a
                            href="?<?= htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') ?>"
                            class="page-pill <?= $p === $page ? 'is-active' : '' ?>"
                        >
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </section>
    </section>
</main>

<?php 
incluirTemplate('footer');
?>
