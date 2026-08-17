<?php
session_start();
include '../config/koneksi.php';

// Cek apakah user adalah pelayan atau admin
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['pelayan', 'admin'])) {
    header('Location: ../auth/login.php');
    exit;
}

$error = '';
$success = '';

// Proses form registrasi member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daftar_member'])) {
    $nama = trim($_POST['nama'] ?? '');
    $nomor_telepon = trim($_POST['nomor_telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    // Validasi input
    if (empty($nama)) {
        $error = 'Nama member wajib diisi.';
    } elseif (empty($nomor_telepon)) {
        $error = 'Nomor telepon wajib diisi.';
    } elseif (strlen($nomor_telepon) < 10) {
        $error = 'Nomor telepon minimal 10 digit.';
    } else {
        // Cek apakah nomor telepon sudah terdaftar
        $check_query = mysqli_query($conn, "SELECT id FROM member WHERE nomor_telepon = '" . mysqli_real_escape_string($conn, $nomor_telepon) . "'");
        
        if (mysqli_num_rows($check_query) > 0) {
            $error = 'Nomor telepon sudah terdaftar.';
        } else {
            // Escape input
            $nama_escaped = mysqli_real_escape_string($conn, $nama);
            $nomor_escaped = mysqli_real_escape_string($conn, $nomor_telepon);
            $email_escaped = mysqli_real_escape_string($conn, $email);
            $alamat_escaped = mysqli_real_escape_string($conn, $alamat);

            // Insert member baru
            $query = "INSERT INTO member (nama, nomor_telepon, email, alamat, status) 
                     VALUES ('$nama_escaped', '$nomor_escaped', '$email_escaped', '$alamat_escaped', 'aktif')";
            
            if (mysqli_query($conn, $query)) {
                $member_id = mysqli_insert_id($conn);
                $success = "Member berhasil didaftarkan! ID Member: <strong>$member_id</strong>";
            } else {
                $error = 'Terjadi kesalahan saat menyimpan data. ' . mysqli_error($conn);
            }
        }
    }
}

// Ambil daftar member
$memberList = mysqli_query($conn, "SELECT * FROM member WHERE status = 'aktif' ORDER BY nama ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Member - Maufood</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts (Poppins) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; }
        
        /* Custom Scrollbar */
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }

        /* Remove arrows from number input if used */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        
        /* Toast Animation */
        .toast-enter { transform: translateY(100%); opacity: 0; }
        .toast-enter-active { transform: translateY(0); opacity: 1; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .toast-exit { transform: translateY(0); opacity: 1; }
        .toast-exit-active { transform: translateY(100%); opacity: 0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="bg-white/80 backdrop-blur-md shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] sticky top-0 z-50 border-b border-slate-100">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Tombol Kembali -->
                <a href="pesan.php" class="inline-flex items-center gap-2 bg-slate-50 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 px-4 py-2 rounded-xl font-medium text-sm transition-colors duration-200 border border-slate-200 hover:border-emerald-200">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span class="hidden sm:inline">Kasir Pelayan</span>
                </a>

                <!-- Judul / Brand -->
                <div class="flex-shrink-0 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 text-white flex items-center justify-center shadow-md shadow-green-500/20">
                        <i class="fa-solid fa-users text-sm"></i>
                    </div>
                    <h1 class="font-bold text-xl tracking-tight hidden sm:block">Mau<span class="text-green-500">food</span> <span class="text-slate-400 font-normal text-sm ml-1">| Member</span></h1>
                </div>

                <!-- Tombol Logout -->
                <a href="../auth/logout.php" class="inline-flex items-center justify-center px-4 py-2 bg-rose-50 rounded-xl text-rose-500 font-medium text-sm hover:bg-rose-100 hover:text-rose-600 transition-colors duration-200" title="Keluar">
                    <span class="hidden sm:inline mr-2">Logout</span>
                    <i class="fa-solid fa-right-from-bracket text-lg sm:text-base"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-grow max-w-[1400px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="flex flex-col lg:flex-row gap-6 lg:gap-8">
            
            <!-- Kolom Kiri: Form Pendaftaran -->
            <div class="w-full lg:w-[400px] flex-shrink-0 space-y-6">
                
                <!-- Alerts -->
                <?php if ($error): ?>
                    <div class="bg-rose-50 text-rose-600 p-4 rounded-xl text-sm border border-rose-100 flex items-start gap-3 shadow-sm">
                        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                        <span class="flex-grow"><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl text-sm border border-emerald-100 flex items-start gap-3 shadow-sm">
                        <i class="fa-solid fa-circle-check mt-0.5"></i>
                        <span class="flex-grow"><?= $success ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                        <div class="p-2 bg-emerald-100 text-emerald-600 rounded-lg">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <h2 class="font-bold text-slate-800 text-lg">Daftar Member Baru</h2>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="space-y-4">
                            <!-- Nama -->
                            <div>
                                <label for="nama" class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <i class="fa-regular fa-user text-slate-400"></i>
                                    </div>
                                    <input type="text" id="nama" name="nama" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors text-sm text-slate-800" placeholder="Jude Bellingham" required>
                                </div>
                            </div>

                            <!-- No Telepon -->
                            <div>
                                <label for="nomor_telepon" class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor Telepon <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-phone text-slate-400"></i>
                                    </div>
                                    <input type="tel" id="nomor_telepon" name="nomor_telepon" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors text-sm text-slate-800" placeholder="08123456789" required pattern="[0-9]{10,15}">
                                </div>
                                <p class="text-[11px] text-slate-400 mt-1.5 ml-1">Format angka saja, tanpa spasi atau tanda hubung (-).</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <i class="fa-regular fa-envelope text-slate-400"></i>
                                    </div>
                                    <input type="email" id="email" name="email" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors text-sm text-slate-800" placeholder="budi@email.com">
                                </div>
                            </div>

                            <!-- Alamat -->
                            <div>
                                <label for="alamat" class="block text-sm font-semibold text-slate-700 mb-1.5">Alamat <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                <div class="relative">
                                    <div class="absolute top-3 left-0 pl-3.5 flex items-start pointer-events-none">
                                        <i class="fa-solid fa-map-location-dot text-slate-400 mt-1"></i>
                                    </div>
                                    <textarea id="alamat" name="alamat" rows="3" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors text-sm text-slate-800 resize-none" placeholder="Jl. Merdeka No. 10..."></textarea>
                                </div>
                            </div>

                            <button type="submit" name="daftar_member" class="w-full py-3 px-4 mt-2 bg-gradient-to-r from-emerald-500 to-green-500 hover:from-emerald-600 hover:to-green-600 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/30 transform transition-all duration-300 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 flex justify-center items-center gap-2">
                                <i class="fa-solid fa-save"></i> Simpan Member
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tips Box -->
                <div class="bg-amber-50 rounded-2xl border border-amber-100 p-5 shadow-sm">
                    <h3 class="font-bold text-amber-800 flex items-center gap-2 mb-2 text-sm">
                        <i class="fa-solid fa-lightbulb text-amber-500 text-lg"></i> Info Penting
                    </h3>
                    <ul class="space-y-1.5 text-sm text-amber-700/90 list-disc list-inside ml-1">
                        <li>Nomor telepon berfungsi sebagai identitas unik.</li>
                        <li>ID Member otomatis tergenerate setelah sukses.</li>
                        <li>Gunakan fitur "Copy" di tabel untuk menyalin ID dengan cepat.</li>
                    </ul>
                </div>
            </div>

            <!-- Kolom Kanan: Daftar Member -->
            <div class="flex-grow">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col h-full min-h-[500px]">
                    
                    <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center flex-wrap gap-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-100 text-blue-600 rounded-lg">
                                <i class="fa-solid fa-address-book"></i>
                            </div>
                            <h2 class="font-bold text-slate-800 text-lg">Direktori Member</h2>
                        </div>
                        <span class="bg-emerald-100 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <?php 
                                $countQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM member WHERE status = 'aktif'");
                                $countResult = mysqli_fetch_assoc($countQuery);
                                echo $countResult['total'] . " Aktif";
                            ?>
                        </span>
                    </div>

                    <div class="p-0 flex-grow overflow-x-auto custom-scroll">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                    <th class="px-6 py-4">ID</th>
                                    <th class="px-6 py-4">Informasi Member</th>
                                    <th class="px-6 py-4">Kontak</th>
                                    <th class="px-6 py-4 text-center">Pesanan</th>
                                    <th class="px-6 py-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                <?php if (mysqli_num_rows($memberList) > 0): ?>
                                    <?php while ($member = mysqli_fetch_assoc($memberList)): ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors group">
                                            <td class="px-6 py-4 font-bold text-slate-700">
                                                #<?= $member['id'] ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-semibold text-slate-800 mb-0.5"><?= htmlspecialchars($member['nama']) ?></div>
                                                <div class="text-xs text-slate-400 truncate max-w-[200px]" title="<?= htmlspecialchars($member['alamat']) ?>">
                                                    <?= !empty($member['alamat']) ? htmlspecialchars($member['alamat']) : '<i class="text-slate-300">Alamat tidak diisi</i>' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-mono text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded inline-block mb-1 border border-slate-200">
                                                    <i class="fa-solid fa-phone text-[10px] mr-1"></i> <?= htmlspecialchars($member['nomor_telepon']) ?>
                                                </div>
                                                <div class="text-[11px] text-slate-400">
                                                    <?= !empty($member['email']) ? htmlspecialchars($member['email']) : '-' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <?php if($member['total_pesanan'] > 0): ?>
                                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full bg-blue-50 text-blue-600 text-xs font-bold border border-blue-100">
                                                        <?= $member['total_pesanan'] ?>x
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-medium">
                                                        Baru
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <button class="copy-member-id w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-emerald-500 hover:border-emerald-300 hover:bg-emerald-50 transition-all flex items-center justify-center mx-auto shadow-sm" 
                                                        data-id="<?= $member['id'] ?>" 
                                                        data-nama="<?= htmlspecialchars($member['nama']) ?>"
                                                        title="Salin Data Member">
                                                    <i class="fa-regular fa-copy"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center">
                                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 text-slate-300 mb-3">
                                                <i class="fa-solid fa-users-slash text-2xl"></i>
                                            </div>
                                            <p class="text-slate-500 font-medium">Belum ada member yang terdaftar.</p>
                                            <p class="text-slate-400 text-xs mt-1">Daftarkan member baru melalui form di samping.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Custom Tailwind Toast Notification (Hidden by default) -->
    <div id="customToast" class="fixed bottom-6 right-6 z-50 flex items-center gap-3 bg-slate-800 text-white px-5 py-3.5 rounded-xl shadow-2xl toast-enter pointer-events-none" style="display: none;">
        <div class="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-check"></i>
        </div>
        <div>
            <h4 class="text-sm font-semibold">Berhasil Disalin!</h4>
            <p class="text-xs text-slate-300 mt-0.5">ID dan Nama member masuk ke clipboard.</p>
        </div>
    </div>

    <script>
        // Custom Toast Logic
        function showToast() {
            const toast = document.getElementById('customToast');
            toast.style.display = 'flex';
            
            // Trigger reflow for animation
            void toast.offsetWidth; 
            
            toast.classList.remove('toast-enter', 'toast-exit-active');
            toast.classList.add('toast-enter-active');
            
            setTimeout(() => {
                toast.classList.remove('toast-enter-active');
                toast.classList.add('toast-exit-active');
                
                setTimeout(() => {
                    toast.style.display = 'none';
                    toast.classList.remove('toast-exit-active');
                    toast.classList.add('toast-enter');
                }, 300); // Wait for exit animation
            }, 3000); // Show for 3 seconds
        }

        // Copy Member ID dan Nama ke clipboard
        document.querySelectorAll('.copy-member-id').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const memberId = this.dataset.id;
                const memberNama = this.dataset.nama;
                const textToCopy = `${memberId} - ${memberNama}`;
                
                // Animasi klik tombol
                const icon = this.querySelector('i');
                this.classList.add('bg-emerald-500', 'text-white', 'border-emerald-500');
                this.classList.remove('text-slate-400', 'bg-white');
                icon.className = 'fa-solid fa-check';
                
                setTimeout(() => {
                    this.classList.remove('bg-emerald-500', 'text-white', 'border-emerald-500');
                    this.classList.add('text-slate-400', 'bg-white');
                    icon.className = 'fa-regular fa-copy';
                }, 1500);

                // Copy Text API
                if(navigator.clipboard && window.isSecureContext) {
                    // Modern Approach
                    navigator.clipboard.writeText(textToCopy).then(() => {
                        showToast();
                    });
                } else {
                    // Fallback for older browsers / non-HTTPS local dev
                    let textArea = document.createElement("textarea");
                    textArea.value = textToCopy;
                    textArea.style.position = "fixed";
                    textArea.style.left = "-999999px";
                    textArea.style.top = "-999999px";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        showToast();
                    } catch (err) {
                        console.error('Fallback: Oops, unable to copy', err);
                    }
                    textArea.remove();
                }
            });
        });
    </script>

</body>
</html>