<?php
require_once '../process/config_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $nik = trim($_POST['nik'] ?? '');
    $otp = trim($_POST['otp'] ?? '');

    // Validasi input
    if (empty($nik)) {
        throw new Exception("NIK tidak ditemukan.");
    }

    if (empty($otp)) {
        throw new Exception("Kode OTP wajib diisi.");
    }

    if (!preg_match('/^\d{6}$/', $otp)) {
        throw new Exception("Kode OTP harus 6 digit angka.");
    }

    // Ambil data user
    $stmt = $pdo->prepare("
        SELECT NIK_NIP, nama_lengkap, otp, otp_expiry, is_verified 
        FROM user 
        WHERE NIK_NIP = ?
    ");
    $stmt->execute([$nik]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("Data registrasi tidak ditemukan.");
    }

    if ($user['is_verified'] == 1) {
        throw new Exception("Akun sudah terverifikasi. Silakan login.");
    }

    // Cek apakah OTP sudah expired
    $now = new DateTime();
    $expiry = new DateTime($user['otp_expiry']);
    
    if ($now > $expiry) {
        throw new Exception("Kode OTP telah kedaluwarsa. Silakan daftar ulang.");
    }

    // Verifikasi OTP
    if (!password_verify($otp, $user['otp'])) {
        throw new Exception("Kode OTP salah. Silakan cek kembali WhatsApp Anda.");
    }

    // Update status verifikasi
    $stmt = $pdo->prepare("
        UPDATE user 
        SET is_verified = 1, 
            otp = NULL, 
            otp_expiry = NULL,
            updated_at = NOW()
        WHERE NIK_NIP = ?
    ");
    $stmt->execute([$nik]);

    echo json_encode([
        'success' => true,
        'message' => 'Verifikasi berhasil! Akun Anda telah aktif.',
        'nama' => $user['nama_lengkap']
    ]);

} catch (Exception $e) {
    error_log("OTP Verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}