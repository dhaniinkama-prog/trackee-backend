<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Habit extends Model
{
    use HasFactory;

    protected $table = 'habits';

    protected $fillable = [
        'user_id',
        'name',
        'target',
        'frequency_type',   // 'every_day', 'specific_days', 'flexible_week'
        'specific_days',    // string nama hari gabungan ("Mon,Wed,Fri")
        'flexible_count',   // target angka fleksibel seminggu (misal: 3)
        
        // SINKRONISASI TOTAL: Menggunakan format snake_case sesuai skema DB & Controller baru
        'hours_and_minutes', 
        'alarm_datetime',   // Waktu alarm independen (Tanggal + Jam spesifik)
        'ringtone_uri',
        'mission_target',   // Target objek foto MLKit (misal: "bottle", "bed")
    ];

    /**
     * Casting properti agar otomatis dikonversi ke tipe data yang sesuai saat diakses
     */
    protected $casts = [
        'alarm_datetime' => 'datetime',
        'flexible_count' => 'integer',
    ];

    /**
     * FIX UTAMA: Efek Domino Hapus Otomatis (Cascade Delete via Model)
     * Ketika Habit dihapus, hapus juga semua history dan image_detections terkait 
     * agar database tidak mengunci/foreign key error saat didelete dari Android.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($habit) {
            // Ambil semua ID history yang terikat dengan habit ini
            $historyIds = $habit->histories()->pluck('id');

            // 1. Hapus cucunya dulu (image_detections)
            \DB::table('image_detections')->whereIn('history_id', $historyIds)->delete();

            // 2. Hapus anaknya (histories)
            $habit->histories()->delete();
        });
    }

    /**
     * RELASI BARU: Menghubungkan satu Habit ke banyak data riwayatnya di tabel histories
     */
    public function histories()
    {
        return $this->hasMany(History::class, 'habit_id');
    }
}