<?php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: index.php?error=Acceso denegado");
    exit;
}

// ---------------------------
// Filtros (GET)
// ---------------------------
$filtros = [
    'nombre'     => $_GET['nombre']     ?? '',
    'correo'     => $_GET['correo']     ?? '',
    'rol'        => $_GET['rol']        ?? '',
    'campana'    => $_GET['campana']    ?? '',
    'puesto'     => $_GET['puesto']     ?? '',
    'estacion'   => $_GET['estacion']   ?? '',
    'estado'     => $_GET['estado']     ?? '',
    'creado_en'  => $_GET['creado_en']  ?? '',
    'biblioteca' => $_GET['biblioteca'] ?? '',
];

// ---------------------------
// Paginación
// ---------------------------
$perPage = 10;
$page    = (isset($_GET['page']) && ctype_digit($_GET['page'])) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $perPage;

// ---------------------------
// Construir WHERE dinámico
// ---------------------------
$where  = [];
$params = [];
$types  = '';

if ($filtros['nombre'] !== '') {
    $where[] = "nombre LIKE ?";
    $params[] = "%" . $filtros['nombre'] . "%";
    $types .= 's';
}
if ($filtros['correo'] !== '') {
    $where[] = "correo LIKE ?";
    $params[] = "%" . $filtros['correo'] . "%";
    $types .= 's';
}
if ($filtros['rol'] !== '') {
    $where[] = "rol = ?";
    $params[] = $filtros['rol'];
    $types .= 's';
}
if ($filtros['campana'] !== '') {
    $where[] = "campana LIKE ?";
    $params[] = "%" . $filtros['campana'] . "%";
    $types .= 's';
}
if ($filtros['puesto'] !== '') {
    $where[] = "puesto LIKE ?";
    $params[] = "%" . $filtros['puesto'] . "%";
    $types .= 's';
}
if ($filtros['estacion'] !== '') {
    $where[] = "estacion LIKE ?";
    $params[] = "%" . $filtros['estacion'] . "%";
    $types .= 's';
}
if ($filtros['estado'] !== '') {
    $where[] = "activo = ?";
    $params[] = ($filtros['estado'] === '1') ? 1 : 0;
    $types .= 'i';
}
if ($filtros['creado_en'] !== '') {
    $where[] = "creado_en LIKE ?";
    $params[] = "%" . $filtros['creado_en'] . "%";
    $types .= 's';
}
if ($filtros['biblioteca'] !== '') {
    $where[] = "acceso_biblioteca = ?";
    $params[] = ($filtros['biblioteca'] === '1') ? 1 : 0;
    $types .= 'i';
}

$countSql = "
    SELECT COUNT(*) AS total
    FROM usuarios u
    LEFT JOIN usuarios_eliminados ue ON ue.id_usuario = u.id
";

$whereCount = $where;
$whereCount[] = "ue.id_usuario IS NULL";

if (!empty($whereCount)) {
    $countSql .= " WHERE " . implode(" AND ", $whereCount);
}
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRows = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

// ---------------------------
// 2) Datos con LIMIT/OFFSET
// ---------------------------

$dataSql = "
    SELECT u.*
    FROM usuarios u
    LEFT JOIN usuarios_eliminados ue ON ue.id_usuario = u.id
";

$whereFinal = $where;

// SIEMPRE excluir eliminados
$whereFinal[] = "ue.id_usuario IS NULL";

if (!empty($whereFinal)) {
    $dataSql .= " WHERE " . implode(" AND ", $whereFinal);
}

$dataSql .= " ORDER BY u.creado_en DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($dataSql);

if (!empty($params)) {
    $typesData  = $types . 'ii';
    $paramsData = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($typesData, ...$paramsData);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}


$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once __DIR__ . '/../includes/funciones.php';
incluirTemplate('head', [
    'page_title' => 'Usuarios',
    'page_desc'  => 'Panel para listar a los usuarios'
]);
incluirTemplate('header');

// helper para mantener querystring en paginación
function qs_keep(array $extra = []) {
    $q = $_GET;
    unset($q['page']);
    $q = array_merge($q, $extra);
    return http_build_query($q);
}
?>

<main class="admin-tickets-page users-page">
    <a href="panel_tecnico.php" class="btn-1 btn-volver ticket-detail__back">← Volver</a>
    <section class="admin-tickets__inner">
        <header class="admin-tickets__header">
            <div class="admin-tickets__title-group">
                <h1 class="admin-tickets__title">Administrar Usuarios</h1>
                <p class="admin-tickets__subtitle">
                    Filtra, revisa y administra acceso de usuarios (incluye biblioteca).
                </p>
            </div>
        </header>

        <section class="admin-tickets-card">
            <form method="GET" class="users-filters">
                <div class="admin-tickets-table__wrapper">
                    <table class="admin-tickets-table users-table">
                        <thead>
                            <tr class="users-table__filters">
                                <th class="users-table__th-id">ID</th>

                                <th>
                                    <input class="users-filter__input" type="text" name="nombre"
                                           placeholder="Nombre"
                                           value="<?= htmlspecialchars($filtros['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                </th>

                                <th>
                                    <input class="users-filter__input" type="text" name="correo"
                                           placeholder="Correo"
                                           value="<?= htmlspecialchars($filtros['correo'], ENT_QUOTES, 'UTF-8') ?>">
                                </th>

                                <th>
                                    <input class="users-filter__input" type="text" name="campana"
                                           placeholder="Campaña"
                                           value="<?= htmlspecialchars($filtros['campana'], ENT_QUOTES, 'UTF-8') ?>">
                                </th>

                                <th>
                                    <select class="users-filter__select" name="biblioteca">
                                        <option value="">Biblioteca</option>
                                        <option value="1" <?= $filtros['biblioteca'] === '1' ? 'selected' : '' ?>>Permitido</option>
                                        <option value="0" <?= $filtros['biblioteca'] === '0' ? 'selected' : '' ?>>Bloqueado</option>
                                    </select>
                                </th>

                                <th class="users-table__th-actions">
                                    <button type="submit" class="btn-primary users-filter__btn">
                                        Filtrar
                                    </button>
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($usuarios)): ?>
                                <tr>
                                    <td colspan="11" class="admin-tickets-table__empty">
                                        No se encontraron usuarios con esos filtros.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $u): ?>
                                    <?php
                                        $isActive  = !empty($u['activo']);
                                        $permiteBib = !empty($u['acceso_biblioteca']);

                                        $estadoTxt = $isActive ? 'Activo' : 'Inactivo';
                                        $estadoClass = $isActive ? 'status-pill--open' : 'status-pill--closed'; // reusa colores

                                        $bibTxt = $permiteBib ? 'Permitido' : 'Bloqueado';
                                        $bibClass = $permiteBib ? 'status-pill--resolved' : 'status-pill--closed';
                                    ?>
                                    <tr
                                        class="admin-tickets-table__row admin-tickets-table__row--clickable"
                                        onclick="window.location='editar_usuario.php?id=<?= (int)$u['id'] ?>';"
                                    >
                                        <td class="admin-tickets-table__cell-id">#<?= (int)$u['id'] ?></td>
                                        <td><?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($u['correo'], ENT_QUOTES, 'UTF-8') ?></td>                            
                                        <td><?= htmlspecialchars($u['campana'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="status-pill <?= $bibClass ?>">
                                                <?= $bibTxt ?>
                                            </span>
                                        </td>
                                        <td onclick="event.stopPropagation();">
                                            <a href="eliminar_usuario.php?id=<?= (int)$u['id'] ?>"
                                            class="btn-ghost-small btn-ghost-small--danger js-eliminar-usuario"
                                            data-id="<?= (int)$u['id'] ?>">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <?php if ($totalPages > 1): ?>
                <nav class="admin-tickets__pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a
                            href="?<?= htmlspecialchars(qs_keep(['page' => $p]), ENT_QUOTES, 'UTF-8') ?>"
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
