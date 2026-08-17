<?php
session_start();
include '../config/koneksi.php';
include 'rekomendasi.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelayan') {
    header('Location: ../auth/login.php');
    exit;
}

$pelayan_id = (int) $_SESSION['user']['id'];
$error = '';
$success = '';
$selected_member = null;

// Keranjang pesanan pelayan disimpan terpisah dari keranjang customer
if (!isset($_SESSION['cart_pelayan'])) {
    $_SESSION['cart_pelayan'] = [];
}

// Bersihkan entri keranjang yang formatnya tidak valid
$_SESSION['cart_pelayan'] = array_values(array_filter(
    $_SESSION['cart_pelayan'],
    fn($item) => is_array($item) && isset($item['menu_id'], $item['jumlah'])
));

// Ambil member dari session jika ada
if (isset($_SESSION['selected_member_id'])) {
    $member_id_sess = (int) $_SESSION['selected_member_id'];
    $member_query = mysqli_query($conn, "SELECT * FROM member WHERE id = $member_id_sess AND status = 'aktif'");
    if ($member_query && mysqli_num_rows($member_query) > 0) {
        $selected_member = mysqli_fetch_assoc($member_query);
    } else {
        unset($_SESSION['selected_member_id']);
    }
}

// Pilih member dari hasil pencarian
if (isset($_POST['select_member'])) {
    $_SESSION['selected_member_id'] = (int) $_POST['select_member'];
    header('Location: pesan.php');
    exit;
}

// Batalkan pilihan member
if (isset($_POST['clear_member'])) {
    unset($_SESSION['selected_member_id']);
    header('Location: pesan.php');
    exit;
}

// Tambah item ke keranjang
if (isset($_POST['tambah_item'])) {
    $menuId = (int) $_POST['menu_id'];
    $jumlah = max(1, min(999, (int) $_POST['jumlah']));

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

// Update quantity
if (isset($_POST['update_quantity'])) {
    $menuId = (int) $_POST['menu_id'];
    $jumlah = max(1, min(999, (int) $_POST['jumlah']));
    
    foreach ($_SESSION['cart_pelayan'] as &$item) {
        if ($item['menu_id'] === $menuId) {
            $item['jumlah'] = $jumlah;
            break;
        }
    }
    unset($item);
}

// Clear cart
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

// Gunakan deskripsi menu yang pernah diminta member ini sebelumnya
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

            if ($member_id) {
                mysqli_query($conn, "UPDATE member 
                    SET total_pesanan = total_pesanan + 1, 
                        total_pengeluaran = total_pengeluaran + $total 
                    WHERE id = $member_id");
            }

            $member_info = ($member_id && $selected_member)
                ? " (Member: " . htmlspecialchars($selected_member['nama']) . ")"
                : "";

            $_SESSION['cart_pelayan'] = [];
            unset($_SESSION['selected_member_id']);
            $selected_member = null;

            $success = "Pesanan meja " . htmlspecialchars($meja) . " berhasil dibuat!" . $member_info;
            $_POST = [];
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

// FITUR REKOMENDASI MACHINE LEARNING (API PYTHON)

$kategoriOptions = ['Makanan Utama', 'Camilan', 'Minuman'];
$bahanOptions = ['Nasi', 'Mie', 'Ayam', 'Daging', 'Kopi', 'Cokelat', 'Teh', 'Sayuran'];
$rasaOptions = ['Manis', 'Gurih', 'Pedas'];

$kategoriPilihan = isset($_POST['kategori_rekomendasi']) && $_POST['kategori_rekomendasi'] !== '' ? $_POST['kategori_rekomendasi'] : null;
$bahanPilihan    = isset($_POST['bahan_rekomendasi']) && $_POST['bahan_rekomendasi'] !== '' ? $_POST['bahan_rekomendasi'] : null;
$rasaPilihan     = isset($_POST['rasa_rekomendasi']) && $_POST['rasa_rekomendasi'] !== '' ? $_POST['rasa_rekomendasi'] : null;

$rekomendasiMenu = [];
if (isset($_POST['cari_rekomendasi'])) {
    $userId = 'U_BARU';
    $isPelangganBaru = true;

    if (isset($selected_member) && $selected_member) {
        $userId = 'U' . str_pad($selected_member['id'], 3, '0', STR_PAD_LEFT);
        $isPelangganBaru = false;
    }

    $rekomendasiMenu = rekomendasikanMenu($conn, $userId, $isPelangganBaru, $kategoriPilihan, $bahanPilihan, $rasaPilihan);
}

// Best Seller per Kategori
$bestSellerQuery = mysqli_query($conn, "
    SELECT m.*, COALESCE(SUM(CASE WHEN p.status = 'selesai' THEN dp.jumlah ELSE 0 END), 0) as total_terjual
    FROM menu m
    LEFT JOIN detail_pesanan dp ON dp.menu_id = m.id
    LEFT JOIN pesanan p ON p.id = dp.pesanan_id
    GROUP BY m.id
    ORDER BY m.kategori ASC, total_terjual DESC
");

$bestSellerPerKategori = [];
if ($bestSellerQuery) {
    while ($row = mysqli_fetch_assoc($bestSellerQuery)) {
        $kat = $row['kategori'];
        if ((int) $row['total_terjual'] > 0 && !isset($bestSellerPerKategori[$kat])) {
            $bestSellerPerKategori[$kat] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Pesanan Dine-In - Maufood</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
        @media (min-width: 1024px) { .cart-sticky { position: sticky; top: 5.5rem; } }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <nav class="bg-white/80 backdrop-blur-md shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] sticky top-0 z-50 border-b border-slate-100">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex-shrink-0 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 text-white flex items-center justify-center shadow-md shadow-green-500/20">
                        <i class="fa-solid fa-utensils text-sm"></i>
                    </div>
                    <h1 class="font-bold text-xl tracking-tight hidden sm:block">Mau<span class="text-green-500">food</span></h1>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="kelola_pesanan.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
                        <i class="fa-solid fa-list-check"></i> <span class="hidden md:inline">Pesanan</span>
                    </a>
                    <a href="daftar_member.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
                        <i class="fa-solid fa-users"></i> <span class="hidden md:inline">Member</span>
                    </a>
                    <a href="pesan_quick.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-amber-600 bg-amber-50 border border-amber-200 hover:bg-amber-100 transition-colors">
                        <i class="fa-solid fa-bolt"></i> <span class="hidden md:inline">Quick Order</span>
                    </a>
                    <div class="w-px h-6 bg-slate-200 mx-1"></div>
                    <a href="../auth/logout.php" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-rose-500 hover:bg-rose-50 hover:text-rose-600 transition-colors" title="Keluar">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-[1400px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col-reverse lg:flex-row gap-6">
        
        <div class="flex-grow space-y-6 w-full lg:w-auto">
            
            <?php if ($error): ?>
                <div class="bg-rose-50 text-rose-600 p-4 rounded-xl text-sm border border-rose-100 flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation text-lg"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl text-sm border border-emerald-100 flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-lg"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- Pilih Member -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 relative z-30">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 rounded-t-2xl flex items-center gap-2">
                    <i class="fa-solid fa-user-check text-emerald-500"></i>
                    <h2 class="font-semibold text-slate-800 text-sm">Pilih Member <span class="text-slate-400 font-normal">(Opsional)</span></h2>
                </div>
                <div class="p-5 relative">
                    <?php if ($selected_member): ?>
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-emerald-50/50 border border-emerald-100 rounded-xl p-4">
                            <div>
                                <h3 class="font-bold text-emerald-800 text-lg">#<?= (int) $selected_member['id'] ?> - <?= htmlspecialchars($selected_member['nama']) ?></h3>
                                <div class="flex items-center gap-2 text-emerald-600/80 text-sm mt-1">
                                    <i class="fa-solid fa-phone text-xs"></i> <?= htmlspecialchars($selected_member['nomor_telepon']) ?>
                                </div>
                            </div>
                            <form method="POST">
                                <button type="submit" name="clear_member" class="px-4 py-2 bg-white text-rose-500 border border-rose-200 rounded-lg text-sm font-medium hover:bg-rose-50 transition-colors shadow-sm">
                                    <i class="fa-solid fa-times mr-1"></i> Ganti
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                            </div>
                            <input type="text" id="memberSearch" class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all" placeholder="Cari ID Member, Nama, atau No. Telp...">
                            <div id="searchDropdown" class="hidden absolute top-full left-0 right-0 mt-2 bg-white border border-slate-200 shadow-xl rounded-xl max-h-60 overflow-y-auto z-50 custom-scroll py-2">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-wand-magic-sparkles text-indigo-500"></i>
                        <h2 class="font-semibold text-slate-800 text-sm">Cari Rekomendasi Menu</h2>
                    </div>
                    <?php if ($selected_member): ?>
                        <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-md font-medium"><i class="fa-solid fa-user"></i> Mode Pelanggan Lama (Hybrid)</span>
                    <?php else: ?>
                        <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-1 rounded-md font-medium"><i class="fa-solid fa-user-plus"></i> Mode Pelanggan Baru (CBF)</span>
                    <?php endif; ?>
                </div>
                <div class="p-5">
                    <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wider">Kategori</label>
                            <select name="kategori_rekomendasi" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($kategoriOptions as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>" <?= $kategoriPilihan === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wider">Bahan Baku</label>
                            <select name="bahan_rekomendasi" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">Semua Bahan</option>
                                <?php foreach ($bahanOptions as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>" <?= $bahanPilihan === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wider">Rasa</label>
                            <select name="rasa_rekomendasi" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">Semua Rasa</option>
                                <?php foreach ($rasaOptions as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>" <?= $rasaPilihan === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" name="cari_rekomendasi" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-medium py-2.5 px-4 rounded-xl text-sm transition-colors shadow-sm">
                                Cari Rekomendasi
                            </button>
                        </div>
                    </form>

                    <?php if (isset($_POST['cari_rekomendasi'])): ?>
                        <div class="w-full h-px bg-slate-100 my-5"></div>
                        
                        <?php if (count($rekomendasiMenu) === 0): ?>
                            <div class="text-center py-4 text-slate-500 text-sm">
                                <i class="fa-solid fa-server text-2xl mb-2 text-slate-300 block"></i>
                                Tidak ada rekomendasi yang sesuai filter atau Server API mati.
                            </div>
                        <?php else: ?>
                            <h3 class="text-sm font-semibold text-indigo-600 mb-3"><i class="fa-solid fa-check mr-1"></i> <?= count($rekomendasiMenu) ?> Rekomendasi Teratas</h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-5">
                                <?php foreach ($rekomendasiMenu as $row): ?>
                                    <form method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md hover:border-indigo-200 transition-all group flex flex-col">
                                        <input type="hidden" name="menu_id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="kategori_rekomendasi" value="<?= htmlspecialchars($kategoriPilihan ?? '') ?>">
                                        <input type="hidden" name="bahan_rekomendasi" value="<?= htmlspecialchars($bahanPilihan ?? '') ?>">
                                        <input type="hidden" name="rasa_rekomendasi" value="<?= htmlspecialchars($rasaPilihan ?? '') ?>">
                                        <input type="hidden" name="cari_rekomendasi" value="1">
                                        
                                        <div class="h-32 bg-slate-100 overflow-hidden relative">
                                            <!-- PERBAIKAN: LENCANA (BADGE) AI DIKEMBALIKAN -->
                                            <div class="absolute top-2 left-2 z-10 bg-indigo-600/90 backdrop-blur-sm text-white text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 max-w-[90%]">
                                                <i class="fa-solid fa-microchip"></i> <span class="truncate"><?= htmlspecialchars($row['metode_rekomendasi'] ?? '') ?></span>
                                            </div>
                                            <div class="absolute bottom-2 right-2 z-10 bg-white/90 backdrop-blur-sm text-indigo-700 text-[10px] font-bold px-2 py-1 rounded">
                                                Match: <?= round((float) ($row['skor_rekomendasi'] ?? 0) * 100) ?>%
                                            </div>

                                            <img src="../upload/<?= htmlspecialchars($row['gambar']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="<?= htmlspecialchars($row['nama_menu']) ?>">
                                        </div>
                                        <div class="p-3.5 flex flex-col flex-grow">
                                            <h4 class="font-semibold text-slate-800 text-sm mb-1 line-clamp-2 leading-tight flex-grow"><?= htmlspecialchars($row['nama_menu']) ?></h4>
                                            <div class="font-bold text-emerald-600 text-[15px] mb-3">Rp <?= number_format($row['harga'], 0, ',', '.') ?></div>
                                            <div class="flex gap-2 mt-auto">
                                                <input type="number" name="jumlah" value="1" min="1" class="w-1/3 bg-slate-50 border border-slate-200 rounded-lg text-center text-sm font-medium focus:outline-none focus:border-emerald-500">
                                                <button type="submit" name="tambah_item" class="w-2/3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg py-1.5 text-sm font-semibold transition-colors flex items-center justify-center gap-1.5 shadow-sm shadow-emerald-500/20">
                                                    <i class="fa-solid fa-plus text-xs"></i> Tambah
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($bestSellerPerKategori) > 0): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                    <i class="fa-solid fa-fire text-orange-500"></i>
                    <h2 class="font-semibold text-slate-800 text-sm">Menu Terlaris per Kategori</h2>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-5">
                        <?php foreach ($bestSellerPerKategori as $kat => $menuBS): ?>
                            <form method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md hover:border-orange-200 transition-all group flex flex-col relative">
                                <input type="hidden" name="menu_id" value="<?= (int) $menuBS['id'] ?>">
                                <div class="absolute top-2 left-2 z-10 bg-gradient-to-r from-orange-400 to-orange-500 text-white text-[10px] font-bold px-2 py-1 rounded-md shadow-sm flex items-center gap-1 uppercase tracking-wider">
                                    <i class="fa-solid fa-crown"></i> #1 <?= htmlspecialchars($kat) ?>
                                </div>
                                <div class="h-32 bg-slate-100 overflow-hidden relative">
                                    <img src="../upload/<?= htmlspecialchars($menuBS['gambar']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="<?= htmlspecialchars($menuBS['nama_menu']) ?>">
                                </div>
                                <div class="p-3.5 flex flex-col flex-grow">
                                    <h4 class="font-semibold text-slate-800 text-sm mb-1 line-clamp-2 leading-tight"><?= htmlspecialchars($menuBS['nama_menu']) ?></h4>
                                    <div class="text-[11px] font-medium text-orange-500 mb-2 flex items-center gap-1"><i class="fa-solid fa-chart-line"></i> <?= (int) $menuBS['total_terjual'] ?> Terjual</div>
                                    <div class="font-bold text-emerald-600 text-[15px] mb-3 mt-auto">Rp <?= number_format($menuBS['harga'], 0, ',', '.') ?></div>
                                    <div class="flex gap-2">
                                        <input type="number" name="jumlah" value="1" min="1" class="w-1/3 bg-slate-50 border border-slate-200 rounded-lg text-center text-sm font-medium focus:outline-none focus:border-emerald-500">
                                        <button type="submit" name="tambah_item" class="w-2/3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg py-1.5 text-sm font-semibold transition-colors flex items-center justify-center gap-1.5 shadow-sm shadow-emerald-500/20">
                                            <i class="fa-solid fa-plus text-xs"></i> Tambah
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-8">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                    <i class="fa-solid fa-book-open text-blue-500"></i>
                    <h2 class="font-semibold text-slate-800 text-sm">Semua Menu</h2>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-5">
                        <?php while ($menu = mysqli_fetch_assoc($dataMenu)): ?>
                            <form method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md transition-all group flex flex-col">
                                <input type="hidden" name="menu_id" value="<?= (int) $menu['id'] ?>">
                                <div class="h-32 bg-slate-100 overflow-hidden relative">
                                    <img src="../upload/<?= htmlspecialchars($menu['gambar']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="<?= htmlspecialchars($menu['nama_menu']) ?>">
                                </div>
                                <div class="p-3.5 flex flex-col flex-grow">
                                    <h4 class="font-semibold text-slate-800 text-sm mb-1 line-clamp-2 leading-tight flex-grow"><?= htmlspecialchars($menu['nama_menu']) ?></h4>
                                    <div class="font-bold text-emerald-600 text-[15px] mb-3">Rp <?= number_format($menu['harga'], 0, ',', '.') ?></div>
                                    <div class="flex gap-2 mt-auto">
                                        <input type="number" name="jumlah" value="1" min="1" class="w-1/3 bg-slate-50 border border-slate-200 rounded-lg text-center text-sm font-medium focus:outline-none focus:border-emerald-500">
                                        <button type="submit" name="tambah_item" class="w-2/3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg py-1.5 text-sm font-semibold transition-colors flex items-center justify-center gap-1.5 shadow-sm shadow-emerald-500/20">
                                            <i class="fa-solid fa-plus text-xs"></i> Tambah
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan (Keranjang) -->
        <div class="w-full lg:w-[380px] flex-shrink-0">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden cart-sticky flex flex-col max-h-[calc(100vh-5rem)]">
                
                <!-- Cart Header -->
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-basket-shopping text-emerald-500"></i>
                        <h2 class="font-semibold text-slate-800 text-sm">Keranjang</h2>
                    </div>
                    <?php if (count($cartDetail) > 0): ?>
                        <span class="bg-rose-500 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-sm animate-pulse">
                            <?= count($cartDetail) ?> Item
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Cart Body (Items) -->
                <div class="flex-1 overflow-y-auto min-h-0 custom-scroll p-4 bg-slate-50/30">
                    <?php if ($selected_member): ?>
                        <div class="bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-medium px-3 py-2 rounded-lg mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-user-check"></i> Pesanan Member: <?= htmlspecialchars($selected_member['nama']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (count($cartDetail) === 0): ?>
                        <div class="text-center py-10">
                            <div class="w-16 h-16 bg-slate-100 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fa-solid fa-cart-arrow-down text-3xl"></i>
                            </div>
                            <p class="text-slate-500 font-medium text-sm">Keranjang masih kosong</p>
                            <p class="text-slate-400 text-xs mt-1">Silakan pilih menu di samping</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($cartDetail as $item): ?>
                                <div class="bg-white border border-slate-100 p-3 rounded-xl shadow-sm flex gap-3 hover:border-emerald-200 transition-colors">
                                    <img src="../upload/<?= htmlspecialchars($item['menu']['gambar']) ?>" class="w-14 h-14 object-cover rounded-lg bg-slate-100 flex-shrink-0" alt="">
                                    <div class="flex-grow min-w-0 flex flex-col justify-between">
                                        <div class="flex justify-between items-start gap-2">
                                            <h4 class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($item['menu']['nama_menu']) ?></h4>
                                            <form method="POST" class="flex-shrink-0">
                                                <button type="submit" name="hapus_item" value="<?= (int) $item['menu']['id'] ?>" class="text-slate-300 hover:text-rose-500 transition-colors p-1 -mt-1 -mr-1 rounded">
                                                    <i class="fa-solid fa-trash-can text-sm"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="flex justify-between items-center mt-1">
                                            <span class="text-emerald-600 font-semibold text-sm">Rp <?= number_format($item['menu']['harga'], 0, ',', '.') ?></span>
                                            
                                            <form method="POST" class="flex items-center gap-1 bg-slate-50 border border-slate-200 rounded-md p-0.5">
                                                <input type="hidden" name="menu_id" value="<?= (int) $item['menu']['id'] ?>">
                                                <button type="submit" name="update_quantity" class="w-6 h-6 flex items-center justify-center text-slate-500 hover:bg-slate-200 hover:text-slate-700 rounded transition-colors" onclick="const inp=this.form.querySelector('[name=jumlah]'); let val=parseInt(inp.value)||1; inp.value = val > 1 ? val - 1 : 1;">
                                                    <i class="fa-solid fa-minus text-[10px]"></i>
                                                </button>
                                                <input type="number" name="jumlah" value="<?= (int) $item['jumlah'] ?>" class="w-6 text-center text-xs font-semibold bg-transparent border-none focus:outline-none text-slate-700 p-0 pointer-events-none" readonly>
                                                <button type="submit" name="update_quantity" class="w-6 h-6 flex items-center justify-center text-slate-500 hover:bg-emerald-100 hover:text-emerald-600 rounded transition-colors" onclick="const inp=this.form.querySelector('[name=jumlah]'); inp.value = (parseInt(inp.value)||1) + 1;">
                                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                                </button>
                                            </form>
                                        </div>

                                        <!-- PERBAIKAN: FITUR CATATAN MENU DARI PELAYAN -->
                                        <form method="POST" class="flex items-center gap-1.5 mt-2">
                                            <input type="hidden" name="menu_id" value="<?= (int) $item['menu']['id'] ?>">
                                            <input type="text" name="catatan" maxlength="255" placeholder="Catatan (mis. tidak pedas)"
                                                value="<?= htmlspecialchars($item['catatan']) ?>"
                                                class="flex-grow min-w-0 text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                            <button type="submit" name="update_catatan" class="flex-shrink-0 w-7 h-7 flex items-center justify-center text-emerald-600 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors" title="Simpan catatan">
                                                <i class="fa-solid fa-check text-xs"></i>
                                            </button>
                                        </form>
                                        <?php if ($selected_member): ?>
                                            <form method="POST" class="mt-1.5">
                                                <button type="submit" name="gunakan_deskripsi_sebelumnya" value="<?= (int) $item['menu']['id'] ?>" class="text-[11px] text-indigo-600 hover:text-indigo-700 font-medium inline-flex items-center gap-1">
                                                    <i class="fa-solid fa-clock-rotate-left"></i> Gunakan deskripsi sebelumnya
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <!-- END CATATAN -->

                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($cartDetail) > 0): ?>
                <div class="p-4 bg-white border-t border-slate-100 flex-shrink-0">
                    <div class="bg-emerald-500 text-white p-4 rounded-xl mb-4 shadow-sm shadow-emerald-500/20">
                        <div class="flex justify-between text-emerald-50 text-sm mb-1.5">
                            <span>Subtotal</span>
                            <span>Rp <?= number_format($cartTotal, 0, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between text-emerald-50 text-sm mb-3">
                            <span>PPN (0%)</span>
                            <span>Rp 0</span>
                        </div>
                        <div class="w-full h-px bg-emerald-400 mb-3"></div>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-emerald-100 text-sm">Total Bayar</span>
                            <span class="font-bold text-xl">Rp <?= number_format($cartTotal, 0, ',', '.') ?></span>
                        </div>
                    </div>

                    <div class="flex gap-2 mb-4">
                        <form method="POST" class="w-full">
                            <button type="submit" name="clear_cart" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-600 font-medium py-2.5 rounded-xl text-sm transition-colors border border-slate-200">
                                <i class="fa-solid fa-broom mr-1"></i> Kosongkan
                            </button>
                        </form>
                    </div>

                    <form method="POST" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nomor Meja Pelanggan</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-hashtag text-slate-400"></i>
                                </div>
                                <input type="text" name="meja" placeholder="Contoh: A1, B3..." class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 text-slate-800 font-medium text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all uppercase" required>
                            </div>
                        </div>
                        <button type="submit" name="submit_pesanan" class="w-full bg-gradient-to-r from-emerald-500 to-green-500 hover:from-emerald-600 hover:to-green-600 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-emerald-500/30 transform transition-all hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <i class="fa-solid fa-check-circle text-lg"></i> Proses Pesanan
                        </button>
                    </form>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <script>
        const memberSearch = document.getElementById('memberSearch');
        const searchDropdown = document.getElementById('searchDropdown');
        let searchTimeout;

        memberSearch?.addEventListener('input', function () {
            const query = this.value.trim();
            clearTimeout(searchTimeout);

            if (query.length < 1) {
                searchDropdown.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`api_member.php?action=search&q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        searchDropdown.classList.remove('hidden');
                        if (data.success && data.data.length > 0) {
                            searchDropdown.innerHTML = data.data.map(member => `
                                <div class="px-4 py-2.5 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0 transition-colors" onclick="selectMember(${member.id})">
                                    <div class="font-semibold text-slate-800 text-sm">#${member.id} - ${member.nama}</div>
                                    <div class="text-xs text-slate-500 mt-0.5"><i class="fa-solid fa-phone text-[10px]"></i> ${member.nomor_telepon} &bull; ${member.total_pesanan} Pesanan</div>
                                </div>
                            `).join('');
                        } else {
                            searchDropdown.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500 text-center">Tidak ada member ditemukan</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        searchDropdown.classList.remove('hidden');
                        searchDropdown.innerHTML = '<div class="px-4 py-3 text-sm text-rose-500 text-center">Terjadi kesalahan koneksi</div>';
                    });
            }, 300);
        });

        function selectMember(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="select_member" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }

        document.addEventListener('click', function (event) {
            if (searchDropdown && !event.target.closest('#memberSearch') && !event.target.closest('#searchDropdown')) {
                searchDropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>