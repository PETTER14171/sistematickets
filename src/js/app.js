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


})();

  /* ============================
   *  Interfaz de visualizar pdf
   * ============================*/

  
document.addEventListener('DOMContentLoaded', () => {
  const frame      = document.querySelector('.js-pdf-frame');
  const zoomLabel  = document.querySelector('.js-pdf-zoom-label');
  const btnZoomIn  = document.querySelector('.js-pdf-zoom-in');
  const btnReset   = document.querySelector('.js-pdf-zoom-reset');
  const btnSearch  = document.querySelector('.js-pdf-search-tip');

  if (!frame || !zoomLabel || !btnZoomIn || !btnReset) {
    return; // si algo no existe, no hacemos nada
  }

  // --- Estado de zoom ---
  let zoom = 1;        // 100% inicial
  const MIN_ZOOM = 0.6;
  const MAX_ZOOM = 2.0;
  const STEP     = 0.1;

  function applyZoom() {
    // Escalamos el iframe visualmente
    frame.style.transform = `scale(${zoom})`;
    frame.style.transformOrigin = 'top center';

    // Actualizamos el texto del indicador
    zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
  }

  // Aplicamos el zoom inicial
  applyZoom();

  // --- Eventos de los botones ---
  btnZoomIn.addEventListener('click', () => {
    zoom = Math.min(MAX_ZOOM, +(zoom + STEP).toFixed(2));
    applyZoom();
  });

  // El cuadrado resetea a 100%
  btnReset.addEventListener('click', () => {
    zoom = 1.0;
    applyZoom();
  });

  // --- Atajos de teclado: Ctrl + / Ctrl - / Ctrl 0 ---
  document.addEventListener('keydown', (ev) => {
    const isCtrl = ev.ctrlKey || ev.metaKey; // Cmd en Mac

    // Ctrl + / =  Zoom in
    if (isCtrl && (ev.key === '+' || ev.key === '=')) {
      ev.preventDefault();
      zoom = Math.min(MAX_ZOOM, +(zoom + STEP).toFixed(2));
      applyZoom();
    }

    // Ctrl - = Zoom out
    if (isCtrl && ev.key === '-') {
      ev.preventDefault();
      zoom = Math.max(MIN_ZOOM, +(zoom - STEP).toFixed(2));
      applyZoom();
    }

    // Ctrl 0 = reset
    if (isCtrl && ev.key === '0') {
      ev.preventDefault();
      zoom = 1.0;
      applyZoom();
    }
  });

  // --- Botón de “buscar” (tip para el usuario) ---
  if (btnSearch) {
    btnSearch.addEventListener('click', () => {
      alert('Tip: usa Ctrl + F (Cmd + F en Mac) para buscar dentro del documento.');
    });
  }
});


  /* ============================
   *  Funciones del panel admin
   * ============================*/

  // public/js/panel-tecnico.js
document.addEventListener('DOMContentLoaded', () => {
  // 1) Tarjetas clickeables (stats / middle)
  const clickableCards = document.querySelectorAll('.js-card-link');

  clickableCards.forEach(card => {
    const link = card.dataset.link;
    if (!link) return;

    card.classList.add('admin-card--clickable');

    card.addEventListener('click', () => {
      window.location.href = link;
    });
  });

  // 2) Botón de búsqueda del header
  const btnSearch = document.querySelector('.js-admin-search');
  if (btnSearch) {
    btnSearch.addEventListener('click', () => {
      const term = window.prompt('Buscar tickets por título o descripción:');
      if (term && term.trim() !== '') {
        const q = encodeURIComponent(term.trim());
        window.location.href = `admin_tickets.php?q=${q}`;
      }
    });
  }

  // 3) Botón de campana del header -> scroll a notificaciones + highlight
  const btnBell = document.querySelector('.js-admin-bell');
  const notifWrapper = document.querySelector('.js-notifications-wrapper');

  if (btnBell && notifWrapper) {
    btnBell.addEventListener('click', () => {
      notifWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
      notifWrapper.classList.add('admin-card--highlight');

      setTimeout(() => {
        notifWrapper.classList.remove('admin-card--highlight');
      }, 1800);
    });
  }

  // 4) Scroll en notificaciones cuando haya más de 5
  const notifList = document.querySelector('.js-notifications-list');
  if (notifList) {
    const items = notifList.querySelectorAll('.admin-notifications__item');

    if (items.length > 5) {
      notifList.classList.add('has-scroll');
    }
  }
});


  /* ============================
   *  Navegacion Responsiva
   * ============================*/

document.addEventListener('DOMContentLoaded', () => {
  const navToggle = document.getElementById('navToggle');
  const mainNav   = document.getElementById('mainNav');

  if (!navToggle || !mainNav) return;

  // Abrir/cerrar menú
  navToggle.addEventListener('click', () => {
    const isOpen = mainNav.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  // Cerrar menú al hacer clic en un enlace (en móvil)
  mainNav.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 900 && mainNav.classList.contains('open')) {
        mainNav.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    });
  });

  // Cerrar menú si se cambia a escritorio
  window.addEventListener('resize', () => {
    if (window.innerWidth > 900 && mainNav.classList.contains('open')) {
      mainNav.classList.remove('open');
      navToggle.setAttribute('aria-expanded', 'false');
    }
  });

  // Opcional: cerrar con Esc
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mainNav.classList.contains('open')) {
      mainNav.classList.remove('open');
      navToggle.setAttribute('aria-expanded', 'false');
    }
  });
});


  /* ============================
   *  Ajax para mensajes
   * ============================*/

  document.addEventListener('DOMContentLoaded', function () {
    const messagesContainer = document.getElementById('ticketMessages');
    const form = document.querySelector('.ticket-thread__form');
    if (!messagesContainer || !form) return;

    const textarea = form.querySelector('#mensaje');
    const fileInput = form.querySelector('#archivo_adjunto');

    function getLastId() {
        return parseInt(messagesContainer.dataset.lastId || '0', 10);
    }

    function setLastId(id) {
        messagesContainer.dataset.lastId = String(id);
    }

    function buildAjaxUrl(params = {}) {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax', '1');
        for (const [k, v] of Object.entries(params)) {
            url.searchParams.set(k, v);
        }
        return url.toString();
    }

    // Enviar mensaje por AJAX
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const fd = new FormData(form);
        // aseguramos accion=mensaje_nuevo por si acaso
        fd.set('accion', 'mensaje_nuevo');

        fetch(buildAjaxUrl(), {
            method: 'POST',
            body: fd,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.ok) {
                alert(data && data.error ? data.error : 'Error al enviar el mensaje.');
                return;
            }

            if (data.html) {
                messagesContainer.insertAdjacentHTML('beforeend', data.html);
            }
            if (data.last_id) {
                setLastId(data.last_id);
            }

            if (textarea) textarea.value = '';
            if (fileInput) fileInput.value = '';

            // hacer scroll hacia abajo
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión al enviar el mensaje.');
        });
    });

    // Polling para nuevos mensajes del otro lado
    function pollNewMessages() {
        const lastId = getLastId();
        fetch(buildAjaxUrl({action: 'list', last_id: String(lastId)}), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.ok) return;
            if (data.html) {
                messagesContainer.insertAdjacentHTML('beforeend', data.html);
            }
            if (data.last_id && data.last_id > lastId) {
                setLastId(data.last_id);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        })
        .catch(err => {
            console.error('Error al consultar nuevos mensajes', err);
        });
    }

    // Lanzar polling cada 10 segundos (ajusta si quieres)
    setInterval(pollNewMessages, 1000);
});


/* ============================
*  Alerta eliminar Usuario
* ============================*/
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.js-eliminar-usuario').forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();

      const url = this.getAttribute('href');

      Swal.fire({
        title: '¿Eliminar usuario?',
        text: 'El usuario será desactivado y ya no aparecerá en la lista.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#dc2626', // rojo elegante
        cancelButtonColor: '#6b7280'   // gris
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = url;
        }
      });
    });
  });
});



/* ============================
*  Ajax para mensajes
* ============================*/

document.addEventListener('DOMContentLoaded', function () {
    const form      = document.querySelector('.ticket-thread__form');
    if (!form) return;

    const textarea  = document.getElementById('mensaje');
    const fileInput = document.getElementById('archivo_adjunto');

    if (!textarea || !fileInput) return;

    // 1) ALERTA si el usuario intenta adjuntar archivo sin mensaje
    fileInput.addEventListener('change', function () {
        const hasMessage = textarea.value.trim().length > 0;

        if (fileInput.files.length > 0 && !hasMessage) {
            Swal.fire({
                icon: 'warning',
                title: 'Mensaje requerido',
                text: 'Para adjuntar evidencia, primero escribe un mensaje.',
                confirmButtonColor: '#3085d6',
            });

            // Limpiar archivo seleccionado
            fileInput.value = '';
            textarea.focus();
        }
    });

    // 2) Validación completa al enviar
    form.addEventListener('submit', function (e) {
        const hasMessage = textarea.value.trim().length > 0;

        if (!hasMessage) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Mensaje vacío',
                text: 'El mensaje no puede estar vacío.',
                confirmButtonColor: '#3085d6',
            });
            textarea.focus();
        }
    });
});

