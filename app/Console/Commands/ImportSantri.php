<?php

namespace App\Console\Commands;

use App\Services\SantriImportService;
use Illuminate\Console\Command;

class ImportSantri extends Command
{
    protected $signature = 'santri:import {file : Path file Excel DATA SISWA NATA.xlsx} {--foto= : Folder berisi foto santri bernama {nis}.jpg}';

    protected $description = 'Impor data santri dari Excel (6 sheet = 6 unit) + foto dari folder lokal';

    public function handle(SantriImportService $importer): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File tidak ditemukan: {$file}");
            return self::FAILURE;
        }

        $report = $importer->import($file, $this->option('foto'));

        $this->info("Selesai — diproses {$report['diproses']}, ditambah {$report['ditambah']}, "
            ."diupdate {$report['diupdate']}, foto terpasang {$report['foto_terpasang']}, error {$report['error']}");

        return self::SUCCESS;
    }
}
