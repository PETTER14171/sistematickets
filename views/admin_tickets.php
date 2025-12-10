<?php
// views/admin_tickets.php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: ../index.php?error=Acceso denegado");
    exit;
}

// ---------------------------
// Filtros (GET)
// ---------------------------
$search       = isset($_GET['q']) ? trim($_GET['q']) : '';
$estadoFiltro = isset($_GET['estado']) ? trim($_GET['estado']) : 'todos';

// Página actual (para paginación)
$perPage = 10;
$page    = (isset($_GET['page']) && ctype_digit($_GET['page']))
    ? max(1, (int)$_GET['page'])
    : 1;
$offset  = ($page - 1) * $perPage;

// Normalizamos el filtro de estado
$estadoGrupo = null;
switch ($estadoFiltro) {
    case 'abierto':
    case 'en_proceso':
    case 'resuelto':
    case 'cerrado':
        $estadoGrupo = $estadoFiltro;
        break;
    default:
        $estadoGrupo = null; // todos
}

// ---------------------------
// Construir FROM + WHERE comunes
// ---------------------------
$baseFrom = "
    FROM tickets t
    JOIN usuarios u ON t.id_usuario = u.id
    LEFT JOIN fallas_comunes f ON t.referencia_falla = f.id
    WHERE 1 = 1
";

$conditions = '';
$params     = [];
$types      = '';

// Filtro por estado agrupado
if ($estadoGrupo !== null) {
    switch ($estadoGrupo) {
        case 'abierto':
            $conditions .= " AND (t.estado = 'abierto' OR t.estado = 'open')";
            break;
        case 'en_proceso':
            $conditions .= " AND (t.estado = 'en_proceso' OR t.estado = 'en proceso' OR t.estado = 'in_progress')";
            break;
        case 'resuelto':
            $conditions .= " AND (t.estado = 'resuelto' OR t.estado = 'resuelto_ok' OR t.estado = 'resolved')";
            break;
        case 'cerrado':
            $conditions .= " AND (t.estado = 'cerrado' OR t.estado = 'closed')";
            break;
    }
}

// Filtro de búsqueda (título, agente, categoría)
if ($search !== '') {
    $conditions .= " AND (
        t.titulo       LIKE CONCAT('%', ?, '%')
        OR u.nombre    LIKE CONCAT('%', ?, '%')
        OR t.categoria LIKE CONCAT('%', ?, '%')
    )";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types   .= 'sss';
}

// ---------------------------
// 1) Conteo total para paginación
// ---------------------------
$countSql = "SELECT COUNT(*) AS total " . $baseFrom . $conditions;
$stmt = $conn->prepare($countSql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRows = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

// ---------------------------
// 2) Consulta de datos con LIMIT/OFFSET
// ---------------------------
$dataSql = "
    SELECT 
        t.*,
        u.nombre AS nombre_agente,
        f.titulo AS titulo_falla
" . $baseFrom . $conditions . "
    ORDER BY t.creado_en DESC
    LIMIT ? OFFSET ?
";

if ($types !== '') {
    $typesData  = $types . 'ii';
    $paramsData = array_merge($params, [$perPage, $offset]);
    $stmt = $conn->prepare($dataSql);
    $stmt->bind_param($typesData, ...$paramsData);
} else {
    $stmt = $conn->prepare($dataSql);
    $stmt->bind_param('ii', $perPage, $offset);
}

$stmt->execute();
$result  = $stmt->get_result();

// ---------------------------
// Transformar resultados
// ---------------------------
$tickets = [];

while ($row = $result->fetch_assoc()) {
    // PRIORIDAD → label + key
    $prioBd = strtolower(trim($row['prioridad'] ?? ''));
    $prioLabel = 'Normal';
    $prioKey   = 'normal';

    switch ($prioBd) {
        case 'alta':
        case 'high':
            $prioLabel = 'High';
            $prioKey   = 'high';
            break;
        case 'media':
        case 'medio':
        case 'medium':
            $prioLabel = 'Medium';
            $prioKey   = 'medium';
            break;
        case 'baja':
        case 'low':
            $prioLabel = 'Low';
            $prioKey   = 'low';
            break;
        default:
            $prioLabel = ucfirst($prioBd ?: 'Normal');
            $prioKey   = 'normal';
            break;
    }

    // ESTADO → label + key
    $estadoBd = strtolower(trim($row['estado']));
    $statusLabel = 'Open';
    $statusKey   = 'open';

    switch ($estadoBd) {
        case 'en_proceso':
        case 'en proceso':
        case 'in_progress':
            $statusLabel = 'In Progress';
            $statusKey   = 'in-progress';
            break;
        case 'resuelto':
        case 'resuelto_ok':
        case 'resolved':
            $statusLabel = 'Resolved';
            $statusKey   = 'resolved';
            break;
        case 'cerrado':
        case 'closed':
            $statusLabel = 'Closed';
            $statusKey   = 'closed';
            break;
        case 'abierto':
        case 'open':
        default:
            $statusLabel = 'Open';
            $statusKey   = 'open';
            break;
    }

    // Fecha bonita (solo fecha, tipo "Mar 10, 2023")
    $fechaCreado = '';
    if (!empty($row['creado_en'])) {
        $dt = new DateTime($row['creado_en']);
        $fechaCreado = $dt->format('M j, Y');
    }

    $tickets[] = [
        'id'            => (int)$row['id'],
        'titulo'        => $row['titulo'],
        'agente'        => $row['nombre_agente'],
        'categoria'     => $row['categoria'],
        'prioridad_lbl' => $prioLabel,
        'prioridad_key' => $prioKey,
        'estado_lbl'    => $statusLabel,
        'estado_key'    => $statusKey,
        'falla_titulo'  => $row['titulo_falla'],
        'creado'        => $fechaCreado,
    ];
}

$stmt->close();

require_once __DIR__ . '/../includes/funciones.php';
incluirTemplate('head', [
    'page_title' => 'Admin Tickets',
    'page_desc'  => 'Panel para que el Técnico vea y administre tickets'
]);
incluirTemplate('header');
?>

<main class="admin-tickets-page">
    <section class="admin-tickets__inner">
        <header class="admin-tickets__header">
            <div class="admin-tickets__title-group">
                <h1 class="admin-tickets__title">Manage Tickets</h1>
                <p class="admin-tickets__subtitle">
                    Revisa, filtra y administra los tickets abiertos por los agentes.
                </p>
            </div>

            <div class="admin-tickets__actions">
                <!-- Buscador: al enviar siempre vuelve a página 1 -->
                <form class="admin-tickets__search" method="GET">
                    <?php if ($estadoFiltro !== 'todos'): ?>
                        <input type="hidden" name="estado"
                               value="<?= htmlspecialchars($estadoFiltro, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <input
                        class="admin-tickets__search-input"
                        type="text"
                        name="q"
                        placeholder="Search"
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </form>

                <!-- Filtro de estado: al cambiar siempre página 1 -->
                <form class="admin-tickets__filter" method="GET">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="q"
                               value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <div class="admin-tickets__filter-select-wrapper">
                        <select
                            class="admin-tickets__filter-select"
                            name="estado"
                            onchange="this.form.submit()"
                        >
                            <option value="todos"      <?= $estadoFiltro === 'todos'      ? 'selected' : '' ?>>All</option>
                            <option value="abierto"    <?= $estadoFiltro === 'abierto'    ? 'selected' : '' ?>>Open</option>
                            <option value="en_proceso" <?= $estadoFiltro === 'en_proceso' ? 'selected' : '' ?>>In Progress</option>
                            <option value="resuelto"   <?= $estadoFiltro === 'resuelto'   ? 'selected' : '' ?>>Resolved</option>
                            <option value="cerrado"    <?= $estadoFiltro === 'cerrado'    ? 'selected' : '' ?>>Closed</option>
                        </select>
                        <span class="admin-tickets__filter-label">Filters</span>
                    </div>
                </form>
            </div>
        </header>

        <section class="admin-tickets-card">
            <div class="admin-tickets-table__wrapper">
                <table class="admin-tickets-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Agent</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="7" class="admin-tickets-table__empty">
                                No hay tickets con los filtros aplicados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                            <tr
                                class="admin-tickets-table__row admin-tickets-table__row--clickable"
                                onclick="window.location='responder_ticket.php?id=<?= (int)$t['id'] ?>';"
                            >
                                <td class="admin-tickets-table__cell-id">
                                    #<?= (int)$t['id'] ?>
                                </td>
                                <td class="admin-tickets-table__cell-title">
                                    <div class="admin-tickets-table__title-main">
                                        <?= htmlspecialchars($t['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php if (!empty($t['falla_titulo'])): ?>
                                        <div class="admin-tickets-table__title-sub">
                                            Ref: <?= htmlspecialchars($t['falla_titulo'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($t['agente'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($t['categoria'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <span class="priority-pill priority-pill--<?= htmlspecialchars($t['prioridad_key'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($t['prioridad_lbl'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill status-pill--<?= htmlspecialchars($t['estado_key'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($t['estado_lbl'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($t['creado'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="admin-tickets__pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php
                        // Construir query string conservando filtros / búsqueda
                        $qs = http_build_query([
                            'page'   => $p,
                            'q'      => $search,
                            'estado' => $estadoFiltro
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

<?php incluirTemplate('footer'); ?>
