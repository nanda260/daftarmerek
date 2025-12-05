<?php
session_start();
if (!isset($_SESSION['NIK_NIP']) || !isset($_SESSION['nama_lengkap'])) {
    header("Location: login.php");
    exit();
}

$nama = $_SESSION['nama_lengkap'];
$nik = $_SESSION['NIK_NIP'];

require_once 'process/config_db.php';

// Ambil data user dari tabel user
$stmt_user = $pdo->prepare("SELECT * FROM user WHERE NIK_NIP = ?");
$stmt_user->execute([$nik]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

// Siapkan data user untuk JavaScript
$nama_pemilik = $user_data['nama_lengkap'] ?? '';
$alamat_pemilik = ($user_data['rt_rw'] ? $user_data['rt_rw'] . ', ' : '') .
    ($user_data['kel_desa'] ? $user_data['kel_desa'] . ', ' : '') .
    ($user_data['kecamatan'] ? $user_data['kecamatan'] . ', ' : '') .
    ($user_data['nama_kabupaten'] ? $user_data['nama_kabupaten'] . ', ' : '') .
    ($user_data['nama_provinsi'] ? $user_data['nama_provinsi'] : '');
$no_telp_pemilik = $user_data['no_wa'] ?? '';
$email = $user_data['email'] ?? '';

// Ambil daftar pendaftaran merek
$stmt_pendaftaran = $pdo->prepare("
    SELECT 
        p.id_pendaftaran,
        p.NIK,
        du.nama_usaha,
        du.jenis_pemohon,
        m.nama_merek1 as nama_merek,
        p.tgl_daftar,
       p.status_validasi,
       (SELECT COUNT(*) FROM lampiran l 
        WHERE l.id_pendaftaran = p.id_pendaftaran 
        AND l.id_jenis_file = 8) as is_ditolak
    FROM pendaftaran p
    JOIN datausaha du ON p.id_usaha = du.id_usaha
    JOIN merek m ON m.id_pendaftaran = p.id_pendaftaran
    WHERE p.NIK = ?
    ORDER BY p.tgl_daftar DESC
");
$stmt_pendaftaran->execute([$nik]);
$daftar_pendaftaran = $stmt_pendaftaran->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar pengajuan surat mandiri
$stmt_pengajuan = $pdo->prepare("
    SELECT * FROM pengajuansurat
    WHERE NIK = ? AND tipe_pengajuan = 'mandiri'
    ORDER BY tgl_daftar DESC
");
$stmt_pengajuan->execute([$nik]);
$daftar_pengajuan = $stmt_pengajuan->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_perpanjangan'])) {
    // Debug: Log received files
    error_log('POST data received');
    error_log('Files received: ' . print_r(array_keys($_FILES), true));
    error_log('Akta file info: ' . print_r($_FILES['akta'] ?? 'NOT SET', true));
    error_log('Akta path old: ' . ($_POST['akta_path_old'] ?? 'NOT SET'));

    try {
        // Validasi input (sama seperti form-pendaftaran-mandiri.php)
        $nama_usaha = htmlspecialchars($_POST['nama_usaha'] ?? '', ENT_QUOTES);
        $kecamatan_usaha = htmlspecialchars($_POST['kecamatan_usaha'] ?? '', ENT_QUOTES);
        $rt_rw_usaha = htmlspecialchars($_POST['rt_rw_usaha'] ?? '', ENT_QUOTES);
        $kel_desa_usaha = htmlspecialchars($_POST['kel_desa_usaha'] ?? '', ENT_QUOTES);

        $alamat_usaha = '';
        if ($kel_desa_usaha) $alamat_usaha .= 'Desa/Kel. ' . $kel_desa_usaha . ', ';
        if ($rt_rw_usaha) $alamat_usaha .= 'RT/RW ' . $rt_rw_usaha . ', ';
        if ($kecamatan_usaha) $alamat_usaha .= 'Kecamatan ' . $kecamatan_usaha . ', ';
        $alamat_usaha .= 'SIDOARJO, JAWA TIMUR';
        $no_telp_perusahaan = htmlspecialchars($_POST['no_telp_perusahaan'] ?? '', ENT_QUOTES);


        $jenis_usaha = htmlspecialchars($_POST['jenis_usaha'] ?? '', ENT_QUOTES);
        if ($jenis_usaha === 'lainnya') {
            $jenis_usaha = htmlspecialchars($_POST['jenis_usaha_lainnya'] ?? '', ENT_QUOTES);
        }
        $produk = htmlspecialchars($_POST['produk'] ?? '', ENT_QUOTES);
        $jml_tenaga_kerja = (int)($_POST['jml_tenaga_kerja'] ?? 0);
        $merek = htmlspecialchars($_POST['merek'] ?? '', ENT_QUOTES);
        $kelas_merek = htmlspecialchars($_POST['kelas_merek'] ?? '', ENT_QUOTES);

        // Validasi dasar
        if (
            empty($nama_usaha) || empty($alamat_usaha) || empty($no_telp_perusahaan) ||
            empty($jenis_usaha) || empty($produk) || empty($merek) || empty($kelas_merek)
        ) {
            throw new Exception('Silakan isi semua field yang wajib (ditandai *)');
        }

        if ($jml_tenaga_kerja < 1) {
            throw new Exception('Jumlah tenaga kerja harus minimal 1 orang');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format email tidak valid');
        }

        // Setup upload directories
        $upload_dir_logo = 'uploads/logo_mandiri/';
        $upload_dir_logo_perusahaan = 'uploads/logo_perusahaan_mandiri/';
        $upload_dir_nib = 'uploads/nib_mandiri/';
        $upload_dir_foto_produk = 'uploads/foto_produk_mandiri/';
        $upload_dir_foto_proses = 'uploads/foto_proses_mandiri/';
        $upload_dir_akta = 'uploads/akta_mandiri/';

        foreach ([$upload_dir_logo, $upload_dir_logo_perusahaan, $upload_dir_nib, $upload_dir_foto_produk, $upload_dir_foto_proses, $upload_dir_akta] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Handle uploads (sama seperti sebelumnya)
        $logo_path = null;
        $nib_paths = [];
        $foto_produk_paths = [];
        $foto_proses_paths = [];
        $akta_path = null;

        // Upload Logo
        if (isset($_FILES['logo_merek']) && $_FILES['logo_merek']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024;

            if (!in_array($_FILES['logo_merek']['type'], $allowed_types)) {
                throw new Exception('Format logo harus JPG, PNG, atau GIF');
            }

            if ($_FILES['logo_merek']['size'] > $max_size) {
                throw new Exception('Ukuran logo tidak boleh lebih dari 5MB');
            }

            $file_ext = pathinfo($_FILES['logo_merek']['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . $nik . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir_logo . $filename;

            if (!move_uploaded_file($_FILES['logo_merek']['tmp_name'], $file_path)) {
                throw new Exception('Gagal mengupload logo');
            }
            $logo_path = $file_path;
        } else {
            throw new Exception('Logo merek wajib diupload');
        }

        // Upload NIB
        if (isset($_FILES['nib']) && is_array($_FILES['nib']['name'])) {
            if (count($_FILES['nib']['name']) < 1) {
                throw new Exception('Minimal 1 file NIB harus diupload');
            }

            if (count($_FILES['nib']['name']) > 5) {
                throw new Exception('Maksimal 5 file NIB yang dapat diupload');
            }

            foreach ($_FILES['nib']['name'] as $key => $filename) {
                if ($_FILES['nib']['error'][$key] === UPLOAD_ERR_OK) {
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                    $max_size = 5 * 1024 * 1024;

                    if (!in_array($_FILES['nib']['type'][$key], $allowed_types)) {
                        throw new Exception('Format NIB harus PDF, JPG, atau PNG');
                    }

                    if ($_FILES['nib']['size'][$key] > $max_size) {
                        throw new Exception('Ukuran file NIB tidak boleh lebih dari 5MB');
                    }

                    $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $new_filename = 'nib_' . $nik . '_' . time() . '_' . count($nib_paths) . '.' . $file_ext;
                    $file_path = $upload_dir_nib . $new_filename;

                    if (!move_uploaded_file($_FILES['nib']['tmp_name'][$key], $file_path)) {
                        throw new Exception('Gagal mengupload salah satu file NIB');
                    }
                    $nib_paths[] = $file_path;
                }
            }
            if (empty($nib_paths)) {
                throw new Exception('Minimal 1 file NIB harus diupload');
            }
        } else {
            throw new Exception('File NIB wajib diupload');
        }

        // Handle jenis pemohon
        $jenis_pemohon = htmlspecialchars($_POST['jenis_pemohon'] ?? 'perseorangan', ENT_QUOTES);
        $nama_perusahaan = null;
        $logo_perusahaan_path = null;
        $alamat_perusahaan = null;
        $no_telp_perusahaan = null;
        $email_perusahaan = null;

        if ($jenis_pemohon === 'perusahaan') {
            $nama_perusahaan = htmlspecialchars($_POST['nama_perusahaan'] ?? '', ENT_QUOTES);
            $alamat_perusahaan = htmlspecialchars($_POST['alamat_perusahaan'] ?? '', ENT_QUOTES);
            $no_telp_perusahaan = htmlspecialchars($_POST['no_telp_perusahaan'] ?? '', ENT_QUOTES);
            $email_perusahaan = htmlspecialchars($_POST['email_perusahaan'] ?? '', ENT_QUOTES);

            // Upload logo perusahaan
            if (isset($_FILES['logo_perusahaan']) && $_FILES['logo_perusahaan']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($_FILES['logo_perusahaan']['type'], $allowed_types)) {
                    throw new Exception('Format logo perusahaan harus JPG, PNG, atau GIF');
                }
                $file_ext = pathinfo($_FILES['logo_perusahaan']['name'], PATHINFO_EXTENSION);
                $filename = 'logo_perusahaan_' . $nik . '_' . time() . '.' . $file_ext;
                $file_path = 'uploads/logo_perusahaan_mandiri/' . $filename;
                if (!move_uploaded_file($_FILES['logo_perusahaan']['tmp_name'], $file_path)) {
                    throw new Exception('Gagal mengupload logo perusahaan');
                }
                $logo_perusahaan_path = $file_path;
            }
        }

        // Upload Foto Produk
        if (isset($_FILES['foto_produk']) && is_array($_FILES['foto_produk']['name'])) {
            if (count($_FILES['foto_produk']['name']) < 1) {
                throw new Exception('Minimal 1 foto produk harus diupload');
            }

            foreach ($_FILES['foto_produk']['name'] as $key => $filename) {
                if ($_FILES['foto_produk']['error'][$key] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024;

                    if (!in_array($_FILES['foto_produk']['type'][$key], $allowed_types)) {
                        throw new Exception('Format foto produk harus JPG, PNG, atau GIF');
                    }

                    if ($_FILES['foto_produk']['size'][$key] > $max_size) {
                        throw new Exception('Ukuran foto produk tidak boleh lebih dari 5MB');
                    }

                    $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $new_filename = 'produk_' . $nik . '_' . time() . '_' . count($foto_produk_paths) . '.' . $file_ext;
                    $file_path = $upload_dir_foto_produk . $new_filename;

                    if (!move_uploaded_file($_FILES['foto_produk']['tmp_name'][$key], $file_path)) {
                        throw new Exception('Gagal mengupload salah satu foto produk');
                    }
                    $foto_produk_paths[] = $file_path;
                }
            }
            if (empty($foto_produk_paths)) {
                throw new Exception('Minimal 1 foto produk harus diupload');
            }
        } else {
            throw new Exception('Foto produk wajib diupload');
        }

        // Upload Foto Proses Produksi
        if (isset($_FILES['foto_proses']) && is_array($_FILES['foto_proses']['name'])) {
            if (count($_FILES['foto_proses']['name']) < 1) {
                throw new Exception('Minimal 1 foto proses produksi harus diupload');
            }

            foreach ($_FILES['foto_proses']['name'] as $key => $filename) {
                if ($_FILES['foto_proses']['error'][$key] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024;

                    if (!in_array($_FILES['foto_proses']['type'][$key], $allowed_types)) {
                        throw new Exception('Format foto proses harus JPG, PNG, atau GIF');
                    }

                    if ($_FILES['foto_proses']['size'][$key] > $max_size) {
                        throw new Exception('Ukuran foto proses tidak boleh lebih dari 5MB');
                    }

                    $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $new_filename = 'proses_' . $nik . '_' . time() . '_' . count($foto_proses_paths) . '.' . $file_ext;
                    $file_path = $upload_dir_foto_proses . $new_filename;

                    if (!move_uploaded_file($_FILES['foto_proses']['tmp_name'][$key], $file_path)) {
                        throw new Exception('Gagal mengupload salah satu foto proses');
                    }
                    $foto_proses_paths[] = $file_path;
                }
            }
            if (empty($foto_proses_paths)) {
                throw new Exception('Minimal 1 foto proses produksi harus diupload');
            }
        } else {
            throw new Exception('Foto proses produksi wajib diupload');
        }

        // Upload Akta (optional)
        {
            // Prioritaskan file baru jika ada
            if (isset($_FILES['akta']) && $_FILES['akta']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['application/pdf'];
                $max_size = 5 * 1024 * 1024;

                if (!in_array($_FILES['akta']['type'], $allowed_types)) {
                    throw new Exception('Format akta harus PDF');
                }

                if ($_FILES['akta']['size'] > $max_size) {
                    throw new Exception('Ukuran file akta tidak boleh lebih dari 5MB');
                }

                $file_ext = 'pdf';
                $filename = 'akta_' . $nik . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir_akta . $filename;

                if (!move_uploaded_file($_FILES['akta']['tmp_name'], $file_path)) {
                    throw new Exception('Gagal mengupload akta');
                }
                $akta_path = $file_path;
            } elseif (isset($_POST['akta_path_old']) && !empty($_POST['akta_path_old'])) {
                // Gunakan akta lama jika tidak ada upload baru
                $akta_path_old = htmlspecialchars($_POST['akta_path_old'], ENT_QUOTES);

                // Validasi path untuk keamanan (pastikan tidak ada directory traversal)
                $akta_path_old = str_replace(['../', '..\\'], '', $akta_path_old);

                if (file_exists($akta_path_old)) {
                    $akta_path = $akta_path_old;
                }
            }
        }

        // Ambil path surat permohonan dari hidden input
        $suratpermohonan_path = htmlspecialchars($_POST['suratpermohonan'] ?? '', ENT_QUOTES);

        if (empty($suratpermohonan_path)) {
            throw new Exception('Surat permohonan belum di-generate. Mohon klik tombol "Simpan Tanda Tangan" terlebih dahulu.');
        }

        if (!file_exists($suratpermohonan_path)) {
            throw new Exception('File surat permohonan tidak ditemukan. Mohon generate ulang.');
        }

        // Insert ke tabel pengajuansurat
        $id_pendaftaran = htmlspecialchars($_POST['id_pendaftaran'] ?? '', ENT_QUOTES);
        $id_pendaftaran = $id_pendaftaran ? $id_pendaftaran : null;

        $stmt_insert = $pdo->prepare("
             INSERT INTO pengajuansurat (
                NIK, id_pendaftaran, tipe_pengajuan, nama_usaha, alamat_usaha, no_telp_perusahaan,
                 nama_pemilik, alamat_pemilik, no_telp_pemilik, email,
                jenis_usaha, produk, jml_tenaga_kerja, jenis_pemohon,
                nama_perusahaan, logo_perusahaan, alamat_perusahaan, no_telp_kop, email_perusahaan,
                merek, kelas_merek,
                 logo_merek, nib_file, foto_produk, foto_proses, akta_file, suratpermohonan_file,
                 status_validasi, tgl_daftar
             ) VALUES (
                 ?, ?, 'perpanjangan', ?, ?, ?,
                 ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?,
                 ?, ?, ?, ?, ?, ?,
                 'Menunggu Surat Terbit', NOW()
             )
         ");

        $nib_json = json_encode($nib_paths);
        $foto_produk_json = json_encode($foto_produk_paths);
        $foto_proses_json = json_encode($foto_proses_paths);

        $no_telp_perusahaan = htmlspecialchars($_POST['no_telp_perusahaan'] ?? '', ENT_QUOTES);
        $no_telp_kop = htmlspecialchars($_POST['no_telp_kop'] ?? '', ENT_QUOTES);

        $stmt_insert->execute([
            $nik,
            $id_pendaftaran,
            $nama_usaha,
            $alamat_usaha,
            $no_telp_perusahaan,
            $nama_pemilik,
            $alamat_pemilik,
            $no_telp_pemilik,
            $email,
            $jenis_usaha,
            $produk,
            $jml_tenaga_kerja,
            $jenis_pemohon,
            $nama_perusahaan,
            $logo_perusahaan_path,
            $alamat_perusahaan,
            $no_telp_kop,
            $email_perusahaan,
            $merek,
            $kelas_merek,
            $logo_path,
            $nib_json,
            $foto_produk_json,
            $foto_proses_json,
            $akta_path,
            $suratpermohonan_path
        ]);

        $_SESSION['alert_message'] = "Perpanjangan surat keterangan berhasil diajukan!\n\nPermohonan Anda sedang diproses admin. Anda akan menerima notifikasi ketika status berubah.";
        $_SESSION['alert_type'] = 'success';

        header("Location: lihat-pengajuan-fasilitasi.php");
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perpanjangan Surat Keterangan - Disperindag Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/form-pendaftaran.css">
    <link rel="stylesheet" href="assets/css/preview-file.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <style>
        #btnSubmit {
            transition: all 0.3s ease;
        }

        #btnSubmit:disabled,
        #btnSubmit.disabled {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            background-color: #6c757d !important;
        }

        #btnSubmit:not(:disabled):not(.disabled) {
            opacity: 1 !important;
            cursor: pointer !important;
            pointer-events: auto !important;
        }

        .riwayat-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .riwayat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .riwayat-card.active {
            border-color: #007bff;
            background-color: #e7f1ff;
        }

        .riwayat-card.opacity-50 {
            opacity: 0.5;
            cursor: not-allowed !important;
        }

        .riwayat-card.opacity-50:hover {
            box-shadow: none !important;
            transform: none !important;
        }

        #profilPerusahaanSection {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #0d6efd;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 4px 6px rgba(13, 110, 253, 0.1);
        }

        #profilPerusahaanSection .alert-info {
            border-left: 4px solid #0d6efd;
            background-color: rgba(13, 110, 253, 0.08);
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php include 'navbar-login.php' ?>

    <div class="container main-container">
        <div class="row cont">
            <!-- Sidebar -->
            <div class="col-lg-4">
                <h5 class="judul">Perpanjangan Surat Keterangan</h5>
                <p>Pilih salah satu pendaftaran merek atau pengajuan surat sebelumnya untuk melakukan perpanjangan. Data akan dimuat otomatis ke formulir.</p>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-info-square pe-2"></i>Informasi</h5>
                    <ul class="list-unstyled info-list">
                        <li><strong>Output:</strong> Surat Keterangan IKM Perpanjangan Merek</li>
                        <li><strong>Syarat:</strong> Minimal 1 pendaftaran merek sebelumnya</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle bg-info bg-opacity-10">
                    <h5><i class="bi bi-exclamation-triangle pe-2"></i>Catatan Penting</h5>
                    <ul class="info-list">
                        <li>Data dari pendaftaran lama akan dimuat otomatis</li>
                        <li>Anda dapat memperbarui lampiran dokumen</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5>Bantuan</h5>
                    <p>Jika ada kendala dalam mengisi formulir bisa menghubungi kami dibawah ini.</p>
                    <a href="https://wa.me/6281235051286?text=Halo%2C%20saya%20ingin%20bertanya%20mengenai%20layanan%20industri" class="help-contact" target="_blank">
                        <i class="fab fa-whatsapp pe-2"></i> Bidang Perindustrian Disperindag Sidoarjo
                    </a>
                    <p class="text-danger mt-2">* Tidak menerima panggilan, hanya chat.</p>
                </div>
            </div>

            <!-- Form Content -->
            <div class="col-lg-8">
                <div class="form-container border border-light-subtle">
                    <h4>Form Perpanjangan Surat Keterangan</h4>
                    <hr class="border-2 border-secondary w-100">

                    <!-- Alert Container -->
                    <div id="alertContainer"></div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            <strong>Error:</strong> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Pilihan Data Sebelumnya -->
                    <div class="mb-4">
                        <h5>Langkah 1: Pilih Data Sebelumnya</h5>
                        <hr class="border-2 border-secondary w-100">

                        <?php if (empty($daftar_pendaftaran) && empty($daftar_pengajuan)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Anda belum memiliki data pendaftaran merek atau pengajuan surat sebelumnya. Silakan lakukan pendaftaran terlebih dahulu.
                            </div>
                            <a href="pengajuan-surat-keterangan.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i> Kembali
                            </a>
                        <?php else: ?>
                            <div class="row">
                                <!-- Daftar Pendaftaran Merek -->

                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3"><i class="bi bi-bookmark-check me-2"></i>Fasilitasi Merek Gratis</h6>
                                    <?php foreach ($daftar_pendaftaran as $pendaftaran): ?>
                                        <?php
                                        $isDitolak = ($pendaftaran['is_ditolak'] > 0);
                                        $cardClass = $isDitolak ? 'riwayat-card opacity-50' : 'riwayat-card';
                                        $dataAttr = $isDitolak ? '' : 'data-type="pendaftaran" data-id="' . $pendaftaran['id_pendaftaran'] . '"';
                                        $cursorStyle = $isDitolak ? 'style="cursor: not-allowed;"' : '';
                                        ?>
                                        <div class="card <?php echo $cardClass; ?> mb-3" <?php echo $dataAttr; ?> <?php echo $cursorStyle; ?>>
                                            <div class="card-body">
                                                <h6 class="card-title mb-2"><?php echo htmlspecialchars($pendaftaran['nama_usaha']); ?></h6>
                                                <p class="card-text mb-1">
                                                    <small class="text-muted">
                                                        <i class="bi bi-tag me-1"></i>
                                                        <?php echo htmlspecialchars($pendaftaran['nama_merek']); ?>
                                                    </small>
                                                </p>
                                                <p class="card-text mb-0">
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar me-1"></i>
                                                        <?php echo date('d M Y', strtotime($pendaftaran['tgl_daftar'])); ?>
                                                    </small>
                                                </p>
                                                <?php if ($isDitolak): ?>
                                                    <div class="mt-2">
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-x-circle me-1"></i>Ditolak Kementerian
                                                        </span>
                                                        <p class="text-danger small mb-0 mt-2">
                                                            <i class="bi bi-info-circle me-1"></i>
                                                            Tidak dapat digunakan untuk perpanjangan karena merek ditolak oleh Kementerian
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Daftar Pengajuan Surat -->
                                <?php if (!empty($daftar_pengajuan)): ?>
                                    <div class="col-md-6">
                                        <h6 class="text-success mb-3"><i class="bi bi-file-text me-2"></i>Pengajuan Surat Mandiri</h6>
                                        <?php foreach ($daftar_pengajuan as $pengajuan): ?>
                                            <div class="card riwayat-card mb-3" data-type="pengajuan" data-id="<?php echo $pengajuan['id_pengajuan']; ?>">
                                                <div class="card-body">
                                                    <h6 class="card-title mb-2"><?php echo htmlspecialchars($pengajuan['nama_usaha']); ?></h6>
                                                    <p class="card-text mb-1">
                                                        <small class="text-muted">
                                                            <i class="bi bi-tag me-1"></i>
                                                            <?php echo htmlspecialchars($pengajuan['merek']); ?>
                                                        </small>
                                                    </p>
                                                    <p class="card-text mb-0">
                                                        <small class="text-muted">
                                                            <i class="bi bi-calendar me-1"></i>
                                                            <?php echo date('d M Y', strtotime($pengajuan['tgl_daftar'])); ?>
                                                        </small>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <input type="hidden" id="selectedDataType" value="">
                            <input type="hidden" id="selectedDataId" value="">
                        <?php endif; ?>
                    </div>

                    <!-- Form (akan muncul setelah memilih data) -->
                    <?php if (!empty($daftar_pendaftaran) || !empty($daftar_pengajuan)): ?>
                        <div id="formSection" style="display: none;">
                            <h5>Langkah 2: Perbarui Data (Opsional)</h5>
                            <hr class="border-2 border-secondary w-100">

                            <form method="POST" enctype="multipart/form-data" id="formPerpanjangan">
                                <input type="hidden" id="userNikData" value="<?php echo htmlspecialchars($nik); ?>">
                                <input type="hidden" name="id_pendaftaran" id="id_pendaftaran" value="">

                                <!-- Data Usaha -->
                                <h5>Data Usaha</h5>
                                <hr class="border-2 border-secondary w-100">

                                <div class="mb-3">
                                    <label class="form-label">Nama Usaha <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_usaha" class="form-control" required>
                                </div>

                                <div class="row">
                                    <label class="form-label">Alamat Usaha</label>
                                    <div class="mb-3">
                                        <label class="form-label-alamat">Kecamatan <span class="text-danger">*</span></label>
                                        <select name="kecamatan_usaha" id="kecamatan_usaha" class="form-control" required>
                                            <option value="">-- Pilih Kecamatan --</option>
                                            <option value="Sidoarjo">Sidoarjo</option>
                                            <option value="Buduran">Buduran</option>
                                            <option value="Candi">Candi</option>
                                            <option value="Porong">Porong</option>
                                            <option value="Krembung">Krembung</option>
                                            <option value="Tulangan">Tulangan</option>
                                            <option value="Tanggulangin">Tanggulangin</option>
                                            <option value="Jabon">Jabon</option>
                                            <option value="Krian">Krian</option>
                                            <option value="Balongbendo">Balongbendo</option>
                                            <option value="Wonoayu">Wonoayu</option>
                                            <option value="Tarik">Tarik</option>
                                            <option value="Prambon">Prambon</option>
                                            <option value="Taman">Taman</option>
                                            <option value="Waru">Waru</option>
                                            <option value="Gedangan">Gedangan</option>
                                            <option value="Sedati">Sedati</option>
                                            <option value="Sukodono">Sukodono</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label-alamat">RT/RW <span class="text-danger">*</span></label>
                                        <input type="text" name="rt_rw_usaha" id="rt_rw_usaha" class="form-control" placeholder="Contoh: 003/005">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label-alamat">Kelurahan/Desa <span class="text-danger">*</span></label>
                                        <select name="kel_desa_usaha" id="kel_desa_usaha" class="form-control" required>
                                            <option value="">-- Pilih Kecamatan Terlebih Dahulu --</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">No. Telepon Usaha <span class="text-danger">*</span></label>
                                        <input type="tel"
                                            name="no_telp_perusahaan"
                                            id="no_telp_perusahaan"
                                            class="form-control"
                                            placeholder="Nomor telepon usaha"
                                            required>
                                    </div>
                                </div>
                                <!-- Detail Usaha -->
                                <h5>Detail Usaha</h5>
                                <hr class="border-2 border-secondary w-100">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Jenis Usaha <span class="text-danger">*</span></label>
                                        <select name="jenis_usaha" class="form-select" id="jenis_usaha" required>
                                            <option value="">Pilih Jenis Usaha</option>
                                            <option value="Fashion dan Tekstil">Fashion & Tekstil</option>
                                            <option value="Kuliner dan Makanan">Kuliner & Makanan</option>
                                            <option value="Kerajinan dan Seni">Kerajinan & Seni</option>
                                            <option value="Elektronik dan Teknologi">Elektronik & Teknologi</option>
                                            <option value="Pertanian dan Agribisnis">Pertanian & Agribisnis</option>
                                            <option value="Jasa dan Konsultasi">Jasa & Konsultasi</option>
                                            <option value="Industri Kimia">Industri Kimia</option>
                                            <option value="Logistik dan Transportasi">Logistik & Transportasi</option>
                                            <option value="Perhotelan dan Pariwisata">Perhotelan & Pariwisata</option>
                                            <option value="Pendidikan dan Pelatihan">Pendidikan & Pelatihan</option>
                                            <option value="Kesehatan dan Kecantikan">Kesehatan & Kecantikan</option>
                                            <option value="Konstruksi dan Real Estate">Konstruksi & Real Estate</option>
                                            <option value="lainnya">Lainnya (Sebutkan)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Produk <span class="text-danger">*</span></label>
                                        <input type="text" name="produk" class="form-control" placeholder="Produk yang dihasilkan" required>
                                    </div>

                                    <div class="col-md-6 mb-3" id="jenis_usaha_lainnya_field" style="display: none;">
                                        <label class="form-label">Sebutkan Jenis Usaha Lainnya <span class="text-danger">*</span></label>
                                        <input type="text" name="jenis_usaha_lainnya" id="jenis_usaha_lainnya" class="form-control" placeholder="Masukkan jenis usaha Anda">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Jumlah Tenaga Kerja <span class="text-danger">*</span></label>
                                    <input type="number" name="jml_tenaga_kerja" class="form-control" min="1" required>
                                </div>

                                <!-- Jenis Pemohon (NEW) -->
                                <div class="mb-3">
                                    <label class="form-label">Jenis Pemohon <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="jenis_pemohon" id="perseorangan_perpanjangan" value="perseorangan" checked>
                                            <label class="form-check-label" for="perseorangan_perpanjangan">
                                                Perseorangan
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="jenis_pemohon" id="perusahaan_perpanjangan" value="perusahaan">
                                            <label class="form-check-label" for="perusahaan_perpanjangan">
                                                Perusahaan (CV/PT)
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Profil Perusahaan Section (NEW) -->
                                <div id="profilPerusahaanSection" style="display: none;">
                                    <h5>Profil Perusahaan</h5>
                                    <hr class="border-2 border-secondary w-100">
                                    <div class="alert alert-info mb-3" style="font-size: 0.9rem;">
                                        <i class="bi bi-info-circle-fill me-2"></i>
                                        <strong>Informasi:</strong> Data perusahaan ini akan digunakan untuk membuat <strong>Kop Surat otomatis.</strong>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Logo Perusahaan <span class="text-danger">*</span></label>
                                        <small class="text-muted d-block mb-2">
                                            Logo ini akan ditampilkan di kop surat perusahaan Anda
                                        </small>
                                        <div class="file-drop-zone" id="logoPerusahaanDropZone">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                            <small>Format: JPG, PNG, GIF | Ukuran max: 5MB</small>
                                            <input type="file" name="logo_perusahaan" id="logo-perusahaan-file" accept=".jpg,.jpeg,.png,.gif" hidden>
                                        </div>
                                        <div class="preview-container" id="logoPerusahaanPreview"></div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                                        <input type="text" name="nama_perusahaan" id="nama_perusahaan_perpanjangan" class="form-control" placeholder="PT/CV Nama Perusahaan">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Alamat Perusahaan <span class="text-danger">*</span></label>
                                        <textarea name="alamat_perusahaan" id="alamat_perusahaan_perpanjangan" class="form-control" rows="3" placeholder="Alamat lengkap perusahaan"></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">No. Telepon Kop Surat <span class="text-danger">*</span></label>
                                            <input type="tel" name="no_telp_kop" id="no_telp_kop_perpanjangan" class="form-control">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email Perusahaan <span class="text-danger">*</span></label>
                                            <input type="email" name="email_perusahaan" id="email_perusahaan_perpanjangan" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <!-- Data Merek -->
                                <h5>Data Merek</h5>
                                <hr class="border-2 border-secondary w-100">

                                <div class="mb-3">
                                    <label class="form-label">Merek <span class="text-danger">*</span></label>
                                    <input type="text" name="merek" class="form-control" required>
                                    <small class="text-muted">Nama merek yang ingin Anda daftarkan</small>
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-label mb-0">
                                            Kelas Merek <span class="text-danger">*</span>
                                        </label>
                                        <a href="#" class="text-primary text-decoration-none" style="font-size: 0.9rem;" data-bs-toggle="modal" data-bs-target="#modalKlasifikasiMerek">
                                            Lihat Sistem Klasifikasi Merek
                                        </a>
                                    </div>
                                    <input type="text" name="kelas_merek" class="form-control mt-2" placeholder="Tentukan Kelas Merek" required>
                                </div>

                                <!-- Modal Sistem Klasifikasi Merek -->
                                <div class="modal fade" id="modalKlasifikasiMerek" tabindex="-1" aria-labelledby="modalKlasifikasiMerekLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalKlasifikasiMerekLabel">
                                                    <i class="fas fa-book me-2"></i>Sistem Klasifikasi Merek
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-0" style="height: 65vh;">
                                                <iframe src="https://skm.dgip.go.id/" class="w-100 h-100" frameborder="0" title="Sistem Klasifikasi Merek"></iframe>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-2"></i>Tutup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Upload Files -->
                                <h5>Lampiran Dokumen</h5>
                                <hr class="border-2 border-secondary w-100">

                                <!-- NIB -->
                                <div class="mb-3">
                                    <label class="form-label">Izin Usaha (NIB RBA Berbasis Risiko) <span class="text-danger">*</span></label>
                                    <div class="file-drop-zone" id="nibDropZone">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                        <small>Upload maksimal 5 file (PDF). Maks 5 MB per file</small>
                                        <input type="file" name="nib[]" id="nib-file" accept=".pdf" multiple hidden>
                                    </div>
                                    <div class="preview-container" id="nibPreview"></div>
                                </div>

                                <!-- Upload Akta (Conditional for Perusahaan) -->
                                <div class="mb-3" id="aktaWrapper" style="display: none;">
                                    <label class="form-label">Akta Pendirian CV/PT <span class="text-danger">*</span></label>
                                    <div class="alert alert-info" style="font-size: 0.85rem; border-left: 4px solid #0d6efd; background-color: rgba(13, 110, 253, 0.05);">
                                        <i class="bi bi-lightbulb me-2" style="color: #0d6efd; font-weight: bold;"></i>
                                        <strong>Info Penting:</strong> Wajib upload Akta Pendirian untuk jenis pemohon Perusahaan (CV/PT)
                                    </div>
                                    <div class="file-drop-zone" id="aktaDropZone">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                        <small>Format: PDF | Ukuran max: 5MB</small>
                                        <input type="file" name="akta" id="akta-file" accept=".pdf" hidden>
                                    </div>
                                    <div class="preview-container" id="aktaPreview"></div>
                                </div>

                                <!-- Logo Merek -->
                                <div class="mb-3">
                                    <label class="form-label">Logo Merek <span class="text-danger">*</span></label>
                                    <div class="file-drop-zone" id="logoDropZone">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                        <small>Format: JPG, PNG, GIF | Ukuran max: 5MB</small>
                                        <input type="file" name="logo_merek" id="logo-file" accept=".jpg,.jpeg,.png,.gif" hidden>
                                    </div>
                                    <div class="preview-container" id="logoPreview"></div>
                                </div>

                                <!-- Foto Produk -->
                                <div class="mb-3">
                                    <label class="form-label">Foto Produk <span class="text-danger">*</span></label>
                                    <div class="file-drop-zone" id="produkDropZone">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                        <small>Upload maksimal 5 file (JPG/PNG/GIF). Maks 5 MB per file</small>
                                        <input type="file" name="foto_produk[]" id="produk-file" accept=".jpg,.jpeg,.png,.gif" multiple hidden>
                                    </div>
                                    <div class="preview-container" id="produkPreview"></div>
                                </div>

                                <!-- Foto Proses Produksi -->
                                <div class="mb-3">
                                    <label class="form-label">Foto Proses Produksi <span class="text-danger">*</span></label>
                                    <div class="file-drop-zone" id="prosesDropZone">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                        <small>Upload maksimal 5 file (JPG/PNG/GIF). Maks 5 MB per file</small>
                                        <input type="file" name="foto_proses[]" id="proses-file" accept=".jpg,.jpeg,.png,.gif" multiple hidden>
                                    </div>
                                    <div class="preview-container" id="prosesPreview"></div>
                                </div>

                                <!-- Tanda Tangan Digital -->
                                <h5>Tanda Tangan Digital</h5>
                                <hr class="border-2 border-secondary w-100">

                                <div class="mb-3">
                                    <label class="form-label">Tanda Tangan Pemilik <span class="text-danger">*</span></label>
                                    <div class="card-body p-0">
                                        <canvas id="signaturePad" style="display: block; width: 100%; border: 2px solid #ddd; border-radius: 4px; background-color: #fff; cursor: crosshair; touch-action: none;"></canvas>
                                        <small class="text-muted d-block p-3 mb-0">Tanda tangani di area di atas menggunakan mouse atau layar sentuh</small>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnClearSignature">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Hapus Tanda Tangan
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" id="btnGeneratePDF">
                                            <i class="fas fa-file-pdf me-1"></i>Simpan Tanda Tangan
                                        </button>
                                    </div>
                                    <div id="statusGeneratePDF" class="mt-2"></div>
                                </div>

                                <!-- Preview Surat Permohonan -->
                                <div class="mb-3" id="previewSuratContainer" style="display: none;">
                                    <label class="form-label">Preview Surat Permohonan</label>
                                    <div class="preview-container" id="previewSuratContent"></div>
                                </div>

                                <!-- Hidden input untuk menyimpan path PDF -->
                                <input type="hidden" name="suratpermohonan" id="suratpermohonanPath">

                                <!-- Submit -->
                                <div class="text-center">
                                    <div class="alert alert-warning d-inline-block mb-3" style="max-width: 600px;">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong>Perhatian:</strong> Dengan menekan tombol "Kirim Pengajuan", Anda menyatakan bahwa semua data yang diisi sudah benar dan lengkap. Data yang sudah dikirim tidak dapat diubah.
                                    </div>
                                    <br>
                                    <input type="hidden" name="submit_perpanjangan" value="1">
                                    <button type="submit" class="btn btn-submitpendaftaran" id="btnSubmit" disabled>
                                        <i class="fas fa-paper-plane pe-2"></i> Kirim Pengajuan
                                    </button>
                                    <br><br>
                                    <a href="pengajuan-surat-keterangan.php" class="btn btn-outline-dark">
                                        <i class="bi bi-arrow-left me-2"></i> Kembali
                                    </a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Submit -->
    <div class="modal fade" id="modalKonfirmasiSubmit" tabindex="-1" aria-labelledby="modalKonfirmasiSubmitLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning bg-opacity-10">
                    <h5 class="modal-title" id="modalKonfirmasiSubmitLabel">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                        Konfirmasi Pengiriman
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Apakah Anda yakin ingin mengirim pengajuan perpanjangan ini?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Perhatian:</strong> Data yang sudah dikirim tidak dapat diubah atau dibatalkan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Batal
                    </button>
                    <button type="button" class="btn btn-primary" id="btnKonfirmasiYa">
                        <i class="bi bi-check-circle me-2"></i>Ya, Kirim Sekarang
                    </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/preview-file-mandiri.js"></script>
    <script>
        // Data kelurahan/desa (sama seperti form-pendaftaran-mandiri.php)
        const desaKelurahan = {
            "Sidoarjo": ["Sidoarjo", "Lemahputro", "Magersari", "Gebang", "Celep", "Bulusidokare", "Urangagung", "Banjarbendo", "Blurukidul", "Cemengbakalan", "Jati", "Kemiri", "Lebo", "Rangkalkidul", "Sarirogo", "Suko", "Sumput", "Cemengkalan", "Pekawuman", "Pucang", "Pucanganom", "Sekardangan", "Sidoklumpuk", "Sidokumpul"],
            "Buduran": ["Buduran", "Sawohan", "Siwalanpanji", "Prasung", "Banjarkemantren", "Banjarsari", "Damarsi", "Dukuhtengah", "Entalsewu", "Pagerwojo", "Sidokerto", "Sidomulyo", "Sidokepung", "Sukorejo", "Wadungasin"],
            "Candi": ["Candi", "Durungbanjar", "Larangan", "Sumokali", "Sepande", "Kebonsari", "Kedensari", "Bligo", "Balongdowo", "Balonggabus", "Durungbanjar", "Durungbedug", "Gelam", "Jambangan", "Kalipecabean", "Karangtanjung", "Kebonsari", "Kedungkendo", "Kedungpeluk", "Kendalpencabean", "Klurak", "Ngampelsari", "Sidodadi", "Sugihwaras", "Sumorame", "Tenggulunan", "Wedoroklurak"],
            "Porong": ["Porong", "Kebonagung", "Kesambi", "Plumbon", "Pesawahan", "Gedang", "Juwetkenongo", "Kedungboto", "Wunut", "Pamotan", "Kebakalan", "Gempol Pasargi", "Glagaharum", "Lajuk", "Candipari"],
            "Krembung": ["Krembung", "Balanggarut", "Cangkring", "Gading", "Jenggot", "Kandangan", "Kedungrawan", "Kedungsumur", "Keperkeret", "Lemujut", "Ploso", "Rejeni", "Tambakrejo", "Tanjekwagir", "Wangkal", "Wonomlati", "Waung", "Mojoruntut"],
            "Tulangan": ["Tulangan", "Jiken", "Kajeksan", "Kebaran", "Kedondong", "Kepatihan", "Kepunten", "Medalem", "Pangkemiri", "Sudimoro", "Tlasih", "Gelang", "Kepadangan", "Grabagan", "Singopadu", "Kemantren", "Janti", "Modong", "Grogol", "Kenongo", "Grinting"],
            "Tanggulangin": ["kalisampurno", "kedensari", "Ganggang Pnjang", "Randegan", "Kalitengah", "Kedung Banteng", "Putat", "Ketapang", "Kalidawir", "Ketegan", "Banjar Panji", "Gempolsari", "Sentul", "Penatarsewu", "Banjarsari", "Ngaban", "Boro", "Kludan"],
            "Jabon": ["Trompoasri", "Kedung Pandan", "Permisan", "Semambung", "Pangrih", "Kupang", "Tambak Kalisogo", "Kedungrejo", "Kedungcangkring", "Keboguyang", "Jemirahan", "Balongtani", "dukuhsari"],
            "Krian": ["Sidomojo", "Sidomulyo", "Sidorejo", "Tempel", "Terik", "Terungkulon", "Terungwetan", "Tropodo", "Watugolong", "Krian", "Kemasan", "Tambakkemeraan", "Sedenganmijen", "Bareng Krajan", "Keraton", "Keboharan", "Katerungan", "Jeruk Gamping", "Junwangi", "Jatikalang", "Gamping", "Ponokawan"],
            "Balongbendo": ["Balongbendo", "WonoKupang", "Kedungsukodani", "Kemangsen", "Penambangan", "Seduri", "Seketi", "Singkalan", "SumoKembangsri", "Waruberon", "Watesari", "Wonokarang", "Jeruklegi", "Jabaran", "Suwaluh", "Gadungkepuhsari", "Bogempinggir", "Bakungtemenggungan", "Bakungpringgodani", "Wringinpitu", "Bakalan"],
            "Wonoayu": ["Becirongengor", "Candinegoro", "Jimbaran Kulon", "Jimbaran wetan", "Pilang", "Karangturi", "Ketimang", "Lambangan", "Mohorangagung", "Mulyodadi", "Pagerngumbuk", "Plaosan", "Ploso", "Popoh", "Sawocangkring", "semambung", "Simoangin-angin", "Simoketawang", "Sumberejo", "Tanggul", "Wonoayu", "Wonokalang", "Wonokasian"],
            "Tarik": ["Tarik", "Klantingsari", "GedangKlutuk", "Mergosari", "Kedinding", "Kemuning", "Janti", "Mergobener", "Mliriprowo", "Singogalih", "Kramat Temenggung", "Kedungbocok", "Segodobancang", "Gampingrowo", "Mindugading", "Kalimati", "Banjarwungu", "Balongmacekan", "Kendalsewu", "Sebani"],
            "Prambon": ["Prambon", "Bendotretek", "Bulang", "Cangkringturi", "Gampang", "Gedangrowo", "Jati alun-alun", "Watutulis", "jatikalang", "jedongcangkring", "Kajartengguli", "Kedungkembanr", "Kedung Sugo", "Kedungwonokerto", "Penjangkkungan", "Simogirang", "Simpang", "Temu", "Wirobiting", "Wonoplintahan"],
            "Taman": ["Taman", "Trosobo", "Sepanjang", "Ngelom", "Ketegan", "Jemundo", "Geluran", "Wage", "Bebekan", "Kalijaten", "Tawangsari", "Sidodadi", "Sambibulu", "Sadang", "Maduretno", "Krembangan", "Pertapan", "Kramatjegu", "Kletek", "Tanjungsari", "Kedungturi", "Gilang", "Bringinbendo", "Bohar", "Wonocolo"],
            "Waru": ["Waru", "Tropodo", "Kureksari", "Jambangan", "Medaeng", "Berbek", "Bungurasih", "Janti", "Kedungrejo", "Kepuhkiriman", "Ngingas", "Pepelegi", "Tambakoso", "Tambakrejo", "Tambahsawah", "Tambaksumur", "Wadungasri", "Wedoro"],
            "Gedangan": ["Gedangan", "Ketajen", "Wedi", "Bangah", "Sawotratap", "Semambung", "Ganting", "Tebel", "Kebonanom", "Gemurung", "Karangbong", "Kebiansikep", "Kragan", "Punggul", "Seruni"],
            "Sedati": ["Sedati", "Pabean", "Semampir", "Banjarkemuningtambak", "Pulungan", "Betro", "Segoro Tambak", "Gisik Cemandi", "Cemandi", "Kalanganyar", "Buncitan", "Wangsan", "Pranti", "Pepe", "Sedatiagung", "Sedatigede", "Tambakcemandi"],
            "Sukodono": ["Sukodono", "Jumputrejo", "Kebonagung", "Keloposepuluh", "Jogosatru", "Suruh", "Ngaresrejo", "Cangkringsari", "Masangan Wetan", "Masangan Kulon", "Bangsri", "Anggaswangi", "Pandemonegoro", "Panjunan", "Pekarungan", "Plumbungan", "Sambungrejo", "Suko", "Wilayut"]
        };

        // Kecamatan - Kelurahan/Desa Dropdown
        const kecamatanSelect = document.getElementById('kecamatan_usaha');
        const kelDesaSelect = document.getElementById('kel_desa_usaha');

        kecamatanSelect.addEventListener('change', function() {
            const kecamatan = this.value;
            kelDesaSelect.innerHTML = '<option value="">-- Pilih Kelurahan/Desa --</option>';

            if (kecamatan && desaKelurahan[kecamatan]) {
                desaKelurahan[kecamatan].forEach(function(desa) {
                    const option = document.createElement('option');
                    option.value = desa;
                    option.textContent = desa;
                    kelDesaSelect.appendChild(option);
                });
                kelDesaSelect.disabled = false;
            } else {
                kelDesaSelect.disabled = true;
            }
        });

        // RT/RW Formatting
        document.getElementById('rt_rw_usaha').addEventListener('input', function(e) {
            let value = this.value;
            value = value.replace(/[^\d\/]/g, '');
            const slashCount = (value.match(/\//g) || []).length;
            if (slashCount > 1) {
                value = value.substring(0, value.lastIndexOf('/'));
            }
            this.value = value;
        });

        document.getElementById('rt_rw_usaha').addEventListener('blur', function() {
            let value = this.value.trim();
            if (!value) return;

            let parts = value.split('/');
            let rt = parts[0] ? parts[0].replace(/\D/g, '') : '';
            let rw = parts[1] ? parts[1].replace(/\D/g, '') : '';

            if (rt) {
                rt = rt.substring(0, 3).padStart(3, '0');
                rw = rw ? rw.substring(0, 3).padStart(3, '0') : '001';
                this.value = rt + '/' + rw;
            } else {
                this.value = '';
            }
        });

        // Pilihan data sebelumnya
        document.querySelectorAll('.riwayat-card').forEach(card => {
            card.addEventListener('click', async function() {
                // Cek apakah card ini ditolak (tidak punya data-type)
                if (!this.hasAttribute('data-type')) {
                    showAlert('Pendaftaran merek ini ditolak oleh Kementerian dan tidak dapat digunakan untuk perpanjangan.', 'warning');
                    return;
                }

                const type = this.dataset.type;
                const id = this.dataset.id;

                // Update selected indicator
                document.querySelectorAll('.riwayat-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');

                // Set hidden inputs
                document.getElementById('selectedDataType').value = type;
                document.getElementById('selectedDataId').value = id;

                // Load data dari server
                await loadDataFromServer(type, id);

                // Tampilkan form section
                document.getElementById('formSection').style.display = 'block';
            });
        });

        // Load data dari server
        async function loadDataFromServer(type, id) {
            try {
                const response = await fetch('process/get_perpanjangan_data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type: type,
                        id: id,
                        nik: document.getElementById('userNikData').value
                    })
                });

                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    // Cek dan isi form fields dengan null check
                    const setValueSafe = (selector, value) => {
                        const element = document.querySelector(selector);
                        if (element) {
                            element.value = value || '';
                        } else {
                            console.error('Element not found:', selector);
                        }
                    };

                    setValueSafe('[name="nama_usaha"]', data.nama_usaha);
                    setValueSafe('#no_telp_perusahaan', data.no_telp_perusahaan);
                    setValueSafe('[name="produk"]', data.produk);
                    setValueSafe('[name="jml_tenaga_kerja"]', data.jml_tenaga_kerja);
                    setValueSafe('[name="merek"]', data.merek);
                    setValueSafe('[name="kelas_merek"]', data.kelas_merek);
                    setValueSafe('#rt_rw_usaha', data.rt_rw);


                    // Set jenis usaha dengan trigger untuk field "lainnya"
                    const jenisUsahaSelect = document.querySelector('[name="jenis_usaha"]');
                    if (jenisUsahaSelect && data.jenis_usaha) {
                        // Cek apakah jenis usaha ada di dropdown
                        const optionExists = Array.from(jenisUsahaSelect.options).some(
                            option => option.value === data.jenis_usaha
                        );

                        if (optionExists) {
                            jenisUsahaSelect.value = data.jenis_usaha;
                        } else {
                            // Jika tidak ada, set ke "lainnya" dan isi field lainnya
                            jenisUsahaSelect.value = 'lainnya';
                            jenisUsahaSelect.dispatchEvent(new Event('change'));

                            setTimeout(() => {
                                const jenisLainnyaInput = document.getElementById('jenis_usaha_lainnya');
                                if (jenisLainnyaInput) {
                                    jenisLainnyaInput.value = data.jenis_usaha;
                                }
                            }, 100);
                        }
                    } else {
                        // Jika jenis_usaha kosong/null, set dropdown ke default (option pertama/kosong)
                        if (jenisUsahaSelect) {
                            jenisUsahaSelect.selectedIndex = 0;
                        }
                    }

                    // Set kecamatan dan trigger change event
                    const kecamatanSelect = document.getElementById('kecamatan_usaha');
                    const kelDesaSelect = document.getElementById('kel_desa_usaha');

                    if (kecamatanSelect && data.kecamatan) {
                        kecamatanSelect.value = data.kecamatan;
                        kecamatanSelect.dispatchEvent(new Event('change'));

                        // Set kelurahan setelah kecamatan di-trigger
                        setTimeout(() => {
                            if (kelDesaSelect && data.kel_desa) {
                                kelDesaSelect.value = data.kel_desa;
                            }
                        }, 100);
                    }


                    // Set jenis pemohon dan profil perusahaan
                    if (data.jenis_pemohon) {
                        const radioPemohon = document.querySelector(`input[name="jenis_pemohon"][value="${data.jenis_pemohon}"]`);
                        if (radioPemohon) {
                            radioPemohon.checked = true;
                            radioPemohon.dispatchEvent(new Event('change'));
                        }

                        if (data.jenis_pemohon === 'perusahaan') {
                            setTimeout(() => {
                                setValueSafe('#nama_perusahaan_perpanjangan', data.nama_perusahaan);
                                setValueSafe('#alamat_perusahaan_perpanjangan', data.alamat_perusahaan);
                                setValueSafe('#no_telp_kop_perpanjangan', data.no_telp_perusahaan);
                                setValueSafe('#email_perusahaan_perpanjangan', data.email_perusahaan);
                            }, 100);
                        }
                    } else {
                        // Fallback: Jika tidak ada jenis_pemohon di data, cek dari field lain
                        // Jika ada nama_perusahaan atau logo_perusahaan, anggap sebagai perusahaan
                        if (data.nama_perusahaan || data.logo_perusahaan) {
                            const radioPerusahaan = document.querySelector('input[name="jenis_pemohon"][value="perusahaan"]');
                            if (radioPerusahaan) {
                                radioPerusahaan.checked = true;
                                radioPerusahaan.dispatchEvent(new Event('change'));

                                setTimeout(() => {
                                    setValueSafe('#nama_perusahaan_perpanjangan', data.nama_perusahaan);
                                    setValueSafe('#alamat_perusahaan_perpanjangan', data.alamat_perusahaan);
                                    setValueSafe('#no_telp_kop_perpanjangan', data.no_telp_perusahaan);
                                    setValueSafe('#email_perusahaan_perpanjangan', data.email_perusahaan);
                                }, 100);
                            }
                        }
                    }
                    // Set id_pendaftaran jika ada
                    if (type === 'pendaftaran') {
                        setValueSafe('#id_pendaftaran', data.id_pendaftaran);
                    }

                    // Load file lampiran jika ada
                    if (data.lampiran) {
                        await loadLampiranFiles(data.lampiran);
                    }

                    showAlert('Data berhasil dimuat. Silakan periksa dan perbarui jika diperlukan.', 'success');
                } else {
                    showAlert(result.message || 'Gagal memuat data', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Terjadi kesalahan: ' + error.message, 'danger');
            }
        }

        // Load lampiran files
        async function loadLampiranFiles(lampiran) {
            const fileMapping = {
                logo: lampiran.logo || [],
                logo_perusahaan: lampiran.logo_perusahaan || [],
                nib: lampiran.nib || [],
                produk: lampiran.produk || [],
                proses: lampiran.proses || [],
                akta: lampiran.akta || []
            };

            // Simpan path akta lama jika ada
            if (lampiran.akta && lampiran.akta.length > 0) {
                let hiddenAktaInput = document.querySelector('input[name="akta_path_old"]');
                if (!hiddenAktaInput) {
                    hiddenAktaInput = document.createElement('input');
                    hiddenAktaInput.type = 'hidden';
                    hiddenAktaInput.name = 'akta_path_old';
                    document.getElementById('formPerpanjangan').appendChild(hiddenAktaInput);
                }
                hiddenAktaInput.value = lampiran.akta[0];
            }

            // Clear existing files
            for (let type in uploadedFilesStore) {
                uploadedFilesStore[type] = [];
            }

            // Load each file type
            for (let type in fileMapping) {
                if (fileMapping[type].length > 0) {
                    for (let filePath of fileMapping[type]) {
                        try {
                            const response = await fetch(filePath);
                            const blob = await response.blob();
                            const filename = filePath.split('/').pop();
                            const file = new File([blob], filename, {
                                type: blob.type
                            });
                            uploadedFilesStore[type].push(file);
                        } catch (error) {
                            console.error('Error loading file:', filePath, error);
                        }
                    }
                }
            }

            // Update preview
            updateFileInputWithDataTransfer('logo');
            renderPreviewItems('logo', 'logoPreview');

            updateFileInputWithDataTransfer('nib');
            renderPreviewItems('nib', 'nibPreview');

            updateFileInputWithDataTransfer('produk');
            renderPreviewItems('produk', 'produkPreview');

            updateFileInputWithDataTransfer('proses');
            renderPreviewItems('proses', 'prosesPreview');

            updateFileInputWithDataTransfer('akta');
            renderPreviewItems('akta', 'aktaPreview');

            updateFileInputWithDataTransfer('logo_perusahaan');
            renderPreviewItems('logo_perusahaan', 'logoPerusahaanPreview');
        }

        // Handle jenis usaha "Lainnya"
        const jenis_usaha_select = document.getElementById('jenis_usaha');
        const jenis_usaha_lainnya_field = document.getElementById('jenis_usaha_lainnya_field');
        const jenis_usaha_lainnya_input = document.getElementById('jenis_usaha_lainnya');

        jenis_usaha_select.addEventListener('change', function() {
            if (this.value === 'lainnya') {
                jenis_usaha_lainnya_field.style.display = 'block';
                jenis_usaha_lainnya_input.required = true;
            } else {
                jenis_usaha_lainnya_field.style.display = 'none';
                jenis_usaha_lainnya_input.required = false;
                jenis_usaha_lainnya_input.value = '';
            }
        });

        const noTelpPerusahaanInput = document.getElementById('no_telp_perusahaan');
        if (noTelpPerusahaanInput) {
            noTelpPerusahaanInput.addEventListener('blur', function() {
                let value = this.value.trim();
                value = value.replace(/\D/g, '');

                if (value.startsWith('0')) {
                    value = '62' + value.substring(1);
                    this.value = value;
                } else if (value.length > 0 && !value.startsWith('62')) {
                    value = '62' + value;
                    this.value = value;
                }
            });
        }

        // Auto convert untuk no_telp_kop
        const noTelpKopInput = document.getElementById('no_telp_kop_perpanjangan');
        if (noTelpKopInput) {
            noTelpKopInput.addEventListener('blur', function() {
                let value = this.value.trim();
                value = value.replace(/\D/g, '');

                if (value.startsWith('0')) {
                    value = '62' + value.substring(1);
                    this.value = value;
                } else if (value.length > 0 && !value.startsWith('62')) {
                    value = '62' + value;
                    this.value = value;
                }
            });
        }


        // Form submission
        document.getElementById('formPerpanjangan').addEventListener('submit', function(e) {
            e.preventDefault();

            // Validasi file uploads
            const fileValidations = [{
                    store: uploadedFilesStore.logo,
                    name: 'Logo Merek'
                },
                {
                    store: uploadedFilesStore.nib,
                    name: 'NIB/RBA'
                },
                {
                    store: uploadedFilesStore.produk,
                    name: 'Foto Produk'
                },
                {
                    store: uploadedFilesStore.proses,
                    name: 'Foto Proses Produksi'
                }
            ];

            const missingFiles = [];
            fileValidations.forEach(validation => {
                if (validation.store.length === 0) {
                    missingFiles.push(validation.name);
                }
            });

            // Cek akta jika CV/PT
            const jenisUsaha = document.querySelector('[name="jenis_usaha"]')?.value;
            console.log('Jenis usaha:', jenisUsaha);

            if (jenisUsaha === 'cv' || jenisUsaha === 'pt') {
                const aktaPathOld = document.querySelector('input[name="akta_path_old"]');
                const hasAktaFile = uploadedFilesStore.akta.length > 0;
                const hasAktaOld = aktaPathOld && aktaPathOld.value;

                console.log('Akta validation - hasAktaFile:', hasAktaFile, 'hasAktaOld:', hasAktaOld);
                console.log('Akta old path value:', aktaPathOld?.value);

                if (!hasAktaFile && !hasAktaOld) {
                    missingFiles.push('Akta Pendirian CV/PT');
                }
            }

            if (missingFiles.length > 0) {
                showAlert(`Mohon upload file berikut:<br><br>${missingFiles.join('<br>')}`, 'warning');
                return;
            }

            // Cek surat permohonan
            const suratPath = document.getElementById('suratpermohonanPath').value;
            if (!suratPath) {
                showAlert('Mohon generate Surat Permohonan terlebih dahulu dengan menekan tombol "Simpan Tanda Tangan"', 'warning');
                document.getElementById('signaturePad').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return;
            }

            // Tampilkan modal konfirmasi
            const modalKonfirmasi = new bootstrap.Modal(document.getElementById('modalKonfirmasiSubmit'));
            modalKonfirmasi.show();
        });

        // Handle konfirmasi submit
        document.getElementById('btnKonfirmasiYa').addEventListener('click', function() {
            const modalKonfirmasi = bootstrap.Modal.getInstance(document.getElementById('modalKonfirmasiSubmit'));
            modalKonfirmasi.hide();

            // Kirim form
            const form = document.getElementById('formPerpanjangan');
            const formData = new FormData();

            // Tambah semua input
            const formInputs = form.querySelectorAll('input:not([type="file"]), select, textarea');
            formInputs.forEach(input => {
                if (input.name && input.value) {
                    formData.append(input.name, input.value);
                }
            });

            // Tambah files
            if (uploadedFilesStore.logo.length > 0) {
                formData.append('logo_merek', uploadedFilesStore.logo[0]);
            }

            uploadedFilesStore.nib.forEach((file, index) => {
                formData.append('nib[]', file);
            });

            uploadedFilesStore.produk.forEach((file, index) => {
                formData.append('foto_produk[]', file);
            });

            uploadedFilesStore.proses.forEach((file, index) => {
                formData.append('foto_proses[]', file);
            });

            // Tambahkan akta jika ada
            if (uploadedFilesStore.akta.length > 0) {
                console.log('Uploading new akta file:', uploadedFilesStore.akta[0].name);
                formData.append('akta', uploadedFilesStore.akta[0]);
            } else {
                // Jika tidak ada file baru tapi ada akta lama dari data sebelumnya
                const aktaPathOld = document.querySelector('input[name="akta_path_old"]');
                if (aktaPathOld && aktaPathOld.value) {
                    console.log('Using old akta path:', aktaPathOld.value);
                    formData.append('akta_path_old', aktaPathOld.value);
                }
            }

            // Tambahkan jenis pemohon
            const jenisPemohonChecked = document.querySelector('input[name="jenis_pemohon"]:checked');
            if (jenisPemohonChecked) {
                formData.append('jenis_pemohon', jenisPemohonChecked.value);

                // Jika perusahaan, tambahkan data profil perusahaan
                if (jenisPemohonChecked.value === 'perusahaan') {
                    formData.append('nama_perusahaan', document.getElementById('nama_perusahaan_perpanjangan').value || '');
                    formData.append('alamat_perusahaan', document.getElementById('alamat_perusahaan_perpanjangan').value || '');
                    formData.append('no_telp_kop', document.getElementById('no_telp_kop_perpanjangan').value || '');
                    formData.append('email_perusahaan', document.getElementById('email_perusahaan_perpanjangan').value || '');

                    // Logo perusahaan
                    if (uploadedFilesStore.logo_perusahaan.length > 0) {
                        formData.append('logo_perusahaan', uploadedFilesStore.logo_perusahaan[0]);
                    }
                }
            }

            const noTelpInput = document.getElementById('no_telp_perusahaan');
            if (noTelpInput && noTelpInput.value) {
                formData.append('no_telp_perusahaan', noTelpInput.value);
            }

            // Tambahkan submit flag
            formData.append('submit_perpanjangan', '1');
            const btnSubmit = document.getElementById('btnSubmit');
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin pe-2"></i>Mengirim...';

            // Debug: Log formData contents
            console.log('FormData contents:');
            for (let [key, value] of formData.entries()) {
                if (value instanceof File) {
                    console.log(key, ':', value.name, '(', value.size, 'bytes)');
                } else {
                    console.log(key, ':', value);
                }
            }

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        return response.text();
                    }
                })
                .then(html => {
                    if (html) {
                        document.open();
                        document.write(html);
                        document.close();
                    }
                })
                .catch(error => {
                    console.error('Submit error:', error);
                    showAlert('Terjadi kesalahan: ' + error.message, 'danger');
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="fas fa-paper-plane pe-2"></i> Kirim Pengajuan';
                });

        });

        // Show alert helper
        function showAlert(message, type = 'warning') {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = 'alert-' + Date.now();

            const iconMap = {
                'success': 'bi-check-circle-fill',
                'danger': 'bi-exclamation-circle-fill',
                'warning': 'bi-exclamation-triangle-fill',
                'info': 'bi-info-circle-fill'
            };

            const icon = iconMap[type] || iconMap['warning'];

            const alertHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
                    <i class="bi ${icon} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;

            alertContainer.innerHTML = alertHTML;
            alertContainer.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            setTimeout(() => {
                const alertElement = document.getElementById(alertId);
                if (alertElement) {
                    const bsAlert = new bootstrap.Alert(alertElement);
                    bsAlert.close();
                }
            }, 5000);
        }

        // Handle Radio Button Jenis Pemohon untuk Perpanjangan
        const radioPemohonPerpanjangan = document.querySelectorAll('input[name="jenis_pemohon"]');
        const profilPerusahaanSection = document.getElementById('profilPerusahaanSection');

        radioPemohonPerpanjangan.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'perusahaan') {
                    profilPerusahaanSection.style.display = 'block';

                    // Set required
                    document.getElementById('nama_perusahaan_perpanjangan').required = true;
                    document.getElementById('alamat_perusahaan_perpanjangan').required = true;
                    document.getElementById('no_telp_kop_perpanjangan').required = true;
                    document.getElementById('email_perusahaan_perpanjangan').required = true;
                } else {
                    profilPerusahaanSection.style.display = 'none';

                    // Remove required
                    document.getElementById('nama_perusahaan_perpanjangan').required = false;
                    document.getElementById('alamat_perusahaan_perpanjangan').required = false;
                    document.getElementById('no_telp_kop_perpanjangan').required = false;
                    document.getElementById('email_perusahaan_perpanjangan').required = false;

                    // Clear data
                    document.getElementById('nama_perusahaan_perpanjangan').value = '';
                    document.getElementById('alamat_perusahaan_perpanjangan').value = '';
                    document.getElementById('no_telp_kop_perpanjangan').value = '';
                    document.getElementById('email_perusahaan_perpanjangan').value = '';
                    uploadedFilesStore.logo_perusahaan = [];
                    renderPreviewItems('logo_perusahaan', 'logoPerusahaanPreview');
                }
            });
        });
    </script>
</body>

</html>