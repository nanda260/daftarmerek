<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
require_once 'config_db.php';
require_once 'notification_helper.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['NIK_NIP']) || $_SESSION['role'] != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Cek method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Method tidak valid. Gunakan POST method.',
        'error_type' => 'invalid_method'
    ]);
    exit;
}

// Ambil data dari request
$id_pendaftaran = isset($_POST['id_pendaftaran']) ? intval($_POST['id_pendaftaran']) : 0;
$id_jenis_file = isset($_POST['id_jenis_file']) ? intval($_POST['id_jenis_file']) : 0;

// Validasi input
if ($id_pendaftaran <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'ID Pendaftaran tidak valid atau kosong.',
        'error_type' => 'validation_error',
        'field' => 'id_pendaftaran',
        'received_value' => $id_pendaftaran
    ]);
    exit;
}

if ($id_jenis_file <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Jenis file tidak valid atau belum dipilih.',
        'error_type' => 'validation_error',
        'field' => 'id_jenis_file',
        'received_value' => $id_jenis_file
    ]);
    exit;
}

// Cek apakah file di-upload
if (!isset($_FILES['file'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'File tidak ditemukan dalam request. Pastikan field upload bernama "file".',
        'error_type' => 'file_not_found'
    ]);
    exit;
}

if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File melebihi upload_max_filesize di php.ini',
        UPLOAD_ERR_FORM_SIZE => 'File melebihi MAX_FILE_SIZE yang ditentukan',
        UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
        UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
    ];
    
    echo json_encode([
        'success' => false, 
        'message' => 'Upload gagal: ' . ($error_messages[$_FILES['file']['error']] ?? 'Error tidak diketahui'),
        'error_type' => 'upload_error',
        'error_code' => $_FILES['file']['error']
    ]);
    exit;
}

$file = $_FILES['file'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

// Validasi ekstensi file
if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Format file tidak diizinkan.',
        'error_type' => 'invalid_extension',
        'detail' => [
            'file_name' => $file['name'],
            'extension_received' => $file_extension,
            'allowed_extensions' => implode(', ', $allowed_extensions)
        ]
    ]);
    exit;
}

// Validasi ukuran file (max 10MB untuk file-file besar)
$max_size = 10 * 1024 * 1024;
if ($file['size'] > $max_size) {
    echo json_encode([
        'success' => false, 
        'message' => 'Ukuran file terlalu besar.',
        'error_type' => 'file_too_large',
        'detail' => [
            'file_size' => round($file['size'] / 1024 / 1024, 2) . ' MB',
            'max_size' => '10 MB',
            'file_name' => $file['name']
        ]
    ]);
    exit;
}

try {
    // folder upload berdasarkan jenis file
    $physical_folder = "../uploads/lampiran/";
    $db_folder = "uploads/lampiran/";
    
    switch ($id_jenis_file) {
        case 4: // Surat Keterangan Difasilitasi
            $physical_folder = "../uploads/berkasfasilitasi/";
            $db_folder = "uploads/berkasfasilitasi/";
            break;
        case 5: // Surat IKM
            $physical_folder = "../uploads/suratikm/";
            $db_folder = "uploads/suratikm/";
            break;
        case 6: // Bukti Pendaftaran Kementerian
            $physical_folder = "../uploads/buktipendaftaran/";
            $db_folder = "uploads/buktipendaftaran/";
            break;
        case 7: // Sertifikat Terbit
            $physical_folder = "../uploads/sertifikat/";
            $db_folder = "uploads/sertifikat/";
            break;
        case 8: // Surat Penolakan Kementerian
            $physical_folder = "../uploads/penolakan/";
            $db_folder = "uploads/penolakan/";
            break;
        default:
            $physical_folder = "../uploads/lampiran/";
            $db_folder = "uploads/lampiran/";
            break;
    }
    
    // Buat folder jika belum ada
    if (!file_exists($physical_folder)) {
        if (!mkdir($physical_folder, 0777, true)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Gagal membuat direktori upload.',
                'error_type' => 'directory_creation_failed',
                'folder' => $physical_folder
            ]);
            exit;
        }
    }
    
    // Cek permission folder
    if (!is_writable($physical_folder)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Direktori upload tidak memiliki permission untuk menulis.',
            'error_type' => 'permission_denied',
            'folder' => $physical_folder
        ]);
        exit;
    }
    
    // Generate nama file
    // Ambil NIK dari pendaftaran untuk nama file
    $stmt_nik = $pdo->prepare("SELECT NIK FROM pendaftaran WHERE id_pendaftaran = ?");
    $stmt_nik->execute([$id_pendaftaran]);
    $nik_data = $stmt_nik->fetch(PDO::FETCH_ASSOC);
    $nik = $nik_data['NIK'] ?? time();
    
    // Generate nama file berdasarkan jenis
    if ($id_jenis_file == 7) {
        // Sertifikat Terbit
        $filename = "sertifikat_" . $nik . "." . $file_extension;
    } elseif ($id_jenis_file == 8) {
        // Surat Penolakan
        $filename = "penolakan_" . $nik . "." . $file_extension;
    } else {
        // File lainnya tetap pakai timestamp
        $filename = time() . "_" . uniqid() . "." . $file_extension;
    }
    
    $physical_target = $physical_folder . $filename;
    
    // simpan di database (tanpa ../)
    $db_target = $db_folder . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $physical_target)) {
        $tgl_upload = date('Y-m-d H:i:s');
        
        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = ?");
            $stmt->execute([$id_pendaftaran, $id_jenis_file]);
            $old_file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($old_file && $old_file['file_path']) {
                $old_physical_path = (strpos($old_file['file_path'], '../') === 0) 
                    ? $old_file['file_path'] 
                    : '../' . $old_file['file_path'];
                
                if (file_exists($old_physical_path)) {
                    unlink($old_physical_path);
                }
            }
            
            // Hapus record lama
            $stmt = $pdo->prepare("DELETE FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = ?");
            $stmt->execute([$id_pendaftaran, $id_jenis_file]);
            
            $stmt = $pdo->prepare("INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_pendaftaran, $id_jenis_file, $tgl_upload, $db_target]);
            
            $stmt_user = $pdo->prepare("SELECT p.NIK, u.email, u.nama_lengkap, u.no_wa FROM pendaftaran p INNER JOIN user u ON p.NIK = u.NIK_NIP WHERE p.id_pendaftaran = ?");
            $stmt_user->execute([$id_pendaftaran]);
            $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
            
            $notif_result = null;
            
            // Update status dan kirim notifikasi berdasarkan jenis file
            switch ($id_jenis_file) {
                case 4: // Surat Keterangan Difasilitasi
                    
                    if ($user_data) {
                        $deskripsi = NotificationTemplates::suratKeteranganTersedia();
                        $notif_result = sendNotification(
                            $user_data['NIK'],
                            $id_pendaftaran,
                            $user_data['email'],
                            $deskripsi,
                            $user_data['no_wa']
                        );
                        
                        error_log("Notifikasi Surat Keterangan - DB: " . ($notif_result['db'] ? 'SUCCESS' : 'FAILED'));
                        error_log("Notifikasi Surat Keterangan - WA: " . json_encode($notif_result['wa']));
                    }
                    break;
                    
                case 5: // Surat IKM
                    if ($user_data) {
                        $deskripsi = NotificationTemplates::suratIKMTersedia();
                        $notif_result = sendNotification(
                            $user_data['NIK'],
                            $id_pendaftaran,
                            $user_data['email'],
                            $deskripsi,
                            $user_data['no_wa']
                        );
                        
                        error_log("Notifikasi Surat IKM - DB: " . ($notif_result['db'] ? 'SUCCESS' : 'FAILED'));
                        error_log("Notifikasi Surat IKM - WA: " . json_encode($notif_result['wa']));
                    }
                    break;
                    
                case 6: // Bukti Pendaftaran Kementerian
                    if ($user_data) {
                        $deskripsi = NotificationTemplates::buktiPendaftaranTersedia();
                        $notif_result = sendNotification(
                            $user_data['NIK'],
                            $id_pendaftaran,
                            $user_data['email'],
                            $deskripsi,
                            $user_data['no_wa']
                        );
                        
                        error_log("Notifikasi Bukti Pendaftaran - DB: " . ($notif_result['db'] ? 'SUCCESS' : 'FAILED'));
                        error_log("Notifikasi Bukti Pendaftaran - WA: " . json_encode($notif_result['wa']));
                    }
                    break;
                    
                case 7: // Sertifikat Terbit (DITERIMA)
                    // Update status ke "Hasil Verifikasi Kementerian"
                    $stmt = $pdo->prepare("UPDATE pendaftaran SET status_validasi = 'Hasil Verifikasi Kementerian' WHERE id_pendaftaran = ?");
                    $stmt->execute([$id_pendaftaran]);
                    
                    $pdo->commit();
                    
                    if ($user_data) {
                        $deskripsi = NotificationTemplates::sertifikatTerbit();
                        $notif_result = sendNotification(
                            $user_data['NIK'],
                            $id_pendaftaran,
                            $user_data['email'],
                            $deskripsi,
                            $user_data['no_wa']
                        );
                        
                        error_log("Notifikasi Sertifikat - DB: " . ($notif_result['db'] ? 'SUCCESS' : 'FAILED'));
                        error_log("Notifikasi Sertifikat - WA: " . json_encode($notif_result['wa']));
                    }
                    
                    // Return response untuk sertifikat
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Sertifikat berhasil diupload!',
                        'data' => [
                            'file_path' => $db_target,
                            'file_name' => $filename,
                            'file_size' => round($file['size'] / 1024, 2) . ' KB',
                            'upload_time' => $tgl_upload,
                            'id_jenis_file' => $id_jenis_file
                        ],
                        'notif_sent' => $notif_result
                    ]);
                    exit; 
                    
                case 8: // Surat Penolakan Kementerian (DITOLAK)
                    // Update status ke "Hasil Verifikasi Kementerian"
                    $stmt = $pdo->prepare("UPDATE pendaftaran SET status_validasi = 'Hasil Verifikasi Kementerian' WHERE id_pendaftaran = ?");
                    $stmt->execute([$id_pendaftaran]);
                    
                    $pdo->commit();
                    
                    if ($user_data) {
                        $deskripsi = NotificationTemplates::suratPenolakan();
                        $notif_result = sendNotification(
                            $user_data['NIK'],
                            $id_pendaftaran,
                            $user_data['email'],
                            $deskripsi,
                            $user_data['no_wa']
                        );
                        
                        error_log("Notifikasi Surat Penolakan - DB: " . ($notif_result['db'] ? 'SUCCESS' : 'FAILED'));
                        error_log("Notifikasi Surat Penolakan - WA: " . json_encode($notif_result['wa']));
                    }
                    
                    // Return response untuk surat penolakan
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Surat Penolakan berhasil diupload!',
                        'data' => [
                            'file_path' => $db_target,
                            'file_name' => $filename,
                            'file_size' => round($file['size'] / 1024, 2) . ' KB',
                            'upload_time' => $tgl_upload,
                            'id_jenis_file' => $id_jenis_file
                        ],
                        'notif_sent' => $notif_result
                    ]);
                    exit;
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'File berhasil diupload!',
                'data' => [
                    'file_path' => $db_target,
                    'file_name' => $filename,
                    'file_size' => round($file['size'] / 1024, 2) . ' KB',
                    'upload_time' => $tgl_upload,
                    'id_jenis_file' => $id_jenis_file
                ],
                'notif_sent' => $notif_result
            ]);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            
            if (file_exists($physical_target)) {
                unlink($physical_target);
            }
            
            throw $e;
        }
        
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal memindahkan file dari temporary ke folder tujuan.',
            'error_type' => 'move_upload_failed',
            'detail' => [
                'tmp_name' => $file['tmp_name'],
                'target' => $physical_target,
                'file_exists' => file_exists($file['tmp_name']) ? 'Ya' : 'Tidak'
            ]
        ]);
    }
    
} catch (PDOException $e) {
    // Rollback jika terjadi error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Hapus file yang sudah di-upload jika ada error database
    if (isset($physical_target) && file_exists($physical_target)) {
        unlink($physical_target);
    }
    
    error_log("Error upload lampiran: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Terjadi kesalahan database.',
        'error_type' => 'database_error',
        'detail' => [
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage(),
            'file_name' => isset($filename) ? $filename : 'N/A'
        ]
    ]);
} catch (Exception $e) {
    error_log("Error upload lampiran: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Terjadi kesalahan sistem.',
        'error_type' => 'system_error',
        'detail' => [
            'error_message' => $e->getMessage()
        ]
    ]);
}
?>