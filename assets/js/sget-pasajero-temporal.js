/* Alta de pasajero ocasional durante un viaje en curso. */
(function (window, document) {
    'use strict';

    var form = document.getElementById('formPasajeroTemporal');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!form.reportValidity()) return;

        var boton = form.querySelector('button[type="submit"]');
        var textoOriginal = boton ? boton.innerHTML : '';
        if (boton) {
            boton.disabled = true;
            boton.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Registrando…';
        }

        var cuerpo = new FormData(form);
        cuerpo.append('modulo', 'reserva');
        cuerpo.append('accion', 'pasajeroEnRuta');

        fetch('../api/index.php', {
            method: 'POST',
            body: cuerpo,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (respuesta) {
                return respuesta.json().catch(function () {
                    return { status: 'error', mensaje: 'El servidor devolvió una respuesta inválida.' };
                });
            })
            .then(function (resultado) {
                if (window.SGETModal) {
                    window.SGETModal.toast(resultado.mensaje || 'No se pudo registrar al pasajero.',
                        resultado.status === 'ok' ? 'exito' : 'error');
                }
                if (resultado.status === 'ok') {
                    window.SGETModal && window.SGETModal.cerrar('modalPasajeroTemporal');
                    window.location.reload();
                }
            })
            .catch(function () {
                window.SGETModal && window.SGETModal.toast('No se pudo conectar con el servidor.', 'error');
            })
            .finally(function () {
                if (boton) {
                    boton.disabled = false;
                    boton.innerHTML = textoOriginal;
                }
            });
    });
})(window, document);
