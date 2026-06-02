<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\HabitController;

// =========================================================================
// ROUTE PUBLIC (Bisa diakses langsung dari Android tanpa Token)
// =========================================================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rute Akun User (Ganti Password & Upload Foto Profil)
Route::post('/user/change-password', [AuthController::class, 'changePassword']);
Route::post('/user/upload-profile', [AuthController::class, 'uploadProfilePicture']);

// =========================================================================
// JALUR TESTING PUBLIC: HISTORY CONTROLLER
// =========================================================================
Route::get('/history', [HistoryController::class, 'index']);
Route::post('/history', [HistoryController::class, 'store']); 

// PENTING: Taruh jalur spesifik DELETE sebelum jalur dinamis {id} jika ada, 
// atau pastikan dikelompokkan dengan baik.
Route::post('/history/delete/{id}', [HistoryController::class, 'destroy']); 
Route::put('/history/{id}', [HistoryController::class, 'update']);
Route::delete('/history/{id}', [HistoryController::class, 'destroy']);

// =========================================================================
// JALUR TESTING PUBLIC: HABIT CONTROLLER (Disesuaikan penuh dengan Android)
// =========================================================================

// 1. Tulis semua rute STATIS (tanpa {id}) terlebih dahulu di bagian atas
Route::get('/habits', [HabitController::class, 'getHabits']);           // Mengambil daftar habit
Route::post('/habits', [HabitController::class, 'store']);              // Menambah habit baru

Route::post('/habits/rekomendasi', [HabitController::class, 'generateRekomendasi']); // <-- AMAN: Pindah ke atas {id}
Route::post('/habits/toggle', [HabitController::class, 'toggleChecklist']);          
Route::post('/habits/toggle-checklist', [HabitController::class, 'toggleChecklist']); 

// 2. Tulis rute alternatif POST Delete sebelum rute dinamis agar tidak dianggap sebagai ID
Route::post('/habits/delete/{id}', [HabitController::class, 'deleteHabit']); // <-- Jalur alternatif POST

// 3. Tulis rute DINAMIS (yang menggunakan {id}) di paling bawah kelompok ini
Route::put('/habits/{id}', [HabitController::class, 'update']); 
Route::delete('/habits/{id}', [HabitController::class, 'deleteHabit']);      // Jalur utama DELETE

// =========================================================================
// ROUTE PRIVATE (Wajib menyertakan Bearer Token / Harus Login)
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});