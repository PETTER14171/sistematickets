<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'agente') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}

$busqueda = '';
$resultados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $busqueda = trim($_POST['busqueda']);

    $query = "SELECT * FROM fallas_comunes 
              WHERE titulo LIKE ? OR categoria LIKE ? OR palabras_clave LIKE ?
              ORDER BY creado_en DESC";

    $stmt = $conn->prepare($query);
    $like = "%$busqueda%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}else {
    // Si no hay búsqueda, mostrar todas
    $query = "SELECT * FROM fallas_comunes ORDER BY id DESC";
    $resultados = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}
?>

<?php
require 'includes/funciones.php';
incluirTemplate ('header');
?>

<main>
    <div class="centrat-titulo_boton">
        <h3>🔍Buscar solución a una falla común</h3>
        <a href="/panel_agente.php" class="btn-1 btn-volver">← Volver</a>
    </div>
    <form method="POST" class="form-control">
        <input class="form-control-input" type="text" name="busqueda" placeholder="Ej: impresora, VPN, Outlook..." value="<?= htmlspecialchars($busqueda) ?>" required>
        <button class="button-input" type="submit">Buscar</button>
    </form>

    <?php if ($resultados): ?>
        <h1>Resultados encontrados:</h1>
            <div class="grid-fallas">
                <?php foreach ($resultados as $falla): ?>
                    <?php
                        $archivo = $falla['multimedia'];
                        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
                        $ruta = 'fallamultimedia/' . $archivo;
                    ?>
                    <div class="falla" onclick="abrirModalFalla(<?= $falla['id'] ?>)">
                        <h3><?= htmlspecialchars($falla['titulo']) ?></h3>

                        <?php if (!empty($archivo)): ?>
                            <?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <div class="media-wrapper">
                                    <img src="<?= $ruta ?>" alt="Multimedia de la falla">
                                </div>
                            <?php elseif (in_array($extension, ['mp4', 'webm', 'ogg'])): ?>
                                <div class="media-wrapper">
                                    <video muted autoplay loop>
                                        <source src="<?= $ruta ?>" type="video/<?= $extension ?>">
                                    </video>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Modal -->
                    <div id="modal-falla-<?= $falla['id'] ?>" class="modal-falla">
                        <div class="modal-contenido">
                            <button class="cerrar-modal" onclick="cerrarModalFalla(<?= $falla['id'] ?>)">✖</button>

                            <?php if (!empty($archivo)): ?>
                                <?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <div class="media-modal">
                                        <img src="<?= $ruta ?>" alt="Imagen de la falla">
                                    </div>
                                <?php elseif (in_array($extension, ['mp4', 'webm', 'ogg'])): ?>
                                    <div class="media-modal">
                                        <video controls>
                                            <source src="<?= $ruta ?>" type="video/<?= $extension ?>">
                                        </video>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <h3><?= htmlspecialchars($falla['titulo']) ?></h3>
                            <p><strong>Categoría:</strong> <?= htmlspecialchars($falla['categoria']) ?></p>
                            <p><strong>Descripción:</strong> <?= nl2br(htmlspecialchars($falla['descripcion'])) ?></p>
                            <p class="pasos-solucion"><strong>Pasos:</strong> <?= nl2br(htmlspecialchars($falla['pasos_solucion'])) ?></p>
                            <a href="crear_ticket.php?referencia=<?= $falla['id'] ?>" class="crear-ticket">🛠 No resolvió mi problema</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <p>No se encontraron coincidencias. Puedes <a href="crear_ticket.php">crear un ticket</a>.</p>
    <?php endif; ?>
</main>

<?php 
incluirTemplate('footer');
?>
