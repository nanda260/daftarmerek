<?php
session_start();
if (!isset($_SESSION['NIK_NIP']) || !isset($_SESSION['nama_lengkap'])) {
    header("Location: login.php");
    exit();
}

$nama = $_SESSION['nama_lengkap'];
$nik = $_SESSION['NIK_NIP'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Surat Keterangan - Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
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

        .header-section {
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.8)), url(assets/img/bg-dashboard.png);
            background-size: cover;
            background-position: center;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header-subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }

        .container-main {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        .option-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .option-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
            border-color: #161616;
        }

        .option-icon {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: white;
        }

        .option-icon.mandiri {
            background: linear-gradient(135deg, #161616 0%, #2d2d2d 100%);
        }

        .option-icon.perpanjang {
            background: linear-gradient(135deg, #06923E 0%, #015322 100%);
        }

        .option-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #161616;
        }

        .option-description {
            font-size: 0.95rem;
            color: #6c757d;
            line-height: 1.6;
            flex-grow: 1;
            margin-bottom: 1.5rem;
        }

        .option-benefits {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .option-benefits h6 {
            font-size: 0.85rem;
            font-weight: 600;
            color: #161616;
            margin-bottom: 0.5rem;
        }

        .option-benefits ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .option-benefits li {
            font-size: 0.85rem;
            color: #6c757d;
            padding: 0.3rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .option-benefits li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #06923E;
            font-weight: bold;
        }

        .btn-option {
            background: linear-gradient(135deg, #161616 0%, #2d2d2d 100%);
            color: white !important;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: auto;
        }

        .btn-option:hover {
            background: linear-gradient(135deg, #2d2d2d 0%, #3d3d3d 100%);
            color: white;
            text-decoration: none;
        }

        .btn-option.green {
            background: linear-gradient(135deg, #06923E 0%, #015322 100%);
        }

        .btn-option.green:hover {
            background: linear-gradient(135deg, #015322 0%, #010f1a 100%);
        }

        .footer {
            background-color: #161616;
            color: white;
            text-align: center;
            padding: 2rem 0;
            margin-top: 3rem;
        }

        @media (max-width: 768px) {
            .header-title {
                font-size: 1.5rem;
            }

            .options-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .option-card {
                padding: 1.5rem;
            }

            .option-icon {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }

            .option-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <?php include 'navbar-login.php' ?>

    <!-- Header -->
    <section class="header-section">
        <div class="container">
            <div class="header-title">Pengajuan Surat Keterangan</div>
            <div class="header-subtitle">Pilih jenis pengajuan yang Anda inginkan</div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container-main">
        <div class="alert alert-info" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Informasi:</strong> Surat Keterangan IKM dapat digunakan untuk fasilitasi mandiri pendaftaran atau perpanjangan merek ke Kementerian Hukum dan HAM.
        </div>

        <div class="options-grid">
            <!-- Card Pendaftaran Mandiri -->
            <div class="option-card">
                <div class="option-icon mandiri">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="option-title">Pendaftaran Mandiri</div>
                <div class="option-description">
                    Ajukan surat keterangan untuk fasilitasi mendaftar merek baru secara mandiri ke Kementerian Hukum dan HAM tanpa melalui program fasilitasi gratis.
                </div>
                <a href="form-pendaftaran-mandiri.php" class="btn btn-option">
                    <i class="bi bi-arrow-right me-2"></i> Lanjutkan
                </a>
            </div>

            <!-- Card Perpanjangan Sertifikat -->
            <div class="option-card">
                <div class="option-icon perpanjang">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div class="option-title">Perpanjangan Sertifikat</div>
                <div class="option-description">
                    Ajukan surat keterangan untuk perpanjangan sertifikat merek yang sudah terdaftar sebelumnya di Kementerian Hukum dan HAM.
                </div>
                <a href="form-perpanjangan-mandiri.php" class="btn btn-option green">
                    <i class="bi bi-arrow-right me-2"></i> Lanjutkan
                </a>
            </div>
        </div>

        <!-- Info Box -->
        <div class="alert alert-warning mt-4" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Perhatian:</strong> Pastikan semua data yang Anda masukkan akurat dan sesuai dengan dokumen yang Anda miliki.
        </div>


        <!-- Tombol Back -->
        <div class="row mt-4">
            <div class="col-12 text-end">
                <a href="home.php" class="btn btn-dark">
                    <i class="fa-solid fa-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container text-center">
            <p class="mb-1">Copyright © 2025. All Rights Reserved.</p>
            <p class="mb-0">Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>