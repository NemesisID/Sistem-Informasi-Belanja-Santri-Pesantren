<?php

/**
 * Backup database harian untuk shared hosting cPanel (tanpa SSH).
 * Didaftarkan lewat cPanel > Cron Jobs, contoh perintahnya:
 *   /usr/local/bin/php /home/USERNAME/nata-api/backup-db.php
 *
 * Murni PHP (PDO + gzip) - tidak butuh mysqldump/exec yang sering
 * dinonaktifkan di shared hosting.
 *
 * Hasil   : ~/backups/nata-YYYYmmdd-HHMMss.sql.gz
 * Retensi : 14 backup terakhir, sisanya dihapus otomatis.
 * Log     : storage/logs/backup.log
 */

declare(strict_types=1);

$appRoot = __DIR__;
require $appRoot . '/vendor/autoload.php';

// ---- Baca kredensial dari .env ---------------------------------------------
$env = [];

if (is_file($appRoot . '/.env')) {
    foreach (file($appRoot . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/', trim($line), $m)) {
            $env[$m[1]] = trim(trim($m[2]), "\"'");
        }
    }
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$name = $env['DB_DATABASE'] ?? '';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
$logFile = $appRoot . '/storage/logs/backup.log';

$log = function (string $message) use ($logFile): void {
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
};

if ($name === '' || $user === '') {
    $log('GAGAL: kredensial DB belum lengkap di .env');
    echo "GAGAL: kredensial DB belum lengkap di .env\n";
    exit(1);
}

$backupDir = dirname($appRoot) . '/backups';

if (! is_dir($backupDir) && ! @mkdir($backupDir, 0755, true)) {
    $log('GAGAL: tidak bisa membuat folder ' . $backupDir);
    exit(1);
}

// ---- Koneksi ----------------------------------------------------------------
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    $log('GAGAL: koneksi DB - ' . $e->getMessage());
    echo "GAGAL: koneksi DB - " . $e->getMessage() . "\n";
    exit(1);
}

// ---- Dump --------------------------------------------------------------------
$target = $backupDir . '/nata-' . date('Ymd-His') . '.sql.gz';
$gz = gzopen($target, 'wb9');

$write = function (string $text) use ($gz): void {
    gzwrite($gz, $text);
};

$write("-- Backup database \"{$name}\" - " . date('Y-m-d H:i:s') . "\n");
$write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();

    $write("-- ----------------------------\n-- Struktur tabel `{$table}`\n-- ----------------------------\n");
    $write($create['Create Table'] . ";\n\n");

    // Catatan: memuat semua baris ke memori. Untuk ukuran database aplikasi
    // santri (puluhan ribu baris) ini aman; kalau kelak jutaan baris, ganti
    // dengan chunk query (implementasi bertahap).
    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        continue;
    }

    $write("-- Isi tabel `{$table}`\n");

    foreach ($rows as $row) {
        $cols = array_map(fn (string $c): string => "`{$c}`", array_keys($row));
        $vals = array_map(
            fn ($v): string => $v === null ? 'NULL' : $pdo->quote((string) $v),
            array_values($row)
        );
        $write('INSERT INTO `' . $table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n");
    }

    $write("\n");
}

$write("SET FOREIGN_KEY_CHECKS=1;\n");
gzclose($gz);

// ---- Retensi: simpan 14 backup terakhir -------------------------------------
$files = glob($backupDir . '/nata-*.sql.gz');

if (is_array($files) && count($files) > 14) {
    sort($files);

    foreach (array_slice($files, 0, count($files) - 14) as $old) {
        @unlink($old);
    }
}

$size = number_format((float) filesize($target) / 1024) . ' KB';
$log('OK: ' . basename($target) . " ({$size}, " . count($tables) . ' tabel)');
echo 'OK: ' . basename($target) . " ({$size}, " . count($tables) . " tabel)\n";
