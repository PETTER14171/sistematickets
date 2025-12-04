<?php
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: ../index.php?error=Debes+iniciar+sesion");
        exit;
    }

    $usuario_id = $_SESSION['usuario_id'];

    // Estructura base para los 4 estados
    $estatusResumen = [
        'abierto' => [
            'label'      => 'Abierto',
            'status_key' => 'open',
            'count'      => 0,
            'last_raw'   => null, // fecha cruda
            'date_label' => '',   // fecha formateada
        ],
        'en_proceso' => [
            'label'      => 'En proceso',
            'status_key' => 'in-progress',
            'count'      => 0,
            'last_raw'   => null,
            'date_label' => '',
        ],
        'resuelto' => [
            'label'      => 'Resuelto',
            'status_key' => 'resolved',
            'count'      => 0,
            'last_raw'   => null,
            'date_label' => '',
        ],
        'cerrado' => [
            'label'      => 'Cerrado',
            'status_key' => 'closed',
            'count'      => 0,
            'last_raw'   => null,
            'date_label' => '',
        ],
    ];

    // 1) Sacamos conteo + fecha más reciente por estado real en BD
    $sql = "
        SELECT 
            estado,
            COUNT(*) AS total,
            MAX(COALESCE(actualizado_en, creado_en)) AS last_update
        FROM tickets
        WHERE id_usuario = ?
        GROUP BY estado
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $estado_bd   = strtolower(trim($row['estado']));
        $total       = (int)$row['total'];
        $last_update = $row['last_update']; // string tipo '2025-11-30 10:20:00'

        // Normalizamos el estado de BD a uno de los 4 grupos
        $grupo = null;

        switch ($estado_bd) {
            case 'abierto':
            case 'open':
                $grupo = 'abierto';
                break;

            case 'en_proceso':
            case 'en proceso':
            case 'in_progress':
                $grupo = 'en_proceso';
                break;

            case 'resuelto':
            case 'resuelto_ok':
            case 'resolved':
                $grupo = 'resuelto';
                break;

            case 'cerrado':
            case 'closed':
                $grupo = 'cerrado';
                break;

            default:
                // Estados raros, de momento los ignoramos
                break;
        }

        if ($grupo && isset($estatusResumen[$grupo])) {
            // sumamos cantidad
            $estatusResumen[$grupo]['count'] += $total;

            // almacenamos la fecha más reciente dentro del grupo
            if ($last_update) {
                if ($estatusResumen[$grupo]['last_raw'] === null ||
                    $last_update > $estatusResumen[$grupo]['last_raw']) {
                    $estatusResumen[$grupo]['last_raw'] = $last_update;
                }
            }
        }
    }

    $stmt->close();

    // 2) Formateamos la fecha "bonita" por grupo usando last_raw
    $hoy = new DateTime('today');

    foreach ($estatusResumen as $key => &$info) {
        if ($info['last_raw']) {
            $dt = new DateTime($info['last_raw']);

            if ($dt->format('Y-m-d') === $hoy->format('Y-m-d')) {
                $info['date_label'] = 'Today';
            } else {
                // Formato tipo "Apr 20" como en el diseño
                $info['date_label'] = $dt->format('M d');
            }
        } else {
            $info['date_label'] = ''; // sin tickets en ese estado
        }
    }
    unset($info); // buena práctica al usar referencia
?>


<aside class="ticket-sidebar" aria-label="Resumen de tickets">
    <div class="ticket-sidebar__header">
        <h2 class="ticket-sidebar__title">Mis Tickets</h2>
    </div>

    <ul class="ticket-list">
        <?php foreach ($estatusResumen as $estado_key => $info): ?>
            <li
                class="ticket-item"
                tabindex="0"
                onclick="window.location.href='ver_mis_tickets.php?estado=<?= urlencode($estado_key) ?>'"
            >
                <!-- Icono y estado -->
                <div class="ticket-item__status">
                    <span class="status-dot status-dot--<?= htmlspecialchars($info['status_key']) ?>"></span>
                    <span class="ticket-item__status-text">
                        <?= htmlspecialchars($info['label']) ?>
                    </span>
                </div>

                <!-- Cuerpo (segunda línea: número de tickets) -->
                <div class="ticket-item__body">
                    <p class="ticket-item__title">
                        <?php if ($info['date_label'] && $info['count'] > 0): ?>
                            <?= htmlspecialchars($info['date_label']) ?>
                        <?php else: ?>
                            Haz clic para ver detalles
                        <?php endif; ?>
                    </p>
                    <p class="ticket-item__preview">
                        <?php if ($info['count'] > 0): ?>
                            <?= (int)$info['count'] . ' ' . ($info['count'] === 1 ? 'ticket' : 'tickets') ?>
                        <?php else: ?>
                            Sin tickets aún
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Fecha a la derecha (como en la imagen) -->
                <div class="ticket-item__meta">
                    <span class="ticket-item__date">
                    
                    </span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>

