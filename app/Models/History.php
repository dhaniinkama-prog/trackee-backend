<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    use HasFactory;

    // 1. Tegaskan nama tabel database yang digunakan
    protected $table = 'histories';

    // 2. Buka izin akses kolom agar data dari Android bisa masuk ke MySQL
    protected $fillable = [
        'user_id',      
        'habit_id',     
        'object_name',
        'status',       // 'completed' atau 'pending'
        'notes',
        'date',         
        'checked_at'    
    ];

    /**
     * Casting properti agar otomatis dikonversi ke tipe data yang sesuai saat diakses
     */
    protected $casts = [
        'checked_at' => 'datetime',
    ];

    /**
     * FIX UTAMA: Efek Domino Hapus Otomatis Level Riwayat
     * Jika data riwayat (History) dihapus secara mandiri dari Android/Controller, 
     * bersihkan juga log gambar AI terkait agar database tetap bersih.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($history) {
            // Hapus data image_detections yang terikat dengan ID history yang sedang dihapus
            $history->image_detections()->delete();
        });
    }

    /**
     * 3. RELASI UTAMA (CamelCase): Digunakan internal oleh Laravel Eloquent
     */
    public function imageDetections()
    {
        return $this->hasMany(ImageDetection::class, 'history_id');
    }

    /**
     * FIX UTAMA: RELASI ALIAS (SnakeCase)
     * Menjembatani request data dari Android agar fungsi delete & eager loading 
     * tidak crash akibat perbedaan format penulisan key dari Retrofit.
     */
    public function image_detections()
    {
        return $this->hasMany(ImageDetection::class, 'history_id');
    }

    /**
     * 4. RELASI BALIK: Menghubungkan riwayat kembali ke data induk Habit-nya
     */
    public function habit()
    {
        return $this->belongsTo(Habit::class, 'habit_id');
    }
}