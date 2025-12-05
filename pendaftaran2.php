<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

include 'process/config_db.php';

// ===== CEK APAKAH USER SUDAH LOGIN =====
if (!isset($_SESSION['NIK_NIP'])) {
    header("Location: login.php");
    exit();
}

$NIK = $_SESSION['NIK_NIP'];

try {
    $stmt_master = $pdo->prepare("SELECT id_jenis_file, nama_jenis_file FROM masterfilelampiran WHERE id_jenis_file >= 9 AND id_jenis_file != 16 ORDER BY id_jenis_file");
    $stmt_master->execute();
    $master_legalitas = $stmt_master->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching master legalitas: " . $e->getMessage());
    $master_legalitas = [];
}

// ===== PROSES FORM SUBMISSION =====
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // // Double check apakah sudah pernah mendaftar (proteksi tambahan)
        // $stmt = $pdo->prepare("SELECT id_pendaftaran FROM pendaftaran WHERE NIK = ? LIMIT 1");
        // $stmt->execute([$NIK]);
        // if ($stmt->fetch()) {
        //     throw new Exception("Anda sudah memiliki pendaftaran aktif. Setiap akun hanya dapat mendaftar 1 kali.");
        // }

        // Mulai transaction
        $pdo->beginTransaction();

        // ===== 1. Simpan ke tabel datausaha =====
        $nama_usaha = trim($_POST['nama_usaha']);
        $rt_rw = trim($_POST['rt_rw']);
        $kel_desa = trim($_POST['kel_desa']);
        $kecamatan = trim($_POST['kecamatan']);
        $no_telp_perusahaan = trim($_POST['no_telp_perusahaan']);

        // Normalisasi nomor telepon perusahaan ke format 62 (jika diisi)
        if (!empty($no_telp_perusahaan)) {
            // Hapus semua karakter non-digit
            $no_telp_perusahaan = preg_replace('/\D/', '', $no_telp_perusahaan);

            // Konversi ke format 62
            if (substr($no_telp_perusahaan, 0, 1) == '0') {
                $no_telp_perusahaan = '62' . substr($no_telp_perusahaan, 1);
            } elseif (substr($no_telp_perusahaan, 0, 2) != '62') {
                $no_telp_perusahaan = '62' . $no_telp_perusahaan;
            }

            // Validasi format (11-15 digit, diawali 62)
            if (!preg_match('/^62\d{9,13}$/', $no_telp_perusahaan)) {
                throw new Exception("Format nomor telepon perusahaan tidak valid. Harus format 62xxx (11-15 digit)");
            }
        }

        // Parse data produk dari JSON
        $produk_data = json_decode($_POST['produk_data'], true);
        $hasil_produk = [];
        $kapasitas_produk = [];
        $omset_perbulan = [];

        foreach ($produk_data as $produk) {
            $hasil_produk[] = $produk['nama'];
            $kapasitas_produk[] = $produk['nama'] . ": " . $produk['jumlah'] . "/bulan";
            $omset_perbulan[] = $produk['nama'] . ": Rp " . number_format($produk['omset'], 0, ',', '.');
        }

        // Hitung total omset
        $total_omset = array_sum(array_column($produk_data, 'omset'));
        $omset_perbulan[] = "TOTAL OMSET: Rp " . number_format($total_omset, 0, ',', '.');

        $hasil_produk_str = implode(', ', $hasil_produk);
        $kapasitas_produk_str = implode('; ', $kapasitas_produk);
        $omset_perbulan_str = implode('; ', $omset_perbulan);

        $jml_tenaga_kerja = intval($_POST['jml_tenaga_kerja']);
        $wilayah_pemasaran = trim($_POST['wilayah_pemasaran']);

        // Gabungkan legalitas yang dipilih
        $legalitas = [];
        if (isset($_POST['legalitas']) && is_array($_POST['legalitas'])) {
            $legalitas = $_POST['legalitas'];
        }
        if (isset($_POST['legalitas_lain']) && !empty(trim($_POST['legalitas_lain']))) {
            $legalitas[] = trim($_POST['legalitas_lain']);
        }
        $legalitas_string = implode(', ', $legalitas);

        $stmt = $pdo->prepare("INSERT INTO datausaha (nama_usaha, rt_rw, kel_desa, kecamatan, no_telp_perusahaan, hasil_produk, jml_tenaga_kerja, kapasitas_produk, omset_perbulan, wilayah_pemasaran, legalitas)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nama_usaha, $rt_rw, $kel_desa, $kecamatan, $no_telp_perusahaan, $hasil_produk_str, $jml_tenaga_kerja, $kapasitas_produk_str, $omset_perbulan_str, $wilayah_pemasaran, $legalitas_string]);
        $id_usaha = $pdo->lastInsertId();

        // ===== 2. Simpan ke tabel pendaftaran =====
        $tgl_daftar = date('Y-m-d H:i:s');
        $status_validasi = 'Pengecekan Berkas';

        $stmt2 = $pdo->prepare("INSERT INTO pendaftaran (NIK, id_usaha, tgl_daftar, status_validasi)
                                VALUES (?, ?, ?, ?)");
        $stmt2->execute([$NIK, $id_usaha, $tgl_daftar, $status_validasi]);
        $id_pendaftaran = $pdo->lastInsertId();

        // ===== 3. Upload file helper function (UPDATED) =====
        function uploadFile($fileInputName, $NIK, $fileType, $folder = 'uploads/')
        {
            // Tentukan subfolder berdasarkan tipe file
            $subFolder = '';
            switch ($fileType) {
                case 'logo':
                    $subFolder = 'logo/';
                    break;
                default:
                    $subFolder = '';
            }

            // Format: uploads/(tipeFile)/(tipeFile)_NIK/
            $finalFolder = $folder . $subFolder . $fileType . '_' . $NIK . '/';

            if (!file_exists($finalFolder)) {
                mkdir($finalFolder, 0777, true);
            }

            if (isset($_FILES[$fileInputName]) && !empty($_FILES[$fileInputName]['name'])) {
                $file_extension = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception("Format file tidak diizinkan untuk {$fileInputName}");
                }

                $filename = time() . "_" . uniqid() . "." . $file_extension;
                $target = $finalFolder . $filename;

                if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $target)) {
                    return $target;
                } else {
                    throw new Exception("Gagal mengupload file {$fileInputName}");
                }
            }
            return null;
        }

        // ===== 4. Simpan ke tabel merek =====
        $kelas_merek = trim($_POST['kelas_merek']);
        $nama_merek1 = trim($_POST['nama_merek1']);
        $nama_merek2 = trim($_POST['nama_merek2']);
        $nama_merek3 = trim($_POST['nama_merek3']);

        $logo1 = uploadFile('logo1', $NIK, 'logo', 'uploads/');
        $logo2 = uploadFile('logo2', $NIK, 'logo', 'uploads/');
        $logo3 = uploadFile('logo3', $NIK, 'logo', 'uploads/');

        $stmt3 = $pdo->prepare("INSERT INTO merek (id_pendaftaran, kelas_merek, nama_merek1, nama_merek2, nama_merek3, logo1, logo2, logo3)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt3->execute([$id_pendaftaran, $kelas_merek, $nama_merek1, $nama_merek2, $nama_merek3, $logo1, $logo2, $logo3]);

        // ===== 5. Simpan ke tabel lampiran =====
        function uploadMultipleFiles($inputName, $id_jenis_file, $id_pendaftaran, $pdo, $NIK, $fileType, $isRequired = false)
        {
            // Cek apakah input file ada dan tidak kosong
            if (!isset($_FILES[$inputName]) || empty($_FILES[$inputName]['name'])) {
                if ($isRequired) {
                    throw new Exception("File untuk {$inputName} wajib diupload!");
                }
                return;
            }

            // Cek apakah ada file yang benar-benar diupload
            if (empty($_FILES[$inputName]['name'][0])) {
                if ($isRequired) {
                    throw new Exception("File untuk {$inputName} wajib diupload!");
                }
                return;
            }

            // Tentukan base folder berdasarkan id_jenis_file
            $baseFolder = '';
            switch ($id_jenis_file) {
                case 1: // NIB
                    $baseFolder = "uploads/nib/nib_{$NIK}/";
                    break;
                case 2: // Foto Produk
                    $baseFolder = "uploads/fotoproduk/fotoproduk_{$NIK}/";
                    break;
                case 3: // Proses Produksi
                    $baseFolder = "uploads/prosesproduksi/prosesproduksi_{$NIK}/";
                    break;
                case 9: // P-IRT
                    $baseFolder = "uploads/legalitas/PIRT_{$NIK}/";
                    break;
                case 10: // BPOM-MD
                    $baseFolder = "uploads/legalitas/BPOMMD_{$NIK}/";
                    break;
                case 11: // HALAL
                    $baseFolder = "uploads/legalitas/HALAL_{$NIK}/";
                    break;
                case 12: // NUTRITION FACTS
                    $baseFolder = "uploads/legalitas/NUTRITIONFACTS_{$NIK}/";
                    break;
                case 13: // SNI
                    $baseFolder = "uploads/legalitas/SNI_{$NIK}/";
                    break;
                case 14: // Legalitas Lainnya
                    $baseFolder = "uploads/legalitas/Lainnya_{$NIK}/";
                    break;
                default:
                    $baseFolder = "uploads/lampiran/lampiran_{$NIK}/";
                    break;
            }

            // Pastikan folder utama ada
            if (!file_exists($baseFolder)) {
                mkdir($baseFolder, 0777, true);
            }

            $total_files = count($_FILES[$inputName]['name']);

            for ($i = 0; $i < $total_files; $i++) {
                if (!empty($_FILES[$inputName]['name'][$i])) {
                    $file_extension = strtolower(pathinfo($_FILES[$inputName]['name'][$i], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

                    if (!in_array($file_extension, $allowed_extensions)) {
                        throw new Exception("Format file tidak diizinkan pada {$inputName}");
                    }

                    $max_size = ($id_jenis_file == 1) ? 10 * 1024 * 1024 : 1 * 1024 * 1024;
                    if ($_FILES[$inputName]['size'][$i] > $max_size) {
                        throw new Exception("Ukuran file {$_FILES[$inputName]['name'][$i]} melebihi batas maksimal");
                    }

                    $filename = time() . "_" . uniqid() . "_" . $i . "." . $file_extension;
                    $target = $baseFolder . $filename;

                    if (move_uploaded_file($_FILES[$inputName]['tmp_name'][$i], $target)) {
                        $tgl_upload = date('Y-m-d H:i:s');
                        $stmt = $pdo->prepare("INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path)
                       VALUES (?, ?, ?, ?)");
                        $stmt->execute([$id_pendaftaran, $id_jenis_file, $tgl_upload, $target]);
                    } else {
                        throw new Exception("Gagal mengupload file {$_FILES[$inputName]['name'][$i]}");
                    }
                }
            }
        }

        uploadMultipleFiles('nib_files', 1, $id_pendaftaran, $pdo, $NIK, 'nib', true);
        
        // ===== Upload lampiran legalitas (sistem baru) =====
        if (isset($_FILES)) {
            foreach ($_FILES as $inputName => $fileData) {
                // Cek apakah ini file legalitas (format: legalitas_files_X)
                if (preg_match('/^legalitas_files_(\d+)$/', $inputName, $matches)) {
                    $id_jenis_file = intval($matches[1]);
                    
                    // Validasi id_jenis_file ada di master
                    $stmt_check = $pdo->prepare("SELECT nama_jenis_file FROM masterfilelampiran WHERE id_jenis_file = ?");
                    $stmt_check->execute([$id_jenis_file]);
                    $master = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$master) {
                        throw new Exception("Jenis file legalitas tidak valid (ID: {$id_jenis_file})");
                    }
                    
                    // Tentukan folder berdasarkan id_jenis_file
                    $folderMap = [
                        9 => "PIRT",
                        10 => "BPOMMD",
                        11 => "HALAL",
                        12 => "NUTRITIONFACTS",
                        13 => "SNI",
                        14 => "Lainnya"
                    ];
                    
                    $folderName = isset($folderMap[$id_jenis_file]) ? $folderMap[$id_jenis_file] : "Legalitas_{$id_jenis_file}";
                    $baseFolder = "uploads/legalitas/{$folderName}_{$NIK}/";
                    
                    // Pastikan folder ada
                    if (!file_exists($baseFolder)) {
                        mkdir($baseFolder, 0777, true);
                    }
                    
                    // Upload semua file untuk jenis ini
                    $total_files = count($fileData['name']);
                    
                    for ($i = 0; $i < $total_files; $i++) {
                        if (!empty($fileData['name'][$i])) {
                            $file_extension = strtolower(pathinfo($fileData['name'][$i], PATHINFO_EXTENSION));
                            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                            
                            if (!in_array($file_extension, $allowed_extensions)) {
                                throw new Exception("Format file tidak diizinkan: {$fileData['name'][$i]}");
                            }
                            
                            if ($fileData['size'][$i] > 1 * 1024 * 1024) {
                                throw new Exception("Ukuran file {$fileData['name'][$i]} melebihi 1 MB");
                            }
                            
                            $filename = time() . "_" . uniqid() . "_" . $i . "." . $file_extension;
                            $target = $baseFolder . $filename;
                            
                            if (move_uploaded_file($fileData['tmp_name'][$i], $target)) {
                                $tgl_upload = date('Y-m-d H:i:s');
                                $stmt = $pdo->prepare("INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path)
                                                      VALUES (?, ?, ?, ?)");
                                $stmt->execute([$id_pendaftaran, $id_jenis_file, $tgl_upload, $target]);
                            } else {
                                throw new Exception("Gagal mengupload file {$fileData['name'][$i]}");
                            }
                        }
                    }
                }
            }
        }



        // Upload foto produk dan proses
        uploadMultipleFiles('foto_produk', 2, $id_pendaftaran, $pdo, $NIK, 'fotoproduk', true);
        uploadMultipleFiles('foto_proses', 3, $id_pendaftaran, $pdo, $NIK, 'prosesproduksi', true);


        // Commit transaction
        $pdo->commit();

        // Set session message untuk sukses
        $_SESSION['alert_message'] = 'Data pendaftaran merek berhasil dikirim!\n\nSilakan cek status pengajuan Anda secara berkala.\n\nTerima kasih.';
        $_SESSION['alert_type'] = 'success';

        // Langsung redirect ke status-seleksi-pendaftaran.php
        header("Location: status-seleksi-pendaftaran.php");
        exit();
    } catch (Exception $e) {
        // Rollback jika ada error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log error
        error_log("Error form pendaftaran: " . $e->getMessage());

        $_SESSION['alert_message'] = 'Terjadi kesalahan: ' . $e->getMessage() . '\n\nSilakan coba lagi.';
        $_SESSION['alert_type'] = 'danger';

        // Redirect ke halaman ini sendiri
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Pendaftaran Merek - Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/form-pendaftaran.css">
</head>

<body>
    <?php include 'navbar-login.php' ?>

    <div class="container main-container">
        <div class="row cont">
            <div class="col-lg-4">
                <h5 class="judul">Fasilitasi Surat Keterangan IKM untuk Pendaftaran Merek Mandiri</h5>
                <p>Pemohon hanya mendapatkan Surat Keterangan IKM (Industri Kecil Menengah) untuk melakukan Pendaftaran Merek Mandiri di Kemenkumham RI.</p>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-info-square pe-2"></i> Informasi Pendaftaran</h5>
                    <ul class="list-unstyled info-list">
                        <li>Output: <br> Surat Keterangan IKM</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-journal-check pe-2"></i>Syarat dan Ketentuan</h5>
                    <ul class="info-list">
                        <li>Industri Kecil yang memproduksi produk di Sidoarjo (tidak untuk jasa, catering, rumah makan, repacking, dst)</li>
                        <li>Aktif memproduksi dan memasarkan produknya secara kontinyu</li>
                        <li>Produk kemasan dengan masa simpan lebih dari 7 hari</li>
                        <li>Nomor Induk Berusaha (NIB) berbasis risiko dengan KBLI industri sesuai jenis produk</li>
                        <li>Logo Merek</li>
                        <li>Foto produk jadi</li>
                        <li>Foto proses produksi yang membuktikan memang memproduksi sendiri</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle">
                    <h5><i class="bi bi-journal pe-2"></i>Catatan</h5>
                    <ul class="info-list">
                        <li>Cek Ketersediaan Merek: <br> Pastikan merek tersebut belum didaftarkan oleh orang lain <br> <a href="https://pdki-indonesia.dgip.go.id/" target="_blank">Cek di PDKI Indonesia</a></li>
                        <li>Kelas Merek: <br>Tentukan Kelas Merek dengan mencari "Sistem Klasifikasi Merek" di Google</li>
                    </ul>
                </div>

                <div class="sidebar-section border border-light-subtle bg-warning bg-opacity-10">
                    <h5><i class="bi bi-exclamation-triangle pe-2"></i>Perhatian</h5>
                    <ul class="info-list">
                        <li>Pastikan semua data yang diisi sudah benar dan lengkap</li>
                        <li>Data yang sudah dikirim tidak dapat diubah</li>
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

            <div class="col-lg-8">

                <div class="form-container border border-light-subtle">
                    <h4>Data Usaha</h4>
                    <hr class="border-2 border-secondary w-100">
                    <form method="POST" enctype="multipart/form-data" id="formPendaftaran">
                        <div class="row">
                            <div class="mb-3">
                                <label class="form-label">Nama Usaha <span class="text-danger">*</span></label>
                                <input type="text" name="nama_usaha" class="form-control" placeholder="Masukkan nama usaha sesuai ijin yang dimiliki" required>
                            </div>
                        </div>

                        <div class="row">
                            <label class="form-label">Alamat Perusahaan</label>
                            <div class="mb-3">
                                <label class="form-label-alamat">Kecamatan <span class="text-danger">*</span></label>
                                <select name="kecamatan" id="kecamatan" class="form-control" required>
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
                                <label class="form-label-alamat">RT/RW</label>
                                <input type="text" name="rt_rw" id="rt_rw" class="form-control" placeholder="Contoh: 003/005">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-alamat">Kelurahan/Desa <span class="text-danger">*</span></label>
                                <select name="kel_desa" id="kel_desa" class="form-control" required>
                                    <option value="">-- Pilih Kecamatan Terlebih Dahulu --</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nomor Telepon Perusahaan</label>
                                <input type="tel" name="no_telp_perusahaan" id="no_telp_perusahaan" class="form-control" placeholder="6281234567890 (Kosongi jika tidak ada atau sama dengan nomor telepon pemilik)">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Produk, Kapasitas Produksi, dan Omset <span class="text-danger">*</span></label>
                            <p class="text-muted small">Isi data produk yang dihasilkan beserta kapasitas produksi per bulan, harga satuan, dan omset akan dihitung otomatis</p>

                            <div class="table-responsive">
                                <table class="produk-table table table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width: 25%">Nama Produk</th>
                                            <th style="width: 15%">Jumlah Produk/Bulan</th>
                                            <th style="width: 18%">Harga Satuan (Rp)</th>
                                            <th style="width: 20%">Omset/Bulan (Rp)</th>
                                            <th style="width: 7%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="produkTableBody">
                                        <tr>
                                            <td><input type="text" class="form-control form-control-sm produk-nama" required></td>
                                            <td><input type="number" class="form-control form-control-sm produk-jumlah" min="1" required></td>
                                            <td><input type="number" class="form-control form-control-sm produk-harga" min="0" required></td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm produk-omset bg-light" readonly value="Rp 0">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-sm remove-produk" disabled>
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-secondary">
                                            <td colspan="3" class="text-end"><strong>Total Omset per Bulan:</strong></td>
                                            <td><strong id="totalOmset">Rp 0</strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <button type="button" class="btn btn-primary btn-sm" id="addProdukRow">
                                <i class="fas fa-plus me-1"></i> Tambah Produk
                            </button>

                            <input type="hidden" name="produk_data" id="produkData">
                        </div>

                        <div class="row">
                            <div class="mb-3">
                                <label class="form-label">Jumlah Tenaga Kerja <span class="text-danger">*</span></label>
                                <input type="number" name="jml_tenaga_kerja" class="form-control" placeholder="Apabila dilakukan sendiri maka tenaga kerja = 1" required min="1">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Wilayah pemasaran <span class="text-danger">*</span></label>
                            <textarea name="wilayah_pemasaran" class="form-control" rows="3" placeholder="Sebutkan kota tujuan pemasaran. Misal: Sidoarjo, Gresik, Surabaya, Malang, dst." required></textarea>
                        </div>

                        <h5>Lampiran Dokumen</h5>
                        <hr class="border-2 border-secondary w-100">

                        <div class="mb-3">
                            <label class="form-label">Lampiran 1: Nomor Induk Berusaha (NIB) <span class="text-danger">*</span></label>
                            <label class="form-label-alamat">Beserta lampiran tabel KBLI halaman 2, dari website <a href="https://oss.go.id/" target="_blank"> https://oss.go.id/. </a></label>
                            <div class="file-drop-zone" id="nibDropZone">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                <small>Upload maksimal 5 file PDF. Maks 10 MB per file</small>
                                <input type="file" name="nib_files[]" id="nib-file" accept=".pdf" multiple hidden>
                            </div>
                            <div class="preview-container" id="nibPreview"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Legalitas/Standardisasi yang telah dimiliki</label>
                            <p class="text-muted small">Upload file legalitas yang Anda miliki, kemudian pilih jenis legalitasnya dari dropdown<br>
                            <span style="font-weight: 600;">Contohnya: P-IRT, BPOM-MD, HALAL, NUTRITION FACTS, SNI, dan lainnya.</span></p>
                            
                            <!-- Upload Zone -->
                            <div class="file-drop-zone" id="legalitasDropZone">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                <small>Upload file legalitas (Format PDF). Maks 1 MB per file</small>
                                <input type="file" id="legalitas-file-input" accept=".pdf" multiple hidden>
                            </div>

                            <!-- List File yang diupload -->
                            <div id="legalitasFileList" class="mt-3"></div>

                            <!-- Hidden inputs untuk form submission -->
                            <div id="legalitasHiddenInputs"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Lampiran 2: Foto Produk <span class="text-danger">*</span></label>
                            <div class="file-drop-zone" id="produkDropZone">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                <small>Upload maksimal 5 file (JPG/PNG). Maks 1 MB per file</small>
                                <input type="file" name="foto_produk[]" id="product-file" accept=".jpg,.jpeg,.png" multiple hidden>
                            </div>
                            <div class="preview-container" id="produkPreview"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Lampiran 3: Foto Proses Produksi <span class="text-danger">*</span></label>
                            <div class="file-drop-zone" id="prosesDropZone">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                <small>Upload maksimal 5 file (JPG/PNG). Maks 1 MB per file</small>
                                <input type="file" name="foto_proses[]" id="prosesproduksi-file" accept=".jpg,.jpeg,.png" multiple hidden>
                            </div>
                            <div class="preview-container" id="prosesPreview"></div>
                        </div>

                        <h5>Informasi Merek</h5>
                        <hr class="border-2 border-secondary w-100">

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <label class="form-label mb-0">
                                    Kelas Merek sesuai produk <span class="text-danger">*</span>
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

                        <div class="w-100 trademark-alternative">
                            <div>
                                <label class="form-label">Contoh Merek yang Sesuai</label>
                                <div class="trademark-examples">
                                    <div class="trademark-example">
                                        <img src="assets/img/aqua.png" alt="AQUA">
                                        <h6 class="fw-normal">Nama Merek Alternatif<br><strong>AQUA</strong></h6>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <p style="font-size: 0.9rem; color: #666;">
                                    <strong>Catatan:</strong>
                                <ul class="info-list">
                                    <li>Harus berbeda baik logo dan namanya</li>
                                    <li>Tanpa tulisan lain seperti nomor whatsapp, komposisi, dll. Cukup nama merek saja.</li>
                                </ul>
                                </p>
                            </div>
                        </div>

                        <div class="trademark-alternative ms-4">
                            <h6>Merek <span class="text-danger">*</span></h6>
                            <div class="mb-2">
                                <label class="form-label">Nama Merek </label>
                                <input type="text" name="nama_merek1" class="form-control" placeholder="Masukkan nama merek alternatif 1" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Logo Merek</label>
                                <div class="file-drop-zone" id="logo1DropZone">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Seret & Lepas file di sini</strong><br>atau klik untuk memilih file</p>
                                    <small>Upload 1 file (JPG/PNG). Maks 1 MB</small>
                                    <input type="file" name="logo1" id="logo1-file" accept=".jpg,.jpeg,.png" hidden>
                                </div>
                                <div class="preview-container" id="logo1Preview"></div>
                            </div>
                        </div>

                        <div class="text-center">
                            <div class="alert alert-warning d-inline-block mb-3" style="max-width: 600px;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Perhatian:</strong> Dengan menekan tombol "Kirim Data Pendaftaran", Anda menyatakan bahwa semua data yang diisi sudah benar dan lengkap. Data yang sudah dikirim tidak dapat diubah.
                            </div>
                            <br>
                            <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                                <strong>AMANKAN IDENTITAS BISNIS ANDA, DAFTARKAN MEREK SEKARANG!</strong>
                            </p>
                            <button type="submit" class="btn btn-submitpendaftaran" id="btnSubmit">
                                <i class="fas fa-paper-plane pe-2"></i> Kirim Data Pendaftaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer  -->
    <footer class="footer">
        <div class="container">
            <p>Copyright © 2025. All Rights Reserved.</p>
            <p>Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Inject master legalitas data
        var masterLegalitas = <?php echo json_encode($master_legalitas); ?>;
        console.log('Master Legalitas Loaded:', masterLegalitas); // Debug line
    </script>
    <script src="assets/js/form-pendaftaran.js"></script>


</body>

</html>