<table>
    <!-- KOP SURAT RESMI -->
    <tr>
        <th colspan="7" style="font-size: 16px; font-weight: bold; text-align: center; color: #1E5E3A;">
            YAYASAN PONDOK PESANTREN NAZHATUT THULLAB
        </th>
    </tr>
    <tr>
        <th colspan="7" style="font-size: 13px; font-weight: bold; text-align: center; color: #1E5E3A;">
            BAGIAN ADMINISTRASI KEUANGAN (BAK) &amp; RUMAH KOIN
        </th>
    </tr>
    <tr>
        <td colspan="7" style="font-size: 10px; text-align: center; color: #475569;">
            Jl. Raya Camplong No. 45, Prajjan, Camplong, Sampang, Madura - Jawa Timur 69281
        </td>
    </tr>
    <tr>
        <td colspan="7" style="font-size: 9px; text-align: center; color: #64748B;">
            Telepon/Helpdesk: (0323) 456789 | Email: keuangan@nazhatutthullab.sch.id
        </td>
    </tr>
    <tr>
        <td colspan="7" style="border-bottom: 2px solid #1E5E3A;"></td>
    </tr>
    <tr>
        <td colspan="7"></td>
    </tr>

    <!-- JUDUL DOKUMEN & METADATA -->
    <tr>
        <th colspan="7" style="font-size: 14px; font-weight: bold; text-align: center; color: #0F172A; text-decoration: underline;">
            LAPORAN PERTANGGUNGJAWABAN ARUS KAS SANTRI
        </th>
    </tr>
    <tr>
        <td colspan="7" style="font-size: 10px; text-align: center; color: #334155; font-weight: bold;">
            Periode Rekapitulasi: {{ $activeMonthLabel }} | No. Dokumen: {{ $docNumber }}
        </td>
    </tr>
    <tr>
        <td colspan="7"></td>
    </tr>
    <tr>
        <td colspan="4" style="font-size: 9px; color: #334155;">
            <strong>Tanggal Dicetak:</strong> {{ $printedAt }}
        </td>
        <td colspan="3" style="font-size: 9px; color: #1E5E3A; text-align: right; font-weight: bold;">
            Status Dokumen: RESMI &amp; TERVERIFIKASI
        </td>
    </tr>
    <tr>
        <td colspan="4" style="font-size: 9px; color: #334155;">
            <strong>Dicetak Oleh:</strong> {{ $printedBy }} (BAK Pesantren)
        </td>
        <td colspan="3" style="font-size: 9px; color: #475569; text-align: right;">
            Sistem Operasional: Sistem Belanja Santri &amp; Rumah Koin
        </td>
    </tr>
    <tr>
        <td colspan="7"></td>
    </tr>

    <!-- I. RINGKASAN EKSEKUTIF KEUANGAN -->
    <tr>
        <th colspan="7" style="font-size: 11px; font-weight: bold; color: #0F172A; background-color: #E2E8F0; border: 1px solid #CBD5E1;">
            I. RINGKASAN EKSEKUTIF KEUANGAN ({{ strtoupper($activeMonthLabel) }})
        </th>
    </tr>
    <tr>
        <td colspan="4" style="border: 1px solid #CBD5E1; font-weight: bold;">Total Saldo Simpanan Santri Aktif</td>
        <td colspan="3" style="border: 1px solid #CBD5E1; text-align: right; font-weight: bold; color: #1E5E3A;">Rp {{ number_format($totalSaldoAktif, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td colspan="4" style="border: 1px solid #CBD5E1;">Total Pemasukan / Top Up VA BNI ({{ $activeMonthLabel }})</td>
        <td colspan="3" style="border: 1px solid #CBD5E1; text-align: right; font-weight: bold; color: #166534;">+Rp {{ number_format($activeMonthPemasukan, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td colspan="4" style="border: 1px solid #CBD5E1;">Total Pengeluaran / Tarik Koin Santri ({{ $activeMonthLabel }})</td>
        <td colspan="3" style="border: 1px solid #CBD5E1; text-align: right; font-weight: bold; color: #991B1B;">-Rp {{ number_format($activeMonthPengeluaran, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td colspan="4" style="border: 1px solid #CBD5E1; font-weight: bold;">Selisih Arus Kas Bersih (Net Cash Flow)</td>
        <td colspan="3" style="border: 1px solid #CBD5E1; text-align: right; font-weight: bold; color: #0F172A;">Rp {{ number_format($activeMonthNet, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td colspan="4" style="border: 1px solid #CBD5E1;">Frekuensi Mutasi Transaksi</td>
        <td colspan="3" style="border: 1px solid #CBD5E1; text-align: right; font-weight: bold;">{{ $activeMonthTrx }} Transaksi</td>
    </tr>
    <tr>
        <td colspan="7"></td>
    </tr>

    <!-- II. RINCIAN REKAPITULASI KEUANGAN PER PERIODE -->
    <tr>
        <th colspan="7" style="font-size: 11px; font-weight: bold; color: #0F172A; background-color: #E2E8F0; border: 1px solid #CBD5E1;">
            II. RINCIAN REKAPITULASI KEUANGAN PER PERIODE
        </th>
    </tr>
    <tr style="background-color: #1E5E3A; color: #FFFFFF;">
        <th style="border: 1px solid #CBD5E1; text-align: center; font-weight: bold;">No</th>
        <th style="border: 1px solid #CBD5E1; text-align: left; font-weight: bold;">Periode</th>
        <th style="border: 1px solid #CBD5E1; text-align: right; font-weight: bold;">Total Masuk</th>
        <th style="border: 1px solid #CBD5E1; text-align: right; font-weight: bold;">Total Keluar</th>
        <th style="border: 1px solid #CBD5E1; text-align: right; font-weight: bold;">Selisih (Net)</th>
        <th style="border: 1px solid #CBD5E1; text-align: center; font-weight: bold;">Jumlah Trx</th>
        <th style="border: 1px solid #CBD5E1; text-align: left; font-weight: bold;">Petugas Staff</th>
    </tr>
    @foreach ($rows as $index => $row)
    <tr style="background-color: {{ $index % 2 === 0 ? '#FFFFFF' : '#F8FAFC' }};">
        <td style="border: 1px solid #CBD5E1; text-align: center;">{{ $index + 1 }}</td>
        <td style="border: 1px solid #CBD5E1; font-weight: bold; color: #0F172A;">{{ $row['label'] }}</td>
        <td style="border: 1px solid #CBD5E1; text-align: right; color: #166534; font-weight: bold;">+Rp {{ number_format($row['pemasukan'], 0, ',', '.') }}</td>
        <td style="border: 1px solid #CBD5E1; text-align: right; color: #991B1B; font-weight: bold;">-Rp {{ number_format($row['pengeluaran'], 0, ',', '.') }}</td>
        <td style="border: 1px solid #CBD5E1; text-align: right; font-weight: bold; color: #0F172A;">Rp {{ number_format($row['net'], 0, ',', '.') }}</td>
        <td style="border: 1px solid #CBD5E1; text-align: center;">{{ $row['jumlah_transaksi'] }} trx</td>
        <td style="border: 1px solid #CBD5E1;">{{ $row['staff'] }}</td>
    </tr>
    @endforeach
    <tr style="background-color: #F1F5F9; font-weight: bold;">
        <td colspan="2" style="border: 1px solid #CBD5E1; text-align: center; font-weight: bold;">TOTAL AKUMULASI</td>
        <td style="border: 1px solid #CBD5E1; text-align: right; color: #166534; font-weight: bold;">+Rp {{ number_format($totalMasukAll, 0, ',', '.') }}</td>
        <td style="border: 1px solid #CBD5E1; text-align: right; color: #991B1B; font-weight: bold;">-Rp {{ number_format($totalKeluarAll, 0, ',', '.') }}</td>
        <td style="border: 1px solid #CBD5E1; text-align: right; font-weight: bold; color: #0F172A;">Rp {{ number_format($totalNetAll, 0, ',', '.') }}</td>
        <td style="border: 1px solid #CBD5E1; text-align: center;">{{ $totalTrxAll }} trx</td>
        <td style="border: 1px solid #CBD5E1; text-align: center; color: #64748B; font-style: italic;">Terverifikasi Database</td>
    </tr>
    <tr>
        <td colspan="7"></td>
    </tr>
    <tr>
        <td colspan="7"></td>
    </tr>

    <!-- LEMBAR PENGESAHAN & TANDA TANGAN -->
    <tr>
        <td colspan="3" style="text-align: center; font-size: 10px; color: #64748B;">Mengetahui,</td>
        <td></td>
        <td colspan="3" style="text-align: center; font-size: 10px; color: #64748B;">Sampang, {{ $todayDateIndo }}</td>
    </tr>
    <tr>
        <td colspan="3" style="text-align: center; font-weight: bold; font-size: 11px;">Petugas Kasir Rumah Koin</td>
        <td></td>
        <td colspan="3" style="text-align: center; font-weight: bold; font-size: 11px;">Kepala Bagian Keuangan (BAK)</td>
    </tr>
    <tr>
        <td colspan="3" style="height: 35px;"></td>
        <td></td>
        <td colspan="3" style="height: 35px;"></td>
    </tr>
    <tr>
        <td colspan="3" style="text-align: center; font-weight: bold; font-size: 11px; text-decoration: underline; color: #0F172A;">
            Ust. Miftahul Huda
        </td>
        <td></td>
        <td colspan="3" style="text-align: center; font-weight: bold; font-size: 11px; text-decoration: underline; color: #0F172A;">
            Ustadzah Ina Wahdiah
        </td>
    </tr>
    <tr>
        <td colspan="3" style="text-align: center; font-size: 9px; color: #64748B;">
            NIP. 202208002
        </td>
        <td></td>
        <td colspan="3" style="text-align: center; font-size: 9px; color: #64748B;">
            NIP. 202105001
        </td>
    </tr>
</table>
