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

    return `
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
            const contenedor = document.getElementById("alertaDinamica");
            if (Array.isArray(data) && data.length > 0) {
                contenedor.innerHTML = data.map(alerta => 
                    mostrarAlerta(alerta.mensaje, alerta.prioridad)
                ).join("");
            } else {
                contenedor.innerHTML = "";
            }
        })
        .catch(err => console.error("Error al consultar notificaciones:", err));
}


setInterval(verificarNuevaNotificacion, 2000); // cada 5 segundos
verificarNuevaNotificacion(); // ejecuta al cargar