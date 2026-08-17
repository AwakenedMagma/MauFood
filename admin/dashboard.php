<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}


// 1. TANGKAP PARAMETER FILTER (STATUS & TANGGAL)
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'semua';
$validStatuses = ['pending', 'diproses', 'selesai', 'dibatalkan'];

$tanggalMulai = $_GET['tanggal_mulai'] ?? '';
$tanggalAkhir = $_GET['tanggal_akhir'] ?? '';

// Array untuk menyimpan kondisi query
$whereConditions = [];
$dateConditions = []; // Khusus untuk kartu statistik global

// Filter Status
if (in_array($statusFilter, $validStatuses)) {
    $whereConditions[] = "p.status = '" . mysqli_real_escape_string($conn, $statusFilter) . "'";
}

// Filter Tanggal
if (!empty($tanggalMulai)) {
    $dateCond = "DATE(tanggal) >= '" . mysqli_real_escape_string($conn, $tanggalMulai) . "'";
    $whereConditions[] = str_replace('tanggal', 'p.tanggal', $dateCond);
    $dateConditions[] = $dateCond;
}
if (!empty($tanggalAkhir)) {
    $dateCond = "DATE(tanggal) <= '" . mysqli_real_escape_string($conn, $tanggalAkhir) . "'";
    $whereConditions[] = str_replace('tanggal', 'p.tanggal', $dateCond);
    $dateConditions[] = $dateCond;
}

// Gabungkan klausa WHERE untuk tabel utama
$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = " WHERE " . implode(" AND ", $whereConditions);
}

// Gabungkan klausa WHERE khusus untuk statistik (mengabaikan status, hanya tanggal)
$dateWhereClause = "";
if (count($dateConditions) > 0) {
    $dateWhereClause = " WHERE " . implode(" AND ", $dateConditions);
}

// String query parameter untuk ditaruh di tombol "Pills" status agar tanggal tidak hilang saat diklik
$dateQueryString = "";
if (!empty($tanggalMulai)) $dateQueryString .= "&tanggal_mulai=" . urlencode($tanggalMulai);
if (!empty($tanggalAkhir)) $dateQueryString .= "&tanggal_akhir=" . urlencode($tanggalAkhir);



// 2. QUERY KARTU STATISTIK (DIPENGARUHI FILTER TANGGAL)

// Hitung jumlah bersih pesanan berdasarkan tanggal
$orderCountQuery = mysqli_query($conn, "SELECT COUNT(*) AS total_orders FROM pesanan $dateWhereClause");
$orderCount = mysqli_fetch_assoc($orderCountQuery)['total_orders'];

// Hitung total revenue (Hanya pesanan selesai + sesuai rentang tanggal)
$revenueWhere = "WHERE status='selesai'";
if (count($dateConditions) > 0) {
    $revenueWhere .= " AND " . implode(" AND ", $dateConditions);
}
$totalRevenueQuery = mysqli_query($conn, "SELECT SUM(total) AS total FROM pesanan $revenueWhere");
$totalRevenue = mysqli_fetch_assoc($totalRevenueQuery)['total'] ?? 0;

$periodeText = (!empty($tanggalMulai) || !empty($tanggalAkhir)) ? "Periode Terpilih" : "All Time";
$periodeIcon = (!empty($tanggalMulai) || !empty($tanggalAkhir)) ? "fa-calendar-day" : "fa-arrow-trend-up";



// 3. QUERY TABEL UTAMA (DIPENGARUHI FILTER STATUS & TANGGAL)

$orderQuery = mysqli_query($conn, "SELECT p.*, u.nama AS customer_name FROM pesanan p LEFT JOIN users u ON p.user_id = u.id $whereClause ORDER BY p.id DESC");
$totalOrdersTable = mysqli_num_rows($orderQuery);
$orders = [];
$orderIds = [];

// Fungsi untuk mendapatkan warna badge berdasarkan status
function getStatusBadge($status) {
    $statusConfig = [
        'pending' => ['warna' => 'bg-amber-100 text-amber-700 border-amber-200', 'label' => 'Menunggu'],
        'diproses' => ['warna' => 'bg-sky-100 text-sky-700 border-sky-200', 'label' => 'Diproses'],
        'selesai' => ['warna' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'label' => 'Selesai'],
        'dibatalkan' => ['warna' => 'bg-rose-100 text-rose-700 border-rose-200', 'label' => 'Dibatalkan']
    ];
    return $statusConfig[$status] ?? ['warna' => 'bg-slate-100 text-slate-700 border-slate-200', 'label' => 'Tidak Diketahui'];
}

function formatTanggalIndonesia($tanggal) {
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $date = new DateTime($tanggal);
    return $date->format('d') . " " . $bulan[(int)$date->format('m') - 1] . " " . $date->format('Y') . " pukul " . $date->format('H:i');
}

while ($row = mysqli_fetch_assoc($orderQuery)) {
    $row['formatted_date'] = formatTanggalIndonesia($row['tanggal']);
    $row['status_badge'] = getStatusBadge($row['status']);
    $orders[$row['id']] = $row;
    $orderIds[] = $row['id'];
}

// OPTIMASI: Hanya ambil detail pesanan untuk ID yang sedang difilter/ditampilkan saja
$orderDetails = [];
if (count($orderIds) > 0) {
    $ids_string = implode(',', $orderIds);
    $detailQuery = mysqli_query($conn, "
        SELECT dp.*, m.nama_menu, m.gambar, m.harga 
        FROM detail_pesanan dp 
        LEFT JOIN menu m ON dp.menu_id = m.id
        WHERE dp.pesanan_id IN ($ids_string)
    ");
    while ($d = mysqli_fetch_assoc($detailQuery)) {
        $orderDetails[$d['pesanan_id']][] = $d;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Maufood</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .stat-card { animation: fadeIn 0.5s ease-out forwards; opacity: 0; transform: translateY(10px); }
        @keyframes fadeIn { to { opacity: 1; transform: translateY(0); } }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        /* Style input date native */
        input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; transition: 0.2s; }
        input[type="date"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }
    </style>
</head>
<!-- Tambahkan print:bg-white agar background jadi bersih saat print -->
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col relative print:bg-white">

    <!-- Tambahkan print:hidden untuk menyembunyikan elemen saat print -->
    <nav class="bg-white/80 backdrop-blur-md shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] sticky top-0 z-40 border-b border-slate-100 print:hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex-shrink-0 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 text-white flex items-center justify-center shadow-md shadow-green-500/20">
                        <i class="fa-solid fa-chart-pie text-sm"></i>
                    </div>
                    <h1 class="font-bold text-xl tracking-tight hidden sm:block">Admin <span class="text-green-500">Maufood</span></h1>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="dashboard.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 transition-colors">
                        <i class="fa-solid fa-house"></i> <span class="hidden md:inline">Dashboard</span>
                    </a>
                    <a href="menu.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
                        <i class="fa-solid fa-utensils"></i> <span class="hidden md:inline">Kelola Menu</span>
                    </a>
                    <a href="kelola_pelayan.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
                        <i class="fa-solid fa-user-tie"></i> <span class="hidden md:inline">Kelola Pelayan</span>
                    </a>
                    <div class="w-px h-6 bg-slate-200 mx-1 hidden sm:block"></div>
                    <div class="hidden sm:flex items-center gap-2 text-sm font-medium text-slate-600">
                        <div class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <span>Halo, <?= htmlspecialchars($_SESSION['user']['nama'] ?? 'Admin') ?></span>
                    </div>
                    <a href="../auth/logout.php" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-rose-500 hover:bg-rose-50 hover:text-rose-600 transition-colors" title="Keluar">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Elemen utama UI yang disembunyikan saat di-print -->
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 print:hidden">
        
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Dashboard Admin</h2>
            <p class="text-slate-500 text-sm mt-1">Ringkasan pesanan dan pendapatan operasional restoran.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Stat 1 -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 stat-card hover:shadow-md transition-all duration-300 transform hover:-translate-y-1" style="animation-delay: 0s;">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Total Pesanan</p>
                        <h3 class="text-3xl font-bold text-slate-800"><?= number_format($orderCount) ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center shadow-inner border border-blue-100">
                        <i class="fa-solid fa-receipt text-xl"></i>
                    </div>
                </div>
                <div class="flex items-center text-xs text-slate-400 font-medium mt-2">
                    <span class="flex items-center gap-1 <?= !empty($tanggalMulai) ? 'text-indigo-500 bg-indigo-50' : 'text-blue-500 bg-blue-50' ?> px-2 py-0.5 rounded-md mr-2">
                        <i class="fa-solid <?= $periodeIcon ?>"></i> <?= $periodeText ?>
                    </span>
                    Tercatat dalam sistem
                </div>
            </div>

            <!-- Stat 2 -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 stat-card hover:shadow-md transition-all duration-300 transform hover:-translate-y-1" style="animation-delay: 0.1s;">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Pendapatan</p>
                        <h3 class="text-3xl font-bold text-emerald-600 tracking-tight">
                            <span class="text-xl mr-0.5 text-emerald-400">Rp</span><?= number_format($totalRevenue, 0, ',', '.') ?>
                        </h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center shadow-inner border border-emerald-100">
                        <i class="fa-solid fa-wallet text-xl"></i>
                    </div>
                </div>
                <div class="flex items-center text-xs text-slate-400 font-medium mt-2">
                    <span class="flex items-center gap-1 <?= !empty($tanggalMulai) ? 'text-indigo-500 bg-indigo-50' : 'text-emerald-500 bg-emerald-50' ?> px-2 py-0.5 rounded-md mr-2">
                        <i class="fa-solid <?= $periodeIcon ?>"></i> <?= $periodeText ?>
                    </span>
                    Dari pesanan selesai
                </div>
            </div>

            <!-- Stat 3 -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 stat-card hover:shadow-md transition-all duration-300 transform hover:-translate-y-1" style="animation-delay: 0.2s;">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Total Member</p>
                        <?php
                        $userCountQuery = mysqli_query($conn, "SELECT COUNT(*) AS total_users FROM member WHERE status='aktif'");
                        $userCount = mysqli_fetch_assoc($userCountQuery)['total_users'];
                        ?>
                        <h3 class="text-3xl font-bold text-slate-800"><?= number_format($userCount) ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-500 flex items-center justify-center shadow-inner border border-purple-100">
                        <i class="fa-solid fa-users text-xl"></i>
                    </div>
                </div>
                <div class="flex items-center text-xs text-slate-400 font-medium mt-2">
                    <span class="flex items-center gap-1 text-purple-500 bg-purple-50 px-2 py-0.5 rounded-md mr-2">
                        <i class="fa-regular fa-id-card"></i> Aktif
                    </span>
                    Akun member terdaftar
                </div>
            </div>
        </div>

        <!-- Tabel Data Pesanan -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden stat-card flex flex-col" style="animation-delay: 0.3s;">
            
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex flex-col gap-4">
                
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-emerald-100 text-emerald-600 rounded-lg">
                            <i class="fa-solid fa-list-ul"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Daftar Pesanan</h3>
                            <p class="text-xs text-slate-500">Menampilkan <span class="font-semibold text-slate-700"><?= number_format($totalOrdersTable) ?></span> pesanan untuk filter yang dipilih.</p>
                        </div>
                    </div>
                    
                    <!-- FORM FILTER TANGGAL & CETAK LAPORAN -->
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                        
                        <div class="flex items-center bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                            <input type="date" name="tanggal_mulai" value="<?= htmlspecialchars($tanggalMulai) ?>" class="px-3 py-1.5 text-sm text-slate-600 focus:outline-none focus:bg-slate-50 transition-colors" title="Tanggal Mulai">
                            <span class="text-slate-300 bg-slate-50 px-2 py-1.5 border-x border-slate-200 text-sm font-medium">s/d</span>
                            <input type="date" name="tanggal_akhir" value="<?= htmlspecialchars($tanggalAkhir) ?>" class="px-3 py-1.5 text-sm text-slate-600 focus:outline-none focus:bg-slate-50 transition-colors" title="Tanggal Akhir">
                        </div>
                        
                        <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm" title="Terapkan Filter Tanggal">
                            <i class="fa-solid fa-filter"></i>
                        </button>
                        
                        <?php if(!empty($tanggalMulai) || !empty($tanggalAkhir)): ?>
                            <a href="dashboard.php?status=<?= htmlspecialchars($statusFilter) ?>" class="bg-rose-50 hover:bg-rose-100 text-rose-500 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border border-rose-200" title="Hapus Filter Tanggal">
                                <i class="fa-solid fa-rotate-left"></i>
                            </a>
                        <?php endif; ?>

                        <!-- TOMBOL CETAK LAPORAN -->
                        <button type="button" onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-colors shadow-sm ml-2 flex items-center gap-2" title="Cetak Laporan Bulanan">
                            <i class="fa-solid fa-print"></i> <span class="hidden sm:inline">Cetak Laporan</span>
                        </button>
                    </form>
                </div>

                <!-- FILTER PILLS STATUS -->
                <div class="flex items-center gap-2 overflow-x-auto custom-scroll pb-1">
                    <a href="dashboard.php?status=semua<?= $dateQueryString ?>" class="px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors <?= $statusFilter === 'semua' ? 'bg-slate-800 text-white shadow-md' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' ?>">Semua Status</a>
                    <a href="dashboard.php?status=pending<?= $dateQueryString ?>" class="px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors <?= $statusFilter === 'pending' ? 'bg-amber-500 text-white shadow-md shadow-amber-500/30' : 'bg-white border border-slate-200 text-slate-600 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200' ?>">Menunggu</a>
                    <a href="dashboard.php?status=diproses<?= $dateQueryString ?>" class="px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors <?= $statusFilter === 'diproses' ? 'bg-sky-500 text-white shadow-md shadow-sky-500/30' : 'bg-white border border-slate-200 text-slate-600 hover:bg-sky-50 hover:text-sky-600 hover:border-sky-200' ?>">Diproses</a>
                    <a href="dashboard.php?status=selesai<?= $dateQueryString ?>" class="px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors <?= $statusFilter === 'selesai' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/30' : 'bg-white border border-slate-200 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-200' ?>">Selesai</a>
                    <a href="dashboard.php?status=dibatalkan<?= $dateQueryString ?>" class="px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors <?= $statusFilter === 'dibatalkan' ? 'bg-rose-500 text-white shadow-md shadow-rose-500/30' : 'bg-white border border-slate-200 text-slate-600 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200' ?>">Dibatalkan</a>
                </div>
            </div>
            
            <div class="overflow-x-auto custom-scroll">
                <table class="w-full text-left border-collapse min-w-[700px]">
                    <thead>
                        <tr class="bg-white border-b border-slate-100 text-slate-400 text-xs uppercase tracking-wider font-semibold">
                            <th class="px-6 py-4 w-20">ID</th>
                            <th class="px-6 py-4">Dibuat Oleh</th>
                            <th class="px-6 py-4">Waktu Pemesanan</th>
                            <th class="px-6 py-4">Total Harga</th>
                            <th class="px-6 py-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (count($orders) === 0): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center bg-slate-50/30">
                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 text-slate-300 mb-3">
                                        <i class="fa-solid fa-filter-circle-xmark text-2xl"></i>
                                    </div>
                                    <p class="text-slate-500 font-medium">Tidak ada pesanan yang sesuai dengan filter.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr onclick="showModal(<?= $order['id'] ?>)" class="cursor-pointer hover:bg-slate-50/80 transition-colors group">
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-slate-700 group-hover:text-emerald-600 transition-colors">#<?= htmlspecialchars($order['id']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-xs group-hover:bg-emerald-100 group-hover:text-emerald-500 transition-colors">
                                                <i class="fa-solid fa-user-tie"></i>
                                            </div>
                                            <span class="font-medium text-slate-700"><?= htmlspecialchars($order['customer_name'] ?? 'Pelayan / Tamu') ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2 text-slate-500 text-xs">
                                            <i class="fa-regular fa-clock text-slate-400"></i>
                                            <?= $order['formatted_date'] ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-slate-800">Rp <?= number_format($order['total'], 0, ',', '.') ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border <?= $order['status_badge']['warna'] ?>">
                                            <?= $order['status_badge']['label'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </main>

    <!-- MODAL DETAIL PESANAN (Disembunyikan saat Print) -->
    <div id="orderModal" class="fixed inset-0 z-[100] hidden items-center justify-center print:hidden">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 relative z-10 flex flex-col max-h-[90vh] transform scale-95 opacity-0 transition-all duration-300" id="modalContent">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 rounded-t-2xl flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg" id="modalOrderId">Detail Pesanan</h3>
                        <p class="text-xs text-slate-500 font-medium mt-0.5" id="modalOrderDate"></p>
                    </div>
                </div>
                <button onclick="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-rose-100 hover:text-rose-600 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto custom-scroll flex-grow">
                <div class="flex gap-4 mb-6">
                    <div class="flex-1 bg-slate-50 border border-slate-100 rounded-xl p-3 text-center">
                        <p class="text-xs text-slate-400 font-semibold uppercase mb-1">Status</p>
                        <div id="modalOrderStatus"></div>
                    </div>
                    <div class="flex-1 bg-slate-50 border border-slate-100 rounded-xl p-3 text-center">
                        <p class="text-xs text-slate-400 font-semibold uppercase mb-1">Nomor Meja</p>
                        <p class="font-bold text-slate-800" id="modalOrderTable">-</p>
                    </div>
                    <div class="flex-1 bg-slate-50 border border-slate-100 rounded-xl p-3 text-center">
                        <p class="text-xs text-slate-400 font-semibold uppercase mb-1">Tipe Pesanan</p>
                        <p class="font-bold text-slate-800 capitalize" id="modalOrderType">-</p>
                    </div>
                </div>
                <h4 class="font-bold text-slate-700 text-sm mb-3 border-b border-slate-100 pb-2">Daftar Menu</h4>
                <div class="space-y-3" id="modalItemList"></div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 rounded-b-2xl flex items-center justify-between flex-shrink-0">
                <span class="font-medium text-slate-500">Total Pembayaran</span>
                <span class="text-2xl font-bold text-emerald-600" id="modalOrderTotal">Rp 0</span>
            </div>
        </div>
    </div>

    <!-- ============================================================================== -->
    <!-- BAGIAN KHUSUS PRINT (Tampil Hanya Saat Cetak Kertas / PDF)                     -->
    <!-- ============================================================================== -->
    <div class="hidden print:block w-full p-8 text-black bg-white">
        <!-- Kop Laporan -->
        <div class="text-center mb-8 border-b-2 border-black pb-4">
            <h1 class="text-3xl font-bold uppercase tracking-widest mb-1">Laporan Pesanan Maufood</h1>
            <p class="text-md">
                Periode: 
                <span class="font-semibold">
                    <?= !empty($tanggalMulai) ? date('d M Y', strtotime($tanggalMulai)) : 'Awal' ?> 
                    s/d 
                    <?= !empty($tanggalAkhir) ? date('d M Y', strtotime($tanggalAkhir)) : 'Sekarang' ?>
                </span>
            </p>
            <p class="text-sm mt-1">Status Pesanan: <span class="font-semibold uppercase"><?= $statusFilter ?></span></p>
        </div>

        <!-- Tabel Laporan -->
        <table class="w-full text-left border-collapse border border-black text-sm mb-6">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border border-black px-3 py-2 text-center w-16">ID</th>
                    <th class="border border-black px-3 py-2 w-48">Tanggal & Waktu</th>
                    <th class="border border-black px-3 py-2 w-40">Pelayan</th>
                    <th class="border border-black px-3 py-2">Detail Menu Pesanan</th>
                    <th class="border border-black px-3 py-2 text-center w-24">Status</th>
                    <th class="border border-black px-3 py-2 text-right w-36">Total Harga</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($orders) === 0): ?>
                    <tr>
                        <td colspan="6" class="border border-black px-3 py-4 text-center italic">Tidak ada data pesanan pada periode dan status ini.</td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $totalSemuaHalaman = 0;
                    foreach ($orders as $order): 
                        $totalSemuaHalaman += $order['total'];
                    ?>
                        <tr>
                            <td class="border border-black px-3 py-2 text-center align-top">#<?= $order['id'] ?></td>
                            <td class="border border-black px-3 py-2 align-top"><?= date('d M Y, H:i', strtotime($order['tanggal'])) ?></td>
                            <td class="border border-black px-3 py-2 align-top"><?= htmlspecialchars($order['customer_name'] ?? 'Tidak Diketahui') ?></td>
                            <td class="border border-black px-3 py-2 align-top">
                                <ul class="list-decimal list-inside">
                                    <?php 
                                    $details = $orderDetails[$order['id']] ?? [];
                                    if (count($details) === 0) {
                                        echo "<li>-</li>";
                                    } else {
                                        foreach ($details as $d) {
                                            echo "<li>" . htmlspecialchars($d['nama_menu']) . " <strong>(x" . $d['jumlah'] . ")</strong></li>";
                                        }
                                    }
                                    ?>
                                </ul>
                            </td>
                            <td class="border border-black px-3 py-2 text-center align-top capitalize"><?= $order['status'] ?></td>
                            <td class="border border-black px-3 py-2 text-right align-top">Rp <?= number_format($order['total'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-gray-100 font-bold">
                    <td colspan="5" class="border border-black px-3 py-2 text-right uppercase tracking-wider">Total Pendapatan (Status Selesai Saja):</td>
                    <td class="border border-black px-3 py-2 text-right text-lg">Rp <?= number_format($totalRevenue, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Footer Tanda Tangan -->
        <div class="mt-12 flex justify-end">
            <div class="text-center w-64">
                <p class="mb-16 text-sm">Dicetak pada: <?= date('d M Y, H:i') ?></p>
                <p class="font-bold border-b border-black pb-1"><?= htmlspecialchars($_SESSION['user']['nama'] ?? 'Admin') ?></p>
                <p class="text-sm mt-1">Administrator Maufood</p>
            </div>
        </div>
    </div>
    <!-- AKHIR BAGIAN PRINT -->

    <!-- Data PHP ke JavaScript -->
    <script>
        const ordersData = <?= json_encode($orders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const orderDetailsData = <?= json_encode($orderDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        const modal = document.getElementById('orderModal');
        const modalContent = document.getElementById('modalContent');

        function formatRupiah(angka) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
        }

        function showModal(orderId) {
            const order = ordersData[orderId];
            const details = orderDetailsData[orderId] || [];

            if (!order) return;

            document.getElementById('modalOrderId').innerText = `Detail Pesanan #${order.id}`;
            document.getElementById('modalOrderDate').innerHTML = `<i class="fa-regular fa-clock mr-1"></i> ${order.formatted_date}`;
            document.getElementById('modalOrderTable').innerText = order.meja ? order.meja.toUpperCase() : '-';
            document.getElementById('modalOrderType').innerText = order.tipe || '-';
            
            document.getElementById('modalOrderStatus').innerHTML = `
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold border ${order.status_badge.warna}">
                    ${order.status_badge.label}
                </span>
            `;

            const itemListContainer = document.getElementById('modalItemList');
            itemListContainer.innerHTML = '';

            if (details.length === 0) {
                itemListContainer.innerHTML = '<p class="text-sm text-slate-500 text-center py-4">Detail pesanan tidak ditemukan.</p>';
            } else {
                details.forEach(item => {
                    const imgSrc = item.gambar ? `../upload/${item.gambar}` : 'https://placehold.co/100x100?text=No+Image';
                    itemListContainer.innerHTML += `
                        <div class="flex items-center gap-4 bg-white p-3 rounded-xl border border-slate-100 shadow-sm">
                            <img src="${imgSrc}" class="w-16 h-16 rounded-lg object-cover bg-slate-100" alt="Menu Image">
                            <div class="flex-grow min-w-0">
                                <h5 class="font-bold text-slate-800 text-sm truncate">${item.nama_menu || 'Menu Dihapus'}</h5>
                                <p class="text-xs text-slate-500 mt-0.5">${item.jumlah}x @ ${formatRupiah(item.harga || 0)}</p>
                            </div>
                            <div class="font-bold text-slate-700 text-sm whitespace-nowrap">
                                ${formatRupiah(item.subtotal)}
                            </div>
                        </div>
                    `;
                });
            }

            document.getElementById('modalOrderTotal').innerText = formatRupiah(order.total);

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeModal() {
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }
    </script>
</body>
</html>