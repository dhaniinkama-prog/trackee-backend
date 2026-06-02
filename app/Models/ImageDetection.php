<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // Tambahkan ini jika menggunakan factory
use Illuminate\Database\Eloquent\Model;

class ImageDetection extends Model
{
    use HasFactory;

    // 1. Tegaskan nama tabel di database agar presisi
    protected $table = 'image_detections';

    // 2. Kolom yang diizinkan untuk diisi data dari Android Studio
    protected $fillable = [
        'history_id', 
        'detected_label', 
        'confidence_score', 
        'image_path'
    ];

    /**
     * 3. RELASI BALIK: Menghubungkan detail deteksi ke data utamanya di tabel histories
     */
    public function history()
    {
        return $this->belongsTo(History::class, 'history_id');
    }
}