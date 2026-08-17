<?php
include '../config/koneksi.php';

$success = false;

if (isset($_POST['register'])) {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $password = md5($_POST['password']);

    // Menjalankan query insert
    $query = mysqli_query($conn, "INSERT INTO users (nama, email, password) 
                         VALUES ('$nama','$email','$password')");
    
    if($query) {
        $success = true;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Maufood</title>
    
    <!-- Menggunakan Tailwind CSS untuk styling modern -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Menggunakan Font Poppins agar selaras dengan halaman Login -->
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
            color: #10B981; /* Tailwind Emerald 500 */
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
                <!-- Ikon User Plus -->
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Daftar <span class="text-green-500">Akun</span></h1>
            <p class="text-slate-500 text-sm mt-1">Bergabunglah dan nikmati menu terbaik kami!</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-emerald-50 text-emerald-600 p-4 rounded-2xl text-center mb-4 border border-emerald-100 animate-fade-in-up" style="animation-duration: 0.3s;">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100 text-emerald-500 mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h3 class="font-bold text-lg text-emerald-700 mb-1">Registrasi Berhasil!</h3>
                <p class="text-sm">Akun Anda telah dibuat. Mengalihkan ke halaman login...</p>
            </div>
            <script>
                // Mengalihkan ke halaman login setelah 2 detik tanpa menggunakan alert
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 2000);
            </script>
        <?php else: ?>
            <form method="POST" autocomplete="off" class="space-y-4">
                
                <!-- Input Nama -->
                <div>
                    <label for="nama" class="block text-sm font-medium text-slate-700 mb-1.5">Nama Lengkap</label>
                    <div class="relative flex flex-col-reverse">
                        <input type="text" id="nama" name="nama" class="input-field block w-full pl-11 pr-4 py-3 border border-slate-200 rounded-xl bg-slate-50/50 text-slate-800 focus:bg-white focus:outline-none focus:ring-4 focus:ring-green-500/10 focus:border-green-500 transition-all duration-300" placeholder="Verdi" required>
                        <div class="icon-container absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-400 input-icon transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Input Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                    <div class="relative flex flex-col-reverse">
                        <input type="email" id="email" name="email" class="input-field block w-full pl-11 pr-4 py-3 border border-slate-200 rounded-xl bg-slate-50/50 text-slate-800 focus:bg-white focus:outline-none focus:ring-4 focus:ring-green-500/10 focus:border-green-500 transition-all duration-300" placeholder="contoh@email.com" required>
                        <div class="icon-container absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-400 input-icon transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path></svg>
                        </div>
                    </div>
                </div>

                <!-- Input Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <div class="relative flex flex-col-reverse">
                        <input type="password" id="password" name="password" class="input-field block w-full pl-11 pr-12 py-3 border border-slate-200 rounded-xl bg-slate-50/50 text-slate-800 focus:bg-white focus:outline-none focus:ring-4 focus:ring-green-500/10 focus:border-green-500 transition-all duration-300" placeholder="Buat password yang kuat" required>
                        <div class="icon-container absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-slate-400 input-icon transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <!-- Tombol Lihat Password -->
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-green-500 transition-colors focus:outline-none">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" name="register" class="w-full py-3 px-4 mt-4 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-semibold rounded-xl shadow-lg shadow-green-500/30 transform transition-all duration-300 hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Daftar Sekarang
                </button>
            </form>
        <?php endif; ?>

        <p class="text-center mt-8 text-sm text-slate-500">
            Sudah punya akun? <a href="login.php" class="font-semibold text-green-600 hover:text-green-500 transition-colors">Masuk di sini</a>
        </p>
    </div>

    <script>
        // Fungsi untuk menampilkan/menyembunyikan password
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput && passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path>';
            } else if (passwordInput) {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
            }
        }
    </script>
</body>
</html>