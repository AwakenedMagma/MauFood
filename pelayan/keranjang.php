<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Tambah item ke keranjang (jika menu sudah ada, jumlahnya digabung)
if (isset($_POST['menu_id']) && !isset($_POST['update_quantity'])) {
    $menuId = (int) $_POST['menu_id'];
    $jumlah = max(1, (int) $_POST['jumlah']);

    $sudahAda = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['menu_id'] === $menuId) {
            $item['jumlah'] += $jumlah;
            $sudahAda = true;
            break;
        }
    }
    unset($item);

    if (!$sudahAda) {
        $_SESSION['cart'][] = ['menu_id' => $menuId, 'jumlah' => $jumlah];
    }
}

// Ubah jumlah item di keranjang
if (isset($_POST['update_quantity'])) {
    $menuId = (int) $_POST['menu_id'];
    $jumlah = max(1, (int) $_POST['jumlah']);

    foreach ($_SESSION['cart'] as &$item) {
        if ($item['menu_id'] === $menuId) {
            $item['jumlah'] = $jumlah;
            break;
        }
    }
    unset($item);
}

// Hapus item dari keranjang
if (isset($_POST['hapus_item'])) {
    $menuId = (int) $_POST['hapus_item'];
    $_SESSION['cart'] = array_values(array_filter(
        $_SESSION['cart'],
        fn($item) => $item['menu_id'] !== $menuId
    ));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { background: #f4f8f6; }
        .cart-card { border-radius: 22px; }
        .cart-item { border-radius: 18px; }
        .cart-item img { width: 100px; height: 100px; object-fit: cover; border-radius: 16px; }
        .cart-footer { border-radius: 20px; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm py-3">
    <div class="container">
        <a href="menu.php" class="text-success text-decoration-none">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
        <h5 class="mb-0 fw-bold">Keranjang Belanja</h5>
        <div></div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <div class="card p-4 shadow-sm cart-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-1">Keranjang Anda</h5>
                <p class="text-muted mb-0">Cek ulang pesananmu sebelum checkout.</p>
            </div>
            <span class="badge bg-success py-2 px-3"><?= count($_SESSION['cart']) ?> item</span>
        </div>

        <?php $total = 0; ?>
        <?php if (count($_SESSION['cart']) === 0): ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-box-open fa-2x mb-3"></i>
                <p class="mb-0">Keranjang masih kosong.</p>
                <a href="menu.php" class="btn btn-success btn-sm mt-3">Kembali ke menu</a>
            </div>
        <?php else: ?>
            <?php foreach ($_SESSION['cart'] as $item):
                $menuId = (int) $item['menu_id'];
                $query = mysqli_query($conn, "SELECT * FROM menu WHERE id='$menuId'");
                $menu = mysqli_fetch_assoc($query);
                if (!$menu) continue;
                $jumlah = max(1, (int) $item['jumlah']);
                $subtotal = $menu['harga'] * $jumlah;
                $total += $subtotal;
            ?>
                <div class="card mb-3 cart-item shadow-sm">
                    <div class="row g-0 align-items-center">
                        <div class="col-auto p-3">
                            <img src="../upload/<?= htmlspecialchars($menu['gambar']) ?>" alt="<?= htmlspecialchars($menu['nama_menu']) ?>">
                        </div>
                        <div class="col">
                            <div class="card-body py-3">
                                <h6 class="fw-semibold mb-1"><?= htmlspecialchars($menu['nama_menu']) ?></h6>
                                <p class="text-muted mb-1">Rp <?= number_format($menu['harga'], 0, ',', '.') ?> x <?= $jumlah ?></p>
                                <p class="fw-bold mb-2">Subtotal: Rp <?= number_format($subtotal, 0, ',', '.') ?></p>
                                <div class="d-flex align-items-center gap-2">
                                    <form method="POST" class="d-flex align-items-center gap-1 mb-0">
                                        <input type="hidden" name="menu_id" value="<?= (int) $menu['id'] ?>">
                                        <button type="submit" name="update_quantity" class="btn btn-sm btn-outline-secondary" title="Kurangi jumlah"
                                            onclick="const inp=this.form.querySelector('[name=jumlah]'); inp.value = Math.max(1, (parseInt(inp.value) || 1) - 1);">
                                            <i class="fa-solid fa-minus"></i>
                                        </button>
                                        <input type="number" name="jumlah" value="<?= $jumlah ?>" min="1" class="form-control form-control-sm text-center" style="width: 55px;" readonly>
                                        <button type="submit" name="update_quantity" class="btn btn-sm btn-outline-secondary" title="Tambah jumlah"
                                            onclick="const inp=this.form.querySelector('[name=jumlah]'); inp.value = (parseInt(inp.value) || 1) + 1;">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="mb-0">
                                        <button type="submit" name="hapus_item" value="<?= (int) $menu['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus dari keranjang">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="card p-3 mt-3 cart-footer shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-1">Total Pembayaran</h6>
                        <small class="text-muted">Termasuk biaya layanan</small>
                    </div>
                    <h5 class="text-success mb-0">Rp <?= number_format($total, 0, ',', '.') ?></h5>
                </div>
                <a href="checkout.php" class="btn btn-success w-100 py-2">Lanjut ke Checkout</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>