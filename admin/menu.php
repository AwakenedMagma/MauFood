<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Fungsi menembak sinyal ke Railway untuk Retrain AI
function triggerRetrainRailway() {
    $url = 'https://maufood-api-python-production.up.railway.app/api/trigger-retrain';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    // Kirim password rahasia yang sama dengan di Python
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['secret' => 'rahasia_maufood_123']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    // Cukup tunggu 2 detik, karena Python memprosesnya di latar belakang
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
    curl_exec($ch);
    curl_close($ch);
}

$success = '';
$error = '';
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$menuToEdit = null;

// VOCABULARY MACHINE LEARNING (SINKRON DENGAN app.py)
$kategoriOptions = ['Makanan Utama', 'Camilan', 'Minuman'];
$bahanOptions    = ['Nasi', 'Mie', 'Ayam', 'Daging', 'Kopi', 'Cokelat', 'Teh', 'Sayuran'];
$rasaOptions     = ['Manis', 'Gurih', 'Pedas'];

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = (int) $_GET['id'];
    $item = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar FROM menu WHERE id='$deleteId'"));
    if ($item) {
        try {
            // Mencoba menghapus menu
            if (!empty($item['gambar']) && file_exists(__DIR__ . '/../upload/' . $item['gambar'])) {
            @unlink(__DIR__ . '/../upload/' . $item['gambar']);
        }
        triggerRetrainRailway(); // <--- TAMBAHKAN DI SINI
        header('Location: menu.php?success=Menu berhasil dihapus');
        exit;
        } catch (mysqli_sql_exception $e) {
            // Menangkap penolakan dari MySQL jika menu ada di riwayat pesanan
            $error = 'Menu ini tidak bisa dihapus karena sudah tercatat dalam riwayat pesanan pelanggan.';
        }
    }
}
if ($editId > 0) {
    $menuToEdit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM menu WHERE id='$editId'"));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_menu'])) {
    $namaMenu  = mysqli_real_escape_string($conn, trim($_POST['nama_menu']));
    $kategori  = trim($_POST['kategori'] ?? '');
    $bahan     = trim($_POST['bahan_baku'] ?? '');
    $rasa      = trim($_POST['rasa'] ?? '');
    $harga     = trim($_POST['harga']);
    $deskripsi = mysqli_real_escape_string($conn, trim($_POST['deskripsi']));
    $id        = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    // Validasi ketat sesuai kosa kata Machine Learning
    if ($namaMenu === '' || $kategori === '' || $harga === '') {
        $error = 'Nama, kategori, dan harga wajib diisi.';
    } elseif (!in_array($kategori, $kategoriOptions, true)) {
        $error = 'Kategori tidak valid. Silakan pilih dari daftar yang tersedia.';
    } elseif ($bahan !== '' && !in_array($bahan, $bahanOptions, true)) {
        $error = 'Bahan baku tidak valid. Silakan pilih dari daftar yang tersedia.';
    } elseif ($rasa !== '' && !in_array($rasa, $rasaOptions, true)) {
        $error = 'Rasa tidak valid. Silakan pilih dari daftar yang tersedia.';
    } elseif (!is_numeric($harga) || (float) $harga < 0) {
        $error = 'Harga harus berupa angka positif.';
    } else {
        $kategori = mysqli_real_escape_string($conn, $kategori);
        $bahanSql = $bahan === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $bahan) . "'";
        $rasaSql  = $rasa === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $rasa) . "'";
        $harga    = (int) $harga;
        $imageName = '';

        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($_FILES['gambar']['type'], $allowed, true)) {
                $error = 'Format gambar harus JPG, PNG, atau WEBP.';
            } else {
                $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                $imageName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $uploadDir = __DIR__ . '/../upload/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $destination = $uploadDir . $imageName;
                if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $destination)) {
                    $error = 'Gagal mengunggah gambar.';
                }
            }
        }

        if ($error === '') {
            if ($id > 0) {
                $updateSql = "UPDATE menu SET nama_menu='$namaMenu', kategori='$kategori', bahan_baku=$bahanSql, rasa=$rasaSql, harga='$harga', deskripsi='$deskripsi'";
                if ($imageName !== '') {
                    $oldItem = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar FROM menu WHERE id='$id'"));
                    if ($oldItem && !empty($oldItem['gambar']) && file_exists(__DIR__ . '/../upload/' . $oldItem['gambar'])) {
                        @unlink(__DIR__ . '/../upload/' . $oldItem['gambar']);
                    }
                    $updateSql .= ", gambar='$imageName'";
                }
                $updateSql .= " WHERE id='$id'";
                mysqli_query($conn, $updateSql);
            $success = 'Menu berhasil diperbarui.';
            triggerRetrainRailway(); // <--- TAMBAHKAN DI SINI
            header('Location: menu.php?success=' . urlencode($success));
            exit;
            } else {
                if ($imageName === '') {
                    $error = 'Gambar menu wajib diunggah.';
                } else {
mysqli_query($conn, "INSERT INTO menu (nama_menu, kategori, bahan_baku, rasa, harga, deskripsi, gambar) VALUES ('$namaMenu', '$kategori', $bahanSql, $rasaSql, '$harga', '$deskripsi', '$imageName')");
                $success = 'Menu baru berhasil ditambahkan.';
                triggerRetrainRailway(); // <--- TAMBAHKAN DI SINI
                header('Location: menu.php?success=' . urlencode($success));
                exit;
                }
            }
        }
    }
}

if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

$menuList = mysqli_query($conn, "SELECT * FROM menu ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - Admin Maufood</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts (Poppins) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
        @media (min-width: 1024px) { .form-sticky { position: sticky; top: 5.5rem; } }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <nav class="bg-white/80 backdrop-blur-md shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] sticky top-0 z-50 border-b border-slate-100">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Judul / Brand -->
                <div class="flex-shrink-0 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 text-white flex items-center justify-center shadow-md shadow-green-500/20">
                        <i class="fa-solid fa-utensils text-sm"></i>
                    </div>
                    <h1 class="font-bold text-xl tracking-tight hidden sm:block">Admin <span class="text-green-500">Maufood</span></h1>
                </div>

                <!-- Navigasi -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="dashboard.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
                        <i class="fa-solid fa-house"></i> <span class="hidden md:inline">Dashboard</span>
                    </a>
                    <a href="menu.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 transition-colors">
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

    <main class="flex-grow max-w-[1400px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Manajemen Menu</h2>
            <p class="text-slate-500 text-sm mt-1">Tambah, ubah, dan hapus item menu yang tampil di aplikasi Maufood.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl text-sm border border-emerald-100 mb-6 flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-check text-lg"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-rose-50 text-rose-600 p-4 rounded-xl text-sm border border-rose-100 mb-6 flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-lg"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row gap-8 items-start">
            
            <!-- Kolom Kiri: Daftar Menu (Tabel) -->
            <div class="flex-1 w-full bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-100 text-blue-600 rounded-lg">
                            <i class="fa-solid fa-list"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-lg">Daftar Menu Tersedia</h3>
                    </div>
                    <span class="text-xs font-semibold bg-white border border-slate-200 px-3 py-1.5 rounded-full text-slate-500 shadow-sm">
                        Total <?= mysqli_num_rows($menuList) ?> Item
                    </span>
                </div>
                
                <div class="overflow-x-auto custom-scroll">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-white border-b border-slate-100 text-slate-400 text-xs uppercase tracking-wider font-semibold">
                                <th class="px-6 py-4 w-16">ID</th>
                                <th class="px-6 py-4 w-24">Gambar</th>
                                <th class="px-6 py-4">Informasi Menu</th>
                                <th class="px-6 py-4">Kategori, Bahan & Rasa</th>
                                <th class="px-6 py-4">Harga</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php if (mysqli_num_rows($menuList) === 0): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 text-slate-300 mb-3">
                                            <i class="fa-solid fa-utensils text-2xl"></i>
                                        </div>
                                        <p class="text-slate-500 font-medium">Belum ada menu yang terdaftar.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($menu = mysqli_fetch_assoc($menuList)): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors group">
                                        <td class="px-6 py-4 font-bold text-slate-400">
                                            #<?= htmlspecialchars($menu['id']) ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if (!empty($menu['gambar'])): ?>
                                                <div class="w-14 h-14 rounded-xl overflow-hidden shadow-sm border border-slate-100">
                                                    <img src="../upload/<?= htmlspecialchars($menu['gambar']) ?>" alt="<?= htmlspecialchars($menu['nama_menu']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                                </div>
                                            <?php else: ?>
                                                <div class="w-14 h-14 rounded-xl bg-slate-100 flex items-center justify-center text-slate-300">
                                                    <i class="fa-solid fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-slate-800 mb-0.5"><?= htmlspecialchars($menu['nama_menu']) ?></div>
                                            <div class="text-xs text-slate-500 line-clamp-2 max-w-xs" title="<?= htmlspecialchars($menu['deskripsi']) ?>">
                                                <?= htmlspecialchars($menu['deskripsi'] ?? 'Tidak ada deskripsi') ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1.5 items-start">
                                                <!-- Kategori -->
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-600 border border-emerald-100">
                                                    <?= htmlspecialchars($menu['kategori']) ?>
                                                </span>
                                                <!-- Bahan Baku -->
                                                <?php if (!empty($menu['bahan_baku'])): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-600 border border-blue-100">
                                                        Bahan: <?= htmlspecialchars($menu['bahan_baku']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <!-- Rasa -->
                                                <?php if (!empty($menu['rasa'])): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-600 border border-amber-100">
                                                        Rasa: <?= htmlspecialchars($menu['rasa']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="font-bold text-emerald-600">Rp <?= number_format($menu['harga'], 0, ',', '.') ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <a href="menu.php?edit=<?= $menu['id'] ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-blue-500 hover:border-blue-300 hover:bg-blue-50 transition-all shadow-sm" title="Edit Menu">
                                                    <i class="fa-solid fa-pen text-xs"></i>
                                                </a>
                                                <a href="menu.php?action=delete&id=<?= $menu['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus menu <?= htmlspecialchars(addslashes($menu['nama_menu'])) ?>?');" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-300 hover:bg-rose-50 transition-all shadow-sm" title="Hapus Menu">
                                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Kolom Kanan: Form Tambah/Edit (Sticky) -->
            <div class="w-full lg:w-[400px] flex-shrink-0 form-sticky">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden relative">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-bl-full -z-0 opacity-50"></div>
                    
                    <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3 relative z-10">
                        <div class="p-2 <?= $menuToEdit ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600' ?> rounded-lg">
                            <i class="fa-solid <?= $menuToEdit ? 'fa-pen-to-square' : 'fa-plus' ?>"></i>
                        </div>
                        <h2 class="font-bold text-slate-800 text-lg">
                            <?= $menuToEdit ? 'Edit Data Menu' : 'Tambah Menu Baru' ?>
                        </h2>
                    </div>
                    
                    <div class="p-6 relative z-10">
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="id" value="<?= $menuToEdit ? (int) $menuToEdit['id'] : 0 ?>">
                            
                            <!-- Nama Menu -->
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Menu <span class="text-rose-500">*</span></label>
                                <input type="text" name="nama_menu" value="<?= htmlspecialchars($menuToEdit['nama_menu'] ?? '') ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors text-sm text-slate-800" placeholder="Contoh: Nasi Goreng Spesial" required>
                            </div>

                            <!-- Kategori -->
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Kategori <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <select name="kategori" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors cursor-pointer" required>
                                        <option value="">-- Pilih Kategori --</option>
                                        <?php foreach ($kategoriOptions as $opt): ?>
                                            <option value="<?= htmlspecialchars($opt) ?>" <?= (isset($menuToEdit['kategori']) && $menuToEdit['kategori'] === $opt) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($opt) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400">
                                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Bahan Baku & Rasa (Grid) -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Bahan <span class="text-slate-400 font-normal">(Ops.)</span></label>
                                    <div class="relative">
                                        <select name="bahan_baku" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors cursor-pointer">
                                            <option value="">-- Kosong --</option>
                                            <?php foreach ($bahanOptions as $opt): ?>
                                                <option value="<?= htmlspecialchars($opt) ?>" <?= (isset($menuToEdit['bahan_baku']) && $menuToEdit['bahan_baku'] === $opt) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($opt) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400">
                                            <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Rasa <span class="text-slate-400 font-normal">(Ops.)</span></label>
                                    <div class="relative">
                                        <select name="rasa" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors cursor-pointer">
                                            <option value="">-- Kosong --</option>
                                            <?php foreach ($rasaOptions as $opt): ?>
                                                <option value="<?= htmlspecialchars($opt) ?>" <?= (isset($menuToEdit['rasa']) && $menuToEdit['rasa'] === $opt) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($opt) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400">
                                            <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Harga -->
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Harga Menu <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <span class="text-slate-500 font-semibold text-sm">Rp</span>
                                    </div>
                                    <input type="number" name="harga" value="<?= htmlspecialchars($menuToEdit['harga'] ?? '') ?>" class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors text-sm text-slate-800 font-semibold" placeholder="25000" min="0" required>
                                </div>
                            </div>

                            <!-- Deskripsi -->
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Deskripsi <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                <textarea name="deskripsi" rows="3" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors text-sm text-slate-800 resize-none custom-scroll" placeholder="Penjelasan singkat mengenai bahan atau porsi..."><?= htmlspecialchars($menuToEdit['deskripsi'] ?? '') ?></textarea>
                            </div>

                            <!-- Upload Gambar -->
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Foto Menu <?= !$menuToEdit ? '<span class="text-rose-500">*</span>' : '' ?></label>
                                
                                <input type="file" name="gambar" accept="image/jpeg, image/png, image/webp" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 transition-colors cursor-pointer border border-slate-200 rounded-xl bg-slate-50 p-1" <?= !$menuToEdit ? 'required' : '' ?>>
                                
                                <?php if ($menuToEdit && !empty($menuToEdit['gambar'])): ?>
                                    <div class="mt-2 text-[11px] text-amber-600 bg-amber-50 px-2 py-1 rounded-md border border-amber-100 inline-block">
                                        <i class="fa-solid fa-circle-info"></i> Kosongkan jika tidak ingin mengganti gambar lama.
                                    </div>
                                <?php else: ?>
                                    <div class="mt-1 text-[11px] text-slate-400">
                                        Format wajib: JPG, PNG, atau WEBP.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Spacer -->
                            <div class="h-2"></div>

                            <!-- Submit Button -->
                            <div class="flex gap-2">
                                <?php if ($menuToEdit): ?>
                                    <a href="menu.php" class="w-1/3 py-3 px-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-xl transition-all duration-300 text-center text-sm border border-slate-200">
                                        Batal
                                    </a>
                                <?php endif; ?>
                                
                                <button type="submit" name="save_menu" class="<?= $menuToEdit ? 'w-2/3' : 'w-full' ?> py-3 px-4 bg-gradient-to-r from-emerald-500 to-green-500 hover:from-emerald-600 hover:to-green-600 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/30 transform transition-all duration-300 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 flex justify-center items-center gap-2">
                                    <i class="fa-solid fa-save"></i> 
                                    <?= $menuToEdit ? 'Simpan Perubahan' : 'Simpan Menu Baru' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>