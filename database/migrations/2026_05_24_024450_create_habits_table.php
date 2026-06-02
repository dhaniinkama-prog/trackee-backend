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
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            // Menghubungkan ke tabel users secara dinamis
            $table->unsignedBigInteger('user_id')->default(1); 
            $table->string('name');
            $table->string('target'); // Misal: "8 Gelas" atau "30 Menit"

            // --- PENGATURAN FREKUENSI KALENDER ---
            // Menyimpan jenis frekuensi: 'every_day', 'specific_days', atau 'flexible_week'
            $table->string('frequency_type')->default('every_day');
            // Menyimpan string hari pilihan jika tipe specific_days (Contoh: "Mon,Wed,Fri")
            $table->string('specific_days')->nullable();
            // Menyimpan angka target frekuensi jika tipe fleksibel (Contoh: 3 untuk 3x seminggu)
            $table->integer('flexible_count')->nullable();

            // --- PENGATURAN ALARM INDEPENDEN ---
            // SINKRONISASI ANDROID: Diubah ke snake_case agar cocok dengan @Field("hours_and_minutes")
            $table->string('hours_and_minutes')->nullable();
            // Menyimpan tanggal + jam alarm spesifik pilihan user (Contoh: "2026-05-24 07:30:00")
            $table->dateTime('alarm_datetime')->nullable();

            // --- ATRIBUT UTENSIL ---
            // SINKRONISASI ANDROID: Diubah ke snake_case agar cocok dengan @Field("ringtone_uri")
            $table->text('ringtone_uri')->nullable();
            // SINKRONISASI ANDROID: Diubah ke snake_case agar cocok dengan @Field("mission_target")
            $table->string('mission_target'); // Target objek foto MLKit (misal: "bottle", "bed")
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};