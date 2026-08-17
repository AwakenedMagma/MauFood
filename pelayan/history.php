<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user']['id'];
$orderQuery = mysqli_query($conn, "SELECT * FROM pesanan WHERE user_id='$userId' ORDER BY id DESC");
$orders = [];
while ($row = mysqli_fetch_assoc($orderQuery)) {
    $row['details'] = [];
    $detailQuery = mysqli_query($conn, "SELECT dp.*, m.nama_menu, m.gambar FROM detail_pesanan dp LEFT JOIN menu m ON dp.menu_id = m.id WHERE dp.pesanan_id='{$row['id']}'");
    while ($detail = mysqli_fetch_assoc($detailQuery)) {
        $row['details'][] = $detail;
    }
    $orders[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pembelian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { background: #eef6f1; }
        .history-card { border-radius: 24px; }
        .item-image { width: 60px; height: 60px; object-fit: cover; border-radius: 14px; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm py-3 mb-3">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="menu.php" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-chevron-left"></i> Kembali
        </a>
        <h5 class="mb-0 fw-bold">Riwayat Pembelian</h5>
        <div></div>
    </div>
</nav>

<div class="container mb-5">
    <div class="card p-4 shadow-sm history-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-bold mb-1">Pesanan Terakhir</h5>
                <p class="text-muted mb-0">Lihat semua pesanan yang telah kamu lakukan.</p>
            </div>
            <span class="badge bg-success py-2 px-3">Total <?= number_format(count($orders)) ?></span>
        </div>

        <?php if (count($orders) === 0): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-clock-rotate-left fa-2x mb-3"></i>
                <p class="mb-0">Belum ada riwayat pembelian.</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="card mb-3 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold mb-1">Pesanan #<?= htmlspecialchars($order['id']) ?></h6>
                                <small class="text-muted">Total: Rp <?= number_format($order['total'], 0, ',', '.') ?></small>
                            </div>
                            <span class="badge bg-success">Selesai</span>
                        </div>

                        <?php foreach ($order['details'] as $detail): ?>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img src="../upload/<?= htmlspecialchars($detail['gambar']) ?>" alt="<?= htmlspecialchars($detail['nama_menu']) ?>" class="item-image">
                                <div class="flex-grow-1">
                                    <p class="mb-1 fw-semibold"><?= htmlspecialchars($detail['nama_menu'] ?? 'Menu') ?></p>
                                    <small class="text-muted">x<?= htmlspecialchars($detail['jumlah']) ?> • Rp <?= number_format($detail['subtotal'], 0, ',', '.') ?></small>
                                    <?php if (!empty($detail['catatan'])): ?>
                                        <div class="small text-warning-emphasis mt-1"><i class="fa-solid fa-note-sticky"></i> <?= htmlspecialchars($detail['catatan']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
