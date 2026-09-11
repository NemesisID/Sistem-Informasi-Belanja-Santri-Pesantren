<?php

// Aturan bisnis sistem koin. Diedit di sini, jangan di-hardcode di controller.
return [
    // Batas maksimal nominal satu transaksi penarikan (rupiah)
    'maks_penarikan' => (int) env('KOIN_MAKS_PENARIKAN', 30000),

    // Nominal penarikan minimum (rupiah)
    'min_penarikan' => (int) env('KOIN_MIN_PENARIKAN', 1000),

    // Maksimal item per halaman pada semua list
    'max_per_page' => (int) env('KOIN_MAX_PER_PAGE', 2000),
];