<?php
require_once 'config_db.php';

header('Content-Type: application/json');

try {
    $type = $_GET['type'] ?? '';
    $parent_kode = $_GET['parent'] ?? '';

    switch ($type) {
        case 'provinsi':
            // Ambil semua provinsi (kode 2 digit)
            $stmt = $pdo->prepare("SELECT kode, nama FROM wilayah WHERE LENGTH(kode) = 2 ORDER BY nama");
            $stmt->execute();
            break;

        case 'kabupaten':
            // Ambil kabupaten berdasarkan provinsi (kode 5 digit, format: XX.YY)
            if (empty($parent_kode)) {
                throw new Exception("Kode provinsi tidak valid");
            }
            $stmt = $pdo->prepare("SELECT kode, nama FROM wilayah WHERE kode LIKE ? AND LENGTH(kode) = 5 ORDER BY nama");
            $stmt->execute([$parent_kode . '.%']);
            break;

        case 'kecamatan':
            // Ambil kecamatan berdasarkan kabupaten (kode 8 digit, format: XX.YY.ZZ)
            if (empty($parent_kode)) {
                throw new Exception("Kode kabupaten tidak valid");
            }
            $stmt = $pdo->prepare("SELECT kode, nama FROM wilayah WHERE kode LIKE ? AND LENGTH(kode) = 8 ORDER BY nama");
            $stmt->execute([$parent_kode . '.%']);
            break;

        case 'desa':
            // Ambil desa berdasarkan kecamatan (kode 13 digit, format: XX.YY.ZZ.NNNN)
            if (empty($parent_kode)) {
                throw new Exception("Kode kecamatan tidak valid");
            }
            $stmt = $pdo->prepare("SELECT kode, nama FROM wilayah WHERE kode LIKE ? AND LENGTH(kode) = 13 ORDER BY nama");
            $stmt->execute([$parent_kode . '.%']);
            break;

        default:
            throw new Exception("Tipe request tidak valid");
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>