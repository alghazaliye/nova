<?php
/**
 * NOVA Messenger - Development Router
 * Usage: php -S 0.0.0.0:8080 backend/public/router.php  (run from project root)
 */
declare(strict_types=1);

// Resolve all paths absolutely.
// backend/public/router.php → up two levels → project root (works on any host).
define('PROJECT_ROOT', realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2));

// Enable error display for diagnosing 500 errors (development)
ini_set('display_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', PROJECT_ROOT . '/backend/php_errors.log');
define('PUBLIC_DIR', PROJECT_ROOT . '/backend/public');

// ---------- 0) Load .env ----------
$envFile = PROJECT_ROOT . '/backend/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        // Remove surrounding quotes if present
        if (strlen($v) >= 2 && $v[0] === '"' && $v[strlen($v) - 1] === '"') {
            $v = substr($v, 1, -1);
        }
        if (!array_key_exists($k, $_ENV)) {
            $_ENV[$k] = $v;
        }
        putenv("$k=$v");
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = '/' . ltrim($uri, '/');

// ---------- 1) API routes -> index.php ----------
if (strpos($uri, '/api/') === 0) {
    require PUBLIC_DIR . '/index.php';
    return false;
}

// ---------- 2) Flutter web engine assets ----------
// Flutter >=3.47 resolves engine assets under /assets/packages/flutter_web_assets/
// while the build emits /canvaskit/, /skwasm* and /fonts/ at the web root.
$engineAliasMap = [
    'canvaskit.js'                      => 'canvaskit/canvaskit.js',
    'canvaskit.wasm'                    => 'canvaskit/canvaskit.wasm',
    'canvaskit.js.symbols'              => 'canvaskit/canvaskit.js.symbols',
    'chromium/canvaskit.js'             => 'canvaskit/chromium/canvaskit.js',
    'chromium/canvaskit.wasm'           => 'canvaskit/chromium/canvaskit.wasm',
    'chromium/canvaskit.js.symbols'     => 'canvaskit/chromium/canvaskit.js.symbols',
    'webparagraph/canvaskit.js'         => 'canvaskit/webparagraph/canvaskit.js',
    'webparagraph/canvaskit.wasm'       => 'canvaskit/webparagraph/canvaskit.wasm',
    'webparagraph/canvaskit.js.symbols' => 'canvaskit/webparagraph/canvaskit.js.symbols',
    'skwasm.js'                         => 'skwasm.js',
    'skwasm.wasm'                       => 'skwasm.wasm',
    'skwasm.js.symbols'                 => 'skwasm.js.symbols',
    'skwasm_heavy.js'                   => 'skwasm_heavy.js',
    'skwasm_heavy.wasm'                 => 'skwasm_heavy.wasm',
    'skwasm_heavy.js.symbols'           => 'skwasm_heavy.js.symbols',
    'wimp.js'                           => 'wimp.js',
    'wimp.wasm'                         => 'wimp.wasm',
    'wimp.js.symbols'                   => 'wimp.js.symbols',
];
$enginePrefix = '/assets/packages/flutter_web_assets/';
if (strpos($uri, $enginePrefix) === 0) {
    $engineFile = substr($uri, strlen($enginePrefix));
    if (isset($engineAliasMap[$engineFile])) {
        $realPath = realpath(PROJECT_ROOT . '/web_app/' . $engineAliasMap[$engineFile]);
        if ($realPath !== false && is_file($realPath)) {
            $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'js' => 'application/javascript; charset=utf-8',
                'mjs' => 'application/javascript; charset=utf-8',
                'wasm' => 'application/wasm',
                'json' => 'application/json; charset=utf-8',
                'ttf' => 'font/ttf',
                'otf' => 'font/otf',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
            ];
            header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
            header('Cross-Origin-Resource-Policy: cross-origin');
            header('Cross-Origin-Embedder-Policy: require-corp');
            header('Cross-Origin-Opener-Policy: same-origin');
            header('Access-Control-Allow-Origin: *');
            header('Content-Length: ' . filesize($realPath));
            readfile($realPath);
            return true;
        }
    }
    // Fonts requested under the virtual path
    if (strpos($engineFile, 'fonts/') === 0) {
        $realPath = realpath(PROJECT_ROOT . '/web_app/fonts/' . substr($engineFile, 6));
        if ($realPath !== false && is_file($realPath)) {
            header('Content-Type: font/woff2');
            header('Cross-Origin-Resource-Policy: cross-origin');
            header('Access-Control-Allow-Origin: *');
            readfile($realPath);
            return true;
        }
    }
}

// ---------- 2.4) Admin panel ----------
$adminDir = PROJECT_ROOT . '/admin';
if (strpos($uri, '/admin') === 0) {
    $adminRel = substr($uri, 6); // e.g. /admin/ or /admin/login.php
    if ($adminRel === '' || $adminRel === '/') {
        $adminRel = '/index.php';
    }
    // API routes under /admin/* (no .php extension) are handled by the
    // main index.php router, not as admin panel files.
    if (strpos($adminRel, '.php') === false && strpos($adminRel, '.') === false) {
        require PUBLIC_DIR . '/index.php';
        return true;
    }
    $adminFile = realpath($adminDir . $adminRel);
    if ($adminFile !== false && is_file($adminFile) && strpos($adminFile, $adminDir) === 0) {
        require $adminFile;
        return true;
    }
    http_response_code(404);
    echo 'Admin page not found';
    return true;
}

// ---------- 2.5) Web app bare path -> redirect to /web_app/ ----------
if ($uri === '/web_app') {
    header('Location: /web_app/', true, 302);
    return true;
}

// ---------- 2.5b) Flutter web build files requested at the root (old cached page)
// Old builds referenced flutter_bootstrap.js, main.dart.js, flutter_service_worker.js etc. at the root.
// Redirect them to /web_app/ so legacy cached pages never produce a 404.
$rootFlutterFiles = [
    '/flutter_bootstrap.js',
    '/flutter_service_worker.js',
    '/main.dart.js',
    '/main.dart.mjs',
    '/main.dart.wasm',
    '/main.dart.js.map',
    '/main.dart.mjs.map',
    '/manifest.json',
    '/favicon.png',
    '/icons/',
];
foreach ($rootFlutterFiles as $rf) {
    if ($uri === $rf || strpos($uri, $rf) === 0) {
        header('Location: /web_app' . $uri, true, 301);
        return true;
    }
}

// ---------- 3) Physical files: public dir, project root, web app ----------
$webAppDir = PROJECT_ROOT . '/web_app';
$candidates = [
    PUBLIC_DIR . $uri,
    PROJECT_ROOT . $uri,
    $uri === '/web_app/' ? $webAppDir . '/index.html' : (strpos($uri, '/web_app/') === 0 || $uri === '/web_app' ? $webAppDir . substr($uri, 8) : ''),
];
foreach ($candidates as $candidate) {
    if (strpos($candidate, '..') !== false) {
        http_response_code(403);
        echo 'Forbidden';
        return true;
    }
    $resolved = realpath($candidate);
    if ($resolved !== false && is_file($resolved)) {
        // Pre-compressed variants (Brotli > Gzip) for static assets
        $origExt = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        if ($origExt !== 'php') {
            $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
            $compressed = false;
            if (strpos($acceptEncoding, 'br') !== false && is_file($resolved . '.br')) {
                $compressed = $resolved . '.br';
                header('Content-Encoding: br');
            } elseif (strpos($acceptEncoding, 'gzip') !== false && is_file($resolved . '.gz')) {
                $compressed = $resolved . '.gz';
                header('Content-Encoding: gzip');
            }
            if ($compressed !== false) {
                $mimeTypes = [
                    'html' => 'text/html; charset=utf-8',
                    'js'   => 'application/javascript; charset=utf-8',
                    'mjs'  => 'application/javascript; charset=utf-8',
                    'css'  => 'text/css; charset=utf-8',
                    'json' => 'application/json; charset=utf-8',
                    'wasm' => 'application/wasm',
                    'ttf'  => 'font/ttf',
                    'otf'  => 'font/otf',
                    'woff' => 'font/woff',
                    'woff2' => 'font/woff2',
                    'png'  => 'image/png',
                    'svg'  => 'image/svg+xml',
                ];
                header('Content-Type: ' . ($mimeTypes[$origExt] ?? 'application/octet-stream'));
                header('Vary: Accept-Encoding');
                header('Content-Length: ' . filesize($compressed));
                if (in_array($origExt, ['wasm', 'js', 'mjs'], true)) {
                    // skwasm (Flutter wasm renderer) requires cross-origin isolation
                    header('Cross-Origin-Opener-Policy: same-origin');
                    header('Cross-Origin-Embedder-Policy: require-corp');
                }
                readfile($compressed);
                return true;
            }
        }
        $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        if ($ext === 'php') {
            require $resolved;
        } else {
            $mimeTypes = [
                'html' => 'text/html; charset=utf-8',
                'js'   => 'application/javascript; charset=utf-8',
                'mjs'  => 'text/javascript; charset=utf-8',
                'css'  => 'text/css; charset=utf-8',
                'json' => 'application/json; charset=utf-8',
                'png'  => 'image/png',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
                'svg'  => 'image/svg+xml',
                'ico'  => 'image/x-icon',
                'mp3'  => 'audio/mpeg',
                'mp4'  => 'video/mp4',
                'webm' => 'video/webm',
                'ogg'  => 'audio/ogg',
                'wav'  => 'audio/wav',
                'weba' => 'audio/webm',
                'm4a'  => 'audio/mp4',
                'aac'  => 'audio/aac',
                'pdf'  => 'application/pdf',
                'wasm' => 'application/wasm',
                'ttf'  => 'font/ttf',
                'otf'  => 'font/otf',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
            ];
            header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Range');
            if (in_array($ext, ['wasm', 'js', 'mjs'], true)) {
                header('Cross-Origin-Opener-Policy: same-origin');
                header('Cross-Origin-Embedder-Policy: require-corp');
            }
            header('Access-Control-Expose-Headers: Content-Range, Content-Length, Accept-Ranges');
            header('Accept-Ranges: bytes');
            $size = filesize($resolved);
            header('Content-Length: ' . $size);
            if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $rm)) {
                $start = (int)$rm[1];
                $end = $rm[2] === '' ? $size - 1 : (int)$rm[2];
                $end = min($end, $size - 1);
                $length = $end - $start + 1;
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
                header('Content-Length: ' . $length);
                $fh = fopen($resolved, 'rb');
                fseek($fh, $start);
                while ($length > 0 && !feof($fh)) {
                    $chunk = min(65536, $length);
                    $length -= $chunk;
                    echo fread($fh, $chunk);
                }
                fclose($fh);
            } else {
                readfile($resolved);
            }
        }
        return true;
    }
}

// ---------- 4) Storage files via virtual media paths ----------
if (preg_match('#^/media/(.+)$#i', $uri, $mm)) {
    $file = rtrim($_ENV['STORAGE_PATH'] ?? (PROJECT_ROOT . '/backend/storage'), '/') . '/' . $mm[1];
    if (is_file($file) && strpos($file, '..') === false) {
        static $mimeTypes = [
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
            'm4v' => 'video/x-m4v',
            'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg',
            'weba' => 'audio/webm', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp',
        ];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        header('Access-Control-Allow-Origin: *');
        header('Accept-Ranges: bytes');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return true;
        }
        $size = filesize($file);
        header('Content-Length: ' . $size);
        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if ($range && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $rm)) {
            $start = $rm[1] !== '' ? (int)$rm[1] : 0;
            $end = $rm[2] !== '' ? (int)$rm[2] : $size - 1;
            if ($start >= $size) {
                http_response_code(416);
                header("Content-Range: bytes */{$size}");
                return true;
            }
            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$size}");
            $fp = fopen($file, 'rb');
            fseek($fp, $start);
            echo fread($fp, $end - $start + 1);
            fclose($fp);
        } else {
            readfile($file);
        }
        return true;
    }
}

// ---------- 5) Default: API entry point ----------
require PUBLIC_DIR . '/index.php';
return true;
