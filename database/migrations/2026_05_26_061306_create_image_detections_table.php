<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
    {
        Schema::create('image_detections', function (Blueprint $table) {
            $table->id();
            // Menghubungkan detail deteksi ke id riwayat di tabel histories
            $table->foreignId('history_id')->constrained('histories')->onDelete('cascade');
            $table->string('detected_label'); // Nama objek yang dibaca AI (misal: "Food")
            $table->float('confidence_score')->nullable(); // Skor akurasi AI (misal: 0.85)
            $table->string('image_path')->nullable(); // Path lokasi foto jika disimpan di server
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_detections');
    }
};
