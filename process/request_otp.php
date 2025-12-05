<?php
require_once 'config_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($identifier)) {
        throw new Exception("Email atau nomor WhatsApp harus diisi!");
    }

    // Cek apakah input adalah email atau nomor WA
    $is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL);
    
    if ($is_email) {
        // Cari user berdasarkan email
        $stmt = $pdo->prepare("SELECT NIK_NIP, nama_lengkap, email, no_wa FROM user WHERE email = ?");
        $stmt->execute([$identifier]);
    } else {
        // Cari user berdasarkan nomor WA
        $phone = preg_replace('/\D/', '', $identifier);
        
        // Konversi semua ke format 62xxx
        if (substr($phone, 0, 1) == '0') {
            $phone_normalized = '62' . substr($phone, 1);
        } elseif (substr($phone, 0, 2) == '62') {
            $phone_normalized = $phone;
        } else {
            $phone_normalized = '62' . $phone;
        }
        
        $stmt = $pdo->prepare("SELECT NIK_NIP, nama_lengkap, email, no_wa FROM user WHERE no_wa = ?");
        $stmt->execute([$phone_normalized]);
    }

    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("Email atau nomor WhatsApp tidak terdaftar!");
    }

    // Generate OTP 6 digit
    $otp = sprintf("%06d", rand(0, 999999));
    $otp_expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Hash OTP untuk keamanan
    $otp_hashed = password_hash($otp, PASSWORD_BCRYPT);

    // Update OTP di database
    $stmt = $pdo->prepare("UPDATE user SET otp = ?, otp_expiry = ? WHERE NIK_NIP = ?");
    $stmt->execute([$otp_hashed, $otp_expiry, $user['NIK_NIP']]);

    // Kirim OTP via WhatsApp
    $nomor_wa = $user['no_wa'];
    
    $message = "*🔐 Kode Login Anda*\n\n";
    $message .= "Halo *{$user['nama_lengkap']}*,\n\n";
    $message .= "Kode OTP Anda adalah:\n\n";
    $message .= "*{$otp}*\n\n";
    $message .= "Kode ini berlaku selama *5 menit*.\n";
    $message .= "Jangan bagikan kode ini kepada siapapun!\n\n";
    $message .= "_Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo_";

    $curl = curl_init();
    $data = [
        'target' => $nomor_wa,
        'message' => $message,
        'countryCode' => '62'
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.fonnte.com/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => [
            "Authorization: RVwjvMqkCEySULGE92BM" 
        ],
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0
    ]);

    $result = curl_exec($curl);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($curl_error) {
        error_log("Fonnte API Error: " . $curl_error);
        // Log error tapi tetap lanjut (bisa fallback ke email jika perlu)
    }

    // Mask nomor untuk keamanan
    $masked_phone = substr($user['no_wa'], 0, 5) . '****' . substr($user['no_wa'], -4);

    echo json_encode([
        'success' => true,
        'message' => "Kode OTP telah dikirim ke WhatsApp {$masked_phone}",
        'masked_phone' => $masked_phone
    ]);

} catch (Exception $e) {
    error_log("Request OTP error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>