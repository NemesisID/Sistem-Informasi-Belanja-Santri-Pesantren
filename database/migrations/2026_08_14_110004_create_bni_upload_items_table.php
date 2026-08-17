<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bni_upload_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('bni_uploads')->cascadeOnDelete();
            $table->string('va');
            $table->string('nama')->nullable();
            $table->bigInteger('nominal');
            $table->date('tanggal')->nullable();
            $table->foreignId('santri_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('status_valid')->default(false);
            $table->string('catatan')->nullable();
            $table->boolean('diterapkan')->default(false);
            $table->timestamp('applied_at')->nullable();

            $table->index(['upload_id', 'diterapkan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bni_upload_items');
    }
};
