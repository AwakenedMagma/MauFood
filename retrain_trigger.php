<?php
// Fungsi bersama untuk menembak sinyal retrain ke layanan Flask di Railway.
// Dipanggil setiap kali ada perubahan yang memengaruhi model rekomendasi:
// tambah/edit/hapus menu (admin/menu.php), atau transaksi baru selesai
// disimpan (pelayan/pesan.php, pelayan/pesan_quick.php).
//
// Sengaja "fire-and-forget": kegagalan memanggil retrain TIDAK boleh
// menggagalkan alur utama (simpan menu / simpan pesanan), karena retrain
// hanya menyegarkan model AI, bukan bagian inti transaksi.
if (!function_exists('triggerRetrainRailway')) {
    function triggerRetrainRailway() {
        $url = 'https://maufood-api-python-production.up.railway.app/api/trigger-retrain';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        // Kirim password rahasia yang sama dengan di Python (WEBHOOK_SECRET di app.py)
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['secret' => 'rahasia_maufood_123']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        // Cukup tunggu beberapa detik untuk sinyal terkirim; Python memprosesnya
        // secara asinkron di background thread, jadi tidak perlu menunggu selesai.
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        @curl_exec($ch);
        curl_close($ch);
    }
}
