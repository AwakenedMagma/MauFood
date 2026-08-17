<?php
session_start();
include '../config/koneksi.php';
include 'rekomendasi.php';
include '../config/rekomendasi_ml_service.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelayan') {
    header('Location: ../auth/login.php');
    exit;
}

$pelayan_id = (int) $_SESSION['user']['id'];
$error = '';
$selected_member = null;

// Keranjang pesanan pelayan disimpan terpisah dari keranjang customer
if (!isset($_SESSION['cart_pelayan'])) {
    $_SESSION['cart_pelayan'] = [];
}

// Ambil member dari session jika ada
if (isset($_SESSION['selected_member_id'])) {
    $member_id = (int) $_SESSION['selected_member_id'];
    $member_query = mysqli_query($conn, "SELECT * FROM member WHERE id = $member_id AND status = 'aktif'");
    if (mysqli_num_rows($member_query) > 0) {
        $selected_member = mysqli_fetch_assoc($member_query);
    }
}

// Pilih member dari hasil pencarian
if (isset($_POST['select_member'])) {
    $_SESSION['selected_member_id'] = (int) $_POST['select_member'];
    header('Location: pesan_dengan_member.php');
    exit;
}

// Clear member selection
if (isset($_POST['clear_member'])) {
    unset($_SESSION['selected_member_id']);
    $selected_member = null;
    header('Location: pesan_dengan_member.php');
    exit;
}

// Tambah item ke keranjang
if (isset($_POST['tambah_item'])) {
    $menuId = (int) $_POST['menu_id'];
    $jumlah = max(1, (int) $_POST['jumlah']);

    // Jika menu sudah ada di keranjang, tambahkan jumlahnya
    $sudahAda = false;
    foreach ($_SESSION['cart_pelayan'] as &$item) {
        if ($item['menu_id'] === $menuId) {
            $item['jumlah'] += $jumlah;
            $sudahAda = true;
            break;
        }
    }
    unset($item);

    if (!$sudahAda) {
        $_SESSION['cart_pelayan'][] = ['menu_id' => $menuId, 'jumlah' => $jumlah, 'catatan' => ''];
    }
}

// Hapus item dari keranjang
if (isset($_POST['hapus_item'])) {
    $menuId = (int) $_POST['hapus_item'];
    $_SESSION['cart_pelayan'] = array_values(array_filter(
        $_SESSION['cart_pelayan'],
        fn($item) => $item['menu_id'] !== $menuId
    ));
}

// Update jumlah item di keranjang
if (isset($_POST['update_quantity'])) {
    $menuId = (int) $_POST['menu_id'];
    $jumlah = max(1, (int) $_POST['jumlah']);

    foreach ($_SESSION['cart_pelayan'] as &$item) {
        if ($item['menu_id'] === $menuId) {
            $item['jumlah'] = $jumlah;
            break;
        }
    }
    unset($item);
}

// Kosongkan keranjang
if (isset($_POST['clear_cart'])) {
    $_SESSION['cart_pelayan'] = [];
}

// Simpan deskripsi/catatan menu untuk salah satu item di keranjang
if (isset($_POST['update_catatan'])) {
    $menuId = (int) $_POST['menu_id'];
    $catatan = trim($_POST['catatan'] ?? '');
    if (mb_strlen($catatan) > 255) {
        $catatan = mb_substr($catatan, 0, 255);
    }

    foreach ($_SESSION['cart_pelayan'] as &$item) {
        if ($item['menu_id'] === $menuId) {
            $item['catatan'] = $catatan;
            break;
        }
    }
    unset($item);
}

// Gunakan deskripsi menu yang pernah diminta member ini sebelumnya (agar tidak menulis ulang)
if (isset($_POST['gunakan_deskripsi_sebelumnya'])) {
    $menuId = (int) $_POST['gunakan_deskripsi_sebelumnya'];
    $member_id_note = isset($_SESSION['selected_member_id']) ? (int) $_SESSION['selected_member_id'] : null;

    if ($member_id_note) {
        $catatanQuery = mysqli_query($conn, "SELECT dp.catatan
            FROM detail_pesanan dp
            JOIN pesanan p ON p.id = dp.pesanan_id
            WHERE p.member_id = '$member_id_note' AND dp.menu_id = '$menuId'
              AND dp.catatan IS NOT NULL AND dp.catatan != ''
            ORDER BY p.tanggal DESC
            LIMIT 1");
        $catatanRow = $catatanQuery ? mysqli_fetch_assoc($catatanQuery) : null;

        if ($catatanRow) {
            foreach ($_SESSION['cart_pelayan'] as &$item) {
                if ($item['menu_id'] === $menuId) {
                    $item['catatan'] = $catatanRow['catatan'];
                    break;
                }
            }
            unset($item);
        } else {
            $error = 'Member ini belum pernah memberi deskripsi untuk menu tersebut.';
        }
    }
}

// Submit pesanan dine-in
if (isset($_POST['submit_pesanan'])) {
    $meja = trim($_POST['meja'] ?? '');
    $member_id = isset($_SESSION['selected_member_id']) ? (int) $_SESSION['selected_member_id'] : null;

    if ($meja === '') {
        $error = 'Nomor meja wajib diisi.';
    } elseif (count($_SESSION['cart_pelayan']) === 0) {
        $error = 'Belum ada menu yang dipilih.';
    } else {
        $meja_escaped = mysqli_real_escape_string($conn, $meja);

        $total = 0;
        $itemsValid = [];
        foreach ($_SESSION['cart_pelayan'] as $item) {
            $menuId = (int) $item['menu_id'];
            $query = mysqli_query($conn, "SELECT * FROM menu WHERE id='$menuId'");
            $menu = mysqli_fetch_assoc($query);
            if (!$menu) continue;
            $jumlah = max(1, (int) $item['jumlah']);
            $subtotal = $menu['harga'] * $jumlah;
            $total += $subtotal;
            $catatanItem = trim($item['catatan'] ?? '');
            $itemsValid[] = ['menu' => $menu, 'jumlah' => $jumlah, 'subtotal' => $subtotal, 'catatan' => $catatanItem];
        }

        if (count($itemsValid) === 0) {
            $error = 'Menu yang dipilih tidak valid.';
        } else {
            // Insert pesanan dengan member_id jika ada
            $member_col = $member_id ? "member_id, " : "";
            $member_val = $member_id ? "'$member_id', " : "";

            mysqli_query($conn, "INSERT INTO pesanan (user_id, {$member_col}total, status, meja, tipe, dibuat_oleh)
                VALUES ('$pelayan_id', {$member_val}'$total', 'pending', '$meja_escaped', 'dine-in', '$pelayan_id')");
            $pesanan_id = mysqli_insert_id($conn);

            foreach ($itemsValid as $item) {
                $menuId = (int) $item['menu']['id'];
                $jumlah = (int) $item['jumlah'];
                $subtotal = (float) $item['subtotal'];
                $catatanEscaped = mysqli_real_escape_string($conn, $item['catatan']);
                mysqli_query($conn, "INSERT INTO detail_pesanan (pesanan_id, menu_id, jumlah, subtotal, catatan)
                    VALUES ('$pesanan_id', '$menuId', '$jumlah', '$subtotal', '$catatanEscaped')");
            }

            // Update total pesanan member
            if ($member_id) {
                mysqli_query($conn, "UPDATE member 
                    SET total_pesanan = total_pesanan + 1, 
                        total_pengeluaran = total_pengeluaran + $total 
                    WHERE id = $member_id");
            }

            unset($_SESSION['cart_pelayan']);
            unset($_SESSION['selected_member_id']);
            
            $member_info = $member_id ? " (Member: {$selected_member['nama']})" : "";
            echo "<script>alert('Pesanan meja $meja berhasil dibuat!$member_info');window.location='pesan_dengan_member.php';</script>";
            exit;
        }
    }
}

// Ambil daftar menu untuk ditampilkan
$dataMenu = mysqli_query($conn, "SELECT * FROM menu ORDER BY kategori, nama_menu");

// Hitung total keranjang saat ini untuk ditampilkan
$cartTotal = 0;
$cartDetail = [];
foreach ($_SESSION['cart_pelayan'] as $item) {
    $menuId = (int) $item['menu_id'];
    $query = mysqli_query($conn, "SELECT * FROM menu WHERE id='$menuId'");
    $menu = mysqli_fetch_assoc($query);
    if (!$menu) continue;
    $jumlah = max(1, (int) $item['jumlah']);
    $subtotal = $menu['harga'] * $jumlah;
    $cartTotal += $subtotal;
    $cartDetail[] = ['menu' => $menu, 'jumlah' => $jumlah, 'subtotal' => $subtotal, 'catatan' => $item['catatan'] ?? ''];
}

// Rekomendasi menu
$kategoriOptions = ['main course', 'appetizer', 'soup', 'side dish', 'dessert', 'drinking', 'coffe'];
$rasaOptions = ['manis', 'pedas', 'gurih'];

$kategoriPilihan = isset($_POST['kategori_rekomendasi']) && $_POST['kategori_rekomendasi'] !== ''
    ? $_POST['kategori_rekomendasi'] : null;
$rasaPilihan = isset($_POST['rasa_rekomendasi']) && $_POST['rasa_rekomendasi'] !== ''
    ? $_POST['rasa_rekomendasi'] : null;

$menuIdKeranjangSaatIni = array_map(fn($item) => (int) $item['menu']['id'], $cartDetail);

$rekomendasiMenu = [];
if (isset($_POST['cari_rekomendasi']) && ($kategoriPilihan !== null || $rasaPilihan !== null)) {
    // Dropdown "Rekomendasi Menu" ini tidak terikat identitas pelanggan tertentu,
    // jadi selalu dikirim sebagai jalur cold-start CBF murni (is_pelanggan_baru=true,
    // user_id kosong), murni berdasarkan kategori/rasa yang dipilih pelayan.
    // CATATAN: signature rekomendasikanMenu() di rekomendasi.php adalah
    // ($conn, $userId, $isPelangganBaru, $kategori, $bahan, $rasa) — TIDAK punya
    // parameter untuk mengecualikan menu yang sudah ada di keranjang, sehingga
    // pengecualian dilakukan manual pada array hasil di bawah ini.
    $rekomendasiMenu = rekomendasikanMenu($conn, '', true, $kategoriPilihan, $rasaPilihan, '');
    if (!empty($menuIdKeranjangSaatIni)) {
        $rekomendasiMenu = array_values(array_filter(
            $rekomendasiMenu,
            fn($row) => !in_array((int) $row['id'], $menuIdKeranjangSaatIni, true)
        ));
    }
}

// Rekomendasi berbasis model ML (hybrid filtering) — hanya jika ada member terpilih,
// karena rekomendasi ini dipersonalisasi per pelanggan. Fungsi ini sudah menangani
// sendiri fallback ke menu terpopuler kalau model ML tidak tersedia/tidak nyambung.
$rekomendasiAI = null;
if ($selected_member !== null) {
    $rekomendasiAI = getRekomendasiUntukMember($conn, (int) $selected_member['id'], 6, $menuIdKeranjangSaatIni);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Pesanan - Pelayan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { background: #eef6f1; }
        .menu-mini { border-radius: 16px; }
        .menu-mini img { width: 60px; height: 60px; object-fit: cover; border-radius: 12px; }
        .cart-box { border-radius: 20px; position: sticky; top: 1rem; }
        .rekomendasi-box { border-radius: 20px; background: #f4fbf7; }
        .member-badge { border-radius: 20px; padding: 8px 16px; background: #e7f5f0; }
        .search-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
        }
        .search-dropdown.show { display: block; }
        .search-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        .search-item:hover { background: #f8f9fa; }
    </style>
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm py-3">
    <div class="container">
        <a href="kelola_pesanan.php" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-list-check"></i> Kelola Pesanan
        </a>
        <h5 class="mb-0 fw-bold">Input Pesanan Dine-In</h5>
        <div>
            <a href="daftar_member.php" class="btn btn-outline-info btn-sm me-2">
                <i class="fa-solid fa-user-plus"></i> Kelola Member
            </a>
            <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- MEMBER SELECTION CARD -->
    <div class="card p-3 shadow-sm mb-4" style="border-radius: 16px; background: linear-gradient(135deg, #e7f5f0 0%, #f0fbf8 100%);">
        <div class="row align-items-center">
            <div class="col-md-8">
                <?php if ($selected_member): ?>
                    <h6 class="fw-bold mb-2">
                        <i class="fa-solid fa-user-check text-success"></i> Member Terpilih
                    </h6>
                    <div class="member-badge">
                        <strong>#<?= $selected_member['id'] ?> - <?= htmlspecialchars($selected_member['nama']) ?></strong>
                        <br>
                        <small class="text-muted">
                            <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($selected_member['nomor_telepon']) ?>
                        </small>
                    </div>
                <?php else: ?>
                    <h6 class="fw-bold mb-2">
                        <i class="fa-solid fa-magnifying-glass text-success"></i> Cari Member (Opsional)
                    </h6>
                    <div class="position-relative">
                        <input type="text" id="memberSearch" class="form-control form-control-sm" 
                               placeholder="Masukkan ID Member, Nama, atau Nomor Telepon...">
                        <div id="searchDropdown" class="search-dropdown"></div>
                    </div>
                    <small class="text-muted d-block mt-1">Pencarian otomatis akan menampilkan member yang sesuai</small>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($selected_member): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="clear_member" class="btn btn-sm btn-outline-danger">
                            <i class="fa-solid fa-times"></i> Ganti Member
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- REKOMENDASI AI (Hybrid Filtering ML) -->
    <?php if ($rekomendasiAI !== null && !empty($rekomendasiAI['items'])): ?>
    <div class="card p-3 shadow-sm mb-4 rekomendasi-box" style="background:#f3f0fb;">
        <h6 class="fw-semibold mb-1">
            <i class="fa-solid fa-robot text-primary"></i> Rekomendasi AI untuk <?= htmlspecialchars($selected_member['nama']) ?>
            <?php if ($rekomendasiAI['sumber_utama'] === 'ai'): ?>
                <span class="badge bg-primary ms-1">Model ML</span>
            <?php elseif ($rekomendasiAI['sumber_utama'] === 'campuran'): ?>
                <span class="badge bg-info text-dark ms-1">ML + Populer</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-1">Menu Populer</span>
            <?php endif; ?>
        </h6>
        <?php if (!empty($rekomendasiAI['catatan'])): ?>
            <p class="text-muted small mb-2"><i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($rekomendasiAI['catatan']) ?></p>
        <?php endif; ?>
        <div class="row g-2">
            <?php foreach ($rekomendasiAI['items'] as $row): ?>
                <div class="col-12 col-md-6">
                    <form method="POST" class="card p-2 menu-mini shadow-sm h-100 border-primary">
                        <input type="hidden" name="menu_id" value="<?= (int) $row['id'] ?>">
                        <div class="d-flex align-items-center gap-2">
                            <img src="../upload/<?= htmlspecialchars($row['gambar']) ?>" alt="<?= htmlspecialchars($row['nama_menu']) ?>" onerror="this.style.visibility='hidden'">
                            <div class="flex-grow-1">
                                <p class="mb-1 fw-semibold small"><?= htmlspecialchars($row['nama_menu']) ?></p>
                                <p class="mb-1 text-muted small">
                                    Rp <?= number_format($row['harga'], 0, ',', '.') ?>
                                    <?php if ($row['sumber'] === 'ai'): ?>
                                        <span class="text-primary">· cocok <?= (float) $row['hybrid_score'] ?>%</span>
                                    <?php else: ?>
                                        <span class="text-muted">· terlaris</span>
                                    <?php endif; ?>
                                </p>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="jumlah" value="1" min="1" class="form-control" style="width: 50px;">
                                    <button class="btn btn-primary btn-sm" type="submit" name="tambah_item">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- REKOMENDASI MENU -->
    <div class="card p-3 shadow-sm mb-4 rekomendasi-box">
        <h6 class="fw-semibold mb-3"><i class="fa-solid fa-wand-magic-sparkles text-success"></i> Rekomendasi Menu</h6>
        <form method="POST" class="row gy-2 gx-2 align-items-end">
            <div class="col-12 col-md-4">
                <label for="kategori_rekomendasi" class="form-label small mb-1">Kategori Hidangan</label>
                <select id="kategori_rekomendasi" name="kategori_rekomendasi" class="form-select form-select-sm">
                    <option value="">-- Semua kategori --</option>
                    <?php foreach ($kategoriOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $kategoriPilihan === $opt ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucwords($opt)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label for="rasa_rekomendasi" class="form-label small mb-1">Rasa</label>
                <select id="rasa_rekomendasi" name="rasa_rekomendasi" class="form-select form-select-sm">
                    <option value="">-- Semua rasa --</option>
                    <?php foreach ($rasaOptions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $rasaPilihan === $opt ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucwords($opt)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <button type="submit" name="cari_rekomendasi" class="btn btn-success btn-sm w-100">
                    <i class="fa-solid fa-magnifying-glass"></i> Cari Rekomendasi
                </button>
            </div>
        </form>

        <?php if (isset($_POST['cari_rekomendasi'])): ?>
            <hr>
            <?php if (count($rekomendasiMenu) === 0): ?>
                <p class="text-muted small mb-0">Belum ada menu yang cocok dengan kriteria ini.</p>
            <?php else: ?>
                <p class="text-muted small mb-2">Menampilkan <?= count($rekomendasiMenu) ?> rekomendasi teratas:</p>
                <div class="row g-2">
                    <?php foreach ($rekomendasiMenu as $row): ?>
                        <div class="col-12 col-md-6">
                            <form method="POST" class="card p-2 menu-mini shadow-sm h-100 border-success">
                                <input type="hidden" name="menu_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="kategori_rekomendasi" value="<?= htmlspecialchars($kategoriPilihan ?? '') ?>">
                                <input type="hidden" name="rasa_rekomendasi" value="<?= htmlspecialchars($rasaPilihan ?? '') ?>">
                                <input type="hidden" name="cari_rekomendasi" value="1">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="../upload/<?= htmlspecialchars($row['gambar']) ?>" alt="<?= htmlspecialchars($row['nama_menu']) ?>">
                                    <div class="flex-grow-1">
                                        <p class="mb-1 fw-semibold small"><?= htmlspecialchars($row['nama_menu']) ?></p>
                                        <p class="mb-1 text-muted small">Rp <?= number_format($row['harga'], 0, ',', '.') ?></p>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="jumlah" value="1" min="1" class="form-control" style="width: 50px;">
                                            <button class="btn btn-success btn-sm" type="submit" name="tambah_item">
                                                <i class="fa-solid fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- KERANJANG -->
    <div class="card p-3 shadow-sm mb-4" style="border-radius: 16px;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">
                <i class="fa-solid fa-cart-shopping text-success"></i> Keranjang
                <?php if (count($cartDetail) > 0): ?>
                    <span class="badge bg-success ms-1"><?= count($cartDetail) ?> item</span>
                <?php endif; ?>
            </h6>
            <?php if (count($cartDetail) > 0): ?>
                <form method="POST" class="mb-0">
                    <button type="submit" name="clear_cart" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-broom"></i> Kosongkan
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (count($cartDetail) === 0): ?>
            <p class="text-muted small mb-0">Keranjang masih kosong. Tambahkan menu dari rekomendasi di atas.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        <?php foreach ($cartDetail as $item): ?>
                            <tr>
                                <td style="width: 56px;">
                                    <img src="../upload/<?= htmlspecialchars($item['menu']['gambar']) ?>" alt="" style="width: 48px; height: 48px; object-fit: cover; border-radius: 10px;">
                                </td>
                                <td>
                                    <div class="fw-semibold small"><?= htmlspecialchars($item['menu']['nama_menu']) ?></div>
                                    <div class="text-muted small">Rp <?= number_format($item['menu']['harga'], 0, ',', '.') ?></div>
                                </td>
                                <td style="width: 150px;">
                                    <form method="POST" class="d-flex align-items-center gap-1">
                                        <input type="hidden" name="menu_id" value="<?= (int) $item['menu']['id'] ?>">
                                        <button type="submit" name="update_quantity" class="btn btn-sm btn-outline-secondary" title="Kurangi"
                                            onclick="const inp=this.form.querySelector('[name=jumlah]'); inp.value = Math.max(1, (parseInt(inp.value) || 1) - 1);">
                                            <i class="fa-solid fa-minus"></i>
                                        </button>
                                        <input type="number" name="jumlah" value="<?= (int) $item['jumlah'] ?>" min="1" class="form-control form-control-sm text-center" style="width: 50px;" readonly>
                                        <button type="submit" name="update_quantity" class="btn btn-sm btn-outline-secondary" title="Tambah"
                                            onclick="const inp=this.form.querySelector('[name=jumlah]'); inp.value = (parseInt(inp.value) || 1) + 1;">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </form>
                                </td>
                                <td style="width: 130px;" class="text-end fw-semibold small">
                                    Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                                </td>
                                <td style="width: 40px;">
                                    <form method="POST" class="mb-0">
                                        <button type="submit" name="hapus_item" value="<?= (int) $item['menu']['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus dari keranjang">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <tr class="border-0">
                                <td></td>
                                <td colspan="4" class="pt-0 pb-2">
                                    <form method="POST" class="d-flex align-items-center gap-1">
                                        <input type="hidden" name="menu_id" value="<?= (int) $item['menu']['id'] ?>">
                                        <input type="text" name="catatan" maxlength="255" placeholder="Catatan menu (mis. tidak pedas, tanpa bawang)"
                                            value="<?= htmlspecialchars($item['catatan']) ?>"
                                            class="form-control form-control-sm">
                                        <button type="submit" name="update_catatan" class="btn btn-sm btn-outline-success flex-shrink-0" title="Simpan catatan">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    </form>
                                    <?php if ($selected_member): ?>
                                        <form method="POST" class="mt-1 mb-0">
                                            <button type="submit" name="gunakan_deskripsi_sebelumnya" value="<?= (int) $item['menu']['id'] ?>" class="btn btn-link btn-sm p-0 text-decoration-none">
                                                <i class="fa-solid fa-clock-rotate-left"></i> Gunakan deskripsi sebelumnya
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- FORM SUBMIT -->
    <form method="POST" class="card p-4 shadow-sm" style="border-radius: 16px;">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="meja" class="form-label fw-bold">Nomor Meja <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="meja" name="meja" 
                           placeholder="Contoh: A1, A2, B3, dll" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Total Pesanan</label>
                    <div class="alert alert-info mb-0">
                        <strong>Rp <?= number_format($cartTotal, 0, ',', '.') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" name="submit_pesanan" class="btn btn-success btn-lg w-100" style="border-radius: 12px;" <?= count($cartDetail) === 0 ? 'disabled' : '' ?>>
            <i class="fa-solid fa-check-circle"></i> Buat Pesanan
        </button>
    </form>
</div>

<!-- Hidden input untuk member ID -->
<input type="hidden" id="selectedMemberId" value="<?= $selected_member ? $selected_member['id'] : '' ?>">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Member search functionality
const memberSearch = document.getElementById('memberSearch');
const searchDropdown = document.getElementById('searchDropdown');
let searchTimeout;

memberSearch?.addEventListener('input', function() {
    const query = this.value.trim();
    
    clearTimeout(searchTimeout);
    
    if (query.length < 1) {
        searchDropdown.classList.remove('show');
        return;
    }

    searchTimeout = setTimeout(() => {
        fetch(`api_member.php?action=search&q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    searchDropdown.innerHTML = data.data.map(member => `
                        <div class="search-item" onclick="selectMember(${member.id}, '${member.nama.replace(/'/g, "\\'")}', '${member.nomor_telepon}')">
                            <strong>#${member.id} - ${member.nama}</strong><br>
                            <small class="text-muted">${member.nomor_telepon} | ${member.total_pesanan} pesanan</small>
                        </div>
                    `).join('');
                    searchDropdown.classList.add('show');
                } else {
                    searchDropdown.innerHTML = '<div class="search-item text-muted">Tidak ada member yang cocok</div>';
                    searchDropdown.classList.add('show');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                searchDropdown.innerHTML = '<div class="search-item text-danger">Terjadi kesalahan</div>';
                searchDropdown.classList.add('show');
            });
    }, 300);
});

function selectMember(id, nama, telepon) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="select_member" value="${id}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('#memberSearch') && !event.target.closest('#searchDropdown')) {
        searchDropdown.classList.remove('show');
    }
});
</script>

</body>
</html>
