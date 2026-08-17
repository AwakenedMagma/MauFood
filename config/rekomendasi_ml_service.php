<?php
/**
 * rekomendasi_ml_service.php
 * -----------------------------------------------------------------
 * Service layer untuk menghubungkan aplikasi PHP dengan REST API
 * model ML Hybrid Filtering (Flask, app.py + hybrid_artifacts.pkl).
 *
 * REVISI: Berkas ini sebelumnya ditulis untuk kontrak API Flask versi
 * lama (endpoint GET /recommend, identifier "CUST_xxx" dari data
 * sintetis, endpoint /health, field respons "Menu"/"Hybrid Score").
 * Kontrak tersebut SUDAH TIDAK SESUAI dengan app.py yang sekarang
 * benar-benar berjalan (di-deploy via Railway), yang:
 *   - Hanya punya satu endpoint: POST /api/recommend (menerima JSON,
 *     BUKAN query string GET), tidak ada endpoint /health sama sekali.
 *   - Artefaknya (hybrid_artifacts.pkl) sejak retrain.py diperbarui
 *     dilatih dari data ASLI MauFood (tabel menu & pesanan), bukan lagi
 *     dataset sintetis nutrition.csv/CUST_xxx. Artinya nama menu hasil
 *     ML sekarang SAMA dengan tabel `menu` asli, dan User_ID pelanggan
 *     berformat "U" + member_id asli (mis. member_id=1 -> "U001"),
 *     dihasilkan oleh retrain.py dari kolom pesanan.member_id.
 *   - Field respons JSON: {"status": "success", "data": [{"ID_Menu":
 *     ..., "Nama_Menu": ..., "skor": 0-1, "metode": ...}, ...]}.
 *
 * Karena data training sekarang berasal dari transaksi ASLI, prefiks
 * "MEMBER_" yang dulu sengaja dipakai supaya TIDAK PERNAH cocok dengan
 * data sintetis (agar sistem jujur jatuh ke cold-start) justru menjadi
 * kontraproduktif: prefiks itu juga tidak akan pernah cocok dengan
 * format "U001" yang sekarang dipakai data ASLI, sehingga rekomendasi
 * personal (Weighted Hybrid) untuk member LAMA sekalipun tidak akan
 * pernah aktif. Revisi ini mengirim identifier berformat "U" + member_id
 * (zero-padded 3 digit) agar cocok dengan konvensi retrain.py.
 *
 * File ini tetap WAJIB melakukan:
 *   1. Pencocokan ID_Menu hasil ML terhadap tabel `menu` asli (jaga-jaga
 *      apabila hybrid_artifacts.pkl belum di-retrain ulang setelah ada
 *      menu baru/dihapus).
 *   2. Fallback otomatis ke rekomendasi berbasis popularitas dari data
 *      transaksi ASLI jika hasil ML tidak cukup/tidak nyambung/API mati.
 * -----------------------------------------------------------------
 */

// URL publik service Flask di Railway. Ganti ke 'http://localhost:5000'
// (port default app.py) bila sedang menguji secara lokal via MAMP.
if (!defined('ML_API_BASE_URL')) {
    define('ML_API_BASE_URL', 'https://maufood-api-python-production.up.railway.app');
    // TODO: cocokkan lagi dengan URL asli pada dashboard Railway Anda.
}
// Timeout pendek supaya jika Flask mati/lambat, halaman pesan tidak ikut hang
if (!defined('ML_API_TIMEOUT_SECONDS')) {
    define('ML_API_TIMEOUT_SECONDS', 3);
}
// Minimal jumlah menu hasil ML yang berhasil dicocokkan ke DB asli
// sebelum kita anggap hasil ML "layak tampil". Di bawah ini -> fallback.
if (!defined('ML_MIN_MATCHED_ITEMS')) {
    define('ML_MIN_MATCHED_ITEMS', 3);
}

/**
 * Format member_id asli MauFood menjadi User_ID yang dikenal model,
 * mengikuti persis konvensi f"U{str(int(x)).zfill(3)}" pada retrain.py.
 */
function formatUserIdUntukModel(int $memberId): string
{
    return 'U' . str_pad((string) $memberId, 3, '0', STR_PAD_LEFT);
}

/**
 * Panggil endpoint POST /api/recommend di Flask.
 *
 * @param string $userId           Identifier sesuai konvensi model (mis. "U001"),
 *                                  atau string kosong bila belum ada member.
 * @param bool   $isPelangganBaru  true = paksa jalur Content-Based Filtering murni;
 *                                  false = coba Weighted Hybrid, Flask akan otomatis
 *                                  jatuh ke cold-start internal bila user_id tidak
 *                                  dikenali pada user_item_matrix.
 * @return array|null  null jika gagal total (network/timeout/HTTP/JSON error),
 *                      atau array daftar rekomendasi (isi field 'data' dari respons).
 */
function callMlRecommend(string $userId, bool $isPelangganBaru, string $kategori = '', string $bahan = '', string $rasa = ''): ?array
{
    $payload = json_encode([
        'user_id'           => $userId,
        'is_pelanggan_baru' => $isPelangganBaru,
        'kategori'          => $kategori,
        'bahan'             => $bahan,
        'rasa'              => $rasa,
    ]);

    $ch = curl_init(rtrim(ML_API_BASE_URL, '/') . '/api/recommend');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload),
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, ML_API_TIMEOUT_SECONDS);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, ML_API_TIMEOUT_SECONDS);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200 || !$response) {
        error_log("[rekomendasi_ml_service] Panggilan ML API gagal (HTTP $httpCode): $curlError");
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || ($decoded['status'] ?? null) !== 'success' || !isset($decoded['data'])) {
        error_log("[rekomendasi_ml_service] Respons ML API tidak sesuai format yang diharapkan.");
        return null;
    }

    return $decoded['data'];
}

/**
 * Cocokkan hasil rekomendasi ML terhadap tabel `menu` asli MauFood.
 * Pencocokan dilakukan lewat ID_Menu (bukan lagi lewat nama), karena
 * artefak model sekarang dilatih langsung dari tabel `menu` asli
 * (lihat retrain.py) sehingga ID_Menu = id pada tabel `menu`.
 *
 * @param array $mlResults  [['ID_Menu' => int, 'Nama_Menu' => string,
 *                            'skor' => float(0-1), 'metode' => string], ...]
 * @return array  Baris menu asli (id, nama_menu, harga, gambar, kategori)
 *                yang berhasil dicocokkan, ditambah field 'hybrid_score' (0-100)
 */
function matchMlResultsToRealMenu(mysqli $conn, array $mlResults): array
{
    if (empty($mlResults)) {
        return [];
    }

    $result = mysqli_query($conn, "SELECT id, nama_menu, kategori, harga, gambar FROM menu");
    if (!$result) {
        return [];
    }

    $menuById = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $menuById[(int) $row['id']] = $row;
    }

    $matched = [];
    foreach ($mlResults as $item) {
        if (!isset($item['ID_Menu'])) {
            continue;
        }
        $menuId = (int) $item['ID_Menu'];
        if (isset($menuById[$menuId])) {
            $row = $menuById[$menuId];
            $row['hybrid_score'] = round((float) ($item['skor'] ?? 0) * 100, 1);
            $row['sumber'] = 'ai';
            $matched[] = $row;
        }
    }

    return $matched;
}

/**
 * Fallback: rekomendasi berbasis popularitas dari data transaksi ASLI
 * MauFood. Dipakai kalau ML API mati/timeout, atau hasil ML tidak cukup
 * nyambung dengan menu asli.
 *
 * @param int[] $excludeMenuIds  Menu yang mau dikecualikan (mis. sudah di keranjang)
 */
function rekomendasiPopulerAsli(mysqli $conn, int $limit = 6, array $excludeMenuIds = []): array
{
    $excludeClause = '';
    if (!empty($excludeMenuIds)) {
        $ids = implode(',', array_map('intval', $excludeMenuIds));
        $excludeClause = "WHERE m.id NOT IN ($ids)";
    }

    $limit = (int) $limit;
    $sql = "
        SELECT m.id, m.nama_menu, m.kategori, m.harga, m.gambar,
               COALESCE(SUM(dp.jumlah), 0) AS total_terjual
        FROM menu m
        LEFT JOIN detail_pesanan dp ON dp.menu_id = m.id
        $excludeClause
        GROUP BY m.id
        ORDER BY total_terjual DESC, m.id ASC
        LIMIT $limit
    ";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['hybrid_score'] = null; // tidak relevan utk popularitas biasa
        $row['sumber'] = 'populer';
        $rows[] = $row;
    }

    return $rows;
}

/**
 * FUNGSI UTAMA — panggil ini dari halaman pemesanan.
 *
 * Alur:
 *   1. Format member_id asli menjadi User_ID sesuai konvensi model, lalu
 *      minta rekomendasi ML (is_pelanggan_baru=false; Flask akan otomatis
 *      menempuh jalur cold-start internal bila member ini belum dikenali
 *      pada user_item_matrix, mis. member baru yang belum pernah order).
 *   2. Jika panggilan gagal total (Flask mati/timeout/format tak sesuai)
 *      -> langsung fallback popularitas asli.
 *   3. Cocokkan ID_Menu hasil ML ke tabel menu asli.
 *   4. Jika hasil cocok >= ML_MIN_MATCHED_ITEMS -> pakai hasil ML.
 *      Jika kurang -> lengkapi sisanya dengan rekomendasi populer asli
 *      (supaya UI tidak pernah kosong / setengah-setengah).
 *
 * @return array{items: array, sumber_utama: string, catatan: ?string}
 *   sumber_utama: 'ai' | 'campuran' | 'populer' — dipakai UI utk label singkat
 */
function getRekomendasiUntukMember(mysqli $conn, int $memberId, int $limit = 6, array $excludeMenuIds = []): array
{
    $userId = formatUserIdUntukModel($memberId);
    $mlResults = callMlRecommend($userId, false);

    if ($mlResults === null) {
        return [
            'items' => rekomendasiPopulerAsli($conn, $limit, $excludeMenuIds),
            'sumber_utama' => 'populer',
            'catatan' => 'Layanan model ML tidak aktif/tidak merespons, menampilkan menu terpopuler.',
        ];
    }

    $matched = matchMlResultsToRealMenu($conn, $mlResults);

    // Buang item yang sedang di keranjang
    if (!empty($excludeMenuIds)) {
        $excludeSet = array_flip($excludeMenuIds);
        $matched = array_values(array_filter($matched, fn($m) => !isset($excludeSet[(int) $m['id']])));
    }

    if (count($matched) >= ML_MIN_MATCHED_ITEMS) {
        return [
            'items' => array_slice($matched, 0, $limit),
            'sumber_utama' => 'ai',
            'catatan' => null,
        ];
    }

    // Hasil ML terlalu sedikit yang nyambung -> lengkapi dgn populer asli,
    // hindari duplikat id yang sudah ada di $matched
    $matchedIds = array_map(fn($m) => (int) $m['id'], $matched);
    $sisaLimit = $limit - count($matched);
    $pelengkap = rekomendasiPopulerAsli($conn, $sisaLimit, array_merge($excludeMenuIds, $matchedIds));

    return [
        'items' => array_merge($matched, $pelengkap),
        'sumber_utama' => count($matched) > 0 ? 'campuran' : 'populer',
        'catatan' => 'Hasil model ML terbatas (menu ini mungkin belum tercakup riwayat transaksi), dilengkapi dengan menu terpopuler.',
    ];
}
