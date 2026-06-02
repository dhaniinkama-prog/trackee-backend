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
        Schema::create('histories', function (Blueprint $table) {
            $table->id();
            
            // =========================================================================
            // FIX UTAMA: Menambahkan user_id agar query filter ->where('user_id', ...) 
            // di HistoryController tidak memicu Error 500 (Column Not Found)
            // =========================================================================
            $table->unsignedBigInteger('user_id'); 
            
            // Diubah ke unsignedBigInteger agar tipe datanya klop dengan id utama tabel habits
            $table->unsignedBigInteger('habit_id')->nullable(); 
            
            // Dibuat nullable() agar aman jika Android lupa/tidak mengirimkan deskripsi objek
            $table->string('object_name')->nullable(); 
            $table->string('status');      
            $table->string('notes')->nullable();       
            
            $table->date('date')->nullable(); // Kolom tanggal untuk sinkronisasi check/uncheck harian
            $table->timestamp('checked_at')->nullable(); // Menyimpan waktu spesifik saat dicentang
            
            $table->timestamps();          
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('histories');
    }
};