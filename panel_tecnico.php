<?php
include __DIR__ . '/includes/config/verificar_sesion.php';
include __DIR__ . '/includes/config/conexion.php';

if ($_SESSION['rol'] !== 'tecnico') {
    header("Location: login.php?error=Acceso denegado");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Técnico</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 30px;
            background-color: #f7f7f7;
        }

        h2 {
            color: #222;
        }

        .opciones {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }

        .opciones a {
            text-decoration: none;
            padding: 12px 20px;
            background-color: #007bff;
            color: white;
            font-weight: bold;
            border-radius: 6px;
            transition: background-color 0.2s ease-in-out;
            max-width: 400px;
        }

        .opciones a:hover {
            background-color: #0056b3;
        }

        .logout {
            background-color: #dc3545 !important;
        }

        .logout:hover {
            background-color: #c82333 !important;
        }
    </style>
</head>
<body>
    <?php
        $alerta = $conn->query("
            SELECT prioridad, mensaje, creado_en 
            FROM notificaciones 
            WHERE leido = FALSE 
            ORDER BY creado_en DESC 
            LIMIT 1
        ")->fetch_assoc();
    ?>
    
    <h2>🔧 Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?> (Área de TI)</h2>

    <!-- Alerta dinámica -->
    <div id="alertaDinamica"></div>

    <?php if ($alerta): ?>
        <div style="
            background-color: <?= $alerta['prioridad'] === 'alta' ? '#f8d7da' : ($alerta['prioridad'] === 'media' ? '#fff3cd' : '#d1ecf1') ?>;
            color: #000;
            padding: 12px;
            border-left: 5px solid <?= $alerta['prioridad'] === 'alta' ? '#dc3545' : ($alerta['prioridad'] === 'media' ? '#ffc107' : '#17a2b8') ?>;
            margin-bottom: 20px;
            border-radius: 4px;
            animation: parpadeo <?= $alerta['prioridad'] === 'alta' ? '2s' : ($alerta['prioridad'] === 'media' ? '6s' : '10s') ?> infinite;
        ">
            ⚠️ <?= htmlspecialchars($alerta['mensaje']) ?> (<?= ucfirst($alerta['prioridad']) ?>)
        </div>

        <style>
            @keyframes parpadeo {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.3; }
            }
        </style>
    <?php endif; ?>

    <div class="opciones">
                <!-- Agrega este botón donde quieras mostrarlo -->
        <a href="#" onclick="abrirModalNotificaciones()" style="background:#28a745" class="btn-notificaciones">🔔 Ver notificaciones</a>
        <!-- Modal -->
        <div id="modalNotificaciones" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#00000066; z-index:9999; justify-content:center; align-items:center;">
            <div style="background:#fff; width:90%; max-width:800px; padding:20px; border-radius:8px; position:relative;">
                <button onclick="cerrarModal()" style="position:absolute; top:10px; right:15px; background:#dc3545; color:white; border:none; padding:5px 10px; border-radius:4px;">Cerrar ✖</button>
                <div id="contenidoNotificaciones">Cargando notificaciones...</div>
            </div>
        </div>

        <a href="admin_tickets.php">📋 Ver y administrar todos los tickets</a>
        <a href="fallas_comunes_admin.php">📚 Subir y editar guías de fallas comunes</a>
        <a href="crear_usuario.php">👥 Crear nuevos usuarios</a>
        <a href="resetear_contraseña.php">🔐 Resetear contraseñas de usuarios</a>
        <a href="logout.php" class="logout">🚪 Cerrar sesión</a>
    </div>

<script>
    function abrirModalNotificaciones() {
        const modal = document.getElementById("modalNotificaciones");
        const contenido = document.getElementById("contenidoNotificaciones");
        modal.style.display = "flex";

        fetch("notificaciones.php")
            .then(response => response.text())
            .then(html => {
                contenido.innerHTML = html;
            })
            .catch(error => {
                contenido.innerHTML = "<p>Error al cargar las notificaciones.</p>";
                console.error(error);
            });
    }

    function cerrarModal() {
        document.getElementById("modalNotificaciones").style.display = "none";
    }

    function marcarNotificacionesLeidas() {
        fetch("notificaciones.php?marcar=1")
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    abrirModalNotificaciones(); // Recargar el contenido del modal
                    location.reload(); // Refresca alerta superior si es necesario
                }
            });
    }
</script>

<script>
function mostrarAlerta(mensaje, prioridad) {
    const colorFondo = {
        alta: '#f8d7da',
        media: '#fff3cd',
        baja: '#d1ecf1'
    }[prioridad] || '#e2e3e5';

    const colorBorde = {
        alta: '#dc3545',
        media: '#ffc107',
        baja: '#17a2b8'
    }[prioridad] || '#6c757d';

    document.getElementById("alertaDinamica").innerHTML = `
        <div style="
            background-color: ${colorFondo};
            color: #000;
            padding: 12px;
            border-left: 5px solid ${colorBorde};
            margin-bottom: 20px;
            border-radius: 4px;
            animation: parpadeo ${prioridad === 'alta' ? '2s' : (prioridad === 'media' ? '6s' : '10s')} infinite;
        ">
            ⚠️ ${mensaje} (${prioridad.charAt(0).toUpperCase() + prioridad.slice(1)})
        </div>
    `;
}

function verificarNuevaNotificacion() {
    fetch("notificaciones_alerta.php")
        .then(res => res.json())
        .then(data => {
            if (data) {
                mostrarAlerta(data.mensaje, data.prioridad);
            } else {
                document.getElementById("alertaDinamica").innerHTML = "";
            }
        })
        .catch(err => console.error("Error al consultar notificaciones:", err));
}

setInterval(verificarNuevaNotificacion, 2000); // cada 5 segundos
verificarNuevaNotificacion(); // ejecuta al cargar
</script>

<style>
@keyframes parpadeo {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}
</style>

</body>
</html>
