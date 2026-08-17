<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger saldo, append-only: tidak boleh UPDATE/DELETE.
     * nominal bertanda (+masuk / −keluar).
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('santri_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bni_upload_item_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('tipe', ['topup', 'tarik_koin', 'penyesuaian']);
            $table->bigInteger('nominal');
            $table->bigInteger('saldo_sebelum');
            $table->bigInteger('saldo_setelah');
            $table->string('keterangan')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['santri_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
