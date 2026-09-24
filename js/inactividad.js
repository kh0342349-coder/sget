// js/inactividad.js

// 1. CONFIGURACIÓN DE TIEMPOS
const TIEMPO_BLOQUEO_INICIAL = 30 * 1000;      // 30 segundos de inactividad para mostrar el modal
const TIEMPO_ESPERA_TEMPORIZADOR = 60 * 1000; // 60 segundos (1 min) en el modal antes de mostrar el temporizador
const SEGUNDOS_CONTEO_FINAL = 60;              // 60 segundos (1 min) de cuenta regresiva final

let temporizadorInactividad;
let temporizadorEsperaModal;
let intervaloConteoFinal;
let tiempoRestante = SEGUNDOS_CONTEO_FINAL;
let sesionBloqueada = false;

const eventosActividad = ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll'];

function reiniciarTemporizador() {
    if (sesionBloqueada) return; // Si la pantalla ya está bloqueada, ignora la actividad de fondo
    clearTimeout(temporizadorInactividad);
    temporizadorInactividad = setTimeout(bloquearInterfaz, TIEMPO_BLOQUEO_INICIAL);
}

// Función robusta para la gestión de subcarpetas (admin, conductor, pasajero)
function obtenerRutaRaiz(subruta) {
    const path = window.location.pathname.toLowerCase();
    const esSubcarpeta = path.includes('/admin/') || 
                         path.includes('/conductor/') || 
                         path.includes('/pasajero/');
    return esSubcarpeta ? '../' + subruta : subruta;
}

// ETAPA 1: Bloquear la interfaz a los 30 segundos
function bloquearInterfaz() {
    sesionBloqueada = true;
    let modal = document.getElementById('modalBloqueoInactividad');
    if (modal) {
        modal.style.display = 'flex';
        const inputPass = document.getElementById('inputPasswordModal');
        if (inputPass) {
            inputPass.value = '';
            inputPass.focus();
        }
        
        // Ocultar caja de temporizador previa si existía
        const contenedorTemp = document.getElementById('contenedorTemporizadorInactividad');
        if (contenedorTemp) {
            contenedorTemp.style.display = 'none';
        }

        // Programar el inicio del temporizador si pasa 1 minuto en el modal sin movimiento
        clearTimeout(temporizadorEsperaModal);
        temporizadorEsperaModal = setTimeout(mostrarEIniciarTemporizador, TIEMPO_ESPERA_TEMPORIZADOR);
    } else {
        window.location.href = obtenerRutaRaiz('desbloquear_sesion.php?inactivo=1');
    }
}

// ETAPA 2: Mostrar el temporizador tras 1 minuto adicional en el modal
function mostrarEIniciarTemporizador() {
    asegurarEstructuraTemporizador();
    
    const contenedorTemp = document.getElementById('contenedorTemporizadorInactividad');
    if (contenedorTemp) {
        contenedorTemp.style.display = 'block';
    }

    clearInterval(intervaloConteoFinal);
    tiempoRestante = SEGUNDOS_CONTEO_FINAL;
    actualizarTextoTemporizador();

    intervaloConteoFinal = setInterval(() => {
        tiempoRestante--;
        actualizarTextoTemporizador();

        if (tiempoRestante <= 0) {
            clearInterval(intervaloConteoFinal);
            window.location.href = obtenerRutaRaiz('assets/cerrar.php');
        }
    }, 1000);
}

function actualizarTextoTemporizador() {
    const elemTemporizador = document.getElementById('temporizadorRegresivoModal');
    if (elemTemporizador) {
        elemTemporizador.innerText = tiempoRestante;
    }
}

function asegurarEstructuraTemporizador() {
    let contenedorTemp = document.getElementById('contenedorTemporizadorInactividad');

    if (!contenedorTemp) {
        const modal = document.getElementById('modalBloqueoInactividad');
        if (modal) {
            const contenedorInterno = modal.querySelector('div');
            if (contenedorInterno) {
                const cajaConteo = document.createElement('div');
                cajaConteo.id = 'contenedorTemporizadorInactividad';
                cajaConteo.style.cssText = 'background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 0.6rem 1rem; border-radius: 0.85rem; font-size: 0.8rem; font-weight: 800; margin-bottom: 1.25rem; font-family: monospace; text-align: center; display: none;';
                cajaConteo.innerHTML = '<i class="fas fa-stopwatch" style="margin-right: 0.4rem; color: #ef4444;"></i> Cierre automático en: <span id="temporizadorRegresivoModal" style="font-size: 1rem; font-weight: 900; color: #ef4444;">60</span>s';
                
                const inputPassword = document.getElementById('inputPasswordModal');
                const contenedorInput = inputPassword ? inputPassword.parentElement : null;
                
                if (contenedorInput) {
                    contenedorInterno.insertBefore(cajaConteo, contenedorInput);
                } else {
                    contenedorInterno.appendChild(cajaConteo);
                }
            }
        }
    }
}

// Iniciar monitoreo de interacción en pantalla limpia
eventosActividad.forEach(evento => {
    window.addEventListener(evento, reiniciarTemporizador);
});
reiniciarTemporizador();

// Escuchar envío del formulario de desbloqueo
document.addEventListener('DOMContentLoaded', () => {
    const btnDesbloquear = document.getElementById('btnDesbloquearModal');
    const inputPassword = document.getElementById('inputPasswordModal');

    if (btnDesbloquear) {
        btnDesbloquear.addEventListener('click', enviarValidacionPassword);
    }

    if (inputPassword) {
        inputPassword.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                enviarValidacionPassword();
            }
        });
    }
});

async function enviarValidacionPassword() {
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
        const targetUrl = obtenerRutaRaiz('validar_password.php');
        const respuesta = await fetch(targetUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ password: password })
        });

        const resultado = await respuesta.json();

        if (resultado.success) {
            // Contraseña correcta: Cancelar todos los timers, ocultar modal y reiniciar
            clearTimeout(temporizadorEsperaModal);
            clearInterval(intervaloConteoFinal);
            sesionBloqueada = false;
            
            const modal = document.getElementById('modalBloqueoInactividad');
            if (modal) modal.style.display = 'none';
            if (inputPassword) inputPassword.value = '';
            if (divError) divError.style.display = 'none';

            const contenedorTemp = document.getElementById('contenedorTemporizadorInactividad');
            if (contenedorTemp) contenedorTemp.style.display = 'none';
            
            reiniciarTemporizador();
        } else {
            if (resultado.cerrar_sesion) {
                window.location.href = obtenerRutaRaiz('assets/cerrar.php');
                return;
            }
            mostrarErrorModal(resultado.message || 'Contraseña incorrecta.');
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

function mostrarErrorModal(mensaje) {
    let divError = document.getElementById('mensajeErrorModal');
    if (divError) {
        divError.innerText = mensaje;
        divError.style.display = 'block';
    } else {
        alert(mensaje);
    }
}