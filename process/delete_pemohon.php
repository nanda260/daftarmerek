<?php
session_start();
require_once 'config_db.php';

header('Content-Type: application/json');

// Cek apakah user adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action !== 'delete_pemohon') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

$nik = $_POST['nik'] ?? '';

if (empty($nik)) {
    echo json_encode(['success' => false, 'message' => 'NIK tidak valid']);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // 1. Cek apakah user memiliki pendaftaran
    $query_check = "SELECT id_pendaftaran FROM pendaftaran WHERE NIK = :nik";
    $stmt_check = $pdo->prepare($query_check);
    $stmt_check->bindParam(':nik', $nik, PDO::PARAM_STR);
    $stmt_check->execute();
    $pendaftaran_list = $stmt_check->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($pendaftaran_list)) {
        // 2. Hapus lampiran untuk setiap pendaftaran
        foreach ($pendaftaran_list as $id_pendaftaran) {
            // Ambil file path lampiran untuk dihapus dari storage
            $query_lampiran = "SELECT file_path FROM lampiran WHERE id_pendaftaran = :id_pendaftaran";
            $stmt_lampiran = $pdo->prepare($query_lampiran);
            $stmt_lampiran->bindParam(':id_pendaftaran', $id_pendaftaran, PDO::PARAM_INT);
            $stmt_lampiran->execute();
            $files = $stmt_lampiran->fetchAll(PDO::FETCH_COLUMN);

            // Hapus file fisik
            foreach ($files as $file_path) {
                $full_path = '../' . $file_path;
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }

            // Hapus record lampiran dari database
            $query_delete_lampiran = "DELETE FROM lampiran WHERE id_pendaftaran = :id_pendaftaran";
            $stmt_delete_lampiran = $pdo->prepare($query_delete_lampiran);
            $stmt_delete_lampiran->bindParam(':id_pendaftaran', $id_pendaftaran, PDO::PARAM_INT);
            $stmt_delete_lampiran->execute();

            // 3. Hapus merek
            // Ambil logo path untuk dihapus
            $query_merek = "SELECT logo1, logo2, logo3 FROM merek WHERE id_pendaftaran = :id_pendaftaran";
            $stmt_merek = $pdo->prepare($query_merek);
            $stmt_merek->bindParam(':id_pendaftaran', $id_pendaftaran, PDO::PARAM_INT);
            $stmt_merek->execute();
            $merek = $stmt_merek->fetch();

            if ($merek) {
                // Hapus file logo
                foreach (['logo1', 'logo2', 'logo3'] as $logo_field) {
                    if (!empty($merek[$logo_field])) {
                        $full_path = '../' . $merek[$logo_field];
                        if (file_exists($full_path)) {
                            unlink($full_path);
                        }
                    }
                }
            }

            // Hapus record merek
            $query_delete_merek = "DELETE FROM merek WHERE id_pendaftaran = :id_pendaftaran";
            $stmt_delete_merek = $pdo->prepare($query_delete_merek);
            $stmt_delete_merek->bindParam(':id_pendaftaran', $id_pendaftaran, PDO::PARAM_INT);
            $stmt_delete_merek->execute();

            // 4. Hapus notifikasi
            $query_delete_notif = "DELETE FROM notifikasi WHERE id_pendaftaran = :id_pendaftaran";
            $stmt_delete_notif = $pdo->prepare($query_delete_notif);
            $stmt_delete_notif->bindParam(':id_pendaftaran', $id_pendaftaran, PDO::PARAM_INT);
            $stmt_delete_notif->execute();
        }

        // 5. Hapus data usaha yang terkait dengan pendaftaran user ini
        $query_usaha_ids = "SELECT DISTINCT id_usaha FROM pendaftaran WHERE NIK = :nik";
        $stmt_usaha_ids = $pdo->prepare($query_usaha_ids);
        $stmt_usaha_ids->bindParam(':nik', $nik, PDO::PARAM_STR);
        $stmt_usaha_ids->execute();
        $usaha_ids = $stmt_usaha_ids->fetchAll(PDO::FETCH_COLUMN);

        // 6. Hapus pendaftaran
        $query_delete_pendaftaran = "DELETE FROM pendaftaran WHERE NIK = :nik";
        $stmt_delete_pendaftaran = $pdo->prepare($query_delete_pendaftaran);
        $stmt_delete_pendaftaran->bindParam(':nik', $nik, PDO::PARAM_STR);
        $stmt_delete_pendaftaran->execute();

        // 7. Hapus data usaha
        if (!empty($usaha_ids)) {
            $placeholders = str_repeat('?,', count($usaha_ids) - 1) . '?';
            $query_delete_usaha = "DELETE FROM datausaha WHERE id_usaha IN ($placeholders)";
            $stmt_delete_usaha = $pdo->prepare($query_delete_usaha);
            $stmt_delete_usaha->execute($usaha_ids);
        }
    }

    // 8. Hapus foto KTP dari storage
    $query_ktp = "SELECT foto_ktp FROM user WHERE NIK_NIP = :nik";
    $stmt_ktp = $pdo->prepare($query_ktp);
    $stmt_ktp->bindParam(':nik', $nik, PDO::PARAM_STR);
    $stmt_ktp->execute();
    $user_data = $stmt_ktp->fetch();

    if ($user_data && !empty($user_data['foto_ktp'])) {
        $full_path = '../' . $user_data['foto_ktp'];
        if (file_exists($full_path)) {
            unlink($full_path);
        }
    }

    // 9. Hapus user dari database
    $query_delete_user = "DELETE FROM user WHERE NIK_NIP = :nik";
    $stmt_delete_user = $pdo->prepare($query_delete_user);
    $stmt_delete_user->bindParam(':nik', $nik, PDO::PARAM_STR);
    $stmt_delete_user->execute();

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Data pemohon berhasil dihapus beserta semua data pendaftaran terkait'
    ]);

} catch (PDOException $e) {
    // Rollback jika terjadi error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>