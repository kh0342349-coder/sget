/* ==========================================================================
   assets/js/sget-anuncios.js
   ------------------------------------------------------------------------------
   CARRUSEL DE ANUNCIOS de la landing.

   POR QUE ESTA EN UN ARCHIVO PROPIO Y NO EN index.php
     La landing era la unica pagina con logica escrita dentro del HTML. Este
     bloque salio de ahi sin cambiar un solo comportamiento, con dos anadidos
     que solo el carrusel puede saber: la pausa heredada del texto original y
     el conteo de vistas cuando el anuncio entra de verdad en pantalla.

   QUE HACE
     · Rota los anuncios (puntos, flechas y deslizamiento con el dedo).
     · Cuenta una vista por anuncio, que es lo que alimenta el KPI
       "Visualizaciones" de Admin/anuncios.php. Sin esto el contador se quedaba
       siempre en 0 y el modulo parecia no registrar nada.

   ATRIBUTOS QUE USA (los pone index.php)
     data-sget-carrusel         contenedor
     data-sget-slide            cada anuncio  (el valor es su id_ann)
     data-sget-carrusel-move    flechas: -1 / +1
     data-sget-carrusel-ir      puntos: indice del anuncio
     data-sget-vista-url        endpoint donde se suma la vista
   ========================================================================== */
(function (window, document) {
    'use strict';

    var INI   = 1000;    // espera antes de empezar a rotar
    var RITMO = 6000;    // tiempo por anuncio

    /* ------------------------------------------------------------------ */
    /* Contador de vistas                                                  */
    /* ------------------------------------------------------------------ */
    function contarVistas(slides) {
        var url = slides[0].closest('[data-sget-carrusel]')?.dataset.sgetVistaUrl;
        if (!url) return;

        var yaContados = {};
        var cuenta = function (slide) {
            var id = slide.dataset.sgetSlide;
            if (!id || yaContados[id]) return;
            yaContados[id] = true;

            var sep = url.indexOf('?') === -1 ? '?' : '&';
            // keepalive: la petición se envía aunque el usuario se vaya al
            // instante; si falla, se pierde una métrica y nada más.
            fetch(url + sep + 'id=' + encodeURIComponent(id), {
                method: 'GET',
                keepalive: true,
                credentials: 'same-origin'
            }).catch(function () { /* una métrica no rompe la landing */ });
        };

        if (!window.IntersectionObserver) { cuenta(slides[0]); return; }

        var observador = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (e) { if (e.isIntersecting) { cuenta(e.target); observador.unobserve(e.target); } });
        }, { threshold: 0.45 });

        slides.forEach(function (s) { observador.observe(s); });
    }

    /* ------------------------------------------------------------------ */
    /* Rotacion                                                            */
    /* ------------------------------------------------------------------ */
    function rotar(carrusel) {
        var slides = Array.prototype.slice.call(carrusel.querySelectorAll('[data-sget-slide]'));
        if (slides.length === 0) return;

        contarVistas(slides);

        var puntos  = Array.prototype.slice.call(carrusel.querySelectorAll('[data-sget-carrusel-ir]'));
        var actual  = 0;
        var reduce  = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var temporizador = null;

        function mostrar(i) {
            actual = (i + slides.length) % slides.length;

            slides.forEach(function (s, n) {
                var activo = n === actual;
                s.classList.toggle('es-activo', activo);
                s.setAttribute('aria-hidden', activo ? 'false' : 'true');
            });
            puntos.forEach(function (p, n) {
                p.classList.toggle('es-activo', n === actual);
                p.setAttribute('aria-selected', n === actual ? 'true' : 'false');
            });
        }

        // Con un solo anuncio no hay carrusel que girar: se muestra tal cual.
        if (slides.length === 1) { mostrar(0); return; }

        function programar() {
            if (reduce) return;
            clearTimeout(temporizador);
            temporizador = setTimeout(function () { mostrar(actual + 1); programar(); }, RITMO);
        }

        carrusel.addEventListener('mouseenter', function () { clearTimeout(temporizador); });
        carrusel.addEventListener('mouseleave', programar);
        carrusel.addEventListener('focusin', function () { clearTimeout(temporizador); });

        carrusel.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-sget-carrusel-move], [data-sget-carrusel-ir]');
            if (!btn) return;
            e.preventDefault();

            if (btn.hasAttribute('data-sget-carrusel-move')) {
                mostrar(actual + parseInt(btn.dataset.sgetCarruselMove, 10));
            } else {
                mostrar(parseInt(btn.dataset.sgetCarruselIr, 10));
            }
            programar();
        });

        // Deslizamiento con el dedo (móvil)
        var inicioX = null;
        carrusel.addEventListener('touchstart', function (e) { inicioX = e.touches[0].clientX; }, { passive: true });
        carrusel.addEventListener('touchend', function (e) {
            if (inicioX === null) return;
            var delta = e.changedTouches[0].clientX - inicioX;
            if (Math.abs(delta) > 45) { mostrar(actual + (delta < 0 ? 1 : -1)); programar(); }
            inicioX = null;
        }, { passive: true });

        mostrar(0);
        programar();
        setTimeout(programar, INI);
    }

    function init() {
        Array.prototype.slice.call(document.querySelectorAll('[data-sget-carrusel]')).forEach(rotar);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}(window, document));
