<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelayan') {
    header('Location: ../auth/login.php');
    exit;
}


// 1. UPDATE STATUS PESANAN (TETAP MENGGUNAKAN POST NORMAL)

if (isset($_POST['update_status'])) {
    $pesanan_id = (int) $_POST['pesanan_id'];
    $status_baru = $_POST['status_baru'];

    $statusValid = ['pending', 'diproses', 'selesai', 'dibatalkan'];
    if (in_array($status_baru, $statusValid, true)) {
        mysqli_query($conn, "UPDATE pesanan SET status='$status_baru' WHERE id='$pesanan_id'");
    }

    header('Location: kelola_pesanan.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
    exit;
}


// 2. AMBIL DATA DARI DATABASE

$filter = $_GET['filter'] ?? 'aktif';
$whereClause = "1=1";

if ($filter === 'aktif') {
    $whereClause = "status IN ('pending','diproses')";
} elseif (in_array($filter, ['pending', 'diproses', 'selesai', 'dibatalkan'], true)) {
    $whereClause = "status = '" . mysqli_real_escape_string($conn, $filter) . "'";
}

$orderQuery = mysqli_query($conn, "SELECT p.*, u.nama AS nama_pemesan
    FROM pesanan p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE $whereClause
    ORDER BY p.id DESC");

$orders = [];
while ($row = mysqli_fetch_assoc($orderQuery)) {
    $detailQuery = mysqli_query($conn, "SELECT dp.*, m.nama_menu FROM detail_pesanan dp
        LEFT JOIN menu m ON dp.menu_id = m.id
        WHERE dp.pesanan_id='{$row['id']}'");
    $row['details'] = [];
    while ($d = mysqli_fetch_assoc($detailQuery)) {
        $row['details'][] = $d;
    }
    $orders[] = $row;
}


// 3. FUNGSI HELPER

function badgeColorTailwind($status) {
    return match ($status) {
        'pending' => 'bg-amber-100 text-amber-700 border-amber-200',
        'diproses' => 'bg-sky-100 text-sky-700 border-sky-200',
        'selesai' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'dibatalkan' => 'bg-rose-100 text-rose-700 border-rose-200',
        default => 'bg-slate-100 text-slate-700 border-slate-200'
    };
}


// 4. RENDER BLOK KONTEN PESANAN (OUTPUT BUFFERING)

// Kita menampung HTML kartu pesanan ke dalam variabel. 
// Jika ini adalah request AJAX, kita HANYA mengirimkan variabel ini lalu exit.
ob_start();

if (count($orders) === 0): ?>
    <div class="bg-white rounded-3xl p-12 text-center border border-slate-100 shadow-sm mt-8">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-50 text-slate-300 mb-4">
            <i class="fa-solid fa-receipt text-4xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-slate-700 mb-1">Tidak Ada Pesanan</h3>
        <p class="text-slate-500 text-sm">Belum ada data pesanan untuk filter yang dipilih saat ini.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php 
        $delay = 0;
        foreach ($orders as $order): 
            $animDelay = $delay * 0.1;
            $delay++;
        ?>
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition-shadow duration-300 order-card flex flex-col h-full" style="animation-delay: <?= $animDelay ?>s;">
                
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Pesanan #<?= (int) $order['id'] ?></h3>
                        <div class="flex items-center gap-1.5 text-xs font-medium mt-1 text-slate-500">
                            <?php if ($order['tipe'] === 'dine-in'): ?>
                                <span class="bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded flex items-center gap-1">
                                    <i class="fa-solid fa-utensils text-[10px]"></i> Dine-in
                                </span>
                                <span>• Meja <?= htmlspecialchars($order['meja'] ?? '-') ?></span>
                            <?php else: ?>
                                <span class="bg-orange-50 text-orange-600 px-2 py-0.5 rounded flex items-center gap-1">
                                    <i class="fa-solid fa-motorcycle text-[10px]"></i> Delivery
                                </span>
                                <span class="truncate max-w-[100px]">• <?= htmlspecialchars($order['nama_pemesan'] ?? 'Pelanggan') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full border capitalize <?= badgeColorTailwind($order['status']) ?>">
                        <?= htmlspecialchars($order['status']) ?>
                    </span>
                </div>

                <div class="w-full h-px bg-slate-100 mb-4"></div>

                <div class="flex-grow space-y-2.5 mb-4">
                    <?php foreach ($order['details'] as $d): ?>
                        <div class="text-sm">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-2 overflow-hidden text-slate-600">
                                    <span class="font-medium text-slate-700 min-w-[24px]">x<?= (int) $d['jumlah'] ?></span>
                                    <span class="truncate"><?= htmlspecialchars($d['nama_menu'] ?? 'Menu Dihapus') ?></span>
                                </div>
                                <span class="font-medium text-slate-800 whitespace-nowrap ml-2">Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></span>
                            </div>
                            <?php if (!empty($d['catatan'])): ?>
                                <div class="text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-md px-2 py-1 mt-1 ml-8 flex items-start gap-1">
                                    <i class="fa-solid fa-note-sticky mt-0.5"></i>
                                    <span><?= htmlspecialchars($d['catatan']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="w-full h-px bg-slate-100 mb-4"></div>

                <div class="flex justify-between items-center mb-5">
                    <span class="text-sm font-medium text-slate-500">Total Pembayaran</span>
                    <strong class="text-lg text-emerald-600">Rp <?= number_format($order['total'], 0, ',', '.') ?></strong>
                </div>

                <?php if (!in_array($order['status'], ['selesai', 'dibatalkan'], true)): ?>
                    <form method="POST" class="mt-auto flex gap-2 status-form">
                        <input type="hidden" name="pesanan_id" value="<?= (int) $order['id'] ?>">
                        <div class="relative flex-grow">
                            <select name="status_baru" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-colors">
                                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="diproses" <?= $order['status'] === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                                <option value="selesai">Tandai Selesai</option>
                                <option value="dibatalkan">Batalkan</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                        <button type="submit" name="update_status" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors duration-200 shadow-sm shadow-emerald-500/30">
                            Update
                        </button>
                    </form>
                <?php else: ?>
                    <div class="mt-auto bg-slate-50 text-slate-500 text-sm font-medium py-2.5 rounded-xl text-center border border-slate-100">
                        Pesanan telah <?= htmlspecialchars($order['status']) ?>
                    </div>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
    </div>
<?php endif; 

$cardsHtml = ob_get_clean(); // Menyimpan seluruh blok HTML di atas

// Jika ini adalah Request AJAX (Auto-Refresh dari JS), keluarkan HTML-nya lalu hentikan eksekusi
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    echo $cardsHtml;
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Maufood</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; }
        
        .order-card {
            animation: fadeIn 0.5s ease-out forwards;
            opacity: 0;
            transform: translateY(10px);
        }
        
        @keyframes fadeIn {
            to { opacity: 1; transform: translateY(0); }
        }
        
        .filter-scroll::-webkit-scrollbar { height: 4px; }
        .filter-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex flex-col">

    <nav class="bg-white/80 backdrop-blur-md shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] sticky top-0 z-50 border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <a href="pesan.php" class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700 px-4 py-2 rounded-xl font-medium text-sm transition-colors duration-200 border border-emerald-100">
                    <i class="fa-solid fa-plus"></i>
                    <span class="hidden sm:inline">Pesanan Baru</span>
                </a>

                <div class="flex-shrink-0 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 text-white flex items-center justify-center shadow-md shadow-green-500/20 relative">
                        <i class="fa-solid fa-utensils text-sm"></i>
                        <!-- Live Indicator -->
                        <span class="absolute top-0 right-0 flex h-2.5 w-2.5">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500 border border-white"></span>
                        </span>
                    </div>
                    <h1 class="font-bold text-xl tracking-tight">Mau<span class="text-green-500">food</span></h1>
                </div>

                <a href="../auth/logout.php" class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-rose-500 hover:bg-rose-50 hover:text-rose-600 transition-colors duration-200" title="Keluar">
                    <i class="fa-solid fa-right-from-bracket text-lg"></i>
                </a>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-slate-800">Kelola Pesanan</h2>
                <span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider animate-pulse flex items-center gap-1"><i class="fa-solid fa-satellite-dish"></i> Live</span>
            </div>
            
            <div class="flex gap-2 overflow-x-auto filter-scroll pb-2 sm:pb-0">
                <?php
                $filters = [
                    'aktif' => 'Aktif',
                    'pending' => 'Pending',
                    'diproses' => 'Diproses',
                    'selesai' => 'Selesai',
                    'dibatalkan' => 'Dibatalkan',
                    'semua' => 'Semua'
                ];
                foreach ($filters as $key => $label):
                    $isActive = $filter === $key;
                    $activeClass = "bg-emerald-500 text-white shadow-md shadow-emerald-500/25 border-transparent";
                    $inactiveClass = "bg-white text-slate-600 border-slate-200 hover:bg-slate-50 hover:border-emerald-300 hover:text-emerald-600";
                    $appliedClass = $isActive ? $activeClass : $inactiveClass;
                ?>
                    <a href="?filter=<?= $key ?>" class="whitespace-nowrap px-4 py-1.5 rounded-full text-sm font-medium border transition-all duration-200 <?= $appliedClass ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Wadah Data Pesanan (Tempat target injeksi AJAX) -->
        <div id="order-container">
            <?= $cardsHtml ?>
        </div>

    </main>

    <!-- SCRIPT AJAX SHORT POLLING -->
    <script>
        let isEditing = false;
        let currentHtml = document.getElementById('order-container').innerHTML;

        // 1. Tahan auto-refresh saat pelayan sedang memilih dropdown status
        document.addEventListener('focusin', function(e) {
            if (e.target && e.target.tagName === 'SELECT') {
                isEditing = true;
            }
        });
        
        document.addEventListener('focusout', function(e) {
            if (e.target && e.target.tagName === 'SELECT') {
                isEditing = false;
            }
        });

        // 2. Fetch ke server setiap 5 detik
        setInterval(() => {
            if (isEditing) return; // Jangan di-refresh jika sedang ganti status

            // Ambil parameter filter dari URL yang sedang aktif
            const urlParams = new URLSearchParams(window.location.search);
            const filter = urlParams.get('filter') || 'aktif';

            // Kirim request di belakang layar (parameter ajax=1)
            fetch(`kelola_pesanan.php?filter=${filter}&ajax=1`)
                .then(res => res.text())
                .then(html => {
                    // Smart Diffing: Hanya ubah tampilan DOM jika memang ada update baru dari sisi server
                    if (html.trim() !== currentHtml.trim()) {
                        document.getElementById('order-container').innerHTML = html;
                        currentHtml = html; // Simpan state terbaru
                    }
                })
                .catch(err => console.error("Auto-refresh error:", err));
        }, 5000); // 5000ms = 5 detik
    </script>
</body>
</html>