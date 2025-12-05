<?php
session_start();
require_once 'config_db.php';

header('Content-Type: application/json');

// Pastikan user sudah login
if (!isset($_SESSION['NIK_NIP'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$nik_nip = $_SESSION['NIK_NIP'];

try {
    // Ambil notifikasi berdasarkan NIK_NIP user yang login
    // PERBAIKAN: Gunakan COALESCE untuk menangani id_pendaftaran yang NULL (untuk pengajuan surat)
    $query = "SELECT 
                n.id_notif,
                n.NIK_NIP,
                COALESCE(n.id_pendaftaran, 0) as id_pendaftaran,
                n.email,
                n.deskripsi,
                n.tgl_notif,
                COALESCE(n.is_read, 0) as is_read
              FROM notifikasi n
              WHERE n.NIK_NIP = :nik_nip
              ORDER BY n.tgl_notif DESC
              LIMIT 50";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['nik_nip' => $nik_nip]);
    $results = $stmt->fetchAll();
    
    $notifications = [];
    $unread_count = 0;
    
    foreach ($results as $row) {
        $notifications[] = [
            'id_notif' => $row['id_notif'],
            'id_pendaftaran' => $row['id_pendaftaran'], // Bisa 0 untuk pengajuan surat
            'deskripsi' => $row['deskripsi'],
            'tgl_notif' => $row['tgl_notif'],
            'is_read' => $row['is_read']
        ];
        
        if ($row['is_read'] == 0) {
            $unread_count++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
    
} catch (PDOException $e) {
    error_log("Get notifications error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat mengambil notifikasi'
    ]);
}
?>