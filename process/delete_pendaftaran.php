<?php
session_start();
require_once 'config_db.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['NIK_NIP']) || $_SESSION['role'] != 'Admin') {
    $_SESSION['error_message'] = 'Unauthorized access';
    header("Location: ../login.php");
    exit;
}

// Cek apakah ada ID pendaftaran
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'ID pendaftaran tidak valid';
    header("Location: ../dashboard-admin.php");
    exit;
}

$id_pendaftaran = intval($_GET['id']);

try {
    // Mulai transaction
    $pdo->beginTransaction();
    
    // 1. Ambil data pendaftaran untuk mendapatkan id_usaha dan file-file yang terkait
    $stmt = $pdo->prepare("
        SELECT p.id_usaha, p.NIK, m.logo1, m.logo2, m.logo3
        FROM pendaftaran p
        LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
        WHERE p.id_pendaftaran = ?
    ");
    $stmt->execute([$id_pendaftaran]);
    $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pendaftaran) {
        throw new Exception('Data pendaftaran tidak ditemukan');
    }
    
    $id_usaha = $pendaftaran['id_usaha'];
    $nik = $pendaftaran['NIK'];
    
    // 2. Ambil semua file lampiran yang terkait
    $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ?");
    $stmt->execute([$id_pendaftaran]);
    $lampiran_files = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 3. Hapus file lampiran dari server
    foreach ($lampiran_files as $file_path) {
        if (!empty($file_path) && file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // 4. Hapus file logo merek dari server
    $logo_files = [$pendaftaran['logo1'], $pendaftaran['logo2'], $pendaftaran['logo3']];
    foreach ($logo_files as $logo_path) {
        if (!empty($logo_path) && file_exists($logo_path)) {
            unlink($logo_path);
        }
    }
    
    // 5. Hapus data dari tabel notifikasi
    $stmt = $pdo->prepare("DELETE FROM notifikasi WHERE id_pendaftaran = ?");
    $stmt->execute([$id_pendaftaran]);
    
    // 6. Hapus data dari tabel lampiran
    $stmt = $pdo->prepare("DELETE FROM lampiran WHERE id_pendaftaran = ?");
    $stmt->execute([$id_pendaftaran]);
    
    // 7. Hapus data dari tabel merek
    $stmt = $pdo->prepare("DELETE FROM merek WHERE id_pendaftaran = ?");
    $stmt->execute([$id_pendaftaran]);
    
    // 8. Hapus data dari tabel pendaftaran
    $stmt = $pdo->prepare("DELETE FROM pendaftaran WHERE id_pendaftaran = ?");
    $stmt->execute([$id_pendaftaran]);
    
    // 9. Hapus data dari tabel datausaha (jika tidak ada pendaftaran lain yang menggunakan usaha ini)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pendaftaran WHERE id_usaha = ?");
    $stmt->execute([$id_usaha]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        // Tidak ada pendaftaran lain yang menggunakan usaha ini, hapus data usaha
        $stmt = $pdo->prepare("DELETE FROM datausaha WHERE id_usaha = ?");
        $stmt->execute([$id_usaha]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Set success message
    $_SESSION['success_message'] = 'Data pendaftaran berhasil dihapus';
    
    // Log aktivitas admin (opsional)
    $log_message = "Admin " . $_SESSION['nama_lengkap'] . " menghapus data pendaftaran ID: " . $id_pendaftaran;
    error_log($log_message);
    
} catch (Exception $e) {
    // Rollback jika terjadi error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error delete pendaftaran: " . $e->getMessage());
    $_SESSION['error_message'] = 'Gagal menghapus data: ' . $e->getMessage();
}

// Redirect kembali ke dashboard
header("Location: ../dashboard-admin.php");
exit;
?>