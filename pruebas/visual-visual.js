/*
 * pruebas/visual-visual.js
 * -----------------------------------------------------------------------------
 * Verificación visual de MODALES y del cambio de TEMA, en Chrome headless:
 *
 *   1. Landing: los 4 modales públicos arrancan cerrados, se abren, tienen
 *      tamaño real, y son claros en tema claro y oscuros en tema oscuro.
 *   2. CRUD: no queda ningún drawer; crear/editar son modales centrados con
 *      tamaño real y buen contraste en ambos temas.
 *
 * Además comprueba, en CSS, que ningún contenedor de overlay declare `display`
 * (el error que dejaba overlays invisibles bloqueando la página).
 *
 *     php -S 127.0.0.1:8899 -t .
 *     node pruebas/visual-visual.js
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const BASE = process.argv[2] || 'http://127.0.0.1:8899';
const PERFIL = path.join(os.tmpdir(), 'sget-chrome-visual-' + process.pid);

let ok = 0, fallos = 0;
function check(t, c, d = '') {
    if (c) { ok++; console.log('  [OK]   ' + t); }
    else { fallos++; console.log('  [FALLA] ' + t + (d ? ' -> ' + d : '')); }
}

function cargar(url) {
    return execFileSync(CHROME, [
        '--headless=new', '--disable-gpu', '--no-sandbox', '--disable-dev-shm-usage',
        // Bloquea subrecursos externos: si no, el tiempo virtual se congela
        '--host-resolver-rules=MAP * 127.0.0.1, EXCLUDE 127.0.0.1',
        '--window-size=1400,1000',
        `--user-data-dir=${PERFIL}`, '--virtual-time-budget=20000', '--dump-dom', url,
    ], { maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
}

process.on('exit', () => { try { fs.rmSync(PERFIL, { recursive: true, force: true }); } catch (e) {} });

function leerSonda(salida, inicio, fin, nombre) {
    // OJO: dentro de un literal de cadena hay que escribir \\s, no \s.
    // Con un solo backslash, JavaScript lo interpreta como la letra 's' y el
    // patrón queda (sS*?) y nunca encuentra nada.
    const m = String(salida).match(new RegExp(inicio + '([\\s\\S]*?)' + fin));
    if (!m) {
        check(nombre + ': la sonda responde', false, 'no se encontró ' + inicio);
        return null;
    }
    if (m[1].trim() === '' || m[1].trim().indexOf('FALLOS=0') === 0) {
        check(nombre + ': la sonda produjo resultados', false, 'la sonda volvió vacía');
        return null;
    }
    m[1].trim().split('\n').forEach((line) => {
        if (!line.trim() || !line.includes('|') || line.startsWith('INFO')) return;
        const correcto = line.startsWith('OK');
        const q = line.replace('|', '~~').split('~~').map((x) => x.trim());
        check(q[1] + (q[2] ? '  [' + q[2] + ']' : ''), correcto, correcto ? '' : (q[2] || ''));
    });
    return m[1];
}

/* --------------------------------------------------------------------------
   1) Landing: login, registro, política y cookies
   -------------------------------------------------------------------------- */
console.log('\n=== Landing: modales de login y crear cuenta ===');
leerSonda(cargar(BASE + '/pruebas/_landing_modales_probe.html'), 'LAND_START', 'LAND_END', 'landing-modales');

/* --------------------------------------------------------------------------
   2) Landing: los componentes se ven (botones, campos, etiquetas)
   --------------------------------------------------------------------------
   El fallo original: index.css importaba 01 y 04 pero NO 03-componentes.css,
   así que .sget-btn/.sget-input no existían y los botones del login salían
   como texto plano. */
console.log('\n=== Landing: los componentes tienen apariencia ===');
leerSonda(cargar(BASE + '/pruebas/_landing_componentes_probe.html'), 'BTN_START', 'BTN_END', 'landing-componentes');

/* --------------------------------------------------------------------------
   3) CRUD: crear y editar son modales, no drawers
   -------------------------------------------------------------------------- */
console.log('\n=== CRUD: crear y editar como modales ===');
leerSonda(cargar(BASE + '/pruebas/_modales_crud_probe.html'), 'VIS_START', 'VIS_END', 'crud-modales');

/* --------------------------------------------------------------------------
   3) Regla de CSS: ningún overlay declara `display`
   -------------------------------------------------------------------------- */
console.log('\n=== CSS: ningún overlay heredado puede pisar el `display` ===');/* `.sget-modal-wrap` y `.sget-drawer-wrap` SÍ deben declarar display: son los
   contenedores propios del sistema y no dependen de utilidades de Tailwind.
   El peligro es `.modal-isla-container` / `.sget-overlay`, que se aplican sobre
   marcado que también usa `hidden`: si declaran `display`, pisan el
   `display:none` de Tailwind y dejan un overlay invisible bloqueando la página
   (fue exactamente el fallo que dejaba los modales de login "sin verse nada"). */
const PROPIOS = ['.sget-modal-wrap', '.sget-drawer-wrap'];

for (const archivo of ['assets/css/04-modales.css', 'assets/css/index.css']) {
    const css = fs.readFileSync(archivo, 'utf8');

    for (const bloque of css.matchAll(/\.(sget-overlay|modal-isla-container)\s*\{([^}]*)\}/g)) {
        const declara = /(^|;)\s*display\s*:/.test(bloque[2]);
        check(archivo + ' · .' + bloque[1] + ' NO declara `display`', !declara,
              declara ? bloque[0].slice(0, 90) : '');
    }

    // Los contenedores del sistema los define 04-modales.css; index.css solo
    // lo importa, así que no se le exige definirlos.
    if (archivo.indexOf('04-modales') === -1) continue;

    for (const sel of PROPIOS) {
        const m = css.match(new RegExp(sel.replace('.', '\\.') + '\\s*\\{([^}]*)\\}'));
        check(archivo + ' · ' + sel + ' SÍ declara `display`', !!m && /display\s*:/.test(m[1]),
              m ? m[1].slice(0, 60) : 'no encontrado');
    }
}

console.log('\n' + '='.repeat(60));
console.log(' TOTAL: ' + ok + ' correctas, ' + fallos + ' fallidas');
console.log('='.repeat(60) + '\n');
process.exit(fallos === 0 ? 0 : 1);
