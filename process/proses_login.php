<?php
// Matikan output error HTML
error_reporting(0);
ini_set('display_errors', 0);

// Mulai output buffering untuk menangkap output yang tidak diinginkan
ob_start();

session_start();

// Clear output buffer dan set header JSON
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// Function untuk mengirim response JSON
function sendResponse($success, $message, $data = [])
{
    // Pastikan tidak ada output sebelumnya
    if (ob_get_length()) ob_clean();

    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

// Validasi request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan');
}

// Load dependencies dengan error handling
try {
    if (!file_exists('config_db.php')) {
        throw new Exception('File config_db.php tidak ditemukan');
    }
    require_once 'config_db.php';

    if (!file_exists('config_email.php')) {
        throw new Exception('File config_email.php tidak ditemukan');
    }
    require_once 'config_email.php';

    if (!file_exists('../vendor/autoload.php')) {
        throw new Exception('File vendor/autoload.php tidak ditemukan. Jalankan: composer install');
    }
    require_once '../vendor/autoload.php';
} catch (Exception $e) {
    sendResponse(false, 'Error loading dependencies', ['details' => $e->getMessage()]);
}

// Import PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Main process
try {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi input
    if (empty($email) || empty($password)) {
        sendResponse(false, 'Email dan password harus diisi!');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Format email tidak valid!');
    }

    // Cari user berdasarkan email
    $stmt = $pdo->prepare("
    SELECT NIK_NIP, nama_lengkap, email, password, role, is_verified, otp_expiry
    FROM user 
    WHERE email = ?
  ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Cek apakah user ditemukan
    if (!$user) {
        sendResponse(false, 'Email atau password salah!', [
            'details' => 'Email tidak terdaftar dalam sistem'
        ]);
    }

    // Verifikasi password
    if (!password_verify($password, $user['password'])) {
        sendResponse(false, 'Email atau password salah!', [
            'details' => 'Password yang Anda masukkan tidak sesuai'
        ]);
    }

    // Cek apakah akun sudah terverifikasi
    if ($user['is_verified'] != 1) {
        // Generate OTP baru
        $otp = random_int(100000, 999999);
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Update OTP di database
        $stmt = $pdo->prepare("UPDATE user SET otp = ?, otp_expiry = ? WHERE NIK_NIP = ?");
        $stmt->execute([$otp, $otp_expiry, $user['NIK_NIP']]);

        // Kirim OTP via email
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $mail_host;
            $mail->SMTPAuth = true;
            $mail->Username = $mail_username;
            $mail->Password = $mail_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mail_port;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($mail_from, $mail_from_name);
            $mail->addAddress($email, $user['nama_lengkap']);

            $mail->isHTML(true);
            $mail->Subject = 'Kode Verifikasi OTP - Disperindag Sidoarjo';
            $mail->Body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5;'>
          <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px;'>
            <h2 style='color: #007bff; text-align: center;'>Verifikasi Akun Anda</h2>
            <p>Halo, <strong>{$user['nama_lengkap']}</strong></p>
            <p>Anda mencoba login tetapi akun Anda belum terverifikasi.</p>
            <p>Berikut adalah kode OTP Anda untuk verifikasi akun:</p>
            <div style='text-align: center; margin: 30px 0;'>
              <div style='background-color: #007bff; color: white; font-size: 32px; font-weight: bold; padding: 20px; border-radius: 8px; letter-spacing: 5px;'>
                {$otp}
              </div>
            </div>
            <p style='color: #d9534f;'><strong>⚠️ Kode ini berlaku selama 10 menit.</strong></p>
            <p style='color: #666; font-size: 12px; margin-top: 30px;'>
              Jika Anda tidak melakukan login, abaikan email ini.
            </p>
          </div>
        </div>
      ";

            $mail->send();
        } catch (PHPMailerException $e) {
            error_log("Email error: " . $mail->ErrorInfo);
            // Tetap lanjut meskipun gagal kirim email
        }

        sendResponse(false, 'Akun Anda belum terverifikasi!', [
            'need_verification' => true,
            'details' => 'Kami telah mengirim kode OTP ke email Anda'
        ]);
    }

    // Login berhasil - simpan session
    $_SESSION['user_nik'] = $user['NIK_NIP'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_nama'] = $user['nama_lengkap'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();

    // Update last login (optional, jika kolom ada)
    try {
        $stmt = $pdo->prepare("UPDATE user SET last_login = NOW() WHERE NIK_NIP = ?");
        $stmt->execute([$user['NIK_NIP']]);
    } catch (Exception $e) {
        // Abaikan jika kolom last_login tidak ada
        error_log("Last login update error: " . $e->getMessage());
    }

    // Tentukan redirect berdasarkan role
    $redirect = 'home.php'; // default
    if ($user['role'] === 'Admin') {
        $redirect = 'dashboard-admin.php';
    } elseif ($user['role'] === 'Pemohon') {
        $redirect = 'home.php';
    }

    sendResponse(true, 'Login berhasil!', [
        'nama' => $user['nama_lengkap'],
        'role' => $user['role'],
        'redirect' => $redirect
    ]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, 'Terjadi kesalahan database!', [
        'details' => 'Silakan coba lagi atau hubungi administrator'
    ]);
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    sendResponse(false, 'Terjadi kesalahan sistem!', [
        'details' => $e->getMessage()
    ]);
}
