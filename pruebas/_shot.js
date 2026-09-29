const { execFileSync } = require('child_process');
const os = require('os');
const path = require('path');
const CHROME = 'C:' + String.fromCharCode(92) + 'Program Files' + String.fromCharCode(92) +
               'Google' + String.fromCharCode(92) + 'Chrome' + String.fromCharCode(92) +
               'Application' + String.fromCharCode(92) + 'chrome.exe';
const perfil = path.join(os.tmpdir(), 'sget-shot-' + process.pid);
const url = process.argv[2], out = process.argv[3];
const b = String.fromCharCode(92);
const args = ['--headless=new', '--disable-gpu', '--no-sandbox', '--hide-scrollbars',
    '--user-data-dir=' + perfil, '--window-size=1440,2600', '--virtual-time-budget=20000',
    '--screenshot=' + out, url];
execFileSync(CHROME, args, { stdio: ['ignore', 'ignore', 'ignore'] });
console.log('captura -> ' + out);
