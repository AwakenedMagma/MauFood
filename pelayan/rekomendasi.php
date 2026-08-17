<?php
/**
 * rekomendasi.php
 * -----------------------------------------------------------------
 * Helper algoritma rekomendasi menu untuk pelayan restoran.
 * VERSI BARU: Terintegrasi dengan Backend Python Flask (Machine Learning).
 *
 * Skrip ini bertugas sebagai API Client yang akan mengirim parameter
 * ke endpoint Flask (app.py) dan memproses hasil JSON-nya, lalu 
 * menggabungkannya dengan data riil dari database (seperti harga/gambar).
 *
 * REVISI: ditambahkan mekanisme fallback ke rekomendasi berbasis
 * popularitas transaksi asli MauFood, dipicu pada tiga kondisi:
 * (1) permintaan ke API Python gagal total (timeout/network/HTTP != 200),
 * (2) API merespons tapi dengan status error atau data kosong, atau
 * (3) API merespons sukses tapi tidak satu pun ID_Menu-nya cocok dengan
 *     tabel `menu` saat ini (mis. artefak model belum di-retrain ulang
 *     setelah ada menu baru/dihapus). Dengan ini, widget rekomendasi
 * pada pesan.php dan pesan_quick.php tidak akan pernah kosong tanpa
 * keterangan meskipun layanan Flask di Railway sedang tidak tersedia.
 * -----------------------------------------------------------------
 */

/**
 * Ambil menu terpopuler dari data transaksi ASLI MauFood, dipakai
 * sebagai jaring pengaman ketika rekomendasi berbasis model ML tidak
 * tersedia atau tidak menghasilkan kecocokan.
 *
 * @param int[] $excludeMenuIds  ID menu yang mau dikecualikan (opsional)
 */
function ambilMenuPopulerFallback(mysqli $conn, int $limit = 6, array $excludeMenuIds = []): array
{
    $excludeClause = '';
    if (!empty($excludeMenuIds)) {
        $ids = implode(',', array_map('intval', $excludeMenuIds));
        $excludeClause = "WHERE m.id NOT IN ($ids)";
    }

    $limit = max(1, (int) $limit);
    $query = "
        SELECT m.*, COALESCE(SUM(dp.jumlah), 0) AS total_terjual
        FROM menu m
        LEFT JOIN detail_pesanan dp ON dp.menu_id = m.id
        $excludeClause
        GROUP BY m.id
        ORDER BY total_terjual DESC, m.id ASC
        LIMIT $limit
    ";

    $result = mysqli_query($conn, $query);
    if (!$result) {
        return [];
    }

    $hasil = [];
    while ($row = mysqli_fetch_assoc($result)) {
        unset($row['total_terjual']);
        $row['skor_rekomendasi'] = null; // tidak relevan utk rekomendasi populer
        $row['metode_rekomendasi'] = 'Populer (fallback)';
        $hasil[] = $row;
    }

    return $hasil;
}

/**
 * Meminta rekomendasi menu dari API Python dan menggabungkannya dengan DB.
 * Otomatis jatuh ke ambilMenuPopulerFallback() apabila API tidak tersedia
 * atau tidak menghasilkan kecocokan sama sekali terhadap tabel menu saat ini.
 *
 * @param mysqli $conn             Koneksi ke database MySQL
 * @param string $userId           ID pelanggan (misal: 'U001' untuk pelanggan lama, 'U_BARU' jika baru)
 * @param bool $isPelangganBaru    True jika pelanggan belum punya riwayat, False jika pelanggan lama
 * @param string|null $kategori    Filter kategori dari dialog (contoh: 'Makanan Utama')
 * @param string|null $bahan       Filter bahan dari dialog (contoh: 'Ayam')
 * @param string|null $rasa        Filter rasa dari dialog (contoh: 'Pedas')
 * @param int $limit               Jumlah menu yang diminta (dipakai juga saat fallback populer)
 * @return array                   Daftar baris menu lengkap dengan skor dan metode dari API,
 *                                  atau dari fallback populer bila ML tidak tersedia/tidak nyambung
 */
function rekomendasikanMenu(
    mysqli $conn,
    string $userId,
    bool $isPelangganBaru,
    ?string $kategori = '',
    ?string $bahan = '',
    ?string $rasa = '',
    int $limit = 6
): array {
    // 1. Endpoint Backend Python (Sesuaikan host/port jika Python di-hosting terpisah)
    $apiUrl = 'https://maufood-api-python-production.up.railway.app/api/recommend';

    // 2. Siapkan Payload (Sesuai dengan yang dibutuhkan app.py)
    $payload = json_encode([
        'user_id' => $userId,
        'is_pelanggan_baru' => $isPelangganBaru,
        'kategori' => $kategori ?? '',
        'bahan' => $bahan ?? '',
        'rasa' => $rasa ?? ''
    ]);

    // 3. Setup & Eksekusi cURL untuk menembak API Python
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout 5 detik agar web tidak hang jika Python mati

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 4. Validasi Respon API — KONDISI FALLBACK #1: API tidak terjangkau/error HTTP
    if ($response === false || $httpCode !== 200) {
        error_log("[rekomendasi.php] FALLBACK#1 - Gagal hubungi API. HTTP Code: $httpCode | cURL Error: $curlError | Body: " . substr((string) $response, 0, 300));
        return ambilMenuPopulerFallback($conn, $limit);
    }

    $responseData = json_decode($response, true);
    // KONDISI FALLBACK #2: API merespons tapi berisi status error
    if (!isset($responseData['status']) || $responseData['status'] !== 'success') {
        error_log("[rekomendasi.php] FALLBACK#2 - Status API bukan success. Message: " . ($responseData['message'] ?? 'Unknown') . " | Raw body: " . substr($response, 0, 300));
        return ambilMenuPopulerFallback($conn, $limit);
    }

    $rekomendasiAPI = $responseData['data'] ?? [];
    // KONDISI FALLBACK #3: API sukses tapi tidak ada data sama sekali
    if (empty($rekomendasiAPI)) {
        error_log("[rekomendasi.php] FALLBACK#3 - status=success tapi data=[] kosong. Payload yang dikirim: user_id=$userId, is_pelanggan_baru=" . ($isPelangganBaru ? 'true' : 'false') . ", kategori=$kategori, bahan=$bahan, rasa=$rasa");
        return ambilMenuPopulerFallback($conn, $limit);
    }

    // 5. Ekstraksi ID Menu dari API dan Ambil Detailnya dari Database MySQL
    $hasil = [];
    $ids = [];
    $skorMap = [];
    $metodeMap = [];

    // Mapping hasil dari Python (ID, Skor, Metode)
    foreach ($rekomendasiAPI as $item) {
        $mid = (int) $item['ID_Menu'];
        $ids[] = $mid;
        $skorMap[$mid] = $item['skor'];
        $metodeMap[$mid] = $item['metode'];
    }

    $idsEscaped = implode(',', $ids);
    if ($idsEscaped !== '') {
        // ORDER BY FIELD digunakan agar urutan ranking yang diberikan oleh Python tidak berantakan
        $query = "SELECT * FROM menu WHERE id IN ($idsEscaped) ORDER BY FIELD(id, $idsEscaped)";
        $result = mysqli_query($conn, $query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $mid = (int) $row['id'];
            
            // Tambahkan atribut dari Python ke dalam array hasil MySQL
            $row['skor_rekomendasi'] = $skorMap[$mid];
            $row['metode_rekomendasi'] = $metodeMap[$mid];
            
            $hasil[] = $row;
        }
    }

    // KONDISI FALLBACK #4: API sukses & merespons data, tapi tidak satu pun
    // ID_Menu-nya cocok dengan tabel menu saat ini (artefak model basi)
    if (empty($hasil)) {
        error_log("[rekomendasi.php] FALLBACK#4 - API mengembalikan " . count($rekomendasiAPI) . " item (ID: " . implode(',', $ids) . ") tapi TIDAK SATU PUN cocok dengan tabel menu saat ini. Kemungkinan hybrid_artifacts.pkl belum di-retrain sejak menu terakhir diubah.");
        return ambilMenuPopulerFallback($conn, $limit);
    }

    return $hasil;
}
?>