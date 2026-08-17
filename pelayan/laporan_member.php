<?php
session_start();
include '../config/koneksi.php';

// Cek apakah user adalah pelayan atau admin
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['pelayan', 'admin'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Query untuk mendapatkan statistik
$statsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_member,
        ROUND(AVG(total_pesanan), 2) as avg_pesanan,
        ROUND(AVG(total_pengeluaran), 2) as avg_pengeluaran,
        MAX(total_pengeluaran) as max_pengeluaran,
        MIN(total_pengeluaran) as min_pengeluaran
    FROM member 
    WHERE status = 'aktif'
");
$stats = mysqli_fetch_assoc($statsQuery);

// Top 10 Members by Spending
$topMembersQuery = mysqli_query($conn, "
    SELECT id, nama, nomor_telepon, total_pesanan, total_pengeluaran
    FROM member
    WHERE status = 'aktif'
    ORDER BY total_pengeluaran DESC
    LIMIT 10
");

// Top 10 Members by Order Count
$topOrderQuery = mysqli_query($conn, "
    SELECT id, nama, nomor_telepon, total_pesanan, total_pengeluaran
    FROM member
    WHERE status = 'aktif'
    ORDER BY total_pesanan DESC
    LIMIT 10
");

// Member Registrasi Last 7 Days
$recentMembersQuery = mysqli_query($conn, "
    SELECT id, nama, nomor_telepon, created_at, total_pesanan
    FROM member
    WHERE status = 'aktif'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
");

// Revenue by Member
$revenueQuery = mysqli_query($conn, "
    SELECT 
        m.id,
        m.nama,
        COUNT(p.id) as jumlah_pesanan,
        COALESCE(SUM(p.total), 0) as total_revenue
    FROM member m
    LEFT JOIN pesanan p ON m.id = p.member_id
    WHERE m.status = 'aktif'
    GROUP BY m.id
    ORDER BY total_revenue DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Member</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        body { background: #eef6f1; }
        .stat-card { border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2.5rem; font-weight: bold; color: #27ae60; }
        .stat-label { color: #7f8c8d; font-size: 0.9rem; }
        .chart-container { position: relative; height: 300px; margin-bottom: 30px; }
        .table-hover tbody tr:hover { background-color: #f0f9f7; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-light bg-white shadow-sm py-3 mb-4">
    <div class="container">
        <a href="pesan.php" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
        <h5 class="mb-0 fw-bold">Laporan & Analisis Member</h5>
        <a href="daftar_member.php" class="btn btn-outline-info btn-sm">
            <i class="fa-solid fa-user-plus"></i> Daftar Member
        </a>
    </div>
</nav>

<div class="container mb-5">
    <!-- Statistik Utama -->
    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="card stat-card p-4 text-center">
                <div class="stat-value"><?= number_format($stats['total_member']) ?></div>
                <div class="stat-label">Total Member Aktif</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-4 text-center">
                <div class="stat-value"><?= number_format($stats['avg_pesanan'], 0) ?></div>
                <div class="stat-label">Rata-rata Pesanan per Member</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-4 text-center">
                <div class="stat-value">Rp <?= number_format($stats['avg_pengeluaran'], 0) ?></div>
                <div class="stat-label">Rata-rata Pengeluaran</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-4 text-center">
                <div class="stat-value">Rp <?= number_format($stats['max_pengeluaran'], 0) ?></div>
                <div class="stat-label">Pengeluaran Tertinggi</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <!-- Top 10 Members by Spending -->
        <div class="col-lg-6">
            <div class="card shadow-sm p-4" style="border-radius: 12px;">
                <h5 class="card-title fw-bold mb-4">
                    <i class="fa-solid fa-crown text-warning"></i> Top 10 Members by Spending
                </h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Pesanan</th>
                                <th>Total Belanja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($topMembersQuery)): ?>
                            <tr>
                                <td>
                                    <strong>#<?= $row['id'] ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['nama']) ?></small>
                                </td>
                                <td><span class="badge bg-info"><?= $row['total_pesanan'] ?></span></td>
                                <td class="fw-bold text-success">Rp <?= number_format($row['total_pengeluaran'], 0) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top 10 Members by Order Count -->
        <div class="col-lg-6">
            <div class="card shadow-sm p-4" style="border-radius: 12px;">
                <h5 class="card-title fw-bold mb-4">
                    <i class="fa-solid fa-star text-danger"></i> Top 10 Members by Order Count
                </h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Jumlah Pesanan</th>
                                <th>Rata-rata Pesanan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($topOrderQuery)): ?>
                            <tr>
                                <td>
                                    <strong>#<?= $row['id'] ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['nama']) ?></small>
                                </td>
                                <td><span class="badge bg-success"><?= $row['total_pesanan'] ?></span></td>
                                <td class="text-muted">
                                    Rp <?= number_format(round($row['total_pengeluaran'] / max($row['total_pesanan'], 1)), 0) ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue by Member -->
    <div class="card shadow-sm p-4 mb-5" style="border-radius: 12px;">
        <h5 class="card-title fw-bold mb-4">
            <i class="fa-solid fa-chart-pie text-info"></i> Revenue Distribution by Top Members
        </h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Member</th>
                        <th>Jumlah Pesanan</th>
                        <th>Total Revenue</th>
                        <th>% dari Total</th>
                        <th>Rata-rata</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Get total revenue for percentage calculation
                    $totalRevenue = 0;
                    $revenueData = [];
                    mysqli_data_seek($revenueQuery, 0);
                    while ($row = mysqli_fetch_assoc($revenueQuery)) {
                        $totalRevenue += $row['total_revenue'];
                        $revenueData[] = $row;
                    }

                    foreach ($revenueData as $row):
                        $percentage = ($totalRevenue > 0) ? ($row['total_revenue'] / $totalRevenue) * 100 : 0;
                    ?>
                    <tr>
                        <td>
                            <strong>#<?= $row['id'] ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($row['nama']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-primary"><?= $row['jumlah_pesanan'] ?></span>
                        </td>
                        <td class="fw-bold text-success">Rp <?= number_format($row['total_revenue'], 0) ?></td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?= $percentage ?>%" 
                                     aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?= number_format($percentage, 1) ?>%
                                </div>
                            </div>
                        </td>
                        <td>
                            Rp <?= number_format(round($row['total_revenue'] / max($row['jumlah_pesanan'], 1)), 0) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Members (Last 7 Days) -->
    <div class="card shadow-sm p-4" style="border-radius: 12px;">
        <h5 class="card-title fw-bold mb-4">
            <i class="fa-solid fa-clock text-secondary"></i> Member Baru (Last 7 Days)
        </h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal Daftar</th>
                        <th>Nama</th>
                        <th>Nomor Telepon</th>
                        <th>Pesanan Sejak Daftar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $recentCount = 0;
                    while ($row = mysqli_fetch_assoc($recentMembersQuery)):
                        $recentCount++;
                    ?>
                    <tr>
                        <td>
                            <small><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small>
                        </td>
                        <td>
                            <strong>#<?= $row['id'] ?></strong><br>
                            <?= htmlspecialchars($row['nama']) ?>
                        </td>
                        <td>
                            <code class="bg-light p-1 rounded" style="font-size: 0.85rem;">
                                <?= htmlspecialchars($row['nomor_telepon']) ?>
                            </code>
                        </td>
                        <td>
                            <span class="badge bg-info"><?= $row['total_pesanan'] ?> pesanan</span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($recentCount === 0): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            Belum ada member baru dalam 7 hari terakhir
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
