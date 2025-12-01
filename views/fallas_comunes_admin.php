<?php
include __DIR__ . '/../includes/config/verificar_sesion.php';
include __DIR__ . '/../includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: index.php?error=Acceso denegado");
    exit;
}

$stmt = $conn->prepare("
    SELECT f.*, u.nombre AS autor
    FROM fallas_comunes f
    JOIN usuarios u ON f.creado_por = u.id
    ORDER BY f.creado_en DESC
");
$stmt->execute();
$fallas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<?php
    require_once __DIR__ . '/../includes/funciones.php';
    incluirTemplate('head', [
        'page_title' => 'Fallas Admin',
        'page_desc'  => 'Panel para que el Tecnico administre las documentaciones'
    ]);
    incluirTemplate('header');
?>

<main >
    <div class="centrat-titulo_boton">
        <h3>📚 Gestión de Fallas Comunes</h3>
        <a href="panel_agente.php" class="btn-1 btn-volver">← Volver</a>
    </div>

    <a href="crear_falla.php" class="crear-btn">➕ Nueva Falla Común</a>

    <?php if (count($fallas) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Categoría</th>
                    <th>Palabras clave</th>
                    <th>Descripción</th>
                    <th>Pasos de solución</th>
                    <th>Autor</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fallas as $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['titulo']) ?></td>
                        <td><?= htmlspecialchars($f['categoria']) ?></td>
                        <td>
                            <?= strlen($f['palabras_clave']) > 30 
                                ? nl2br(substr(htmlspecialchars($f['palabras_clave']), 0, 30)) . '...' 
                                : nl2br(htmlspecialchars($f['palabras_clave'])) 
                            ?>
                        </td>
                        <td>
                            <?= strlen($f['descripcion']) > 30 
                                ? nl2br(substr(htmlspecialchars($f['descripcion']), 0, 30)) . '...' 
                                : nl2br(htmlspecialchars($f['descripcion'])) 
                            ?>
                        </td>
                        <td>
                        <?= strlen($f['pasos_solucion']) > 30 
                                ? nl2br(substr(htmlspecialchars($f['pasos_solucion']), 0, 30)) . '...' 
                                : nl2br(htmlspecialchars($f['pasos_solucion'])) 
                        ?>
                        </td>
                        <td><?= htmlspecialchars($f['autor']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($f['creado_en'])) ?></td>
                        <td class="acciones">
                            <a href="editar_falla.php?id=<?= $f['id'] ?>" class="editar">Editar</a>
                            <a href="eliminar_falla.php?id=<?= $f['id'] ?>" class="eliminar" onclick="return confirm('¿Eliminar esta guía?')">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
    <?php else: ?>
        <p>No hay guías registradas aún.</p>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado'): ?>
        <div class="mensaje" style="background:#f8d7da;color:#721c24;padding:10px;border:1px solid #f5c6cb;margin-bottom:15px;">
            ✅ Guía eliminada correctamente.
        </div>
    <?php endif; ?>
</main>

<?php 
incluirTemplate('footer');
?>
