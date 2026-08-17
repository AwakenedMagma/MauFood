<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelayan') {
    header('Location: ../auth/login.php');
    exit;
}

// Get statistics
$statsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_member,
        ROUND(AVG(total_pesanan), 0) as avg_order_per_member,
        COUNT(DISTINCT DATE(created_at)) as days_active
    FROM member
    WHERE status = 'aktif'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = mysqli_fetch_assoc($statsQuery);

// Get recent members
$recentMembersQuery = mysqli_query($conn, "
    SELECT id, nama, nomor_telepon, total_pesanan, created_at
    FROM member
    WHERE status = 'aktif'
    ORDER BY created_at DESC
    LIMIT 5
");

// Get pending orders count
$pendingQuery = mysqli_query($conn, "
    SELECT COUNT(*) as pending_count
    FROM pesanan
    WHERE status = 'pending'
");
$pending = mysqli_fetch_assoc($pendingQuery);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Menu - Pilih Metode</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" />
    <style>
        * { margin: 0; padding: 0; }
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .navbar-custom {
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .order-method {
            border-radius: 20px;
            border: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            position: relative;
            overflow: hidden;
        }

        .order-method::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .order-method:hover {
            transform: translateY(-10px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
        }

        .order-method:hover::before {
            opacity: 1;
        }

        .order-method.quick {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .order-method.regular {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .order-method.manage {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }

        .order-method.member {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .order-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }

        .order-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .order-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .badge-new {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: white;
            font-size: 0.75rem;
            padding: 5px 12px;
            border-radius: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            color: #999;
            font-size: 0.9rem;
            margin-top: 8px;
        }

        .member-list {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .member-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .member-item:last-child {
            border-bottom: none;
        }

        .member-info h6 {
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .member-info small {
            color: #999;
        }

        .member-badge {
            background: #f0f9f7;
            color: #27ae60;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .pending-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #ff6b6b;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            animation: pulse 2s infinite;
        }

        .container-main {
            max-width: 1400px;
            margin: 0 auto;
        }

        .footer-text {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-top: 40px;
            padding-bottom: 20px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-light navbar-custom py-3 mb-4">
    <div class="container-main">
        <span class="navbar-brand fw-bold" style="font-size: 1.3rem;">
            <i class="fa-solid fa-utensils" style="color: #667eea;"></i> MauFood - Menu Pesanan
        </span>
        <div>
            <a href="laporan_member.php" class="btn btn-sm btn-outline-secondary me-2">
                <i class="fa-solid fa-chart-bar"></i> Laporan
            </a>
            <a href="../auth/logout.php" class="btn btn-sm btn-outline-danger">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</nav>

<div class="container-main">
    <!-- Pending Orders Alert -->
    <?php if ($pending['pending_count'] > 0): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert" style="background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 12px;">
        <i class="fa-solid fa-info-circle"></i> 
        Ada <strong><?= $pending['pending_count'] ?> pesanan pending</strong> yang menunggu diproses.
        <a href="kelola_pesanan.php" class="alert-link">Lihat sekarang</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="text-center mb-5" style="color: white;">
        <h1 class="fw-bold mb-2" style="font-size: 2.5rem;">
            <i class="fa-solid fa-lightning"></i> Pilih Metode Pesanan
        </h1>
        <p style="font-size: 1.1rem; opacity: 0.9;">
            Pilih cara tercepat untuk membuat pesanan
        </p>
    </div>

    <!-- Main Content -->
    <div class="row g-4 mb-5">
        <!-- Quick Order by Member -->
        <div class="col-md-6 col-lg-3">
            <a href="pesan_quick.php" class="order-method quick">
                <div class="badge-new">
                    <i class="fa-solid fa-star"></i> NEW
                </div>
                <div class="order-icon">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div class="order-title">Quick Order</div>
                <div class="order-subtitle">
                    Load pesanan terakhir member dengan 1 klik
                </div>
                <small style="position: relative; z-index: 1; opacity: 0.8;">
                    <i class="fa-solid fa-arrow-right"></i> Tercepat
                </small>
            </a>
        </div>

        <!-- Regular Order -->
        <div class="col-md-6 col-lg-3">
            <a href="pesan.php" class="order-method regular">
                <div class="order-icon">
                    <i class="fa-solid fa-clipboard"></i>
                </div>
                <div class="order-title">Regular Order</div>
                <div class="order-subtitle">
                    Buat pesanan baru dari awal
                </div>
                <small style="position: relative; z-index: 1; opacity: 0.8;">
                    <i class="fa-solid fa-arrow-right"></i> Standard
                </small>
            </a>
        </div>

        <!-- Manage Member -->
        <div class="col-md-6 col-lg-3">
            <a href="daftar_member.php" class="order-method member">
                <div class="order-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="order-title">Kelola Member</div>
                <div class="order-subtitle">
                    Daftar atau edit data member
                </div>
                <small style="position: relative; z-index: 1; opacity: 0.8;">
                    <i class="fa-solid fa-arrow-right"></i> Database
                </small>
            </a>
        </div>

        <!-- Manage Orders -->
        <div class="col-md-6 col-lg-3">
            <a href="kelola_pesanan.php" class="order-method manage">
                <div class="pending-badge" style="display: <?= $pending['pending_count'] > 0 ? 'flex' : 'none' ?>;">
                    <?= $pending['pending_count'] ?>
                </div>
                <div class="order-icon">
                    <i class="fa-solid fa-list-check"></i>
                </div>
                <div class="order-title">Kelola Pesanan</div>
                <div class="order-subtitle">
                    Lihat & update status pesanan
                </div>
                <small style="position: relative; z-index: 1; opacity: 0.8;">
                    <i class="fa-solid fa-arrow-right"></i> Tracking
                </small>
            </a>
        </div>
    </div>

    <!-- Statistics & Recent Members -->
    <div class="row g-4 mb-5">
        <!-- Stats -->
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stat-value"><?= number_format($stats['total_member']) ?></div>
                <div class="stat-label">Total Member Aktif</div>
            </div>
            <div class="stats-card">
                <div class="stat-value"><?= number_format($stats['avg_order_per_member'], 0) ?></div>
                <div class="stat-label">Rata-rata Pesanan/Member</div>
            </div>
        </div>

        <!-- Recent Members -->
        <div class="col-md-8">
            <div class="member-list">
                <div style="padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600;">
                    <i class="fa-solid fa-clock"></i> Member Terbaru (Last 5)
                </div>
                <?php while ($member = mysqli_fetch_assoc($recentMembersQuery)): ?>
                <div class="member-item">
                    <div class="member-info">
                        <h6>#<?= $member['id'] ?> - <?= htmlspecialchars($member['nama']) ?></h6>
                        <small><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($member['nomor_telepon']) ?></small><br>
                        <small class="text-muted">Sejak <?= date('d/m/Y', strtotime($member['created_at'])) ?></small>
                    </div>
                    <div class="member-badge">
                        <?= $member['total_pesanan'] ?> pesanan
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Tips Section -->
    <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.2); padding: 30px; margin-bottom: 30px;">
        <h5 class="fw-bold mb-3" style="color: #667eea;">
            <i class="fa-solid fa-lightbulb"></i> Tips Penggunaan
        </h5>
        <div class="row g-4">
            <div class="col-md-6">
                <h6 style="color: #f5576c;">⚡ Gunakan Quick Order Jika:</h6>
                <ul class="small text-muted" style="margin-bottom: 0;">
                    <li>Member punya pesanan yang sering berulang</li>
                    <li>Jam sibuk dan perlu cepat</li>
                    <li>Member sudah dari database</li>
                    <li>Hanya ubah beberapa item dari pesanan terakhir</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 style="color: #00f2fe;">📝 Gunakan Regular Order Jika:</h6>
                <ul class="small text-muted" style="margin-bottom: 0;">
                    <li>Member baru atau belum terdaftar</li>
                    <li>Pesanan custom dari awal</li>
                    <li>Tidak ada riwayat pesanan sebelumnya</li>
                    <li>Butuh memilih menu secara spesifik</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer-text">
        <small>
            <i class="fa-solid fa-info-circle"></i> 
            Quick Order dapat menghemat waktu hingga 70% untuk pesanan member yang berulang
        </small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
