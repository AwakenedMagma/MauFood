<?php
include '../config/koneksi.php';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM menu WHERE id='$id'"));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { background: #eef6f1; }
        .detail-banner { height: 320px; object-fit: cover; border-bottom-left-radius: 32px; border-bottom-right-radius: 32px; }
        .detail-body { border-radius: 32px 32px 0 0; margin-top: -60px; }
        .tag-badge { border-radius: 16px; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm py-3 mb-0">
    <div class="container">
        <a href="menu.php" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-chevron-left"></i> Kembali
        </a>
        <h5 class="mb-0 fw-bold">Detail Menu</h5>
        <div></div>
    </div>
</nav>

<?php if (!$data): ?>
    <div class="container mt-5">
        <div class="alert alert-warning shadow-sm">
            Menu tidak ditemukan. <a href="menu.php" class="alert-link">Kembali ke daftar menu</a>.
        </div>
    </div>
<?php else: ?>
    <div class="position-relative">
        <img src="../upload/<?= htmlspecialchars($data['gambar']) ?>" class="w-100 detail-banner" alt="<?= htmlspecialchars($data['nama_menu']) ?>">
    </div>

    <div class="container bg-white shadow-sm detail-body px-4 py-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h3 class="fw-bold mb-1"><?= htmlspecialchars($data['nama_menu']) ?></h3>
                <p class="text-muted mb-2">Kategori: <?= htmlspecialchars($data['kategori']) ?></p>
            </div>
            <span class="badge bg-success px-3 py-2 tag-badge">Best Seller</span>
        </div>

        <div class="mb-4">
            <h4 class="text-success">Rp <?= number_format($data['harga'], 0, ',', '.') ?></h4>
            <p class="text-muted">Stok terbatas, segera pesan sebelum kehabisan.</p>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="fw-semibold">Deskripsi</h6>
                <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($data['deskripsi'] ?? 'Tidak ada deskripsi tersedia.')) ?></p>
            </div>
        </div>

        <form method="POST" action="keranjang.php" class="row g-3 align-items-end">
            <input type="hidden" name="menu_id" value="<?= $data['id'] ?>">
            <div class="col-auto flex-grow-1">
                <label for="jumlah" class="form-label">Jumlah</label>
                <input type="number" id="jumlah" name="jumlah" value="1" min="1" class="form-control">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-success btn-lg px-4">
                    <i class="fa-solid fa-cart-plus"></i> Tambah
                </button>
            </div>
        </form>

        <div class="mt-4">
            <small class="text-muted">Kamu bisa kembali ke halaman menu untuk menambahkan item lain.</small>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
