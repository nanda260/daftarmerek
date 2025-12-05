<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['NIK_NIP'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'config_db.php';

$input = json_decode(file_get_contents('php://input'), true);
$id_notif = intval($input['id_notif'] ?? 0);
$nik = $_SESSION['NIK_NIP'];

if (!$id_notif) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit();
}

try {
    // Update notifikasi menjadi sudah dibaca
    $stmt = $pdo->prepare("
        UPDATE notifikasi 
        SET is_read = 1 
        WHERE id_notif = ? AND NIK_NIP = ?
    ");
    
    $stmt->execute([$id_notif, $nik]);

    echo json_encode([
        'success' => true,
        'message' => 'Notifikasi sudah dibaca'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
