// js/inactividad.js
(() => {
    'use strict';

    const TIEMPO_BLOQUEO_INICIAL = 30 * 1000;
    const TIEMPO_ESPERA_TEMPORIZADOR = 60 * 1000;
    const SEGUNDOS_CONTEO_FINAL = 60;

    const configuracion = window.SGET_INACTIVITY_CONFIG || {};
    const claveAlmacenamiento = `sget_inactividad_${configuracion.contexto || 'sin_contexto'}`;

    let temporizadorInactividad;
    let temporizadorEsperaModal;
    let intervaloConteoFinal;
    let tiempoRestante = SEGUNDOS_CONTEO_FINAL;
    let sesionBloqueada = false;
    let tiempoBloqueoMs = Date.now();
    let inicioTemporizadorMs = tiempoBloqueoMs + TIEMPO_ESPERA_TEMPORIZADOR;
    let cierreAutomaticoMs = inicioTemporizadorMs + (SEGUNDOS_CONTEO_FINAL * 1000);
    let overflowAnterior = '';
    let elementosInhabilitados = [];

    const eventosActividad = ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll'];

    function convertirTiempoServidor(segundos) {
        return Number.isFinite(segundos) ? segundos * 1000 : null;
    }

    function leerBloqueoCliente() {
        try {
            const valor = sessionStorage.getItem(claveAlmacenamiento);
            if (!valor) return null;
            if (valor === '1') return { bloqueada: true };

            const estado = JSON.parse(valor);
            return estado && estado.bloqueada === true ? estado : null;
        } catch (error) {
            return null;
        }
    }

    function guardarBloqueoCliente() {
        try {
            sessionStorage.setItem(claveAlmacenamiento, JSON.stringify({
                bloqueada: true,
                bloqueadaEn: tiempoBloqueoMs,
                temporizadorIniciaEn: inicioTemporizadorMs,
                cierraEn: cierreAutomaticoMs
            }));
        } catch (error) {
            // La sesión PHP sigue siendo la fuente principal de persistencia.
        }
    }

    function borrarBloqueoCliente() {
        try {
            sessionStorage.removeItem(claveAlmacenamiento);
        } catch (error) {
            // Si storage está bloqueado, el servidor ya fue notificado.
        }
    }

    // Token CSRF emitido por el backend (ver core/Auth::token()).
    function tokenCsrf() {
        const campo = document.querySelector('input[name="_token"]');
        if (campo && campo.value) return campo.value;
        return window.SGET_CSRF || '';
    }

    function obtenerRutaRaiz(ruta) {
        const path = window.location.pathname.toLowerCase();
        const esSubcarpeta = path.includes('/admin/') ||
            path.includes('/conductor/') ||
            path.includes('/pasajero/');

        return esSubcarpeta ? `../${ruta}` : ruta;
    }

    function actualizarTiemposDesdeConfiguracion() {
        const bloqueoServidor = convertirTiempoServidor(configuracion.bloqueadaEn);
        const inicioServidor = convertirTiempoServidor(configuracion.temporizadorIniciaEn);
        const cierreServidor = convertirTiempoServidor(configuracion.cierraEn);

        if (bloqueoServidor !== null) tiempoBloqueoMs = bloqueoServidor;
        if (inicioServidor !== null) inicioTemporizadorMs = inicioServidor;
        if (cierreServidor !== null) cierreAutomaticoMs = cierreServidor;
    }

    function notificarBloqueoAlServidor() {
        fetch(obtenerRutaRaiz('api/inactividad.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            keepalive: true,
            body: 'accion=estado&_token=' + encodeURIComponent(tokenCsrf())
        })
            .then((respuesta) => respuesta.ok ? respuesta.json() : null)
            .then((resultado) => {
                if (!resultado || resultado.status !== 'ok') return;

                const bloqueoServidor = Number(resultado.bloqueadaEn) * 1000;
                const inicioServidor = Number(resultado.temporizadorIniciaEn) * 1000;
                const cierreServidor = Number(resultado.cierraEn) * 1000;

                if (Number.isFinite(bloqueoServidor)) tiempoBloqueoMs = bloqueoServidor;
                if (Number.isFinite(inicioServidor)) inicioTemporizadorMs = inicioServidor;
                if (Number.isFinite(cierraServidor)) cierreAutomaticoMs = cierreServidor;
            })
            .catch(() => {
                // El bloqueo visual ya está activo; no se permite desbloquear por un fallo de red.
            });
    }

    function obtenerElementosEnfocables(modal) {
        return Array.from(modal.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter((elemento) => elemento.offsetParent !== null);
    }

    function habilitarFondo(modal, activo) {
        if (!activo) {
            elementosInhabilitados.forEach(({ elemento, inertPrevio, ariaPrevia }) => {
                elemento.inert = inertPrevio;
                if (ariaPrevia === null) {
                    elemento.removeAttribute('aria-hidden');
                } else {
                    elemento.setAttribute('aria-hidden', ariaPrevia);
                }
            });
            elementosInhabilitados = [];
            document.documentElement.style.overflow = overflowAnterior;
            return;
        }

        overflowAnterior = document.documentElement.style.overflow;
        document.documentElement.style.overflow = 'hidden';

        // Deshabilita las ramas del DOM que quedan fuera del modal, incluso si
        // el modal está anidado dentro del contenido principal.
        let ramaActual = modal;
        while (ramaActual && ramaActual !== document.body) {
            const padre = ramaActual.parentElement;
            if (!padre) break;

            Array.from(padre.children).forEach((elemento) => {
                if (elemento === ramaActual) return;

                elementosInhabilitados.push({
                    elemento,
                    inertPrevio: elemento.inert,
                    ariaPrevia: elemento.getAttribute('aria-hidden')
                });
                elemento.inert = true;
                elemento.setAttribute('aria-hidden', 'true');
            });

            ramaActual = padre;
        }
    }

    function mantenerFocoEnModal(evento) {
        if (!sesionBloqueada) return;

        if (evento.key === 'Escape') {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            return;
        }

        const modal = document.getElementById('modalBloqueoInactividad');
        if (!modal) return;

        if (evento.key === 'Tab') {
            const elementos = obtenerElementosEnfocables(modal);
            if (elementos.length === 0) {
                evento.preventDefault();
                return;
            }

            const primero = elementos[0];
            const ultimo = elementos[elementos.length - 1];

            if (!modal.contains(document.activeElement)) {
                evento.preventDefault();
                primero.focus();
            } else if (evento.shiftKey && document.activeElement === primero) {
                evento.preventDefault();
                ultimo.focus();
            } else if (!evento.shiftKey && document.activeElement === ultimo) {
                evento.preventDefault();
                primero.focus();
            }
        }
    }

    function bloquearFondo(modal) {
        // Un clic sobre el backdrop no debe alcanzar ningún elemento situado detrás.
        modal.addEventListener('mousedown', (evento) => {
            if (evento.target === modal) {
                evento.preventDefault();
                evento.stopImmediatePropagation();
            }
        });
        modal.addEventListener('click', (evento) => {
            if (evento.target === modal) {
                evento.preventDefault();
                evento.stopImmediatePropagation();
            }
        });
    }

    function reiniciarTemporizador() {
        if (sesionBloqueada) return;

        clearTimeout(temporizadorInactividad);
        temporizadorInactividad = setTimeout(() => bloquearInterfaz({ restaurar: false }), TIEMPO_BLOQUEO_INICIAL);
    }

    function programarCierreModal() {
        clearTimeout(temporizadorEsperaModal);
        clearInterval(intervaloConteoFinal);

        const contenedor = document.getElementById('contenedorTemporizadorInactividad');
        if (contenedor) contenedor.style.display = 'none';

        const restante = inicioTemporizadorMs - Date.now();
        if (restante > 0) {
            temporizadorEsperaModal = setTimeout(mostrarEIniciarTemporizador, restante);
        } else {
            mostrarEIniciarTemporizador();
        }
    }

    function bloquearInterfaz({ restaurar }) {
        sesionBloqueada = true;

        const modal = document.getElementById('modalBloqueoInactividad');
        if (!modal) {
            // Sin modal no hay forma de pedir la contraseña: se cierra la sesión
            // en lugar de dejar al usuario en una pantalla inaccesible.
            window.location.href = obtenerRutaRaiz('assets/cerrar.php?motivo=inactividad');
            return;
        }

        if (restaurar) {
            actualizarTiemposDesdeConfiguracion();
        } else {
            const ahora = Date.now();
            tiempoBloqueoMs = ahora;
            inicioTemporizadorMs = ahora + TIEMPO_ESPERA_TEMPORIZADOR;
            cierreAutomaticoMs = inicioTemporizadorMs + (SEGUNDOS_CONTEO_FINAL * 1000);
            notificarBloqueoAlServidor();
        }

        guardarBloqueoCliente();
        modal.style.display = 'flex';
        habilitarFondo(modal, true);
        bloquearFondo(modal);

        const inputPass = document.getElementById('inputPasswordModal');
        if (inputPass) {
            inputPass.value = '';
            inputPass.focus();
        }

        programarCierreModal();
    }

    function mostrarEIniciarTemporizador() {
        asegurarEstructuraTemporizador();

        const contenedor = document.getElementById('contenedorTemporizadorInactividad');
        if (contenedor) contenedor.style.display = 'block';

        clearInterval(intervaloConteoFinal);
        actualizarTextoTemporizador();

        intervaloConteoFinal = setInterval(() => {
            const restante = Math.max(0, Math.ceil((cierreAutomaticoMs - Date.now()) / 1000));
            tiempoRestante = restante;
            actualizarTextoTemporizador();

            if (restante <= 0) {
                clearInterval(intervaloConteoFinal);
                borrarBloqueoCliente();
                window.location.href = obtenerRutaRaiz('assets/cerrar.php');
            }
        }, 250);
    }

    function actualizarTextoTemporizador() {
        const elemento = document.getElementById('temporizadorRegresivoModal');
        if (elemento) elemento.innerText = String(tiempoRestante);
    }

    function asegurarEstructuraTemporizador() {
        if (document.getElementById('contenedorTemporizadorInactividad')) return;

        const modal = document.getElementById('modalBloqueoInactividad');
        const contenido = modal ? modal.firstElementChild : null;
        if (!contenido) return;

        const caja = document.createElement('div');
        caja.id = 'contenedorTemporizadorInactividad';
        caja.style.cssText = 'background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 0.6rem 1rem; border-radius: 0.85rem; font-size: 0.8rem; font-weight: 800; margin-bottom: 1.25rem; font-family: monospace; text-align: center; display: none;';
        caja.innerHTML = '<i class="fas fa-stopwatch" style="margin-right: 0.4rem; color: #ef4444;"></i> Cierre automático en: <span id="temporizadorRegresivoModal" style="font-size: 1rem; font-weight: 900; color: #ef4444;">60</span>s';

        const inputPassword = document.getElementById('inputPasswordModal');
        const contenedorInput = inputPassword ? inputPassword.parentElement : null;
        contenido.insertBefore(caja, contenedorInput || contenido.firstChild);
    }

    function mostrarErrorModal(mensaje) {
        const divError = document.getElementById('mensajeErrorModal');
        if (divError) {
            divError.innerText = mensaje;
            divError.style.display = 'block';
        }
    }

    async function enviarValidacionPassword() {
        if (!sesionBloqueada) return;

        const inputPassword = document.getElementById('inputPasswordModal');
        const divError = document.getElementById('mensajeErrorModal');
        const btnDesbloquear = document.getElementById('btnDesbloquearModal');
        const password = inputPassword ? inputPassword.value.trim() : '';

        if (!password) {
            mostrarErrorModal('Por favor ingresa tu contraseña.');
            return;
        }

        if (btnDesbloquear) {
            btnDesbloquear.disabled = true;
            btnDesbloquear.innerText = 'VERIFICANDO...';
        }

        try {
            const respuesta = await fetch(obtenerRutaRaiz('api/inactividad.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: 'accion=desbloquear&_token=' + encodeURIComponent(tokenCsrf()) +
                      '&password=' + encodeURIComponent(password)
            });
            const resultado = await respuesta.json();

            if (resultado.status === 'ok') {
                clearTimeout(temporizadorInactividad);
                clearTimeout(temporizadorEsperaModal);
                clearInterval(intervaloConteoFinal);
                sesionBloqueada = false;
                borrarBloqueoCliente();

                const modal = document.getElementById('modalBloqueoInactividad');
                if (modal) {
                    modal.style.display = 'none';
                    habilitarFondo(modal, false);
                }
                if (inputPassword) inputPassword.value = '';
                if (divError) divError.style.display = 'none';

                const contenedor = document.getElementById('contenedorTemporizadorInactividad');
                if (contenedor) contenedor.style.display = 'none';

                reiniciarTemporizador();
            } else {
                mostrarErrorModal(resultado.mensaje || 'Contraseña incorrecta.');
                if (inputPassword) {
                    inputPassword.value = '';
                    inputPassword.focus();
                }
            }
        } catch (error) {
            mostrarErrorModal('Error al comunicar con el servidor.');
        } finally {
            if (btnDesbloquear) {
                btnDesbloquear.disabled = false;
                btnDesbloquear.innerText = 'DESBLOQUEAR SESIÓN';
            }
        }
    }

    function iniciar() {
        eventosActividad.forEach((evento) => {
            window.addEventListener(evento, reiniciarTemporizador);
        });
        document.addEventListener('keydown', mantenerFocoEnModal, true);

        const btnDesbloquear = document.getElementById('btnDesbloquearModal');
        const inputPassword = document.getElementById('inputPasswordModal');
        if (btnDesbloquear) btnDesbloquear.addEventListener('click', enviarValidacionPassword);
        if (inputPassword) {
            inputPassword.addEventListener('keypress', (evento) => {
                if (evento.key === 'Enter') {
                    evento.preventDefault();
                    enviarValidacionPassword();
                }
            });
        }

        const bloqueadaEnServidor = configuracion.inicialmenteBloqueada === true;
        const estadoCliente = leerBloqueoCliente();
        const bloqueadaEnCliente = estadoCliente !== null;

        if (bloqueadaEnServidor || bloqueadaEnCliente) {
            if (estadoCliente && !bloqueadaEnServidor) {
                if (Number.isFinite(estadoCliente.bloqueadaEn)) tiempoBloqueoMs = estadoCliente.bloqueadaEn;
                if (Number.isFinite(estadoCliente.temporizadorIniciaEn)) inicioTemporizadorMs = estadoCliente.temporizadorIniciaEn;
                if (Number.isFinite(estadoCliente.cierraEn)) cierreAutomaticoMs = estadoCliente.cierraEn;
            }

            // Si el servidor aún no había recibido el evento, se reintenta de forma idempotente.
            if (!bloqueadaEnServidor) notificarBloqueoAlServidor();
            bloquearInterfaz({ restaurar: true });
        } else {
            reiniciarTemporizador();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar, { once: true });
    } else {
        iniciar();
    }
})();
