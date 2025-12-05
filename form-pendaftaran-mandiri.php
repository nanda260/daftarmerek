<?php
session_start();
if (!isset($_SESSION['NIK_NIP']) || !isset($_SESSION['nama_lengkap'])) {
    header("Location: login.php");
    exit();
}

$nama = $_SESSION['nama_lengkap'];
$nik = $_SESSION['NIK_NIP'];



require_once 'process/config_db.php';

// Ambil data user dari tabel userr
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


// Ambil data usaha terakhir (jika sudah pernah mendaftar)
$data_usaha_existing = null;
$stmt_usaha = $pdo->prepare("
    SELECT du.* FROM datausaha du
    JOIN pendaftaran p ON p.id_usaha = du.id_usaha
    WHERE p.NIK = ?
    ORDER BY p.tgl_daftar DESC
    LIMIT 1
");
$stmt_usaha->execute([$nik]);
$data_usaha_existing = $stmt_usaha->fetch(PDO::FETCH_ASSOC);

// Ambil data merek terakhir (jika sudah pernah mendaftar)
$data_merek_existing = null;
$stmt_merek = $pdo->prepare("
    SELECT m.* FROM merek m
    JOIN pendaftaran p ON p.id_pendaftaran = m.id_pendaftaran
    WHERE p.NIK = ?
    ORDER BY p.tgl_daftar DESC
    LIMIT 1
");
$stmt_merek->execute([$nik]);
$data_merek_existing = $stmt_merek->fetch(PDO::FETCH_ASSOC);

// Ambil data pengajuan terakhir (jika sudah pernah mengajukan mandiri)
$data_pengajuan_existing = null;
$stmt_pengajuan = $pdo->prepare("
    SELECT no_telp_perusahaan FROM pengajuansurat
    WHERE NIK = ?
    ORDER BY tgl_daftar DESC
    LIMIT 1
");
$stmt_pengajuan->execute([$nik]);
$data_pengajuan_existing = $stmt_pengajuan->fetch(PDO::FETCH_ASSOC);

// Ambil contact person dari pengaturan
try {
    $stmt_contact = $pdo->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'contact_person'");
    $stmt_contact->execute();
    $contact_data = $stmt_contact->fetch(PDO::FETCH_ASSOC);
    $contact_person = $contact_data ? $contact_data['setting_value'] : '6281235051286';
} catch (PDOException $e) {
    error_log("Error fetching contact person: " . $e->getMessage());
    $contact_person = '6281235051286';
}

// Ambil jenis usaha dari pengaturan
$stmt_jenis_usaha = $pdo->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'jenis_usaha'");
$stmt_jenis_usaha->execute();
$jenis_usaha_data = $stmt_jenis_usaha->fetch(PDO::FETCH_ASSOC);
$jenis_usaha_list = $jenis_usaha_data ? json_decode($jenis_usaha_data['setting_value'], true) : [];

$error = '';
$success = '';

$nib_paths = [];
$nib_json = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_mandiri'])) {
    try {
        // Validasi input
        $nama_usaha = htmlspecialchars($_POST['nama_usaha'] ?? '', ENT_QUOTES);
        // Buat alamat_usaha dari komponen terpisah
        $kecamatan_usaha = htmlspecialchars($_POST['kecamatan_usaha'] ?? '', ENT_QUOTES);
        $rt_rw_usaha = htmlspecialchars($_POST['rt_rw_usaha'] ?? '', ENT_QUOTES);
        $kel_desa_usaha = htmlspecialchars($_POST['kel_desa_usaha'] ?? '', ENT_QUOTES);

        $alamat_usaha = '';
        if ($kel_desa_usaha) $alamat_usaha .= 'Desa/Kel. ' . $kel_desa_usaha . ', ';
        if ($rt_rw_usaha) $alamat_usaha .= 'RT/RW ' . $rt_rw_usaha . ', ';
        if ($kecamatan_usaha) $alamat_usaha .= 'Kecamatan ' . $kecamatan_usaha . ', ';
        $alamat_usaha .= 'SIDOARJO, JAWA TIMUR';

        $jenis_usaha = htmlspecialchars($_POST['jenis_usaha'] ?? '', ENT_QUOTES);
        $produk = htmlspecialchars($_POST['produk'] ?? '', ENT_QUOTES);
        $jml_tenaga_kerja = (int)($_POST['jml_tenaga_kerja'] ?? 0);
        $jenis_pemohon = htmlspecialchars($_POST['jenis_pemohon'] ?? 'perseorangan', ENT_QUOTES);
        $nama_perusahaan = null;
        $logo_perusahaan_path = null;
        $alamat_perusahaan = null;
        $email_perusahaan = null;
        $no_telp_kop = null;

        // Jika jenis pemohon adalah perusahaan
        if ($jenis_pemohon === 'perusahaan') {
            $nama_perusahaan = htmlspecialchars($_POST['nama_perusahaan'] ?? '', ENT_QUOTES);
            $alamat_perusahaan = htmlspecialchars($_POST['alamat_perusahaan'] ?? '', ENT_QUOTES);
            $email_perusahaan = htmlspecialchars($_POST['email_perusahaan'] ?? '', ENT_QUOTES);
            $no_telp_kop = htmlspecialchars($_POST['no_telp_kop'] ?? '', ENT_QUOTES);

            if (empty($nama_perusahaan) || empty($alamat_perusahaan) || empty($no_telp_kop) || empty($email_perusahaan)) {
                throw new Exception('Semua data profil perusahaan wajib diisi untuk jenis pemohon Perusahaan');
            }
        }
        $merek = htmlspecialchars($_POST['merek'] ?? '', ENT_QUOTES);
        $kelas_merek = htmlspecialchars($_POST['kelas_merek'] ?? '', ENT_QUOTES);

        // Validasi dasar
        if (
            empty($nama_usaha) || empty($alamat_usaha) ||
            empty($jenis_usaha) || empty($produk) || empty($merek) || empty($kelas_merek)
        ) {
            throw new Exception('Silakan isi semua field yang wajib (ditandai *)');
        }

        if ($jml_tenaga_kerja < 1) {
            throw new Exception('Jumlah tenaga kerja harus minimal 1 orang');
        }

        // Validasi email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format email tidak valid');
        }

        // Cek folder upload
        $upload_dir_logo = 'uploads/logo_mandiri/';
        $upload_dir_logo_perusahaan = 'uploads/logo_perusahaan_mandiri/';
        $upload_dir_nib = 'uploads/nib_mandiri/';
        $upload_dir_foto_produk = 'uploads/foto_produk_mandiri/';
        $upload_dir_foto_proses = 'uploads/foto_proses_mandiri/';
        $upload_dir_akta = 'uploads/akta_mandiri/';
        $upload_dir_suratpermohonan = 'uploads/suratpermohonanmandiri/';

        foreach ([$upload_dir_logo, $upload_dir_logo_perusahaan, $upload_dir_nib, $upload_dir_foto_produk, $upload_dir_foto_proses, $upload_dir_akta] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Handle uploads
        $logo_path = null;
        $nib_paths = [];
        $foto_produk_paths = [];
        $foto_proses_paths = [];
        $akta_path = null;
        $suratpermohonan_path = null;

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

        // Upload Logo Perusahaan (jika jenis pemohon = perusahaan)
        if ($jenis_pemohon === 'perusahaan') {
            if (isset($_FILES['logo_perusahaan']) && $_FILES['logo_perusahaan']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024;

                if (!in_array($_FILES['logo_perusahaan']['type'], $allowed_types)) {
                    throw new Exception('Format logo perusahaan harus JPG, PNG, atau GIF');
                }

                if ($_FILES['logo_perusahaan']['size'] > $max_size) {
                    throw new Exception('Ukuran logo perusahaan tidak boleh lebih dari 5MB');
                }

                $file_ext = pathinfo($_FILES['logo_perusahaan']['name'], PATHINFO_EXTENSION);
                $filename = 'logo_perusahaan_' . $nik . '_' . time() . '.' . $file_ext;
                $file_path = $upload_dir_logo_perusahaan . $filename;

                if (!move_uploaded_file($_FILES['logo_perusahaan']['tmp_name'], $file_path)) {
                    throw new Exception('Gagal mengupload logo perusahaan');
                }
                $logo_perusahaan_path = $file_path;
            } else {
                throw new Exception('Logo perusahaan wajib diupload untuk jenis pemohon Perusahaan');
            }
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


        // Upload Foto Produk (multiple)
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

        // Upload Foto Proses Produksi (multiple)
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

        // Upload Akta (optional, hanya untuk CV/PT)
        if ($jenis_pemohon === 'perusahaan') {
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
            } else {
                throw new Exception('Akta pendirian wajib diupload untuk jenis pemohon Perusahaan');
            }
        }

        // Ambil path surat permohonan dari hidden input (sudah di-generate sebelumnya)
        $suratpermohonan_path = htmlspecialchars($_POST['suratpermohonan'] ?? '', ENT_QUOTES);

        if (empty($suratpermohonan_path)) {
            throw new Exception('Surat permohonan belum di-generate. Mohon klik tombol "Simpan Tanda Tangan" terlebih dahulu.');
        }

        // Validasi file exists
        if (!file_exists($suratpermohonan_path)) {
            throw new Exception('File surat permohonan tidak ditemukan. Mohon generate ulang.');
        }

        // Insert ke tabel pengajuansurat
        $stmt_insert = $pdo->prepare("
     INSERT INTO pengajuansurat (
         NIK, tipe_pengajuan, nama_usaha, alamat_usaha, no_telp_perusahaan,
         nama_pemilik, alamat_pemilik, no_telp_pemilik, email,
         jenis_usaha, produk, jml_tenaga_kerja, jenis_pemohon, 
         nama_perusahaan, logo_perusahaan, alamat_perusahaan, email_perusahaan, no_telp_kop,
         merek, kelas_merek,
         logo_merek, nib_file, foto_produk, foto_proses, akta_file, suratpermohonan_file,
         status_validasi, tgl_daftar
     ) VALUES (
         ?, 'mandiri', ?, ?, ?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?, ?, ?, ?,
         'Menunggu Surat Terbit', NOW()
     )
 ");

        $nib_json = json_encode($nib_paths);
        $foto_produk_json = json_encode($foto_produk_paths);
        $foto_proses_json = json_encode($foto_proses_paths);

        $no_telp_perusahaan = htmlspecialchars($_POST['no_telp_perusahaan'] ?? '', ENT_QUOTES);

        $stmt_insert->execute([
            $nik,
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
            $email_perusahaan,
            $no_telp_kop,
            $merek,
            $kelas_merek,
            $logo_path,
            $nib_json,
            $foto_produk_json,
            $foto_proses_json,
            $akta_path,
            $suratpermohonan_path
        ]);
        $_SESSION['alert_message'] = "Pendaftaran mandiri berhasil diajukan!\n\nPermohonan Anda sedang diproses admin. Anda akan menerima notifikasi ketika status berubah.";
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
    <title>Pendaftaran Mandiri Surat Keterangan - Disperindag Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/form-pendaftaran.css">
    <link rel="stylesheet" href="assets/css/preview-file.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <style>
        /* Force button submit style */
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

        /* Profil Perusahaan Section */
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
                <h5 class="judul">Pendaftaran Surat Keterangan Mandiri</h5>
                <p>Lengkapi semua data usaha dan merek Anda dengan akurat. Data yang Anda isi akan divalidasi oleh admin sebelum surat keterangan diterbitkan.</p>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-info-square pe-2"></i>Informasi</h5>
                    <ul class="list-unstyled info-list">
                        <li><strong>Output:</strong> Surat Keterangan IKM untuk Pendaftaran Mandiri Merek</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-journal-check pe-2"></i>Dokumen yang Diperlukan</h5>
                    <ul class="info-list">
                        <li>Logo Merek (JPG/PNG/GIF)</li>
                        <li>NIB/RBA Berbasis Risiko (PDF/JPG/PNG)</li>
                        <li>Foto Produk (JPG/PNG/GIF)</li>
                        <li>Foto Proses Produksi (JPG/PNG/GIF)</li>
                        <li>Akta CV/PT (PDF) - jika ada</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle bg-warning bg-opacity-10">
                    <h5><i class="bi bi-exclamation-triangle pe-2"></i>Catatan Penting</h5>
                    <ul class="info-list">
                        <li>Pastikan semua data sudah benar sebelum submit</li>
                        <li>Admin akan memvalidasi dalam 3-5 hari kerja</li>
                        <li>File max 5MB per dokumen</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5>Bantuan</h5>
                    <p>Jika ada kendala dalam mengisi formulir bisa menghubungi kami dibawah ini.</p>
                    <a href="https://wa.me/<?php echo htmlspecialchars($contact_person); ?>?text=Halo%2C%20saya%20ingin%20bertanya%20mengenai%20layanan%20industri" class="help-contact" target="_blank">
                        <i class="fab fa-whatsapp pe-2"></i> Bidang Perindustrian Disperindag Sidoarjo
                    </a>
                    <p class="text-danger mt-2">* Tidak menerima panggilan, hanya chat.</p>
                </div>
            </div>

            <!-- Form Content -->
            <div class="col-lg-8">
                <div class="form-container border border-light-subtle">
                    <h4>Form Pendaftaran Surat Keterangan - Mandiri</h4>
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

                    <form method="POST" enctype="multipart/form-data" id="formMandiri">

                        <!-- Hidden input untuk menyimpan NIK dari session -->
                        <input type="hidden" id="userNikData" value="<?php echo htmlspecialchars($nik); ?>">

                        <!-- Data Usaha -->
                        <h5>Data Usaha</h5>
                        <hr class="border-2 border-secondary w-100">

                        <div class="mb-3">
                            <label class="form-label">Nama Usaha <span class="text-danger">*</span></label>
                            <input type="text" name="nama_usaha" class="form-control"
                                value="<?php echo htmlspecialchars($data_usaha_existing['nama_usaha'] ?? ''); ?>" required>
                        </div>

                        <div class="row">
                            <label class="form-label">Alamat Usaha</label>
                            <div class="mb-3">
                                <label class="form-label-alamat">Kecamatan <span class="text-danger">*</span></label>
                                <select name="kecamatan_usaha" id="kecamatan_usaha" class="form-control" required>
                                    <option value="">-Pilih Kecamatan-</option>
                                    <option value="Sidoarjo" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Sidoarjo') ? 'selected' : ''; ?>>Sidoarjo</option>
                                    <option value="Buduran" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Buduran') ? 'selected' : ''; ?>>Buduran</option>
                                    <option value="Candi" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Candi') ? 'selected' : ''; ?>>Candi</option>
                                    <option value="Porong" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Porong') ? 'selected' : ''; ?>>Porong</option>
                                    <option value="Krembung" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Krembung') ? 'selected' : ''; ?>>Krembung</option>
                                    <option value="Tulangan" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Tulangan') ? 'selected' : ''; ?>>Tulangan</option>
                                    <option value="Tanggulangin" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Tanggulangin') ? 'selected' : ''; ?>>Tanggulangin</option>
                                    <option value="Jabon" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Jabon') ? 'selected' : ''; ?>>Jabon</option>
                                    <option value="Krian" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Krian') ? 'selected' : ''; ?>>Krian</option>
                                    <option value="Balongbendo" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Balongbendo') ? 'selected' : ''; ?>>Balongbendo</option>
                                    <option value="Wonoayu" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Wonoayu') ? 'selected' : ''; ?>>Wonoayu</option>
                                    <option value="Tarik" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Tarik') ? 'selected' : ''; ?>>Tarik</option>
                                    <option value="Prambon" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Prambon') ? 'selected' : ''; ?>>Prambon</option>
                                    <option value="Taman" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Taman') ? 'selected' : ''; ?>>Taman</option>
                                    <option value="Waru" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Waru') ? 'selected' : ''; ?>>Waru</option>
                                    <option value="Gedangan" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Gedangan') ? 'selected' : ''; ?>>Gedangan</option>
                                    <option value="Sedati" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Sedati') ? 'selected' : ''; ?>>Sedati</option>
                                    <option value="Sukodono" <?php echo (isset($data_usaha_existing['kecamatan']) && $data_usaha_existing['kecamatan'] == 'Sukodono') ? 'selected' : ''; ?>>Sukodono</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-alamat">RT/RW <span class="text-danger">*</span></label>
                                <input type="text" name="rt_rw_usaha" id="rt_rw_usaha" class="form-control"
                                    placeholder="Contoh: 003/005"
                                    value="<?php echo htmlspecialchars($data_usaha_existing['rt_rw'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-alamat">Kelurahan/Desa <span class="text-danger">*</span></label>
                                <select name="kel_desa_usaha" id="kel_desa_usaha" class="form-control" required>
                                    <option value="">-Pilih Kecamatan Terlebih Dahulu-</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No. Telepon Usaha <span class="text-danger">*</span></label>
                                <input type="tel" name="no_telp_perusahaan" id="no_telp_perusahaan" class="form-control"
                                    value="<?php echo htmlspecialchars($data_usaha_existing['no_telp_perusahaan'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <!-- Data Usaha Detail -->
                        <h5>Detail Usaha</h5>
                        <hr class="border-2 border-secondary w-100">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Usaha <span class="text-danger">*</span></label>
                                <select name="jenis_usaha" class="form-select" id="jenis_usaha" required>
                                    <option value="">Pilih Jenis Usaha</option>
                                    <?php foreach ($jenis_usaha_list as $jenis): ?>
                                        <option value="<?php echo htmlspecialchars($jenis); ?>">
                                            <?php echo htmlspecialchars($jenis); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Produk <span class="text-danger">*</span></label>
                                <input type="text" name="produk" class="form-control" placeholder="Produk yang dihasilkan" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jumlah Tenaga Kerja <span class="text-danger">*</span></label>
                            <input type="number" name="jml_tenaga_kerja" class="form-control" min="1" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jenis Pemohon <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="jenis_pemohon" id="perseorangan" value="perseorangan" checked>
                                    <label class="form-check-label" for="perseorangan">
                                        Perseorangan
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="jenis_pemohon" id="perusahaan" value="perusahaan">
                                    <label class="form-check-label" for="perusahaan">
                                        Perusahaan (CV/PT)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Profil Perusahaan (Hidden by default) -->
                        <div id="profilPerusahaanSection" style="display: none;">
                            <h5>
                                Profil Perusahaan
                            </h5>

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
                                <small class="text-muted d-block mb-2">
                                    Nama resmi perusahaan (PT/CV) untuk kop surat
                                </small>
                                <input type="text" name="nama_perusahaan" id="nama_perusahaan" class="form-control" placeholder="PT/CV Nama Perusahaan">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Alamat Perusahaan <span class="text-danger">*</span></label>
                                <small class="text-muted d-block mb-2">
                                    Alamat lengkap kantor/pabrik untuk dicantumkan di kop surat
                                </small>
                                <textarea name="alamat_perusahaan" id="alamat_perusahaan" class="form-control" rows="3" placeholder="Alamat lengkap perusahaan"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">No. Telepon Kop Surat <span class="text-danger">*</span></label>
                                    <input type="tel"
                                        name="no_telp_kop"
                                        id="no_telp_kop"
                                        class="form-control"
                                        placeholder="Nomor telepon untuk kop surat">
                                    <small class="text-muted">Nomor ini akan ditampilkan di kop surat perusahaan</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Perusahaan <span class="text-danger">*</span></label>
                                    <input type="email"
                                        name="email_perusahaan"
                                        id="email_perusahaan"
                                        class="form-control"
                                        placeholder="">
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
                                <a href="#"
                                    class="text-primary text-decoration-none"
                                    style="font-size: 0.9rem;"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalKlasifikasiMerek">
                                    Lihat Sistem Klasifikasi Merek
                                </a>
                            </div>
                            <input type="text" name="kelas_merek" class="form-control mt-2"
                                placeholder="Tentukan Kelas Merek (cek 'Sistem Klasifikasi Merek' di Google)" required>
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
                                        <iframe src="https://skm.dgip.go.id/"
                                            class="w-100 h-100"
                                            frameborder="0"
                                            title="Sistem Klasifikasi Merek">
                                        </iframe>
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
                            <input type="hidden" name="submit_mandiri" value="1">
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
                    <p class="mb-3">Apakah Anda yakin ingin mengirim pengajuan ini?</p>
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
    <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>
    <script>
        // ===== KELURAHAN/DESA DATA =====
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

        // Kecamatan - Kelurahan/Desa Dropdown dengan auto-select dari database
        const kecamatanSelect = document.getElementById('kecamatan_usaha');
        const kelDesaSelect = document.getElementById('kel_desa_usaha');

        // Pre-populate kelurahan jika ada data existing
        <?php if (isset($data_usaha_existing['kecamatan']) && isset($data_usaha_existing['kel_desa'])): ?>
            const existingKecamatan = '<?php echo $data_usaha_existing['kecamatan']; ?>';
            const existingKelDesa = '<?php echo $data_usaha_existing['kel_desa']; ?>';

            if (existingKecamatan && desaKelurahan[existingKecamatan]) {
                kelDesaSelect.innerHTML = '<option value="">-Pilih Kelurahan/Desa-</option>';
                desaKelurahan[existingKecamatan].forEach(function(desa) {
                    const option = document.createElement('option');
                    option.value = desa;
                    option.textContent = desa;
                    if (desa === existingKelDesa) {
                        option.selected = true;
                    }
                    kelDesaSelect.appendChild(option);
                });
                kelDesaSelect.disabled = false;
            }
        <?php endif; ?>

        kecamatanSelect.addEventListener('change', function() {
            const kecamatan = this.value;
            kelDesaSelect.innerHTML = '<option value="">-Pilih Kelurahan/Desa-</option>';

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

        // Konfirmasi sebelum submit
        document.getElementById('formMandiri').addEventListener('submit', function(e) {

            e.preventDefault();
            // Validasi file uploads secara manual
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
            if ((jenisUsaha === 'cv' || jenisUsaha === 'pt') && uploadedFilesStore.akta.length === 0) {
                missingFiles.push('Akta Pendirian CV/PT');
            }

            if (missingFiles.length > 0) {
                showAlert(`Mohon upload file berikut:<br><br>${missingFiles.join('<br>')}`, 'warning');
                return;
            }

            // Cek apakah surat permohonan sudah digenerate
            const suratPath = document.getElementById('suratpermohonanPath').value;
            if (!suratPath) {
                showAlert('Mohon generate Surat Permohonan terlebih dahulu dengan menekan tombol "Simpan Tanda Tangan"', 'warning');
                // Scroll ke bagian tanda tangan
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

        // Handle konfirmasi YA dari modal
        document.getElementById('btnKonfirmasiYa').addEventListener('click', function() {
            // Tutup modal
            const modalKonfirmasi = bootstrap.Modal.getInstance(document.getElementById('modalKonfirmasiSubmit'));
            modalKonfirmasi.hide();

            // KIRIM FORM DENGAN FORMDATA MANUAL
            const form = document.getElementById('formMandiri');
            const formData = new FormData();

            // Ambil semua input text/select/textarea
            const formInputs = form.querySelectorAll('input:not([type="file"]), select, textarea');
            formInputs.forEach(input => {
                if (input.name && input.value) {
                    formData.append(input.name, input.value);
                }
            });
            // Tambahkan radio button jenis_pemohon
            const jenisPemohonChecked = document.querySelector('input[name="jenis_pemohon"]:checked');
            if (jenisPemohonChecked) {
                formData.append('jenis_pemohon', jenisPemohonChecked.value);
            }

            // Tambahkan data profil perusahaan jika jenis pemohon = perusahaan
            if (jenisPemohonChecked && jenisPemohonChecked.value === 'perusahaan') {
                const namaPerusahaan = document.getElementById('nama_perusahaan')?.value || '';
                const alamatPerusahaan = document.getElementById('alamat_perusahaan')?.value || '';
                const noTelpKop = document.getElementById('no_telp_kop')?.value || '';
                const emailPerusahaan = document.getElementById('email_perusahaan')?.value || '';

                formData.append('nama_perusahaan', namaPerusahaan);
                formData.append('alamat_perusahaan', alamatPerusahaan);
                formData.append('no_telp_kop', noTelpKop);
                formData.append('email_perusahaan', emailPerusahaan);
            }

            // Tambahkan file dari uploadedFilesStore
            // Logo (single file)
            if (uploadedFilesStore.logo.length > 0) {
                formData.append('logo_merek', uploadedFilesStore.logo[0]);
            }

            // NIB (multiple files)
            uploadedFilesStore.nib.forEach((file, index) => {
                formData.append('nib[]', file);
            });

            // Foto Produk (multiple files)
            uploadedFilesStore.produk.forEach((file, index) => {
                formData.append('foto_produk[]', file);
            });

            // Foto Proses (multiple files)
            uploadedFilesStore.proses.forEach((file, index) => {
                formData.append('foto_proses[]', file);
            });

            // Akta (single file, optional)
            if (uploadedFilesStore.akta.length > 0) {
                formData.append('akta', uploadedFilesStore.akta[0]);
            }
            // Logo Perusahaan (single file, conditional)
            if (uploadedFilesStore.logo_perusahaan.length > 0) {
                formData.append('logo_perusahaan', uploadedFilesStore.logo_perusahaan[0]);
            }

            // Disable button submit selama proses
            const btnSubmit = document.getElementById('btnSubmit');
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin pe-2"></i>Mengirim...';

            // Kirim via fetch
            fetch(this.action || window.location.href, {
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
                        // Jika ada error, reload page dengan error message
                        document.open();
                        document.write(html);
                        document.close();
                    }
                })
                .catch(error => {
                    console.error('Submit error:', error);
                    showAlert('Terjadi kesalahan saat mengirim form: ' + error.message, 'danger');
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="fas fa-paper-plane pe-2"></i> Kirim Pengajuan';
                });
        });

        // Show alert if exists
        <?php if (isset($_SESSION['alert_message'])): ?>
            showAlert('<?php echo addslashes($_SESSION['alert_message']); ?>', '<?php echo $_SESSION['alert_type'] ?? 'success'; ?>');
            <?php
            unset($_SESSION['alert_message']);
            unset($_SESSION['alert_type']);
            ?>
        <?php endif; ?>

        // Auto convert nomor telepon 0 ke 62 untuk no_telp_perusahaan
        const noTelpPerusahaanInput = document.getElementById('no_telp_perusahaan');
        const noTelpKopInput = document.getElementById('no_telp_kop');

        if (noTelpPerusahaanInput) {
            noTelpPerusahaanInput.addEventListener('blur', function() {
                let value = this.value.trim();

                // Hapus semua karakter non-digit
                value = value.replace(/\D/g, '');

                // Convert 0 ke 62
                if (value.startsWith('0')) {
                    value = '62' + value.substring(1);
                    this.value = value;
                }
                // Jika user langsung input tanpa awalan, tambahkan 62
                else if (value.length > 0 && !value.startsWith('62')) {
                    value = '62' + value;
                    this.value = value;
                }
            });

        }

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

        // RT/RW FORMATTING
        const rtRwInput = document.getElementById('rt_rw_usaha');
        Inputmask("999/999", {
            placeholder: "___/___",
            clearMaskOnLostFocus: false
        }).mask(rtRwInput);

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
    </script>
</body>

</html>