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

// HANDLER AJAX REQUEST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
  header('Content-Type: application/json');

  $action = $_POST['ajax_action'];
  $id_pendaftaran = isset($_POST['id_pendaftaran']) ? intval($_POST['id_pendaftaran']) : 0;

  if (!$id_pendaftaran) {
    echo json_encode(['success' => false, 'message' => 'ID pendaftaran tidak valid']);
    exit;
  }

  try {
    // Verifikasi bahwa pendaftaran ini milik user yang login
    $stmt = $pdo->prepare("SELECT NIK FROM pendaftaran WHERE id_pendaftaran = ?");
    $stmt->execute([$id_pendaftaran]);
    $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pendaftaran || $pendaftaran['NIK'] !== $NIK) {
      echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
      exit;
    }

    // HANDLE KONFIRMASI LANJUT (dari Merek 2 atau 3)
    if ($action === 'konfirmasi_lanjut') {
      $stmt = $pdo->prepare("UPDATE pendaftaran SET status_validasi = 'Surat Keterangan Difasilitasi' WHERE id_pendaftaran = ?");
      $stmt->execute([$id_pendaftaran]);

      echo json_encode(['success' => true, 'message' => 'Status berhasil diperbarui']);
      exit;
    }

    // HANDLE UPLOAD SURAT TTD DARI PEMOHON
    if ($action === 'upload_surat') {
      if (!isset($_FILES['fileSurat']) || $_FILES['fileSurat']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File tidak valid']);
        exit;
      }

      $file = $_FILES['fileSurat'];
      $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
      $allowed_extensions = ['pdf'];

      if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak diizinkan']);
        exit;
      }

      if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 5MB']);
        exit;
      }

      $folder = "uploads/berkas_fasilitasi/berkasfasilitasi_{$NIK}/";
      if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
      }

      $filename = "berkasfasilitasi_{$NIK}_" . time() . "." . $file_extension;
      $target = $folder . $filename;

      if (move_uploaded_file($file['tmp_name'], $target)) {
        $tgl_upload = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
          $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 4");
          $stmt->execute([$id_pendaftaran]);
          $old_file = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($old_file && file_exists($old_file['file_path'])) {
            unlink($old_file['file_path']);
          }

          $stmt = $pdo->prepare("DELETE FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 4");
          $stmt->execute([$id_pendaftaran]);

          $stmt = $pdo->prepare("INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path) VALUES (?, 4, ?, ?)");
          $stmt->execute([$id_pendaftaran, $tgl_upload, $target]);

          $stmt = $pdo->prepare("UPDATE pendaftaran SET status_validasi = 'Menunggu Bukti Pendaftaran' WHERE id_pendaftaran = ?");
          $stmt->execute([$id_pendaftaran]);

          $pdo->commit();

          echo json_encode(['success' => true, 'message' => 'Surat berhasil dikirim']);
        } catch (PDOException $e) {
          $pdo->rollBack();
          if (file_exists($target)) {
            unlink($target);
          }
          throw $e;
        }
      } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
      }
      exit;
    }
  } catch (PDOException $e) {
    error_log("Error AJAX: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan database']);
    exit;
  }
}

// AMBIL TOKEN DARI URL DAN DECRYPT
$encrypted_token = isset($_GET['ref']) ? $_GET['ref'] : '';
$id_pendaftaran = 0;

if (!empty($encrypted_token)) {
    $decrypted_id = decryptId($encrypted_token);
    $id_pendaftaran = $decrypted_id !== false ? intval($decrypted_id) : 0;
}

if (!$id_pendaftaran) {
  header("Location: lihat-pengajuan-fasilitasi.php");
  exit;
}

// AMBIL DATA PENDAFTARAN BERDASARKAN ID
$pendaftaran = null;
$perpanjangan = null;
$alasan_notifikasi = '';
$alasan_konfirmasi = '';

try {
  // Query 1: Ambil data pendaftaran
  $stmt = $pdo->prepare("
    SELECT p.id_pendaftaran, p.NIK, p.id_usaha, p.tgl_daftar, p.status_validasi, 
           p.merek_difasilitasi, p.alasan_tidak_difasilitasi, p.alasan_konfirmasi,
           u.nama_usaha, u.kel_desa, u.kecamatan, 
           m.kelas_merek, m.nama_merek1, m.nama_merek2, m.nama_merek3,
           m.logo1, m.logo2, m.logo3
    FROM pendaftaran p
    LEFT JOIN datausaha u ON p.id_usaha = u.id_usaha
    LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
    WHERE p.id_pendaftaran = ? AND p.NIK = ?
  ");
  
  $stmt->execute([$id_pendaftaran, $NIK]);
  $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$pendaftaran) {
    header("Location: lihat-pengajuan-fasilitasi.php");
    exit;
  }

  // Ambil alasan dari database
  if ($pendaftaran['status_validasi'] === 'Tidak Bisa Difasilitasi') {
    $alasan_notifikasi = $pendaftaran['alasan_tidak_difasilitasi'] ?: "Mohon maaf merek yang anda ajukan tidak bisa difasilitasi.";
  }

  if ($pendaftaran['status_validasi'] === 'Konfirmasi Lanjut') {
    $alasan_konfirmasi = $pendaftaran['alasan_konfirmasi'] ?: '';
  }

} catch (PDOException $e) {
  error_log("Error fetching pendaftaran: " . $e->getMessage());
  die("Terjadi kesalahan saat mengambil data: " . $e->getMessage());
}

// Mapping status untuk kode unik
$statusMap = [
  'Pengecekan Berkas' => 'pengecekanberkas',
  'Tidak Bisa Difasilitasi' => 'tidakbisadifasilitasi',
  'Konfirmasi Lanjut' => 'konfirmasilanjut',
  'Surat Keterangan Difasilitasi' => 'melengkapisurat',
  'Menunggu Bukti Pendaftaran' => 'menunggubukti',
  'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian' => 'buktiterbit',
  'Hasil Verifikasi Kementerian' => 'sertifikatterbit'
];

$statusKey = $statusMap[$pendaftaran['status_validasi']] ?? 'pengecekanberkas';

$dataStatus = [
  'pengecekanberkas' => [
    'proses' => 'Pengecekan Berkas',
    'status' => 'Merek dalam Proses Pengecekan Berkas',
    'desc'   => 'Anda baru saja mengajukan permohonan merek, sekarang merek dalam proses pengecekan berkas.',
  ],
  'tidakbisadifasilitasi' => [
    'proses' => 'Tidak Bisa Difasilitasi',
    'status' => 'Merek Tidak Bisa Difasilitasi',
    'desc'   => $alasan_notifikasi,
  ],
  'konfirmasilanjut' => [
    'proses' => 'Konfirmasi Lanjut',
    'status' => 'Konfirmasi untuk Melanjutkan dengan Merek Alternatif',
    'desc'   => 'Merek yang bisa difasilitasi adalah Merek Alternatif ' . ($pendaftaran['merek_difasilitasi'] ?? '2') . '.',
    'alasan' => $alasan_konfirmasi,
  ],
  'melengkapisurat' => [
    'proses' => 'Surat Keterangan Difasilitasi',
    'status' => 'Melengkapi Surat Keterangan Difasilitasi',
    'desc'   => 'Silakan download Surat Keterangan Difasilitasi di bawah ini, tandatangani, lalu upload kembali untuk melanjutkan proses.',
  ],
  'menunggubukti' => [
    'proses' => 'Menunggu Bukti Pendaftaran',
    'status' => 'Menunggu Bukti Pendaftaran dari Admin',
    'desc'   => 'Surat yang sudah ditandatangani telah berhasil dikirim. Menunggu admin mengirimkan Surat Keterangan IKM dan Bukti Pendaftaran.',
  ],
  'buktiterbit' => [
    'proses' => 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian',
    'status' => 'Bukti Pendaftaran Sudah Terbit dan Diajukan Ke Kementerian',
    'desc'   => 'Bukti Pendaftaran merek Anda telah tersedia dan sudah diajukan ke Kementerian. Silakan download dokumen di bawah ini.',
    'countdown' => 'Estimasi Proses Verifikasi Kementerian: 1 tahun 6 bulan',
  ],
  'sertifikatterbit' => [
    'proses' => 'Hasil Verifikasi Kementerian',
    'status' => 'Hasil Verifikasi Kementerian',
    'desc'   => 'Selamat, merek anda sudah terdaftar dan sudah terbit sertifikatnya.',
    'masa_berlaku' => 'Masa Berlaku Sertifikat: 10 tahun',
  ],
];

$data = $dataStatus[$statusKey];

// Ambil file lampiran untuk download
$suratKeteranganIKM = null;
$buktiPendaftaran = null;
$sertifikatMerek = null;
$suratPenolakan = null;

try {
  $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 5 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $suratKeteranganIKM = $result ? $result['file_path'] : null;

  $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 6 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $buktiPendaftaran = $result ? $result['file_path'] : null;

 $stmt = $pdo->prepare("SELECT file_path, tgl_upload, tanggal_terbit FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 7 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $sertifikatMerek = $result ? $result['file_path'] : null;
  $tanggalTerbitSertifikat = $result && $result['tanggal_terbit'] ? $result['tanggal_terbit'] : null;


  $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 8 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $suratPenolakan = $result ? $result['file_path'] : null;

} catch (PDOException $e) {
  error_log("Error fetching lampiran: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Status Seleksi Pendaftaran Merek</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/status-seleksi.css" />
  <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>

<body>
  <?php include 'navbar-login.php' ?>
  <main class="main-content">
    <div class="container">
      <h1 class="page-title">Status Seleksi Pendaftaran Merek</h1>
      <p class="page-description">
        Cek secara berkala untuk mengetahui perkembangan lebih<br />
        lanjut status pendaftaran merek anda.
      </p>
      
      <div class="info-card">
        <div class="info-header d-flex flex-column flex-md-row justify-content-between align-items-start">
          <h2 class="info-title">Informasi Merek yang Didaftarkan</h2>
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
                <?php if ($statusKey === 'tidakbisadifasilitasi'): ?>
                  <div class="alert alert-danger">
                    <strong><i class="fa-solid fa-exclamation-circle me-2"></i>Alasan:</strong>
                    <p class="m-0 mt-2"><?php echo nl2br(htmlspecialchars($data['desc'])); ?></p>
                  </div>

                <?php elseif ($statusKey === 'konfirmasilanjut'): ?>
                  <p class="m-0"><?php echo htmlspecialchars($data['desc']); ?></p>

                  <?php if (!empty($data['alasan'])): ?>
                    <div class="alert alert-info mt-3">
                      <strong><i class="fa-solid fa-info-circle me-2"></i>Alasan Pemilihan:</strong>
                      <p class="m-0 mt-2"><?php echo nl2br(htmlspecialchars($data['alasan'])); ?></p>
                    </div>
                  <?php endif; ?>

                  <div class="mt-3">
                    <p class="mb-2"><strong>Mohon konfirmasi:</strong></p>
                    <p class="mb-3">Jika berkenan untuk lanjut maka tekan <strong>Lanjut</strong>, dan jika tidak berkenan tekan <strong>Mundur</strong> untuk mengubah data pendaftaran.</p>
                    <div class="d-flex gap-2">
                      <button id="btnLanjut" class="btn btn-dark">Lanjut</button>
                      <a id="btnMundur" href="form-pendaftaran.php?edit=<?php echo $pendaftaran['id_pendaftaran']; ?>" class="btn btn-outline-dark">Mundur</a>
                    </div>
                  </div>

                <?php elseif ($statusKey === 'melengkapisurat'): ?>
                  <p class="m-0 mb-4"><?php echo htmlspecialchars($data['desc']); ?></p>

                  <div class="step-card">
                    <div class="step-header">
                      <i class="fa-solid fa-clipboard-list" style="font-size: 1.5rem;"></i>
                      <h5 class="mb-0">Langkah Melengkapi Surat</h5>
                    </div>
                  </div>

                  <!-- Step 1: Download Surat Keterangan -->
                  <div class="step-card">
                    <div class="step-body">
                      <div class="d-flex align-items-start gap-3">
                        <div class="step-number">1</div>
                        <div class="flex-grow-1">
                          <h6 class="fw-bold mb-3">Download Surat Kelengkapan</h6>

                          <!-- Download surat otomatis dari template -->
                          <a class="btn btn-dark" href="generate-surat-otomatis.php?ref=<?php echo urlencode(encryptId($pendaftaran['id_pendaftaran'])); ?>" target="_blank">
                            <i class="fa-solid fa-download me-2"></i> Download Surat Kelengkapan Difasilitasi (PDF)
                          </a>

                          <div class="alert alert-info mt-3 mb-0">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            <strong>Informasi:</strong>
                            <ul class="mb-0 mt-2" style="padding-left: 20px;">
                              <li>Surat akan diunduh dalam format PDF</li>
                              <li>Data sudah terisi otomatis sesuai dengan data pendaftaran Anda</li>
                              <li>Cetak surat, tanda tangani di atas materai Rp 10.000 x2</li>
                              <li>Scan atau foto hasil tanda tangan, lalu upload di step 3</li>
                            </ul>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Step 2: Tanda Tangan Surat -->
                  <div class="step-card">
                    <div class="step-body">
                      <div class="d-flex align-items-start gap-3">
                        <div class="step-number">2</div>
                        <div class="flex-grow-1">
                          <h6 class="fw-bold mb-2">Tanda Tangan Surat</h6>
                          <p class="text-muted mb-0">Cetak surat, tanda tangani di atas materai Rp 10.000, lalu scan atau foto</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Step 3: Upload Surat - DENGAN PREVIEW ICON -->
                  <div class="step-card">
                    <div class="step-body">
                      <div class="d-flex align-items-start gap-3">
                        <div class="step-number">3</div>
                        <div class="flex-grow-1">
                          <h6 class="fw-bold mb-3">Upload Surat yang Sudah Ditandatangani</h6>
                          <form id="formSurat">
                            <div class="mb-3">
                              <label for="fileSurat" class="form-label">Pilih file dengan format PDF (Max 5MB)</label>
                              <input class="form-control" type="file" id="fileSurat" name="fileSurat" accept=".pdf" required />

                              <!-- Preview Area dalam bentuk Icon -->
                              <div id="filePreview" class="mt-3" style="display: none;">
                                <h6 class="fw-bold mb-2">File Terpilih:</h6>
                                <div class="card border-primary">
                                  <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                      <div class="d-flex align-items-center">
                                        <div id="fileIcon" class="me-3" style="font-size: 2.5rem;">
                                          <!-- Icon akan ditampilkan di sini -->
                                        </div>
                                        <div>
                                          <h6 id="fileName" class="mb-1 fw-bold"></h6>
                                          <p id="fileSize" class="text-muted mb-0 small"></p>
                                        </div>
                                      </div>
                                      <button type="button" id="btnViewFile" class="btn btn-outline-primary btn-sm">
                                        <i class="fa-solid fa-eye me-1"></i> Lihat
                                      </button>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <button id="btnKirimSurat" type="submit" class="btn btn-success">
                              <i class="fa-solid fa-paper-plane me-2"></i> Kirim Surat
                            </button>
                          </form>
                        </div>
                      </div>
                    </div>
                  </div>

                <?php elseif ($statusKey === 'menunggubukti'): ?>
                  <p class="m-0 mb-3"><?php echo htmlspecialchars($data['desc']); ?></p>

                  <div class="row g-3">
                    <!-- Card Surat Keterangan IKM -->
                    <div class="col-md-6">
                      <div class="card border-primary">
                        <div class="card-body">
                          <h6 class="fw-bold mb-3">
                            <i class="fa-solid fa-file me-2 text-danger"></i>
                            Surat Keterangan IKM
                          </h6>
                          <?php if ($suratKeteranganIKM && file_exists($suratKeteranganIKM)): ?>
                            <div class="alert alert-success mb-3">
                              <i class="fa-solid fa-check-circle me-2"></i>
                              <strong>File Tersedia</strong>
                            </div>
                            <a class="btn btn-dark w-100" href="<?php echo htmlspecialchars($suratKeteranganIKM); ?>" target="_blank" download>
                              <i class="fa-solid fa-download me-2"></i> Download Surat Keterangan IKM
                            </a>
                          <?php else: ?>
                            <div class="alert alert-warning mb-0">
                              <i class="fa-solid fa-clock me-2"></i>
                              <strong>Belum Tersedia</strong>
                              <p class="mb-0 mt-2 small">Menunggu admin mengupload Surat Keterangan IKM</p>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>

                    <!-- Card Bukti Pendaftaran -->
                    <div class="col-md-6">
                      <div class="card border-success">
                        <div class="card-body">
                          <h6 class="fw-bold mb-3">
                            <i class="fa-solid fa-file-circle-check me-2 text-danger"></i>
                            Bukti Pendaftaran
                          </h6>
                          <?php if ($buktiPendaftaran && file_exists($buktiPendaftaran)): ?>
                            <div class="alert alert-success mb-3">
                              <i class="fa-solid fa-check-circle me-2"></i>
                              <strong>File Tersedia</strong>
                            </div>
                            <a class="btn btn-success w-100" href="<?php echo htmlspecialchars($buktiPendaftaran); ?>" target="_blank" download>
                              <i class="fa-solid fa-download me-2"></i> Download Bukti Pendaftaran
                            </a>
                          <?php else: ?>
                            <div class="alert alert-warning mb-0">
                              <i class="fa-solid fa-clock me-2"></i>
                              <strong>Belum Tersedia</strong>
                              <p class="mb-0 mt-2 small">Menunggu admin mengupload Bukti Pendaftaran</p>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="alert alert-info mt-3" role="alert">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    <strong>Informasi:</strong> Setelah admin mengupload kedua dokumen, status akan otomatis berubah menjadi "Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian"
                  </div>

                <?php elseif ($statusKey === 'buktiterbit'): ?>
                  <p class="m-0 mb-4"><?php echo htmlspecialchars($data['desc']); ?></p>

                  <div class="row g-3">
                    <!-- Card Surat Keterangan IKM -->
                    <div class="col-md-6">
                      <div class="card border-primary h-100">
                        <div class="card-body d-flex flex-column">
                          <h6 class="fw-bold mb-3">
                            <i class="fa-solid fa-file me-2 text-danger"></i>
                            Surat Keterangan IKM
                          </h6>
                          <?php if ($suratKeteranganIKM && file_exists($suratKeteranganIKM)): ?>
                            <div class="alert alert-success flex-grow-1 mb-3">
                              <i class="fa-solid fa-check-circle me-2"></i>
                              <strong>File Tersedia untuk Diunduh</strong>
                              <p class="mb-0 mt-2 small">Surat Keterangan IKM telah diupload oleh admin</p>
                            </div>
                            <a class="btn btn-dark w-100" href="<?php echo htmlspecialchars($suratKeteranganIKM); ?>" target="_blank" download>
                              <i class="fa-solid fa-download me-2"></i> Download Surat Keterangan IKM
                            </a>
                          <?php else: ?>
                            <div class="alert alert-warning flex-grow-1 mb-3">
                              <i class="fa-solid fa-exclamation-triangle me-2"></i>
                              <strong>Tidak Tersedia</strong>
                              <p class="mb-0 mt-2 small">Silakan hubungi admin jika dokumen ini diperlukan</p>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>

                    <!-- Card Bukti Pendaftaran -->
                    <div class="col-md-6">
                      <div class="card border-success h-100">
                        <div class="card-body d-flex flex-column">
                          <h6 class="fw-bold mb-3">
                            <i class="fa-solid fa-file-circle-check me-2 text-danger"></i>
                            Bukti Pendaftaran
                          </h6>
                          <?php if ($buktiPendaftaran && file_exists($buktiPendaftaran)): ?>
                            <div class="alert alert-success flex-grow-1 mb-3">
                              <i class="fa-solid fa-check-circle me-2"></i>
                              <strong>File Tersedia untuk Diunduh</strong>
                              <p class="mb-0 mt-2 small">Bukti Pendaftaran telah diupload oleh admin</p>
                            </div>
                            <a class="btn btn-success w-100" href="<?php echo htmlspecialchars($buktiPendaftaran); ?>" target="_blank" download>
                              <i class="fa-solid fa-download me-2"></i> Download Bukti Pendaftaran
                            </a>
                          <?php else: ?>
                            <div class="alert alert-warning flex-grow-1 mb-3">
                              <i class="fa-solid fa-exclamation-triangle me-2"></i>
                              <strong>Tidak Tersedia</strong>
                              <p class="mb-0 mt-2 small">Silakan hubungi admin untuk informasi lebih lanjut</p>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>

                  <?php
                  // Ambil tanggal upload Bukti Pendaftaran
                  $stmt = $pdo->prepare("SELECT tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 6 ORDER BY tgl_upload DESC LIMIT 1");
                  $stmt->execute([$pendaftaran['id_pendaftaran']]);
                  $bukti_data = $stmt->fetch(PDO::FETCH_ASSOC);

                  if ($bukti_data && $bukti_data['tgl_upload']) {
                    $tgl_upload_bukti = new DateTime($bukti_data['tgl_upload']);
                    $tgl_estimasi_selesai = clone $tgl_upload_bukti;
                    $tgl_estimasi_selesai->add(new DateInterval('P1Y6M')); // Tambah 1 tahun 6 bulan

                    $tgl_estimasi_formatted = $tgl_estimasi_selesai->format('Y-m-d H:i:s');

                    // Format tanggal dalam Bahasa Indonesia
                    $bulan_indonesia = [
                      1 => 'Januari',
                      2 => 'Februari',
                      3 => 'Maret',
                      4 => 'April',
                      5 => 'Mei',
                      6 => 'Juni',
                      7 => 'Juli',
                      8 => 'Agustus',
                      9 => 'September',
                      10 => 'Oktober',
                      11 => 'November',
                      12 => 'Desember'
                    ];

                    $tanggal = $tgl_estimasi_selesai->format('d');
                    $bulan = $bulan_indonesia[(int)$tgl_estimasi_selesai->format('m')];
                    $tahun = $tgl_estimasi_selesai->format('Y');
                    $jam = $tgl_estimasi_selesai->format('H:i');

                    $tgl_estimasi_indo = "$tanggal $bulan $tahun, $jam";
                  ?>
                    <div class="alert alert-info mt-3" role="alert" id="countdown-alert">
                      <i class="fa-solid fa-clock me-2"></i>
                      <strong>Estimasi Proses Verifikasi Kementerian:</strong>
                      <div class="mt-2" style="font-size: 1.1rem; font-weight: 600;">
                        <span id="countdown-timer">Menghitung...</span>
                      </div>
                      <small class="d-block mt-2 text-muted">
                        Diperkirakan selesai pada: <?php echo $tgl_estimasi_indo; ?> WIB
                      </small>
                    </div>

                    <script>
                      // Set target date untuk countdown (1 tahun 6 bulan dari upload bukti pendaftaran)
                      const targetDate = new Date('<?php echo $tgl_estimasi_formatted; ?>').getTime();
                      const startDate = new Date('<?php echo $tgl_upload_bukti->format('Y-m-d H:i:s'); ?>').getTime();

                      function updateCountdown() {
                        const now = new Date().getTime();
                        const distance = targetDate - now;

                        if (distance < 0) {
                          document.getElementById('countdown-timer').innerHTML = 'Estimasi waktu telah berakhir';
                          document.getElementById('countdown-alert').classList.remove('alert-info');
                          document.getElementById('countdown-alert').classList.add('alert-warning');
                          clearInterval(countdownInterval);
                          return;
                        }

                        // Hitung mundur menggunakan date object untuk akurasi bulan
                        const currentDate = new Date(now);
                        const endDate = new Date(targetDate);

                        let years = endDate.getFullYear() - currentDate.getFullYear();
                        let months = endDate.getMonth() - currentDate.getMonth();
                        let days = endDate.getDate() - currentDate.getDate();
                        let hours = endDate.getHours() - currentDate.getHours();
                        let minutes = endDate.getMinutes() - currentDate.getMinutes();
                        let seconds = endDate.getSeconds() - currentDate.getSeconds();

                        // Adjustment untuk nilai negatif
                        if (seconds < 0) {
                          seconds += 60;
                          minutes--;
                        }
                        if (minutes < 0) {
                          minutes += 60;
                          hours--;
                        }
                        if (hours < 0) {
                          hours += 24;
                          days--;
                        }
                        if (days < 0) {
                          const prevMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 0);
                          days += prevMonth.getDate();
                          months--;
                        }
                        if (months < 0) {
                          months += 12;
                          years--;
                        }

                        // Format output - hanya tampilkan yang tidak nol
                        let countdownText = '';

                        if (years > 0) countdownText += years + ' tahun ';
                        if (months > 0) countdownText += months + ' bulan ';
                        if (days > 0) countdownText += days + ' hari ';
                        countdownText += hours + ' jam ' + minutes + ' menit ' + seconds + ' detik';

                        document.getElementById('countdown-timer').innerHTML = countdownText.trim();

                        // Ubah warna alert jika kurang dari 3 bulan
                        const threeMonthsInMs = 90 * 24 * 60 * 60 * 1000;
                        const alertElement = document.getElementById('countdown-alert');

                        if (distance < threeMonthsInMs) {
                          alertElement.classList.remove('alert-info');
                          alertElement.classList.add('alert-warning');
                        }
                      }

                      // Update countdown setiap 1 detik
                      updateCountdown();
                      const countdownInterval = setInterval(updateCountdown, 1000);
                    </script>
                  <?php } else { ?>
                    <div class="alert alert-info mt-3" role="alert">
                      <i class="fa-solid fa-clock me-2"></i>
                      <strong>Estimasi Proses Verifikasi Kementerian: 1 tahun 6 bulan</strong>
                    </div>
                  <?php } ?>

                  <div class="alert alert-success mt-3" role="alert">
                    <i class="fa-solid fa-check-circle me-2"></i>
                    Merek Anda telah diajukan ke Kementerian. Proses verifikasi sedang berlangsung. Anda akan mendapatkan notifikasi jika hasil verifikasi kementerian terbit.
                  </div>

                <?php elseif ($statusKey === 'sertifikatterbit'): ?>
                  <p class="m-0 mb-4"><?php echo htmlspecialchars($data['desc']); ?></p>

                  <!-- Cek file mana yang tersedia -->
                  <?php if ($sertifikatMerek && file_exists($sertifikatMerek)): ?>
                    <!-- JIKA ADA SERTIFIKAT (DITERIMA) -->
                    <div class="card border-success">
                      <div class="card-body">
                        <div class="text-center mb-3">
                          <i class="fa-solid fa-award text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h5 class="text-center text-success fw-bold mb-3">
                          Selamat! Merek Anda Telah Terdaftar
                        </h5>
                        <div class="alert alert-success mb-3">
                          <i class="fa-solid fa-check-circle me-2"></i>
                          <strong>Sertifikat Merek Tersedia</strong>
                          <p class="mb-0 mt-2">Sertifikat merek Anda telah diterbitkan oleh Kementerian. Silakan download di bawah ini.</p>
                        </div>

                        <div class="d-grid gap-2">
                          <a class="btn btn-success btn-lg fs-6" href="<?php echo htmlspecialchars($sertifikatMerek); ?>" target="_blank" download>
                            <i class="fa-solid fa-download me-2"></i> Download Sertifikat Merek
                          </a>
                        </div>

                        <?php
                        // Ambil tanggal upload Sertifikat Merek untuk countdown
 $stmt = $pdo->prepare("SELECT tgl_upload, tanggal_terbit FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 7 ORDER BY tgl_upload DESC LIMIT 1");
                        $stmt->execute([$pendaftaran['id_pendaftaran']]);
                        $sertifikat_data = $stmt->fetch(PDO::FETCH_ASSOC);

 // Inisialisasi variabel
 $bisa_perpanjang = false;
 $sudah_kadaluarsa = false;
 
 if ($sertifikat_data && !empty($sertifikat_data['tanggal_terbit'])) {
   $tgl_terbit_sertifikat = new DateTime($sertifikat_data['tanggal_terbit']);
                          $tgl_kadaluarsa = clone $tgl_terbit_sertifikat;
                          $tgl_kadaluarsa->add(new DateInterval('P10Y')); // Tambah 10 tahun
                          $tgl_kadaluarsa->setTime(0, 0, 0); // Set ke jam 00:00:00

                          $tgl_kadaluarsa_formatted = $tgl_kadaluarsa->format('Y-m-d H:i:s');

                          // Hitung tanggal batas perpanjangan (1 tahun sebelum kadaluarsa)
                          $tgl_batas_perpanjangan = clone $tgl_kadaluarsa;
                          $tgl_batas_perpanjangan->sub(new DateInterval('P1Y')); // Kurangi 1 tahun

                          $tanggal_sekarang = new DateTime();

                          // Cek apakah sudah waktunya perpanjangan (sisa waktu <= 1 tahun)
                          $bisa_perpanjang = ($tanggal_sekarang >= $tgl_batas_perpanjangan && $tanggal_sekarang < $tgl_kadaluarsa);
                          $sudah_kadaluarsa = ($tanggal_sekarang >= $tgl_kadaluarsa);

                          // Format tanggal dalam Bahasa Indonesia
                          $bulan_indonesia = [
                            1 => 'Januari',
                            2 => 'Februari',
                            3 => 'Maret',
                            4 => 'April',
                            5 => 'Mei',
                            6 => 'Juni',
                            7 => 'Juli',
                            8 => 'Agustus',
                            9 => 'September',
                            10 => 'Oktober',
                            11 => 'November',
                            12 => 'Desember'
                          ];

                          $tanggal = $tgl_kadaluarsa->format('d');
                          $bulan = $bulan_indonesia[(int)$tgl_kadaluarsa->format('m')];
                          $tahun = $tgl_kadaluarsa->format('Y');

                          $tgl_kadaluarsa_indo = "$tanggal $bulan $tahun";
                        ?>
                          <!-- COUNTDOWN TIMER -->
                          <div class="alert <?php echo $sudah_kadaluarsa ? 'alert-danger' : ($bisa_perpanjang ? 'alert-warning' : 'alert-info'); ?> mt-3" role="alert" id="countdown-sertifikat-alert">
                            <?php if ($sudah_kadaluarsa): ?>
                              <i class="fa-solid fa-exclamation-circle me-2"></i>
                              <strong>Sertifikat Sudah Kadaluarsa!</strong>
                              <div class="mt-2" style="font-size: 1.1rem; font-weight: 600; color: #dc3545;">
                                Masa berlaku sertifikat telah berakhir
                              </div>
                            <?php elseif ($bisa_perpanjang): ?>
                              <i class="fa-solid fa-exclamation-triangle me-2"></i>
                              <strong>Waktu Perpanjangan Telah Tiba!</strong>
                              <div class="mt-2" style="font-size: 1.1rem; font-weight: 600;">
                                <span id="countdown-sertifikat-timer">Menghitung...</span>
                              </div>
                            <?php else: ?>
                              <i class="fa-solid fa-hourglass-half me-2"></i>
                              <strong>Masa Berlaku Sertifikat:</strong>
                              <div class="mt-2" style="font-size: 1.1rem; font-weight: 600;">
                                <span id="countdown-sertifikat-timer">Menghitung...</span>
                              </div>
                            <?php endif; ?>

                            <small class="d-block mt-2 text-muted">
                              Sertifikat berlaku hingga: <?php echo $tgl_kadaluarsa_indo; ?> pukul 00:00 WIB
                            </small>
                          </div>

                          <script>
                            // Set target date untuk countdown sertifikat (10 tahun dari terbit)
                            const targetDateSertifikat = new Date('<?php echo $tgl_kadaluarsa_formatted; ?>').getTime();
                            const startDateSertifikat = new Date('<?php echo $tgl_terbit_sertifikat->format('Y-m-d H:i:s'); ?>').getTime();

                            function updateCountdownSertifikat() {
                              const now = new Date().getTime();
                              const distance = targetDateSertifikat - now;

                              if (distance < 0) {
                                document.getElementById('countdown-sertifikat-timer').innerHTML = 'Masa berlaku sertifikat telah berakhir';
                                document.getElementById('countdown-sertifikat-alert').classList.remove('alert-warning', 'alert-info');
                                document.getElementById('countdown-sertifikat-alert').classList.add('alert-danger');
                                clearInterval(countdownSertifikatInterval);
                                return;
                              }

                              // Hitung mundur menggunakan date object untuk akurasi bulan
                              const currentDate = new Date(now);
                              const endDate = new Date(targetDateSertifikat);

                              let years = endDate.getFullYear() - currentDate.getFullYear();
                              let months = endDate.getMonth() - currentDate.getMonth();
                              let days = endDate.getDate() - currentDate.getDate();
                              let hours = endDate.getHours() - currentDate.getHours();
                              let minutes = endDate.getMinutes() - currentDate.getMinutes();
                              let seconds = endDate.getSeconds() - currentDate.getSeconds();

                              // Adjustment untuk nilai negatif
                              if (seconds < 0) {
                                seconds += 60;
                                minutes--;
                              }
                              if (minutes < 0) {
                                minutes += 60;
                                hours--;
                              }
                              if (hours < 0) {
                                hours += 24;
                                days--;
                              }
                              if (days < 0) {
                                const prevMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 0);
                                days += prevMonth.getDate();
                                months--;
                              }
                              if (months < 0) {
                                months += 12;
                                years--;
                              }

                              // Format output
                              let countdownText = '';

                              if (years > 0) countdownText += years + ' tahun ';
                              if (months > 0) countdownText += months + ' bulan ';
                              if (days > 0) countdownText += days + ' hari ';
                              countdownText += hours + ' jam ' + minutes + ' menit ' + seconds + ' detik';

                              document.getElementById('countdown-sertifikat-timer').innerHTML = countdownText.trim();

                              // Ubah warna alert jika kurang dari 1 tahun
                              const oneYearInMs = 365 * 24 * 60 * 60 * 1000;
                              const alertElement = document.getElementById('countdown-sertifikat-alert');

                              if (distance < oneYearInMs) {
                                alertElement.classList.remove('alert-info');
                                alertElement.classList.add('alert-warning');
                              }
                            }

                            // Update countdown setiap 1 detik
                            updateCountdownSertifikat();
                            const countdownSertifikatInterval = setInterval(updateCountdownSertifikat, 1000);
                          </script>
                        <?php } ?>

                        <!-- INFORMASI PENTING -->
                        <div class="alert alert-info mt-3 mb-0">
                          <i class="fa-solid fa-info-circle me-2"></i>
                          <strong>Informasi Penting:</strong>
                          <ul class="mb-0 mt-2" style="padding-left: 20px;">
                            <li><strong>Masa Berlaku:</strong> 10 tahun sejak tanggal penerbitan</li>
                            <li><strong>Perlindungan:</strong> Merek Anda dilindungi secara hukum di Indonesia</li>
                            <?php if ($bisa_perpanjang || $sudah_kadaluarsa): ?>
                              <li class="text-danger"><strong>Perpanjangan:</strong> Anda dapat mengajukan perpanjangan sertifikat</li>
                            <?php endif; ?>
                          </ul>
                        </div>

                        <!-- BUTTON PERPANJANGAN - MUNCUL JIKA SISA WAKTU <= 1 TAHUN -->
                        <?php if ($bisa_perpanjang || $sudah_kadaluarsa): ?>
                          <div class="mt-4 p-4 bg-warning bg-opacity-10 rounded border border-warning">
                            <div class="text-center">
                              <i class="fa-solid fa-rotate text-warning mb-3" style="font-size: 3rem;"></i>
                              <h5 class="fw-bold mb-3">
                                <?php echo $sudah_kadaluarsa ? 'Sertifikat Telah Kadaluarsa' : 'Saatnya Perpanjangan Sertifikat'; ?>
                              </h5>
                              <p class="mb-4 text-muted">
                                <?php if ($sudah_kadaluarsa): ?>
                                  Sertifikat merek Anda telah kadaluarsa. Segera ajukan perpanjangan untuk melindungi merek Anda.
                                <?php else: ?>
                                  Masa berlaku sertifikat Anda tinggal kurang dari 1 tahun. Ajukan perpanjangan sekarang untuk memastikan perlindungan merek Anda tetap aktif.
                                <?php endif; ?>
                              </p>
                              <div class="d-grid gap-2 col-md-6 mx-auto">
                                <a href="form-perpanjangan-mandiri.php" class="btn btn-warning btn-lg fs-6">
                                  <i class="fa-solid fa-file-invoice me-2"></i>
                                  Ajukan Perpanjangan Sertifikat
                                </a>
                              </div>
                              <small class="d-block mt-3 text-muted">
                                <i class="fa-solid fa-info-circle me-1"></i>
                                Perpanjangan dapat dilakukan <?php echo $sudah_kadaluarsa ? 'meskipun sertifikat sudah kadaluarsa' : 'mulai sekarang'; ?>
                              </small>
                            </div>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>

                  <?php elseif ($suratPenolakan && file_exists($suratPenolakan)): ?>
                    <!-- JIKA ADA SURAT PENOLAKAN (DITOLAK) -->
                    <!-- Bagian ini tetap sama seperti sebelumnya -->
                    <div class="card border-danger">
                      <div class="card-body">
                        <div class="text-center mb-3">
                          <i class="fa-solid fa-times-circle text-danger" style="font-size: 4rem;"></i>
                        </div>
                        <h5 class="text-center text-danger fw-bold mb-3">
                          Permohonan Merek Ditolak
                        </h5>
                        <div class="alert alert-danger mb-3">
                          <i class="fa-solid fa-exclamation-triangle me-2"></i>
                          <strong>Surat Penolakan dari Kementerian</strong>
                          <p class="mb-0 mt-2">Mohon maaf, permohonan merek Anda tidak dapat disetujui oleh Kementerian. Silakan download surat penolakan untuk mengetahui alasan detail.</p>
                        </div>

                        <div class="d-grid gap-2">
                          <a class="btn btn-danger btn-lg" href="<?php echo htmlspecialchars($suratPenolakan); ?>" target="_blank" download>
                            <i class="fa-solid fa-download me-2"></i> Download Surat Penolakan
                          </a>
                        </div>

                        <div class="alert alert-warning mt-3 mb-0">
                          <i class="fa-solid fa-lightbulb me-2"></i>
                          <strong>Informasi Penting!</strong>
                          <ul class="mb-0 mt-2" style="padding-left: 20px;">
                            <li>Mohon maaf untuk fasilitasi merk gratis tidak bisa dilanjutkan.</li>
                            <li>Anda tidak bisa mengajukan kembali fasilitasi merk di Dinas Perindustrian dan Perdagangan Kab. Sidoarjo</li>
                            <li>Silahkan mengajukan Mandiri atau hubungi Admin Dinas Perindustrian dan Perdagangan Kab. Sidoarjo untuk informasi lebih lanjut</li>
                          </ul>
                        </div>
                      </div>
                    </div>

                  <?php else: ?>
                    <!-- JIKA BELUM ADA FILE -->
                    <div class="alert alert-warning">
                      <i class="fa-solid fa-clock me-2"></i>
                      <strong>Menunggu Hasil Verifikasi</strong>
                      <p class="mb-0 mt-2">Hasil verifikasi dari Kementerian belum tersedia. Silakan hubungi admin untuk informasi lebih lanjut.</p>
                    </div>
                  <?php endif; ?>

                <?php else: ?>
                  <!-- Status lainnya tetap sama -->
                  <p class="m-0"><?php echo htmlspecialchars($data['desc']); ?></p>
                <?php endif; ?>

                <!-- SECTION DOKUMEN YANG TERSEDIA -->
                <?php
                // Cek dokumen mana saja yang tersedia
                $ada_dokumen = false;

                // Cek Surat Keterangan IKM
                $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 5 ORDER BY tgl_upload DESC LIMIT 1");
                $stmt->execute([$pendaftaran['id_pendaftaran']]);
                $doc_surat_ikm = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($doc_surat_ikm && file_exists($doc_surat_ikm['file_path'])) $ada_dokumen = true;

                // Cek Bukti Pendaftaran
                $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 6 ORDER BY tgl_upload DESC LIMIT 1");
                $stmt->execute([$pendaftaran['id_pendaftaran']]);
                $doc_bukti_pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($doc_bukti_pendaftaran && file_exists($doc_bukti_pendaftaran['file_path'])) $ada_dokumen = true;

                // Cek Surat Berkas Difasilitasi (dari pemohon)
                $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 4 ORDER BY tgl_upload DESC LIMIT 1");
                $stmt->execute([$pendaftaran['id_pendaftaran']]);
                $doc_berkas_pemohon = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($doc_berkas_pemohon && file_exists($doc_berkas_pemohon['file_path'])) $ada_dokumen = true;
                ?>

                <?php if ($ada_dokumen): ?>
                  <div class="info-card mt-4">
                    <div class="info-header">
                      <h2 class="info-title">
                        <i class="fa-solid fa-folder-open me-2"></i>
                        Dokumen yang Tersedia
                      </h2>
                    </div>

                    <hr class="border-2 border-secondary w-100 line" />

                    <p class="text-muted mb-4">
                      <i class="fa-solid fa-info-circle me-2"></i>
                      Berikut adalah dokumen-dokumen yang telah diupload dan tersedia untuk diunduh kapan saja.
                    </p>

                    <div class="row g-3">
                      <!-- Surat Berkas Difasilitasi dari Pemohon -->
                      <?php if ($doc_berkas_pemohon && file_exists($doc_berkas_pemohon['file_path'])): ?>
                        <div class="col-md-6 col-lg-4">
                          <div class="card border-secondary h-100">
                            <div class="card-body">
                              <div class="text-center mb-3">
                                <i class="fa-solid fa-file-signature text-secondary" style="font-size: 3rem;"></i>
                              </div>
                              <h6 class="fw-bold text-center mb-3">Surat Kelengkapan Berkas Difasilitasi</h6>
                              <div class="alert alert-success mb-3">
                                <small>
                                  <i class="fa-solid fa-check-circle me-1"></i>
                                  <strong>File Tersedia</strong>
                                </small>
                                <p class="mb-0 mt-1" style="font-size: 0.85rem;">
                                  <i class="fa-solid fa-calendar me-1"></i>
                                  Diupload: <?php echo date('d/m/Y H:i', strtotime($doc_berkas_pemohon['tgl_upload'])); ?> WIB
                                </p>
                              </div>
                              <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-secondary btn-view"
                                  data-src="<?php echo htmlspecialchars($doc_berkas_pemohon['file_path']); ?>"
                                  data-title="Surat Kelengkapan Berkas">
                                  <i class="bi bi-eye me-1"></i>Preview
                                </button>
                                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($doc_berkas_pemohon['file_path']); ?>" download>
                                  <i class="fa-solid fa-download me-1"></i> Download
                                </a>
                              </div>
                            </div>
                          </div>
                        </div>
                      <?php endif; ?>

                      <!-- Surat Keterangan IKM -->
                      <?php if ($doc_surat_ikm && file_exists($doc_surat_ikm['file_path'])): ?>
                        <div class="col-md-6 col-lg-4">
                          <div class="card border-primary h-100">
                            <div class="card-body">
                              <div class="text-center mb-3">
                                <i class="fa-solid fa-file text-primary" style="font-size: 3rem;"></i>
                              </div>
                              <h6 class="fw-bold text-center mb-3">Surat Keterangan IKM</h6>
                              <div class="alert alert-success mb-3">
                                <small>
                                  <i class="fa-solid fa-check-circle me-1"></i>
                                  <strong>File Tersedia</strong>
                                </small>
                                <p class="mb-0 mt-1" style="font-size: 0.85rem;">
                                  <i class="fa-solid fa-calendar me-1"></i>
                                  Diupload: <?php echo date('d/m/Y H:i', strtotime($doc_surat_ikm['tgl_upload'])); ?> WIB
                                </p>
                              </div>
                              <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-primary btn-view"
                                  data-src="<?php echo htmlspecialchars($doc_surat_ikm['file_path']); ?>"
                                  data-title="Surat IKM">
                                  <i class="bi bi-eye me-1"></i>Preview
                                </button>
                                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($doc_surat_ikm['file_path']); ?>" download>
                                  <i class="fa-solid fa-download me-1"></i> Download
                                </a>
                              </div>
                            </div>
                          </div>
                        </div>
                      <?php endif; ?>

                      <!-- Bukti Pendaftaran -->
                      <?php if ($doc_bukti_pendaftaran && file_exists($doc_bukti_pendaftaran['file_path'])): ?>
                        <div class="col-md-6 col-lg-4">
                          <div class="card border-success h-100">
                            <div class="card-body">
                              <div class="text-center mb-3">
                                <i class="fa-solid fa-file-circle-check text-success" style="font-size: 3rem;"></i>
                              </div>
                              <h6 class="fw-bold text-center mb-3">Bukti Pendaftaran</h6>
                              <div class="alert alert-success mb-3">
                                <small>
                                  <i class="fa-solid fa-check-circle me-1"></i>
                                  <strong>File Tersedia</strong>
                                </small>
                                <p class="mb-0 mt-1" style="font-size: 0.85rem;">
                                  <i class="fa-solid fa-calendar me-1"></i>
                                  Diupload: <?php echo date('d/m/Y H:i', strtotime($doc_bukti_pendaftaran['tgl_upload'])); ?> WIB
                                </p>
                              </div>
                              <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-success btn-view"
                                  data-src="<?php echo htmlspecialchars($doc_bukti_pendaftaran['file_path']); ?>"
                                  data-title="Bukti Pendaftaran">
                                  <i class="bi bi-eye me-1"></i>Preview
                                </button>
                                <a class="btn btn-success btn-sm" href="<?php echo htmlspecialchars($doc_bukti_pendaftaran['file_path']); ?>" download>
                                  <i class="fa-solid fa-download me-1"></i> Download
                                </a>
                              </div>
                            </div>
                          </div>
                        </div>
                      <?php endif; ?>

                      <!-- Sertifikat Merek (jika sudah terbit) -->
                      <?php if ($sertifikatMerek && file_exists($sertifikatMerek)): ?>
                        <div class="col-md-6 col-lg-4">
                          <div class="card border-warning h-100">
                            <div class="card-body">
                              <div class="text-center mb-3">
                                <i class="fa-solid fa-award text-warning" style="font-size: 3rem;"></i>
                              </div>
                              <h6 class="fw-bold text-center mb-3">Sertifikat Merek</h6>
                              <div class="alert alert-success mb-3">
                                <small>
                                  <i class="fa-solid fa-check-circle me-1"></i>
                                  <strong>File Tersedia</strong>
                                </small>
                                <p class="mb-0 mt-1" style="font-size: 0.85rem;">
                                  <i class="fa-solid fa-shield-halved me-1"></i>
                                  Merek Terdaftar Resmi
                                </p>
                              </div>
                              <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-warning btn-view"
                                  data-src="<?php echo htmlspecialchars($sertifikatMerek); ?>"
                                  data-title="Sertifikat Merek">
                                  <i class="bi bi-eye me-1"></i>Preview
                                </button>
                                <a class="btn btn-warning btn-sm" href="<?php echo htmlspecialchars($sertifikatMerek); ?>" download>
                                  <i class="fa-solid fa-download me-1"></i> Download
                                </a>
                              </div>
                            </div>
                          </div>
                        </div>
                      <?php endif; ?>

                      <!-- Surat Penolakan (jika ada) -->
                      <?php if ($suratPenolakan && file_exists($suratPenolakan)): ?>
                        <div class="col-md-6 col-lg-4">
                          <div class="card border-danger h-100">
                            <div class="card-body">
                              <div class="text-center mb-3">
                                <i class="fa-solid fa-file-circle-xmark text-danger" style="font-size: 3rem;"></i>
                              </div>
                              <h6 class="fw-bold text-center mb-3">Surat Penolakan</h6>
                              <div class="alert alert-danger mb-3">
                                <small>
                                  <i class="fa-solid fa-exclamation-triangle me-1"></i>
                                  <strong>File Tersedia</strong>
                                </small>
                                <p class="mb-0 mt-1" style="font-size: 0.85rem;">
                                  <i class="fa-solid fa-info-circle me-1"></i>
                                  Dari Kementerian
                                </p>
                              </div>
                              <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-danger btn-view"
                                  data-src="<?php echo htmlspecialchars($suratPenolakan); ?>"
                                  data-title="Surat Penolakan">
                                  <i class="bi bi-eye me-1"></i>Preview
                                </button>
                                <a class="btn btn-danger btn-sm" href="<?php echo htmlspecialchars($suratPenolakan); ?>" download>
                                  <i class="fa-solid fa-download me-1"></i> Download
                                </a>
                              </div>
                            </div>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endif; ?>

              </div>
            </div>
          </div>
        </div>

        <div>
          <strong>Nama Pemohon:</strong><br />
          <p><?php echo strtoupper(htmlspecialchars($nama)); ?></p>
          <div class="mb-2 mt-2">
            <strong>Nama Usaha:</strong><br />
            <p><?php echo htmlspecialchars($pendaftaran['nama_usaha']); ?></p>
          </div>
          <div class="mb-2">
            <strong>Tanggal Pendaftaran:</strong><br />
            <p><?php echo date('d F Y, H:i', strtotime($pendaftaran['tgl_daftar'])); ?> WIB</p>
          </div>
          <div class="mb-4">
            <strong>Merek yang Didaftarkan:</strong><br />
          </div>
        </div>

        <!-- Kartu alternatif merek -->
        <?php
        $merek_difasilitasi = $pendaftaran['merek_difasilitasi'];

        $border1 = '';
        $border2 = '';
        $border3 = '';
        $badge1 = '';
        $badge2 = '';
        $badge3 = '';

        if ($merek_difasilitasi) {
          if ($merek_difasilitasi == 1) {
            $border1 = 'border-success border-3';
            $badge1 = '<span class="badge bg-success ms-2 mb-3">Difasilitasi</span>';
            $border2 = 'border-danger border-2';
            $badge2 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
            $border3 = 'border-danger border-2';
            $badge3 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
          } elseif ($merek_difasilitasi == 2) {
            $border1 = 'border-danger border-2';
            $badge1 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
            $border2 = 'border-success border-3';
            $badge2 = '<span class="badge bg-success ms-2 mb-3">Difasilitasi</span>';
            $border3 = 'border-danger border-2';
            $badge3 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
          } elseif ($merek_difasilitasi == 3) {
            $border1 = 'border-danger border-2';
            $badge1 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
            $border2 = 'border-danger border-2';
            $badge2 = '<span class="badge bg-danger ms-2 mb-3">Tidak Difasilitasi</span>';
            $border3 = 'border-success border-3';
            $badge3 = '<span class="badge bg-success ms-2 mb-3">Difasilitasi</span>';
          }
        }
        ?>

        <div class="row">
          <div class="col-md-4 mb-4">
            <div class="brand-card <?php echo $border1; ?>">
              <h3 class="brand-title">Merek Alternatif 1 (diutamakan)</h3>
              <?php echo $badge1; ?>
              <div class="brand-name-label">Nama Merek Alternatif 1</div>
              <div class="brand-name-display"><?php echo htmlspecialchars($pendaftaran['nama_merek1']); ?></div>
              <div class="logo-label">Logo Merek Alternatif 1</div>
              <div class="logo-container">
                <?php if ($pendaftaran['logo1'] && file_exists($pendaftaran['logo1'])): ?>
                  <img src="<?php echo htmlspecialchars($pendaftaran['logo1']); ?>" alt="Logo 1" style="max-width: 200px; max-height: 200px;">
                <?php else: ?>
                  <i class="fas fa-image brand-logo"></i>
                  <div class="brand-logo-text"><?php echo htmlspecialchars($pendaftaran['nama_merek1']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-md-4 mb-4">
            <div class="brand-card <?php echo $border2; ?>">
              <h3 class="brand-title">Merek Alternatif 2</h3>
              <?php echo $badge2; ?>
              <div class="brand-name-label">Nama Merek Alternatif 2</div>
              <div class="brand-name-display"><?php echo htmlspecialchars($pendaftaran['nama_merek2']); ?></div>
              <div class="logo-label">Logo Merek Alternatif 2</div>
              <div class="logo-container">
                <?php if ($pendaftaran['logo2'] && file_exists($pendaftaran['logo2'])): ?>
                  <img src="<?php echo htmlspecialchars($pendaftaran['logo2']); ?>" alt="Logo 2" style="max-width: 200px; max-height: 200px;">
                <?php else: ?>
                  <i class="fas fa-image brand-logo"></i>
                  <div class="brand-logo-text"><?php echo htmlspecialchars($pendaftaran['nama_merek2']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-md-4 mb-4">
            <div class="brand-card <?php echo $border3; ?>">
              <h3 class="brand-title">Merek Alternatif 3</h3>
              <?php echo $badge3; ?>
              <div class="brand-name-label">Nama Merek Alternatif 3</div>
              <div class="brand-name-display"><?php echo htmlspecialchars($pendaftaran['nama_merek3']); ?></div>
              <div class="logo-label">Logo Merek Alternatif 3</div>
              <div class="logo-container">
                <?php if ($pendaftaran['logo3'] && file_exists($pendaftaran['logo3'])): ?>
                  <img src="<?php echo htmlspecialchars($pendaftaran['logo3']); ?>" alt="Logo 3" style="max-width: 200px; max-height: 200px;">
                <?php else: ?>
                  <i class="fas fa-image brand-logo"></i>
                  <div class="brand-logo-text"><?php echo htmlspecialchars($pendaftaran['nama_merek3']); ?></div>
                <?php endif; ?>
              </div>
            </div>
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

  <footer class="footer">
    <div class="container text-center">
      <p class="mb-1">Copyright © 2025. All Rights Reserved.</p>
      <p class="mb-0">Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // BOOTSTRAP ALERT & CONFIRM MODALS

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
    // Handler untuk tombol Lanjut (Konfirmasi Lanjut)
    const btnLanjut = document.getElementById('btnLanjut');
    if (btnLanjut) {
      btnLanjut.addEventListener('click', function() {
        showConfirm('Apakah Anda yakin ingin melanjutkan dengan Merek Alternatif <?php echo $pendaftaran['merek_difasilitasi'] ?? '2'; ?>?', function() {
          btnLanjut.disabled = true;
          btnLanjut.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Memproses...';

          const formData = new FormData();
          formData.append('ajax_action', 'konfirmasi_lanjut');
          formData.append('id_pendaftaran', <?php echo $pendaftaran['id_pendaftaran']; ?>);

          fetch(window.location.href, {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                showAlert('Konfirmasi berhasil! Status diperbarui ke Surat Keterangan Difasilitasi. Silakan download dan upload surat yang sudah ditandatangani.', 'success');
                setTimeout(() => location.reload(), 2000);
              } else {
                showAlert('Terjadi kesalahan: ' + data.message, 'danger');
                btnLanjut.disabled = false;
                btnLanjut.innerHTML = 'Lanjut';
              }
            })
            .catch(error => {
              console.error('Error:', error);
              showAlert('Terjadi kesalahan saat mengirim konfirmasi.', 'danger');
              btnLanjut.disabled = false;
              btnLanjut.innerHTML = 'Lanjut';
            });
        });
      });
    }

    // Handler untuk Upload Surat
    const formSurat = document.getElementById('formSurat');
    if (formSurat) {
      formSurat.addEventListener('submit', function(e) {
        e.preventDefault();

        const fileInput = document.getElementById('fileSurat');
        const btnKirim = document.getElementById('btnKirimSurat');

        if (!fileInput.files[0]) {
          showAlert('Silakan pilih file terlebih dahulu!');
          return;
        }

        // Validasi ukuran file (max 5MB)
        if (fileInput.files[0].size > 5 * 1024 * 1024) {
          showAlert('Ukuran file maksimal 5MB!');
          return;
        }

        // Validasi format file
        const allowedExtensions = /(\.pdf)$/i;
        if (!allowedExtensions.exec(fileInput.files[0].name)) {
          showAlert('Format file harus PDF!');
          return;
        }

        showConfirm('Apakah Anda yakin ingin mengirim surat ini?<br><br>Status akan berubah menjadi "Menunggu Bukti Pendaftaran".', function() {
          const formData = new FormData();
          formData.append('ajax_action', 'upload_surat');
          formData.append('fileSurat', fileInput.files[0]);
          formData.append('id_pendaftaran', <?php echo $pendaftaran['id_pendaftaran']; ?>);

          // Disable button dan tampilkan loading
          btnKirim.disabled = true;
          btnKirim.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Mengirim...';

          fetch(window.location.href, {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                showAlert('Surat berhasil dikirim! Status diperbarui ke Menunggu Bukti Pendaftaran. Admin sekarang dapat melihat surat Anda.', 'success');
                setTimeout(() => location.reload(), 2000);
              } else {
                showAlert('Gagal mengirim surat: ' + data.message, 'danger');
                btnKirim.disabled = false;
                btnKirim.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Kirim Surat';
              }
            })
            .catch(error => {
              console.error('Error:', error);
              showAlert('Terjadi kesalahan saat mengirim file.', 'danger');
              btnKirim.disabled = false;
              btnKirim.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Kirim Surat';
            });
        });
      });
    }

    // Auto-refresh
    setInterval(function() {
      location.reload();
    }, 5 * 60 * 1000);

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
          modalPdf.src = src + '#toolbar=0'; // Hide PDF toolbar
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

    // Bersihkan iframe saat modal ditutup
    document.getElementById('imageModal').addEventListener('hidden.bs.modal', function() {
      document.getElementById('modalPdf').src = '';
      document.getElementById('modalImage').src = '';
    });

    // PREVIEW FILE DALAM BENTUK ICON UNTUK STEP 3 UPLOAD SURAT 
    document.addEventListener('DOMContentLoaded', function() {
      const fileInput = document.getElementById('fileSurat');
      const filePreview = document.getElementById('filePreview');
      const fileIcon = document.getElementById('fileIcon');
      const fileName = document.getElementById('fileName');
      const fileSize = document.getElementById('fileSize');
      const btnViewFile = document.getElementById('btnViewFile');

      if (fileInput && filePreview && fileIcon && fileName && fileSize && btnViewFile) {
        console.log('Preview icon elements found');

        let currentFileData = null;
        let currentFileName = null;
        let currentFileType = null;

        fileInput.addEventListener('change', function(e) {
          const file = e.target.files[0];
          console.log('File selected:', file);

          if (!file) {
            filePreview.style.display = 'none';
            currentFileData = null;
            return;
          }

          // Validasi ukuran file
          if (file.size > 5 * 1024 * 1024) {
            showAlert('Ukuran file maksimal 5MB!');
            fileInput.value = '';
            filePreview.style.display = 'none';
            return;
          }

          // Validasi tipe file
          const allowedTypes = ['application/pdf'];
          if (!allowedTypes.includes(file.type)) {
            showAlert('Format file harus PDF!');
            fileInput.value = '';
            filePreview.style.display = 'none';
            return;
          }

          // Tampilkan icon preview
          displayFileIcon(file);

          // Baca file untuk preview modal
          const reader = new FileReader();

          reader.onload = function(e) {
            console.log('File loaded for preview');
            currentFileData = e.target.result;
            currentFileName = file.name;
            currentFileType = file.type;
          };

          reader.onerror = function() {
            console.error('Error reading file');
            showAlert('Error membaca file. Silakan coba file lain.');
          };

          reader.readAsDataURL(file);
        });

        // Fungsi untuk menampilkan icon file
        function displayFileIcon(file) {
          const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);

          // Set icon berdasarkan tipe file
          if (file.type === 'application/pdf') {
            fileIcon.innerHTML = '<i class="fa-solid fa-file-signature text-danger"></i>';
          } else if (file.type === 'image/jpeg' || file.type === 'image/jpg') {
            fileIcon.innerHTML = '<i class="fa-solid fa-file-image text-warning"></i>';
          } else if (file.type === 'image/png') {
            fileIcon.innerHTML = '<i class="fa-solid fa-file-image text-info"></i>';
          } else {
            fileIcon.innerHTML = '<i class="fa-solid fa-file text-secondary"></i>';
          }

          // Set nama dan ukuran file
          fileName.textContent = file.name;
          fileSize.textContent = `${fileSizeMB} MB • ${getFileTypeText(file.type)}`;

          // Tampilkan preview
          filePreview.style.display = 'block';
        }

        // Fungsi untuk mendapatkan teks tipe file
        function getFileTypeText(fileType) {
          switch (fileType) {
            case 'application/pdf':
              return 'PDF Document';
            case 'image/jpeg':
            case 'image/jpg':
              return 'JPEG Image';
            case 'image/png':
              return 'PNG Image';
            default:
              return 'File';
          }
        }

        // Handler untuk tombol Lihat
        btnViewFile.addEventListener('click', function() {
          if (currentFileData && currentFileName && currentFileType) {
            showFullPreview(currentFileData, currentFileName, currentFileType);
          } else {
            showAlert('File belum siap untuk dilihat. Silakan tunggu sebentar.');
          }
        });

        // Fungsi untuk menampilkan preview full size di modal
        function showFullPreview(fileData, fileName, fileType) {
          const modalTitle = document.getElementById('modalTitle');
          const downloadBtn = document.getElementById('downloadBtn');
          const imageContainer = document.getElementById('imageContainer');
          const pdfContainer = document.getElementById('pdfContainer');
          const modalImg = document.getElementById('modalImage');
          const modalPdf = document.getElementById('modalPdf');

          if (!modalTitle) {
            console.error('Modal elements not found');
            return;
          }

          modalTitle.textContent = 'Preview: ' + fileName;

          if (fileType === 'application/pdf') {
            // Tampilkan PDF di modal
            imageContainer.style.display = 'none';
            pdfContainer.style.display = 'block';
            modalPdf.src = fileData + '#toolbar=0';
            downloadBtn.style.display = 'none';
          } else {
            // Tampilkan gambar di modal
            pdfContainer.style.display = 'none';
            imageContainer.style.display = 'block';
            modalImg.src = fileData;
            downloadBtn.style.display = 'block';
            downloadBtn.href = fileData;
            downloadBtn.download = fileName;
          }

          // Buka modal
          const modal = new bootstrap.Modal(document.getElementById('imageModal'));
          modal.show();
        }
      } else {
        console.log('Preview icon elements NOT found');
      }
    });
    // Show alert for new perpanjangan
    <?php if (isset($_SESSION['baru_perpanjangan']) && $_SESSION['baru_perpanjangan'] === true): ?>
      <?php
      unset($_SESSION['baru_perpanjangan']);
      $id_perpanjangan_show = isset($_SESSION['id_perpanjangan_baru']) ? $_SESSION['id_perpanjangan_baru'] : 0;
      unset($_SESSION['id_perpanjangan_baru']);
      ?>

      setTimeout(function() {
        const message = 'Permohonan perpanjangan berhasil diajukan!\n\nSurat permohonan perpanjangan telah otomatis dibuat.\nAnda dapat melihat dan mendownload surat di bagian "Status Perpanjangan Sertifikat" di bawah.';
        showAlert(message, 'success');

        // Scroll ke section perpanjangan
        setTimeout(function() {
          const perpanjanganSection = document.querySelector('.info-card.mt-4');
          if (perpanjanganSection) {
            perpanjanganSection.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
            perpanjanganSection.style.border = '3px solid #28a745';
            setTimeout(() => {
              perpanjanganSection.style.border = '';
            }, 3000);
          }
        }, 500);
      }, 500);
    <?php endif; ?>
  </script>

</body>

</html>