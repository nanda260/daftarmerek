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
$id_pendaftaran = isset($_POST['id_pendaftaran']) ? intval($_POST['id_pendaftaran']) : 0;

if ($id_pendaftaran == 0) {
    echo json_encode(['success' => false, 'message' => 'ID pendaftaran tidak valid']);
    exit();
}

try {
    $uploaded_file = null;
    $id_jenis_file = null;
    $upload_dir = '../uploads/lampiran/';

    // Pastikan direktori ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Handle upload berdasarkan action
    if ($action === 'upload_bukti_pendaftaran') {
        if (!isset($_FILES['fileBukti']) || $_FILES['fileBukti']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File tidak ditemukan atau error saat upload']);
            exit();
        }

        $file = $_FILES['fileBukti'];
        $id_jenis_file = 5; // ID untuk "Bukti Pendaftaran Kementerian"
        
    } elseif ($action === 'upload_hasil_verifikasi') {
        if (!isset($_FILES['fileHasil']) || $_FILES['fileHasil']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File tidak ditemukan atau error saat upload']);
            exit();
        }

        $file = $_FILES['fileHasil'];
        $id_jenis_file = 6; // ID untuk "Sertifikat Merek"
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
        exit();
    }

    // Validasi file
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $max_file_size = 5 * 1024 * 1024; // 5MB

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak diizinkan. Hanya PDF, JPG, JPEG, PNG']);
        exit();
    }

    if ($file['size'] > $max_file_size) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB']);
        exit();
    }

    // Generate nama file unik
    $timestamp = time();
    $unique_id = uniqid();
    $new_filename = $timestamp . '_' . $unique_id . '.' . $file_ext;
    $file_path = $upload_dir . $new_filename;

    // Upload file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
        exit();
    }

    // Simpan ke database
    $db_file_path = 'uploads/lampiran/' . $new_filename;
    
    $query = "INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path) 
              VALUES (:id_pendaftaran, :id_jenis_file, NOW(), :file_path)";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id_pendaftaran', $id_pendaftaran, PDO::PARAM_INT);
    $stmt->bindParam(':id_jenis_file', $id_jenis_file, PDO::PARAM_INT);
    $stmt->bindParam(':file_path', $db_file_path, PDO::PARAM_STR);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'File berhasil diupload',
        'file_path' => $db_file_path
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error database: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>