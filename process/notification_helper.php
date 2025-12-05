<?php

require_once 'config_db.php';

// ===== KONFIGURASI FONNTE =====
define('FONNTE_TOKEN', 'RVwjvMqkCEySULGE92BM');
define('FONNTE_API_URL', 'https://api.fonnte.com/send');
define('ENABLE_WHATSAPP', true);

function sendWhatsApp($target, $message)
{
    if (!ENABLE_WHATSAPP) {
        return ['status' => 'disabled', 'message' => 'WhatsApp notification disabled'];
    }

    $original_target = $target;
    $target = preg_replace('/[^0-9]/', '', $target);

    // Konversi dari format 62xxx ke 08xxx (WAJIB untuk Fonnte)
    if (substr($target, 0, 2) === '62') {
        $target = '0' . substr($target, 2);
    } elseif (substr($target, 0, 1) !== '0') {
        $target = '0' . $target;
    }

    if (substr($target, 0, 2) !== '08') {
        error_log("❌ INVALID PHONE FORMAT");
        error_log("   Original: " . $original_target);
        error_log("   Converted: " . $target);
        return ['status' => false, 'message' => 'Nomor telepon harus format Indonesia (08xxx)'];
    }
    error_log("========================================");
    error_log("📤 SENDING WHATSAPP MESSAGE");
    error_log("========================================");
    error_log("Original Number: " . $original_target);
    error_log("Formatted Number: " . $target);
    error_log("Message Length: " . strlen($message) . " chars");
    error_log("----------------------------------------");

    $curl = curl_init();

    $postData = array(
        'target' => $target,
        'message' => $message,
        'countryCode' => '62'
    );
    curl_setopt_array($curl, array(
        CURLOPT_URL => FONNTE_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . FONNTE_TOKEN
        ),
        // PERBAIKAN SSL - Gunakan cacert.pem untuk production
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if (curl_errno($curl)) {
        $error_msg = curl_error($curl);
        error_log("----------------------------------------");
        error_log("❌ cURL ERROR: " . $error_msg);
        error_log("========================================\n");
        curl_close($curl);
        return ['status' => false, 'message' => $error_msg];
    }

    curl_close($curl);

    error_log("----------------------------------------");
    error_log("📥 RESPONSE FROM FONNTE");
    error_log("HTTP Code: " . $httpcode);
    error_log("Response: " . ($response ?: '(empty)'));
    error_log("========================================\n");

    if (!$response) {
        return ['status' => false, 'message' => 'Empty response from Fonnte'];
    }

    $result = json_decode($response, true);

    if ($result === null) {
        error_log("❌ JSON DECODE ERROR: " . json_last_error_msg());
        return ['status' => false, 'message' => 'Invalid JSON response', 'raw' => $response];
    }

    // Check success
    $is_success = false;

    if (isset($result['status'])) {
        $is_success = ($result['status'] === true || $result['status'] === 'true');
    } elseif (isset($result['detail']) && is_array($result['detail'])) {
        foreach ($result['detail'] as $detail) {
            if (isset($detail['status']) && ($detail['status'] === 'success' || $detail['status'] === 'sent')) {
                $is_success = true;
                break;
            }
        }
    }

    if (!$is_success && isset($result['data'])) {
        $is_success = true;
    }

    if (!$is_success && $httpcode === 200) {
        if (!isset($result['error']) && !isset($result['reason'])) {
            $is_success = true;
        }
    }

    if ($is_success) {
        error_log("✅ WhatsApp SENT SUCCESSFULLY");
        return ['status' => true, 'data' => $result];
    } else {
        error_log("❌ WhatsApp FAILED");
        $error_reason = 'Unknown error';
        if (isset($result['reason'])) {
            $error_reason = $result['reason'];
        } elseif (isset($result['detail'][0]['message'])) {
            $error_reason = $result['detail'][0]['message'];
        } elseif (isset($result['message'])) {
            $error_reason = $result['message'];
        }

        return ['status' => false, 'data' => $result, 'message' => $error_reason];
    }
}

function sendNotification($nik_nip, $id_pendaftaran, $email, $deskripsi, $no_wa = null, $id_pengajuan = null)
{
    global $pdo;

    error_log("=== sendNotification Called ===");
    error_log("NIK: " . $nik_nip);
    error_log("ID Pendaftaran: " . ($id_pendaftaran ?? 'NULL'));
    error_log("ID Pengajuan: " . ($id_pengajuan ?? 'NULL'));
    error_log("Email: " . $email);
    error_log("No WA: " . ($no_wa ?? 'NULL'));

    $result = [
        'db' => false,
        'wa' => null
    ];

    try {
        $query = "INSERT INTO notifikasi (NIK_NIP, id_pendaftaran, id_pengajuan, email, deskripsi, tgl_notif, is_read) 
                  VALUES (:nik_nip, :id_pendaftaran, :id_pengajuan, :email, :deskripsi, NOW(), 0)";

        $stmt = $pdo->prepare($query);
        $params = [
            'nik_nip' => $nik_nip,
            'id_pendaftaran' => $id_pendaftaran,
            'id_pengajuan' => $id_pengajuan,
            'email' => $email,
            'deskripsi' => $deskripsi
        ];

        error_log("📊 Query params: " . json_encode($params));

        $execute_result = $stmt->execute($params);

        if (!$execute_result) {
            $errorInfo = $stmt->errorInfo();
            error_log("❌ Execute failed!");
            error_log("   SQL State: " . $errorInfo[0]);
            error_log("   Error Code: " . $errorInfo[1]);
            error_log("   Error Message: " . $errorInfo[2]);
        }

        $result['db'] = $execute_result;
        $last_notif_id = $pdo->lastInsertId();

        error_log("✅ Database insert: " . ($result['db'] ? 'SUCCESS' : 'FAILED'));
        error_log("   Last Notification ID: " . $last_notif_id);

        // Kirim WhatsApp
        if ($no_wa && ENABLE_WHATSAPP) {
            error_log("Attempting to send WhatsApp to: " . $no_wa);

            $wa_message = "*INFORMASI STATUS PENDAFTARAN/PENGAJUAN SUKET MEREK*\n\n";
            $wa_message .= "ID: #" . ($id_pengajuan ?? $id_pendaftaran) . "\n\n";
            if ($id_pengajuan) {
                $wa_message .= "Surat Keterangan IKM Anda telah terbit dan tersedia untuk diunduh.\n\n";
                $wa_message .= "Silakan login ke sistem dan download surat di halaman Status Pengajuan Surat.\n\n";
            } else {
                $wa_message .= $deskripsi . "\n\n";
            }
            $wa_message .= "Silakan login ke sistem untuk informasi lebih lanjut.\n\n";
            $wa_message .= "_Pesan otomatis dari Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo_";

            $result['wa'] = sendWhatsApp($no_wa, $wa_message);
            error_log("WhatsApp send result: " . json_encode($result['wa']));
        }

        return $result;
    } catch (PDOException $e) {
        error_log("❌ Error adding notification: " . $e->getMessage());

        // errorInfo adalah property, bukan method
        $errorInfo = $e->errorInfo;
        error_log("   SQL State: " . ($errorInfo[0] ?? 'UNKNOWN'));
        error_log("   Driver Code: " . ($errorInfo[1] ?? 'UNKNOWN'));
        error_log("   Driver Message: " . ($errorInfo[2] ?? 'UNKNOWN'));

        // Jika error karena kolom tidak ada, tampilkan pesan bantuan
        if (strpos($e->getMessage(), 'id_pengajuan') !== false) {
            error_log("❌ SOLUSI: Jalankan ALTER TABLE notifikasi ADD COLUMN id_pengajuan INT NULL AFTER id_pendaftaran;");
        }

        return $result;
    }
}

/**
 * PERBAIKAN: Fungsi baru untuk mendapatkan data user dari pengajuan surat
 */
function getUserByPengajuan($id_pengajuan)
{
    global $pdo;

    try {
        $query = "SELECT 
                    p.NIK as NIK_NIP,
                    COALESCE(u.email, p.email) as email,
                    COALESCE(u.nama_lengkap, p.nama_pemilik) as nama_lengkap,
                    COALESCE(u.no_wa, p.no_telp_pemilik) as no_wa
                  FROM pengajuansurat p
                  LEFT JOIN user u ON p.NIK = u.NIK_NIP
                  WHERE p.id_pengajuan = :id_pengajuan";

        $stmt = $pdo->prepare($query);
        $stmt->execute(['id_pengajuan' => $id_pengajuan]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            error_log("getUserByPengajuan - Found user:");
            error_log("  - Nama: " . $user['nama_lengkap']);
            error_log("  - Email: " . $user['email']);
            error_log("  - No WA: " . ($user['no_wa'] ?? 'NULL'));
        } else {
            error_log("getUserByPengajuan - User not found for ID: " . $id_pengajuan);
        }

        return $user;
    } catch (PDOException $e) {
        error_log("Error getting user info: " . $e->getMessage());
        return null;
    }
}

function getUserByPendaftaran($id_pendaftaran)
{
    global $pdo;

    try {
        $query = "SELECT p.NIK as NIK_NIP, u.email, u.nama_lengkap, u.no_wa 
                  FROM pendaftaran p 
                  INNER JOIN user u ON p.NIK = u.NIK_NIP 
                  WHERE p.id_pendaftaran = :id_pendaftaran";

        $stmt = $pdo->prepare($query);
        $stmt->execute(['id_pendaftaran' => $id_pendaftaran]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            error_log("getUserByPendaftaran - Found user:");
            error_log("  - Nama: " . $user['nama_lengkap']);
            error_log("  - Email: " . $user['email']);
            error_log("  - No WA: " . ($user['no_wa'] ?? 'NULL'));
        } else {
            error_log("getUserByPendaftaran - User not found for ID: " . $id_pendaftaran);
        }
        return $user;
    } catch (PDOException $e) {
        error_log("Error getting user info: " . $e->getMessage());
        return null;
    }
}

function getAllAdmins()
{
    global $pdo;

    try {
        $query = "SELECT NIK_NIP, email, nama_lengkap, no_wa FROM user WHERE role = 'Admin'";
        $stmt = $pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting admins: " . $e->getMessage());
        return [];
    }
}

function notifyPemohon($id_pendaftaran, $deskripsi, $id_pengajuan = null)
{
    $user = getUserByPendaftaran($id_pendaftaran);
    if (!$user) return ['db' => false, 'wa' => null];

    return sendNotification(
        $user['NIK_NIP'],
        $id_pendaftaran,
        $user['email'],
        $deskripsi,
        $user['no_wa'] ?? null,
        $id_pengajuan
    );
}

function notifyAllAdmins($id_pendaftaran, $deskripsi, $id_pengajuan = null)
{
    $admins = getAllAdmins();
    $db_success = 0;
    $wa_success = 0;

    foreach ($admins as $admin) {
        $result = sendNotification(
            $admin['NIK_NIP'],
            $id_pendaftaran,
            $admin['email'],
            $deskripsi,
            $admin['no_wa'] ?? null,
            $id_pengajuan
        );

        if ($result['db']) $db_success++;
        if ($result['wa'] && isset($result['wa']['status']) && $result['wa']['status'] === true) {
            $wa_success++;
        }
    }
    return [
        'db_count' => $db_success,
        'wa_count' => $wa_success,
        'total_admins' => count($admins)
    ];
}

class NotificationTemplates
{

    public static function tidakBisaDifasilitasi($alasan)
    {
        return "Maaf, pendaftaran merek Anda tidak bisa difasilitasi.\n\nAlasan: {$alasan}";
    }

    public static function konfirmasiMerekAlternatif($merek_no, $alasan)
    {
        return "Merek Alternatif {$merek_no} Anda telah dipilih untuk difasilitasi.\n\nAlasan: {$alasan}\n\nMohon konfirmasi jika Anda ingin melanjutkan proses dengan merek ini.";
    }

    public static function suratKeteranganDifasilitasi()
    {
        return "Selamat! Merek Alternatif 1 (Utama) Anda telah disetujui untuk difasilitasi.\n\nSilakan lengkapi Surat Keterangan Difasilitasi yang tersedia di halaman Status Pendaftaran.";
    }

    public static function suratKeteranganTersedia()
    {
        return "Surat Keterangan Difasilitasi telah tersedia.\n\nSilakan download, tanda tangani di atas materai Rp 10.000, dan upload kembali surat tersebut di halaman Status Pendaftaran.";
    }

    public static function suratIKMTersedia()
    {
        return "Surat Keterangan IKM telah tersedia untuk didownload di halaman Status Pendaftaran.";
    }

    public static function buktiPendaftaranTersedia()
    {
        return "Bukti Pendaftaran merek Anda telah tersedia dan sudah diajukan ke Kementerian.\n\nSilakan download di halaman Status Pendaftaran.";
    }

    public static function sertifikatTerbit()
    {
        return "Selamat! Sertifikat merek Anda telah terbit dan DITERIMA oleh Kementerian.\n\nSilakan download di halaman Status Pendaftaran. Masa berlaku sertifikat adalah 10 tahun.";
    }

    public static function suratPenolakan()
    {
        return "Mohon maaf, permohonan merek Anda tidak dapat disetujui oleh Kementerian.\n\nSilakan download Surat Penolakan di halaman Status Pendaftaran untuk mengetahui alasan detail.";
    }

    public static function pemohonUploadSuratTTD($id_pendaftaran, $nama_pemohon)
    {
        return "Pemohon {$nama_pemohon} telah mengupload Surat Keterangan yang ditandatangani untuk pendaftaran #{$id_pendaftaran}.\n\nSilakan cek dan lanjutkan proses di halaman Detail Pendaftar.";
    }

    public static function pemohonKonfirmasiLanjut($id_pendaftaran, $nama_pemohon, $merek_no)
    {
        return "Pemohon {$nama_pemohon} telah mengkonfirmasi untuk melanjutkan proses dengan Merek Alternatif {$merek_no} pada pendaftaran #{$id_pendaftaran}.\n\nSilakan lanjutkan proses di halaman Detail Pendaftar.";
    }

    public static function pendaftaranBaru($id_pendaftaran, $nama_pemohon, $nama_usaha)
    {
        return "Pendaftaran merek baru #{$id_pendaftaran} dari {$nama_pemohon} ({$nama_usaha}) menunggu verifikasi.\n\nSilakan cek di halaman Daftar Pendaftaran.";
    }
}

function notifSuratKeteranganIKM($id_pendaftaran)
{
    return notifyPemohon($id_pendaftaran, NotificationTemplates::suratIKMTersedia());
}

function notifBuktiPendaftaran($id_pendaftaran)
{
    return notifyPemohon($id_pendaftaran, NotificationTemplates::buktiPendaftaranTersedia());
}

function notifSertifikatMerek($id_pendaftaran)
{
    return notifyPemohon($id_pendaftaran, NotificationTemplates::sertifikatTerbit());
}

function notifSuratPenolakan($id_pendaftaran)
{
    return notifyPemohon($id_pendaftaran, NotificationTemplates::suratPenolakan());
}

/**
 * PERBAIKAN: Fungsi untuk pengajuan surat keterangan IKM
 */
function notifSuratKeteranganIKMSuket($id_pengajuan)
{
    global $pdo;

    error_log("\n========================================");
    error_log("🔔 notifSuratKeteranganIKMSuket CALLED");
    error_log("ID Pengajuan: " . $id_pengajuan);
    error_log("========================================");

    try {
        $user = getUserByPengajuan($id_pengajuan);

        if (!$user) {
            error_log("❌ User not found for ID: " . $id_pengajuan);
            error_log("========================================\n");
            return false;
        }

        error_log("✅ User found: " . $user['nama_lengkap']);
        error_log("📞 No WA: " . ($user['no_wa'] ?? 'NULL'));
        error_log("📧 Email: " . $user['email']);

        $deskripsi_panel = "Surat Keterangan IKM Anda telah terbit dan tersedia untuk diunduh. Silakan download surat di halaman Status Pengajuan Surat.";

        // PERBAIKAN: Gunakan sendNotification dengan parameter id_pengajuan
        $result = sendNotification(
            $user['NIK_NIP'],
            null, // id_pendaftaran = null untuk pengajuan surat
            $user['email'],
            $deskripsi_panel,
            $user['no_wa'] ?? null,
            $id_pengajuan // tambahkan id_pengajuan
        );

        error_log("📊 Notification Result:");
        error_log("  - DB: " . ($result['db'] ? 'SUCCESS' : 'FAILED'));
        error_log("  - WA Status: " . (isset($result['wa']['status']) ? ($result['wa']['status'] ? 'SUCCESS' : 'FAILED') : 'NULL'));

        if (isset($result['wa']['message'])) {
            error_log("  - WA Message: " . $result['wa']['message']);
        }

        error_log("========================================\n");

        return $result['db'];
    } catch (PDOException $e) {
        error_log("❌ PDOException: " . $e->getMessage());
        error_log("========================================\n");
        return false;
    }
}
