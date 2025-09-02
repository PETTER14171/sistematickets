(() => {
  'use strict';

  // Cambia este marcador en cada despliegue para confirmar carga
  console.log('APP BUILD: 2025-09-02-01');

  /* =========================
   *  Notificaciones (modal)
   * ========================= */
  function abrirModalNotificaciones() {
    const modal = document.getElementById('modalNotificaciones');
    const contenido = document.getElementById('contenidoNotificaciones');
    if (!modal || !contenido) return;

    modal.style.display = 'flex';

    fetch('notificaciones.php')
      .then(response => response.text())
      .then(html => { contenido.innerHTML = html; })
      .catch(error => {
        contenido.innerHTML = '<p>Error al cargar las notificaciones.</p>';
        console.error(error);
      });
  }

  function cerrarModal() {
    const modal = document.getElementById('modalNotificaciones');
    if (modal) modal.style.display = 'none';
  }

  function marcarNotificacionesLeidas() {
    fetch('notificaciones.php?marcar=1')
      .then(response => response.json())
      .then(data => {
        if (data && data.success) {
          abrirModalNotificaciones(); // Recargar contenido del modal
          location.reload();          // Refrescar alerta superior si aplica
        }
      })
      .catch(err => console.error(err));
  }

  /* =========================
   *  Alertas dinámicas
   * ========================= */
  function mostrarAlerta(mensaje, prioridad) {
    const colorFondo = ({ alta:'#f8d7da', media:'#fff3cd', baja:'#d1ecf1' }[prioridad]) || '#e2e3e5';
    const colorBorde = ({ alta:'#dc3545', media:'#ffc107', baja:'#17a2b8' }[prioridad]) || '#6c757d';
    const duracion   = prioridad === 'alta' ? '2s' : (prioridad === 'media' ? '6s' : '10s');
    const etiqueta   = prioridad ? prioridad.charAt(0).toUpperCase() + prioridad.slice(1) : 'Info';

    return `
      <div style="
        background-color: ${colorFondo};
        color: #000;
        padding: 12px;
        border-left: 5px solid ${colorBorde};
        margin-bottom: 20px;
        border-radius: 4px;
        animation: parpadeo ${duracion} infinite;
      ">
        ⚠️ ${mensaje} (${etiqueta})
      </div>
    `;
  }

  function verificarNuevaNotificacion() {
    const contenedor = document.getElementById('alertaDinamica');
    if (!contenedor) return; // si no existe en esta página, salimos

    fetch('notificaciones_alerta.php')
      .then(res => res.json())
      .then(data => {
        if (Array.isArray(data) && data.length > 0) {
          contenedor.innerHTML = data.map(a => mostrarAlerta(a.mensaje, a.prioridad)).join('');
        } else {
          contenedor.innerHTML = '';
        }
      })
      .catch(err => console.error('Error al consultar notificaciones:', err));
  }

  // cada 2 segundos
  setInterval(verificarNuevaNotificacion, 2000);
  verificarNuevaNotificacion();


  /* =========================
   *  Modal de fallas comunes
   * ========================= */
  function abrirModalFalla(id) {
    const modal = document.getElementById('modal-falla-' + id);
    if (!modal) return;

    // Mostrar modal
    modal.style.display = 'flex';

    // Bloquear scroll del body detrás del modal
    document.body.style.overflow = 'hidden';

    // Cerrar al hacer click fuera del contenido
    modal.addEventListener('click', function backdrop(e) {
      if (e.target === modal) cerrarModalFalla(id);
    }, { once: true });

    // Cerrar con ESC
    const onEsc = (e) => { if (e.key === 'Escape') cerrarModalFalla(id); };
    modal._onEsc = onEsc; // guardamos ref para remover luego
    document.addEventListener('keydown', onEsc);
  }

  function cerrarModalFalla(id) {
    const modal = document.getElementById('modal-falla-' + id);
    if (!modal) return;

    modal.style.display = 'none';
    document.body.style.overflow = ''; // restaurar scroll body

    // Remover listener de ESC si existe
    if (modal._onEsc) {
      document.removeEventListener('keydown', modal._onEsc);
      delete modal._onEsc;
    }
  }

  // Exponer funciones llamadas desde HTML (onclick/atributos)
  window.abrirModalNotificaciones   = abrirModalNotificaciones;
  window.cerrarModal                = cerrarModal;
  window.marcarNotificacionesLeidas = marcarNotificacionesLeidas;
  window.abrirModalFalla            = abrirModalFalla;
  window.cerrarModalFalla           = cerrarModalFalla;


  /* =========================
   *  UI Condicional: modo / PDF
   * ========================= */
  // Mostrar/ocultar bloques según "modo"
  const modoSel     = document.getElementById('modo');
  const bloqueExist = document.getElementById('bloque-libro-existente');
  const bloqueNuevo = document.getElementById('bloque-libro-nuevo');
  const bloqueAutor = document.getElementById('bloque-autor');
  const bloqueCat   = document.getElementById('bloque-categoria');
  const bloqueDesc  = document.getElementById('bloque-descripcion');

  if (modoSel && bloqueExist && bloqueNuevo && bloqueAutor && bloqueCat && bloqueDesc) {
    const aplicarModo = () => {
      const esNuevo = (modoSel.value === 'nuevo');
      bloqueExist.style.display = esNuevo ? 'none'  : 'block';
      bloqueNuevo.style.display = esNuevo ? 'block' : 'none';
      bloqueAutor.style.display = esNuevo ? 'block' : 'none';
      bloqueCat.style.display   = esNuevo ? 'block' : 'none';
      bloqueDesc.style.display  = esNuevo ? 'block' : 'none';
    };
    modoSel.addEventListener('change', aplicarModo);
    aplicarModo(); // al cargar
  }

  // Info del PDF seleccionado
  const inputPdf = document.getElementById('pdf');
  const infoPdf  = document.getElementById('pdf-info');

  if (inputPdf && infoPdf) {
    inputPdf.addEventListener('change', () => {
      if (inputPdf.files && inputPdf.files[0]) {
        const f = inputPdf.files[0];
        infoPdf.textContent = `${f.name} — ${(f.size / 1024 / 1024).toFixed(2)} MB`;
      } else {
        infoPdf.textContent = '';
      }
    });
  }

})();
