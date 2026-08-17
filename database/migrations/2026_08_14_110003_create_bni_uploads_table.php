<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bni_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('nama_file');
            $table->string('path');
            $table->enum('status', ['parsing', 'menunggu', 'terapkan', 'dibatalkan'])->default('parsing');
            $table->unsignedInteger('jumlah_total')->default(0);
            $table->unsignedInteger('jumlah_valid')->default(0);
            $table->unsignedInteger('jumlah_invalid')->default(0);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bni_uploads');
    }
};
