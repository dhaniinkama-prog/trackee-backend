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
        Schema::create('alarms', function (Blueprint $table) {
            $table->id();
            // Menghubungkan alarm ke id yang ada di tabel habits
            $table->foreignId('habit_id')->constrained('habits')->onDelete('cascade');
            $table->time('alarm_time'); // Kolom untuk menyimpan jam (HH:MM)
            $table->boolean('is_active')->default(true); // Status alarm aktif/mati
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarms');
    }
};
