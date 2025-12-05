<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

include 'process/config_db.php';

// ===== CEK APAKAH USER SUDAH LOGIN =====
if (!isset($_SESSION['NIK_NIP'])) {
    // Jika AJAX request
    if (isset($_POST['ajax_action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit();
    }
    header("Location: login.php");
    exit();
}

$NIK = $_SESSION['NIK_NIP'];

// ===== HANDLER AJAX GENERATE SURAT =====
// ===== HANDLER AJAX GENERATE SURAT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'generate_surat') {
    // Matikan semua output buffering dulu
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffer
    ob_start();
    
    // PENTING: Set header JSON di awal
    header('Content-Type: application/json');
    
    // Matikan error display
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    try {
        error_log("=== AJAX GENERATE SURAT STARTED ===");
        error_log("NIK: " . $NIK);

        $signature_data = $_POST['signature_data'] ?? '';
        if (empty($signature_data)) {
            throw new Exception('Data tanda tangan kosong');
        }

        error_log("Signature data length: " . strlen($signature_data));

        // Start transaction
        $pdo->beginTransaction();

        // Ambil data pendaftaran terakhir
        $stmt = $pdo->prepare("
            SELECT p.*, u.id_usaha
            FROM pendaftaran p
            LEFT JOIN datausaha u ON p.id_usaha = u.id_usaha
            WHERE p.NIK = :nik 
            ORDER BY p.tgl_daftar DESC
            LIMIT 1
        ");
        $stmt->execute(['nik' => $NIK]);
        $pendaftaran_lama = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pendaftaran_lama) {
            throw new Exception('Data pendaftaran tidak ditemukan');
        }

        error_log("Pendaftaran ID: " . $pendaftaran_lama['id_pendaftaran']);

        // Cek apakah sudah ada draft perpanjangan
        $stmt = $pdo->prepare("
            SELECT id_perpanjangan FROM perpanjangan 
            WHERE NIK = :nik AND status_perpanjangan = 'Draft'
            ORDER BY tgl_pengajuan DESC LIMIT 1
        ");
        $stmt->execute(['nik' => $NIK]);
        $draft_existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($draft_existing) {
            $id_perpanjangan = $draft_existing['id_perpanjangan'];
            error_log("Menggunakan draft perpanjangan yang sudah ada: " . $id_perpanjangan);
        } else {
            $tgl_pengajuan = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                INSERT INTO perpanjangan (id_pendaftaran_lama, NIK, id_usaha, tgl_pengajuan, status_perpanjangan)
                VALUES (?, ?, ?, ?, 'Draft')
            ");

            $result = $stmt->execute([
                $pendaftaran_lama['id_pendaftaran'],
                $NIK,
                $pendaftaran_lama['id_usaha'],
                $tgl_pengajuan
            ]);

            if (!$result) {
                throw new Exception('Gagal menyimpan data perpanjangan');
            }

            $id_perpanjangan = $pdo->lastInsertId();
            error_log("ID Perpanjangan Draft Baru: " . $id_perpanjangan);
        }

        // Simpan tanda tangan
        $signature_data = str_replace('data:image/png;base64,', '', $signature_data);
        $signature_data = str_replace(' ', '+', $signature_data);
        $signature_decoded = base64_decode($signature_data, true);

        if (!$signature_decoded) {
            throw new Exception('Gagal decode tanda tangan');
        }

        error_log("Signature decoded successfully, size: " . strlen($signature_decoded));

        $folder = "uploads/ttd_perpanjangan/ttd_{$NIK}/";
        if (!file_exists($folder)) {
            if (!mkdir($folder, 0777, true)) {
                throw new Exception('Gagal membuat folder: ' . $folder);
            }
        }

        // Hapus TTD lama jika ada
        $stmt = $pdo->prepare("SELECT file_ttd FROM perpanjangan WHERE id_perpanjangan = ?");
        $stmt->execute([$id_perpanjangan]);
        $old_ttd = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($old_ttd && $old_ttd['file_ttd'] && file_exists($old_ttd['file_ttd'])) {
            unlink($old_ttd['file_ttd']);
            error_log("Old TTD deleted: " . $old_ttd['file_ttd']);
        }

        $filename = "ttd_{$NIK}_" . time() . ".png";
        $filepath = $folder . $filename;

        $bytes_written = file_put_contents($filepath, $signature_decoded);
        if ($bytes_written === false) {
            throw new Exception('Gagal menyimpan tanda tangan ke file');
        }

        error_log("TTD saved: " . $filepath . " (" . $bytes_written . " bytes)");

        // Update perpanjangan dengan file_ttd
        $stmt = $pdo->prepare("UPDATE perpanjangan SET file_ttd = ? WHERE id_perpanjangan = ?");
        if (!$stmt->execute([$filepath, $id_perpanjangan])) {
            throw new Exception('Gagal update file_ttd di database');
        }

        error_log("Starting PDF generation...");

        // Generate PDF Surat - PERBAIKAN DI SINI
        if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
            throw new Exception('Autoload vendor tidak ditemukan');
        }
        
        require_once __DIR__ . '/vendor/autoload.php';

        $GLOBALS['id_perpanjangan_global'] = $id_perpanjangan;
        $GLOBALS['pdo_global'] = $pdo;
        $GLOBALS['NIK_global'] = $NIK;

        // PERBAIKAN: Cek file exist
        $generate_file = __DIR__ . '/generate_surat_perpanjangan.php';
        if (!file_exists($generate_file)) {
            throw new Exception('File generate_surat_perpanjangan.php tidak ditemukan');
        }

        // Capture semua output dari generate script
        ob_start();
        try {
            define('GENERATE_FROM_PERPANJANGAN', true);
            require_once $generate_file;
        } catch (Exception $genError) {
            ob_end_clean();
            throw new Exception('Error saat generate PDF: ' . $genError->getMessage());
        }
        $generate_output = ob_get_clean();

        if (!empty($generate_output)) {
            error_log("Generate script output (discarded): " . substr($generate_output, 0, 500));
        }

        error_log("PDF generation completed");

        // Ambil path surat yang baru dibuat
        $stmt = $pdo->prepare("
            SELECT file_path FROM lampiran 
            WHERE id_pendaftaran = ? AND id_jenis_file = 17 
            ORDER BY tgl_upload DESC LIMIT 1
        ");
        $stmt->execute([$id_perpanjangan]);
        $surat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$surat) {
            throw new Exception('Surat gagal dibuat - tidak ditemukan di database');
        }

        if (!file_exists($surat['file_path'])) {
            throw new Exception('File surat tidak ditemukan: ' . $surat['file_path']);
        }

        error_log("Surat file verified: " . $surat['file_path']);

        // Simpan id_perpanjangan ke session untuk digunakan saat submit
        $_SESSION['draft_id_perpanjangan'] = $id_perpanjangan;

        $pdo->commit();
        error_log("=== AJAX GENERATE SURAT COMPLETED ===");

        // PERBAIKAN: Bersihkan buffer dan output JSON
        ob_clean();
        
        $response = [
            'success' => true,
            'message' => 'Surat berhasil dibuat',
            'file_path' => $surat['file_path'],
            'id_perpanjangan' => $id_perpanjangan
        ];
        
        echo json_encode($response);
        ob_end_flush();
        exit();
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("❌ AJAX Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());

        // PERBAIKAN: Bersihkan buffer dan output JSON error
        ob_clean();
        
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
        
        echo json_encode($response);
        ob_end_flush();
        exit();
    }
}

// ... (lanjutkan dengan handler submit form dan ambil data seperti sebelumnya)

// ===== PROSES SUBMIT FORM =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_perpanjangan'])) {
    try {
        error_log("=== SUBMIT PERPANJANGAN PROCESS ===");

        // Ambil draft_id_perpanjangan dari session
        $id_perpanjangan = $_SESSION['draft_id_perpanjangan'] ?? 0;

        if (!$id_perpanjangan) {
            throw new Exception('Draft perpanjangan tidak ditemukan. Silakan simpan tanda tangan terlebih dahulu.');
        }

        // Verifikasi draft perpanjangan
        $stmt = $pdo->prepare("
            SELECT * FROM perpanjangan 
            WHERE id_perpanjangan = ? AND NIK = ? AND status_perpanjangan = 'Draft'
        ");
        $stmt->execute([$id_perpanjangan, $NIK]);
        $draft = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$draft) {
            throw new Exception('Draft perpanjangan tidak valid atau sudah disubmit');
        }

        // Update status perpanjangan menjadi aktif
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE perpanjangan 
            SET status_perpanjangan = 'Menunggu Surat Keterangan IKM',
                tgl_pengajuan = NOW()
            WHERE id_perpanjangan = ?
        ");
        $stmt->execute([$id_perpanjangan]);

        $pdo->commit();

        // Hapus session draft
        unset($_SESSION['draft_id_perpanjangan']);

        // Set session untuk notifikasi
        $_SESSION['alert_message'] = 'Permohonan perpanjangan berhasil diajukan! Surat permohonan telah dibuat.';
        $_SESSION['alert_type'] = 'success';
        $_SESSION['baru_perpanjangan'] = true;
        $_SESSION['id_perpanjangan_baru'] = $id_perpanjangan;

        error_log("=== PERPANJANGAN SUBMITTED SUCCESSFULLY ===");

        header("Location: status-seleksi-pendaftaran.php", true, 302);
        exit();
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Submit Error: " . $e->getMessage());
        $_SESSION['alert_message'] = 'Terjadi kesalahan: ' . $e->getMessage();
        $_SESSION['alert_type'] = 'danger';
        header("Location: perpanjangan.php", true, 302);
        exit();
    }
}


// ===== AMBIL DATA PENDAFTARAN TERAKHIR =====
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               u.nama_usaha, u.rt_rw AS usaha_rt_rw, u.kel_desa, u.kecamatan, u.no_telp_perusahaan,
               u.hasil_produk, u.jml_tenaga_kerja,
               m.nama_merek1, m.nama_merek2, m.nama_merek3, m.kelas_merek,
               usr.nama_lengkap, usr.kel_desa AS user_kel_desa, usr.kecamatan AS user_kecamatan, 
               usr.rt_rw AS user_rt_rw, usr.no_wa, usr.email,
               usr.nama_kabupaten, usr.nama_provinsi
        FROM pendaftaran p
        LEFT JOIN datausaha u ON p.id_usaha = u.id_usaha
        LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
        LEFT JOIN user usr ON p.NIK = usr.NIK_NIP
        WHERE p.NIK = :nik 
        ORDER BY p.tgl_daftar DESC
        LIMIT 1
    ");
    $stmt->execute(['nik' => $NIK]);
    $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pendaftaran) {
        $_SESSION['alert_message'] = 'Anda belum memiliki data pendaftaran. Silakan daftar terlebih dahulu.';
        $_SESSION['alert_type'] = 'warning';
        header("Location: form-pendaftaran.php");
        exit();
    }

    // Ambil sertifikat lama
    $stmt = $pdo->prepare("
        SELECT file_path, tgl_upload 
        FROM lampiran 
        WHERE id_pendaftaran = :id_pendaftaran 
        AND id_jenis_file = 7
        ORDER BY tgl_upload DESC 
        LIMIT 1
    ");
    $stmt->execute(['id_pendaftaran' => $pendaftaran['id_pendaftaran']]);
    $sertifikat_lama = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buat alamat lengkap
    $alamat_parts = array();
    if (!empty($pendaftaran['user_kel_desa'])) $alamat_parts[] = $pendaftaran['user_kel_desa'];
    if (!empty($pendaftaran['user_rt_rw'])) $alamat_parts[] = 'RT/RW: ' . $pendaftaran['user_rt_rw'];
    if (!empty($pendaftaran['user_kecamatan'])) $alamat_parts[] = $pendaftaran['user_kecamatan'];
    if (!empty($pendaftaran['nama_kabupaten'])) $alamat_parts[] = $pendaftaran['nama_kabupaten'];
    if (!empty($pendaftaran['nama_provinsi'])) $alamat_parts[] = $pendaftaran['nama_provinsi'];
    $alamat_lengkap = implode(', ', $alamat_parts);

    // Tentukan merek yang difasilitasi
    $merek_difasilitasi = '';
    $no_merek_difasilitasi = 1;

    if ($pendaftaran['merek_difasilitasi'] == 1) {
        $merek_difasilitasi = $pendaftaran['nama_merek1'];
        $no_merek_difasilitasi = 1;
    } elseif ($pendaftaran['merek_difasilitasi'] == 2) {
        $merek_difasilitasi = $pendaftaran['nama_merek2'];
        $no_merek_difasilitasi = 2;
    } elseif ($pendaftaran['merek_difasilitasi'] == 3) {
        $merek_difasilitasi = $pendaftaran['nama_merek3'];
        $no_merek_difasilitasi = 3;
    } else {
        $merek_difasilitasi = $pendaftaran['nama_merek1'];
        $no_merek_difasilitasi = 1;
    }
} catch (PDOException $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $_SESSION['alert_message'] = 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage();
    $_SESSION['alert_type'] = 'danger';
    header("Location: status-seleksi-pendaftaran.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perpanjangan Sertifikat Merek - Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/form-pendaftaran.css">
    <style>
        .data-static {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            color: #495057;
            font-weight: 500;
        }

        .section-title {
            color: #0d6efd;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0d6efd;
        }

        .signature-container {
            border: 2px solid #0d6efd;
            border-radius: 0.5rem;
            padding: 1rem;
            background-color: #fff;
        }

        #signature-pad {
            border: 2px dashed #6c757d;
            border-radius: 0.375rem;
            cursor: crosshair;
            background-color: #ffffff;
            touch-action: none;
        }

        .signature-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .signature-preview {
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background-color: #f8f9fa;
            display: none;
        }

        .signature-preview img {
            max-width: 100%;
            height: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }

        /* Style untuk preview surat */
        #preview-surat-container {
            display: none;
            margin-top: 2rem;
            padding: 1.5rem;
            border: 2px solid #28a745;
            border-radius: 0.5rem;
            background-color: #f8f9fa;
        }

        #preview-surat-container.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #28a745;
        }

        .preview-header h5 {
            margin: 0;
            color: #28a745;
            font-weight: 600;
        }

        .preview-actions {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>

<body>
    <?php include 'navbar-login.php' ?>

    <div class="container main-container">
        <div class="row cont">
            <!-- Sidebar -->
            <div class="col-lg-4">
                <h5 class="judul">Fasilitasi Surat Keterangan IKM untuk Perpanjangan Merek</h5>
                <p>Pemohon hanya mendapatkan Surat Keterangan IKM (Industri Kecil Menengah) untuk melakukan Perpanjangan Merek di Kemenkumham RI.</p>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-info-square pe-2"></i> Informasi</h5>
                    <ul class="list-unstyled info-list">
                        <li>Output: <br> Surat Keterangan IKM untuk Perpanjangan Sertifikat Merek</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-journal-check pe-2"></i>Syarat dan Ketentuan</h5>
                    <ul class="info-list">
                        <li>Memiliki sertifikat merek yang akan habis masa berlakunya (≤ 1 tahun)</li>
                        <li>Industri Kecil yang masih aktif memproduksi di Sidoarjo</li>
                        <li>Data usaha masih sama dengan pendaftaran sebelumnya</li>
                        <li>Membuat tanda tangan digital sebagai persetujuan</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-journal pe-2"></i>Catatan</h5>
                    <ul class="info-list">
                        <li>Perpanjangan dapat diajukan maksimal 1 tahun sebelum masa berlaku habis</li>
                        <li>Pastikan data usaha Anda masih aktif dan valid</li>
                        <li>Tanda tangan digital akan digunakan sebagai bukti persetujuan</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle bg-warning bg-opacity-10">
                    <h5><i class="bi bi-exclamation-triangle pe-2"></i>Perhatian</h5>
                    <ul class="info-list">
                        <li>Data yang ditampilkan diambil dari pendaftaran sebelumnya</li>
                        <li>Jika ada perubahan data, silakan hubungi admin</li>
                        <li>Tanda tangan harus jelas dan sesuai dengan identitas</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5>Bantuan</h5>
                    <p>Jika ada kendala dalam mengisi formulir bisa menghubungi kami dibawah ini.</p>
                    <a href="https://wa.me/6281235051286?text=Halo%2C%20saya%20ingin%20bertanya%20mengenai%20perpanjangan%20sertifikat%20merek" class="help-contact" target="_blank">
                        <i class="fab fa-whatsapp pe-2"></i> Bidang Perindustrian Disperindag Sidoarjo
                    </a>
                    <p class="text-danger mt-2">* Tidak menerima panggilan, hanya chat.</p>
                </div>
            </div>

            <!-- Form Content -->
            <div class="col-lg-8">
                <div class="form-container border border-light-subtle">
                    <h4>Perpanjangan Sertifikat Merek</h4>
                    <hr class="border-2 border-secondary w-100">

                    <form method="POST" id="formPerpanjangan">
                        <!-- Data Usaha (Statis) -->
                        <h5 class="section-title">Data Usaha</h5>

                        <div class="mb-3">
                            <label class="form-label">Nama Usaha</label>
                            <div class="data-static"><?php echo htmlspecialchars($pendaftaran['nama_usaha']); ?></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kecamatan</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['kecamatan']); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kelurahan/Desa</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['kel_desa']); ?></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">RT/RW</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['usaha_rt_rw'] ?: '-'); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Telepon Perusahaan</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['no_telp_perusahaan'] ?: '-'); ?></div>
                            </div>
                        </div>

                        <!-- Data Pemilik (Statis) -->
                        <h5 class="section-title mt-4">Data Pemilik</h5>

                        <div class="mb-3">
                            <label class="form-label">Nama Pemilik</label>
                            <div class="data-static"><?php echo htmlspecialchars($pendaftaran['nama_lengkap']); ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alamat Pemilik</label>
                            <div class="data-static"><?php echo htmlspecialchars($alamat_lengkap); ?></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Telepon Pemilik</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['no_wa']); ?></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['email']); ?></div>
                            </div>
                        </div>

                        <!-- Informasi Usaha -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Usaha</label>
                                <div class="data-static">Industri Kecil</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jumlah Tenaga Kerja</label>
                                <div class="data-static"><?php echo htmlspecialchars($pendaftaran['jml_tenaga_kerja']); ?> orang</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Produk</label>
                            <div class="data-static"><?php echo htmlspecialchars($pendaftaran['hasil_produk']); ?></div>
                        </div>

                        <!-- Informasi Merek (Statis) -->
                        <h5 class="section-title mt-4">Informasi Merek</h5>

                        <div class="mb-3">
                            <label class="form-label">Merek yang Difasilitasi</label>
                            <div class="data-static">
                                <strong><?php echo htmlspecialchars($merek_difasilitasi); ?></strong>
                                <br>
                                <small class="text-muted">
                                    (Merek Alternatif <?php echo $no_merek_difasilitasi; ?> dari pendaftaran sebelumnya)
                                </small>
                            </div>
                        </div>

                        <!-- Sertifikat Lama -->
                        <?php if ($sertifikat_lama): ?>
                            <h5 class="section-title mt-4">Sertifikat yang Terdaftar</h5>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="card border-success">
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-3">
                                                <i class="fa-solid fa-certificate me-2 text-success"></i>
                                                Sertifikat Merek Terdaftar
                                            </h6>
                                            <div class="alert alert-success mb-3">
                                                <i class="fa-solid fa-check-circle me-2"></i>
                                                <strong>File Tersedia</strong>
                                                <p class="mb-0 mt-2 small">
                                                    <i class="fa-solid fa-calendar me-1"></i>
                                                    Diupload: <?php echo date('d/m/Y H:i', strtotime($sertifikat_lama['tgl_upload'])); ?> WIB
                                                </p>
                                            </div>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-success btn-view-sertifikat"
                                                    data-src="<?php echo htmlspecialchars($sertifikat_lama['file_path']); ?>"
                                                    data-title="Sertifikat Merek">
                                                    <i class="fas fa-eye me-1"></i> Preview
                                                </button>
                                                <a class="btn btn-success btn-sm" href="<?php echo htmlspecialchars($sertifikat_lama['file_path']); ?>" target="_blank" download>
                                                    <i class="fa-solid fa-download me-1"></i> Download Sertifikat
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mt-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Tidak ada sertifikat lama yang terdaftar. Silakan hubungi admin.
                            </div>
                        <?php endif; ?>

                        <!-- Tanda Tangan Digital -->
                        <h5 class="section-title mt-4">Tanda Tangan Digital</h5>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Petunjuk:</strong> Buat tanda tangan Anda di area di bawah ini. Tanda tangan ini akan digunakan sebagai bukti persetujuan permohonan perpanjangan.
                        </div>

                        <div class="signature-container">
                            <label class="form-label">Buat Tanda Tangan Anda <span class="text-danger">*</span></label>
                            <canvas id="signature-pad" width="600" height="200"></canvas>

                            <div class="signature-buttons">
                                <button type="button" class="btn btn-secondary btn-sm" id="clear-signature">
                                    <i class="fas fa-eraser me-1"></i> Hapus
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" id="save-signature">
                                    <i class="fas fa-check me-1"></i> Simpan Tanda Tangan
                                </button>
                            </div>

                            <div class="signature-preview" id="signature-preview">
                                <label class="form-label">Preview Tanda Tangan:</label>
                                <img id="signature-image" src="" alt="Tanda Tangan">
                            </div>

                            <input type="hidden" name="signature_data" id="signature-data" required>
                        </div>

                        <!-- ✅ PREVIEW SURAT PERPANJANGAN -->
                        <div id="preview-surat-container">
                            <div class="preview-header">
                                <h5>
                                    <i class="fas fa-file-signature me-2"></i>
                                    Surat Permohonan Perpanjangan
                                </h5>
                                <div class="preview-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-preview-surat">
                                        <i class="fas fa-eye me-1"></i> Lihat Surat
                                    </button>
                                </div>
                            </div>

                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Surat Berhasil Dibuat!</strong>
                                <p class="mb-0 mt-2">Surat permohonan perpanjangan telah dibuat. Anda dapat melihat preview surat dengan menekan tombol "Lihat Surat" di atas.</p>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Langkah Selanjutnya:</strong>
                                <ol class="mb-0 mt-2" style="padding-left: 20px;">
                                    <li>Periksa surat permohonan yang telah dibuat</li>
                                    <li>Jika sudah sesuai, klik tombol <strong>"Kirim Permohonan Perpanjangan"</strong> di bawah</li>
                                    <li>Surat akan tersimpan dan dapat didownload di halaman status</li>
                                </ol>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <input type="hidden" name="submit_perpanjangan" value="1">
                        <div class="text-center mt-4">
                            <div class="alert alert-warning d-inline-block mb-3" style="max-width: 600px;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Perhatian:</strong> Dengan menekan tombol "Kirim Permohonan Perpanjangan", Anda menyatakan bahwa data yang ditampilkan masih valid dan tanda tangan digital yang dibuat adalah asli.
                            </div>
                            <br>
                            <button type="submit" class="btn btn-submitpendaftaran" id="btnSubmit" disabled>
                                <i class="fas fa-paper-plane pe-2"></i> Kirim Permohonan Perpanjangan
                            </button>
                            <br>
                            <small class="text-muted mt-2" id="submit-hint">* Tombol akan aktif setelah tanda tangan dibuat</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>Copyright © 2025. All Rights Reserved.</p>
            <p>Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
        </div>
    </footer>

    <!-- Modal View -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2 bg-light">
                    <h6 class="modal-title mb-0" id="modalTitle"></h6>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div id="imageContainer" style="display: none;">
                        <img id="modalImage" src="" alt="Preview" class="img-fluid rounded" style="max-height: 50vh; width: 100%; object-fit: contain;" />
                    </div>
                    <div id="pdfContainer" style="display: none;">
                        <iframe id="modalPdf" src="" style="width: 100%; height: 50vh; border: 1px solid #dee2e6; border-radius: 0.375rem;"></iframe>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <a id="downloadBtn" href="#" download class="btn btn-success btn-sm">
                        <i class="fas fa-download me-1"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Alert Modal
        function showAlert(message, type = 'warning') {
            const icon = type === 'danger' ? '❌' : type === 'success' ? '✅' : '⚠️';
            const alertModal = `
                <div class="modal fade" id="alertModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content">
                            <div class="modal-body text-center p-4">
                                <div class="fs-1 mb-3">${icon}</div>
                                <p class="mb-0" style="white-space: pre-line;">${message}</p>
                            </div>
                            <div class="modal-footer border-0 justify-content-center">
                                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            const existingModal = document.getElementById('alertModal');
            if (existingModal) existingModal.remove();
            document.body.insertAdjacentHTML('beforeend', alertModal);
            const modal = new bootstrap.Modal(document.getElementById('alertModal'));
            modal.show();
            modal.addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Session alert
        <?php if (isset($_SESSION['alert_message'])): ?>
            showAlert(<?php echo json_encode($_SESSION['alert_message']); ?>, '<?php echo $_SESSION['alert_type']; ?>');
            <?php
            unset($_SESSION['alert_message']);
            unset($_SESSION['alert_type']);
            ?>
        <?php endif; ?>

        // Signature Pad Variables
        const canvas = document.getElementById('signature-pad');
        const ctx = canvas.getContext('2d');
        const clearBtn = document.getElementById('clear-signature');
        const saveBtn = document.getElementById('save-signature');
        const signatureData = document.getElementById('signature-data');
        const signaturePreview = document.getElementById('signature-preview');
        const signatureImage = document.getElementById('signature-image');
        const btnSubmit = document.getElementById('btnSubmit');
        const previewSuratContainer = document.getElementById('preview-surat-container');
        const submitHint = document.getElementById('submit-hint');

        let isDrawing = false;
        let lastX = 0;
        let lastY = 0;
        let suratGenerated = false;
        let currentSuratPath = '';

        // Setup Canvas
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        // Drawing Functions
        function getPosition(e) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            if (e.touches) {
                return {
                    x: (e.touches[0].clientX - rect.left) * scaleX,
                    y: (e.touches[0].clientY - rect.top) * scaleY
                };
            }
            return {
                x: (e.clientX - rect.left) * scaleX,
                y: (e.clientY - rect.top) * scaleY
            };
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            startDrawing(e);
        });
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            draw(e);
        });
        canvas.addEventListener('touchend', stopDrawing);

        function startDrawing(e) {
            isDrawing = true;
            const pos = getPosition(e);
            lastX = pos.x;
            lastY = pos.y;
        }

        function draw(e) {
            if (!isDrawing) return;
            const pos = getPosition(e);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
        }

        function stopDrawing() {
            isDrawing = false;
        }

        // Clear Button Handler
        clearBtn.addEventListener('click', () => {
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            signatureData.value = '';
            signaturePreview.style.display = 'none';
            previewSuratContainer.classList.remove('show');
            btnSubmit.disabled = true;
            suratGenerated = false;
            currentSuratPath = '';
            submitHint.textContent = '* Tombol akan aktif setelah tanda tangan dibuat';
        });

        // ✅ SAVE SIGNATURE - GENERATE SURAT OTOMATIS VIA AJAX
        saveBtn.addEventListener('click', async () => {
            const imageData = canvas.toDataURL('image/png');

            if (!imageData || imageData === 'data:,') {
                showAlert('Silakan buat tanda tangan terlebih dahulu!', 'warning');
                return;
            }

            // Tampilkan preview tanda tangan
            signatureData.value = imageData;
            signatureImage.src = imageData;
            signaturePreview.style.display = 'block';

            // Disable button saat proses
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Membuat Surat...';

            try {
                // Kirim AJAX untuk generate surat
                const formData = new FormData();
                formData.append('ajax_action', 'generate_surat');
                formData.append('signature_data', imageData);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                // Debug: Cek response
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers.get('content-type'));

                // Cek apakah response adalah JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Response bukan JSON:', text);
                    throw new Error('Server mengembalikan response yang tidak valid. Silakan periksa console untuk detail.');
                }

                const result = await response.json();
                console.log('Result:', result);

                if (result.success) {
                    // Surat berhasil dibuat
                    currentSuratPath = result.file_path;
                    suratGenerated = true;

                    // Tampilkan preview surat
                    previewSuratContainer.classList.add('show');

                    // Aktifkan tombol submit
                    btnSubmit.disabled = false;
                    submitHint.textContent = '* Periksa surat terlebih dahulu sebelum mengirim';

                    // Restore button
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-check me-1"></i> Simpan Tanda Tangan';

                    showAlert('Tanda tangan berhasil disimpan!\n\nSurat permohonan perpanjangan telah dibuat.\nSilakan periksa surat di bawah sebelum mengirim.', 'success');

                    // Scroll ke preview surat
                    setTimeout(() => {
                        previewSuratContainer.scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest'
                        });
                    }, 500);
                } else {
                    throw new Error(result.message || 'Gagal membuat surat');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Terjadi kesalahan saat membuat surat: ' + error.message, 'danger');

                // Restore button
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-check me-1"></i> Simpan Tanda Tangan';
            }
        });

        // ✅ HANDLER PREVIEW SURAT (Modal PDF)
        document.getElementById('btn-preview-surat').addEventListener('click', function() {
            if (!currentSuratPath) {
                showAlert('Surat belum tersedia. Silakan simpan tanda tangan terlebih dahulu.', 'warning');
                return;
            }

            // Buka modal preview
            const modalTitle = document.getElementById('modalTitle');
            const downloadBtn = document.getElementById('downloadBtn');
            const imageContainer = document.getElementById('imageContainer');
            const pdfContainer = document.getElementById('pdfContainer');
            const modalPdf = document.getElementById('modalPdf');

            modalTitle.textContent = 'Surat Permohonan Perpanjangan';
            downloadBtn.href = currentSuratPath;

            imageContainer.style.display = 'none';
            pdfContainer.style.display = 'block';
            modalPdf.src = currentSuratPath + '#toolbar=0';

            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        });

        // ✅ HANDLER SUBMIT FORM
        document.getElementById('formPerpanjangan').addEventListener('submit', function(e) {
            if (!suratGenerated || !currentSuratPath) {
                e.preventDefault();
                showAlert('Silakan simpan tanda tangan dan tunggu surat dibuat terlebih dahulu!', 'warning');
                return false;
            }

            if (!confirm('Apakah Anda yakin data yang ditampilkan masih valid dan ingin mengajukan perpanjangan sertifikat?\n\nSurat permohonan akan dikirim dan tersimpan di sistem.')) {
                e.preventDefault();
                return false;
            }

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin pe-2"></i> Mengirim Permohonan...';
        });

        // Alert Modal Function
        function showAlert(message, type = 'warning') {
            const icon = type === 'danger' ? '❌' : type === 'success' ? '✅' : '⚠️';
            const alertModal = `
        <div class="modal fade" id="alertModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-body text-center p-4">
                        <div class="fs-1 mb-3">${icon}</div>
                        <p class="mb-0" style="white-space: pre-line;">${message}</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center">
                        <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    `;
            const existingModal = document.getElementById('alertModal');
            if (existingModal) existingModal.remove();
            document.body.insertAdjacentHTML('beforeend', alertModal);
            const modal = new bootstrap.Modal(document.getElementById('alertModal'));
            modal.show();
            document.getElementById('alertModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Preview Sertifikat Handler
        document.querySelectorAll('.btn-view-sertifikat').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const src = this.getAttribute('data-src');
                const title = this.getAttribute('data-title');
                const modalTitle = document.getElementById('modalTitle');
                const downloadBtn = document.getElementById('downloadBtn');
                const imageContainer = document.getElementById('imageContainer');
                const pdfContainer = document.getElementById('pdfContainer');
                const modalPdf = document.getElementById('modalPdf');

                modalTitle.textContent = title;
                downloadBtn.href = src;

                imageContainer.style.display = 'none';
                pdfContainer.style.display = 'block';
                modalPdf.src = src + '#toolbar=0';

                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
            });
        });

        // Clean up modal saat ditutup
        const imageModal = document.getElementById('imageModal');
        imageModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('modalPdf').src = '';
            document.getElementById('modalImage').src = '';
        });
    </script>
</body>

</html>