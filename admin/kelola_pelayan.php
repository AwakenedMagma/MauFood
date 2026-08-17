<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$success = '';
$error = '';
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$pelayanToEdit = null;

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = (int) $_GET['id'];
    
    // Pastikan admin tidak bisa menghapus admin (hanya hapus role='pelayan')
    $checkRole = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id='$deleteId'"));
    
    if ($checkRole && $checkRole['role'] === 'pelayan') {
        mysqli_query($conn, "DELETE FROM users WHERE id='$deleteId'");
        header('Location: kelola_pelayan.php?success=Data pelayan berhasil dihapus');
        exit;
    } else {
        $error = "Gagal menghapus. Data tidak ditemukan atau Anda mencoba menghapus Admin.";
    }
}

// Ambil data untuk form Edit
if ($editId > 0) {
    $pelayanToEdit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$editId' AND role='pelayan'"));
    if (!$pelayanToEdit) {
        $error = "Data pelayan tidak ditemukan.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pelayan'])) {
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = trim($_POST['password']);
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($nama === '' || $email === '') {
        $error = 'Nama dan Email wajib diisi.';
    } else {
        // Cek apakah email sudah digunakan oleh user lain (termasuk admin/customer)
        $cekEmail = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' AND id != '$id'");
        
        if (mysqli_num_rows($cekEmail) > 0) {
            $error = 'Email tersebut sudah digunakan oleh akun lain. Silakan gunakan email berbeda.';
        } else {
            if ($id > 0) {
                // Update Mode
                if ($password !== '') {
                    // Update dengan ganti password
                    $passHash = md5($password);
                    mysqli_query($conn, "UPDATE users SET nama='$nama', email='$email', password='$passHash' WHERE id='$id' AND role='pelayan'");
                } else {
                    // Update tanpa ganti password
                    mysqli_query($conn, "UPDATE users SET nama='$nama', email='$email' WHERE id='$id' AND role='pelayan'");
                }
                $success = 'Data pelayan berhasil diperbarui.';
                header('Location: kelola_pelayan.php?success=' . urlencode($success));
                exit;
            } else {
                // Insert Mode
                if ($password === '') {
                    $error = 'Password wajib diisi untuk mendaftarkan pelayan baru.';
                } else {
                    $passHash = md5($password);
                    mysqli_query($conn, "INSERT INTO users (nama, email, password, role) VALUES ('$nama', '$email', '$passHash', 'pelayan')");
                    $success = 'Akun Pelayan baru berhasil didaftarkan.';
                    header('Location: kelola_pelayan.php?success=' . urlencode($success));
                    exit;
                }
            }
        }
    }
}

if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

// Mengambil daftar semua akun dengan role 'pelayan'
$pelayanList = mysqli_query($conn, "SELECT * FROM users WHERE role='pelayan' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pelayan - Admin Maufood</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts (Poppins) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; }
        
        /* Custom Scrollbar */
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }

        /* Sticky Form for Desktop */
        @media (min-width: 1024px) {
            .form-sticky { position: sticky; top: 5.5rem; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <nav class="bg-white/80 backdrop-blur-md shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] sticky top-0 z-50 border-b border-slate-100">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Judul / Brand -->
                <div class="flex-shrink-0 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 text-white flex items-center justify-center shadow-md shadow-green-500/20">
                        <i class="fa-solid fa-users-gear text-sm"></i>
                    </div>
                    <h1 class="font-bold text-xl tracking-tight hidden sm:block">Admin <span class="text-green-500">Maufood</span></h1>
                </div>

                <!-- Navigasi -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="dashboard.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
                        <i class="fa-solid fa-house"></i> <span class="hidden md:inline">Dashboard</span>
                    </a>
                    <a href="menu.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors">
                        <i class="fa-solid fa-utensils"></i> <span class="hidden md:inline">Kelola Menu</span>
                    </a>
                    <a href="kelola_pelayan.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 transition-colors">
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
        
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Manajemen Pelayan</h2>
            <p class="text-slate-500 text-sm mt-1">Daftarkan akun pelayan baru dan kelola akses login pegawai Maufood.</p>
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
            
            <!-- Kolom Kiri: Daftar Pelayan (Tabel) -->
            <div class="flex-1 w-full bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-100 text-blue-600 rounded-lg">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <h3 class="font-bold text-slate-800 text-lg">Daftar Akun Pelayan</h3>
                    </div>
                    <span class="text-xs font-semibold bg-white border border-slate-200 px-3 py-1.5 rounded-full text-slate-500 shadow-sm">
                        Total <?= mysqli_num_rows($pelayanList) ?> Pelayan
                    </span>
                </div>
                
                <div class="overflow-x-auto custom-scroll">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="bg-white border-b border-slate-100 text-slate-400 text-xs uppercase tracking-wider font-semibold">
                                <th class="px-6 py-4 w-16">ID</th>
                                <th class="px-6 py-4">Informasi Akun</th>
                                <th class="px-6 py-4 text-center">Hak Akses</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php if (mysqli_num_rows($pelayanList) === 0): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 text-slate-300 mb-3">
                                            <i class="fa-solid fa-user-slash text-2xl"></i>
                                        </div>
                                        <p class="text-slate-500 font-medium">Belum ada akun pelayan yang terdaftar.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($pelayan = mysqli_fetch_assoc($pelayanList)): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors group">
                                        <td class="px-6 py-4 font-bold text-slate-400">
                                            #<?= htmlspecialchars($pelayan['id']) ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <!-- Inisial Avatar -->
                                                <div class="w-10 h-10 rounded-full bg-emerald-100 border border-emerald-200 text-emerald-600 flex items-center justify-center font-bold shadow-sm">
                                                    <?= htmlspecialchars(strtoupper(substr($pelayan['nama'], 0, 1))) ?>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-slate-800 mb-0.5"><?= htmlspecialchars($pelayan['nama']) ?></div>
                                                    <div class="text-xs text-slate-500 flex items-center gap-1.5">
                                                        <i class="fa-solid fa-envelope text-slate-400"></i> <?= htmlspecialchars($pelayan['email']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-blue-50 text-blue-600 border border-blue-100">
                                                <i class="fa-solid fa-shield-halved mr-1"></i> Pelayan
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <a href="kelola_pelayan.php?edit=<?= $pelayan['id'] ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-blue-500 hover:border-blue-300 hover:bg-blue-50 transition-all shadow-sm" title="Edit Akun">
                                                    <i class="fa-solid fa-pen text-xs"></i>
                                                </a>
                                                <a href="kelola_pelayan.php?action=delete&id=<?= $pelayan['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin mencabut akses dan menghapus akun <?= htmlspecialchars(addslashes($pelayan['nama'])) ?>?');" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-300 hover:bg-rose-50 transition-all shadow-sm" title="Hapus Akun">
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
                    <!-- Ornamen Form -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-bl-full -z-0 opacity-50"></div>
                    
                    <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3 relative z-10">
                        <div class="p-2 <?= $pelayanToEdit ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600' ?> rounded-lg">
                            <i class="fa-solid <?= $pelayanToEdit ? 'fa-user-pen' : 'fa-user-plus' ?>"></i>
                        </div>
                        <h2 class="font-bold text-slate-800 text-lg">
                            <?= $pelayanToEdit ? 'Edit Data Pelayan' : 'Tambah Pelayan' ?>
                        </h2>
                    </div>
                    
                    <div class="p-6 relative z-10">
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="id" value="<?= $pelayanToEdit ? (int) $pelayanToEdit['id'] : 0 ?>">
                            
                            <!-- Nama Lengkap -->
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-user text-slate-400 text-sm"></i>
                                    </div>
                                    <input type="text" name="nama" value="<?= htmlspecialchars($pelayanToEdit['nama'] ?? '') ?>" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors text-sm text-slate-800" placeholder="Vinicius" required>
                                </div>
                            </div>

                            <!-- Email Akses -->
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Akses Login <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-at text-slate-400 text-sm"></i>
                                    </div>
                                    <input type="email" name="email" value="<?= htmlspecialchars($pelayanToEdit['email'] ?? '') ?>" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors text-sm text-slate-800" placeholder="pelayan@maufood.com" required>
                                </div>
                            </div>
                            
                            <!-- Password -->
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Password <?= !$pelayanToEdit ? '<span class="text-rose-500">*</span>' : '' ?></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-lock text-slate-400 text-sm"></i>
                                    </div>
                                    <input type="password" name="password" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors text-sm text-slate-800" placeholder="<?= $pelayanToEdit ? 'Kosongkan jika tidak diubah' : 'Buat password login' ?>" <?= !$pelayanToEdit ? 'required' : '' ?>>
                                </div>
                                <?php if ($pelayanToEdit): ?>
                                    <div class="mt-2 text-[11px] text-amber-600 bg-amber-50 px-2 py-1 rounded-md border border-amber-100 inline-block">
                                        <i class="fa-solid fa-circle-info"></i> Kosongkan jika password tidak ingin diganti.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Spacer -->
                            <div class="h-2"></div>

                            <!-- Submit Button -->
                            <div class="flex gap-2">
                                <?php if ($pelayanToEdit): ?>
                                    <a href="kelola_pelayan.php" class="w-1/3 py-3 px-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-xl transition-all duration-300 text-center text-sm border border-slate-200 flex items-center justify-center">
                                        Batal
                                    </a>
                                <?php endif; ?>
                                
                                <button type="submit" name="save_pelayan" class="<?= $pelayanToEdit ? 'w-2/3' : 'w-full' ?> py-3 px-4 bg-gradient-to-r from-emerald-500 to-green-500 hover:from-emerald-600 hover:to-green-600 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/30 transform transition-all duration-300 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 flex justify-center items-center gap-2">
                                    <i class="fa-solid fa-save"></i> 
                                    <?= $pelayanToEdit ? 'Simpan Perubahan' : 'Buat Akun' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tips Keamanan -->
                <div class="mt-4 bg-blue-50 border border-blue-100 p-4 rounded-2xl shadow-sm">
                    <h3 class="font-bold text-blue-800 flex items-center gap-2 mb-2 text-sm">
                        <i class="fa-solid fa-shield-check text-blue-500"></i> Info Keamanan
                    </h3>
                    <p class="text-xs text-blue-700/80 leading-relaxed">
                        Akun yang dibuat di halaman ini secara otomatis akan memiliki peran <strong>(Role) Pelayan</strong>. Mereka dapat mengakses fitur Input Pesanan dan Kasir POS, namun tidak bisa mengakses halaman Administrator ini.
                    </p>
                </div>
            </div>

        </div>
    </main>

</body>
</html>