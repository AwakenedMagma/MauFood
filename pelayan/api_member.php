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

// Cari member berdasarkan ID atau nomor telepon
if ($action === 'search') {
    $search = trim($_GET['q'] ?? '');
    
    if (strlen($search) < 1) {
        echo json_encode(['error' => 'Query terlalu pendek', 'data' => []]);
        exit;
    }

    // Escape input
    $search_escaped = mysqli_real_escape_string($conn, $search);

    // Cari berdasarkan ID, nama, atau nomor telepon
    $query = "SELECT id, nama, nomor_telepon, email, alamat, total_pesanan, total_pengeluaran 
              FROM member 
              WHERE status = 'aktif' 
              AND (id = '$search_escaped' 
                   OR nama LIKE '%$search_escaped%' 
                   OR nomor_telepon LIKE '%$search_escaped%')
              ORDER BY nama ASC 
              LIMIT 10";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . mysqli_error($conn)]);
        exit;
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'id' => (int) $row['id'],
            'nama' => htmlspecialchars($row['nama']),
            'nomor_telepon' => htmlspecialchars($row['nomor_telepon']),
            'email' => htmlspecialchars($row['email'] ?? ''),
            'alamat' => htmlspecialchars($row['alamat'] ?? ''),
            'total_pesanan' => (int) $row['total_pesanan'],
            'total_pengeluaran' => (float) $row['total_pengeluaran']
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($data),
        'data' => $data
    ]);
    exit;
}

// Ambil detail member berdasarkan ID
if ($action === 'get') {
    $member_id = (int) ($_GET['id'] ?? 0);

    if ($member_id <= 0) {
        echo json_encode(['error' => 'ID tidak valid']);
        exit;
    }

    $query = "SELECT * FROM member WHERE id = $member_id AND status = 'aktif'";
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
            'alamat' => htmlspecialchars($member['alamat'] ?? ''),
            'total_pesanan' => (int) $member['total_pesanan'],
            'total_pengeluaran' => (float) $member['total_pengeluaran'],
            'created_at' => $member['created_at']
        ]
    ]);
    exit;
}

// Ambil semua member aktif
if ($action === 'list') {
    $query = "SELECT id, nama, nomor_telepon, total_pesanan, total_pengeluaran 
              FROM member 
              WHERE status = 'aktif' 
              ORDER BY nama ASC 
              LIMIT 50";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'id' => (int) $row['id'],
            'nama' => htmlspecialchars($row['nama']),
            'nomor_telepon' => htmlspecialchars($row['nomor_telepon']),
            'total_pesanan' => (int) $row['total_pesanan'],
            'total_pengeluaran' => (float) $row['total_pengeluaran']
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($data),
        'data' => $data
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Action tidak valid']);
