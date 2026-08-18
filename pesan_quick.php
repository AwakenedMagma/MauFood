<?php
session_start();
include '../config/koneksi.php';
include '../config/retrain_trigger.php';
include 'rekomendasi.php';

// SECURITY: Check Session & Role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pelayan') {
    header('Location: ../auth/login.php');
    exit;
}

$pelayan_id = (int) $_SESSION['user']['id'];
$error = '';
$success = '';
$selected_member = null;
$member_history = [];
$favorite_menu = [];
$rekomendasiMenu = []; 

// Initialize cart
if (!isset($_SESSION['cart_pelayan'])) {
    $_SESSION['cart_pelayan'] = [];
}

// FUNGSI HELPER - Get Menu Details
function getMenuDetails($conn, $menuId) {
    $stmt = $conn->prepare("SELECT id, nama_menu, harga, kategori, gambar FROM menu WHERE id = ?");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $menuId);
    $stmt->execute();
    $result = $stmt->get_result();
    $menu = $result->fetch_assoc();
    $stmt->close();
    return $menu;
}

// FUNGSI HELPER - Get Member Details
function getMemberDetails($conn, $memberId) {
    $stmt = $conn->prepare("
        SELECT id, nama, email, nomor_telepon, status, total_pesanan, total_pengeluaran 
        FROM member 
        WHERE id = ? AND status = 'aktif'
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();
    return $member;
}

// FUNGSI HELPER - Check Menu Table Structure
function hasMenuStatusColumn($conn) {
    $result = $conn->query("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME='menu'
    ");
    return ($result && $result->num_rows > 0);
}

// FUNGSI HELPER - Validate Table Number
function validateTableNumber($meja) {
    $meja = trim($meja);
    if (empty($meja)) {
        return false;
    }
    if (!preg_match('/^[A-Z0-9]{1,5}$/i', $meja)) {
        return false;
    }
    return true;
}

// FUNGSI HELPER - Validate Quantity
function validateQuantity($jumlah) {
    $jumlah = (int) $jumlah;
    return ($jumlah >= 1 && $jumlah <= 999);
}

// ACTION: Clear Cart
if (isset($_POST['clear_cart'])) {
    $_SESSION['cart_pelayan'] = [];
    unset($_SESSION['selected_member_id']);
    $success = "Keranjang berhasil dikosongkan!";
}

// ACTION: Load Last Order by Member
if (isset($_POST['load_last_order']) && isset($_POST['member_id'])) {
    $member_id = (int) $_POST['member_id'];
    
    $member = getMemberDetails($conn, $member_id);
    if (!$member) {
        $error = "Member tidak ditemukan atau tidak aktif.";
    } else {
        $_SESSION['selected_member_id'] = $member_id;

        $stmt = $conn->prepare("
            SELECT id FROM pesanan 
            WHERE member_id = ? 
            ORDER BY tanggal DESC 
            LIMIT 1
        ");
        if (!$stmt) {
            $error = "Error database: " . htmlspecialchars($conn->error);
        } else {
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $lastOrder = $result->fetch_assoc();
                $pesanan_id = $lastOrder['id'];
                
                $stmtItems = $conn->prepare("
                    SELECT menu_id, jumlah, catatan FROM detail_pesanan WHERE pesanan_id = ?
                ");
                $stmtItems->bind_param("i", $pesanan_id);
                $stmtItems->execute();
                $itemsResult = $stmtItems->get_result();
                
                $_SESSION['cart_pelayan'] = [];
                $itemCount = 0;
                
                while ($item = $itemsResult->fetch_assoc()) {
                    $menuId = (int) $item['menu_id'];
                    $menu = getMenuDetails($conn, $menuId);
                    
                    if ($menu) {
                        $found = false;
                        foreach ($_SESSION['cart_pelayan'] as &$cartItem) {
                            if ($cartItem['menu_id'] === $menuId) {
                                $cartItem['jumlah'] += (int) $item['jumlah'];
                                $found = true;
                                break;
                            }
                        }
                        unset($cartItem);
                        
                        if (!$found) {
                            // Deskripsi/catatan menu dari pesanan terakhir ikut dimuat,
                            // jadi member tidak perlu menulis ulang permintaannya.
                            $_SESSION['cart_pelayan'][] = [
                                'menu_id' => $menuId,
                                'jumlah' => (int) $item['jumlah'],
                                'catatan' => $item['catatan'] ?? ''
                            ];
                        }
                        $itemCount++;
                    }
                }
                
                $success = "Pesanan terakhir Member " . htmlspecialchars($member['nama']) . " dimuat! ({$itemCount} item)";
                $stmtItems->close();
            } else {
                $error = "Member " . htmlspecialchars($member['nama']) . " belum memiliki riwayat pesanan.";
            }
            $stmt->close();
        }
    }
}

// ACTION: Load Specific Order from History
if (isset($_POST['load_order']) && isset($_POST['pesanan_id'])) {
    $pesanan_id = (int) $_POST['pesanan_id'];
    
    $stmtVerify = $conn->prepare("SELECT member_id FROM pesanan WHERE id = ?");
    $stmtVerify->bind_param("i", $pesanan_id);
    $stmtVerify->execute();
    $verifyResult = $stmtVerify->get_result();
    
    if ($verifyResult->num_rows === 0) {
        $error = "Pesanan tidak ditemukan.";
    } else {
        $orderData = $verifyResult->fetch_assoc();
        $member_id = $orderData['member_id'];
        
        $stmtItems = $conn->prepare("SELECT menu_id, jumlah, catatan FROM detail_pesanan WHERE pesanan_id = ?");
        $stmtItems->bind_param("i", $pesanan_id);
        $stmtItems->execute();
        $itemsResult = $stmtItems->get_result();
        
        $_SESSION['cart_pelayan'] = [];
        $itemCount = 0;
        
        while ($item = $itemsResult->fetch_assoc()) {
            $menuId = (int) $item['menu_id'];
            $menu = getMenuDetails($conn, $menuId);
            
            if ($menu) {
                $_SESSION['cart_pelayan'][] = [
                    'menu_id' => $menuId,
                    'jumlah' => (int) $item['jumlah'],
                    'catatan' => $item['catatan'] ?? ''
                ];
                $itemCount++;
            }
        }
        
        if ($member_id) {
            $_SESSION['selected_member_id'] = $member_id;
        }
        $success = "Pesanan berhasil dimuat ke keranjang! ({$itemCount} item)";
        $stmtItems->close();
    }
    $stmtVerify->close();
}

// ACTION: Add Item Manually
if (isset($_POST['tambah_item']) && isset($_POST['menu_id'])) {
    $menuId = (int) $_POST['menu_id'];
    $jumlah = max(1, (int) ($_POST['jumlah'] ?? 1));
    
    if (!validateQuantity($jumlah)) {
        $error = "Jumlah harus antara 1-999.";
    } else {
        $menu = getMenuDetails($conn, $menuId);
        if (!$menu) {
            $error = "Menu tidak ditemukan.";
        } else {
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
    }
}

// ACTION: Simpan deskripsi/catatan menu untuk salah satu item di keranjang
if (isset($_POST['update_catatan']) && isset($_POST['menu_id'])) {
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

// ACTION: Gunakan deskripsi menu yang pernah diminta member ini sebelumnya
if (isset($_POST['gunakan_deskripsi_sebelumnya'])) {
    $menuId = (int) $_POST['gunakan_deskripsi_sebelumnya'];
    $member_id_note = isset($_SESSION['selected_member_id']) ? (int) $_SESSION['selected_member_id'] : null;

    if ($member_id_note) {
        $stmtCatatan = $conn->prepare("
            SELECT dp.catatan
            FROM detail_pesanan dp
            JOIN pesanan p ON p.id = dp.pesanan_id
            WHERE p.member_id = ? AND dp.menu_id = ?
              AND dp.catatan IS NOT NULL AND dp.catatan != ''
            ORDER BY p.tanggal DESC
            LIMIT 1
        ");
        $stmtCatatan->bind_param("ii", $member_id_note, $menuId);
        $stmtCatatan->execute();
        $catatanRow = $stmtCatatan->get_result()->fetch_assoc();
        $stmtCatatan->close();

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

// ACTION: Remove Item from Cart
if (isset($_POST['hapus_item'])) {
    $menuId = (int) $_POST['hapus_item'];
    $_SESSION['cart_pelayan'] = array_values(array_filter(
        $_SESSION['cart_pelayan'],
        fn($item) => $item['menu_id'] !== $menuId
    ));
}

// ACTION: Update Item Quantity
if (isset($_POST['update_quantity']) && isset($_POST['menu_id'])) {
    $menuId = (int) $_POST['menu_id'];
    $jumlah = max(1, (int) $_POST['jumlah']);
    
    if (!validateQuantity($jumlah)) {
        $error = "Jumlah harus antara 1-999.";
    } else {
        foreach ($_SESSION['cart_pelayan'] as &$item) {
            if ($item['menu_id'] === $menuId) {
                $item['jumlah'] = $jumlah;
                break;
            }
        }
        unset($item);
    }
}

// ACTION: Submit Order (MAIN ACTION)
if (isset($_POST['submit_pesanan'])) {
    $meja = trim($_POST['meja'] ?? '');
    $member_id = isset($_SESSION['selected_member_id']) ? (int) $_SESSION['selected_member_id'] : null;
    
    if (!validateTableNumber($meja)) {
        $error = 'Nomor meja tidak valid. Gunakan format: A1, B2, etc.';
    } elseif (count($_SESSION['cart_pelayan']) === 0) {
        $error = 'Belum ada menu di keranjang.';
    } elseif ($member_id && !getMemberDetails($conn, $member_id)) {
        $error = 'Member tidak valid.';
    } else {
        $total = 0;
        $itemsValid = [];
        
        foreach ($_SESSION['cart_pelayan'] as $item) {
            $menuId = (int) $item['menu_id'];
            $menu = getMenuDetails($conn, $menuId);
            
            if (!$menu) {
                $error = "Menu ID {$menuId} tidak ditemukan.";
                break;
            }
            
            $jumlah = max(1, (int) $item['jumlah']);
            if (!validateQuantity($jumlah)) {
                $error = "Jumlah item tidak valid.";
                break;
            }
            
            $subtotal = (int) $menu['harga'] * $jumlah;
            $total += $subtotal;
            $itemsValid[] = [
                'menu_id' => $menuId,
                'menu' => $menu,
                'jumlah' => $jumlah,
                'subtotal' => $subtotal,
                'catatan' => trim($item['catatan'] ?? '')
            ];
        }
        
        if (empty($error) && count($itemsValid) === 0) {
            $error = 'Tidak ada menu valid di keranjang.';
        }
        
        if (empty($error)) {
            $conn->begin_transaction();
            
            try {
                $stmtOrder = $conn->prepare("
                    INSERT INTO pesanan (user_id, member_id, total, status, meja, tipe, dibuat_oleh)
                    VALUES (?, ?, ?, 'pending', ?, 'dine-in', ?)
                ");
                
                if (!$stmtOrder) {
                    throw new Exception("Prepare order failed: " . htmlspecialchars($conn->error));
                }
                
                $stmtOrder->bind_param(
                    "iiisi",
                    $pelayan_id,
                    $member_id,
                    $total,
                    $meja,
                    $pelayan_id
                );
                
                if (!$stmtOrder->execute()) {
                    throw new Exception("Execute order failed: " . htmlspecialchars($stmtOrder->error));
                }
                
                $pesanan_id = $conn->insert_id;
                $stmtOrder->close();
                
                $stmtDetail = $conn->prepare("
                    INSERT INTO detail_pesanan (pesanan_id, menu_id, jumlah, subtotal, catatan)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                if (!$stmtDetail) {
                    throw new Exception("Prepare detail failed: " . htmlspecialchars($conn->error));
                }
                
                foreach ($itemsValid as $item) {
                    $menuId = $item['menu_id'];
                    $jumlah = $item['jumlah'];
                    $subtotal = $item['subtotal'];
                    $catatanItem = $item['catatan'];
                    
                    $stmtDetail->bind_param("iiiis", $pesanan_id, $menuId, $jumlah, $subtotal, $catatanItem);
                    
                    if (!$stmtDetail->execute()) {
                        throw new Exception("Insert detail failed: " . htmlspecialchars($stmtDetail->error));
                    }
                }
                $stmtDetail->close();
                
                if ($member_id) {
                    $stmtMember = $conn->prepare("
                        UPDATE member 
                        SET total_pesanan = total_pesanan + 1, 
                            total_pengeluaran = total_pengeluaran + ? 
                        WHERE id = ?
                    ");
                    
                    if (!$stmtMember) {
                        throw new Exception("Prepare member update failed: " . htmlspecialchars($conn->error));
                    }
                    
                    $stmtMember->bind_param("ii", $total, $member_id);
                    if (!$stmtMember->execute()) {
                        throw new Exception("Update member failed: " . htmlspecialchars($stmtMember->error));
                    }
                    $stmtMember->close();
                }
                
                $conn->commit();

                // Pesanan tersimpan -> segarkan model AI di background agar pola
                // transaksi terbaru langsung terpakai untuk rekomendasi berikutnya.
                triggerRetrainRailway();

                $memberInfoStr = "";
                if ($member_id) {
                    $memData = getMemberDetails($conn, $member_id);
                    if ($memData) {
                        $memberInfoStr = " (Member: " . htmlspecialchars($memData['nama']) . ")";
                    }
                }
                $success = "Pesanan meja " . htmlspecialchars($meja) . " berhasil dibuat!" . $memberInfoStr;
                
                $_SESSION['cart_pelayan'] = [];
                unset($_SESSION['selected_member_id']);
                $selected_member = null;
                $member_history = [];
                $rekomendasiMenu = [];
                $_POST = [];
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// LOAD DATA: Menampilkan Member & Riwayatnya & Rekomendasi ML
if (isset($_SESSION['selected_member_id'])) {
    $member_id = (int) $_SESSION['selected_member_id'];
    $selected_member = getMemberDetails($conn, $member_id);
    
    if ($selected_member) {
        $stmtHistory = $conn->prepare("
            SELECT id, tanggal, total, status, meja
            FROM pesanan
            WHERE member_id = ?
            ORDER BY tanggal DESC
            LIMIT 5
        ");
        $stmtHistory->bind_param("i", $member_id);
        $stmtHistory->execute();
        $historyResult = $stmtHistory->get_result();
        
        while ($row = $historyResult->fetch_assoc()) {
            $member_history[] = $row;
        }
        $stmtHistory->close();

        // AMBIL REKOMENDASI (COLLABORATIVE FILTERING VIA PYTHON API)
        $userIdStr = 'U' . str_pad($member_id, 3, '0', STR_PAD_LEFT);
        $rekomendasiMenu = rekomendasikanMenu($conn, $userIdStr, false, '', '', '');
    }
}

$hasStatusColumn = hasMenuStatusColumn($conn);
$dataMenu = null;

if ($hasStatusColumn) {
    $dataMenu = mysqli_query($conn, "
        SELECT id, nama_menu, harga, kategori, gambar FROM menu  
        ORDER BY kategori, nama_menu
    ");
} else {
    $dataMenu = mysqli_query($conn, "
        SELECT id, nama_menu, harga, kategori, gambar FROM menu 
        ORDER BY kategori, nama_menu
    ");
    
    if (!isset($_SESSION['status_warning_shown'])) {
        $error = "WARNING: Tabel menu belum punya kolom 'status'. Silakan jalankan database migration.";
        $_SESSION['status_warning_shown'] = true;
    }
}

if (!$dataMenu && $hasStatusColumn) {
    $error = "Error loading menu: " . htmlspecialchars($conn->error);
}

// Calculate cart totals
$cartTotal = 0;
$cartDetail = [];

foreach ($_SESSION['cart_pelayan'] as $item) {
    $menuId = (int) $item['menu_id'];
    $menu = getMenuDetails($conn, $menuId);
    
    if ($menu) {
        $jumlah = max(1, (int) $item['jumlah']);
        $subtotal = (int) $menu['harga'] * $jumlah;
        $cartTotal += $subtotal;
        $cartDetail[] = [
            'menu' => $menu,
            'jumlah' => $jumlah,
            'subtotal' => $subtotal,
            'catatan' => $item['catatan'] ?? ''
        ];
    }
}

// Helper badge color
function getStatusBadgeClass($status) {
    return match (strtolower($status)) {
        'pending' => 'bg-amber-100 text-amber-700 border-amber-200',
        'diproses' => 'bg-sky-100 text-sky-700 border-sky-200',
        'selesai' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'dibatalkan' => 'bg-rose-100 text-rose-700 border-rose-200',
        default => 'bg-slate-100 text-slate-700 border-slate-200'
    };
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Order - Maufood</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts (Poppins) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; }
        
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }

        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; margin: 0; 
        }
        input[type=number] { -moz-appearance: textfield; }
        
        @media (min-width: 1024px) {
            .cart-sticky { position: sticky; top: 5.5rem; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <nav class="bg-white/80 backdrop-blur-md shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] sticky top-0 z-50 border-b border-slate-100">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex-shrink-0 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 text-white flex items-center justify-center shadow-md shadow-orange-500/20">
                        <i class="fa-solid fa-bolt text-sm"></i>
                    </div>
                    <h1 class="font-bold text-xl tracking-tight hidden sm:block">Quick <span class="text-orange-500">Order</span></h1>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="pesan.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors border border-slate-200 bg-white">
                        <i class="fa-solid fa-utensils"></i> <span class="hidden md:inline">Regular Order</span>
                    </a>
                    <div class="w-px h-6 bg-slate-200 mx-1"></div>
                    <a href="../auth/logout.php" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-rose-500 hover:bg-rose-50 hover:text-rose-600 transition-colors" title="Keluar">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-[1400px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col lg:flex-row gap-6">
        
        <!-- Left Column -->
        <div class="flex-grow w-full lg:w-auto space-y-6">
            
            <?php if ($error): ?>
                <div class="bg-rose-50 text-rose-600 p-4 rounded-xl text-sm border border-rose-100 flex items-start gap-3 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl text-sm border border-emerald-100 flex items-start gap-3 shadow-sm">
                    <i class="fa-solid fa-circle-check mt-0.5"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                    <i class="fa-solid fa-users text-blue-500"></i>
                    <h2 class="font-semibold text-slate-800 text-sm">Pilih Member (Quick Load)</h2>
                </div>
                <div class="p-5">
                    <form method="POST" class="flex flex-col md:flex-row gap-3">
                        <div class="relative flex-grow">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                            </div>
                            <select name="member_id" class="w-full pl-10 pr-4 py-2.5 appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all cursor-pointer" required>
                                <option value="">Cari Member</option>
                                <?php
                                $memberQuery = mysqli_query($conn, "SELECT id, nama, total_pesanan FROM member WHERE status = 'aktif' ORDER BY nama");
                                while ($m = mysqli_fetch_assoc($memberQuery)):
                                    $selected_val = (isset($_SESSION['selected_member_id']) && $_SESSION['selected_member_id'] == $m['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $m['id'] ?>" <?= $selected_val ?>>
                                        <?= htmlspecialchars($m['nama']) ?> (<?= $m['total_pesanan'] ?> pesanan)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                        <button type="submit" name="load_last_order" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2.5 px-5 rounded-xl text-sm transition-colors shadow-sm shadow-blue-500/20 flex items-center justify-center gap-2 flex-shrink-0">
                            <i class="fa-solid fa-history"></i> Load Last Order
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($selected_member): ?>
                <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl border border-emerald-100 p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 relative overflow-hidden">
                    <i class="fa-solid fa-star absolute -right-4 -bottom-4 text-8xl text-emerald-500/5 rotate-12 pointer-events-none"></i>
                    
                    <div class="relative z-10">
                        <div class="flex items-center gap-2 text-emerald-600 text-xs font-bold uppercase tracking-wider mb-1">
                            <i class="fa-solid fa-crown text-amber-500"></i> Member Aktif
                        </div>
                        <h3 class="font-bold text-emerald-900 text-xl">#<?= (int) $selected_member['id'] ?> - <?= htmlspecialchars($selected_member['nama']) ?></h3>
                        <div class="flex flex-wrap items-center gap-3 text-emerald-700/80 text-sm mt-1">
                            <span class="flex items-center gap-1.5"><i class="fa-solid fa-phone text-xs"></i> <?= htmlspecialchars($selected_member['nomor_telepon'] ?? '-') ?></span>
                            <span class="w-1 h-1 rounded-full bg-emerald-300"></span>
                            <span><?= $selected_member['total_pesanan'] ?>x Pesanan</span>
                            <span class="w-1 h-1 rounded-full bg-emerald-300"></span>
                            <span class="font-semibold text-emerald-800">Total: Rp <?= number_format($selected_member['total_pengeluaran'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($member_history)): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-slate-500"></i>
                            <h2 class="font-semibold text-slate-800 text-sm">Riwayat 5 Pesanan Terakhir</h2>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($member_history as $hist): ?>
                                <form method="POST">
                                    <input type="hidden" name="pesanan_id" value="<?= (int) $hist['id'] ?>">
                                    <button type="submit" name="load_order" class="w-full text-left p-4 hover:bg-slate-50 transition-colors group flex items-center justify-between gap-4">
                                        <div class="flex-grow min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-xs font-semibold px-2 py-0.5 rounded-md border capitalize <?= getStatusBadgeClass($hist['status']) ?>">
                                                    <?= htmlspecialchars($hist['status']) ?>
                                                </span>
                                                <?php if($hist['meja']): ?>
                                                    <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200">
                                                        Meja <?= htmlspecialchars($hist['meja']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm font-medium text-slate-700 flex items-center gap-1.5">
                                                <i class="fa-regular fa-calendar text-slate-400"></i> <?= date('d M Y H:i', strtotime($hist['tanggal'])) ?>
                                            </div>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <div class="font-bold text-emerald-600 group-hover:text-emerald-700 transition-colors">Rp <?= number_format($hist['total'], 0, ',', '.') ?></div>
                                            <div class="text-xs text-blue-500 font-medium opacity-0 group-hover:opacity-100 transition-opacity mt-0.5 flex items-center justify-end gap-1">
                                                <i class="fa-solid fa-rotate-right"></i> Load Ulang
                                            </div>
                                        </div>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- BAGIAN BARU: CARD REKOMENDASI AI -->
                <?php if (!empty($rekomendasiMenu)): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-wand-magic-sparkles text-indigo-500"></i>
                                <h2 class="font-semibold text-slate-800 text-sm">Rekomendasi Menu</h2>
                            </div>
                            <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-md font-medium"><i class="fa-solid fa-user-group"></i> Berdasarkan Pola Pelanggan Serupa</span>
                        </div>
                        <div class="p-5 bg-indigo-50/30">
                            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-5">
                                <?php foreach ($rekomendasiMenu as $row): 
                                    $imgSrc = !empty($row['gambar']) ? '../upload/' . htmlspecialchars($row['gambar']) : 'https://placehold.co/160x120?text=No+Image';
                                ?>
                                    <form method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md hover:border-indigo-200 transition-all group flex flex-col">
                                        <input type="hidden" name="menu_id" value="<?= (int) $row['id'] ?>">
                                        <div class="h-32 bg-slate-100 overflow-hidden relative">
                                            <!-- Badge Metode Machine Learning -->
                                            <div class="absolute top-2 left-2 z-10 bg-indigo-600/90 backdrop-blur-sm text-white text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 max-w-[90%]">
                                                <i class="fa-solid fa-microchip"></i> <span class="truncate"><?= htmlspecialchars($row['metode_rekomendasi'] ?? 'AI') ?></span>
                                            </div>
                                            <div class="absolute bottom-2 right-2 z-10 bg-white/90 backdrop-blur-sm text-indigo-700 text-[10px] font-bold px-2 py-1 rounded shadow-sm">
                                                Match: <?= round((float) ($row['skor_rekomendasi'] ?? 0) * 100) ?>%
                                            </div>
                                            <img src="<?= $imgSrc ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="<?= htmlspecialchars($row['nama_menu']) ?>">
                                        </div>
                                        <div class="p-3.5 flex flex-col flex-grow">
                                            <h4 class="font-semibold text-slate-800 text-sm mb-1 line-clamp-2 leading-tight flex-grow"><?= htmlspecialchars($row['nama_menu']) ?></h4>
                                            <div class="font-bold text-emerald-600 text-[15px] mb-3">Rp <?= number_format($row['harga'], 0, ',', '.') ?></div>
                                            <div class="flex gap-2 mt-auto">
                                                <input type="number" name="jumlah" value="1" min="1" class="w-1/3 bg-slate-50 border border-slate-200 rounded-lg text-center text-sm font-medium focus:outline-none focus:border-indigo-500">
                                                <button type="submit" name="tambah_item" class="w-2/3 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg py-1.5 text-sm font-semibold transition-colors flex items-center justify-center gap-1.5 shadow-sm shadow-indigo-500/20">
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
                <!-- END BAGIAN BARU -->

            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-8">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
                    <i class="fa-solid fa-utensils text-emerald-500"></i>
                    <h2 class="font-semibold text-slate-800 text-sm">Semua Menu</h2>
                </div>
                <div class="p-5">
                    <?php
                    if (!$dataMenu) {
                        echo "<div class='text-center text-rose-500 p-4 bg-rose-50 rounded-xl'>❌ Error loading menu</div>";
                    } else {
                        $currentCategory = '';
                        $categoryHtml = '';
                        
                        while ($menu = mysqli_fetch_assoc($dataMenu)):
                            if ($currentCategory !== $menu['kategori']):
                                if ($categoryHtml) echo '<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-5 mb-8">' . $categoryHtml . '</div>';
                                
                                $catName = htmlspecialchars($menu['kategori'] ?? 'Uncategorized');
                                echo '<h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 mt-2 flex items-center gap-2">';
                                echo '<i class="fa-solid fa-tag text-emerald-500"></i> ' . $catName;
                                echo '<span class="flex-grow h-px bg-slate-100 ml-2"></span></h3>';
                                
                                $currentCategory = $menu['kategori'];
                                $categoryHtml = '';
                            endif;
                            
                            $imgSrc = (isset($menu['gambar']) && !empty($menu['gambar'])) ? '../upload/' . htmlspecialchars($menu['gambar']) : 'https://via.placeholder.com/160x120?text=' . urlencode($menu['nama_menu']);
                            
                            $categoryHtml .= '
                            <form method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md hover:border-emerald-200 transition-all group flex flex-col">
                                <input type="hidden" name="menu_id" value="' . (int) $menu['id'] . '">
                                <div class="h-32 bg-slate-100 overflow-hidden relative">
                                    <img src="' . $imgSrc . '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="' . htmlspecialchars($menu['nama_menu']) . '">
                                </div>
                                <div class="p-3.5 flex flex-col flex-grow">
                                    <h4 class="font-semibold text-slate-800 text-sm mb-1 line-clamp-2 leading-tight flex-grow">' . htmlspecialchars($menu['nama_menu']) . '</h4>
                                    <div class="font-bold text-emerald-600 text-[15px] mb-3">Rp ' . number_format($menu['harga'], 0, ',', '.') . '</div>
                                    <div class="flex gap-2 mt-auto">
                                        <input type="number" name="jumlah" value="1" min="1" class="w-1/3 bg-slate-50 border border-slate-200 rounded-lg text-center text-sm font-medium focus:outline-none focus:border-emerald-500">
                                        <button type="submit" name="tambah_item" class="w-2/3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg py-1.5 text-sm font-semibold transition-colors flex items-center justify-center gap-1.5 shadow-sm shadow-emerald-500/20">
                                            <i class="fa-solid fa-plus text-xs"></i> Tambah
                                        </button>
                                    </div>
                                </div>
                            </form>
                            ';
                        endwhile;
                        
                        if ($categoryHtml) echo '<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-5">' . $categoryHtml . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column (Cart) -->
        <div class="w-full lg:w-[380px] flex-shrink-0">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden cart-sticky flex flex-col max-h-[calc(100vh-8rem)]">
                
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-shopping-basket text-emerald-500"></i>
                        <h2 class="font-semibold text-slate-800 text-sm">Keranjang</h2>
                    </div>
                    <?php if (count($cartDetail) > 0): ?>
                        <span class="bg-emerald-500 text-white text-xs font-bold px-2.5 py-0.5 rounded-full shadow-sm animate-pulse">
                            <?= count($cartDetail) ?> Item
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="flex-grow overflow-y-auto custom-scroll p-4 bg-slate-50/30">
                    <?php if (count($cartDetail) === 0): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-slate-100 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fa-solid fa-cart-arrow-down text-3xl"></i>
                            </div>
                            <p class="text-slate-500 font-medium text-sm">Keranjang masih kosong</p>
                            <p class="text-slate-400 text-xs mt-1">Pilih menu dari samping atau muat pesanan terakhir</p>
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
                                            <span class="text-emerald-600 font-semibold text-sm">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></span>
                                            
                                            <form method="POST" class="flex items-center gap-1 bg-slate-50 border border-slate-200 rounded-md p-0.5">
                                                <input type="hidden" name="menu_id" value="<?= (int) $item['menu']['id'] ?>">
                                                <button type="submit" name="update_quantity" class="w-6 h-6 flex items-center justify-center text-slate-500 hover:bg-slate-200 hover:text-slate-700 rounded transition-colors" onclick="const inp=this.form.querySelector('[name=jumlah]'); inp.value = Math.max(1, (parseInt(inp.value) || 1) - 1);">
                                                    <i class="fa-solid fa-minus text-[10px]"></i>
                                                </button>
                                                <input type="number" name="jumlah" value="<?= (int) $item['jumlah'] ?>" class="w-6 text-center text-xs font-semibold bg-transparent border-none focus:outline-none text-slate-700 p-0 pointer-events-none" readonly>
                                                <button type="submit" name="update_quantity" class="w-6 h-6 flex items-center justify-center text-slate-500 hover:bg-emerald-100 hover:text-emerald-600 rounded transition-colors" onclick="const inp=this.form.querySelector('[name=jumlah]'); inp.value = (parseInt(inp.value) || 1) + 1;">
                                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                                </button>
                                            </form>
                                        </div>

                                        <!-- Deskripsi / Catatan Menu -->
                                        <form method="POST" class="flex items-center gap-1.5 mt-2">
                                            <input type="hidden" name="menu_id" value="<?= (int) $item['menu']['id'] ?>">
                                            <input type="text" name="catatan" maxlength="255" placeholder="Catatan menu (mis. tidak pedas, tanpa bawang)"
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
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (count($cartDetail) > 0): ?>
                <div class="p-4 bg-white border-t border-slate-100">
                    <div class="bg-slate-800 text-white p-4 rounded-xl mb-4 shadow-sm">
                        <div class="flex justify-between text-slate-300 text-sm mb-1.5">
                            <span>Subtotal</span>
                            <span>Rp <?= number_format($cartTotal, 0, ',', '.') ?></span>
                        </div>
                        <div class="w-full h-px bg-slate-700 mb-3 mt-3"></div>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-slate-200 text-sm">Total Bayar</span>
                            <span class="font-bold text-xl text-emerald-400">Rp <?= number_format($cartTotal, 0, ',', '.') ?></span>
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
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nomor Meja Pelanggan <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-hashtag text-slate-400"></i>
                                </div>
                                <input type="text" name="meja" placeholder="A1, B3..." maxlength="5" class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 text-slate-800 font-medium text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all uppercase" required>
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
</body>
</html>