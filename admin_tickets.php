<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$stmt = $conn->prepare("
    SELECT t.*, u.nombre AS nombre_agente, f.titulo AS titulo_falla
    FROM tickets t
    JOIN usuarios u ON t.id_usuario = u.id
    LEFT JOIN fallas_comunes f ON t.referencia_falla = f.id
    ORDER BY t.creado_en DESC
");
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);
?>

<?php
require 'includes/funciones.php';
incluirTemplate ('header');
?>

<main>

    <div class="centrat-titulo_boton">
        <h3>📋 Administración de Tickets</h3>
        <a href="/panel_agente.php" class="btn-1 btn-volver">← Volver</a>
    </div>

    <?php if (count($tickets) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Agente</th>
                    <th>Categoría</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Falla relacionada</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td>#<?= $t['id'] ?></td>
                        <td><?= htmlspecialchars($t['titulo']) ?></td>
                        <td><?= htmlspecialchars($t['nombre_agente']) ?></td>
                        <td><?= htmlspecialchars($t['categoria']) ?></td>
                        <td><?= ucfirst($t['prioridad']) ?></td>
                        <td class="estado-<?= $t['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $t['estado'])) ?></td>
                        <td><?= $t['titulo_falla'] ? htmlspecialchars($t['titulo_falla']) : '-' ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($t['creado_en'])) ?></td>
                        <td>
                            <a href="responder_ticket.php?id=<?= $t['id'] ?>" class="boton-responder">Responder</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <p>No hay tickets registrados aún.</p>
    <?php endif; ?>
</main>
<?php 
incluirTemplate('footer');
?>
