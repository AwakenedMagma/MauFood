<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$user_id = $_SESSION['user']['id'];
$cartItems = $_SESSION['cart'];
$total = 0;
$summaryItems = [];

foreach ($cartItems as $item) {
    $menuId = (int) $item['menu_id'];
    $query = mysqli_query($conn, "SELECT * FROM menu WHERE id='$menuId'");
    $menu = mysqli_fetch_assoc($query);
    if (!$menu) {
        continue;
    }
    $jumlah = max(1, (int) $item['jumlah']);
    $subtotal = $menu['harga'] * $jumlah;
    $total += $subtotal;
    $summaryItems[] = [
        'menu' => $menu,
        'jumlah' => $jumlah,
        'subtotal' => $subtotal
    ];
}

if (isset($_POST['order']) && count($summaryItems) > 0) {
    mysqli_query($conn, "INSERT INTO pesanan(user_id, total) VALUES('$user_id','$total')");
    $pesanan_id = mysqli_insert_id($conn);

    foreach ($summaryItems as $item) {
        $menu = $item['menu'];
        $jumlah = $item['jumlah'];
        $subtotal = $item['subtotal'];
        mysqli_query($conn, "INSERT INTO detail_pesanan(pesanan_id, menu_id, jumlah, subtotal) VALUES('$pesanan_id','{$menu['id']}','$jumlah','$subtotal')");
    }

    unset($_SESSION['cart']);
    echo "<script>alert('Pesanan berhasil!');window.location='menu.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { background: #eef6f1; }
        .checkout-card { border-radius: 24px; }
        .item-card { border-radius: 18px; }
        .item-card img { width: 80px; height: 80px; object-fit: cover; border-radius: 16px; }
        .total-box { border-radius: 20px; }
        .btn-back { color: #198754; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm py-3">
    <div class="container">
        <a href="keranjang.php" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-chevron-left"></i> Kembali
        </a>
        <h5 class="mb-0 fw-bold">Checkout</h5>
        <div></div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <div class="card p-4 shadow checkout-card">
        <div class="mb-4">
            <h5 class="fw-bold mb-1">Konfirmasi Pesanan</h5>
            <p class="text-muted mb-0">Periksa kembali detail pengiriman dan metode pembayaran.</p>
        </div>

        <div class="row gy-3">
            <div class="col-12 col-lg-6">
                <div class="card p-3 item-card shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <h6 class="mb-1">Alamat Pengiriman</h6>
                            <small class="text-muted">Alamat akan dikirim ke akun Anda.</small>
                        </div>
                        <span class="badge bg-success">Utama</span>
                    </div>
                    <p class="mb-0">Jl. Contoh No. 123, Kecamatan Contoh, Kota Contoh</p>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card p-3 item-card shadow-sm">
                    <h6 class="mb-3">Metode Pembayaran</h6>
                    <select class="form-select" name="payment_method" form="checkoutForm">
                        <option value="COD">Bayar di tempat (COD)</option>
                        <option value="Transfer">Transfer Bank</option>
                        <option value="E-Wallet">E-Wallet</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h6 class="fw-semibold mb-3">Ringkasan Pesanan</h6>
            <?php if (count($summaryItems) === 0): ?>
                <div class="text-center text-muted py-5">
                    <i class="fa-solid fa-cart-plus fa-2x mb-3"></i>
                    <p class="mb-0">Keranjang Anda kosong.</p>
                </div>
            <?php else: ?>
                <?php foreach ($summaryItems as $item): ?>
                    <div class="card mb-3 p-3 item-card shadow-sm">
                        <div class="d-flex align-items-center gap-3">
                            <img src="../upload/<?= htmlspecialchars($item['menu']['gambar']) ?>" alt="<?= htmlspecialchars($item['menu']['nama_menu']) ?>">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?= htmlspecialchars($item['menu']['nama_menu']) ?></h6>
                                <p class="text-muted mb-1">Rp <?= number_format($item['menu']['harga'], 0, ',', '.') ?> x <?= $item['jumlah'] ?></p>
                                <p class="mb-0 fw-semibold">Subtotal: Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card p-3 mt-3 total-box shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Total Pesanan</span>
                <strong>Rp <?= number_format($total, 0, ',', '.') ?></strong>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Biaya antar</span>
                <strong>Rp 0</strong>
            </div>
        </div>

        <form id="checkoutForm" method="POST">
            <button type="submit" name="order" class="btn btn-success btn-lg w-100 mt-4" <?= count($summaryItems) === 0 ? 'disabled' : '' ?>>
                Pesan Sekarang
            </button>
        </form>
    </div>
</div>

</body>
</html>