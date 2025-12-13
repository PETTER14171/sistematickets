(() => {
  'use strict';

  // Cambia este marcador en cada despliegue para confirmar carga
  console.log('APP BUILD: 2025-12-12 (fixed)');

  /* =========================
   *  Helpers (SweetAlert seguro)
   * ========================= */
  function swalFire(options, fallbackText = '') {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      return window.Swal.fire(options);
    }
    if (fallbackText) alert(fallbackText);
    return Promise.resolve({ isConfirmed: false });
  }

  /* =========================
   *  Permiso notificación (Botón 🔔)
   * ========================= */
  (function initNotificationPermissionButton() {
    const btn = document.getElementById('btnEnableNotifications');
    if (!btn) return;

    const supports = ('Notification' in window);

    function setBtnState(enabled) {
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

    if (!supports) {
      btn.disabled = true;
      btn.title = 'Tu navegador no soporta notificaciones';
      return;
    }

    setBtnState(Notification.permission === 'granted');

    btn.addEventListener('click', async () => {
      if (!supports) return;

      // Ya está permitido
      if (Notification.permission === 'granted') {
        swalFire(
          {
            icon: 'info',
            title: 'Ya están activadas',
            text: 'Las notificaciones del navegador ya están permitidas.',
            confirmButtonText: 'OK'
          },
          'Las notificaciones ya están activadas.'
        );
        return;
      }

      // El usuario bloqueó
      if (Notification.permission === 'denied') {
        swalFire(
          {
            icon: 'error',
            title: 'Notificaciones bloqueadas',
            html: `
              Tu navegador tiene las notificaciones <b>bloqueadas</b>.<br><br>
              Para habilitarlas: abre el candado 🔒 en la barra de direcciones → Notificaciones → Permitir.
            `,
            confirmButtonText: 'Entendido'
          },
          'Notificaciones bloqueadas. Actívalas desde la configuración del sitio.'
        );
        return;
      }

      // Solicitar permiso
      try {
        const permission = await Notification.requestPermission();

        if (permission === 'granted') {
          setBtnState(true);

          swalFire(
            {
              icon: 'success',
              title: '¡Listo!',
              text: 'Notificaciones activadas. Te avisaré cuando haya nuevos eventos.',
              timer: 1800,
              showConfirmButton: false
            },
            'Notificaciones activadas.'
          );

          // Notificación de prueba (opcional)
          try {
            new Notification('TalkHub', {
              body: 'Notificaciones activadas correctamente ✅'
            });
          } catch (e) {
            // Algunos navegadores requieren interacción/condiciones específicas
          }

        } else if (permission === 'denied') {
          setBtnState(false);
          swalFire(
            {
              icon: 'error',
              title: 'Permiso rechazado',
              text: 'No podré mostrar notificaciones del navegador a menos que las habilites.',
              confirmButtonText: 'OK'
            },
            'Permiso rechazado.'
          );
        } else {
          // "default" (cerró el prompt)
          setBtnState(false);
          swalFire(
            {
              icon: 'warning',
              title: 'Sin cambios',
              text: 'No se otorgó el permiso. Puedes intentarlo de nuevo cuando quieras.',
              confirmButtonText: 'OK'
            },
            'No se otorgó el permiso.'
          );
        }
      } catch (e) {
        swalFire(
          {
            icon: 'error',
            title: 'Error al solicitar permiso',
            text: 'Ocurrió un problema al solicitar notificaciones.',
            confirmButtonText: 'OK'
          },
          'Error al solicitar permiso de notificaciones.'
        );
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

        // 2) Browser Notification SOLO si llegó una nueva (por id)
        if (!latest || !latest.id) return;

        const lastIdKey = 'lastNotiId_seen';
        const lastSeen = Number(localStorage.getItem(lastIdKey) || 0);

        if (latest.id > lastSeen) {
          localStorage.setItem(lastIdKey, String(latest.id));

          if ('Notification' in window && Notification.permission === 'granted') {
            const title = `TalkHub: ${unread} pendiente(s)`;
            const body = latest.mensaje ? latest.mensaje : 'Tienes una nueva notificación.';

            try {
              const n = new Notification(title, { body });

              if (latest.ticket_id) {
                n.onclick = () => {
                  window.focus();
                  window.location.href = `responder_ticket.php?id=${latest.ticket_id}`;
                };
              }
            } catch (e) {
              // silencio
            }
          }
        }
      })
      .catch(err => console.error('Error al consultar notificaciones:', err));
  }

  // Polling (recomendado 2s)
  setInterval(verificarNuevaNotificacionResumen, 2000);
  verificarNuevaNotificacionResumen();

  /* =========================
   *  Modal de fallas comunes
   * ========================= */
  function abrirModalFalla(id) {
    const modal = document.getElementById('modal-falla-' + id);
    if (!modal) return;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    modal.addEventListener(
      'click',
      function backdrop(e) {
        if (e.target === modal) cerrarModalFalla(id);
      },
      { once: true }
    );

    const onEsc = (e) => { if (e.key === 'Escape') cerrarModalFalla(id); };
    modal._onEsc = onEsc;
    document.addEventListener('keydown', onEsc);
  }

  function cerrarModalFalla(id) {
    const modal = document.getElementById('modal-falla-' + id);
    if (!modal) return;

    modal.style.display = 'none';
    document.body.style.overflow = '';

    if (modal._onEsc) {
      document.removeEventListener('keydown', modal._onEsc);
      delete modal._onEsc;
    }
  }

  // Exponer SOLO lo que sí existe
  window.abrirModalFalla = abrirModalFalla;
  window.cerrarModalFalla = cerrarModalFalla;

  /* ============================
   *  Interfaz de visualizar PDF
   * ============================ */
  document.addEventListener('DOMContentLoaded', () => {
    const frame      = document.querySelector('.js-pdf-frame');
    const zoomLabel  = document.querySelector('.js-pdf-zoom-label');
    const btnZoomIn  = document.querySelector('.js-pdf-zoom-in');
    const btnReset   = document.querySelector('.js-pdf-zoom-reset');
    const btnSearch  = document.querySelector('.js-pdf-search-tip');

    if (!frame || !zoomLabel || !btnZoomIn || !btnReset) return;

    let zoom = 1;
    const MIN_ZOOM = 0.6;
    const MAX_ZOOM = 2.0;
    const STEP     = 0.1;

    function applyZoom() {
      frame.style.transform = `scale(${zoom})`;
      frame.style.transformOrigin = 'top center';
      zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
    }

    applyZoom();

    btnZoomIn.addEventListener('click', () => {
      zoom = Math.min(MAX_ZOOM, +(zoom + STEP).toFixed(2));
      applyZoom();
    });

    btnReset.addEventListener('click', () => {
      zoom = 1.0;
      applyZoom();
    });

    document.addEventListener('keydown', (ev) => {
      const isCtrl = ev.ctrlKey || ev.metaKey;

      if (isCtrl && (ev.key === '+' || ev.key === '=')) {
        ev.preventDefault();
        zoom = Math.min(MAX_ZOOM, +(zoom + STEP).toFixed(2));
        applyZoom();
      }

      if (isCtrl && ev.key === '-') {
        ev.preventDefault();
        zoom = Math.max(MIN_ZOOM, +(zoom - STEP).toFixed(2));
        applyZoom();
      }

      if (isCtrl && ev.key === '0') {
        ev.preventDefault();
        zoom = 1.0;
        applyZoom();
      }
    });

    if (btnSearch) {
      btnSearch.addEventListener('click', () => {
        alert('Tip: usa Ctrl + F (Cmd + F en Mac) para buscar dentro del documento.');
      });
    }
  });

  /* ============================
   *  Funciones del panel admin
   * ============================ */
  document.addEventListener('DOMContentLoaded', () => {
    // 1) Tarjetas clickeables (stats / middle)
    document.querySelectorAll('.js-card-link').forEach(card => {
      const link = card.dataset.link;
      if (!link) return;
      card.classList.add('admin-card--clickable');
      card.addEventListener('click', () => { window.location.href = link; });
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

    // 3) Scroll en listado (si aún existe listado viejo)
    const notifList = document.querySelector('.js-notifications-list');
    if (notifList) {
      const items = notifList.querySelectorAll('.admin-notifications__item');
      if (items.length > 5) notifList.classList.add('has-scroll');
    }

    // IMPORTANTE:
    // Ya NO hacemos scroll/highlight al dar clic en la campana, porque ahora la campana es para permisos.
  });

  /* ============================
   *  Navegación Responsiva
   * ============================ */
  document.addEventListener('DOMContentLoaded', () => {
    const navToggle = document.getElementById('navToggle');
    const mainNav   = document.getElementById('mainNav');
    if (!navToggle || !mainNav) return;

    navToggle.addEventListener('click', () => {
      const isOpen = mainNav.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    mainNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 900 && mainNav.classList.contains('open')) {
          mainNav.classList.remove('open');
          navToggle.setAttribute('aria-expanded', 'false');
        }
      });
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 900 && mainNav.classList.contains('open')) {
        mainNav.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && mainNav.classList.contains('open')) {
        mainNav.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    });
  });

  /* ============================
   *  Ajax para mensajes (ENVIAR + POLLING) + VALIDACIONES (UNIFICADO)
   * ============================ */
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

    // VALIDACIÓN: adjunto sin mensaje
    if (fileInput && textarea) {
      fileInput.addEventListener('change', function () {
        const hasMessage = textarea.value.trim().length > 0;

        if (fileInput.files.length > 0 && !hasMessage) {
          swalFire(
            {
              icon: 'warning',
              title: 'Mensaje requerido',
              text: 'Para adjuntar evidencia, primero escribe un mensaje.'
            },
            'Para adjuntar evidencia, primero escribe un mensaje.'
          );

          fileInput.value = '';
          textarea.focus();
        }
      });
    }

    // Enviar mensaje por AJAX
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      const hasMessage = textarea ? textarea.value.trim().length > 0 : false;
      if (!hasMessage) {
        swalFire(
          {
            icon: 'warning',
            title: 'Mensaje vacío',
            text: 'El mensaje no puede estar vacío.'
          },
          'El mensaje no puede estar vacío.'
        );
        if (textarea) textarea.focus();
        return;
      }

      const fd = new FormData(form);
      fd.set('accion', 'mensaje_nuevo');

      fetch(buildAjaxUrl(), {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(r => r.json())
        .then(data => {
          if (!data || !data.ok) {
            swalFire(
              {
                icon: 'error',
                title: 'Error',
                text: (data && data.error) ? data.error : 'Error al enviar el mensaje.'
              },
              (data && data.error) ? data.error : 'Error al enviar el mensaje.'
            );
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

          messagesContainer.scrollTop = messagesContainer.scrollHeight;
        })
        .catch(err => {
          console.error(err);
          swalFire(
            {
              icon: 'error',
              title: 'Error de conexión',
              text: 'Error de conexión al enviar el mensaje.'
            },
            'Error de conexión al enviar el mensaje.'
          );
        });
    });

    // Polling para nuevos mensajes
    function pollNewMessages() {
      const lastId = getLastId();

      fetch(buildAjaxUrl({ action: 'list', last_id: String(lastId) }), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
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

    setInterval(pollNewMessages, 3000);
  });

  /* ============================
   *  Alerta eliminar Usuario (SweetAlert)
   * ============================ */
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-eliminar-usuario').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();

        const url = this.getAttribute('href');

        swalFire(
          {
            title: '¿Eliminar usuario?',
            text: 'El usuario será desactivado y ya no aparecerá en la lista.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280'
          },
          '¿Eliminar usuario?'
        ).then((result) => {
          if (result && result.isConfirmed) {
            window.location.href = url;
          }
        });
      });
    });
  });

})();
