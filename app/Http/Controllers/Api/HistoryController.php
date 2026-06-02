<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\History; 
use App\Models\Habit; 
use App\Models\ImageDetection; 

class HistoryController extends Controller
{
    /**
     * 1. Mengambil data riwayat berdasarkan User ID (GET /api/history)
     */
    public function index(Request $request)
    {
        try {
            // FIX: Mengamankan input baik dari query parameter maupun body request
            $userId = $request->query('user_id') ?? $request->input('user_id');
            
            Log::info('Menerima permintaan riwayat untuk User ID: ' . ($userId ?? 'Kosong'));

            if (!$userId) {
                return response()->json([], 200);
            }

            // FIX: Menggunakan snake_case pada relation name jika di Model diset imageDetections / image_detections
            // Memastikan data diurutkan berdasarkan tanggal terbaru
            $histories = History::with(['imageDetections'])
                ->where('user_id', $userId)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            return response()->json($histories, 200);

        } catch (\Exception $e) {
            Log::error('History Index Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. Menyimpan data riwayat baru dari Android (POST /api/history)
     */
    public function store(Request $request)
    {
        try {
            Log::info('Data masuk ke store history: ', $request->all());

            // FIX: Validasi diperketat namun fleksibel agar tidak crash jika request kosong/parsial
            $request->validate([
                'user_id'          => 'required|integer', 
                'habit_id'         => 'nullable|integer', 
                'object_name'      => 'nullable|string', 
                'status'           => 'required|string',
                'notes'            => 'nullable|string', 
                'date'             => 'nullable|date_format:Y-m-d', 
                'detected_label'   => 'nullable|string',
                'confidence_score' => 'nullable', 
                'image_path'       => 'nullable|string',
            ]);

            // Ambil data user_id secara aman
            $userId = $request->input('user_id');
            $habitId = $request->input('habit_id');
            $statusFinal = $request->input('status', 'pending'); 
            $isAutoChecked = false;
            $objectNameFinal = $request->input('object_name') ?? 'Regular Check';

            // Ambil input deteksi gambar (Mendukung camelCase dari Retrofit Android)
            $detectedLabel = $request->input('detected_label') ?? $request->input('detectedLabel');
            $confidenceScore = $request->input('confidence_score') ?? $request->input('confidenceScore');
            $imagePath = $request->input('image_path') ?? $request->input('imagePath');

            // Logika Otomatisasi Validasi AI Misi
            if (!empty($detectedLabel) && $habitId) {
                $habit = Habit::find($habitId);
                if ($habit && !empty($habit->mission_target)) {
                    $targetMisi = strtolower($habit->mission_target);
                    $hasilScan = strtolower($detectedLabel);

                    if (str_contains($hasilScan, $targetMisi) || str_contains($targetMisi, $hasilScan)) {
                        $statusFinal = 'completed'; 
                        $isAutoChecked = true;
                        $objectNameFinal = $detectedLabel; 
                    }
                }
            }

            // Simpan data utama history
            $history = History::create([
                'user_id'     => $userId,
                'habit_id'    => $habitId,
                'object_name' => $objectNameFinal, 
                'status'      => $statusFinal,
                'notes'       => $request->input('notes') ?? '-', 
                'date'        => $request->input('date') ?? now()->format('Y-m-d'), 
                'checked_at'  => $statusFinal === 'completed' ? now() : null
            ]);

            // Jika ada payload deteksi gambar, pasangkan ke tabel anak
            if (!empty($detectedLabel)) {
                ImageDetection::create([
                    'history_id'       => $history->id,
                    'detected_label'   => $detectedLabel,
                    'confidence_score' => $confidenceScore ?? 0.0,
                    'image_path'       => $imagePath ?? 'assets/detections/captured_object.jpg',
                ]);
            }

            // Memuat ulang relasi agar response yang dikembalikan ke Android lengkap beserta image_detections
            $history->load('imageDetections');

            return response()->json([
                'status'          => 'success',
                'message'         => $isAutoChecked ? 'Misi berhasil divalidasi oleh AI!' : 'Data riwayat berhasil disimpan!',
                'is_auto_checked' => $isAutoChecked,
                'data'            => $history
            ], 200);

        } catch (\Exception $e) {
            Log::error('History Store Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memproses data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 3. Memperbarui Catatan / Notes (PUT /api/history/{id})
     */
    public function update(Request $request, $id)
    {
        try {
            $history = History::find($id);

            if (!$history) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            $history->update([
                'notes' => $request->input('notes') ?? $history->notes
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Catatan riwayat berhasil diperbarui!',
                'data'   => $history->load('imageDetections')
            ], 200);

        } catch (\Exception $e) {
            Log::error('History Update Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 4. Menghapus Data Riwayat (DELETE /api/history/{id})
     * FIX UTAMA: Menyelesaikan bug foreign key kuncian database, mengkalkulasi sisa riwayat, 
     * dan mengirim state penanda uncheck 'is_checked' ke Android Studio.
     */
    public function destroy($id)
    {
        \DB::beginTransaction();
        try {
            Log::info("Mulai proses hapus history ID: {$id}");

            $history = History::find($id);

            if (!$history) {
                \DB::rollBack();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Data sudah tidak ada atau telah dihapus sebelumnya.',
                    'habit_id' => null,
                    'is_checked' => false
                ], 200);
            }

            // Simpan variabel habit_id dan tanggal sebelum record history-nya dihangus-total
            $habitId = $history->habit_id;
            $historyDate = $history->date;

            // 1. Bersihkan paksa relasi tabel anak (image_detections) terlebih dahulu lewat query builder
            \DB::table('image_detections')->where('history_id', $id)->delete();
            Log::info("Berhasil menghapus data anak image_detections terkait history ID: {$id}");

            // 2. Hapus data utama dari tabel histories
            $history->delete();

            \DB::commit();
            Log::info("Berhasil menghapus data utama history ID: {$id} untuk Habit ID: {$habitId}");

            /**
             * LOGIKA UTAMA SINKRONISASI KEMBALI KESEKALI:
             * Cek apakah setelah riwayat ini musnah, masih ada data riwayat bernilai 'completed' 
             * dari habit yang sama di tanggal ini?
             */
            $stillHasHistory = false;
            if ($habitId) {
                $stillHasHistory = History::where('habit_id', $habitId)
                    ->where('date', $historyDate)
                    ->where('status', 'completed')
                    ->exists();
            }

            // Kembalikan response terstruktur lengkap ke Android Studio
            return response()->json([
                'status'     => 'success',
                'message'    => 'Riwayat berhasil dihapus!',
                'habit_id'   => $habitId,
                'date'       => $historyDate,
                'is_checked' => $stillHasHistory // <-- FALSE berarti menyuruh Android mematikan centang (uncheck)
            ], 200);

        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error("History Delete Error untuk ID {$id}: " . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }
}