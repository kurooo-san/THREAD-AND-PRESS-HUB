// Runs Thread & Press Hub at http://localhost:3000
//
//   npm start        (or: node server.js)
//
// Node cannot execute PHP, so this starts PHP's built-in server on an
// internal port and forwards every request to it. scripts/router.php blocks
// the private folders (.env, storage/, database/, vendor/...) that Apache
// blocks via .htaccess. Start MySQL in the XAMPP Control Panel first.

const http = require('http');
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

const PORT = Number(process.env.PORT) || 3000;
const PHP_PORT = Number(process.env.PHP_PORT) || 3080;
const PHP_BIN = process.env.PHP_BIN
  || (fs.existsSync('C:\\xampp\\php\\php.exe') ? 'C:\\xampp\\php\\php.exe' : 'php');

const php = spawn(PHP_BIN, ['-S', `127.0.0.1:${PHP_PORT}`, '-t', '.', 'scripts/router.php'], {
  cwd: __dirname,
  stdio: ['ignore', 'ignore', 'pipe'],
});
php.stderr.on('data', (d) => {
  // The built-in server logs every request to stderr; only show problems.
  for (const line of d.toString().split('\n')) {
    if (/error|warning|fatal|failed/i.test(line) && !/ - No error/i.test(line)) console.error(line.trim());
  }
});
php.on('exit', (code) => {
  console.error(`PHP stopped (exit ${code}). Is port ${PHP_PORT} free and ${PHP_BIN} correct?`);
  process.exit(1);
});

function forward(req, res, attempt = 0) {
  const upstream = http.request(
    { host: '127.0.0.1', port: PHP_PORT, method: req.method, path: req.url, headers: req.headers },
    (up) => {
      res.writeHead(up.statusCode, up.headers);
      up.pipe(res);
    },
  );
  upstream.on('error', (err) => {
    // PHP needs a moment to boot right after start-up.
    if (err.code === 'ECONNREFUSED' && attempt < 20 && !res.headersSent) {
      return setTimeout(() => forward(req, res, attempt + 1), 150);
    }
    if (!res.headersSent) res.writeHead(502, { 'Content-Type': 'text/plain' });
    res.end('PHP server is not responding.');
  });
  req.pipe(upstream);
}

const server = http.createServer((req, res) => forward(req, res));
// AI product generation can take up to 240s; don't cut it off.
server.requestTimeout = 0;
server.on('error', (err) => {
  console.error(err.code === 'EADDRINUSE'
    ? `Port ${PORT} is already in use. Close the other program first.`
    : err.message);
  php.kill();
  process.exit(1);
});
server.listen(PORT, () => {
  console.log(`Thread & Press Hub running at http://localhost:${PORT}  (Ctrl+C to stop)`);
});

for (const sig of ['SIGINT', 'SIGTERM']) {
  process.on(sig, () => { php.kill(); process.exit(0); });
}
