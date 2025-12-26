<?php
// views/admin_notificaciones.php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] ?? '') !== 'tecnico') {
    header("Location: ../index.php?error=Acceso denegado");
    exit;
}

// ---------------------------
// Filtros (GET)
// ---------------------------
$q          = isset($_GET['q']) ? trim($_GET['q']) : '';
$prio       = isset($_GET['prio']) ? trim($_GET['prio']) : ''; // baja|media|alta
$leido      = isset($_GET['leido']) ? trim($_GET['leido']) : ''; // 0|1|''
$ticketId   = isset($_GET['ticket_id']) && ctype_digit($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

// ---------------------------
// Paginación
// ---------------------------
$perPage = 10;
$page    = (isset($_GET['page']) && ctype_digit($_GET['page'])) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $perPage;

// ---------------------------
// Acciones POST (marcar leído / marcar todo)
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'mark_read_one') {
        $id = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE notificaciones SET leido = 1 WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }

        // regresar conservando filtros
        $qs = $_POST['return_qs'] ?? '';
        header("Location: admin_notificaciones.php" . ($qs ? "?{$qs}" : ""));
        exit;
    }

    if ($accion === 'mark_read_all') {
        // Marca como leído todo lo que esté actualmente filtrado (o todo, si no hay filtros)
        $baseFrom = "
            FROM notificaciones n
            LEFT JOIN tickets t ON t.id = n.ticket_id
        ";

        $where = [];
        $params = [];
        $types = '';

        if ($ticketId > 0) {
            $where[]  = "n.ticket_id = ?";
            $params[] = $ticketId;
            $types   .= 'i';
        }

        if ($q !== '') {
            $where[] = "(n.mensaje LIKE CONCAT('%', ?, '%') OR CAST(n.ticket_id AS CHAR) LIKE CONCAT('%', ?, '%'))";
            $params[] = $q;
            $params[] = $q;
            $types   .= 'ss';
        }

        if (in_array($prio, ['baja','media','alta'], true)) {
            $where[]  = "n.prioridad = ?";
            $params[] = $prio;
            $types   .= 's';
        }

        // si el filtro leido está en 0, solo marca los no leídos. Si está vacío, marca todo.
        if ($leido === '0') {
            $where[] = "n.leido = 0";
        } elseif ($leido === '1') {
            // si están filtrando leídos, no tiene sentido marcar leídos otra vez pero lo dejamos
            $where[] = "n.leido = 1";
        }

        $whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

        // UPDATE con JOIN y filtros (seguro)
        $sql = "UPDATE notificaciones n " .
               "LEFT JOIN tickets t ON t.id = n.ticket_id " .
               "SET n.leido = 1 " . $whereSql;

        $stmt = $conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $stmt->close();

        $qs = $_POST['return_qs'] ?? '';
        header("Location: admin_notificaciones.php" . ($qs ? "?{$qs}" : ""));
        exit;
    }
}

// helper: conservar querystring sin page
function qs_keep(array $extra = []) {
    $q = $_GET;
    unset($q['page']);
    $q = array_merge($q, $extra);
    return http_build_query($q);
}

// ---------------------------
// WHERE dinámico para listar + contar
// ---------------------------
$baseFrom = "
    FROM notificaciones n
    LEFT JOIN tickets t ON t.id = n.ticket_id
";

$where = [];
$params = [];
$types = '';

if ($ticketId > 0) {
    $where[]  = "n.ticket_id = ?";
    $params[] = $ticketId;
    $types   .= 'i';
}

if ($q !== '') {
    $where[] = "(n.mensaje LIKE CONCAT('%', ?, '%') OR CAST(n.ticket_id AS CHAR) LIKE CONCAT('%', ?, '%'))";
    $params[] = $q;
    $params[] = $q;
    $types   .= 'ss';
}

if (in_array($prio, ['baja','media','alta'], true)) {
    $where[]  = "n.prioridad = ?";
    $params[] = $prio;
    $types   .= 's';
}

if ($leido === '0') {
    $where[] = "n.leido = 0";
} elseif ($leido === '1') {
    $where[] = "n.leido = 1";
}

$whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : "";

// ---------------------------
// 1) Conteo total
// ---------------------------
$countSql = "SELECT COUNT(*) AS total " . $baseFrom . $whereSql;
$stmt = $conn->prepare($countSql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRows = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

// ---------------------------
// 2) Data con LIMIT/OFFSET
// ---------------------------
$dataSql = "
    SELECT
        n.id,
        n.ticket_id,
        n.mensaje,
        n.prioridad,
        n.leido,
        n.creado_en,
        t.titulo AS ticket_titulo
" . $baseFrom . $whereSql . "
    ORDER BY n.creado_en DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($dataSql);

if ($types !== '') {
    $typesData  = $types . 'ii';
    $paramsData = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($typesData, ...$paramsData);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// map para UI
$notificaciones = [];
foreach ($rows as $r) {
    $prio = strtolower(trim($r['prioridad'] ?? 'media'));
    $prioLbl = ucfirst($prio);
    $prioKey = in_array($prio, ['baja','media','alta'], true) ? $prio : 'media';

    $fecha = '';
    if (!empty($r['creado_en'])) {
        $fecha = (new DateTime($r['creado_en']))->format('M j, Y H:i');
    }

    $notificaciones[] = [
        'id'          => (int)$r['id'],
        'ticket_id'   => (int)($r['ticket_id'] ?? 0),
        'ticket_t'    => $r['ticket_titulo'] ?? '',
        'mensaje'     => $r['mensaje'] ?? '',
        'prio_lbl'    => $prioLbl,
        'prio_key'    => $prioKey,
        'leido'       => !empty($r['leido']),
        'fecha'       => $fecha,
    ];
}

incluirTemplate('head', [
    'page_title' => 'Notificaciones',
    'page_desc'  => 'Histórico de notificaciones del sistema'
]);
incluirTemplate('header');
?>

<main class="admin-tickets-page admin-notifs-page">
    <a href="panel_tecnico.php" class="btn-1 btn-volver ticket-detail__back">← Volver</a>
    <section class="admin-tickets__inner">
        <header class="admin-tickets__header">
            <div class="admin-tickets__title-group">
                <h1 class="admin-tickets__title">Notificaciones</h1>
                <p class="admin-tickets__subtitle">
                    Histórico de alertas y eventos. Filtra, consulta y marca como leídas.
                </p>
            </div>

            <div class="admin-tickets__actions">
                <!-- Buscar -->
                <form class="admin-tickets__search" method="GET">
                    <?php if ($prio !== ''): ?>
                        <input type="hidden" name="prio" value="<?= htmlspecialchars($prio, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <?php if ($leido !== ''): ?>
                        <input type="hidden" name="leido" value="<?= htmlspecialchars($leido, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <?php if ($ticketId > 0): ?>
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">
                    <?php endif; ?>

                    <input
                        class="admin-tickets__search-input"
                        type="text"
                        name="q"
                        placeholder="Search message / ticket id"
                        value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </form>

                <!-- Filtros -->
                <form class="admin-tickets__filter" method="GET">
                    <?php if ($q !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <?php if ($ticketId > 0): ?>
                        <input type="hidden" name="ticket_id" value="<?= (int)$ticketId ?>">
                    <?php endif; ?>

                    <div class="admin-tickets__filter-select-wrapper">
                        <select class="admin-tickets__filter-select" name="prio" onchange="this.form.submit()">
                            <option value="" <?= $prio === '' ? 'selected' : '' ?>>All Priorities</option>
                            <option value="baja"  <?= $prio === 'baja'  ? 'selected' : '' ?>>Baja</option>
                            <option value="media" <?= $prio === 'media' ? 'selected' : '' ?>>Media</option>
                            <option value="alta"  <?= $prio === 'alta'  ? 'selected' : '' ?>>Alta</option>
                        </select>
                        <span class="admin-tickets__filter-label">Priority</span>
                    </div>

                    <div class="admin-tickets__filter-select-wrapper">
                        <select class="admin-tickets__filter-select" name="leido" onchange="this.form.submit()">
                            <option value=""  <?= $leido === ''  ? 'selected' : '' ?>>All</option>
                            <option value="0" <?= $leido === '0' ? 'selected' : '' ?>>Unread</option>
                            <option value="1" <?= $leido === '1' ? 'selected' : '' ?>>Read</option>
                        </select>
                        <span class="admin-tickets__filter-label">Status</span>
                    </div>
                </form>

                <!-- Marcar todo leído -->
                <form method="POST" class="admin-notifs__bulk">
                    <input type="hidden" name="accion" value="mark_read_all">
                    <input type="hidden" name="return_qs" value="<?= htmlspecialchars(qs_keep([]), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn-ghost-small admin-notifs__bulk-btn">
                        Marcar todo como leído
                    </button>
                </form>
            </div>
        </header>

        <section class="admin-tickets-card">
            <div class="admin-tickets-table__wrapper">
                <table class="admin-tickets-table admin-notifs-table">
                    <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Message</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="admin-notifs-table__actions-col">Action</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php if (empty($notificaciones)): ?>
                        <tr>
                            <td colspan="7" class="admin-tickets-table__empty">
                                No hay notificaciones con los filtros aplicados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($notificaciones as $n): ?>
                            <tr class="admin-tickets-table__row <?= $n['leido'] ? '' : 'is-unread' ?> admin-tickets-table__row--clickable"
                            onclick="window.location='responder_ticket.php?id=<?= (int)$n['id'] ?>';"
                            >
                                <td class="admin-notifs-table__ticket">
                                    <?php if ($n['ticket_id'] > 0): ?>
                                        <a class="admin-notifs__ticket-link"
                                           href="responder_ticket.php?id=<?= (int)$n['ticket_id'] ?>">
                                            #<?= (int)$n['ticket_id'] ?>
                                        </a>
                                        <?php if ($n['ticket_t'] !== ''): ?>
                                            <div class="admin-notifs__ticket-sub">
                                                <?= htmlspecialchars($n['ticket_t'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="admin-notifs__muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="admin-notifs-table__msg">
                                    <?= nl2br(htmlspecialchars($n['mensaje'], ENT_QUOTES, 'UTF-8')) ?>
                                </td>

                                <td>
                                    <span class="priority-pill priority-pill--<?= htmlspecialchars($n['prio_key'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($n['prio_lbl'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status-pill status-pill--<?= $n['leido'] ? 'resolved' : 'open' ?>">
                                        <?= $n['leido'] ? 'Read' : 'Unread' ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($n['fecha'], ENT_QUOTES, 'UTF-8') ?></td>

                                <td class="admin-notifs-table__actions">
                                    <?php if (!$n['leido']): ?>
                                        <form method="POST" class="admin-notifs__mark">
                                            <input type="hidden" name="accion" value="mark_read_one">
                                            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                            <input type="hidden" name="return_qs" value="<?= htmlspecialchars(qs_keep([]), ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn-ghost-small">
                                                Marcar leído
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="admin-notifs__muted">—</span>
                                    <?php endif; ?>
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
                        $qs = qs_keep(['page' => $p]);
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
