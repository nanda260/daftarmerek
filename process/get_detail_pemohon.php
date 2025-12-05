<?php
session_start();
require_once 'config_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['NIK_NIP'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$nik = $input['nik'] ?? '';

if (empty($nik)) {
    echo json_encode(['success' => false, 'message' => 'NIK tidak valid']);
    exit;
}

try {
    // Ambil data user
    $stmt = $pdo->prepare("
        SELECT 
            u.NIK_NIP,
            u.nama_lengkap,
            u.email,
            u.no_wa,
            u.foto_ktp,
            CONCAT_WS(', ',
                NULLIF(CONCAT('RT/RW ', u.rt_rw), 'RT/RW '),
                NULLIF(u.kel_desa, ''),
                NULLIF(u.kecamatan, ''),
                NULLIF(u.nama_kabupaten, ''),
                NULLIF(u.nama_provinsi, '')
            ) as alamat_lengkap
        FROM user u
        WHERE u.NIK_NIP = ?
    ");
    $stmt->execute([$nik]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Data pengguna tidak ditemukan']);
        exit;
    }
    
    // Ambil pendaftaran terakhir
    $stmt = $pdo->prepare("
        SELECT id_pendaftaran 
        FROM pendaftaran 
        WHERE NIK = ? 
        ORDER BY tgl_daftar DESC 
        LIMIT 1
    ");
    $stmt->execute([$nik]);
    $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $lampiran = [
        'nib' => [],
        'legalitas' => [],
        'akta' => []
    ];
    
    if ($pendaftaran) {
        $id_pendaftaran = $pendaftaran['id_pendaftaran'];
        
        // Ambil lampiran
        $stmt = $pdo->prepare("
            SELECT l.*, mf.nama_jenis_file 
            FROM lampiran l
            INNER JOIN masterfilelampiran mf ON l.id_jenis_file = mf.id_jenis_file
            WHERE l.id_pendaftaran = ?
            ORDER BY l.id_jenis_file
        ");
        $stmt->execute([$id_pendaftaran]);
        $lampiran_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($lampiran_data as $item) {
            if ($item['id_jenis_file'] == 1) {
                // NIB
                $lampiran['nib'][] = $item;
            } elseif ($item['id_jenis_file'] >= 9 && $item['id_jenis_file'] <= 14) {
                // Legalitas (P-IRT, BPOM, HALAL, dll)
                $lampiran['legalitas'][] = $item;
            } elseif ($item['id_jenis_file'] == 15) {
                // Akta
                $lampiran['akta'][] = $item;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user' => $user,
            'lampiran' => $lampiran
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('Error get_detail_pemohon: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan database']);
}
?>