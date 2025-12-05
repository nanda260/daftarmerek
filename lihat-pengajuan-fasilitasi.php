<?php
session_start();
if (!isset($_SESSION['NIK_NIP']) || !isset($_SESSION['nama_lengkap'])) {
    header("Location: login.php");
    exit();
}

require_once 'process/config_db.php';
require_once 'process/crypto_helper.php';

$nik = $_SESSION['NIK_NIP'];
$nama = $_SESSION['nama_lengkap'];

// Inisialisasi variabel untuk menghindari error
$pendaftaran_list = [];
$pengajuan_mandiri_list = [];

// AMBIL SEMUA PENDAFTARAN (URUT DARI YANG PALING BARU)
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id_pendaftaran,
            p.tgl_daftar,
            p.status_validasi,
            p.merek_difasilitasi,
            u.nama_usaha,
            m.nama_merek1,
            m.nama_merek2,
            m.nama_merek3
        FROM pendaftaran p
        LEFT JOIN datausaha u ON p.id_usaha = u.id_usaha
        LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
        WHERE p.NIK = :nik
        ORDER BY p.tgl_daftar DESC
    ");
    $stmt->execute(['nik' => $nik]);
    $pendaftaran_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pendaftaran: " . $e->getMessage());
    $pendaftaran_list = [];
}

// AMBIL SEMUA PENGAJUAN SURAT KETERANGAN MANDIRI
try {
    $stmt = $pdo->prepare("
        SELECT 
            id_pengajuan,
            tipe_pengajuan,
            nama_usaha,
            merek,
            kelas_merek,
            status_validasi,
            tgl_daftar,
            file_surat_keterangan
        FROM pengajuansurat
        WHERE NIK = :nik
        ORDER BY tgl_daftar DESC
    ");
    $stmt->execute(['nik' => $nik]);
    $pengajuan_mandiri_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pengajuan mandiri: " . $e->getMessage());
    $pengajuan_mandiri_list = [];
}

// Fungsi untuk menentukan nomor urutan pendaftaran
function getNomorPendaftaran($index)
{
    $nomor = $index + 1;
    switch ($nomor) {
        case 1:
            return "Pendaftaran Gratis";
        case 2:
            return "Pendaftaran Kedua";
        case 3:
            return "Pendaftaran Ketiga";
        default:
            return "Pendaftaran Ke-" . $nomor;
    }
}

// Fungsi untuk menentukan warna status
function getStatusBadgeClass($status)
{
    $badges = [
        'Pengecekan Berkas' => 'scan',
        'Berkas Baru' => 'scan',
        'Tidak Bisa Difasilitasi' => 'dangerish',
        'Konfirmasi Lanjut' => 'violet',
        'Surat Keterangan Difasilitasi' => 'infoish',
        'Menunggu Bukti Pendaftaran' => 'emerald',
        'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian' => 'yellow',
        'Hasil Verifikasi Kementerian' => 'mint',
        'Menunggu Surat Terbit' => 'warn',
        'Surat Keterangan Terbit' => 'success',
    ];

    return 'text-bg-' . ($badges[$status] ?? 'secondary');
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Pengajuan Fasilitasi - Pendaftaran Merek</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/lihatpengajuan.css">
</head>

<body>
    <?php include 'navbar-login.php' ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="page-title">Lihat Pengajuan Fasilitasi</div>
            <div class="page-description">Lihat daftar semua pengajuan pendaftaran merek dan perpanjangan Anda</div>
        </div>
    </section>

    <main class="container py-5">
        
        <!-- SECTION PENDAFTARAN -->
        <section class="mb-5">
            <div class="section-title">
                <i class="fa-solid fa-file-invoice"></i>
                Daftar Pendaftaran Merek
            </div>

            <?php if (count($pendaftaran_list) > 0): ?>
                <?php foreach ($pendaftaran_list as $index => $pendaftaran): ?>
                    <div class="pengajuan-card" onclick="window.location.href='status-seleksi-pendaftaran.php?ref=<?php echo urlencode(encryptId($pendaftaran['id_pendaftaran'])); ?>'">
                        <div class="pengajuan-card-header">
                            <div>
                                <div class="nomor-pendaftaran">
                                    <?php echo getNomorPendaftaran($index); ?>
                                </div>
                                <div class="pengajuan-card-title">
                                    <?php echo htmlspecialchars($pendaftaran['nama_usaha'] ?? 'Usaha Tanpa Nama'); ?>
                                </div>
                                <div class="pengajuan-card-subtitle">
                                    <i class="fa-solid fa-calendar me-2"></i>
                                    Terdaftar: <?php echo date('d F Y, H:i', strtotime($pendaftaran['tgl_daftar'])); ?> WIB
                                </div>
                            </div>
                            <div class="status-badge <?php echo getStatusBadgeClass($pendaftaran['status_validasi']); ?>">
                                <?php echo htmlspecialchars($pendaftaran['status_validasi']); ?>
                            </div>
                        </div>

                        <div class="pengajuan-card-body">
                            <div class="pengajuan-info">
                                <div class="info-item">
                                    <div class="info-label">Merek Alternatif 1</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pendaftaran['nama_merek1'] ?? '-'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Merek Alternatif 2</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pendaftaran['nama_merek2'] ?? '-'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Merek Alternatif 3</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pendaftaran['nama_merek3'] ?? '-'); ?></div>
                                </div>
                                <?php if (!empty($pendaftaran['merek_difasilitasi'])): ?>
                                    <div class="info-item">
                                        <div class="info-label">Merek Difasilitasi</div>
                                        <div class="info-value">
                                            <span class="badge bg-success">
                                                Alternatif <?php echo htmlspecialchars($pendaftaran['merek_difasilitasi']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-inbox"></i>
                    <div class="empty-state-title">Belum Ada Pendaftaran</div>
                    <div class="empty-state-desc">
                        Anda belum melakukan pendaftaran merek. Silakan mulai dengan mengajukan pendaftaran baru.
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- SECTION PENGAJUAN SURAT KETERANGAN MANDIRI -->
        <section class="mb-5">
            <div class="section-title">
                <i class="fa-solid fa-file-signature"></i>
                Daftar Pengajuan Surat Keterangan Mandiri
            </div>

            <?php if (count($pengajuan_mandiri_list) > 0): ?>
                <?php foreach ($pengajuan_mandiri_list as $index => $pengajuan): ?>
                    <div class="pengajuan-card" onclick="window.location.href='status-pengajuan-mandiri.php?ref=<?php echo urlencode(encryptId($pengajuan['id_pengajuan'])); ?>'">
                        <div class="pengajuan-card-header">
                            <div>
                                <div class="nomor-pendaftaran">
                                    <i class="fa-solid fa-certificate me-1"></i>
                                    Pengajuan Mandiri #<?php echo $pengajuan['id_pengajuan']; ?>
                                </div>
                                <div class="pengajuan-card-title">
                                    <?php echo htmlspecialchars($pengajuan['nama_usaha'] ?? 'Usaha Tanpa Nama'); ?>
                                </div>
                                <div class="pengajuan-card-subtitle">
                                    <i class="fa-solid fa-calendar me-2"></i>
                                    Diajukan: <?php echo date('d F Y, H:i', strtotime($pengajuan['tgl_daftar'])); ?> WIB
                                </div>
                            </div>
                            <div class="status-badge <?php echo getStatusBadgeClass($pengajuan['status_validasi']); ?>">
                                <?php echo htmlspecialchars($pengajuan['status_validasi']); ?>
                            </div>
                        </div>

                        <div class="pengajuan-card-body">
                            <div class="pengajuan-info">
                                <div class="info-item">
                                    <div class="info-label">Merek</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pengajuan['merek'] ?? '-'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Kelas Merek</div>
                                    <div class="info-value">Kelas <?php echo htmlspecialchars($pengajuan['kelas_merek'] ?? '-'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Tipe Pengajuan</div>
                                    <div class="info-value">
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(htmlspecialchars($pengajuan['tipe_pengajuan'] ?? 'Tidak diketahui')); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!empty($pengajuan['file_surat_keterangan'])): ?>
                                    <div class="info-item">
                                        <div class="info-label">Surat Keterangan</div>
                                        <div class="info-value">
                                            <span class="badge bg-success">
                                                <i class="fa-solid fa-check me-1"></i>Tersedia
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-inbox"></i>
                    <div class="empty-state-title">Belum Ada Pengajuan Mandiri</div>
                    <div class="empty-state-desc">
                        Anda belum memiliki pengajuan surat keterangan mandiri.
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Tombol Back -->
        <div class="row mt-4">
            <div class="col-12 text-end">
                <a href="home.php" class="btn btn-dark">
                    <i class="fa-solid fa-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
        
    </main>

    <footer class="footer">
        <div class="container text-center">
            <p class="mb-1">Copyright © 2025. All Rights Reserved.</p>
            <p class="mb-0">Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>