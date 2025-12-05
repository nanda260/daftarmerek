<?php
session_start();
require_once 'config_db.php';

header('Content-Type: application/json');

function sendResponse($success, $message, $data = [])
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

if (!isset($_SESSION['NIK_NIP'])) {
    sendResponse(false, 'Akses ditolak! Anda belum login.');
}

$nik_session = $_SESSION['NIK_NIP'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Metode tidak valid.');
}

// Ambil data dari POST
$namaPemilik = trim($_POST['namaPemilik'] ?? '');
$nik = trim($_POST['NIK_NIP'] ?? '');
$rt_rw = trim($_POST['rt_rw'] ?? '');
$provinsi = trim($_POST['provinsi'] ?? '');
$kabupaten = trim($_POST['kabupaten'] ?? '');
$kecamatan = trim($_POST['kecamatan'] ?? '');
$kel_desa = trim($_POST['kel_desa'] ?? '');
$telepon = trim($_POST['telepon'] ?? '');
$email = trim($_POST['email'] ?? '');

// Validasi field wajib
if (empty($namaPemilik) || empty($nik) || empty($telepon) || empty($email)) {
    sendResponse(false, 'Harap lengkapi semua field wajib.');
}

// Validasi NIK
if (!preg_match('/^\d{16}$/', $nik)) {
    sendResponse(false, 'Format NIK tidak valid. Harus 16 digit angka.');
}

// Validasi RT/RW
if (!preg_match('/^\d{3}\/\d{3}$/', $rt_rw)) {
    sendResponse(false, 'Format RT/RW tidak valid. Contoh: 002/006');
}

// Validasi email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Format email tidak valid.');
}

// Validasi nomor telepon dengan format 62
if (!preg_match('/^62\d{9,13}$/', $telepon)) {
    sendResponse(false, 'Nomor WhatsApp tidak valid. Harus dimulai dengan 62 dan total 11-15 digit.');
}

// Validasi wilayah
if (empty($provinsi) || empty($kabupaten) || empty($kecamatan) || empty($kel_desa)) {
    sendResponse(false, 'Semua field alamat wajib dipilih.');
}

try {
    // Validasi kode wilayah ada di database
    $stmt = $pdo->prepare("SELECT kode FROM wilayah WHERE kode = ?");
    
    $stmt->execute([$provinsi]);
    if (!$stmt->fetch()) {
        throw new Exception("Kode provinsi tidak valid.");
    }
    
    $stmt->execute([$kabupaten]);
    if (!$stmt->fetch()) {
        throw new Exception("Kode kabupaten tidak valid.");
    }
    
    $stmt->execute([$kecamatan]);
    if (!$stmt->fetch()) {
        throw new Exception("Kode kecamatan tidak valid.");
    }
    
    $stmt->execute([$kel_desa]);
    if (!$stmt->fetch()) {
        throw new Exception("Kode desa tidak valid.");
    }

    // Ambil nama wilayah
    $stmt = $pdo->prepare("SELECT nama FROM wilayah WHERE kode = ?");
    
    $stmt->execute([$provinsi]);
    $nama_provinsi = $stmt->fetchColumn();
    
    $stmt->execute([$kabupaten]);
    $nama_kabupaten = $stmt->fetchColumn();
    
    $stmt->execute([$kecamatan]);
    $nama_kecamatan = $stmt->fetchColumn();
    
    $stmt->execute([$kel_desa]);
    $nama_desa = $stmt->fetchColumn();

    // Cek apakah NIK sudah digunakan user lain (jika NIK berubah)
    if ($nik !== $nik_session) {
        $stmt = $pdo->prepare("SELECT NIK_NIP FROM user WHERE NIK_NIP = ? AND NIK_NIP != ?");
        $stmt->execute([$nik, $nik_session]);
        if ($stmt->fetch()) {
            sendResponse(false, 'NIK sudah digunakan oleh user lain.');
        }
    }

    // Cek apakah email sudah digunakan user lain
    $stmt = $pdo->prepare("SELECT email FROM user WHERE email = ? AND NIK_NIP != ?");
    $stmt->execute([$email, $nik_session]);
    if ($stmt->fetch()) {
        sendResponse(false, 'Email sudah digunakan oleh user lain.');
    }

    // Cek apakah nomor telepon sudah digunakan user lain
    $stmt = $pdo->prepare("SELECT no_wa FROM user WHERE no_wa = ? AND NIK_NIP != ?");
    $stmt->execute([$telepon, $nik_session]);
    if ($stmt->fetch()) {
        sendResponse(false, 'Nomor WhatsApp sudah digunakan oleh user lain.');
    }

    // Upload file KTP jika ada
    $upload_dir = '../uploads/ktp/';
    $foto_ktp = null;

    if (isset($_FILES['fileKTP']) && $_FILES['fileKTP']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['fileKTP'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];

        if (!in_array($file['type'], $allowed_types)) {
            sendResponse(false, 'Format file KTP tidak diperbolehkan. Gunakan JPG, PNG, atau PDF.');
        }

        if ($file['size'] > 1024 * 1024) {
            sendResponse(false, 'Ukuran file KTP terlalu besar. Maksimal 1 MB.');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'ktp_' . $nik . '.' . $ext;
        $target_path = $upload_dir . $new_filename;

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            sendResponse(false, 'Gagal mengunggah file KTP.');
        }

        $foto_ktp = 'uploads/ktp/' . $new_filename;
    }

    // Prepare update query
    $update_fields = "
        nama_lengkap = :nama_lengkap,
        NIK_NIP = :nik,
        no_wa = :telepon,
        kode_provinsi = :kode_provinsi,
        nama_provinsi = :nama_provinsi,
        kode_kabupaten = :kode_kabupaten,
        nama_kabupaten = :nama_kabupaten,
        kode_kecamatan = :kode_kecamatan,
        kecamatan = :kecamatan,
        kode_kel_desa = :kode_kel_desa,
        kel_desa = :kel_desa,
        rt_rw = :rt_rw,
        email = :email,
        updated_at = NOW()
    ";

    $params = [
        ':nama_lengkap' => $namaPemilik,
        ':nik' => $nik,
        ':telepon' => $telepon,
        ':kode_provinsi' => $provinsi,
        ':nama_provinsi' => $nama_provinsi,
        ':kode_kabupaten' => $kabupaten,
        ':nama_kabupaten' => $nama_kabupaten,
        ':kode_kecamatan' => $kecamatan,
        ':kecamatan' => $nama_kecamatan,
        ':kode_kel_desa' => $kel_desa,
        ':kel_desa' => $nama_desa,
        ':rt_rw' => $rt_rw,
        ':email' => $email,
        ':nik_session' => $nik_session
    ];

    // Tambahkan foto_ktp jika ada upload baru
    if ($foto_ktp) {
        $update_fields .= ", foto_ktp = :foto_ktp";
        $params[':foto_ktp'] = $foto_ktp;
    }

    // Ambil data lama user untuk cek perubahan
    $stmt_check = $pdo->prepare("SELECT nama_lengkap, NIK_NIP FROM user WHERE NIK_NIP = ?");
    $stmt_check->execute([$nik_session]);
    $old_data = $stmt_check->fetch();

    // Execute update
    $stmt = $pdo->prepare("UPDATE user SET $update_fields WHERE NIK_NIP = :nik_session");
    $stmt->execute($params);

    // Cek apakah Nama atau NIK berubah
    $nama_berubah = ($namaPemilik !== $old_data['nama_lengkap']);
    $nik_berubah = ($nik !== $old_data['NIK_NIP']);

    if ($nama_berubah || $nik_berubah) {
        // Update session NIK jika berubah
        if ($nik_berubah) {
            $_SESSION['NIK_NIP'] = $nik;
        }
        
        // Destroy session dan redirect ke logout
        session_destroy();
        
        $pesan = 'Profil berhasil diperbarui. Silakan login kembali karena ';
        if ($nama_berubah && $nik_berubah) {
            $pesan .= 'Nama dan NIK berubah.';
        } elseif ($nama_berubah) {
            $pesan .= 'Nama berubah.';
        } else {
            $pesan .= 'NIK berubah.';
        }
        
        sendResponse(true, $pesan, [
            'redirect' => 'logout.php'
        ]);
    } else {
        sendResponse(true, 'Profil berhasil diperbarui!');
    }
    
} catch (PDOException $e) {
    error_log("Edit profil error: " . $e->getMessage());
    sendResponse(false, 'Gagal memperbarui profil: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Edit profil error: " . $e->getMessage());
    sendResponse(false, $e->getMessage());
}
?>