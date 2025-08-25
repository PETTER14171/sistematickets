<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'agente') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];

/*
 Trae los tickets del usuario + última respuesta de cada ticket (mensaje, fecha y adjunto).
 La subconsulta rmax obtiene la fecha más reciente por ticket; se une para recuperar el registro completo.
*/
$sql = "
    SELECT 
        t.id,
        t.titulo,
        t.descripcion,
        t.categoria,
        t.prioridad,
        t.estado,
        t.creado_en,
        r.mensaje         AS ultima_respuesta,
        r.creado_en       AS ultima_respuesta_fecha,
        r.archivo_adjunto AS evidencia
    FROM tickets t
    LEFT JOIN (
        SELECT rt1.*
        FROM respuestas_ticket rt1
        INNER JOIN (
            SELECT ticket_id, MAX(creado_en) AS max_fecha
            FROM respuestas_ticket
            GROUP BY ticket_id
        ) rmax
          ON rmax.ticket_id = rt1.ticket_id
         AND rmax.max_fecha = rt1.creado_en
    ) r
      ON r.ticket_id = t.id
    WHERE t.id_usuario = ?
    ORDER BY t.creado_en DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);

require 'includes/funciones.php';
incluirTemplate('header');
?>

<main>
    <h2>📋 Mis tickets generados
        <a href="/fallas_comunes_admin.php" class="volver">Volver</a>
    </h2>

    <?php if (count($tickets) > 0): ?>
        <div class="margin-table"> 
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Última respuesta</th>
                        <th>Evidencia</th>
                        <th>Creado en</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['titulo']) ?></td>
                            <td><?= htmlspecialchars($t['categoria']) ?></td>
                            <td><?= ucfirst($t['prioridad']) ?></td>
                            <td class="estado-<?= htmlspecialchars($t['estado']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $t['estado'])) ?>
                            </td>

                            <!-- Última respuesta (mensaje + fecha si existe) -->
                            <td>
                                <?php if (!empty($t['ultima_respuesta'])): ?>
                                    <div class="ultima-respuesta">
                                        <div class="ultima-respuesta__msg">
                                            <?= nl2br(htmlspecialchars($t['ultima_respuesta'])) ?>
                                        </div>
                                        <div class="ultima-respuesta__fecha muted">
                                            <?= date('d/m/Y H:i', strtotime($t['ultima_respuesta_fecha'])) ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="muted">Sin respuestas</span>
                                <?php endif; ?>
                            </td>

                            <!-- Evidencia: botón de descarga si hay archivo; si no, alerta -->
                            <td>
                                <?php
                                    $file = $t['evidencia'] ?? '';
                                    // Seguridad: evita rutas arbitrarias
                                    $fileSafe = $file !== '' ? basename($file) : '';
                                    $ruta = '/adjuntos/' . rawurlencode($fileSafe);
                                ?>
                                <?php if ($fileSafe !== ''): ?>
                                    <a class="btn-1 btn-descargar" href="<?= htmlspecialchars($ruta) ?>" download>
                                        Descargar
                                    </a>
                                <?php else: ?>
                                    <button class="btn-1 btn-descargar is-disabled" type="button"
                                        onclick="alert('No hay evidencia adjunta');">
                                        Descargar
                                    </button>
                                <?php endif; ?>
                            </td>

                            <td><?= date('d/m/Y H:i', strtotime($t['creado_en'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No has generado ningún ticket aún.</p>
    <?php endif; ?>
</main>

<?php incluirTemplate('footer'); ?>
