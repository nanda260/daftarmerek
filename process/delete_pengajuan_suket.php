<?php
session_start();

include 'config_db.php';

// Cek login admin
if (!isset($_SESSION['NIK_NIP']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login.php");
    exit;
}

// Cek apakah ada parameter id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../pengajuan-suket.php");
    exit;
}

$id_pengajuan = (int)$_GET['id'];

try {
    // Mulai transaksi
    $pdo->beginTransaction();

    // 1. Ambil data pengajuan terlebih dahulu untuk mendapatkan file-file yang perlu dihapus
    $stmt = $pdo->prepare("SELECT * FROM pengajuansurat WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pengajuan) {
        throw new Exception("Data pengajuan tidak ditemukan");
    }

    // 2. Hapus file-file yang terkait
    $files_to_delete = [];

    if (!empty($pengajuan['logo_merek']) && file_exists("../" . $pengajuan['logo_merek'])) {
        $files_to_delete[] = "../" . $pengajuan['logo_merek'];
    }

    if (!empty($pengajuan['nib_file']) && file_exists("../" . $pengajuan['nib_file'])) {
        $files_to_delete[] = "../" . $pengajuan['nib_file'];
    }

    if (!empty($pengajuan['akta_file']) && file_exists("../" . $pengajuan['akta_file'])) {
        $files_to_delete[] = "../" . $pengajuan['akta_file'];
    }

    if (!empty($pengajuan['suratpermohonan_file']) && file_exists("../" . $pengajuan['suratpermohonan_file'])) {
        $files_to_delete[] = "../" . $pengajuan['suratpermohonan_file'];
    }

    if (!empty($pengajuan['file_surat_keterangan']) && file_exists("../" . $pengajuan['file_surat_keterangan'])) {
        $files_to_delete[] = "../" . $pengajuan['file_surat_keterangan'];
    }

    // Hapus file foto produk (JSON array)
    if (!empty($pengajuan['foto_produk'])) {
        $foto_produk_arr = json_decode($pengajuan['foto_produk'], true);
        if (is_array($foto_produk_arr)) {
            foreach ($foto_produk_arr as $file) {
                if (file_exists("../" . $file)) {
                    $files_to_delete[] = "../" . $file;
                }
            }
        }
    }

    // Hapus file foto proses (JSON array)
    if (!empty($pengajuan['foto_proses'])) {
        $foto_proses_arr = json_decode($pengajuan['foto_proses'], true);
        if (is_array($foto_proses_arr)) {
            foreach ($foto_proses_arr as $file) {
                if (file_exists("../" . $file)) {
                    $files_to_delete[] = "../" . $file;
                }
            }
        }
    }

    // Hapus semua file yang ada
    foreach ($files_to_delete as $file) {
        if (!unlink($file)) {
            error_log("Gagal menghapus file: " . $file);
        }
    }

    // 3. Hapus data dari database
    $stmt = $pdo->prepare("DELETE FROM pengajuansurat WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);

    // Commit transaksi
    $pdo->commit();

    // Redirect ke halaman pengajuan-suket dengan pesan sukses
    header("Location: ../pengajuan-suket.php?delete=success");
    exit;

} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Error delete pengajuan suket: " . $e->getMessage());
    header("Location: ../pengajuan-suket.php?delete=failed");
    exit;
}
?>