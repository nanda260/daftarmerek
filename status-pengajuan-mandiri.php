<?php
session_start();
include 'process/config_db.php';
require_once 'process/crypto_helper.php';
date_default_timezone_set('Asia/Jakarta');

// Cek login
if (!isset($_SESSION['NIK_NIP'])) {
    header("Location: login.php");
    exit;
}

$NIK = $_SESSION['NIK_NIP'];
$nama = $_SESSION['nama_lengkap'];

// AMBIL TOKEN DARI URL DAN DECRYPT
$encrypted_token = isset($_GET['ref']) ? $_GET['ref'] : '';
$id_pengajuan = 0;

if (!empty($encrypted_token)) {
    $decrypted_id = decryptId($encrypted_token);
    $id_pengajuan = $decrypted_id !== false ? intval($decrypted_id) : 0;
}

if (!$id_pengajuan) {
    header("Location: lihat-pengajuan-mandiri.php");
    exit;
}

// AMBIL DATA PENGAJUAN BERDASARKAN ID
$pengajuan = null;

try {
    $stmt = $pdo->prepare("
    SELECT p.*, u.nama_lengkap, u.email as user_email
    FROM pengajuansurat p
    LEFT JOIN user u ON p.NIK = u.NIK_NIP
    WHERE p.id_pengajuan = ? AND p.NIK = ?
  ");

    $stmt->execute([$id_pengajuan, $NIK]);
    $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pengajuan) {
        header("Location: lihat-pengajuan-mandiri.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching pengajuan: " . $e->getMessage());
    die("Terjadi kesalahan saat mengambil data: " . $e->getMessage());
}

// Mapping status untuk kode unik
$statusMap = [
    'Menunggu Surat Terbit' => 'menunggusurat',
    'Surat Keterangan Terbit' => 'suratterbit'
];

$statusKey = $statusMap[$pengajuan['status_validasi']] ?? 'menunggusurat';

$dataStatus = [
    'menunggusurat' => [
        'proses' => 'Menunggu Surat Terbit',
        'status' => 'Pengajuan Sedang Diproses',
        'desc'   => 'Pengajuan surat Anda sedang dalam proses. Admin akan segera menerbitkan Surat Keterangan. Mohon menunggu konfirmasi lebih lanjut.',
    ],
    'suratterbit' => [
        'proses' => 'Surat Keterangan Terbit',
        'status' => 'Surat Keterangan Telah Terbit',
        'desc'   => 'Selamat! Surat Keterangan untuk pengajuan Anda telah terbit. Silakan download surat di bawah ini.',
    ],
];

$data = $dataStatus[$statusKey];

// Decode JSON arrays untuk foto produk dan proses
$foto_produk = json_decode($pengajuan['foto_produk'], true) ?? [];
$foto_proses = json_decode($pengajuan['foto_proses'], true) ?? [];

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Status Pengajuan Surat <?php echo ucfirst($pengajuan['tipe_pengajuan']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/status-seleksi.css" />
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <style>
        .proses-menunggusurat {
            background-color: #FFC107 !important;
        }

        .proses-suratterbit {
            background-color: #198754 !important;
        }

        .brand-card {
            text-align: left;
        }

        .brand-name-label,
        .logo-label {
            text-align: left;
        }
    </style>
</head>

<body>
    <?php include 'navbar-login.php' ?>

    <main class="main-content">
        <div class="container">
            <h1 class="page-title">Status Pengajuan Surat <?php echo ucfirst($pengajuan['tipe_pengajuan']); ?></h1>
            <p class="page-description">
                Cek secara berkala untuk mengetahui perkembangan lebih<br />
                lanjut status pengajuan surat Anda.
            </p>

            <div class="info-card">
                <div class="info-header d-flex flex-column flex-md-row justify-content-between align-items-start">
                    <h2 class="info-title">Informasi Pengajuan</h2>
                    <p class="proses proses-<?php echo htmlspecialchars($statusKey); ?> mt-2 mt-md-0">
                        <?php echo htmlspecialchars($data['proses']); ?>
                    </p>
                </div>

                <hr class="border-2 border-secondary w-100 line" />

                <div class="status-box">
                    <div class="d-flex align-items-start">
                        <i class="fa-solid fa-bell status-icon"></i>
                        <div class="flex-grow-1">
                            <div class="status-text"><?php echo htmlspecialchars($data['status']); ?></div>
                            <div class="status-description">
                                <p class="m-0"><?php echo htmlspecialchars($data['desc']); ?></p>

                                <?php if ($statusKey === 'suratterbit' && $pengajuan['file_surat_keterangan']): ?>
                                    <div class="mt-4">
                                        <div class="card border-success">
                                            <div class="card-body">
                                                <h6 class="fw-bold mb-3">
                                                    <i class="fa-solid fa-file-check me-2 text-success"></i>
                                                    Surat Keterangan
                                                </h6>
                                                <div class="alert alert-success mb-3">
                                                    <i class="fa-solid fa-check-circle me-2"></i>
                                                    <strong>File Tersedia</strong>
                                                    <p class="mb-0 mt-2 small">Surat Keterangan telah diterbitkan oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
                                                </div>
                                                <div class="d-grid gap-2">
                                                    <button class="btn btn-outline-success btn-sm btn-view mb-2"
                                                        data-src="<?php echo htmlspecialchars($pengajuan['file_surat_keterangan']); ?>"
                                                        data-title="Surat Keterangan">
                                                        <i class="fa-solid fa-eye me-1"></i>Preview Surat
                                                    </button>
                                                    <a class="btn btn-success" href="<?php echo htmlspecialchars($pengajuan['file_surat_keterangan']); ?>" target="_blank" download>
                                                        <i class="fa-solid fa-download me-2"></i> Download Surat Keterangan
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($statusKey === 'suratterbit' && !$pengajuan['file_surat_keterangan']): ?>
                                    <div class="alert alert-warning mt-3">
                                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                        <strong>Surat Belum Tersedia</strong>
                                        <p class="mb-0 mt-2">Status menunjukkan surat telah terbit, namun file belum tersedia. Silakan hubungi admin.</p>
                                    </div>
                                <?php elseif ($statusKey === 'menunggusurat'): ?>
                                    <div class="alert alert-info mt-3">
                                        <i class="fa-solid fa-clock me-2"></i>
                                        <strong>Informasi:</strong>
                                        <p class="mb-0 mt-2">Pengajuan Anda sedang diproses. Admin akan segera menerbitkan surat keterangan. Anda akan mendapatkan notifikasi ketika surat sudah tersedia.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <strong>Nama Pemohon:</strong><br />
                    <p><?php echo strtoupper(htmlspecialchars($pengajuan['nama_pemilik'])); ?></p>

                    <div class="mb-2 mt-2">
                        <strong>Nama Usaha:</strong><br />
                        <p><?php echo htmlspecialchars($pengajuan['nama_usaha']); ?></p>
                    </div>

                    <div class="mb-2">
                        <strong>Tipe Pengajuan:</strong><br />
                        <p><?php echo ucfirst(htmlspecialchars($pengajuan['tipe_pengajuan'])); ?></p>
                    </div>

                    <div class="mb-2">
                        <strong>Tanggal Pengajuan:</strong><br />
                        <p><?php echo date('d F Y, H:i', strtotime($pengajuan['tgl_daftar'])); ?> WIB</p>
                    </div>

                    <?php if ($pengajuan['tgl_update']): ?>
                        <div class="mb-2">
                            <strong>Terakhir Diupdate:</strong><br />
                            <p><?php echo date('d F Y, H:i', strtotime($pengajuan['tgl_update'])); ?> WIB</p>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <strong>Merek yang Diajukan:</strong><br />
                    </div>
                </div>

                <!-- Kartu Merek -->
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="brand-card border-primary border-3">
                            <h3 class="brand-title"><?php echo ucfirst($pengajuan['tipe_pengajuan']); ?></h3>
                            <span class="badge bg-primary ms-2 mb-3">Merek Diajukan</span>

                            <div class="brand-name-label">Nama Merek</div>
                            <div class="brand-name-display"><?php echo htmlspecialchars($pengajuan['merek']); ?></div>

                            <div class="brand-name-label mt-3">Kelas Merek</div>
                            <div class="brand-name-display"><?php echo htmlspecialchars($pengajuan['kelas_merek']); ?></div>

                            <div class="logo-label mt-3">Logo Merek</div>
                            <div class="logo-container">
                                <?php if ($pengajuan['logo_merek'] && file_exists($pengajuan['logo_merek'])): ?>
                                    <img src="<?php echo htmlspecialchars($pengajuan['logo_merek']); ?>" alt="Logo" style="max-width: 200px; max-height: 200px;">
                                <?php else: ?>
                                    <i class="fas fa-image brand-logo"></i>
                                    <div class="brand-logo-text"><?php echo htmlspecialchars($pengajuan['merek']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informasi Detail Pengajuan -->
                <div class="info-card mt-4">
                    <div class="info-header">
                        <h2 class="info-title">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            Detail Pengajuan
                        </h2>
                    </div>

                    <hr class="border-2 border-secondary w-100 line" />

                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong>Jenis Usaha:</strong><br />
                            <p><?php echo htmlspecialchars($pengajuan['jenis_usaha']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <strong>Produk:</strong><br />
                            <p><?php echo htmlspecialchars($pengajuan['produk']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <strong>Jumlah Tenaga Kerja:</strong><br />
                            <p><?php echo htmlspecialchars($pengajuan['jml_tenaga_kerja']); ?> orang</p>
                        </div>
                        <div class="col-md-6">
                            <strong>Email:</strong><br />
                            <p><?php echo htmlspecialchars($pengajuan['email']); ?></p>
                        </div>
                        <div class="col-12">
                            <strong>Alamat Usaha:</strong><br />
                            <p><?php echo htmlspecialchars($pengajuan['alamat_usaha']); ?></p>
                        </div>
                        <div class="col-12">
                            <strong>Alamat Pemilik:</strong><br />
                            <p><?php echo htmlspecialchars($pengajuan['alamat_pemilik']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <strong>No. Telepon Usaha:</strong><br />
                            <p><?php echo htmlspecialchars($pengajuan['no_telp_perusahaan'] ?: '-'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <strong>No. Telepon Pemilik:</strong><br />
                            <p><?php echo htmlspecialchars($pengajuan['no_telp_pemilik']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Dokumen yang Tersedia -->
                <div class="info-card mt-4">
                    <div class="info-header">
                        <h2 class="info-title">
                            <i class="fa-solid fa-folder-open me-2"></i>
                            Dokumen Pengajuan
                        </h2>
                    </div>

                    <hr class="border-2 border-secondary w-100 line" />

                    <div class="row g-3">
                        <!-- NIB -->
                        <?php if (!empty($pengajuan['nib_file'])):
                            // Cek apakah nib_file adalah JSON array atau path langsung
                            $nib_files = @json_decode($pengajuan['nib_file'], true);
                            if (!$nib_files) {
                                // Jika bukan JSON, anggap sebagai path langsung
                                $nib_files = [$pengajuan['nib_file']];
                            }
                            if ($nib_files && is_array($nib_files) && !empty($nib_files[0]) && file_exists($nib_files[0])):
                        ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card border-secondary h-100">
                                        <div class="card-body">
                                            <div class="text-center mb-3">
                                                <i class="fa-solid fa-file-alt text-secondary" style="font-size: 3rem;"></i>
                                            </div>
                                            <h6 class="fw-bold text-center mb-3">NIB (Nomor Induk Berusaha)</h6>
                                            <div class="alert alert-success mb-3">
                                                <small><i class="fa-solid fa-check-circle me-1"></i><strong>File Tersedia</strong></small>
                                            </div>
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-sm btn-outline-secondary btn-view"
                                                    data-src="<?php echo htmlspecialchars($nib_files[0]); ?>"
                                                    data-title="NIB">
                                                    <i class="fa-solid fa-eye me-1"></i>Preview
                                                </button>
                                                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($nib_files[0]); ?>" download>
                                                    <i class="fa-solid fa-download me-1"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php endif;
                        endif; ?>

                        <!-- Surat Permohonan -->
                        <?php if ($pengajuan['suratpermohonan_file'] && file_exists($pengajuan['suratpermohonan_file'])): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card border-primary h-100">
                                    <div class="card-body">
                                        <div class="text-center mb-3">
                                            <i class="fa-solid fa-file-signature text-primary" style="font-size: 3rem;"></i>
                                        </div>
                                        <h6 class="fw-bold text-center mb-3">Surat Permohonan</h6>
                                        <div class="alert alert-success mb-3">
                                            <small><i class="fa-solid fa-check-circle me-1"></i><strong>File Tersedia</strong></small>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-sm btn-outline-primary btn-view"
                                                data-src="<?php echo htmlspecialchars($pengajuan['suratpermohonan_file']); ?>"
                                                data-title="Surat Permohonan">
                                                <i class="fa-solid fa-eye me-1"></i>Preview
                                            </button>
                                            <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($pengajuan['suratpermohonan_file']); ?>" download>
                                                <i class="fa-solid fa-download me-1"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Akta (jika ada) -->
                        <?php if ($pengajuan['akta_file'] && file_exists($pengajuan['akta_file'])): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card border-info h-100">
                                    <div class="card-body">
                                        <div class="text-center mb-3">
                                            <i class="fa-solid fa-file-contract text-info" style="font-size: 3rem;"></i>
                                        </div>
                                        <h6 class="fw-bold text-center mb-3">Akta Perusahaan</h6>
                                        <div class="alert alert-success mb-3">
                                            <small><i class="fa-solid fa-check-circle me-1"></i><strong>File Tersedia</strong></small>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-sm btn-outline-info btn-view"
                                                data-src="<?php echo htmlspecialchars($pengajuan['akta_file']); ?>"
                                                data-title="Akta">
                                                <i class="fa-solid fa-eye me-1"></i>Preview
                                            </button>
                                            <a class="btn btn-info btn-sm" href="<?php echo htmlspecialchars($pengajuan['akta_file']); ?>" download>
                                                <i class="fa-solid fa-download me-1"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>


                </div>

                <!-- Tombol Back -->
                <div class="row mt-4">
                    <div class="col-12 text-end">
                        <a href="lihat-pengajuan-fasilitasi.php" class="btn btn-dark">
                            <i class="fa-solid fa-arrow-left me-2"></i>Kembali
                        </a>
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
                        <img id="modalImage" src="" alt="Preview" class="img-fluid rounded" style="max-height: 60vh; width: 100%; object-fit: contain;" />
                    </div>

                    <!-- Container untuk PDF -->
                    <div id="pdfContainer" style="display: none;">
                        <iframe id="modalPdf" src="" style="width: 100%; height: 60vh; border: 1px solid #dee2e6; border-radius: 0.375rem;"></iframe>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <a id="downloadBtn" href="#" download class="btn btn-success btn-sm">
                        <i class="fa-solid fa-download me-1"></i>Download
                    </a>
                </div>
            </div>
        </div>
    </div>



    <footer class="footer">
        <div class="container text-center">
            <p class="mb-1">Copyright © 2025. All Rights Reserved.</p>
            <p class="mb-0">Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View image/pdf modal
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

                // Cek ekstensi file
                const fileExtension = src.split('.').pop().toLowerCase();

                if (fileExtension === 'pdf') {
                    // Tampilkan PDF
                    imageContainer.style.display = 'none';
                    pdfContainer.style.display = 'block';
                    modalPdf.src = src + '#toolbar=0';
                } else {
                    // Tampilkan gambar
                    pdfContainer.style.display = 'none';
                    imageContainer.style.display = 'block';
                    modalImg.src = src;
                }

                // Buka modal
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
    </script>

</body>

</html>