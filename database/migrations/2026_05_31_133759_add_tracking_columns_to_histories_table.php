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
        Schema::table('histories', function (Blueprint $table) {
            // 1. Tambah kolom habit_id sebagai foreign key yang terhubung ke tabel habits
            if (!Schema::hasColumn('histories', 'habit_id')) {
                $table->foreignId('habit_id')
                      ->nullable()
                      ->after('id')
                      ->constrained('habits')
                      ->onDelete('cascade');
            }

            // 2. Tambah kolom checked_at untuk mencatat waktu pas misi berhasil dicentang AI
            if (!Schema::hasColumn('histories', 'checked_at')) {
                $table->timestamp('checked_at')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('histories', function (Blueprint $table) {
            // Hapus foreign key terlebih dahulu sebelum menghapus kolomnya
            if (Schema::hasColumn('histories', 'habit_id')) {
                $table->dropForeign(['habit_id']);
                $table->dropColumn('habit_id');
            }

            if (Schema::hasColumn('histories', 'checked_at')) {
                $table->dropColumn('checked_at');
            }
        });
    }
};