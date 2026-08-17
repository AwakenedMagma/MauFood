<?php
session_start();
include '../config/koneksi.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// 1. Ambil Pesanan Terakhir Member
if ($action === 'last_order') {
    $member_id = (int) ($_GET['member_id'] ?? 0);
    
    if ($member_id <= 0) {
        echo json_encode(['error' => 'Member ID tidak valid']);
        exit;
    }

    // Ambil pesanan terakhir member (dari tabel pesanan + detail_pesanan)
    $query = "
        SELECT 
            p.id as pesanan_id,
            p.tanggal,
            p.total,
            GROUP_CONCAT(
                JSON_OBJECT(
                    'menu_id', dp.menu_id,
                    'menu_name', m.nama_menu,
                    'jumlah', dp.jumlah,
                    'harga', m.harga,
                    'subtotal', dp.subtotal,
                    'catatan', dp.catatan
                )
            ) as items
        FROM pesanan p
        JOIN detail_pesanan dp ON p.id = dp.pesanan_id
        JOIN menu m ON dp.menu_id = m.id
        WHERE p.member_id = $member_id
        GROUP BY p.id
        ORDER BY p.tanggal DESC
        LIMIT 1
    ";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }

    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => true, 'data' => null, 'message' => 'Member belum memiliki riwayat pesanan']);
        exit;
    }

    $pesanan = mysqli_fetch_assoc($result);
    $items = json_decode('[' . $pesanan['items'] . ']', true);

    echo json_encode([
        'success' => true,
        'data' => [
            'pesanan_id' => (int) $pesanan['pesanan_id'],
            'tanggal' => $pesanan['tanggal'],
            'total' => (float) $pesanan['total'],
            'items' => $items
        ]
    ]);
    exit;
}

// 2. Ambil Semua Riwayat Pesanan Member
if ($action === 'order_history') {
    $member_id = (int) ($_GET['member_id'] ?? 0);
    $limit = (int) ($_GET['limit'] ?? 10);

    if ($member_id <= 0) {
        echo json_encode(['error' => 'Member ID tidak valid']);
        exit;
    }

    $query = "
        SELECT 
            p.id as pesanan_id,
            p.tanggal,
            p.total,
            p.meja,
            p.tipe,
            COUNT(dp.id) as jumlah_item
        FROM pesanan p
        LEFT JOIN detail_pesanan dp ON p.id = dp.pesanan_id
        WHERE p.member_id = $member_id
        GROUP BY p.id
        ORDER BY p.tanggal DESC
        LIMIT $limit
    ";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'pesanan_id' => (int) $row['pesanan_id'],
            'tanggal' => $row['tanggal'],
            'total' => (float) $row['total'],
            'meja' => htmlspecialchars($row['meja'] ?? 'N/A'),
            'tipe' => htmlspecialchars($row['tipe']),
            'jumlah_item' => (int) $row['jumlah_item']
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($data),
        'data' => $data
    ]);
    exit;
}

// 3. Ambil Detail Pesanan (untuk expand)
if ($action === 'order_detail') {
    $pesanan_id = (int) ($_GET['pesanan_id'] ?? 0);

    if ($pesanan_id <= 0) {
        echo json_encode(['error' => 'Pesanan ID tidak valid']);
        exit;
    }

    $query = "
        SELECT 
            dp.menu_id,
            m.nama_menu,
            m.harga,
            dp.jumlah,
            dp.subtotal,
            dp.catatan,
            m.gambar
        FROM detail_pesanan dp
        JOIN menu m ON dp.menu_id = m.id
        WHERE dp.pesanan_id = $pesanan_id
        ORDER BY m.nama_menu ASC
    ";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }

    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = [
            'menu_id' => (int) $row['menu_id'],
            'nama_menu' => htmlspecialchars($row['nama_menu']),
            'harga' => (int) $row['harga'],
            'jumlah' => (int) $row['jumlah'],
            'subtotal' => (float) $row['subtotal'],
            'catatan' => htmlspecialchars($row['catatan'] ?? ''),
            'gambar' => htmlspecialchars($row['gambar'] ?? '')
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $items
    ]);
    exit;
}


// 4. Ambil Menu Favorit Member (Most Ordered)

if ($action === 'favorite_menu') {
    $member_id = (int) ($_GET['member_id'] ?? 0);
    $limit = (int) ($_GET['limit'] ?? 5);

    if ($member_id <= 0) {
        echo json_encode(['error' => 'Member ID tidak valid']);
        exit;
    }

    $query = "
        SELECT 
            m.id,
            m.nama_menu,
            m.harga,
            m.gambar,
            COUNT(dp.id) as order_count,
            SUM(dp.jumlah) as total_quantity
        FROM detail_pesanan dp
        JOIN pesanan p ON dp.pesanan_id = p.id
        JOIN menu m ON dp.menu_id = m.id
        WHERE p.member_id = $member_id
        GROUP BY m.id
        ORDER BY total_quantity DESC
        LIMIT $limit
    ";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'menu_id' => (int) $row['id'],
            'nama_menu' => htmlspecialchars($row['nama_menu']),
            'harga' => (int) $row['harga'],
            'gambar' => htmlspecialchars($row['gambar'] ?? ''),
            'order_count' => (int) $row['order_count'],
            'total_quantity' => (int) $row['total_quantity']
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($data),
        'data' => $data
    ]);
    exit;
}


// 5. Ambil Member Info dengan Order Stats

if ($action === 'member_stats') {
    $member_id = (int) ($_GET['member_id'] ?? 0);

    if ($member_id <= 0) {
        echo json_encode(['error' => 'Member ID tidak valid']);
        exit;
    }

    $query = "
        SELECT 
            m.id,
            m.nama,
            m.nomor_telepon,
            m.email,
            m.total_pesanan,
            m.total_pengeluaran,
            COUNT(DISTINCT p.id) as total_orders_calculated,
            COALESCE(SUM(p.total), 0) as total_spent_calculated,
            MAX(p.tanggal) as last_order_date,
            MIN(p.tanggal) as first_order_date,
            ROUND(AVG(p.total), 0) as avg_order_value
        FROM member m
        LEFT JOIN pesanan p ON m.id = p.member_id
        WHERE m.id = $member_id AND m.status = 'aktif'
        GROUP BY m.id
    ";

    $result = mysqli_query($conn, $query);

    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Member tidak ditemukan']);
        exit;
    }

    $member = mysqli_fetch_assoc($result);
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int) $member['id'],
            'nama' => htmlspecialchars($member['nama']),
            'nomor_telepon' => htmlspecialchars($member['nomor_telepon']),
            'email' => htmlspecialchars($member['email'] ?? ''),
            'total_pesanan' => (int) $member['total_pesanan'],
            'total_pengeluaran' => (float) $member['total_pengeluaran'],
            'avg_order_value' => (int) $member['avg_order_value'],
            'last_order_date' => $member['last_order_date'],
            'first_order_date' => $member['first_order_date']
        ]
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action tidak valid']);
