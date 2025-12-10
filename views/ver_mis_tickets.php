<?php
// views/ver_mis_tickets.php

include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';
require_once __DIR__ . '/../includes/funciones.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php?error=Debes+iniciar+sesion");
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

// ----------------------------
// Filtros desde GET
// ----------------------------
$estado_filtro = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$estado_filtro = $estado_filtro !== '' ? $estado_filtro : 'todos';

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Normalizamos el filtro de estado a los grupos que ya usas
$estado_grupo = null; // 'abierto', 'en_proceso', 'resuelto', 'cerrado' o null

switch ($estado_filtro) {
    case 'abierto':
    case 'en_proceso':
    case 'resuelto':
    case 'cerrado':
        $estado_grupo = $estado_filtro;
        break;
    case 'todos':
    default:
        $estado_grupo = null;
        break;
}

// ----------------------------
// Paginación
// ----------------------------
$por_pagina = 10;
$pagina_actual = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;
if ($pagina_actual < 1) {
    $pagina_actual = 1;
}

// ----------------------------
// Construir WHERE reutilizable
// ----------------------------
$sql_base = "
    FROM tickets
    WHERE id_usuario = ?
";
$params = [$usuario_id];
$types  = 'i';

// Filtro por estado (mapeo a tus valores reales)
if ($estado_grupo !== null) {
    switch ($estado_grupo) {
        case 'abierto':
            $sql_base .= " AND (estado = 'abierto' OR estado = 'open')";
            break;
        case 'en_proceso':
            $sql_base .= " AND (estado = 'en_proceso' OR estado = 'en proceso' OR estado = 'in_progress')";
            break;
        case 'resuelto':
            $sql_base .= " AND (estado = 'resuelto' OR estado = 'resuelto_ok' OR estado = 'resolved')";
            break;
        case 'cerrado':
            $sql_base .= " AND (estado = 'cerrado' OR estado = 'closed')";
            break;
    }
}

// Filtro de búsqueda
if ($search !== '') {
    $sql_base .= " AND (titulo LIKE ? OR descripcion LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

// ----------------------------
// 1) TOTAL de registros (para paginación)
// ----------------------------
$sql_count = "SELECT COUNT(*) AS total " . $sql_base;
$stmt = $conn->prepare($sql_count);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res_count = $stmt->get_result()->fetch_assoc();
$total_registros = (int)($res_count['total'] ?? 0);
$stmt->close();

$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));
if ($pagina_actual > $total_paginas) {
    $pagina_actual = $total_paginas;
}
$offset = ($pagina_actual - 1) * $por_pagina;

// ----------------------------
// 2) Consulta de datos paginados
// ----------------------------
$sql_data = "
    SELECT
        id,
        titulo,
        descripcion,
        categoria,
        prioridad,
        estado,
        COALESCE(actualizado_en, creado_en) AS ultima
    " . $sql_base . "
    ORDER BY ultima DESC
    LIMIT ? OFFSET ?
";

$params_data = $params;
$types_data  = $types . 'ii';
$params_data[] = $por_pagina;
$params_data[] = $offset;

$stmt = $conn->prepare($sql_data);
$stmt->bind_param($types_data, ...$params_data);
$stmt->execute();
$result = $stmt->get_result();

// ----------------------------
// Transformar resultados
// ----------------------------
$tickets = [];

while ($row = $result->fetch_assoc()) {
    // Estado → label + clase
    $estado_bd = strtolower(trim($row['estado']));
    $status_label = 'Abierto';
    $status_key   = 'open';

    switch ($estado_bd) {
        case 'en_proceso':
        case 'en proceso':
        case 'in_progress':
            $status_label = 'En proceso';
            $status_key   = 'in-progress';
            break;
        case 'resuelto':
        case 'resuelto_ok':
        case 'resolved':
            $status_label = 'Resuelto';
            $status_key   = 'resolved';
            break;
        case 'cerrado':
        case 'closed':
            $status_label = 'Cerrado';
            $status_key   = 'closed';
            break;
        case 'abierto':
        case 'open':
        default:
            $status_label = 'Abierto';
            $status_key   = 'open';
            break;
    }

    // Prioridad → label + clase
    $prio_bd = strtolower(trim($row['prioridad'] ?? ''));
    $priority_label = 'Normal';
    $priority_key   = 'normal';

    switch ($prio_bd) {
        case 'alta':
        case 'high':
            $priority_label = 'Alta';
            $priority_key   = 'high';
            break;
        case 'media':
        case 'medio':
        case 'medium':
            $priority_label = 'Media';
            $priority_key   = 'medium';
            break;
        case 'baja':
        case 'low':
            $priority_label = 'Baja';
            $priority_key   = 'low';
            break;
        default:
            $priority_label = ucfirst($prio_bd ?: 'Normal');
            $priority_key   = 'normal';
            break;
    }

    // Fecha "última respuesta"
    $fecha_label = '';
    if (!empty($row['ultima'])) {
        $dt = new DateTime($row['ultima']);
        $fecha_label = $dt->format('M j, Y'); // Ej: Apr 9, 2023
    }

    // Preview corto (por si lo quieres usar después)
    $preview = trim($row['descripcion'] ?? '');
    if (function_exists('mb_strlen')) {
        if (mb_strlen($preview) > 80) {
            $preview = mb_substr($preview, 0, 80) . '…';
        }
    } else {
        if (strlen($preview) > 80) {
            $preview = substr($preview, 0, 80) . '…';
        }
    }

    $tickets[] = [
        'id'             => (int)$row['id'],
        'titulo'         => $row['titulo'],
        'categoria'      => $row['categoria'],
        'priority_label' => $priority_label,
        'priority_key'   => $priority_key,
        'status_label'   => $status_label,
        'status_key'     => $status_key,
        'last_response'  => $fecha_label,
        'preview'        => $preview,
    ];
}

$stmt->close();

// ----------------------------
// Render de la vista
// ----------------------------
incluirTemplate('head', [
    'page_title' => 'Mis Tickets',
    'page_desc'  => 'Listado de tickets creados por el agente'
]);

incluirTemplate('header');
?>

<main class="tickets-page">
    <a href="panel_agente.php" class="btn-1 btn-volver ticket-detail__back">← Volver</a>
    <section class="tickets-page__inner">
        <header class="tickets-toolbar">
            <form class="tickets-toolbar__search" method="GET">
                <input
                    type="hidden"
                    name="estado"
                    value="<?= htmlspecialchars($estado_filtro, ENT_QUOTES, 'UTF-8') ?>"
                >

                <input
                    class="tickets-toolbar__search-input"
                    type="text"
                    name="q"
                    placeholder="Buscar por título"
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                >
            </form>

            <form class="tickets-toolbar__filter" method="GET">
                <?php if ($search !== ''): ?>
                    <input
                        type="hidden"
                        name="q"
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    >
                <?php endif; ?>

                <select
                    class="tickets-toolbar__filter-select"
                    name="estado"
                    onchange="this.form.submit()"
                >
                    <option value="todos"      <?= $estado_filtro === 'todos'      ? 'selected' : '' ?>>Todos</option>
                    <option value="abierto"    <?= $estado_filtro === 'abierto'    ? 'selected' : '' ?>>Abierto</option>
                    <option value="en_proceso" <?= $estado_filtro === 'en_proceso' ? 'selected' : '' ?>>En proceso</option>
                    <option value="resuelto"   <?= $estado_filtro === 'resuelto'   ? 'selected' : '' ?>>Resuelto</option>
                    <option value="cerrado"    <?= $estado_filtro === 'cerrado'    ? 'selected' : '' ?>>Cerrado</option>
                </select>
            </form>
        </header>

        <section class="tickets-table-card">
            <div class="tickets-table__wrapper">
                <table class="tickets-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Prioridad</th>
                            <th>Estatus</th>
                            <th>Última respuesta</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="6" class="tickets-table__empty">
                                No hay tickets para este filtro.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                            <tr
                                class="tickets-table__row tickets-table__row--clickable"
                                onclick="window.location.href='detalle_ticket.php?id=<?= (int)$t['id'] ?>'"
                            >
                                <td class="tickets-table__cell-id">
                                    #<?= (int)$t['id'] ?>
                                </td>
                                <td class="tickets-table__cell-title">
                                    <?= htmlspecialchars($t['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($t['categoria'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <span class="priority-pill priority-pill--<?= htmlspecialchars($t['priority_key'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($t['priority_label'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill status-pill--<?= htmlspecialchars($t['status_key'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($t['status_label'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($t['last_response'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_paginas > 1): ?>
                <footer class="tickets-pagination">
                    <?php for ($p = 1; $p <= $total_paginas; $p++): ?>
                        <?php
                            // Reconstruimos la query manteniendo filtros
                            $query = [
                                'page'   => $p,
                                'estado' => $estado_filtro,
                            ];
                            if ($search !== '') {
                                $query['q'] = $search;
                            }
                            $url = 'ver_mis_tickets.php?' . http_build_query($query);
                        ?>
                        <a
                            href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
                            class="page-pill <?= $p === $pagina_actual ? 'is-active' : '' ?>"
                        >
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </footer>
            <?php endif; ?>
        </section>
    </section>
</main>

<?php incluirTemplate('footer'); ?>
