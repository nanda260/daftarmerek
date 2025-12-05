<?php
session_start();
require_once 'process/config_db.php';
require_once 'process/notification_helper.php';
date_default_timezone_set('Asia/Jakarta');

// Ambil ID pengajuan dari URL
$id_pengajuan = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_pengajuan == 0) {
    header("Location: pengajuan-suket.php");
    exit();
}

// ===== HANDLER AJAX REQUEST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    $action = $_POST['ajax_action'];

    try {
        if ($action === 'upload_surat_ikm_suket') {
            if (!isset($_FILES['fileSuratIKM']) || $_FILES['fileSuratIKM']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'File tidak valid']);
                exit;
            }

            $file = $_FILES['fileSuratIKM'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'docx', 'doc'];

            if (!in_array($file_extension, $allowed_extensions)) {
                echo json_encode(['success' => false, 'message' => 'Format file tidak diizinkan. Gunakan PDF atau DOCX']);
                exit;
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 10MB']);
                exit;
            }

            // Ambil NIK dari pengajuan
            $stmt = $pdo->prepare("SELECT NIK FROM pengajuansurat WHERE id_pengajuan = ?");
            $stmt->execute([$id_pengajuan]);
            $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pengajuan) {
                echo json_encode(['success' => false, 'message' => 'Data pengajuan tidak ditemukan']);
                exit;
            }

            $nik = $pengajuan['NIK'];

            $folder = "uploads/surat_ikm_suket/";
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Nama file: surat_keterangan_ikm_NIK_[timestamp]
            $filename = "surat_keterangan_ikm_" . $nik . "_" . time() . "." . $file_extension;
            $target = $folder . $filename;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $tgl_update = date('Y-m-d H:i:s');

                $pdo->beginTransaction();
                try {
                    // Hapus file lama jika ada
                    $stmt = $pdo->prepare("SELECT file_surat_keterangan FROM pengajuansurat WHERE id_pengajuan = ?");
                    $stmt->execute([$id_pengajuan]);
                    $old_file = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($old_file && !empty($old_file['file_surat_keterangan']) && file_exists($old_file['file_surat_keterangan'])) {
                        unlink($old_file['file_surat_keterangan']);
                    }

                    // Update status ke "Surat Keterangan Terbit"
                    $stmt = $pdo->prepare("UPDATE pengajuansurat SET file_surat_keterangan = ?, status_validasi = 'Surat Keterangan Terbit', tgl_update = ? WHERE id_pengajuan = ?");
                    $stmt->execute([$target, $tgl_update, $id_pengajuan]);

                    // KIRIM NOTIFIKASI KE PEMOHON
                    error_log("\n🔔 ========================================");
                    error_log("🔔 UPLOADING SURAT KETERANGAN IKM");
                    error_log("🔔 ID Pengajuan: " . $id_pengajuan);
                    error_log("🔔 File Path: " . $target);
                    error_log("🔔 ========================================");
                    
                    $notif_sent = notifSuratKeteranganIKMSuket($id_pengajuan);
                    
                    error_log("🔔 Notification Result: " . ($notif_sent ? '✅ SUCCESS' : '❌ FAILED'));
                    error_log("🔔 ========================================\n");
                   
                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Surat Keterangan IKM berhasil diupload' . ($notif_sent ? ' dan notifikasi terkirim' : ' (notifikasi gagal terkirim)'),
                        'notif_sent' => $notif_sent,
                        'file_path' => $target
                    ]);
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if (file_exists($target)) unlink($target);
                    
                    error_log("❌ PDO Error: " . $e->getMessage());
                    
                    throw $e;
                }
            } else {
                error_log("❌ Failed to move uploaded file");
                echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
            }
            exit;
        }
        
        // Handler untuk test notifikasi (optional - untuk debugging)
        if ($action === 'test_notification') {
            error_log("\n🧪 ========================================");
            error_log("🧪 TESTING NOTIFICATION SYSTEM");
            error_log("🧪 ID Pengajuan: " . $id_pengajuan);
            error_log("🧪 ========================================");
            
            $result = notifSuratKeteranganIKMSuket($id_pengajuan);
            
            error_log("🧪 Test Result: " . ($result ? '✅ SUCCESS' : '❌ FAILED'));
            error_log("🧪 ========================================\n");
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Notifikasi test berhasil dikirim' : 'Notifikasi test gagal'
            ]);
            exit;
        }
    } catch (PDOException $e) {
        error_log("❌ Error AJAX: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        exit;
    }
}

try {
    // Query untuk mengambil data lengkap pengajuan surat
    $query = "SELECT ps.*, 
                     u.nama_lengkap as user_nama,
                     u.email as user_email,
                     u.no_wa as user_no_wa
              FROM pengajuansurat ps
              LEFT JOIN user u ON ps.NIK = u.NIK_NIP
              WHERE ps.id_pengajuan = :id_pengajuan";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id_pengajuan', $id_pengajuan, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        echo "Data tidak ditemukan";
        exit();
    }

    // Format tanggal
    $tgl_daftar = date('d/m/Y H:i:s', strtotime($data['tgl_daftar']));

    // Parse JSON arrays
    $foto_produk_array = !empty($data['foto_produk']) ? json_decode($data['foto_produk'], true) : [];
    $foto_proses_array = !empty($data['foto_proses']) ? json_decode($data['foto_proses'], true) : [];
    $nib_files_array = !empty($data['nib_file']) ? json_decode($data['nib_file'], true) : [];

    
    // Log data untuk debugging
    error_log("\n📋 DATA PENGAJUAN LOADED:");
    error_log("   - ID: " . $id_pengajuan);
    error_log("   - NIK: " . $data['NIK']);
    error_log("   - Nama: " . ($data['user_nama'] ?? $data['nama_pemilik']));
    error_log("   - Email: " . ($data['user_email'] ?? $data['email']));
    error_log("   - No WA: " . ($data['user_no_wa'] ?? $data['no_telp_pemilik']));
    error_log("   - Status: " . $data['status_validasi']);
    
} catch (PDOException $e) {
    error_log("❌ Query Error: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}

function getBadgeClass($status)
{
    $badges = [
        'Menunggu Surat Terbit' => 'warning',
        'Surat Keterangan Terbit' => 'success'
    ];
    return $badges[$status] ?? 'secondary';
}

function getDisplayTipe($tipe)
{
    $displayText = [
        'mandiri' => 'MANDIRI',
        'perpanjangan' => 'PERPANJANGAN'
    ];
    return $displayText[$tipe] ?? strtoupper($tipe);
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Detail Pengajuan Surat Keterangan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/detail-pendaftar.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <style>
        .surat-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .surat-title {
            font-size: 1rem;
            font-weight: 600;
            color: #161616;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .file-box {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .file-info {
            flex: 1;
        }

        .file-info small {
            display: block;
            margin-top: 0.25rem;
            color: #6c757d;
        }

        .btn-group-compact {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Debug panel untuk development */
        .debug-panel {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #fff;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 15px;
            max-width: 300px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 9999;
            display: none; /* Hidden by default, enable for debugging */
        }
        
        .debug-panel h6 {
            margin-top: 0;
            color: #007bff;
        }
        
        .debug-panel button {
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <?php include 'navbar-admin.php' ?>

    <main class="container-xxl main-container">
        <div class="mb-3">
            <h1 class="section-heading mb-1">Detail Pengajuan Surat Keterangan</h1>
            <p class="lead-note mb-0">Kelola data pengajuan surat keterangan IKM.</p>
        </div>

        <!-- Debug Panel (Optional - uncomment untuk debugging) -->
        <!-- <div class="debug-panel" id="debugPanel">
            <h6><i class="bi bi-bug-fill me-2"></i>Debug Tools</h6>
            <div class="small">
                <strong>ID Pengajuan:</strong> <?php echo $id_pengajuan; ?><br>
                <strong>NIK:</strong> <?php echo $data['NIK']; ?><br>
                <strong>Email:</strong> <?php echo $data['user_email'] ?? $data['email']; ?><br>
                <strong>No WA:</strong> <?php echo $data['user_no_wa'] ?? $data['no_telp_pemilik'] ?? 'NULL'; ?>
            </div>
            <button class="btn btn-sm btn-primary w-100" id="btnTestNotif">
                <i class="bi bi-bell-fill me-1"></i>Test Notifikasi
            </button>
        </div> -->

        <!-- Header Card -->
        <div class="card mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <div>
                        <div class="small text-muted-600"><?php echo $tgl_daftar; ?></div>
                        <h2 class="h5 fw-bold mb-0"><?php echo strtoupper(htmlspecialchars($data['nama_pemilik'])); ?></h2>
                        <span class="badge bg-info text-white fw-semibold mt-2" style="font-size: 0.7rem;">
                            <i class="bi bi-tag-fill me-1"></i><?php echo getDisplayTipe($data['tipe_pengajuan']); ?>
                        </span>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <span class="badge text-bg-<?php echo getBadgeClass($data['status_validasi']); ?> fw-sbold rounded-pill fs-6 py-2 text-white">
                            <?php echo $data['status_validasi']; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Dokumen Penting -->
        <div class="card mb-4">
            <div class="card-body p-3 p-md-4">
                <h5 class="subsection-title mb-1">Dokumen Penting</h5>
                <div class="divider mb-3"></div>

                <!-- Download Surat Permohonan -->
                <?php if (!empty($data['suratpermohonan_file']) && file_exists($data['suratpermohonan_file'])): ?>
                    <div class="surat-ttd-section">
                        <h5>
                            <i class="bi bi-file-earmark-check me-2"></i>
                            Surat Permohonan dengan Tanda Tangan Digital
                        </h5>
                        <p class="text-muted mb-3">
                            Surat permohonan dari pemohon yang sudah ditandatangani secara digital.
                        </p>
                        <div class="file-info bg-white border-success">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 2.5rem;"></i>
                                    <div>
                                        <span class="fw-bold d-block">Surat Permohonan</span>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>
                                            Diupload: <?php echo date('d/m/Y H:i', strtotime($data['tgl_daftar'])); ?> WIB
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-file-earmark me-1"></i>
                                            <?php echo basename($data['suratpermohonan_file']); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-success btn-view"
                                        data-src="<?php echo htmlspecialchars($data['suratpermohonan_file']); ?>"
                                        data-title="Surat Permohonan">
                                        <i class="bi bi-eye me-1"></i>Preview
                                    </button>
                                    <a href="<?php echo htmlspecialchars($data['suratpermohonan_file']); ?>"
                                        class="btn btn-success btn-sm"
                                        download>
                                        <i class="bi bi-download me-2"></i>Download
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="status-badge status-tersedia">
                                <i class="bi bi-check-circle-fill me-1"></i>File Tersedia
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Belum Ada Surat Permohonan</strong>
                        <p class="mb-0 mt-2">File surat permohonan belum diupload oleh pemohon.</p>
                    </div>
                <?php endif; ?>

                <!-- Generate & Download Surat Keterangan IKM -->
                <div class="document-section">
                    <div class="document-title">
                        <i class="bi bi-file-earmark-word me-2"></i>
                        Generate Surat Keterangan IKM
                    </div>
                    <p class="text-muted small mb-3">
                        Surat keterangan IKM akan di-generate otomatis berdasarkan data yang tersimpan di database.
                        Klik tombol di bawah untuk mendownload dalam format DOCX yang siap diedit.
                    </p>
                    <div class="file-info bg-white border-0 p-0">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-file-earmark-word text-primary" style="font-size: 2.5rem;"></i>
                                <div>
                                    <span class="fw-bold d-block">Surat_Keterangan_IKM_<?php echo htmlspecialchars($data['NIK']); ?>.docx</span>
                                    <small class="text-muted">
                                        <i class="bi bi-file-earmark me-1"></i>
                                        Format: Microsoft Word (.docx)
                                    </small>
                                </div>
                            </div>
                            <a href="process/generate_surat_ikm.php?id_pengajuan=<?php echo $id_pengajuan; ?>"
                                class="btn btn-primary btn-sm"
                                download>
                                <i class="bi bi-download me-2"></i>Download
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Upload Surat Keterangan IKM Terbit -->
                <div class="document-section">
                    <div class="document-title">
                        <i class="bi bi-file-earmark-arrow-up me-2"></i>
                        Upload Surat Keterangan IKM Terbit
                    </div>
                    <p class="text-muted small mb-3">
                        Setelah mengedit surat keterangan IKM, upload kembali file yang sudah ditandatangani.
                        File ini akan dikirimkan kepada pemohon melalui notifikasi email dan WhatsApp.
                    </p>

                    <?php if (!empty($data['file_surat_keterangan']) && file_exists($data['file_surat_keterangan'])): ?>
                        <div class="file-info bg-white border-success mb-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 2rem;"></i>
                                    <div>
                                        <span class="fw-bold d-block">Surat Keterangan IKM Terbit</span>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>
                                            Diupload: <?php echo date('d/m/Y H:i', strtotime($data['tgl_update'] ?? $data['tgl_daftar'])); ?> WIB
                                        </small>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-success btn-view"
                                        data-src="<?php echo htmlspecialchars($data['file_surat_keterangan']); ?>"
                                        data-title="Surat Keterangan IKM Terbit">
                                        <i class="bi bi-eye me-1"></i>Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                        <span class="status-badge status-tersedia">
                            <i class="bi bi-check-circle-fill me-1"></i>Surat Sudah Diupload
                        </span>
                    <?php else: ?>
                        <form id="formUploadSuratIKM" enctype="multipart/form-data">
                            <div class="upload-box">
                                <i class="bi bi-cloud-arrow-up text-success mb-3" style="font-size: 3rem;"></i>
                                <h5 class="mb-2">Pilih File Surat Keterangan IKM</h5>
                                <p class="text-muted small mb-3">Format: PDF atau DOCX (Max 10MB)</p>
                                <input type="file"
                                    class="form-control mt-3"
                                    id="fileSuratIKM"
                                    name="fileSuratIKM"
                                    accept=".pdf,.docx,.doc"
                                    required>
                                <button type="submit" class="btn btn-success mt-3" id="btnUploadSuratIKM">
                                    <i class="bi bi-upload me-2"></i>Upload Surat Keterangan IKM
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Data Pemilik & Usaha -->
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-body p-3 p-md-4">
                        <h3 class="subsection-title mb-1">Data Pemilik</h3>
                        <div class="divider"></div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small">Nama Pemilik</label>
                                <input class="form-control" value="<?php echo htmlspecialchars($data['nama_pemilik']); ?>" readonly />
                            </div>
                            <div class="col-12">
                                <label class="form-label small">NIK</label>
                                <input class="form-control" value="<?php echo htmlspecialchars($data['NIK']); ?>" readonly />
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Alamat Pemilik</label>
                                <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($data['alamat_pemilik']); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Nomor Telepon Pemilik</label>
                                <div class="input-group">
                                    <input class="form-control" value="<?php echo htmlspecialchars($data['no_telp_pemilik']); ?>" readonly />
                                    <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $data['no_telp_pemilik']); ?>" target="_blank" class="btn btn-success btn-sm d-flex align-items-center justify-content-center">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Email</label>
                                <input class="form-control" value="<?php echo htmlspecialchars($data['email']); ?>" readonly />
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Jenis Usaha</label>
                                <input class="form-control" value="<?php echo htmlspecialchars($data['jenis_usaha']); ?>" readonly />
                            </div>
                        </div>

                        <hr class="my-4" />

                        <h3 class="subsection-title mb-1">Data Usaha</h3>
                        <div class="divider"></div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small">Nama Usaha</label>
                                <input class="form-control" value="<?php echo htmlspecialchars($data['nama_usaha']); ?>" readonly />
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Alamat Usaha</label>
                                <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($data['alamat_usaha']); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Nomor Telepon Usaha</label>
                                <div class="input-group">
                                    <input class="form-control" value="<?php echo htmlspecialchars($data['no_telp_perusahaan']); ?>" readonly />
                                    <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $data['no_telp_perusahaan']); ?>" target="_blank" class="btn btn-success btn-sm d-flex align-items-center justify-content-center">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Jumlah Tenaga Kerja</label>
                                <input class="form-control" value="<?php echo htmlspecialchars($data['jml_tenaga_kerja']); ?>" readonly />
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Produk</label>
                                <input class="form-control" value="<?php echo htmlspecialchars($data['produk']); ?>" readonly />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Merek & Lampiran -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body p-3 p-md-4">
                        <h3 class="subsection-title mb-1">Informasi Merek</h3>
                        <div class="divider"></div>

                        <div class="mb-3">
                            <label class="form-label small">Kelas Merek</label>
                            <input class="form-control" value="Kelas <?php echo htmlspecialchars($data['kelas_merek']); ?>" readonly />
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Nama Merek</label>
                            <input class="form-control" value="<?php echo htmlspecialchars($data['merek']); ?>" readonly />
                        </div>

                        <?php if (!empty($data['logo_merek']) && file_exists($data['logo_merek'])): ?>
                            <div class="mb-3">
                                <label class="form-label small">Logo Merek</label>
                                <div class="border rounded-3 p-3 text-center">
                                    <img alt="Logo Merek" class="img-fluid" style="max-height:200px" src="<?php echo htmlspecialchars($data['logo_merek']); ?>" />
                                    <div class="text-center mt-3">
                                        <button class="btn btn-dark btn-sm btn-view"
                                            data-src="<?php echo htmlspecialchars($data['logo_merek']); ?>"
                                            data-title="Logo Merek">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <hr class="my-4" />

                        <h3 class="subsection-title mb-1">Lampiran File</h3>
                        <div class="divider"></div>

                        <!-- NIB File -->
                        <?php if (!empty($nib_files_array)): ?>
                            <div class="mb-3">
                                <div class="small fw-semibold mb-2">Nomor Induk Berusaha (NIB)</div>
                                <div class="row g-3">
                                    <?php 
                                    $nib_count = count($nib_files_array);
                                    foreach ($nib_files_array as $index => $file): 
                                        if (!file_exists($file)) continue;
                                        $colClass = $nib_count > 1 ? 'col-md-6' : 'col-12';
                                    ?>
                                        <div class="<?php echo $colClass; ?>">
                                            <?php
                                            $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                            $is_pdf = ($file_ext === 'pdf');
                                            ?>

                                            <?php if ($is_pdf): ?>
                                                <div class="pdf-card" style="cursor: pointer;"
                                                    onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($file); ?>&quot;]').click()">
                                                    <i class="bi bi-file-pdf-fill pdf-icon"></i>
                                                    <div class="pdf-label">
                                                        <?php echo $nib_count > 1 ? 'NIB Halaman ' . ($index + 1) : 'NIB Document'; ?> (PDF)
                                                    </div>
                                                    <small class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">
                                                        Klik untuk preview
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <img class="attach-img"
                                                    alt="Lampiran NIB"
                                                    src="<?php echo htmlspecialchars($file); ?>"
                                                    style="cursor: pointer;"
                                                    onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($file); ?>&quot;]').click()" />
                                            <?php endif; ?>

                                            <div class="text-end mt-2 mb-3">
                                                <button class="btn btn-dark btn-sm btn-view"
                                                    data-src="<?php echo htmlspecialchars($file); ?>"
                                                    data-title="<?php echo $nib_count > 1 ? 'NIB Halaman ' . ($index + 1) : 'Nomor Induk Berusaha (NIB)'; ?>">
                                                    <i class="bi bi-eye me-1"></i>Preview
                                                </button>
                                                <a href="<?php echo htmlspecialchars($file); ?>"
                                                    class="btn btn-outline-dark btn-sm"
                                                    download>
                                                    <i class="bi bi-download me-1"></i>Download
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Akta File -->
                        <?php if (!empty($data['akta_file']) && file_exists($data['akta_file'])): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">File Akta</label>
                                <div class="row g-3">
                                   <div class="col-12">
                                       <?php
                                       $file_ext = strtolower(pathinfo($data['akta_file'], PATHINFO_EXTENSION));
                                       $is_pdf = ($file_ext === 'pdf');
                                       ?>

                                       <?php if ($is_pdf): ?>
                                           <div class="pdf-card" style="cursor: pointer;"
                                               onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($data['akta_file']); ?>&quot;]').click()">
                                               <i class="bi bi-file-pdf-fill pdf-icon"></i>
                                               <div class="pdf-label">
                                                   File Akta (PDF)
                                               </div>
                                               <small class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">
                                                   Klik untuk preview
                                               </small>
                                           </div>
                                       <?php else: ?>
                                           <img class="attach-img"
                                               alt="File Akta"
                                               src="<?php echo htmlspecialchars($data['akta_file']); ?>"
                                               style="cursor: pointer;"
                                               onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($data['akta_file']); ?>&quot;]').click()" />
                                       <?php endif; ?>

                                       <div class="text-end mt-2 mb-3">
                                           <button class="btn btn-dark btn-sm btn-view"
                                               data-src="<?php echo htmlspecialchars($data['akta_file']); ?>"
                                               data-title="File Akta">
                                               <i class="bi bi-eye me-1"></i>Preview
                                           </button>
                                           <a href="<?php echo htmlspecialchars($data['akta_file']); ?>"
                                               class="btn btn-outline-dark btn-sm"
                                               download>
                                               <i class="bi bi-download me-1"></i>Download
                                           </a>
                                       </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Foto Produk -->
                        <?php if (!empty($foto_produk_array)): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Foto Produk</label>
                                <div class="row g-2">
                                    <?php foreach ($foto_produk_array as $file): ?>
                                        <?php if (file_exists($file)): ?>
                                            <div class="col-6">
                                                <img class="attach-img rounded" src="<?php echo htmlspecialchars($file); ?>" alt="Foto Produk" />
                                                <div class="text-end mt-2 mb-2">
                                                    <button class="btn btn-dark btn-sm btn-view"
                                                        data-src="<?php echo htmlspecialchars($file); ?>"
                                                        data-title="Foto Produk">
                                                        <i class="bi bi-eye me-1"></i>View
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Foto Proses -->
                        <?php if (!empty($foto_proses_array)): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Foto Proses Produksi</label>
                                <div class="row g-2">
                                    <?php foreach ($foto_proses_array as $file): ?>
                                        <?php if (file_exists($file)): ?>
                                            <div class="col-6">
                                                <img class="attach-img rounded" src="<?php echo htmlspecialchars($file); ?>" alt="Foto Proses" />
                                                <div class="text-end mt-2 mb-2">
                                                    <button class="btn btn-dark btn-sm btn-view"
                                                        data-src="<?php echo htmlspecialchars($file); ?>"
                                                        data-title="Foto Proses Produksi">
                                                        <i class="bi bi-eye me-1"></i>View
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal View Foto/PDF -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2 bg-light">
                    <h6 class="modal-title mb-0" id="modalTitle"></h6>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <!-- Container untuk gambar -->
                    <div id="imageContainer" style="display: none;">
                        <img id="modalImage" src="" alt="Preview" class="img-fluid rounded" style="max-height: 50vh; width: 100%; object-fit: contain;" />
                    </div>

                    <!-- Container untuk PDF -->
                    <div id="pdfContainer" style="display: none;">
                        <iframe id="modalPdf" src="" style="width: 100%; height: 50vh; border: 1px solid #dee2e6; border-radius: 0.375rem;"></iframe>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <a id="downloadBtn" href="#" download class="btn btn-success btn-sm">
                        <i class="bi bi-download me-1"></i>Download
                    </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ID_PENGAJUAN = <?php echo $id_pengajuan; ?>;

        // ============================================
        // BOOTSTRAP ALERT & CONFIRM MODALS
        // ============================================
        function showAlert(message, type = 'warning') {
            const icon = type === 'danger' ? '❌' : type === 'success' ? '✅' : '⚠️';

            const alertModal = `
    <div class="modal fade" id="alertModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
          <div class="modal-body text-center p-4">
            <div class="fs-1 mb-3">${icon}</div>
            <p class="mb-0">${message}</p>
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

        function showConfirm(message, onConfirm, onCancel = null) {
            const confirmModal = `
    <div class="modal fade" id="confirmModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body text-center p-4">
            <div class="fs-1 mb-3">⚠️</div>
            <p class="mb-0">${message.replace(/\n/g, '<br>')}</p>
          </div>
          <div class="modal-footer border-0 justify-content-center gap-2">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" id="btnModalCancel">Batal</button>
            <button type="button" class="btn btn-primary px-4" id="btnModalConfirm">Ya, Lanjutkan</button>
          </div>
        </div>
      </div>
    </div>
  `;

            const existingModal = document.getElementById('confirmModal');
            if (existingModal) existingModal.remove();

            document.body.insertAdjacentHTML('beforeend', confirmModal);
            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            modal.show();

            document.getElementById('btnModalConfirm').addEventListener('click', function() {
                modal.hide();
                if (onConfirm) onConfirm();
            });

            document.getElementById('btnModalCancel').addEventListener('click', function() {
                if (onCancel) onCancel();
            });

            document.getElementById('confirmModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // ============================================
        // TEST NOTIFICATION (Optional - untuk debugging)
        // ============================================
        const btnTestNotif = document.getElementById('btnTestNotif');
        if (btnTestNotif) {
            btnTestNotif.addEventListener('click', function() {
                console.log('🧪 Testing notification for ID:', ID_PENGAJUAN);
                
                const formData = new FormData();
                formData.append('ajax_action', 'test_notification');
                formData.append('id_pengajuan', ID_PENGAJUAN);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('🧪 Test result:', data);
                    if (data.success) {
                        showAlert('Test notifikasi berhasil! Cek email dan WhatsApp pemohon.', 'success');
                    } else {
                        showAlert('Test notifikasi gagal: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('🧪 Test error:', error);
                    showAlert('Terjadi kesalahan saat test notifikasi.', 'danger');
                });
            });
        }

        // ============================================
        // HANDLER UPLOAD SURAT KETERANGAN IKM
        // ============================================
        const formUploadSuratIKM = document.getElementById('formUploadSuratIKM');
        if (formUploadSuratIKM) {
            const fileInput = document.getElementById('fileSuratIKM');
            const btnUpload = document.getElementById('btnUploadSuratIKM');

            // Event ketika file dipilih
            fileInput.addEventListener('change', function() {
                const file = this.files[0];

                if (!file) return;

                // Validasi ukuran
                if (file.size > 10 * 1024 * 1024) {
                    showAlert('Ukuran file maksimal 10MB!');
                    this.value = '';
                    return;
                }

                // Validasi format
                const allowedExt = ['pdf', 'docx', 'doc'];
                const fileExt = file.name.split('.').pop().toLowerCase();

                if (!allowedExt.includes(fileExt)) {
                    showAlert('Format file harus PDF atau DOCX');
                    this.value = '';
                    return;
                }

                // Tampilkan modal preview
                showPreviewModalSuratIKM(file);
            });

            function showPreviewModalSuratIKM(file) {
                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                const modalTitle = document.getElementById('modalTitle');
                const imageContainer = document.getElementById('imageContainer');
                const pdfContainer = document.getElementById('pdfContainer');
                const modalImg = document.getElementById('modalImage');
                const modalPdf = document.getElementById('modalPdf');
                const downloadBtn = document.getElementById('downloadBtn');

                modalTitle.textContent = 'Preview: Surat Keterangan IKM';

                const fileExt = file.name.split('.').pop().toLowerCase();
                const fileURL = URL.createObjectURL(file);

                if (fileExt === 'pdf') {
                    imageContainer.style.display = 'none';
                    pdfContainer.style.display = 'block';
                    modalPdf.src = fileURL + '#toolbar=0';
                } else {
                    pdfContainer.style.display = 'none';
                    imageContainer.style.display = 'block';
                    modalImg.src = fileURL;
                }

                downloadBtn.outerHTML = `
      <button id="btnKonfirmasiUploadSuratIKM" class="btn btn-success btn-sm">
        <i class="bi bi-upload me-2"></i>Konfirmasi & Upload
      </button>
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
        <i class="bi bi-x me-1"></i>Batal
      </button>
    `;

                modal.show();

                document.getElementById('btnKonfirmasiUploadSuratIKM').addEventListener('click', function() {
                    modal.hide();
                    uploadSuratIKM(file);
                    URL.revokeObjectURL(fileURL);
                });

                document.getElementById('imageModal').addEventListener('hidden.bs.modal', function() {
                    URL.revokeObjectURL(fileURL);
                    modalPdf.src = '';
                    modalImg.src = '';

                    const btnConfirm = document.getElementById('btnKonfirmasiUploadSuratIKM');
                    if (btnConfirm && btnConfirm.parentElement) {
                        btnConfirm.parentElement.innerHTML = `
          <a id="downloadBtn" href="#" download class="btn btn-success btn-sm">
            <i class="bi bi-download me-1"></i>Download
          </a>
        `;
                    }
                }, {
                    once: true
                });
            }

            function uploadSuratIKM(file) {
                showConfirm('Apakah Anda yakin ingin mengupload Surat Keterangan IKM ini?\n\nNotifikasi akan dikirim ke pemohon melalui email dan WhatsApp.', function() {
                    const formData = new FormData();
                    formData.append('ajax_action', 'upload_surat_ikm_suket');
                    formData.append('id_pengajuan', ID_PENGAJUAN);
                    formData.append('fileSuratIKM', file);

                    btnUpload.disabled = true;
                    btnUpload.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengupload...';

                    console.log('📤 Uploading file for ID:', ID_PENGAJUAN);
                    console.log('📤 File name:', file.name);
                    console.log('📤 File size:', (file.size / 1024 / 1024).toFixed(2), 'MB');

                    fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            console.log('📥 Response status:', response.status);
                            return response.json();
                        })
                        .then(data => {
                            console.log('📥 Response data:', data);
                            
                            if (data.success) {
                                showAlert(data.message + '\n\nHalaman akan dimuat ulang...', 'success');
                                setTimeout(() => location.reload(), 2000);
                            } else {
                                showAlert('Gagal: ' + data.message, 'danger');
                                fileInput.value = '';
                                btnUpload.disabled = false;
                                btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Surat Keterangan IKM';
                            }
                        })
                        .catch(error => {
                            console.error('❌ Upload error:', error);
                            showAlert('Terjadi kesalahan saat mengupload file.', 'danger');
                            fileInput.value = '';
                            btnUpload.disabled = false;
                            btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Surat Keterangan IKM';
                        });
                }, function() {
                    fileInput.value = '';
                });
            }

            formUploadSuratIKM.addEventListener('submit', function(e) {
                e.preventDefault();
                const file = fileInput.files[0];
                if (file) {
                    uploadSuratIKM(file);
                } else {
                    showAlert('Silakan pilih file terlebih dahulu!');
                }
            });
        }

        // ============================================
        // VIEW IMAGE/PDF MODAL
        // ============================================
        document.querySelectorAll('.btn-view').forEach(btn => {
            btn.addEventListener('click', function() {
                const src = this.getAttribute('data-src');
                const title = this.getAttribute('data-title');

                const modalTitle = document.getElementById('modalTitle');
                const downloadBtn = document.getElementById('downloadBtn');
                const imageContainer = document.getElementById('imageContainer');
                const pdfContainer = document.getElementById('pdfContainer');
                const modalImg = document.getElementById('modalImage');
                const modalPdf = document.getElementById('modalPdf');

                modalTitle.textContent = title;
                downloadBtn.href = src;

                const fileExtension = src.split('.').pop().toLowerCase();

                if (fileExtension === 'pdf') {
                    imageContainer.style.display = 'none';
                    pdfContainer.style.display = 'block';
                    modalPdf.src = src + '#toolbar=0';
                } else {
                    pdfContainer.style.display = 'none';
                    imageContainer.style.display = 'block';
                    modalImg.src = src;
                }

                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
            });
        });

        // Bersihkan saat modal ditutup
        const imageModal = document.getElementById('imageModal');
        imageModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('modalPdf').src = '';
            document.getElementById('modalImage').src = '';
        });

        // Force z-index saat modal terbuka
        imageModal.addEventListener('show.bs.modal', function() {
            this.style.zIndex = '1055';
            setTimeout(() => {
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.style.zIndex = '1050';
            }, 50);
        });
        
        // ============================================
        // CONSOLE LOG INFO
        // ============================================
        console.log('📋 Page loaded for Pengajuan ID:', ID_PENGAJUAN);
        console.log('📋 User NIK:', '<?php echo $data['NIK']; ?>');
        console.log('📋 User Email:', '<?php echo $data['user_email'] ?? $data['email']; ?>');
        console.log('📋 User WhatsApp:', '<?php echo $data['user_no_wa'] ?? $data['no_telp_pemilik'] ?? 'NULL'; ?>');
    </script>
</body>

</html>