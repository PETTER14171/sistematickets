(() => {
  'use strict';

  // Cambia este marcador en cada despliegue para confirmar carga
  console.log('APP BUILD: 2025-12-12');

  /* =========================
   *  Permiso notificacion
   * ========================= */

  (function () {
  const btn = document.getElementById('btnEnableNotifications');
  if (!btn) return;

  const supports = ('Notification' in window);

  function setBtnState(enabled) {
    // enabled = true cuando ya hay permiso "granted"
    if (enabled) {
      btn.classList.add('is-enabled');
      btn.setAttribute('aria-label', 'Notificaciones activadas');
      btn.title = 'Notificaciones activadas';
    } else {
      btn.classList.remove('is-enabled');
      btn.setAttribute('aria-label', 'Activar notificaciones del navegador');
      btn.title = 'Activar notificaciones del navegador';
    }
  }

  // Estado inicial
  if (!supports) {
    btn.disabled = true;
    btn.title = 'Tu navegador no soporta notificaciones';
    return;
  }
  setBtnState(Notification.permission === 'granted');

  btn.addEventListener('click', async () => {
    if (!supports) return;

    // Si ya está permitido
    if (Notification.permission === 'granted') {
      Swal.fire({
        icon: 'info',
        title: 'Ya están activadas',
        text: 'Las notificaciones del navegador ya están permitidas.',
        confirmButtonText: 'OK'
      });
      return;
    }

    // Si el usuario ya bloqueó
    if (Notification.permission === 'denied') {
      Swal.fire({
        icon: 'error',
        title: 'Notificaciones bloqueadas',
        html: `
          Tu navegador tiene las notificaciones <b>bloqueadas</b>.<br><br>
          Para habilitarlas: abre el candado 🔒 en la barra de direcciones → Notificaciones → Permitir.
        `,
        confirmButtonText: 'Entendido'
      });
      return;
    }

    // Preguntar permiso (prompt)
    try {
      const permission = await Notification.requestPermission();

      if (permission === 'granted') {
        setBtnState(true);

        Swal.fire({
          icon: 'success',
          title: '¡Listo!',
          text: 'Notificaciones activadas. Te avisaré cuando haya nuevos eventos.',
          timer: 1800,
          showConfirmButton: false
        });

        // Notificación de prueba opcional
        new Notification('TalkHub', {
          body: 'Notificaciones activadas correctamente ✅',
        });

      } else if (permission === 'denied') {
        setBtnState(false);
        Swal.fire({
          icon: 'error',
          title: 'Permiso rechazado',
          text: 'No podré mostrar notificaciones del navegador a menos que las habilites.',
          confirmButtonText: 'OK'
        });
      } else {
        // "default" (cerró el prompt)
        setBtnState(false);
        Swal.fire({
          icon: 'warning',
          title: 'Sin cambios',
          text: 'No se otorgó el permiso. Puedes intentarlo de nuevo cuando quieras.',
          confirmButtonText: 'OK'
        });
      }
    } catch (e) {
      Swal.fire({
        icon: 'error',
        title: 'Error al solicitar permiso',
        text: 'Ocurrió un problema al solicitar notificaciones.',
        confirmButtonText: 'OK'
      });
    }
  });
})();


/* =========================
 *  Notificaciones (Resumen + Browser Notification)
 * ========================= */
function prioridadLabel(prio) {
  const p = (prio || '').toLowerCase();
  if (p === 'alta') return 'Alta';
  if (p === 'media') return 'Media';
  if (p === 'baja') return 'Baja';
  return 'Info';
}

function prioridadIcon(prio) {
  const p = (prio || '').toLowerCase();
  if (p === 'alta') return '⚠️';
  if (p === 'media') return '🔔';
  if (p === 'baja') return 'ℹ️';
  return '🔔';
}

// Render del bloque único
function renderNotiResumen(unread, topPriority) {
  const etiqueta = prioridadLabel(topPriority);
  const icono = prioridadIcon(topPriority);

  if (!unread || unread <= 0) {
    return `<p class="admin-card__empty">Sin notificaciones pendientes.</p>`;
  }

  return `
    <div class="admin-noti-summary__box admin-noti-summary__box--${(topPriority || 'media').toLowerCase()}">
      <div class="admin-noti-summary__icon">${icono}</div>
      <div class="admin-noti-summary__content">
        <div class="admin-noti-summary__title">Tienes notificaciones pendientes</div>
        <div class="admin-noti-summary__meta">
          <span class="admin-noti-summary__count">${unread}</span>
          <span class="admin-noti-summary__sep">•</span>
          <span class="admin-noti-summary__prio">Con Prioridad: ${etiqueta}</span>
        </div>
      </div>
    </div>
  `;
}

function verificarNuevaNotificacionResumen() {
  // Puedes usar #alertaDinamica o el nuevo #notiResumen (recomendado)
  const contenedor = document.getElementById('notiResumen') || document.getElementById('alertaDinamica');
  if (!contenedor) return;

  fetch('notificaciones_alerta.php')
    .then(res => res.json())
    .then(data => {
      if (!data || !data.ok) return;

      const unread = Number(data.unread || 0);
      const topPriority = data.top_priority || null;
      const latest = data.latest || null;

      // 1) Pintar bloque único
      contenedor.innerHTML = renderNotiResumen(unread, topPriority);

      // 2) Notificación del navegador SOLO si llegó una nueva (por id)
      if (!latest || !latest.id) return;

      const lastIdKey = 'lastNotiId_seen';
      const lastSeen = Number(localStorage.getItem(lastIdKey) || 0);

      // Si hay una nueva con id mayor
      if (latest.id > lastSeen) {
        // Guardamos el último id visto para evitar spam
        localStorage.setItem(lastIdKey, String(latest.id));

        // Disparar Notification si está permitido
        if ('Notification' in window && Notification.permission === 'granted') {
          const title = `TalkHub: ${unread} pendiente(s)`;
          const body = latest.mensaje ? latest.mensaje : 'Tienes una nueva notificación.';

          const n = new Notification(title, { body });

          // Si hay ticket_id, al hacer click te mando al ticket
          if (latest.ticket_id) {
            n.onclick = () => {
              window.focus();
              window.location.href = `responder_ticket.php?id=${latest.ticket_id}`;
            };
          }
        }
      }
    })
    .catch(err => console.error('Error al consultar notificaciones:', err));
}

  // Polling (recomendado 2s para no saturar)
  setInterval(verificarNuevaNotificacionResumen, 2000);
  verificarNuevaNotificacionResumen();


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

    // Lanzar polling cada 3 segundos (ajusta si quieres)
    setInterval(pollNewMessages, 3000);
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

