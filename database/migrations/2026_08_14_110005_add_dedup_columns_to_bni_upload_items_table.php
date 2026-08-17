<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom untuk dedup & audit upload mutasi BNI.
     * - journal:  nomor journal BNI (unik per pembayaran) → kunci utama anti double-credit.
     * - billing_id: Billing ID asli dari file (JJN... = uang jajan).
     * - dedup_key: kunci dedup (J:journal, fallback V:va|nominal|tanggal) → dipakai cek duplikat lintas file.
     */
    public function up(): void
    {
        Schema::table('bni_upload_items', function (Blueprint $table) {
            $table->string('journal')->nullable()->after('va');
            $table->string('billing_id')->nullable()->after('journal');
            $table->string('dedup_key')->nullable()->after('billing_id');
            $table->index('dedup_key');
        });
    }

    public function down(): void
    {
        Schema::table('bni_upload_items', function (Blueprint $table) {
            $table->dropIndex(['dedup_key']);
            $table->dropColumn(['journal', 'billing_id', 'dedup_key']);
        });
    }
};
