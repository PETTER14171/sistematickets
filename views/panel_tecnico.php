<?php
// views/panel_tecnico.php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: ../index.php?error=Acceso denegado");
    exit;
}

require_once __DIR__ . '/../includes/funciones.php';
incluirTemplate('head', [
    'page_title' => 'Panel Técnico',
    'page_desc'  => 'Panel para que el área técnica administre tickets, soluciones y biblioteca'
]);
incluirTemplate('header');

// ======================
//  MÉTRICAS RÁPIDAS
// ======================

// Total de tickets
$totalTickets = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM tickets");
if ($res) {
    $row = $res->fetch_assoc();
    $totalTickets = (int)($row['c'] ?? 0);
}

// Tickets en progreso
$ticketsEnProgreso = 0;
$res = $conn->query("
    SELECT COUNT(*) AS c
    FROM tickets
    WHERE estado IN ('en_proceso', 'en proceso', 'in_progress')
");
if ($res) {
    $row = $res->fetch_assoc();
    $ticketsEnProgreso = (int)($row['c'] ?? 0);
}

// Tickets abiertos (pendientes)
$pendingTickets = 0;
$res = $conn->query("
    SELECT COUNT(*) AS c
    FROM tickets
    WHERE estado IN ('abierto', 'open')
");
if ($res) {
    $row = $res->fetch_assoc();
    $pendingTickets = (int)($row['c'] ?? 0);
}

// Elementos en biblioteca
$libraryItems = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM libros");
if ($res) {
    $row = $res->fetch_assoc();
    $libraryItems = (int)($row['c'] ?? 0);
}

// Notificaciones no leídas
$newNotifications = 0;
$res = $conn->query("SELECT COUNT(*) AS c FROM notificaciones WHERE leido = 0");
if ($res) {
    $row = $res->fetch_assoc();
    $newNotifications = (int)($row['c'] ?? 0);
}

// Notificación más reciente (para la “campanita”)
$latestAlert = $conn->query("
    SELECT prioridad, mensaje, creado_en 
    FROM notificaciones 
    ORDER BY creado_en DESC
    LIMIT 1
")?->fetch_assoc();

// Listado de notificaciones recientes (sidebar derecha)
$recentNotifications = [];
$res = $conn->query("
    SELECT prioridad, mensaje, creado_en 
    FROM notificaciones 
    ORDER BY creado_en DESC
    LIMIT 5
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recentNotifications[] = $row;
    }
}
?>

<main class="admin-layout">
    <!-- SIDEBAR -->
    <aside class="admin-sidebar" aria-label="Menú de administración">
        <div class="admin-sidebar__header">
            <div class="admin-avatar admin-avatar--small">
                <span><?= strtoupper(mb_substr($_SESSION['nombre'] ?? 'U', 0, 1)) ?></span>
            </div>
            <div class="admin-sidebar__brand">
                <span class="admin-sidebar__title">Admin</span>
                <span class="admin-sidebar__subtitle">Panel Técnico</span>
            </div>
        </div>

        <nav class="admin-nav">
            <a href="admin_notificaciones.php" class="admin-nav__item">
                <span class="admin-nav__icon">🔔</span>
                <span>Notificaciones</span>
                <?php if ($newNotifications > 0): ?>
                    <span class="admin-nav__badge"><?= $newNotifications ?></span>
                <?php endif; ?>
            </a>

            <a href="admin_tickets.php" class="admin-nav__item admin-nav__item--active">
                <span class="admin-nav__icon">💬</span>
                <span>Tickets</span>
            </a>

            <a href="fallas_comunes_admin.php" class="admin-nav__item">
                <span class="admin-nav__icon">📘</span>
                <span>Soluciones</span>
            </a>

            <a href="crear_usuario.php" class="admin-nav__item">
                <span class="admin-nav__icon">👥</span>
                <span>Crear Usuario</span>
            </a>

            <a href="admin_biblioteca_subir.php" class="admin-nav__item">
                <span class="admin-nav__icon">📚</span>
                <span>Biblioteca</span>
            </a>
        </nav>

        <div class="admin-sidebar__footer">
            <a href="logout.php" class="admin-nav__item admin-nav__item--secondary">
                <span class="admin-nav__icon">🚪</span>
                <span>Cerrar Sesion</span>
            </a>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <section class="admin-main">
        <!-- Header del panel -->
        <header class="admin-main__header">
            <div>
                <h1 class="admin-main__title">Admin Dashboard</h1>
                <p class="admin-main__subtitle">
                    Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?>. Aquí puedes gestionar tickets, soluciones y la biblioteca.
                </p>
            </div>
            <div class="admin-header__actions">
                <button class="admin-icon-btn js-admin-bell" type="button" aria-label="Ver notificaciones">
                    🔔
                </button>
                <div class="admin-avatar admin-avatar--small">
                    <span><?= strtoupper(mb_substr($_SESSION['nombre'] ?? 'U', 0, 1)) ?></span>
                </div>
            </div>

        </header>

        <!-- Alerta dinámica (si tienes JS que la llena por AJAX) -->
        <div id="alertaDinamica" class="admin-alert-placeholder"></div>

        <!-- GRID SUPERIOR DE TARJETAS -->
        <section class="admin-grid admin-grid--top">
            <article class="admin-card admin-card--stat js-card-link" data-link="admin_tickets.php">
                <div class="admin-card__label">Total Tickets</div>
                <div class="admin-card__value"><?= $totalTickets ?></div>
            </article>

            <article class="admin-card admin-card--stat js-card-link" data-link="admin_tickets.php?estado=en_proceso">
                <div class="admin-card__label">
                    Tickets en Progreso
                    <?php if ($latestAlert): ?>
                        <span class="admin-card__icon-tip" title="<?= htmlspecialchars($latestAlert['mensaje']) ?>">
                            
                        </span>
                    <?php endif; ?>
                </div>
                <div class="admin-card__value"><?= $ticketsEnProgreso ?></div>
            </article>

            <article class="admin-card admin-card--stat js-card-link" data-link="admin_notificaciones.php">
                <div class="admin-card__label">Nuevas Notificaciones</div>
                <div class="admin-card__value"><?= $newNotifications ?></div>
            </article>
        </section>

        <section class="admin-grid admin-grid--middle">
            <article class="admin-card js-card-link" data-link="admin_tickets.php?estado=abierto">
                <div class="admin-card__label">Tickets Pendientes</div>
                <div class="admin-card__value"><?= $pendingTickets ?></div>
                <div class="admin-card__progress">
                    <span style="width: <?= $totalTickets > 0 ? min(100, ($pendingTickets / max(1, $totalTickets)) * 100) : 0; ?>%"></span>
                </div>
            </article>
        </section>


        <!-- GRID INFERIOR: Acciones a la izquierda, notificaciones a la derecha -->
        <section class="admin-grid admin-grid--bottom">
            <div class="admin-stack">
                <a href="admin_tickets.php" class="admin-row-link">
                    <span>Administrar Tickets</span>
                    <span class="admin-row-link__icon">›</span>
                </a>
                <a href="fallas_comunes_admin.php" class="admin-row-link">
                    <span>Administar Soluciones</span>
                    <span class="admin-row-link__icon">›</span>
                </a>
                <a href="usuarios.php" class="admin-row-link">
                    <span>Administrar Usuarios</span>
                    <span class="admin-row-link__icon">›</span>
                </a>
                <a href="admin_biblioteca_subir.php" class="admin-row-link">
                    <span>Administrar Biblioteca</span>
                    <span class="admin-row-link__icon">›</span>
                </a>
                <a href="resetear_contraseña.php" class="admin-row-link">
                    <span>Cambiar Contraseña</span>
                    <span class="admin-row-link__icon">›</span>
                </a>
            </div>

            <aside class="admin-card admin-card--notifications js-notifications-wrapper">
                <h2 class="admin-card__heading">Notificaciones Recientes</h2>

                <?php if (empty($recentNotifications)): ?>
                    <p class="admin-card__empty">No hay notificaciones recientes.</p>
                <?php else: ?>
                    <ul class="admin-notifications js-notifications-list">
                        <?php foreach ($recentNotifications as $n): ?>
                            <li class="admin-notifications__item">
                                <div class="admin-notifications__icon">
                                    <?php
                                    $prio = strtolower($n['prioridad'] ?? '');
                                    echo $prio === 'alta' ? '⚠️' : ($prio === 'baja' ? 'ℹ️' : '🔔');
                                    ?>
                                </div>
                                <div class="admin-notifications__body">
                                    <p class="admin-notifications__text">
                                        <?= htmlspecialchars($n['mensaje']) ?>
                                    </p>
                                    <span class="admin-notifications__time">
                                        <?= date('d/m/Y H:i', strtotime($n['creado_en'])) ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </aside>
        </section>
    </section>
</main>

<?php incluirTemplate('footer'); ?>
