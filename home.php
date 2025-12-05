<?php
session_start();
if (!isset($_SESSION['NIK_NIP']) || !isset($_SESSION['nama_lengkap'])) {
    header("Location: login.php");
    exit();
}

$nama = $_SESSION['nama_lengkap'];
$nik = $_SESSION['NIK_NIP'];

require_once 'process/config_db.php';

$id_pendaftaran_aktif = null;
$sudahDaftar = false;

try {
    $stmt = $pdo->prepare("SELECT id_pendaftaran, status_validasi FROM pendaftaran WHERE NIK = ? ORDER BY tgl_daftar DESC LIMIT 1");
    $stmt->execute([$nik]);
    $pendaftaran_aktif = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pendaftaran_aktif) {
        $sudahDaftar = true;
        $id_pendaftaran_aktif = $pendaftaran_aktif['id_pendaftaran'];
    }
} catch (PDOException $e) {
    error_log("Error checking pendaftaran: " . $e->getMessage());
}

$total_kuota = 100;
$tahun_sekarang = date('Y');

try {
    $query_difasilitasi = "SELECT COUNT(*) as jumlah_difasilitasi 
                           FROM pendaftaran 
                           WHERE merek_difasilitasi IS NOT NULL 
                           AND YEAR(tgl_daftar) = :tahun";

    $stmt = $pdo->prepare($query_difasilitasi);
    $stmt->execute(['tahun' => $tahun_sekarang]);
    $data = $stmt->fetch();
    $jumlah_difasilitasi = $data['jumlah_difasilitasi'];

    $kuota_tersedia = $total_kuota - $jumlah_difasilitasi;

    if ($kuota_tersedia < 0) {
        $kuota_tersedia = 0;
    }
} catch (PDOException $e) {
    $jumlah_difasilitasi = 0;
    $kuota_tersedia = $total_kuota;
    error_log("Error mengambil data kuota: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pendaftaran Merek</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <style>
        * {
            font-family: 'Montserrat', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f8f9fa;
            padding-top: 65px;
        }

        .dashboard-header {
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.8)), url(assets/img/bg-dashboard.png);
            background-size: cover;
            background-position: center;
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .welcome-text {
            font-size: 1.1rem;
            font-weight: 400;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }

        .user-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .user-nik {
            font-size: 0.95rem;
            opacity: 0.8;
        }

        .alert-info-custom {
            background: linear-gradient(135deg, #e7f3ff 0%, #d4e9ff 100%);
            border-left: 4px solid #0d6efd;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .menu-section {
            padding: 2rem 0;
        }

        .menu-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            height: 100%;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: #161616;
        }

        .menu-card.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .menu-card.disabled:hover {
            transform: none;
            box-shadow: none;
            border-color: #e9ecef;
        }

        .menu-icon {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: white;
        }

        .menu-icon.primary {
            background: linear-gradient(135deg, #161616 0%, #2d2d2d 100%);
        }

        .menu-icon.success {
            background: linear-gradient(135deg, #06923E 0%, #015322 100%);
        }

        .menu-icon.info {
            background: linear-gradient(135deg, #0D3C87 0%, #07224D 100%);
        }

        .menu-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #161616;
        }

        .menu-description {
            font-size: 0.9rem;
            color: #6c757d;
            line-height: 1.5;
            margin-bottom: 0;
        }

        .stats-section {
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            height: 100%;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .stat-card.blue {
            background: linear-gradient(135deg, #0D3C87, #07224D);
            color: white;
        }

        .stat-card.green {
            background: linear-gradient(135deg, #06923E, #015322);
            color: white;
        }

        .stat-card.red {
            background: linear-gradient(135deg, #CB0404, #910A0A);
            color: white;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.85rem;
            font-weight: 500;
            opacity: 0.95;
        }

        .info-section {
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            height: 100%;
            border: 2px solid #e9ecef;
        }

        .info-card h5 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #161616;
        }

        .info-card ul {
            padding-left: 1.25rem;
            margin-bottom: 0;
        }

        .info-card li {
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            background-color: #161616;
            color: white;
            text-align: center;
            padding: 2rem 0;
            margin-top: 3rem;
        }

        .footer p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            body {
                padding-top: 66px;
            }

            .dashboard-header {
                padding: 1.5rem 0;
            }

            .user-name {
                font-size: 1.5rem;
            }

            .welcome-text {
                font-size: 0.95rem;
            }

            .menu-card {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }

            .menu-icon {
                width: 60px;
                height: 60px;
                font-size: 1.75rem;
                margin-bottom: 1rem;
            }

            .menu-title {
                font-size: 1.1rem;
            }

            .menu-description {
                font-size: 0.85rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .stat-label {
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <?php include 'navbar-login.php' ?>

    <!-- Dashboard Header -->
    <section class="dashboard-header">
        <div class="container">
            <div class="welcome-text">Selamat Datang,</div>
            <div class="user-name"><?php echo strtoupper(htmlspecialchars($nama)); ?></div>
            <div class="user-nik">NIK: <?php 
                // Masking NIK: tampilkan 6 digit pertama dan 2 digit terakhir
                $nik_masked = substr($nik, 0, 6) . '******' . substr($nik, -2);
                echo htmlspecialchars($nik_masked); 
            ?></div>
        </div>
    </section>

    <div class="container">
        <?php if ($sudahDaftar): ?>
            <!-- Alert Info -->
            <div class="alert-info-custom">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Informasi:</strong> Anda sudah memiliki pendaftaran merek yang aktif.
                Silakan cek status pengajuan Anda atau ajukan surat keterangan untuk fasilitasi mandiri.
            </div>
        <?php endif; ?>

        <!-- Menu Cards -->
        <section class="menu-section">
            <div class="row g-4">
                <!-- Form Pendaftaran Merek -->
                <?php if (!$sudahDaftar): ?>
                    <div class="col-12 col-md-4">
                        <?php if ($kuota_tersedia <= 0): ?>
                            <div class="menu-card disabled" onclick="showQuotaFullAlert()">
                                <div class="menu-icon primary">
                                    <i class="bi bi-file-earmark-text"></i>
                                </div>
                                <div class="menu-title">Fasilitasi Merek Gratis</div>
                                <div class="menu-description">
                                    Daftarkan merek usaha Anda untuk mendapatkan perlindungan hukum dan fasilitasi dari Disperindag Sidoarjo.
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="form-pendaftaran.php" class="menu-card">
                                <div class="menu-icon primary">
                                    <i class="bi bi-file-earmark-text"></i>
                                </div>
                                <div class="menu-title">Fasilitasi Merek Gratis</div>
                                <div class="menu-description">
                                    Daftarkan merek usaha Anda untuk mendapatkan perlindungan hukum dan fasilitasi dari Disperindag Sidoarjo.
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Pengajuan Surat Keterangan -->
                <div class="col-12 col-md-4">
                    <a href="pengajuan-surat-keterangan.php" class="menu-card">
                        <div class="menu-icon info">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </div>
                        <div class="menu-title">Pengajuan Surat Keterangan</div>
                        <div class="menu-description">
                            Ajukan surat keterangan untuk fasilitasi mandiri pendaftaran merek ke Kemenkumham secara online.
                        </div>
                    </a>
                </div>

                <!-- Lihat Pengajuan -->
                <div class="col-12 col-md-4">
                    <a href="lihat-pengajuan-fasilitasi.php" class="menu-card">
                        <div class="menu-icon success">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <div class="menu-title">Lihat Pengajuan Fasilitasi</div>
                        <div class="menu-description">
                            Pantau status pengajuan pendaftaran merek Anda dan lihat detail proses validasinya.
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats-section">
            <h5 class="mb-4" style="font-weight: 600; color: #161616;">Statistik Kuota Pendaftaran Tahun <?php echo $tahun_sekarang; ?></h5>
            <div class="row g-4">
                <div class="col-12 col-md-4">
                    <div class="stat-card blue">
                        <div class="stat-number"><?php echo $total_kuota; ?></div>
                        <div class="stat-label">Total Kuota Per Tahun</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="stat-card green">
                        <div class="stat-number"><?php echo $kuota_tersedia; ?></div>
                        <div class="stat-label">Kuota Masih Tersedia</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="stat-card red">
                        <div class="stat-number"><?php echo $jumlah_difasilitasi; ?></div>
                        <div class="stat-label">Merek Sudah Difasilitasi</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Info Section -->
        <section class="info-section">
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <div class="info-card">
                        <h5><i class="bi bi-clipboard-data me-2"></i>Syarat Fasilitasi Merek Gratis</h5>
                        <ul>
                            <li>Industri Kecil yang memproduksi produk di Sidoarjo</li>
                            <li>Aktif memproduksi dan memasarkan secara kontinyu</li>
                            <li>Produk kemasan dengan masa simpan lebih dari 7 hari</li>
                            <li>Memiliki NIB berbasis risiko dengan KBLI industri</li>
                            <li>Menyiapkan 3 alternatif logo merek</li>
                            <li>Menyertakan foto produk jadi dan proses produksi</li>
                        </ul>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="info-card">
                        <h5><i class="bi bi-info-circle me-2"></i>Informasi Penting</h5>
                        <ul>
                            <li><strong>Cek Ketersediaan Merek:</strong><br>
                                <a href="https://pdki-indonesia.dgip.go.id/" target="_blank" class="text-primary">PDKI Indonesia</a>
                            </li>
                            <li><strong>Sistem Klasifikasi Merek:</strong><br>
                                <a href="https://skm.dgip.go.id/" target="_blank" class="text-primary">SKM DGIP</a>
                            </li>
                            <li><strong>Pengumuman Peserta Terpilih:</strong><br>
                                Setiap 3 bulan melalui Instagram @disperindagsidoarjo
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <footer class="footer">
        <div class="container text-center">
            <p class="mb-1">Copyright © 2025. All Rights Reserved.</p>
            <p class="mb-0">Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function showQuotaFullAlert() {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.style.zIndex = '9998';

            const alertContainer = document.createElement('div');
            alertContainer.style.position = 'fixed';
            alertContainer.style.top = '50%';
            alertContainer.style.left = '50%';
            alertContainer.style.transform = 'translate(-50%, -50%)';
            alertContainer.style.zIndex = '9999';
            alertContainer.style.width = '90%';
            alertContainer.style.maxWidth = '550px';

            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show m-0';
            alertDiv.style.boxShadow = '0 15px 50px rgba(0,0,0,0.4)';
            alertDiv.style.borderRadius = '12px';
            alertDiv.style.padding = '1.5rem';
            alertDiv.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i class="bi bi-exclamation-octagon-fill" style="font-size: 2rem; color: #dc3545;"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="alert-heading mb-2" style="font-weight: 600;">Kuota Fasilitasi Penuh!</h5>
                        <p class="mb-3" style="line-height: 1.6;">
                            Kuota fasilitasi merek gratis untuk tahun ini sudah penuh. 
                            Jika ingin melanjutkan, pendaftaran Anda akan diproses <strong>tahun depan</strong>.
                        </p>
                        <p class="mb-3" style="line-height: 1.6;">
                            Atau Anda dapat mengajukan <strong>"Surat Keterangan"</strong> untuk fasilitasi mandiri ke Kemenkumham.
                        </p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-danger" onclick="lanjutkanPendaftaran()" style="font-weight: 500;">
                                <i class="bi bi-check-circle me-1"></i> Lanjutkan Tahun Depan
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="batalkanPendaftaran()" style="font-weight: 500;">
                                <i class="bi bi-x-circle me-1"></i> Batal
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-2" aria-label="Close" style="font-size: 0.9rem;"></button>
                </div>
            `;

            alertContainer.appendChild(alertDiv);

            document.body.appendChild(backdrop);
            document.body.appendChild(alertContainer);

            const closeBtn = alertDiv.querySelector('.btn-close');
            closeBtn.addEventListener('click', () => {
                backdrop.remove();
                alertContainer.remove();
            });

            backdrop.addEventListener('click', () => {
                backdrop.remove();
                alertContainer.remove();
            });
        }

        function lanjutkanPendaftaran() {
            window.location.href = 'form-pendaftaran.php';
        }

        function batalkanPendaftaran() {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            const alerts = document.querySelectorAll('.alert');

            backdrops.forEach(b => b.remove());
            alerts.forEach(a => {
                if (a.parentElement && a.parentElement.style.position === 'fixed') {
                    a.parentElement.remove();
                }
            });
        }
    </script>
</body>

</html>