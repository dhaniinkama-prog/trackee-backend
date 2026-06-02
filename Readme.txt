========================================================================
                      README - APLIKASI TRACKEE
========================================================================

1. NAMA APLIKASI
----------------
Trackee (Habit Tracker Application)


2. DESKRIPSI APLIKASI
---------------------
Trackee adalah aplikasi pelacak kebiasaan (habit tracker) berbasis Android 
native yang dirancang secara khusus untuk membantu pengguna membangun, melacak, 
dan mempertahankan rutinitas positif secara disiplin dan konsisten. 

Aplikasi ini mengadopsi arsitektur Client-Server berbasis RESTful API. Sisi 
Client dibangun menggunakan modul Android Studio, sementara sisi Server (Backend) 
dikembangkan menggunakan Framework Laravel untuk manajemen basis data relasional. 
Untuk menjembatani komunikasi data local server (localhost:8000) ke jaringan 
internet publik, sistem memanfaatkan teknologi tunneling dari Ngrok.

Fitur utama dan spesifikasi teknis dalam sistem Trackee meliputi:
- Autentikasi & Keamanan (Sanctum/JWT): Fitur registrasi pengguna baru dan login 
  aman yang menghasilkan Bearer Token. Token ini disimpan di sisi client sebagai 
  kunci otentikasi otomatis untuk mengakses endpoint API yang terproteksi.
- Manajemen Kebiasaan Berbasis CRUD: Sistem memungkinkan pengguna melakukan 
  manajemen data habit secara dinamis, meliputi fungsi Create (Tambah Habit), 
  Read (Melihat Daftar Habit), Update (Mengubah Nama/Target), dan Delete 
  (Menghapus Habit). Sistem backend dikonfigurasi dengan relasi 'Cascade Delete' 
  agar data tetap bersih dari sampah (redundansi).
- Pelacakan Riwayat Komprehensif (History Tracking): Subsistem pencatatan log 
  harian secara real-time untuk memantau habit apa saja yang telah berhasil 
  diselesaikan oleh pengguna pada tanggal tertentu.
- Sistem Alarm & Notification Manager: Fitur pengingat berbasis waktu lokal 
  pada Android untuk memicu notifikasi latar belakang (background notification), 
  memastikan pengguna tidak melewatkan jadwal rutinitas mereka.
- Manajemen Profil Pengguna: Antarmuka khusus untuk mengelola data personal 
  pengguna yang terintegrasi langsung dengan database server.


3. ANGGOTA KELOMPOK & PEMBAGIAN TUGAS
-------------------------------------
- Hazeera Syadza Zul Islamadina (No. Absen: 15)
  * Bertanggung jawab atas Coding Android khusus pada Fitur History.
  * Mengelola, merapikan, dan menyusun Source Code Android keseluruhan.
  * Merancang dan membuat media publikasi berupa Poster Aplikasi.

- Inka Nararya Karuniawardhani (No. Absen: 16)
  * Bertanggung jawab atas Coding Android bagian Database & Fitur Utama (Core Features).
  * Menghubungkan interface Android dengan RESTful API (Laravel & Ngrok).
  * Membuat Video Demo Aplikasi sebagai media presentasi teknis.
  * Menyusun Laporan Dokumentasi Pengujian (Testing Documentation).

- Silvia Resta Audityas (No. Absen: 31)
  * Bertanggung jawab atas Coding Android khusus pada Fitur Alarm/Pengingat.
  * Menyusun Analisis Kebutuhan Data (Data Requirements Analysis) untuk sistem.

- Vanesha Maulidya Pristiany (No. Absen: 32)
  * Bertanggung jawab atas Coding Android khusus pada Fitur Profile Pengguna.
  * Merancang UI Design dan Prototype aplikasi (Figma/Adobe XD).
  * Menyusun berkas dokumentasi sistem (Readme.txt).


4. LINK REPOSITORY (SOURCE CODE)
--------------------------------
- Repository Backend (Laravel): https://github.com/dhaniinkama-prog/trackee-backend.git
- Repository Android (Frontend): https://github.com/dhaniinkama-prog/trackee.git


5.  SCRENSHOOT HASIL BUILD
Splash Activity
- https://drive.google.com/file/d/13UOepHEwCyHDXE-6rXflIpEOGV4BZi3o/view?usp=drive_link
Today Activity
- https://drive.google.com/file/d/1NuT3gDQDIMS61y7oJ8kSgnwFDdh8PeB4/view?usp=drive_link
Alarm Activity
- https://drive.google.com/file/d/1GloHF-vx279uIQSAhi-rOtiHHLGTYrcy/view?usp=drive_link
Profile Activity
- https://drive.google.com/file/d/1ADIFOfgMyCVrr7O8SX_wLdukN8bJ2EDI/view?usp=drive_link
History Activity
- https://drive.google.com/file/d/1xJDzIDY5IQr-u-kghkiC_TMcsK4-vSg3/view?usp=drive_link
- https://drive.google.com/file/d/1pSmY5z2AtEWyaB9fE8lBM5XG3uUZUeKA/view?usp=drive_link
-------------------------


========================================================================
            Dibuat untuk Memenuhi Penilaian Sumatif Akhir Semester 
========================================================================