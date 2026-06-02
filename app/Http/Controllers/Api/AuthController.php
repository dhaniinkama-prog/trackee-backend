<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage; // WAJIB DITAMBAHKAN UNTUK URUSAN UPLOAD FOTO

class AuthController extends Controller
{
    // FITUR REGISTER (Tetap bersih tanpa membuat habit otomatis)
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        return response()->json(['message' => 'Register Berhasil', 'data' => $user], 201);
    }

    // FITUR LOGIN
    public function login(Request $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Email atau Password salah'], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        // PERBAIKAN: Ditambahkan bungkus array 'user' agar sesuai dengan kebutuhan Android
        return response()->json([
            'message' => 'Login Berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ], 200);
    }

    // FITUR LOGOUT
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout Berhasil'], 200);
    }

    // =========================================================================
    // TAMBAHAN BARU: FITUR GANTI PASSWORD (SINKRON DENGAN ANDROID)
    // =========================================================================
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'old_password' => 'required',
            'new_password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        // Mencocokkan password lama dengan hash di database
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Password lama kamu salah!'], 400);
        }

        // Update ke password baru yang sudah di-hash
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json(['message' => 'Password berhasil diperbarui!'], 200);
    }

    // =========================================================================
    // TAMBAHAN BARU: FITUR UPLOAD FOTO PROFIL (SINKRON DENGAN GLIDE ANDROID)
    // =========================================================================
    public function uploadProfilePicture(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Maksimal 2MB
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        // Hapus foto profil lama dari storage jika sebelumnya sudah pernah upload
        if ($user->profile_picture) {
            Storage::delete('public/' . $user->profile_picture);
        }

        // Menyimpan file gambar baru ke: storage/app/public/profiles
        $path = $request->file('image')->store('profiles', 'public');

        // Mengupdate nama path file gambar di kolom tabel users
        $user->update([
            'profile_picture' => $path
        ]);

        // Mengembalikan respons URL lengkap ke Android agar langsung bisa dimuat gambar profilnya
        return response()->json([
            'message' => 'Foto profil berhasil diunggah!',
            'profile_picture_url' => asset('storage/' . $path)
        ], 200);
    }
}