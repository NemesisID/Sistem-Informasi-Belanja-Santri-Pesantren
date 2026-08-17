<?php

// Aturan bisnis sistem koin. Diedit di sini, jangan di-hardcode di controller.
return [
    // Batas maksimal penarikan koin per rolling window 2 hari (rupiah)
    'batas_tarik_2hari' => (int) env('KOIN_BATAS_TARIK_2HARI', 30000),

    // Nominal penarikan minimum (rupiah)
    'min_penarikan' => (int) env('KOIN_MIN_PENARIKAN', 1000),

    // Maksimal item per halaman pada semua list
    'max_per_page' => 100,
];
