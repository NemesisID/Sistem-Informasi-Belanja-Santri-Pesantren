<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Status Aplikasi</title>
<style>
  body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #e2e8f0;
         display: flex; min-height: 100vh; margin: 0; align-items: center; justify-content: center; }
  .card { background: #1e293b; border-radius: 12px; padding: 32px 40px;
          box-shadow: 0 10px 30px rgba(0,0,0,.4); min-width: 340px; }
  h1 { margin: 0 0 4px; font-size: 20px; }
  .sub { color: #94a3b8; font-size: 13px; margin-bottom: 20px; }
  dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 20px; font-size: 14px; margin: 0; }
  dt { color: #94a3b8; }
  dd { margin: 0; font-weight: 600; }
  .ok { color: #4ade80; }
  .bad { color: #f87171; }
</style>
</head>
<body>
<div class="card">
  <h1>Status Aplikasi</h1>
  <div class="sub">{{ $paket }} &middot; {{ $waktu_server }} {{ $zona_waktu }}</div>
  <dl>
    <dt>Aplikasi</dt><dd class="ok">Berjalan</dd>
    <dt>Database</dt><dd class="{{ str_starts_with($database, 'GAGAL') ? 'bad' : 'ok' }}">{{ $database }}</dd>
    <dt>PHP</dt><dd>{{ $php }}</dd>
    <dt>Disk bebas</dt><dd>{{ $disk_bebas }}</dd>
    <dt>Santri</dt><dd>{{ $santri ?? '—' }}</dd>
    <dt>Transaksi hari ini</dt><dd>{{ $transaksi_hari_ini ?? '—' }}</dd>
    <dt>Backup terakhir</dt><dd>{{ $backup_terakhir }}</dd>
  </dl>
</div>
</body>
</html>
