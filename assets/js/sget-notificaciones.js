/* ==========================================================================
   assets/js/sget-notificaciones.js
   -----------------------------------------------------------------------------
   BUZÓN DE NOTIFICACIONES
   -----------------------------------------------------------------------------
   POR QUÉ ES UN ARCHIVO PROPIO
     Las acciones del buzón usaban `data-sget-accion`, que solo ata
     assets/js/sget-page.js. Ese archivo se carga en los módulos CRUD del
     administrador, pero NO en las páginas de Pasajero ni de Conductor: allí el
     botón «marcar como leída» no tenía ningún manejador y el buzón era de
     adorno. Como el buzón vive en la cabecera, se incluye su propio JS, y así
     funciona en el 100% de las páginas sin tocar los listados de scripts.

   QUÉ HACE
     · Marcar una / todas como leídas, eliminar y vaciar el buzón.
     · Actualiza el contador de la cabecera SIN recargar la página.
     · Sondea el servidor: si llega un aviso nuevo (reserva confirmada, viaje
       cancelado, asignación…) aparece un aviso flotante, aunque el usuario no
       recargue nada.
     · Pide recordatorios de salida: al abrir el sistema, comprueba si tienes
       un viaje que sale en los próximos minutos y te avisa una sola vez.
   ========================================================================== */
(function (window, document) {
    'use strict';

    /* La API vive un nivel arriba (todas las páginas con cabecera están en
       /Admin, /Conductor o /Pasajero). */
    var API = '../api/index.php';
    var INTERVALO_MS = 60000;          // sondeo del contador


    function token() {
        if (window.SGETModal && window.SGETModal.__token) return window.SGETModal.__token;
        return window.SGET_CSRF || '';
    }

    function enviar(datos, alTerminar) {
        return fetch(API, {
            method: 'POST',
            body: datos,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json().catch(function () { return { status: 'error', mensaje: 'Respuesta inválida del servidor.' }; }); })
            .then(function (j) { if (alTerminar) alTerminar(j); return j; });
    }

    function aviso(mensaje, tipo) {
        if (window.SGETModal) window.SGETModal.toast(mensaje, tipo || 'info');
    }

    /* ------------------------------------------------------------------ */
    /* Contador de la cabecera                                             */
    /* ------------------------------------------------------------------ */
    function pintarContador(n) {
        document.querySelectorAll('[data-sget-noti-contador]').forEach(function (caja) {
            if (n > 0) {
                caja.textContent = n > 9 ? '9+' : String(n);
                caja.hidden = false;
            } else {
                // Se vacía el texto también: si queda un número dentro de un
                // elemento oculto, la siguiente lectura del DOM (o unlector de
                // pantalla) seguiría anunciando "1 sin leer".
                caja.textContent = '';
                caja.hidden = true;
            }
        });
        document.querySelectorAll('[data-sget-noti-bell]').forEach(function (b) {
            b.setAttribute('aria-label', n > 0 ? ('Notificaciones, ' + n + ' sin leer') : 'Notificaciones');
            b.classList.toggle('sget-noti-pulsa', n > 0);
        });
    }

    function leerContador() {
        var c = new FormData();
        c.append('_token', token());
        c.append('modulo', 'notificacion');
        c.append('accion', 'contador');

        enviar(c, function (j) {
            if (j.status !== 'ok') return;
            var antes = window.__sgetNoLeidas;
            var ahora = j.datos.no_leidas;
            pintarContador(ahora);
            window.__sgetNoLeidas = ahora;

            // Aviso flotante SOLO si el contador sube mientras se navega.
            if (antes !== undefined && ahora > antes) {
                aviso('Tienes ' + (ahora - antes) + ' aviso(s) nuevo(s). Revisa tu buzón.', 'aviso');
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Render del buzón                                                     */
    /* ------------------------------------------------------------------ */
    function fecha(iso) {
        if (!iso || iso.indexOf('-') !== 8) return iso || '';
        return iso.slice(0, 10).split('-').reverse().join('/') + ' · ' + iso.slice(11, 16);
    }

    function pintarLista(lista) {
        var ul = document.querySelector('[data-sget-noti-lista]');
        if (!ul) return;

        if (!lista.length) {
            var vacio = document.querySelector('[data-sget-noti-vacio]');
            if (vacio) vacio.hidden = false;
            ul.innerHTML = '';
            return;
        }

        var html = lista.map(function (n) {
            var p = n.presentacion || { icono: 'fa-circle-info', etiqueta: 'Aviso' };
            var leida = Number(n.leida) === 1;
            var badge = n.obsoleto
                ? '<span class="sget-badge sget-badge--neutro" title="Este aviso quedó sin efecto">Vigente</span>'
                : '';

            return '<li class="sget-noti-item' + (leida ? ' es-leida' : '') + '">' +
                '<span class="sget-noti-item__icono sget-noti-item__icono--' + (p.tono || 'azul') + '">' +
                    '<i class="fas ' + (p.icono || 'fa-circle-info') + '"></i>' +
                '</span>' +
                '<div class="sget-noti-item__cuerpo">' +
                    '<div class="sget-noti-item__cabecera">' +
                        '<span class="sget-badge ' + (p.clase || 'sget-badge--info') + '">' + (p.etiqueta || 'Aviso') + '</span>' +
                        '<time class="sget-help sget-mono">' + fecha(n.fec_envio) + '</time>' +
                    '</div>' +
                    '<p class="sget-noti-item__titulo">' + escapar(n.titulo) + '</p>' +
                    '<p class="sget-noti-item__texto">' + escapar(n.cuerpo) + '</p>' +
                    (n.id_via ? '<a class="sget-help" href="' + (n.enlace_viaje || '#') + '">Ver el viaje #' + n.id_via + ' <i class="fas fa-arrow-right"></i></a>' : '') +
                '</div>' +
                '<div class="sget-noti-item__acciones">' +
                    (leida ? '' : '<button type="button" class="sget-icon-btn sget-icon-btn--exito" title="Marcar como leída" ' +
                        'data-sget-noti="leer" data-id="' + n.id_not + '"><i class="fas fa-check"></i></button>') +
                    '<button type="button" class="sget-icon-btn sget-icon-btn--peligro" title="Eliminar aviso" ' +
                        'data-sget-noti="eliminar" data-id="' + n.id_not + '"><i class="fas fa-trash"></i></button>' +
                '</div>' +
            '</li>';
        }).join('');

        ul.innerHTML = html;
    }

    function escapar(texto) {
        var d = document.createElement('div');
        d.textContent = texto == null ? '' : String(texto);
        return d.innerHTML;
    }

    function cargarLista() {
        var c = new FormData();
        c.append('_token', token());
        c.append('modulo', 'notificacion');
        c.append('accion', 'listar');

        enviar(c, function (j) {
            if (j.status !== 'ok') return;
            pintarLista(j.datos || []);
            pintarContador((j.datos || []).filter(function (n) { return Number(n.leida) === 0; }).length);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Acciones                                                            */
    /* ------------------------------------------------------------------ */
    function accion(tipo, id, extra) {
        var c = new FormData();
        c.append('_token', token());
        c.append('modulo', 'notificacion');
        c.append('accion', tipo);
        if (id) c.append('id', id);
        Object.keys(extra || {}).forEach(function (k) { c.append(k, extra[k]); });

        return enviar(c, function (j) {
            if (j.status === 'ok') {
                if (j.mensaje) aviso(j.mensaje, 'exito');
                if (typeof j.datos?.no_leidas === 'number') {
                    pintarContador(j.datos.no_leidas);
                    window.__sgetNoLeidas = j.datos.no_leidas;
                }
                if (tipo === 'leer' || tipo === 'leerTodas' || tipo === 'eliminar' || tipo === 'vaciar') {
                    cargarLista();
                }
            } else {
                aviso(j.mensaje || 'No se pudo completar la acción.', 'error');
            }
        });
    }

    document.addEventListener('click', function (e) {
        var boton = e.target.closest('[data-sget-noti]');
        if (!boton) return;
        e.preventDefault();

        var tipo = boton.dataset.sgetNoti;
        var id = boton.dataset.id;

        if (tipo === 'leer')      return accion('leer', id);
        if (tipo === 'eliminar')  return accion('eliminar', id);
        if (tipo === 'leerTodas') return accion('leerTodas');
        if (tipo === 'vaciar')    return accion('vaciar');
    });

    document.addEventListener('click', function (e) {
        // Botón "Marcar todas" del pie del modal
        if (!e.target.closest('[data-sget-noti-todas]')) return;
        e.preventDefault();
        accion('leerTodas');
    });

    /* ------------------------------------------------------------------ */
    /* Arranque                                                            */
    /* ------------------------------------------------------------------ */
    function iniciar() {
        if (!document.querySelector('[data-sget-noti-bell]')) return;   // no hay buzón

        var pendientes = 0;
        document.querySelectorAll('[data-sget-noti-contador]').forEach(function (c) {
            if (!c.hidden) pendientes = parseInt(c.textContent, 10) || 0;
        });
        window.__sgetNoLeidas = pendientes;

        // 1) Primera carga del buzón al abrirlo
        document.addEventListener('sget:modal-abierto', function (ev) {
            if (!ev.detail || ev.detail.id !== 'modalNotificaciones') return;
            cargarLista();
        });

        // 2) Recordatorios de salida: "tu viaje sale en 40 minutos".
        //    El servidor los crea UNA vez por viaje (columna `firma`), así que
        //    se puede llamar en cada carga sin miedo a duplicar.
        var c = new FormData();
        c.append('_token', token());
        c.append('modulo', 'notificacion');
        c.append('accion', 'recordatorios');
        c.append('minutos', 90);
        enviar(c, function (j) {
            if (j.status === 'ok' && j.datos && j.datos.creados > 0) {
                pintarContador(j.datos.no_leidas);
                window.__sgetNoLeidas = j.datos.no_leidas;
            }
        });

        // 3) Sondeo del contador: un aviso nuevo salta aunque no se recargue
        setInterval(leerContador, INTERVALO_MS);

        // 4) Al volver a la pestaña se comprueba al instante
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) leerContador();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }

    window.SGETNotificaciones = { cargar: cargarLista, contador: leerContador, accion: accion };
})(window, document);
