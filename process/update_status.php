<?php
session_start();
require_once 'config_db.php';
require_once 'notification_helper.php'; // TAMBAHKAN INI

// Cek apakah user adalah admin
if (!isset($_SESSION['NIK_NIP']) || $_SESSION['role'] != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Cek method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Ambil data dari request
$id_pendaftaran = isset($_POST['id_pendaftaran']) ? intval($_POST['id_pendaftaran']) : 0;
$status_baru = isset($_POST['status']) ? trim($_POST['status']) : '';
$alasan = isset($_POST['alasan']) ? trim($_POST['alasan']) : '';
$merek_dipilih = isset($_POST['merek_dipilih']) ? intval($_POST['merek_dipilih']) : 0;

// Validasi input
if ($id_pendaftaran <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID pendaftaran tidak valid']);
    exit;
}

if (empty($status_baru)) {
    echo json_encode(['success' => false, 'message' => 'Status tidak boleh kosong']);
    exit;
}

// Daftar status yang valid
$valid_statuses = [
    'Pengecekan Berkas',
    'Tidak Bisa Difasilitasi',
    'Konfirmasi Lanjut',
    'Surat Keterangan Difasilitasi',
    'Menunggu Bukti Pendaftaran',
    'Diajukan ke Kementerian',
    'Bukti Pendaftaran Terbit',
    'Hasil Verifikasi Kementerian'
];

if (!in_array($status_baru, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
    exit;
}

try {
    // Mulai transaction
    $pdo->beginTransaction();
    
    // Ambil data pendaftar untuk notifikasi
    $sql_user = "SELECT p.NIK, u.email, u.nama_lengkap, u.no_wa 
                 FROM pendaftaran p 
                 INNER JOIN user u ON p.NIK = u.NIK_NIP 
                 WHERE p.id_pendaftaran = :id";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
    $stmt_user->execute();
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        throw new Exception('Data pendaftaran tidak ditemukan');
    }
    
    // Variable untuk tracking notifikasi
    $notif_result = null;
    
    // ===== HANDLE BERDASARKAN STATUS =====
    
    // 1. TIDAK BISA DIFASILITASI
    if ($status_baru === 'Tidak Bisa Difasilitasi') {
        if (empty($alasan)) {
            throw new Exception('Alasan harus diisi untuk status Tidak Bisa Difasilitasi');
        }
        
        // Update dengan menyimpan alasan_tidak_difasilitasi
        $sql = "UPDATE pendaftaran 
                SET status_validasi = :status,
                    alasan_tidak_difasilitasi = :alasan,
                    merek_difasilitasi = NULL,
                    alasan_konfirmasi = NULL
                WHERE id_pendaftaran = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status_baru, PDO::PARAM_STR);
        $stmt->bindParam(':alasan', $alasan, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
        $stmt->execute();
        
        // Commit dulu sebelum kirim notifikasi
        $pdo->commit();
        
        // KIRIM NOTIFIKASI (DB + WhatsApp)
        $deskripsi = NotificationTemplates::tidakBisaDifasilitasi($alasan);
        $notif_result = sendNotification(
            $user_data['NIK'],
            $id_pendaftaran,
            $user_data['email'],
            $deskripsi,
            $user_data['no_wa']
        );
        
        error_log("Notifikasi Tidak Bisa Difasilitasi - DB: " . ($notif_result['db'] ? 'SUCCESS' : 'FAILED'));
        error_log("Notifikasi Tidak Bisa Difasilitasi - WA: " . json_encode($notif_result['wa']));
    }
    
    // 2. KONFIRMASI LANJUT (Merek 2 atau 3)
    elseif ($status_baru === 'Konfirmasi Lanjut') {
        if ($merek_dipilih < 2 || $merek_dipilih > 3) {
            throw new Exception('Merek dipilih harus 2 atau 3 untuk Konfirmasi Lanjut');
        }
        
        if (empty($alasan)) {
            throw new Exception('Alasan harus diisi untuk memilih Merek Alternatif');
        }
        
        // Update dengan menyimpan merek_difasilitasi dan alasan_konfirmasi
        $sql = "UPDATE pendaftaran 
                SET status_validasi = :status,
                    merek_difasilitasi = :merek,
                    alasan_konfirmasi = :alasan,
                    alasan_tidak_difasilitasi = NULL
                WHERE id_pendaftaran = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status_baru, PDO::PARAM_STR);
        $stmt->bindParam(':merek', $merek_dipilih, PDO::PARAM_INT);
        $stmt->bindParam(':alasan', $alasan, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
        $stmt->execute();
        
        // Commit dulu sebelum kirim notifikasi
        $pdo->commit();
        
        // KIRIM NOTIFIKASI (DB + WhatsApp)
        $deskripsi = NotificationTemplates::konfirmasiMerekAlternatif($merek_dipilih, $alasan);
        $notif_result = sendNotification(
            $user_data['NIK'],
            $id_pendaftaran,
            $user_data['email'],
            $deskripsi,
            $user_data['no_wa']
        );
        
        error_log("Notifikasi Konfirmasi Lanjut - DB: " . ($notif_result['db'] ? 'SUCCESS' : 'FAILED'));
        error_log("Notifikasi Konfirmasi Lanjut - WA: " . json_encode($notif_result['wa']));
    }
    
    // 3. SURAT KETERANGAN DIFASILITASI (Merek 1)
    elseif ($status_baru === 'Surat Keterangan Difasilitasi') {
        if ($merek_dipilih != 1) {
            throw new Exception('Merek dipilih harus 1 untuk Surat Keterangan Difasilitasi');
        }
        
        // Update dengan menyimpan merek_difasilitasi = 1
        $sql = "UPDATE pendaftaran 
                SET status_validasi = :status,
                    merek_difasilitasi = :merek,
                    alasan_konfirmasi = NULL,
                    alasan_tidak_difasilitasi = NULL
                WHERE id_pendaftaran = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status_baru, PDO::PARAM_STR);
        $stmt->bindParam(':merek', $merek_dipilih, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
        $stmt->execute();
        
        // Commit dulu sebelum kirim notifikasi
        $pdo->commit();
        
        // KIRIM NOTIFIKASI (DB + WhatsApp)
        $deskripsi = NotificationTemplates::suratKeteranganDifasilitasi();
        $notif_result = sendNotification(
            $user_data['NIK'],
            $id_pendaftaran,
            $user_data['email'],
            $deskripsi,
            $user_data['no_wa']
        );
        
        error_log("Notifikasi Surat Keterangan Difasilitasi - DB: " . ($notif_result['db'] ? 'SUCCESS' : 'FAILED'));
        error_log("Notifikasi Surat Keterangan Difasilitasi - WA: " . json_encode($notif_result['wa']));
    }
    
    // 4. MELENGKAPI SURAT (setelah upload surat keterangan)
    elseif ($status_baru === 'Melengkapi Surat') {
        $sql = "UPDATE pendaftaran 
                SET status_validasi = :status
                WHERE id_pendaftaran = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status_baru, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
        $stmt->execute();
        
        $pdo->commit();
    }
    
    // 5. MENUNGGU BUKTI PENDAFTARAN
    elseif ($status_baru === 'Menunggu Bukti Pendaftaran') {
        $sql = "UPDATE pendaftaran 
                SET status_validasi = :status
                WHERE id_pendaftaran = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status_baru, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
        $stmt->execute();
        
        $pdo->commit();
    }
    
    // 6. BUKTI PENDAFTARAN TERBIT
    elseif ($status_baru === 'Bukti Pendaftaran Terbit') {
        $sql = "UPDATE pendaftaran 
                SET status_validasi = :status
                WHERE id_pendaftaran = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status_baru, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
        $stmt->execute();
        
        $pdo->commit();
    }
    
    // 7. DIAJUKAN KE KEMENTERIAN
    elseif ($status_baru === 'Diajukan ke Kementerian') {
        $sql = "UPDATE pendaftaran 
                SET status_validasi = :status
                WHERE id_pendaftaran = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status_baru, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
        $stmt->execute();
        
        $pdo->commit();
    }
    
    // 8. SERTIFIKAT TERBIT
    elseif ($status_baru === 'Sertifikat Terbit') {
        $sql = "UPDATE pendaftaran 
                SET status_validasi = :status
                WHERE id_pendaftaran = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status_baru, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
        $stmt->execute();
        
        $pdo->commit();
    }
    
    // 9. HASIL VERIFIKASI KEMENTERIAN
    elseif ($status_baru === 'Hasil Verifikasi Kementerian') {
        $sql = "UPDATE pendaftaran 
                SET status_validasi = :status
                WHERE id_pendaftaran = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status_baru, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
        $stmt->execute();
        
        $pdo->commit();
    }
    
    // STATUS LAINNYA
    else {
        $sql = "UPDATE pendaftaran 
                SET status_validasi = :status
                WHERE id_pendaftaran = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $status_baru, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id_pendaftaran, PDO::PARAM_INT);
        $stmt->execute();
        
        $pdo->commit();
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Status berhasil diupdate',
        'new_status' => $status_baru,
        'notif_sent' => $notif_result // Kirim info notifikasi ke frontend
    ]);
    
} catch (Exception $e) {
    // Rollback jika terjadi error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error update status: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>