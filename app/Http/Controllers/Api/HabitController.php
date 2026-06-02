<?php

namespace App\Http\Controllers\Api; 

use App\Http\Controllers\Controller; 
use App\Models\Habit;
use App\Models\History; 
use App\Models\ImageDetection; 
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Facades\DB;

class HabitController extends Controller
{
    /**
     * 1. Ambil Data Habit Khusus User & Saring Berdasarkan Tipe Frekuensi Tanggal Terpilih
     * PERBAIKAN: Jika user belum punya habit sama sekali, sistem otomatis membuatkan 
     * habit default resmi di database agar sinkron, bisa di-update, delete, dan masuk history.
     */
    public function getHabits(Request $request)
    {
        $userId = $request->query('user_id');
        $dateStr = $request->query('date'); 
        
        if (!$userId) {
            return response()->json(['message' => 'Parameter user_id wajib disertakan'], 400);
        }

        $query = Habit::where('user_id', $userId);

        // Cek secara mutlak apakah user ini sudah punya habit di database
        $cekHabitExist = (clone $query)->exists();

        // =========================================================================
        // === LOGIKA AUTO-INSERT HABIT DEFAULT JIKA DB KOSONG ===
        // =========================================================================
        if (!$cekHabitExist) {
            Log::info("User ID {$userId} belum memiliki habit sama sekali. Membuatkan habit default resmi...");
            
            // Tangkap preferensi goal yang dikirim Android via Query Param (?user_goal=...)
            $userGoal = strtolower($request->query('user_goal') ?? 'drink'); 

            $habitDataDefault = [
                'user_id'           => $userId,
                'frequency_type'    => 'every_day',
                'ringtone_uri'      => 'content://settings/system/alarm_alert',
                'created_at'        => now(),
                'updated_at'        => now()
            ];

            // Filter kecocokan teks untuk menentukan habit default apa yang disimpan ke DB
            if (str_contains($userGoal, 'drink') || str_contains($userGoal, 'air') || str_contains($userGoal, 'minum')) {
                Habit::create(array_merge($habitDataDefault, [
                    'name' => 'Minum Air Putih (Target Harian)',
                    'target' => '8 gelas',
                    'hours_and_minutes' => '07:30',
                    'mission_target' => 'bottle'
                ]));
            } elseif (str_contains($userGoal, 'sleep') || str_contains($userGoal, 'tidur')) {
                Habit::create(array_merge($habitDataDefault, [
                    'name' => 'Tidur Tepat Waktu & Istirahat',
                    'target' => '8 jam',
                    'hours_and_minutes' => '22:00',
                    'mission_target' => 'bed'
                ]));
            } elseif (str_contains($userGoal, 'sport') || str_contains($userGoal, 'olahraga')) {
                Habit::create(array_merge($habitDataDefault, [
                    'name' => 'Push up, Sit up, atau Stretching',
                    'target' => '30 menit',
                    'hours_and_minutes' => '07:30',
                    'mission_target' => 'chair'
                ]));
            } elseif (str_contains($userGoal, 'read') || str_contains($userGoal, 'baca')) {
                Habit::create(array_merge($habitDataDefault, [
                    'name' => 'Membaca Buku/Artikel Bermanfaat',
                    'target' => '15 halaman',
                    'hours_and_minutes' => '20:00',
                    'mission_target' => 'book'
                ]));
            } else {
                // Kebijakan cadangan terakhir jika teks goal tidak spesifik
                Habit::create(array_merge($habitDataDefault, [
                    'name' => 'Minum Air Putih (Target Harian)',
                    'target' => '8 gelas',
                    'hours_and_minutes' => '07:30',
                    'mission_target' => 'bottle'
                ]));
            }
        }

        // =========================================================================
        // === PROSES FILTER FREKUENSI TANGGAL ===
        // =========================================================================
        if ($dateStr) {
            try {
                $targetDate = Carbon::parse($dateStr)->startOfDay();
                $namaHariSingkat = $targetDate->format('D'); 

                $habits = $query->get()->filter(function ($habit) use ($targetDate, $namaHariSingkat) {
                    if ($habit->created_at) {
                        $createdAtDate = Carbon::parse($habit->created_at)->startOfDay();
                        if ($createdAtDate->gt($targetDate)) {
                            return false; 
                        }
                    }

                    switch ($habit->frequency_type) {
                        case 'every_day':
                            return true;
                        case 'specific_days':
                            if (!empty($habit->specific_days)) {
                                $daftarHari = explode(',', $habit->specific_days);
                                $daftarHariClean = array_map('trim', $daftarHari); 
                                return in_array($namaHariSingkat, $daftarHariClean);
                            }
                            return false;
                        case 'flexible_week':
                        case 'flexible_count': 
                            return true; 
                        default:
                            return true;
                    }
                })->values(); 

                // Menyuntikkan status checklist berdasarkan tabel histories
                foreach ($habits as $habit) {
                    $adaRiwayat = History::where('habit_id', $habit->id)
                        ->whereDate('date', $targetDate->format('Y-m-d'))
                        ->exists();
                    
                    $habit->is_checked = $adaRiwayat;
                    $habit->isChecked = $adaRiwayat;
                }

                return response()->json($habits, 200);

            } catch (\Exception $e) {
                Log::error('Gagal memproses getHabits: ' . $e->getMessage());
                return response()->json(['message' => 'Format tanggal tidak valid'], 400);
            }
        }
        
        // Response jika Android memanggil API getHabits tanpa parameter tanggal
        $allHabits = $query->get();
        foreach ($allHabits as $habit) {
            $habit->is_checked = false;
            $habit->isChecked = false;
        }
        
        return response()->json($allHabits, 200);
    }

    /**
     * 2. Sinkronisasi Check/Uncheck Manual dari Android
     */
    public function toggleChecklist(Request $request)
    {
        try {
            $habitId = $request->input('habit_id');
            $date = $request->input('date');
            $isCheckedInput = $request->input('is_checked');

            $isChecked = filter_var($isCheckedInput, FILTER_VALIDATE_BOOLEAN);

            if (!$habitId || !$date) {
                return response()->json(['message' => 'Parameter habit_id dan date wajib diisi'], 400);
            }

            $habit = Habit::find($habitId);
            if (!$habit) {
                return response()->json(['message' => 'Habit tidak ditemukan'], 404);
            }

            if ($isChecked) {
                $history = History::updateOrCreate(
                    [
                        'habit_id' => $habitId,
                        'date'     => $date
                    ],
                    [
                        'user_id'     => $habit->user_id, 
                        'object_name' => $habit->name ?? 'Regular Check',
                        'status'      => 'completed', 
                        'notes'       => 'Dichecklist manual pada ' . $date,
                        'checked_at'  => now()
                    ]
                );

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Riwayat habit berhasil disimpan!',
                    'data'    => $history
                ], 200);

            } else {
                History::where('habit_id', $habitId)
                    ->whereDate('date', $date)
                    ->delete();

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Riwayat dicabut, data berhasil dihapus dari database!'
                ], 200);
            }
        } catch (\Exception $e) {
            Log::error('Toggle Checklist Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Server gagal memproses perubahan status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 3. Simpan Habit Baru
     */
    public function store(Request $request)
    {
        $name = $request->input('name');
        $target = $request->input('target');
        $frequencyType = $request->input('frequency_type') ?? 'every_day';
        $specificDays = $request->input('specific_days');
        $flexibleCount = $request->input('flexible_count');
        
        $alarmDatetime = $request->input('alarm_datetime') ?? $request->input('alarmDatetime');
        $hoursAndMinutes = $request->input('hours_and_minutes') ?? $request->input('hoursAndMinutes') ?? '07:30';
        $ringtoneUri = $request->input('ringtone_uri') ?? $request->input('ringtoneUri');
        $bendaMisi = $request->input('mission_target') ?? $request->input('bendaMisi') ?? 'bottle';

        $habit = Habit::create([
            'user_id'           => $request->input('user_id') ?? 1,
            'name'              => $name,
            'target'            => $target,
            'frequency_type'    => $frequencyType,
            'specific_days'     => $specificDays,
            'flexible_count'    => $flexibleCount ? (int)$flexibleCount : null,
            'hours_and_minutes' => $hoursAndMinutes, 
            'alarm_datetime'    => $alarmDatetime,
            'ringtone_uri'      => $ringtoneUri,      
            'mission_target'    => $bendaMisi,          
            'created_at'        => now() 
        ]);

        return response()->json($habit, 201);
    }

    /**
     * 4. Update Data Habit Existing
     */
    public function update(Request $request, $id)
    {
        $habit = Habit::find($id);

        if (!$habit) {
            return response()->json(['message' => 'Habit tidak ditemukan'], 404);
        }

        $name = $request->input('name') ?? $habit->name;
        $target = $request->input('target') ?? $habit->target;
        $frequencyType = $request->input('frequency_type') ?? $habit->frequency_type;
        $specificDays = $request->input('specific_days') ?? $habit->specific_days;
        $flexibleCount = $request->input('flexible_count') ?? $habit->flexible_count;
        
        $hoursAndMinutes = $request->input('hours_and_minutes') ?? $request->input('hoursAndMinutes') ?? $habit->hours_and_minutes;
        $alarmDatetime = $request->input('alarm_datetime') ?? $request->input('alarmDatetime') ?? $habit->alarm_datetime;
        $ringtoneUri = $request->input('ringtone_uri') ?? $request->input('ringtoneUri') ?? $habit->ringtone_uri;
        $bendaMisi = $request->input('mission_target') ?? $request->input('bendaMisi') ?? $habit->mission_target;

        $habit->update([
            'name'              => $name,
            'target'            => $target,
            'frequency_type'    => $frequencyType,
            'specific_days'     => $specificDays,
            'flexible_count'    => $flexibleCount,
            'hours_and_minutes' => $hoursAndMinutes,
            'alarm_datetime'    => $alarmDatetime,
            'ringtone_uri'      => $ringtoneUri,
            'mission_target'    => $bendaMisi,
        ]);

        return response()->json($habit, 200);
    }

    /**
     * 5. Generate Habit Otomatis Berdasarkan Pilihan Menu Kuesioner
     */
    public function generateRekomendasi(Request $request)
    {
        $userId = $request->input('user_id') ?? 1;
        $category = strtolower($request->input('category') ?? '');

        $habitData = [
            'user_id'        => $userId,
            'frequency_type' => 'every_day',
            'ringtone_uri'   => 'content://settings/system/alarm_alert',
            'created_at'     => now()
        ];

        if (str_contains($category, 'drink') || str_contains($category, 'air') || str_contains($category, 'minum')) {
            $habitData['name'] = 'Minum Air Putih (Target Harian)';
            $habitData['target'] = '8 gelas';
            $habitData['hours_and_minutes'] = '12:30';
            $habitData['mission_target'] = 'bottle';
        } elseif (str_contains($category, 'sleep') || str_contains($category, 'tidur')) {
            $habitData['name'] = 'Tidur Tepat Waktu & Istirahat';
            $habitData['target'] = '8 jam';
            $habitData['hours_and_minutes'] = '22:00';
            $habitData['mission_target'] = 'bed';
        } elseif (str_contains($category, 'sport') || str_contains($category, 'olahraga')) {
            $habitData['name'] = 'Push up, Sit up, atau Stretching';
            $habitData['target'] = '30 menit';
            $habitData['hours_and_minutes'] = '07:30';
            $habitData['mission_target'] = 'chair';
        } elseif (str_contains($category, 'read') || str_contains($category, 'baca')) {
            $habitData['name'] = 'Membaca Buku/Artikel Bermanfaat';
            $habitData['target'] = '15 halaman';
            $habitData['hours_and_minutes'] = '20:00';
            $habitData['mission_target'] = 'book';
        }

        if (isset($habitData['name'])) {
            $habit = Habit::create($habitData);
            return response()->json($habit, 201); 
        }

        return response()->json([
            'status' => 'error', 
            'message' => 'Kategori kuesioner tidak valid: ' . $category
        ], 400);
    }

    /**
     * 6. Hapus Habit Beserta Seluruh Sub-Riwayatnya Sekaligus (Force Truncate Mode)
     */
    public function deleteHabit($id)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::beginTransaction();
        try {
            Log::info("Mulai paksa hapus data Habit ID: {$id}");
            $habit = Habit::find($id);

            if (!$habit) {
                DB::rollBack();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data Habit tidak ditemukan atau sudah dihapus sebelumnya.'
                ], 404);
            }

            $historyIds = DB::table('histories')->where('habit_id', $id)->pluck('id')->toArray();

            if (!empty($historyIds)) {
                DB::table('image_detections')->whereIn('history_id', $historyIds)->delete();
                Log::info("Sukses hapus image_detections dari Habit ID: {$id}");
            }

            DB::table('histories')->where('habit_id', $id)->delete();
            Log::info("Sukses hapus histories dari Habit ID: {$id}");

            DB::table('habits')->where('id', $id)->delete();
            Log::info("Sukses hapus data utama di tabel habits.");

            DB::commit();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            Log::info("Habit ID {$id} sukses dibersihkan tanpa sisa.");

            return response()->json([
                'status' => 'success',
                'message' => 'Habit beserta seluruh data riwayatnya berhasil dihapus!'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            Log::error('Gagal menghapus habit: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus habit dari server: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 7. Simpan Hasil Deteksi Objek Gambar AI + Otomatisasi Checklist Terintegrasi
     */
    public function storeImageDetection(Request $request)
    {
        $request->validate([
            'detected_label'   => 'required|string',
            'confidence_score' => 'required',
            'image'            => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $historyId = $request->input('history_id');
        $habitId = $request->input('habit_id');
        $date = $request->input('date') ?? now()->format('Y-m-d');

        $history = null;

        if ($historyId) {
            $history = History::find($historyId);
        } elseif ($habitId) {
            $history = History::where('habit_id', $habitId)->whereDate('date', $date)->first();
        }

        if (!$history && $habitId) {
            $habit = Habit::find($habitId);
            if ($habit) {
                $history = History::create([
                    'habit_id'    => $habit->id,
                    'user_id'     => $habit->user_id,
                    'date'        => $date,
                    'object_name' => $habit->name ?? 'Scan AI Check',
                    'status'      => 'pending',
                    'notes'       => 'Dibuat otomatis via deteksi gambar AI',
                ]);
            }
        }

        if (!$history) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data riwayat habit tidak ditemukan. Kirimkan parameter habit_id atau history_id.'
            ], 404);
        }

        $finalImagePath = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('detections', 'public');
            $finalImagePath = Storage::url($path);
        }

        $detectedLabel = $request->input('detected_label');

        ImageDetection::where('history_id', $history->id)->delete();

        $detection = ImageDetection::create([
            'history_id'       => $history->id,
            'detected_label'   => $detectedLabel,
            'confidence_score' => $request->input('confidence_score'),
            'image_path'       => $finalImagePath ?? $request->input('image_path')
        ]);

        $isAutoChecked = false;
        $habit = Habit::find($history->habit_id);
        
        if ($habit && !empty($habit->mission_target)) {
            $targetMisi = strtolower($habit->mission_target);
            $hasilScan = strtolower($detectedLabel);

            if (str_contains($hasilScan, $targetMisi) || str_contains($targetMisi, $hasilScan)) {
                $history->update([
                    'status'     => 'completed',
                    'checked_at' => now()
                ]);
                $isAutoChecked = true;
            }
        }

        return response()->json([
            'status'          => 'success',
            'message'         => $isAutoChecked ? 'Misi berhasil terverifikasi oleh AI! Status diubah menjadi Selesai.' : 'Data deteksi gambar disimpan.',
            'is_auto_checked' => $isAutoChecked,
            'history_id'      => $history->id,
            'data'            => $detection
        ], 201);
    }
}