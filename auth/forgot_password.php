<?php
session_start();
include '../config/koneksi.php';

$error = '';
$success = '';
$step = 'cari_email'; // step: cari_email -> ganti_password

// STEP 1: Cek apakah email terdaftar
if (isset($_POST['cari_email'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    $query = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    $data = mysqli_fetch_assoc($query);

    if ($data) {
        // Simpan email yang sudah diverifikasi ke session agar
        // step ganti password tidak bisa diakses untuk email sembarangan
        $_SESSION['reset_email'] = $data['email'];
        $step = 'ganti_password';
    } else {
        $error = 'Email tidak ditemukan. Periksa kembali email Anda.';
    }
}

// STEP 2: Simpan password baru
if (isset($_POST['ganti_password'])) {
    if (!isset($_SESSION['reset_email'])) {
        $error = 'Sesi tidak valid. Silakan ulangi proses dari awal.';
    } else {
        $password_baru = $_POST['password_baru'];
        $konfirmasi_password = $_POST['konfirmasi_password'];

        if ($password_baru !== $konfirmasi_password) {
            $error = 'Konfirmasi password tidak cocok.';
            $step = 'ganti_password';
        } elseif (strlen($password_baru) < 6) {
            $error = 'Password minimal 6 karakter.';
            $step = 'ganti_password';
        } else {
            $email = mysqli_real_escape_string($conn, $_SESSION['reset_email']);
            $password_hash = md5($password_baru);

            mysqli_query($conn, "UPDATE users SET password='$password_hash' WHERE email='$email'");

            unset($_SESSION['reset_email']);
            $success = 'Password berhasil diubah. Silakan login dengan password baru Anda.';
            $step = 'selesai';
        }
    }
}

// Kalau user reload/refresh di tengah step ganti password, tetap di step itu
if (!isset($_POST['cari_email']) && !isset($_POST['ganti_password']) && isset($_SESSION['reset_email'])) {
    $step = 'ganti_password';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Maufood</title>
    
    <!-- Menggunakan Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
        }
        
        /* Animasi Card Muncul */
        .animate-fade-in-up {
            animation: fadeInUp 0.7s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }
        
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        /* Animasi Background Blob Mengambang */
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob { animation: blob 7s infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }

        /* Efek transisi warna icon saat input fokus */
        .input-field:focus + .icon-container .input-icon {
            color: #10B981;
        }
    </style>
</head>

<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    
    <!-- Ornamen Background Animasi -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-green-200 rounded-full mix-blend-multiply filter blur-3xl opacity-60 animate-blob"></div>
    <div class="absolute top-[20%] right-[-10%] w-96 h-96 bg-emerald-200 rounded-full mix-blend-multiply filter blur-3xl opacity-60 animate-blob animation-delay-2000"></div>
    <div class="absolute bottom-[-20%] left-[20%] w-96 h-96 bg-teal-200 rounded-full mix-blend-multiply filter blur-3xl opacity-60 animate-blob animation-delay-4000"></div>

    <div class="bg-white/90 backdrop-blur-xl w-full max-w-md rounded-[24px] shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-white p-8 relative z-10 animate-fade-in-up">
        
        <!-- Header & Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-green-100 mb-4 shadow-inner text-green-600 transform transition hover:scale-110 duration-300">
                <!-- Ikon Kunci / Lock -->
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Lupa <span class="text-green-500">Password</span></h1>
            
            <p class="text-slate-500 text-sm mt-2 px-4">
                <?php if ($step === 'cari_email'): ?>
                    Masukkan email akun Anda untuk mengatur ulang password.
                <?php elseif ($step === 'ganti_password'): ?>
                    Email valid. Silakan buat password baru yang kuat.
                <?php elseif ($step === 'selesai'): ?>
                    Selamat, pemulihan akun berhasil!
                <?php endif; ?>
            </p>
        </div>

        <!-- Tampilkan Alert Error Jika Ada -->
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-xl text-sm mb-5 border border-red-100 flex items-start gap-2 animate-fade-in-up" style="animation-duration: 0.3s;">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($step === 'cari_email'): ?>
            <form method="POST" autocomplete="off" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Alamat Email</label>
                    <div class="relative flex flex-col-reverse">
                        <input type="email" id="email" name="email" class="input-field block w-full pl-11 pr-4 py-3 border border-slate-200 rounded-xl bg-slate-50/50 text-slate-800 focus:bg-white focus:outline-none focus:ring-4 focus:ring-green-500/10 focus:border-green-500 transition-all duration-300" placeholder="contoh@email.com" required>
                        <div class="icon-container absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-400 input-icon transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                        </div>
                    </div>
                </div>
                <button type="submit" name="cari_email" class="w-full py-3 px-4 mt-2 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-semibold rounded-xl shadow-lg shadow-green-500/30 transform transition-all duration-300 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Lanjutkan
                </button>
            </form>

        <?php elseif ($step === 'ganti_password'): ?>
            <form method="POST" autocomplete="off" class="space-y-4">
                <!-- Input Password Baru -->
                <div>
                    <label for="password_baru" class="block text-sm font-medium text-slate-700 mb-1.5">Password Baru</label>
                    <div class="relative flex flex-col-reverse">
                        <input type="password" id="password_baru" name="password_baru" class="input-field block w-full pl-11 pr-12 py-3 border border-slate-200 rounded-xl bg-slate-50/50 text-slate-800 focus:bg-white focus:outline-none focus:ring-4 focus:ring-green-500/10 focus:border-green-500 transition-all duration-300" placeholder="Minimal 6 karakter" minlength="6" required>
                        <div class="icon-container absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-400 input-icon transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <button type="button" onclick="togglePassword('password_baru', 'eye-icon-1')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-green-500 transition-colors focus:outline-none">
                            <svg id="eye-icon-1" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Input Konfirmasi Password -->
                <div>
                    <label for="konfirmasi_password" class="block text-sm font-medium text-slate-700 mb-1.5">Konfirmasi Password</label>
                    <div class="relative flex flex-col-reverse">
                        <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="input-field block w-full pl-11 pr-12 py-3 border border-slate-200 rounded-xl bg-slate-50/50 text-slate-800 focus:bg-white focus:outline-none focus:ring-4 focus:ring-green-500/10 focus:border-green-500 transition-all duration-300" placeholder="Ulangi password baru" minlength="6" required>
                        <div class="icon-container absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-400 input-icon transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        </div>
                        <button type="button" onclick="togglePassword('konfirmasi_password', 'eye-icon-2')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-green-500 transition-colors focus:outline-none">
                            <svg id="eye-icon-2" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" name="ganti_password" class="w-full py-3 px-4 mt-4 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-semibold rounded-xl shadow-lg shadow-green-500/30 transform transition-all duration-300 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Simpan Password Baru
                </button>
            </form>

        <?php elseif ($step === 'selesai'): ?>
            <div class="bg-emerald-50 text-emerald-600 p-5 rounded-2xl text-center mb-6 border border-emerald-100 animate-fade-in-up" style="animation-duration: 0.3s;">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100 text-emerald-500 mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h3 class="font-bold text-lg text-emerald-700 mb-1">Berhasil!</h3>
                <p class="text-sm"><?= htmlspecialchars($success) ?></p>
            </div>
            <a href="login.php" class="block text-center w-full py-3 px-4 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-semibold rounded-xl shadow-lg shadow-green-500/30 transform transition-all duration-300 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                Lanjut ke Login
            </a>
        <?php endif; ?>

        <!-- Link Kembali -->
        <?php if ($step !== 'selesai'): ?>
            <div class="mt-8 text-center">
                <a href="login.php" class="inline-flex items-center text-sm font-semibold text-slate-500 hover:text-green-600 transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Kembali ke halaman Login
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Fungsi untuk menampilkan/menyembunyikan password (mendukung banyak input di 1 halaman)
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            
            if (passwordInput && passwordInput.type === 'password') {
                passwordInput.type = 'text';
                // Mata tertutup
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path>';
            } else if (passwordInput) {
                passwordInput.type = 'password';
                // Mata terbuka
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
            }
        }
    </script>
</body>
</html>