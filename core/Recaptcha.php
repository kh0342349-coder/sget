<?php
/**
 * core/Recaptcha.php
 * -----------------------------------------------------------------------------
 * VERIFICACIÓN DE reCAPTCHA v2 — CONFIGURABLE
 * -----------------------------------------------------------------------------
 * POR QUÉ EXISTE
 *   `validar.php` pintaba siempre un reCAPTCHA con la clave PÚBLICA DE PRUEBA
 *   de Google y en el backend solo verificaba el token «si venía». Es decir:
 *
 *       if ($respuestaRecaptcha !== '') { verificar… }
 *
 *   Sin token → se entraba igual. Con la clave de prueba → cualquier token
 *   pasaba. El control era puramente visual: exactamente la «falsa sensación
 *   de seguridad» que hay que evitar.
 *
 *   AHORA
 *     · `Config::recaptchaHabilitado()` decide si el control existe.
 *     · Si está DESHABILITADO (por defecto, para desarrollo) no se pinta y no
 *       se exige: no hay captura de pantalla que dé falsa confianza.
 *     · Si está HABILITADO y llega el token, se verifica contra Google.
 *     · Si está HABILITADO y NO llega el token → RECHAZO. Siempre.
 *     · Si está HABILITADO pero falta la clave secreta → se rechaza en cerrado
 *       (fallar «cerrado»), porque una configuración mal puesta no puede
 *       convertirse en un bypass.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/Config.php';

final class Recaptcha
{
    private const TIMEOUT_SEGUNDOS = 6;

    /**
     * Comprueba el token recibido en el POST.
     *
     * REGLAS
     *   · Captcha DESACTIVADO de forma explícita -> se permite (atajo documentado).
     *   · Captcha ACTIVO y SIN token             -> RECHAZO. Siempre.
     *   · Captcha ACTIVO y con token             -> se verifica con Google.
     *   · Google no responde                     -> RECHAZO (fallar en cerrado).
     *
     * El caso «si viene token, se verifica; si no, se entra igual» que había
     * antes era el fallo grave: el widget estaba en pantalla y no bloqueaba nada.
     *
     * @return array{ok:bool, mensaje:string}
     */
    public static function verificar(?string $token): array
    {
        if (!Config::recaptchaHabilitado()) {
            return ['ok' => true, 'mensaje' => ''];
        }

        $secret = Config::recaptchaSecret();
        if ($secret === '') {
            error_log('[SGET][recaptcha] Sin SGET_RECAPTCHA_SECRET: se rechaza en cerrado.');
            return ['ok' => false, 'mensaje' =>
                'El control antirobot no está configurado correctamente. Avisa al administrador.'];
        }

        $token = trim((string)$token);
        if ($token === '') {
            return ['ok' => false, 'mensaje' => 'Completa la verificación antirobot para continuar.'];
        }

        /* MODO PRUEBA · claves públicas de Google
         * Google devuelve `success=true` para CUALQUIER token con estas claves.
         * No es una protección real, así que queda registrado de forma
         * explícita: saber en un vistazo que el acceso no está protegido contra
         * bots es preferible a creer lo contrario. La pantalla muestra el mismo
         * aviso al usuario. */
        if (Config::recaptchaEnModoPrueba()) {
            error_log('[SGET][recaptcha] Claves de PRUEBA de Google activas: no bloquea bots.');
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . rawurlencode($secret)
             . '&response=' . rawurlencode($token);
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $url .= '&remoteip=' . rawurlencode((string)$_SERVER['REMOTE_ADDR']);
        }

        $contexto = stream_context_create([
            'http' => ['timeout' => self::TIMEOUT_SEGUNDOS, 'ignore_errors' => true],
        ]);

        $cuerpo = @file_get_contents($url, false, $contexto);
        $datos  = is_string($cuerpo) ? json_decode($cuerpo, true) : null;

        if (!is_array($datos)) {
            // Caída de red o respuesta ilegible: NO se deja pasar. Un fallo de
            // disponibilidad no puede convertirse en «entra sin verificar».
            error_log('[SGET][recaptcha] No se pudo contactar con el servicio de verificación.');
            return ['ok' => false, 'mensaje' =>
                'No pudimos verificar el control antirobot. Inténtalo de nuevo en un momento.'];
        }

        if (empty($datos['success'])) {
            $codigos = (array)($datos['error-codes'] ?? []);
            error_log('[SGET][recaptcha] Rechazo: ' . implode(', ', $codigos));
            return ['ok' => false, 'mensaje' => 'No pudimos verificar que no eres un robot. Vuelve a intentarlo.'];
        }

        return ['ok' => true, 'mensaje' => ''];
    }
}
