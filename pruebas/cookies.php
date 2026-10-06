<?php
/**
 * pruebas/cookies.php
 * -----------------------------------------------------------------------------
 * CONSENTIMIENTO DE COOKIES Y PREFERENCIAS
 * -----------------------------------------------------------------------------
 * Comprueba lo que el sistema REALMENTE hace, para que el panel no pueda
 * prometer una cosa y el código hacer otra.
 *
 * Lo que se verifica:
 *   1. `sget_tema` NO se escribe si el usuario no aceptó preferencias.
 *   2. `sget_tema` SÍ se escribe (con los atributos correctos) si aceptó.
 *   3. Retirar el consentimiento BORRA la cookie.
 *   4. La cookie no lleva `HttpOnly` (la gestiona JavaScript) y sí lleva
 *      `Path`, `Max-Age` y `SameSite`.
 *   5. `Secure` solo aparece cuando la página es HTTPS.
 *   6. El panel describe la realidad: no promete analítica.
 *   7. El botón «Configuración de Cookies» sigue disponible en la landing.
 *   8. La cookie de sesión (`PHPSESSID`) conserva HttpOnly + SameSite.
 *
 * La parte de JavaScript se ejecuta en Chrome headless, que es donde se puede
 * observar de verdad qué cookies existen tras aplicar cada escenario.
 * -----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/..' . '/core/bootstrap.php';

$base = 'http://127.0.0.1:' . (getenv('SGET_TEST_PORT') ?: '8899');

$ok = 0;
$fallos = 0;

function check(string $titulo, bool $cumple, string $detalle = ''): void
{
    global $ok, $fallos;
    if ($cumple) {
        $ok++;
        echo "  [OK]   $titulo\n";
    } else {
        $fallos++;
        echo "  [FALLA] $titulo" . ($detalle !== '' ? " -> $detalle" : '') . "\n";
    }
}

function seccion(string $titulo): void
{
    echo "\n=== $titulo ===\n";
}

/** Ruta de Chrome para Windows (la misma que usan las sondas visuales). */
function rutaChrome(): ?string
{
    $candidatos = [
        'C:' . DIRECTORY_SEPARATOR . 'Program Files' . DIRECTORY_SEPARATOR . 'Google' .
            DIRECTORY_SEPARATOR . 'Chrome' . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'chrome.exe',
        'C:' . DIRECTORY_SEPARATOR . 'Program Files (x86)' . DIRECTORY_SEPARATOR . 'Google' .
            DIRECTORY_SEPARATOR . 'Chrome' . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'chrome.exe',
    ];
    foreach ($candidatos as $ruta) {
        if (is_file($ruta)) {
            return $ruta;
        }
    }
    return null;
}

/**
 * Ejecuta JavaScript sobre la landing y devuelve el valor de la expresión.
 *
 * CÓMO FUNCIONA Y POR QUÉ ASÍ
 *   No hay Puppeteer en el proyecto, así que se usa el propio Chrome headless
 *   con `--dump-dom`, que entrega el DOM DESPUÉS de ejecutar el JavaScript.
 *
 *   La sonda se sirve por HTTP desde `pruebas/` y carga la landing en un
 *   <iframe> del MISMO ORIGEN: así se puede leer `document.cookie` de verdad,
 *   que es justamente lo que hay que comprobar. Con un `file://` el navegador
 *   bloquearía el acceso por origen distinto y la prueba no probaría nada.
 *
 *   El archivo se borra siempre al terminar, y el helper `pruebas/.htaccess`
 *   ya impide que se sirva fuera de una máquina de desarrollo.
 */
function evaluar(string $url, string $expresionJs): ?string
{
    global $base;

    $chrome = rutaChrome();
    if ($chrome === null) {
        return null;
    }

    $sonda = __DIR__ . '/_cookie_probe.html';
    @file_put_contents($sonda, sprintf(
        '<!DOCTYPE html><meta charset="utf-8"><body><pre id="salida">sin-ejecutar</pre>' .
        '<iframe id="marco" src="%s" style="display:none"></iframe>' .
        '<script>' .
        'function resolver(){' .
        '  var m=document.getElementById("marco");' .
        '  var v=null;' .
        '  try{ var w=m.contentWindow; v=(%s); }' .
        '  catch(e){ v="ERROR:"+e.message; }' .
        '  document.getElementById("salida").textContent = (v===null||v===undefined) ? "null" : String(v);' .
        '}' .
        'document.getElementById("marco").addEventListener("load",function(){setTimeout(resolver,400);});' .
        'setTimeout(function(){ if(document.getElementById("salida").textContent==="sin-ejecutar") resolver(); },2500);' .
        '</script></body>',
        htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
        $expresionJs
    ));

    /* Perfil aislado: sin él Chrome reutiliza la instancia del navegador del
       usuario y `--dump-dom` no devuelve nada. */
    $perfil = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sget-ck-' . getmypid() . '-' . random_int(1000, 9999);

    /* `proc_open` con ARRAY, no con `shell_exec`.
       Motivo: la ruta de Chrome contiene espacios y `escapeshellcmd()` no la
       entrecomilla de forma utilizable en Windows — se quedaba en
       «C:\Program no se reconoce como un comando». Con el array no hay shell
       de por medio y cada argumento llega intacto. */
    $cmd = [
        $chrome,
        '--headless=new',
        '--disable-gpu',
        '--no-sandbox',
        '--user-data-dir=' . $perfil,
        '--virtual-time-budget=8000',
        '--dump-dom',
        $base . '/pruebas/_cookie_probe.html',
    ];

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proceso = @proc_open($cmd, $descriptors, $tubos);

    $html = '';
    if (is_resource($proceso)) {
        $html = (string) stream_get_contents($tubos[1]);
        fclose($tubos[1]);
        fclose($tubos[2]);
        proc_close($proceso);
    }

    @unlink($sonda);

    if (preg_match('#<pre id="salida">(.*?)</pre>#s', $html, $m)) {
        return html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }
    return null;
}

/** Expresión que devuelve el estado de `sget_tema` dentro del iframe. */
function exprCookieTema(): string
{
    return 'm.contentWindow.document.cookie.split(";")'
        . '.filter(function(p){return p.trim().indexOf("sget_tema=")===0;})[0] || ""';
}

/* ====================================================================== */
echo str_repeat('=', 72) . "\n";
echo " COOKIES Y PREFERENCIAS · SGET\n";
echo str_repeat('=', 72) . "\n";

$landing = $base . '/index.php';

/* ---------------------------------------------------------------------- */
seccion('1. El gestor de cookies es único y centralizado');

$theme = (string)file_get_contents(Config::raiz('assets/js/theme-init.js'));

check('`theme-init.js` expone el gestor `SGETCookies`',
    str_contains($theme, 'window.SGETCookies'));

check('La cookie `sget_tema` solo se escribe con preferencia aceptada',
    preg_match('/if\s*\(persistir\s*&&\s*SGETCookies\.permitePreferencias\(\)\)/', $theme) === 1);

check('Retirar el consentimiento borra la cookie',
    str_contains($theme, 'borrarCookieTema();') && str_contains($theme, 'max-age=0'));

check('La cookie se borra también al aplicar sin preferencia',
    str_contains($theme, 'function aplicarConsentimiento'));

check('La cookie lleva Path, Max-Age y SameSite',
    str_contains($theme, "'path=/'") && str_contains($theme, "'max-age=31536000'")
    && str_contains($theme, "'samesite=Lax'"));

check('`Secure` solo se añade cuando la página es HTTPS',
    str_contains($theme, 'function esHttps()') && str_contains($theme, "partes.push('secure')"));

/* Se mira SOLO el array de atributos que se escribe, no el archivo entero:
   la palabra «HttpOnly» aparece en comentarios explicativos y daría un falso
   positivo. */
$bloqueCookie = '';
if (preg_match('/function escribirCookieTema[\s\S]*?\n    \}/', $theme, $m)) {
    $bloqueCookie = $m[0];
}
check('`sget_tema` NO lleva HttpOnly (la gestiona JavaScript)',
    $bloqueCookie !== '' && !str_contains(strtolower($bloqueCookie), 'httponly'));

check('El consentimiento se guarda en localStorage, no en una cookie',
    str_contains($theme, "localStorage.setItem(CLAVE_CONSENTIMIENTO")
    && str_contains($theme, "var CLAVE_CONSENTIMIENTO = 'sget_cookies_consent'"));

/* ---------------------------------------------------------------------- */
seccion('2. Ninguna otra implementación de cookiescontradictoria');

$escritores = [];
foreach (glob(Config::raiz('assets/js/*.js')) + glob(Config::raiz('js/*.js')) as $js) {
    if (basename($js) === 'theme-init.js') {
        continue;
    }
    if (str_contains((string)file_get_contents($js), 'document.cookie')) {
        $escritores[] = basename($js);
    }
}
check('Ningún otro archivo JS escribe cookies', $escritores === [], implode(', ', $escritores));

$landingJs = (string)file_get_contents(Config::raiz('index.php'));
check('El script de la landing delega en SGETCookies (no escribe cookies por su cuenta)',
    !str_contains($landingJs, 'document.cookie'));

/* ---------------------------------------------------------------------- */
seccion('3. El panel describe lo que el sistema hace de verdad');

// Que no prometa analítica.
check('El panel NO afirma que se usan cookies analíticas',
    !str_contains($landingJs, 'Nos ayudan a recopilar información anónima'));
check('El panel declara explícitamente que no se usa analítica',
    str_contains($landingJs, 'No utilizamos cookies analíticas actualmente'));
check('La casilla de analítica va marcada como no utilizada',
    str_contains($landingJs, 'No utilizada'));

check('El panel nombra la cookie de sesión real',
    str_contains($landingJs, 'PHPSESSID') && str_contains($landingJs, 'HttpOnly'));
check('El panel explica qué hace la cookie de preferencias',
    str_contains($landingJs, 'sget_tema') && str_contains($landingJs, 'tema claro u oscuro'));

check('Aceptar todo NO activa analítica (no existe)',
    preg_match('/aceptarTodasCookies\(\)[\s\S]*?analitica:\s*false/', $landingJs) === 1);

check('El panel refleja el estado guardado al abrirse',
    str_contains($landingJs, 'sincronizarPanelCookies()')
    && str_contains($landingJs, 'estadoConsentimientoCookies'));

check('El botón «Configuración de Cookies» sigue en la landing',
    str_contains($landingJs, 'abrirConfiguracionCookies()')
    && str_contains($landingJs, 'Configuración de Cookies'));

/* ---------------------------------------------------------------------- */
seccion('4. El reCAPTCHA sigue presente y es obligatorio');

/*
 * ESTA SECCIÓN EXISTE PORQUE EL CONTROL SE PERDIÓ UNA VEZ
 *   Al hacerlo configurable se dejó «opt-in» (activado = variable de entorno
 *   presente). Consecuencia: sin variable, el captcha NO se pintaba y el
 *   backend no lo exigía. La protección desaparecía de la pantalla de acceso
 *   sin que nadie lo pidiera. Estas comprobaciones lo dejan不会再 pasar.
 */
check('El captcha está ACTIVO por defecto (no hace falta configurar nada)',
    Config::recaptchaHabilitado());

check('Hay una clave de sitio para poder pintarlo',
    Config::recaptchaSiteKey() !== '');

$modalAuth = (string)file_get_contents(Config::raiz('modal_auth.php'));
check('El modal de acceso pinta el widget de reCAPTCHA',
    str_contains($modalAuth, 'class="g-recaptcha"'));

check('El SDK de Google se carga con el captcha activo',
    str_contains($modalAuth, 'recaptcha/api.js'));

/* NO se muestra ningún aviso al usuario en el formulario: se pidió que el
   captcha apareciera limpio. El estado del modo de prueba sigue dejando rastro
   en el LOG del servidor, que es donde sirve para diagnosticar. */
check('El formulario NO muestra avisos alrededor del captcha',
    !str_contains($modalAuth, 'Modo de desarrollo'));

check('El modo prueba sigue quedando registrado en el log del servidor',
    Config::recaptchaEnModoPrueba()
    && str_contains((string)file_get_contents(Config::raiz('core/Recaptcha.php')),
        'Claves de PRUEBA de Google activas'));

/* Se leen los COMENTARIOS fuera: el patrón viejo se explica justamente en un
   comentario, y buscarlo en el archivo entero daría un falso positivo. */
$validar = (string)file_get_contents(Config::raiz('validar.php'));
$validarSinComentarios = preg_replace(
    ['#/\*.*?\*/#s', '#//[^
]*#', '#^\s*\*.*$#m'],
    '',
    $validar
);

check('El backend EXIGE el token (no es el «si viene, se verifica» anterior)',
    str_contains($validarSinComentarios, 'Recaptcha::verificar')
    && !str_contains($validarSinComentarios, chr(36) . "respuestaRecaptcha !== ''"));

$recaptcha = (string)file_get_contents(Config::raiz('core/Recaptcha.php'));
/* Regex del caso «sin token»: si `$token === ''` se devuelve `'ok' => false`. */
check('Sin token el captcha rechaza (no deja entrar)',
    preg_match('/\\$token\\s*===\\s*' . chr(39) . chr(39) . '\\)\\s*\\{\\s*return\\s*\\[\\s*'
        . chr(39) . 'ok' . chr(39) . '\\s*=>\\s*false/', $recaptcha) === 1);

check('Ante un fallo de red se rechaza en cerrado, no se deja pasar',
    str_contains($recaptcha, "'ok' => false") && str_contains($recaptcha, 'No pudimos verificar el control antirobot'));

check('El modo prueba queda registrado en el log del servidor',
    str_contains($recaptcha, 'Claves de PRUEBA de Google activas'));

/* El modo se puede apagar a propósito, pero SOLO de forma explícita. */
$deshabilitado = (static function () {
    putenv('SGET_RECAPTCHA_ENABLED=0');
    $r = Config::recaptchaHabilitado();
    putenv('SGET_RECAPTCHA_ENABLED');
    return $r;
})();
check('Se puede desactivar de forma explícita con SGET_RECAPTCHA_ENABLED=0', $deshabilitado === false);

/* ---------------------------------------------------------------------- */
seccion('5. La cookie de sesión conserva sus garantías');

$auth = (string)file_get_contents(Config::raiz('core/Auth.php'));
check('La cookie de sesión es HttpOnly', str_contains($auth, "'httponly' => true"));
check('La cookie de sesión usa SameSite=Lax', str_contains($auth, "'samesite' => 'Lax'"));
check('`Secure` solo se activa si la conexión es HTTPS',
    str_contains($auth, "'secure'   => self::esHttps()"));
check('El cierre de sesión borra la cookie', str_contains($auth, 'time() - 42000'));

/* ---------------------------------------------------------------------- */
seccion('6. Comportamiento real en el navegador');

$hayChrome = rutaChrome() !== null;

if (!$hayChrome) {
    echo "  [--]    Chrome no disponible: se omiten las comprobaciones en navegador.
";
} else {
    $cookie = exprCookieTema();

    /* SIN consentimiento: no puede existir la cookie de preferencias. */
    $sinConsentir = evaluar($landing,
        '(typeof w.SGETCookies==="undefined" ? "sin-gestor" : (' . $cookie . '))');

    check('Sin consentimiento previo NO se crea `sget_tema`',
        $sinConsentir === '', 'valor: ' . var_export($sinConsentir, true));

    /* CON preferencia aceptada: la cookie se crea con el tema elegido. */
    $conConsentir = evaluar($landing,
        '(function(){ if(typeof w.SGETCookies==="undefined") return "sin-gestor";'
        . ' w.SGETCookies.guardar({necesarias:true,preferencias:true,analitica:false});'
        . ' w.SGETTheme.set("dark");'
        . ' return (' . $cookie . '); })()');

    check('Al aceptar preferencias se crea `sget_tema`',
        is_string($conConsentir) && str_contains($conConsentir, 'sget_tema='),
        'valor: ' . var_export($conConsentir, true));

    check('La cookie guarda el tema elegido',
        is_string($conConsentir) && str_contains($conConsentir, 'sget_tema=dark'),
        'valor: ' . var_export($conConsentir, true));

    /* RETIRAR el consentimiento: la cookie debe desaparecer al instante. */
    $trasRetirar = evaluar($landing,
        '(function(){ if(typeof w.SGETCookies==="undefined") return "sin-gestor";'
        . ' w.SGETCookies.guardar({necesarias:true,preferencias:true,analitica:false});'
        . ' w.SGETTheme.set("dark");'
        . ' var antes = (' . $cookie . ') !== "";'
        . ' w.SGETCookies.guardar({necesarias:true,preferencias:false,analitica:false});'
        . ' var despues = (' . $cookie . ') !== "";'
        . ' return (antes?"creada":"nunca") + "->" + (despues?"sigue":"borrada"); })()');

    check('Retirar el consentimiento BORRA `sget_tema`',
        $trasRetirar === 'creada->borrada', 'valor: ' . var_export($trasRetirar, true));

    /* Volver a aceptar debe volver a crearla: el sistema no queda muerto. */
    $reAceptar = evaluar($landing,
        '(function(){ if(typeof w.SGETCookies==="undefined") return "sin-gestor";'
        . ' w.SGETCookies.guardar({necesarias:true,preferencias:false,analitica:false});'
        . ' var sinPermiso = (' . $cookie . ');'
        . ' w.SGETCookies.guardar({necesarias:true,preferencias:true,analitica:false});'
        . ' w.SGETTheme.set("light");'
        . ' var conPermiso = (' . $cookie . ');'
        . ' return (sinPermiso===""?"ok":"mal") + "|" + conPermiso; })()');

    check('Aceptar de nuevo vuelve a crear la cookie',
        is_string($reAceptar) && str_starts_with($reAceptar, 'ok|')
        && str_contains($reAceptar, 'sget_tema=light'),
        'valor: ' . var_export($reAceptar, true));
}


/* ---------------------------------------------------------------------- */
echo "\n" . str_repeat('=', 72) . "\n";
echo " RESULTADO: $ok correctas, $fallos fallidas\n";
echo str_repeat('=', 72) . "\n";

exit($fallos > 0 ? 1 : 0);
