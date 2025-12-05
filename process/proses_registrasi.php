<?php
require_once '../process/config_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Validasi input
    $required_fields = ['namaPemilik', 'nik', 'rt_rw', 'provinsi', 'kabupaten', 'kecamatan', 'kel_desa', 'telepon', 'email'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field $field wajib diisi.");
        }
    }

    $namaPemilik = trim($_POST['namaPemilik']);
    $nik = trim($_POST['nik']);
    $rt_rw = trim($_POST['rt_rw']);
    $provinsi = trim($_POST['provinsi']);
    $kabupaten = trim($_POST['kabupaten']);
    $kecamatan = trim($_POST['kecamatan']);
    $kel_desa = trim($_POST['kel_desa']);
    $telepon = trim($_POST['telepon']);
    $email = trim($_POST['email']);

    // Validasi NIK
    if (!preg_match('/^\d{16}$/', $nik)) {
        throw new Exception("NIK harus 16 digit angka.");
    }

    // Validasi RT/RW
    if (!preg_match('/^\d{3}\/\d{3}$/', $rt_rw)) {
        throw new Exception("Format RT/RW tidak valid. Contoh: 002/006");
    }

    // Validasi dan normalisasi nomor telepon ke format 62
    if (!preg_match('/^62\d{9,13}$/', $telepon)) {
        throw new Exception("Nomor WhatsApp tidak valid. Harus dimulai dengan 62 dan total 11-15 digit.");
    }
    
    // Pastikan nomor dalam format 62xxx (sudah pasti dari validasi di atas)
    // Tidak perlu konversi karena input sudah format 62

    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Format email tidak valid.");
    }

    // Validasi kode wilayah ada di database
    $stmt = $pdo->prepare("SELECT kode FROM wilayah WHERE kode = ?");
    
    // Cek provinsi
    $stmt->execute([$provinsi]);
    if (!$stmt->fetch()) {
        throw new Exception("Kode provinsi tidak valid.");
    }
    
    // Cek kabupaten
    $stmt->execute([$kabupaten]);
    if (!$stmt->fetch()) {
        throw new Exception("Kode kabupaten tidak valid.");
    }
    
    // Cek kecamatan
    $stmt->execute([$kecamatan]);
    if (!$stmt->fetch()) {
        throw new Exception("Kode kecamatan tidak valid.");
    }
    
    // Cek desa
    $stmt->execute([$kel_desa]);
    if (!$stmt->fetch()) {
        throw new Exception("Kode desa tidak valid.");
    }

    // Ambil nama wilayah untuk disimpan
    $stmt = $pdo->prepare("SELECT nama FROM wilayah WHERE kode = ?");
    
    $stmt->execute([$provinsi]);
    $nama_provinsi = $stmt->fetchColumn();
    
    $stmt->execute([$kabupaten]);
    $nama_kabupaten = $stmt->fetchColumn();
    
    $stmt->execute([$kecamatan]);
    $nama_kecamatan = $stmt->fetchColumn();
    
    $stmt->execute([$kel_desa]);
    $nama_desa = $stmt->fetchColumn();

    // Validasi file KTP
    if (!isset($_FILES['fileKTP']) || $_FILES['fileKTP']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File KTP wajib diupload.");
    }

    $file = $_FILES['fileKTP'];
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    $max_size = 1024 * 1024; // 1MB

    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("Format file tidak didukung. Gunakan PDF, JPG, JPEG, atau PNG.");
    }

    if ($file['size'] > $max_size) {
        throw new Exception("Ukuran file terlalu besar. Maksimal 1 MB.");
    }

    // Cek apakah NIK sudah terdaftar
    $stmt = $pdo->prepare("SELECT NIK_NIP FROM user WHERE NIK_NIP = ?");
    $stmt->execute([$nik]);
    if ($stmt->fetch()) {
        throw new Exception("NIK sudah terdaftar. Silakan login atau gunakan NIK lain.");
    }

    // Cek apakah email sudah terdaftar
    $stmt = $pdo->prepare("SELECT email FROM user WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception("Email sudah terdaftar. Silakan login atau gunakan email lain.");
    }

    // Cek apakah nomor telepon sudah terdaftar
    $stmt = $pdo->prepare("SELECT no_wa FROM user WHERE no_wa = ?");
    $stmt->execute([$telepon]);
    if ($stmt->fetch()) {
        throw new Exception("Nomor WhatsApp sudah terdaftar. Silakan login atau gunakan nomor lain.");
    }

    // Upload file KTP
    $upload_dir = '../uploads/ktp/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'ktp_' . $nik . '.' . $file_extension;
    $file_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception("Gagal mengupload file KTP.");
    }

    // Simpan data user dengan kode dan nama wilayah
    $stmt = $pdo->prepare("
        INSERT INTO user (
            NIK_NIP, nama_lengkap, no_wa, 
            kode_provinsi, nama_provinsi,
            kode_kabupaten, nama_kabupaten,
            kode_kecamatan, kecamatan,
            kode_kel_desa, kel_desa,
            rt_rw, 
            email, foto_ktp, is_verified, role, tanggal_buat
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'Pemohon', NOW())
    ");

    $stmt->execute([
        $nik,
        $namaPemilik,
        $telepon,
        $provinsi,
        $nama_provinsi,
        $kabupaten,
        $nama_kabupaten,
        $kecamatan,
        $nama_kecamatan,
        $kel_desa,
        $nama_desa,
        $rt_rw,
        $email,
        'uploads/ktp/' . $new_filename
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Registrasi berhasil! Silakan login dengan email atau nomor WhatsApp Anda.',
        'email' => $email,
        'redirect' => 'login.php'
    ]);

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>