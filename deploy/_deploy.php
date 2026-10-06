<?php

/**
 * Endpoint deploy paket untuk shared hosting cPanel tanpa SSH.
 * Dipanggil oleh workflow GitHub Actions (tombol "Run workflow").
 *
 * Cara kerja:
 *  1. Validasi token (header X-Deploy-Token dibandingkan dengan DEPLOY_TOKEN
 *     di .env server). Salah / tidak ada -> 404.
 *  2. Terima ZIP paket (POST multipart, field "zip").
 *  3. Ekstrak ke folder sementara, lalu salin menimpa isi aplikasi KECUALI
 *     ".env" dan "storage/**" (konfigurasi server & foto santri tak tersentuh).
 *  4. Bersihkan public/assets (aset SPA lama), pastikan skeleton storage ada.
 *  5. Arsipkan paket ke ~/deploy-incoming/ (bahan rollback, 10 terakhir).
 *  6. ?migrate=1 -> jalankan "php artisan migrate --force" in-process.
 *
 * Contoh pemanggilan (di GitHub Actions):
 *   curl -sf -X POST "https://DOMAIN/_deploy.php?migrate=1" \
 *        -H "X-Deploy-Token: $TOKEN" -F "zip=@paket.zip"
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function readEnvKey(string $file, string $key): ?string
{
    if (! is_file($file)) {
        return null;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)$/', $line, $m)) {
            return trim(trim($m[1]), "\"'");
        }
    }

    return null;
}

function deleteDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

function copyRecursive(string $src, string $dst): int
{
    $count = 0;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        $target = $dst . DIRECTORY_SEPARATOR . $items->getSubPathName();

        if ($item->isDir()) {
            @mkdir($target, 0755, true);
            continue;
        }

        @mkdir(dirname($target), 0755, true);

        if (@copy($item->getPathname(), $target)) {
            $count++;
        }
    }

    return $count;
}

$appRoot = dirname(__DIR__);
$logFile = $appRoot . '/storage/logs/deploy.log';

$log = function (string $message) use ($logFile): void {
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
};

// ---- 1. Autentikasi token -------------------------------------------------
$token = (string) ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '');
$secret = readEnvKey($appRoot . '/.env', 'DEPLOY_TOKEN');

if ($secret === null || $secret === '' || $token === '' || ! hash_equals($secret, $token)) {
    $log('DITOLAK: token tidak valid dari ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    respond(404, ['message' => 'Not Found']);
}

// ---- 2. Terima ZIP paket ---------------------------------------------------
if (! isset($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
    respond(422, ['ok' => false, 'error' => 'ZIP tidak diterima (cek upload_max_filesize/post_max_size di .user.ini).']);
}

$zip = new ZipArchive;

if ($zip->open($_FILES['zip']['tmp_name']) !== true) {
    respond(422, ['ok' => false, 'error' => 'Berkas ZIP tidak bisa dibuka.']);
}

// ---- 3. Ekstrak ke folder sementara (aplikasi lama tetap aman) -------------
$tmp = $appRoot . '/deploy-tmp-' . bin2hex(random_bytes(4));

if (! @mkdir($tmp, 0755, true)) {
    $zip->close();
    respond(500, ['ok' => false, 'error' => 'Gagal membuat folder sementara.']);
}

// Deteksi folder pembungkus tunggal di root ZIP (mis. "nata-api/").
$tops = [];

for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = ltrim((string) $zip->getNameIndex($i), '/');

    if ($name === '') {
        continue;
    }

    $top = substr($name, 0, (int) strcspn($name, '/'));

    if ($top === '' || $top === '__MACOSX') {
        continue;
    }

    $tops[$top] = true;
}

$prefix = count($tops) === 1 ? array_key_first($tops) . '/' : '';

$entries = 0;

for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = ltrim((string) $zip->getNameIndex($i), '/');

    if ($name === '' || str_starts_with($name, '__MACOSX')) {
        continue;
    }

    if ($prefix !== '' && ! str_starts_with($name, $prefix)) {
        continue;
    }

    $rel = $prefix === '' ? $name : substr($name, strlen($prefix));

    if ($rel === '') {
        continue;
    }

    // Jangan pernah sentuh konfigurasi server dan folder storage (foto santri).
    if ($rel === '.env' || $rel === 'storage' || str_starts_with($rel, 'storage/')) {
        continue;
    }

    // Amankan dari zip-slip.
    if (str_starts_with($rel, '..') || str_contains($rel, '/../')) {
        continue;
    }

    $zip->extractTo($tmp, $name);
    $entries++;
}

$zip->close();

// ---- 4. Pasang versi baru ---------------------------------------------------
deleteDir($appRoot . '/public/assets'); // aset SPA lama dibuang agar tidak menumpuk
$copied = copyRecursive($tmp, $appRoot);
deleteDir($tmp);

foreach ([
    'storage/app/private/santri-foto',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
] as $dir) {
    @mkdir($appRoot . '/' . $dir, 0755, true);
}

// ---- 5. Arsipkan paket untuk rollback ---------------------------------------
$incoming = dirname($appRoot) . '/deploy-incoming';
@mkdir($incoming, 0755, true);
$archived = 'paket-' . date('Ymd-His') . '.zip';
@move_uploaded_file($_FILES['zip']['tmp_name'], $incoming . '/' . $archived);

$old = glob($incoming . '/paket-*.zip');

if (is_array($old) && count($old) > 10) {
    sort($old);

    foreach (array_slice($old, 0, count($old) - 10) as $file) {
        @unlink($file);
    }
}

// ---- 6. (Opsional) migrasi database in-process ------------------------------
$migrated = false;
$migrateOutput = '';

if (($_GET['migrate'] ?? '') === '1') {
    try {
        require $appRoot . '/vendor/autoload.php';
        $app = require $appRoot . '/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = trim(\Illuminate\Support\Facades\Artisan::output());
        $migrated = true;
    } catch (Throwable $e) {
        $log('GAGAL migrate: ' . $e->getMessage());
        respond(500, ['ok' => false, 'error' => 'Migrasi gagal: ' . $e->getMessage()]);
    }
}

$version = trim((string) @file_get_contents($appRoot . '/version.txt')) ?: '(tanpa versi)';
$log('OK: ' . $version . ' dipasang (' . $entries . ' entri zip, ' . $copied . ' berkas, migrate=' . ($migrated ? 'ya' : 'tidak') . ')');

respond(200, [
    'ok' => true,
    'paket' => $version,
    'entri_zip' => $entries,
    'berkas_dipasang' => $copied,
    'arsip_rollback' => $archived,
    'migrasi' => $migrated ? ($migrateOutput ?: 'selesai') : 'dilewati',
]);
