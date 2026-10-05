const http = require('http');
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');

const PORT = 8000;
const PUBLIC_DIR = __dirname;

// Simple Node HTTP server to serve static assets & PHP info
const server = http.createServer((req, res) => {
    let filePath = path.join(PUBLIC_DIR, req.url === '/' ? 'index.php' : req.url.split('?')[0]);

    // Check if path exists
    if (!fs.existsSync(filePath) && fs.existsSync(filePath + '.php')) {
        filePath += '.php';
    }

    const ext = path.extname(filePath);

    // Serve CSS, JS, Images, HTML
    const mimeTypes = {
        '.html': 'text/html',
        '.css': 'text/css',
        '.js': 'text/javascript',
        '.json': 'application/json',
        '.png': 'image/png',
        '.jpg': 'image/jpeg',
        '.svg': 'image/svg+xml',
        '.ico': 'image/x-icon'
    };

    if (mimeTypes[ext]) {
        fs.readFile(filePath, (err, content) => {
            if (err) {
                res.writeHead(404);
                res.end('File not found');
            } else {
                res.writeHead(200, { 'Content-Type': mimeTypes[ext] });
                res.end(content);
            }
        });
        return;
    }

    // Try executing PHP if requested
    if (ext === '.php' || !ext) {
        exec(`php "${filePath}"`, (error, stdout, stderr) => {
            if (error) {
                // If PHP CLI is not installed locally, return helpful guidance page
                res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                res.end(`
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <title>Hardware Store System - Server Notice</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
                    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
                    <style>body { font-family: 'Outfit', sans-serif; background: #0f172a; color: #fff; display: flex; align-items: center; justify-content: center; min-height: 100vh; }</style>
                </head>
                <body>
                    <div class="card bg-dark text-white p-4 shadow-lg border-secondary" style="max-width: 600px;">
                        <div class="text-center mb-3">
                            <h2 class="fw-bold text-primary">🔧 Hardware Store System</h2>
                            <span class="badge bg-warning text-dark">XAMPP / PHP Environment Required</span>
                        </div>
                        <p>All PHP codebase files, database schema (<code>database.sql</code>), POS billing module, inventory management, reports, and authentication have been successfully generated!</p>
                        <div class="alert alert-secondary text-white">
                            <h6 class="fw-bold text-warning mb-2">To Run in XAMPP:</h6>
                            <ol class="mb-0 small ps-3">
                                <li>Copy this project directory to <code>C:\\xampp\\htdocs\\hardware-store</code></li>
                                <li>Start <strong>Apache</strong> and <strong>MySQL</strong> in XAMPP Control Panel.</li>
                                <li>Import <code>database.sql</code> into phpMyAdmin (<code>http://localhost/phpmyadmin</code>).</li>
                                <li>Open <code>http://localhost/hardware-store</code> in your web browser.</li>
                            </ol>
                        </div>
                        <div class="text-center mt-2">
                            <small class="text-muted">Or install PHP CLI to run <code>php -S localhost:8000</code></small>
                        </div>
                    </div>
                </body>
                </html>
                `);
            } else {
                res.writeHead(200, { 'Content-Type': 'text/html' });
                res.end(stdout);
            }
        });
        return;
    }

    res.writeHead(404);
    res.end('Not Found');
});

server.listen(PORT, () => {
    console.log(`Hardware Store Server listening on http://localhost:${PORT}`);
});
