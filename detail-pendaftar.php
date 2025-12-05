<?php
session_start();
require_once 'process/config_db.php';
require_once 'process/notification_helper.php';
require_once 'process/crypto_helper.php';
date_default_timezone_set('Asia/Jakarta');

 // Ambil encrypted ID dari URL
 $encrypted_id = isset($_GET['ref']) ? $_GET['ref'] : '';

 if (empty($encrypted_id)) {
   header("Location: daftar-pendaftaran.php");
   exit();
 }

 $id_pendaftaran = decryptId($encrypted_id);

 if (!$id_pendaftaran || !is_numeric($id_pendaftaran)) {
  header("Location: daftar-pendaftaran.php");
  exit();
}

// ===== HANDLER AJAX REQUEST ===== 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
  header('Content-Type: application/json');

  $action = $_POST['ajax_action'];

  try {
    // HANDLER UPLOAD SURAT KETERANGAN IKM
    if ($action === 'upload_surat_keterangan') {
      if (!isset($_FILES['fileSuratKeterangan']) || $_FILES['fileSuratKeterangan']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File tidak valid']);
        exit;
      }

      $file = $_FILES['fileSuratKeterangan'];
      $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
      $allowed_extensions = ['pdf'];

      if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak diizinkan']);
        exit;
      }

      if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 10MB']);
        exit;
      }

      // Ambil NIK dari pendaftaran
      $stmt = $pdo->prepare("SELECT NIK FROM pendaftaran WHERE id_pendaftaran = ?");
      $stmt->execute([$id_pendaftaran]);
      $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$pendaftaran) {
        echo json_encode(['success' => false, 'message' => 'Data pendaftaran tidak ditemukan']);
        exit;
      }

      $nik = $pendaftaran['NIK'];

      $folder = "uploads/suratikm/";
      if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
      }

      // Nama file: suratikm_NIK.extension
      $filename = "suratikm_" . $nik . "." . $file_extension;
      $target = $folder . $filename;

      if (move_uploaded_file($file['tmp_name'], $target)) {
        $tgl_upload = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
          // Hapus surat keterangan lama jika ada
          $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 5");
          $stmt->execute([$id_pendaftaran]);
          $old_file = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($old_file && file_exists($old_file['file_path'])) {
            unlink($old_file['file_path']);
          }

          $stmt = $pdo->prepare("DELETE FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 5");
          $stmt->execute([$id_pendaftaran]);

          // Simpan surat keterangan baru
          $stmt = $pdo->prepare("INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path) VALUES (?, 5, ?, ?)");
          $stmt->execute([$id_pendaftaran, $tgl_upload, $target]);

          // KIRIM NOTIFIKASI KE PEMOHON
          $notif_sent = notifSuratKeteranganIKM($id_pendaftaran);

          $pdo->commit();
          echo json_encode([
            'success' => true,
            'message' => 'Surat Keterangan IKM berhasil diupload',
            'notif_sent' => $notif_sent
          ]);
        } catch (PDOException $e) {
          $pdo->rollBack();
          if (file_exists($target)) unlink($target);
          throw $e;
        }
      } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
      }
      exit;
    }

    // HANDLER UPLOAD BUKTI PENDAFTARAN
    if ($action === 'upload_bukti_pendaftaran') {
      if (!isset($_FILES['fileBuktiPendaftaran']) || $_FILES['fileBuktiPendaftaran']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File tidak valid']);
        exit;
      }

      $file = $_FILES['fileBuktiPendaftaran'];
      $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
      $allowed_extensions = ['pdf'];

      if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Format file tidak diizinkan']);
        exit;
      }

      if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 10MB']);
        exit;
      }

      // Ambil NIK dari pendaftaran
      $stmt = $pdo->prepare("SELECT NIK FROM pendaftaran WHERE id_pendaftaran = ?");
      $stmt->execute([$id_pendaftaran]);
      $pendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$pendaftaran) {
        echo json_encode(['success' => false, 'message' => 'Data pendaftaran tidak ditemukan']);
        exit;
      }

      $nik = $pendaftaran['NIK'];

      $folder = "uploads/buktipendaftaran/";
      if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
      }

      // Nama file: buktipendaftaran_NIK.extension
      $filename = "buktipendaftaran_" . $nik . "." . $file_extension;
      $target = $folder . $filename;

      if (move_uploaded_file($file['tmp_name'], $target)) {
        $tgl_upload = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
          // Hapus bukti pendaftaran lama jika ada
          $stmt = $pdo->prepare("SELECT file_path FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 6");
          $stmt->execute([$id_pendaftaran]);
          $old_file = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($old_file && file_exists($old_file['file_path'])) {
            unlink($old_file['file_path']);
          }

          $stmt = $pdo->prepare("DELETE FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 6");
          $stmt->execute([$id_pendaftaran]);

          // Simpan bukti pendaftaran baru
          $stmt = $pdo->prepare("INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path) VALUES (?, 6, ?, ?)");
          $stmt->execute([$id_pendaftaran, $tgl_upload, $target]);

          // Update status ke "Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian"
          $stmt = $pdo->prepare("UPDATE pendaftaran SET status_validasi = 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian' WHERE id_pendaftaran = ?");
          $stmt->execute([$id_pendaftaran]);

          // KIRIM NOTIFIKASI KE PEMOHON
          $notif_sent = notifBuktiPendaftaran($id_pendaftaran);

          $pdo->commit();
          echo json_encode([
            'success' => true,
            'message' => 'Bukti Pendaftaran berhasil diupload',
            'new_status' => 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian',
            'notif_sent' => $notif_sent
          ]);
        } catch (PDOException $e) {
          $pdo->rollBack();
          if (file_exists($target)) unlink($target);
          throw $e;
        }
      } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
      }
      exit;
    }
  } catch (PDOException $e) {
    error_log("Error AJAX: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan database: ' . $e->getMessage()]);
    exit;
  }
}



try {
  // Query untuk mengambil data lengkap dengan KOLOM BARU
  $query = "SELECT 
        p.id_pendaftaran,
        p.tgl_daftar,
        p.status_validasi,
        p.merek_difasilitasi,
        p.alasan_tidak_difasilitasi,
        p.alasan_konfirmasi,
        u.NIK_NIP,
        u.nama_lengkap,
        u.no_wa,
        u.email,
        u.rt_rw AS rt_rw_pemilik,
        u.kode_provinsi,
        u.nama_provinsi,
        u.kode_kabupaten,
        u.nama_kabupaten,
        u.kode_kecamatan,
        u.kecamatan AS kecamatan_pemilik,
        u.kode_kel_desa,
        u.kel_desa AS kel_desa_pemilik,
        u.foto_ktp,
        du.id_usaha,
        du.nama_usaha,
        du.rt_rw AS rt_rw_usaha,
        du.kel_desa AS kel_desa_usaha,
        du.kecamatan AS kecamatan_usaha,
        du.no_telp_perusahaan,
        du.hasil_produk,
        du.jml_tenaga_kerja,
        du.kapasitas_produk,
        du.omset_perbulan,
        du.wilayah_pemasaran,
        m.kelas_merek,
        m.nama_merek1,
        m.nama_merek2,
        m.nama_merek3,
        m.logo1,
        m.logo2,
        m.logo3
    FROM pendaftaran p
    INNER JOIN user u ON p.NIK = u.NIK_NIP
    INNER JOIN datausaha du ON p.id_usaha = du.id_usaha
    LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
    WHERE p.id_pendaftaran = :id_pendaftaran";

  $stmt = $pdo->prepare($query);
  $stmt->bindParam(':id_pendaftaran', $id_pendaftaran, PDO::PARAM_INT);
  $stmt->execute();

  $data = $stmt->fetch();

  if (!$data) {
    echo "Data tidak ditemukan";
    exit();
  }

  // Query lampiran tetap sama (tidak berubah)
  $query_lampiran = "SELECT l.*, mf.nama_jenis_file, l.id_jenis_file
    FROM lampiran l
    INNER JOIN masterfilelampiran mf ON l.id_jenis_file = mf.id_jenis_file
    WHERE l.id_pendaftaran = :id_pendaftaran
    ORDER BY l.id_jenis_file";

  $stmt_lampiran = $pdo->prepare($query_lampiran);
  $stmt_lampiran->bindParam(':id_pendaftaran', $id_pendaftaran, PDO::PARAM_INT);
  $stmt_lampiran->execute();

  $lampiran = [];
  while ($row = $stmt_lampiran->fetch()) {
    $lampiran[$row['nama_jenis_file']][] = $row;
  }

  // ===== AMBIL FILE-FILE PENTING =====
  $suratKeterangan = null;
  $suratTTD = null;
  $buktiPendaftaran = null;
  $sertifikatMerek = null;  // TAMBAHKAN INI
  $suratPenolakan = null;

  // Ambil Surat Keterangan IKM
  $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 5 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $suratKeterangan = $stmt->fetch(PDO::FETCH_ASSOC);

  // Ambil Surat TTD dari Pemohon
  $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 4 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $suratTTD = $stmt->fetch(PDO::FETCH_ASSOC);

  // Ambil Bukti Pendaftaran (id_jenis_file = 5)
  $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 6 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $buktiPendaftaran = $stmt->fetch(PDO::FETCH_ASSOC);

  //  SERTIFIKAT
  $stmt = $pdo->prepare("SELECT file_path, tgl_upload, tanggal_terbit FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 7 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $sertifikatMerek = $stmt->fetch(PDO::FETCH_ASSOC);

  //  SURAT PENOLAKAN
  $stmt = $pdo->prepare("SELECT file_path, tgl_upload FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 8 ORDER BY tgl_upload DESC LIMIT 1");
  $stmt->execute([$id_pendaftaran]);
  $suratPenolakan = $stmt->fetch(PDO::FETCH_ASSOC);

  // Format tanggal
  $tgl_daftar = date('d/m/Y H:i:s', strtotime($data['tgl_daftar']));
} catch (PDOException $e) {
  die("Error: " . $e->getMessage());
}

function getBadgeClass($status)
{
  $badges = [
    'Pengecekan Berkas' => 'scan',
    'Berkas Baru' => 'scan',
    'Tidak Bisa Difasilitasi' => 'dangerish',
    'Konfirmasi Lanjut' => 'violet',
    'Surat Keterangan Difasilitasi' => 'infoish',
    'Menunggu Bukti Pendaftaran' => 'emerald',
    'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian' => 'yellow',
    'Hasil Verifikasi Kementerian' => 'mint'
  ];
  return $badges[$status] ?? 'secondary';
}
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Detail Data Pendaftaran</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/detail-pendaftar.css">
  <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>

<body>
  <?php include 'navbar-admin.php' ?>

  <main class="container-xxl main-container">
    <div class="mb-3">
      <h1 class="section-heading mb-1">Detail Data Pendaftaran</h1>
      <p class="lead-note mb-0">Gunakan halaman ini untuk memastikan kelengkapan dan kebenaran data pendaftaran.</p>
    </div>

    <div class="card mb-4">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
          <div>
            <div class="small text-muted-600"><?php echo $tgl_daftar; ?></div>
            <h2 class="h5 fw-bold mb-0"><?php echo strtoupper($data['nama_lengkap']); ?></h2>
          </div>
          <p class="badge text-bg-<?php echo getBadgeClass($data['status_validasi']); ?>  fw-semibold rounded-pill fs-6 py-2" id="statusButton">
            <?php echo $data['status_validasi'] == 'Pengecekan Berkas' ? 'Berkas Baru' : $data['status_validasi']; ?>
          </p>
        </div>

        <!-- ===== SECTION DOWNLOAD SURAT TTD DARI PEMOHON (DI BAWAH NAMA PEMOHON) ===== -->
        <?php if ($suratTTD && file_exists($suratTTD['file_path'])): ?>
          <div class="surat-ttd-section">
            <h5>
              <i class="bi bi-file-earmark-check me-2"></i>
              Surat Keterangan Difasilitasi dari Pemohon
            </h5>
            <p class="text-muted mb-3">
              Pemohon telah mengupload surat kelengkapan yang sudah ditandatangani. Silakan download untuk diproses lebih lanjut.
            </p>
            <div class="file-info bg-white border-success">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                  <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 2.5rem;"></i>
                  <div>
                    <span class="fw-bold d-block">Surat Berkas Difasilitasi</span>
                    <small class="text-muted">
                      <i class="bi bi-clock me-1"></i>
                      Diupload: <?php echo date('d/m/Y H:i', strtotime($suratTTD['tgl_upload'])); ?> WIB
                    </small>
                    <br>
                    <small class="text-muted">
                      <i class="bi bi-file-earmark me-1"></i>
                      <?php echo basename($suratTTD['file_path']); ?>
                    </small>
                  </div>
                </div>
                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-success btn-view"
                    data-src="<?php echo isset($suratTTD['file_path']) ? htmlspecialchars($suratTTD['file_path']) : ''; ?>"
                    data-title="Surat Berkas Difasilitasi">
                    <i class="bi bi-eye me-1"></i>Preview
                  </button>
                  <a href="<?php echo htmlspecialchars($suratTTD['file_path']); ?>"
                    class="btn btn-success"
                    download>
                    <i class="bi bi-download me-2"></i>Download Surat
                  </a>
                </div>
              </div>
            </div>
            <div class="mt-3">
              <span class="status-badge status-tersedia">
                <i class="bi bi-check-circle-fill me-1"></i>File Tersedia untuk Diproses
              </span>
            </div>
          </div>

          <!-- ===== SECTION DOKUMEN YANG SUDAH DIUPLOAD ===== -->
          <?php if (($suratKeterangan && file_exists($suratKeterangan['file_path'])) || ($buktiPendaftaran && file_exists($buktiPendaftaran['file_path']))): ?>
            <div class="document-section mt-4">
              <div class="document-title">
                <i class="bi bi-file-earmark-check-fill me-2"></i>
                Dokumen yang Sudah Diupload
              </div>
              <p class="text-muted mb-3">
                Daftar dokumen yang telah berhasil diupload untuk pendaftaran ini.
              </p>

              <div class="row g-3">
                <!-- Surat Keterangan IKM -->
                <?php if ($suratKeterangan && file_exists($suratKeterangan['file_path'])): ?>
                  <div class="col-md-6">
                    <div class="card h-100 border-primary">
                      <div class="card-body">
                        <h6 class="fw-bold text-primary mb-3">
                          <i class="bi bi-file-earmark-text me-2"></i>Surat Keterangan IKM
                        </h6>
                        <div class="file-info bg-white border-0 p-0">
                          <div class="d-flex align-items-start gap-2 mb-3">
                            <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 2rem;"></i>
                            <div class="flex-grow-1">
                              <span class="fw-semibold d-block">Surat Keterangan IKM</span>
                              <small class="text-muted d-block">
                                <i class="bi bi-clock me-1"></i>
                                <?php echo date('d/m/Y H:i', strtotime($suratKeterangan['tgl_upload'])); ?> WIB
                              </small>
                              <small class="text-muted d-block">
                                <i class="bi bi-file-earmark me-1"></i>
                                <?php echo basename($suratKeterangan['file_path']); ?>
                              </small>
                            </div>
                          </div>
                          <div class="d-grid gap-2">
                            <button class="btn btn-sm btn-outline-primary btn-view"
                              data-src="<?php echo htmlspecialchars($suratKeterangan['file_path']); ?>"
                              data-title="Surat Keterangan IKM">
                              <i class="bi bi-eye me-1"></i>Preview
                            </button>
                            <a href="<?php echo htmlspecialchars($suratKeterangan['file_path']); ?>"
                              class="btn btn-sm btn-primary"
                              download>
                              <i class="bi bi-download me-1"></i>Download
                            </a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Bukti Pendaftaran -->
                <?php if ($buktiPendaftaran && file_exists($buktiPendaftaran['file_path'])): ?>
                  <div class="col-md-6">
                    <div class="card h-100 border-success">
                      <div class="card-body">
                        <h6 class="fw-bold text-success mb-3">
                          <i class="bi bi-file-earmark-check me-2"></i>Bukti Pendaftaran
                        </h6>
                        <div class="file-info bg-white border-0 p-0">
                          <div class="d-flex align-items-start gap-2 mb-3">
                            <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 2rem;"></i>
                            <div class="flex-grow-1">
                              <span class="fw-semibold d-block">Bukti Pendaftaran</span>
                              <small class="text-muted d-block">
                                <i class="bi bi-clock me-1"></i>
                                <?php echo date('d/m/Y H:i', strtotime($buktiPendaftaran['tgl_upload'])); ?> WIB
                              </small>
                              <small class="text-muted d-block">
                                <i class="bi bi-file-earmark me-1"></i>
                                <?php echo basename($buktiPendaftaran['file_path']); ?>
                              </small>
                            </div>
                          </div>
                          <div class="d-grid gap-2">
                            <button class="btn btn-sm btn-outline-success btn-view"
                              data-src="<?php echo htmlspecialchars($buktiPendaftaran['file_path']); ?>"
                              data-title="Bukti Pendaftaran">
                              <i class="bi bi-eye me-1"></i>Preview
                            </button>
                            <a href="<?php echo htmlspecialchars($buktiPendaftaran['file_path']); ?>"
                              class="btn btn-sm btn-success"
                              download>
                              <i class="bi bi-download me-1"></i>Download
                            </a>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <fieldset class="review-box mt-3" id="reviewFieldset">
          <legend>Berkas Baru Ditambahkan</legend>
          <div class="row g-2 align-items-center">
            <div class="col-12 col-lg-8">
              <div class="text-muted-600 small mb-2">
                Merek baru telah didaftarkan oleh pemohon, silakan cek berkasnya terlebih dahulu.
                Jika merek bisa difasilitasi, tekan <strong>Bisa Difasilitasi</strong>.
                Jika tidak bisa difasilitasi, tekan <strong>Tidak Bisa Difasilitasi</strong> dan berikan alasannya.
              </div>
              <div class="d-flex flex-wrap gap-2 mb-2">
                <button type="button" id="btnBisa" class="btn btn-dark btn-pill">
                  Bisa Difasilitasi
                </button>
                <button type="button" id="btnTidakBisa" class="btn btn-outline-danger btn-pill">
                  Tidak Bisa Difasilitasi
                </button>
              </div>
            </div>

            <div class="col-12" id="alasanBox" style="display: none;">
              <div class="input-group mt-2 mt-lg-0">
                <input class="form-control" id="inputAlasan" placeholder="Berikan alasan tidak bisa difasilitasi" />
                <button class="btn btn-dark fw-semibold" id="btnKonfirmasiTidakBisa">Konfirmasi</button>
              </div>
            </div>
          </div>
        </fieldset>

        <!-- ===== SECTION DOWNLOAD SURAT TTD & UPLOAD FILES ===== -->
        <?php if ($data['status_validasi'] === 'Menunggu Bukti Pendaftaran'): ?>
          <div class="mt-4">
            

          <!-- SECTION 2: UPLOAD SURAT KETERANGAN IKM -->
          <div class="document-section">
            <div class="document-title">
              <i class="bi bi-file-earmark-arrow-up me-2"></i>
              Upload Surat Keterangan IKM
            </div>
            <p class="text-muted mb-3">
              Upload Surat Keterangan IKM yang telah diproses untuk diberikan kepada pemohon.
            </p>


            <form id="formSuratKeterangan" enctype="multipart/form-data">
              <div class="upload-box">
                <i class="bi bi-cloud-arrow-up text-primary mb-3" style="font-size: 3rem;"></i>
                <h5>Pilih File Surat Keterangan IKM</h5>
                <p class="text-muted">Format: PDF (Max 10MB)</p>
                <input type="file"
                  class="form-control mt-3"
                  id="fileSuratKeterangan"
                  name="fileSuratKeterangan"
                  accept=".pdf"
                  required>
                <button type="submit" class="btn btn-dark mt-3" id="btnUploadSuratKeterangan">
                  <i class="bi bi-upload me-2"></i>Upload Surat Keterangan IKM
                </button>
              </div>
            </form>
          </div>

          <!-- SECTION 3: UPLOAD BUKTI PENDAFTARAN -->
          <div class="document-section">
            <div class="document-title">
              <i class="bi bi-file-earmark-check me-2"></i>
              Upload Bukti Pendaftaran
            </div>
            <p class="text-muted mb-3">
              Upload Bukti Pendaftaran yang telah diproses. Status akan otomatis berubah menjadi "Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian".
            </p>

            <?php if ($buktiPendaftaran && file_exists($buktiPendaftaran['file_path'])): ?>
              <div class="file-info mb-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <i class="bi bi-file-pdf text-danger me-2" style="font-size: 2rem;"></i>
                    <span class="fw-bold">Bukti Pendaftaran</span>
                    <br>
                    <small class="text-muted">File sudah diupload</small>
                  </div>
                  <div class="d-flex gap-2">
                    <a href="<?php echo htmlspecialchars($buktiPendaftaran['file_path']); ?>"
                      class="btn btn-sm btn-outline-primary"
                      target="_blank">
                      <i class="bi bi-eye me-1"></i>Lihat
                    </a>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <form id="formBuktiPendaftaran" enctype="multipart/form-data">
              <div class="upload-box">
                <i class="bi bi-cloud-arrow-up text-success mb-3" style="font-size: 3rem;"></i>
                <h5>Pilih File Bukti Pendaftaran</h5>
                <p class="text-muted">Format: PDF (Max 10MB)</p>
                <input type="file"
                  class="form-control mt-3"
                  id="fileBuktiPendaftaran"
                  name="fileBuktiPendaftaran"
                  accept=".pdf"
                  required>
                <button type="submit" class="btn btn-success mt-3" id="btnUploadBuktiPendaftaran">
                  <i class="bi bi-upload me-2"></i>Upload Bukti Pendaftaran
                </button>
              </div>
            </form>
          </div>
      </div>
    <?php endif; ?>
    </div>
    </div>

    <!-- Kolom Data Pemilik & Usaha -->
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card mb-4">
          <div class="card-body p-3 p-md-4">
            <h3 class="subsection-title mb-1">Data Pemilik</h3>
            <div class="divider"></div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small">Nama Pemilik</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['nama_lengkap']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">NIK</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['NIK_NIP']); ?>" readonly />
              </div>

              <!-- ALAMAT LENGKAP DENGAN KODE WILAYAH -->
              <div class="col-md-6">
                <label class="form-label small">Provinsi</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['nama_provinsi'] ?? '-'); ?>" readonly />
                <small class="text-muted">Kode: <?php echo htmlspecialchars($data['kode_provinsi'] ?? '-'); ?></small>
              </div>

              <div class="col-md-6">
                <label class="form-label small">Kabupaten/Kota</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['nama_kabupaten'] ?? '-'); ?>" readonly />
                <small class="text-muted">Kode: <?php echo htmlspecialchars($data['kode_kabupaten'] ?? '-'); ?></small>
              </div>

              <div class="col-md-6">
                <label class="form-label small">Kecamatan</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['kecamatan_pemilik'] ?? '-'); ?>" readonly />
                <small class="text-muted">Kode: <?php echo htmlspecialchars($data['kode_kecamatan'] ?? '-'); ?></small>
              </div>

              <div class="col-md-6">
                <label class="form-label small">Kelurahan/Desa</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['kel_desa_pemilik'] ?? '-'); ?>" readonly />
                <small class="text-muted">Kode: <?php echo htmlspecialchars($data['kode_kel_desa'] ?? '-'); ?></small>
              </div>

              <div class="col-md-6">
                <label class="form-label small">RT/RW</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['rt_rw_pemilik'] ?? '-'); ?>" readonly />
              </div>

              <div class="col-md-6">
                <label class="form-label small">Email</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['email'] ?? '-'); ?>" readonly />
              </div>

              <div class="col-12">
                <label class="form-label small">Nomor Telepon/HP Pemilik</label>
                <div class="input-group">
                  <input class="form-control" value="<?php echo htmlspecialchars($data['no_wa']); ?>" readonly style="max-width: 200px;" />
                  <?php
                  $no_telp = preg_replace('/[^0-9]/', '', $data['no_wa']);
                  if (substr($no_telp, 0, 1) === '0') {
                    $no_wauser = '62' . substr($no_telp, 1);
                  } elseif (substr($no_telp, 0, 2) === '62') {
                    $no_wauser = $no_telp;
                  } else {
                    $no_wauser = '62' . $no_telp;
                  }
                  ?>
                  <a href="https://wa.me/<?php echo $no_wauser; ?>" target="_blank" class="btn btn-success" title="Chat via WhatsApp">
                    <i class="bi bi-whatsapp me-1"></i>WhatsApp
                  </a>
                </div>
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
              <div class="col-md-6">
                <label class="form-label small">RT/RW Usaha</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['rt_rw_usaha']); ?>" readonly />
              </div>
              <div class="col-md-6">
                <label class="form-label small">Kelurahan/Desa Usaha</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['kel_desa_usaha']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">Kecamatan Usaha</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['kecamatan_usaha']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">Nomor Telepon Perusahaan</label>
                <div class="input-group">
                  <?php
                  $no_telp = preg_replace('/[^0-9]/', '', $data['no_telp_perusahaan']);
                  if (substr($no_telp, 0, 1) === '0') {
                    $no_wa = '62' . substr($no_telp, 1);
                  } elseif (substr($no_telp, 0, 2) === '62') {
                    $no_wa = $no_telp;
                  } else {
                    $no_wa = '62' . $no_telp;
                  }
                  ?>
                  <input class="form-control" value="<?php echo htmlspecialchars($data['no_telp_perusahaan']); ?>" readonly style="max-width: 200px;" />
                  <a href="https://wa.me/<?php echo $no_wa; ?>" target="_blank" class="btn btn-success" title="Chat via WhatsApp">
                    <i class="bi bi-whatsapp me-1"></i>WhatsApp
                  </a>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label small">Produk-produk yang Dihasilkan</label>
                <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($data['hasil_produk']); ?></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label small">Jumlah Tenaga Kerja</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['jml_tenaga_kerja']); ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label small">Kapasitas produksi per Bulan, per produk</label>
                <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($data['kapasitas_produk']); ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label small">Omset per Bulan</label>
                <textarea class="form-control" readonly><?php echo htmlspecialchars($data['omset_perbulan']); ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label small">Wilayah pemasaran</label>
                <input class="form-control" value="<?php echo htmlspecialchars($data['wilayah_pemasaran']); ?>" readonly />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Kolom kanan - Lampiran -->
      <div class="col-lg-5">
        <div class="card">
          <div class="card-body p-3 p-md-4">
            <h3 class="subsection-title mb-1">Lampiran Dokumen</h3>
            <div class="divider"></div>

            <?php if (!empty($data['foto_ktp'])): ?>
              <div class="mb-3">
                <div class="small fw-semibold mb-2">Foto KTP</div>
                <img class="attach-img" alt="Foto KTP" src="<?php echo htmlspecialchars($data['foto_ktp']); ?>" />
                <div class="text-end mt-2 mb-3">
                  <button class="btn btn-dark btn-sm btn-view"
                    data-src="<?php echo htmlspecialchars($data['foto_ktp']); ?>"
                    data-title="Foto KTP">
                    <i class="bi bi-eye me-1"></i>View
                  </button>
                </div>
              </div>
            <?php endif; ?>

            <?php if (isset($lampiran['Nomor Induk Berusaha (NIB)'])): ?>
              <div class="mb-3">
                <div class="small fw-semibold mb-2">Nomor Induk Berusaha (NIB)</div>
                <div class="row g-3">
                  <?php
                  $nib_files = $lampiran['Nomor Induk Berusaha (NIB)'];
                  $nib_count = count($nib_files);
                  foreach ($nib_files as $index => $item):
                    $colClass = $nib_count > 1 ? 'col-md-6' : 'col-12';
                  ?>
                    <div class="<?php echo $colClass; ?>">
                      <?php
                      $file_ext = strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION));
                      $is_pdf = ($file_ext === 'pdf');
                      ?>

                      <?php if ($is_pdf): ?>
                        <div class="pdf-card" style="cursor: pointer;"
                          onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;]').click()">
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
                          src="<?php echo htmlspecialchars($item['file_path']); ?>"
                          style="cursor: pointer;"
                          onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;]').click()" />
                      <?php endif; ?>

                      <div class="text-end mt-2 mb-3">
                        <button class="btn btn-dark btn-sm btn-view"
                          data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                          data-title="<?php echo $nib_count > 1 ? 'NIB Halaman ' . ($index + 1) : 'Nomor Induk Berusaha (NIB)'; ?>">
                          <i class="bi bi-eye me-1"></i>Preview
                        </button>
                        <a href="<?php echo htmlspecialchars($item['file_path']); ?>"
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

            <div class="mb-3">
              <div class="small fw-semibold mb-2">Legalitas/Standardisasi yang telah dimiliki</div>
              <div class="row">
                <?php if (isset($lampiran['P-IRT'])): ?>
                  <div class="col-md-6 mb-4">
                    <div class="small fw-semibold mb-2">Lampiran: P-IRT</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['P-IRT'] as $item): ?>
                        <div class="col-12">
                          <?php
                          $file_ext = strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION));
                          $is_pdf = ($file_ext === 'pdf');
                          ?>

                          <?php if ($is_pdf): ?>
                            <div class="pdf-card" style="cursor: pointer;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;P-IRT&quot;]').click()">
                              <i class="bi bi-file-pdf-fill pdf-icon"></i>
                              <div class="pdf-label">Dokumen P-IRT</div>
                              <small class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">Klik untuk preview</small>
                            </div>
                          <?php else: ?>
                            <img class="attach-img"
                              alt="P-IRT"
                              src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              style="cursor: pointer; width: 100%; height: auto;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;P-IRT&quot;]').click()" />
                          <?php endif; ?>

                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="P-IRT">
                              <i class="bi bi-eye me-1"></i>Preview
                            </button>
                            <a href="<?php echo htmlspecialchars($item['file_path']); ?>"
                              class="btn btn-outline-dark btn-sm"
                              download>
                              <i class="bi bi-download me-1"></i>
                            </a>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (isset($lampiran['BPOM-MD'])): ?>
                  <div class="col-md-6 mb-4">
                    <div class="small fw-semibold mb-2">Lampiran: BPOM-MD</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['BPOM-MD'] as $item): ?>
                        <div class="col-12">
                          <?php
                          $file_ext = strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION));
                          $is_pdf = ($file_ext === 'pdf');
                          ?>

                          <?php if ($is_pdf): ?>
                            <div class="pdf-card" style="cursor: pointer;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;BPOM-MD&quot;]').click()">
                              <i class="bi bi-file-pdf-fill pdf-icon"></i>
                              <div class="pdf-label">Dokumen BPOM-MD</div>
                              <small class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">Klik untuk preview</small>
                            </div>
                          <?php else: ?>
                            <img class="attach-img"
                              alt="BPOM-MD"
                              src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              style="cursor: pointer; width: 100%; height: auto;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;BPOM-MD&quot;]').click()" />
                          <?php endif; ?>

                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="BPOM-MD">
                              <i class="bi bi-eye me-1"></i>Preview
                            </button>
                            <a href="<?php echo htmlspecialchars($item['file_path']); ?>"
                              class="btn btn-outline-dark btn-sm"
                              download>
                              <i class="bi bi-download me-1"></i>
                            </a>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (isset($lampiran['HALAL'])): ?>
                  <div class="col-md-6 mb-4">
                    <div class="small fw-semibold mb-2">Lampiran: HALAL</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['HALAL'] as $item): ?>
                        <div class="col-12">
                          <?php
                          $file_ext = strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION));
                          $is_pdf = ($file_ext === 'pdf');
                          ?>

                          <?php if ($is_pdf): ?>
                            <div class="pdf-card" style="cursor: pointer;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;HALAL&quot;]').click()">
                              <i class="bi bi-file-pdf-fill pdf-icon"></i>
                              <div class="pdf-label">Dokumen HALAL</div>
                              <small class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">Klik untuk preview</small>
                            </div>
                          <?php else: ?>
                            <img class="attach-img"
                              alt="HALAL"
                              src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              style="cursor: pointer; width: 100%; height: auto;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;HALAL&quot;]').click()" />
                          <?php endif; ?>

                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="HALAL">
                              <i class="bi bi-eye me-1"></i>Preview
                            </button>
                            <a href="<?php echo htmlspecialchars($item['file_path']); ?>"
                              class="btn btn-outline-dark btn-sm"
                              download>
                              <i class="bi bi-download me-1"></i>
                            </a>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (isset($lampiran['NUTRITION FACTS'])): ?>
                  <div class="col-md-6 mb-4">
                    <div class="small fw-semibold mb-2">Lampiran: NUTRITION FACTS</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['NUTRITION FACTS'] as $item): ?>
                        <div class="col-12">
                          <?php
                          $file_ext = strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION));
                          $is_pdf = ($file_ext === 'pdf');
                          ?>

                          <?php if ($is_pdf): ?>
                            <div class="pdf-card" style="cursor: pointer;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;NUTRITION FACTS&quot;]').click()">
                              <i class="bi bi-file-pdf-fill pdf-icon"></i>
                              <div class="pdf-label">Nutrition Facts</div>
                              <small class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">Klik untuk preview</small>
                            </div>
                          <?php else: ?>
                            <img class="attach-img"
                              alt="NUTRITION FACTS"
                              src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              style="cursor: pointer; width: 100%; height: auto;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;NUTRITION FACTS&quot;]').click()" />
                          <?php endif; ?>

                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="NUTRITION FACTS">
                              <i class="bi bi-eye me-1"></i>Preview
                            </button>
                            <a href="<?php echo htmlspecialchars($item['file_path']); ?>"
                              class="btn btn-outline-dark btn-sm"
                              download>
                              <i class="bi bi-download me-1"></i>
                            </a>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (isset($lampiran['SNI'])): ?>
                  <div class="col-md-6 mb-4">
                    <div class="small fw-semibold mb-2">Lampiran: SNI</div>
                    <div class="row g-3">
                      <?php foreach ($lampiran['SNI'] as $item): ?>
                        <div class="col-12">
                          <?php
                          $file_ext = strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION));
                          $is_pdf = ($file_ext === 'pdf');
                          ?>

                          <?php if ($is_pdf): ?>
                            <div class="pdf-card" style="cursor: pointer;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;SNI&quot;]').click()">
                              <i class="bi bi-file-pdf-fill pdf-icon"></i>
                              <div class="pdf-label">Dokumen SNI</div>
                              <small class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">Klik untuk preview</small>
                            </div>
                          <?php else: ?>
                            <img class="attach-img"
                              alt="SNI"
                              src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              style="cursor: pointer; width: 100%; height: auto;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;SNI&quot;]').click()" />
                          <?php endif; ?>

                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="SNI">
                              <i class="bi bi-eye me-1"></i>Preview
                            </button>
                            <a href="<?php echo htmlspecialchars($item['file_path']); ?>"
                              class="btn btn-outline-dark btn-sm"
                              download>
                              <i class="bi bi-download me-1"></i>
                            </a>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php
                // Tampilkan semua legalitas dengan id_jenis_file >= 9 dan != 15 (bukan Akta)
                foreach ($lampiran as $jenis_nama => $files):
                  // Skip jika bukan legalitas atau jika sudah ditampilkan secara individual
                  if (
                    $jenis_nama === 'Nomor Induk Berusaha (NIB)' ||
                    $jenis_nama === 'Foto Produk' ||
                    $jenis_nama === 'Foto Proses Produksi' ||
                    $jenis_nama === 'Surat Keterangan Difasilitasi' ||
                    $jenis_nama === 'Surat IKM' ||
                    $jenis_nama === 'Bukti Pendaftaran Kementerian' ||
                    $jenis_nama === 'Sertifikat Terbit' ||
                    $jenis_nama === 'Surat Penolakan Kementerian' ||
                    $jenis_nama === 'Akta Pendirian CV/PT' ||
                    $jenis_nama === 'P-IRT' ||
                    $jenis_nama === 'BPOM-MD' ||
                    $jenis_nama === 'HALAL' ||
                    $jenis_nama === 'NUTRITION FACTS' ||
                    $jenis_nama === 'SNI'
                  ) {
                    continue;
                  }

                  // Cek apakah ini legalitas custom (id > 14)
                  $is_custom = false;
                  if (!empty($files) && isset($files[0]['id_jenis_file'])) {
                    $is_custom = ($files[0]['id_jenis_file'] > 15);
                  }
                ?>
                  <div class="col-md-6 mb-4">
                    <div class="small fw-semibold mb-2">
                      Lampiran: <?php echo htmlspecialchars($jenis_nama); ?>
                      <?php if ($is_custom): ?>
                        <span class="badge bg-info text-dark ms-1" style="font-size: 0.7rem;">Custom</span>
                      <?php endif; ?>
                    </div>
                    <div class="row g-3">
                      <?php foreach ($files as $item): ?>
                        <div class="col-12">
                          <?php
                          $file_ext = strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION));
                          $is_pdf = ($file_ext === 'pdf');
                          ?>

                          <?php if ($is_pdf): ?>
                            <div class="pdf-card" style="cursor: pointer;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;<?php echo htmlspecialchars($jenis_nama); ?>&quot;]').click()">
                              <i class="bi bi-file-pdf-fill pdf-icon"></i>
                              <div class="pdf-label"><?php echo htmlspecialchars($jenis_nama); ?> (PDF)</div>
                              <small class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">Klik untuk preview</small>
                            </div>
                          <?php else: ?>
                            <img class="attach-img"
                              alt="<?php echo htmlspecialchars($jenis_nama); ?>"
                              src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              style="cursor: pointer; width: 100%; height: auto;"
                              onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;<?php echo htmlspecialchars($jenis_nama); ?>&quot;]').click()" />
                          <?php endif; ?>

                          <div class="text-end mt-2 mb-3">
                            <button class="btn btn-dark btn-sm btn-view"
                              data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                              data-title="<?php echo htmlspecialchars($jenis_nama); ?>">
                              <i class="bi bi-eye me-1"></i>Preview
                            </button>
                            <a href="<?php echo htmlspecialchars($item['file_path']); ?>"
                              class="btn btn-outline-dark btn-sm"
                              download>
                              <i class="bi bi-download me-1"></i>
                            </a>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Akta Pendirian CV/PT -->
              <?php if (isset($lampiran['Akta Pendirian CV/PT'])): ?>
                <div class="mb-3">
                  <div class="small fw-semibold mb-2">Akta Pendirian CV/PT</div>
                  <div class="row g-3">
                    <?php foreach ($lampiran['Akta Pendirian CV/PT'] as $index => $item): ?>
                      <div class="col-12">
                        <?php
                        $file_ext = strtolower(pathinfo($item['file_path'], PATHINFO_EXTENSION));
                        $is_pdf = ($file_ext === 'pdf');
                        ?>

                        <?php if ($is_pdf): ?>
                          <div class="pdf-card" style="cursor: pointer;"
                            onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;Akta Pendirian CV/PT&quot;]').click()">
                            <i class="bi bi-file-pdf-fill pdf-icon"></i>
                            <div class="pdf-label">Akta Pendirian CV/PT (PDF)</div>
                            <small class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">Klik untuk preview</small>
                          </div>
                        <?php else: ?>
                          <img class="attach-img"
                            alt="Akta Pendirian CV/PT"
                            src="<?php echo htmlspecialchars($item['file_path']); ?>"
                            style="cursor: pointer; width: 100%; height: auto;"
                            onclick="document.querySelector('[data-src=&quot;<?php echo htmlspecialchars($item['file_path']); ?>&quot;][data-title=&quot;Akta Pendirian CV/PT&quot;]').click()" />
                        <?php endif; ?>

                        <div class="text-end mt-2 mb-3">
                          <button class="btn btn-dark btn-sm btn-view"
                            data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                            data-title="Akta Pendirian CV/PT">
                            <i class="bi bi-eye me-1"></i>Preview
                          </button>
                          <a href="<?php echo htmlspecialchars($item['file_path']); ?>"
                            class="btn btn-outline-dark btn-sm"
                            download>
                            <i class="bi bi-download me-1"></i>
                          </a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if (isset($lampiran['Foto Produk'])): ?>
                <div class="mb-3">
                  <div class="small fw-semibold mb-2">Lampiran: Foto Produk</div>
                  <div class="row g-3">
                    <?php foreach ($lampiran['Foto Produk'] as $item): ?>
                      <div class="col-4">
                        <img class="attach-img" alt="Foto Produk" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                        <div class="text-end mt-2 mb-3">
                          <button class="btn btn-dark btn-sm btn-view"
                            data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                            data-title="Foto Produk">
                            <i class="bi bi-eye me-1"></i>View
                          </button>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if (isset($lampiran['Foto Proses Produksi'])): ?>
                <div class="mb-2">
                  <div class="small fw-semibold mb-2">Lampiran: Foto Proses Produksi</div>
                  <div class="row g-3">
                    <?php foreach ($lampiran['Foto Proses Produksi'] as $item): ?>
                      <div class="col-6">
                        <img class="attach-img" alt="Proses Produksi" src="<?php echo htmlspecialchars($item['file_path']); ?>" />
                        <div class="text-end mt-2 mb-3">
                          <button class="btn btn-dark btn-sm btn-view"
                            data-src="<?php echo htmlspecialchars($item['file_path']); ?>"
                            data-title="Foto Proses Produksi">
                            <i class="bi bi-eye me-1"></i>View
                          </button>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <!-- INFORMASI MEREK -->
      <div class="card mt-4">
        <div class="card-body p-3 p-md-4">
          <h3 class="subsection-title mb-1">Informasi Merek</h3>
          <div class="divider"></div>

          <div class="mb-3">
            <label class="form-label small">Kelas Merek</label>
            <input class="form-control" value="Kelas <?php echo htmlspecialchars($data['kelas_merek']); ?>" readonly />
          </div>

          <div class="row g-4">
            <?php if (!empty($data['nama_merek1'])): ?>
              <div class="col-md-4">
                <div class="mb-2 fw-semibold">Merek Alternatif 1 (diutamakan)</div>
                <label class="form-label small">Nama Merek Alternatif 1</label>
                <input class="form-control mb-2" value="<?php echo htmlspecialchars($data['nama_merek1']); ?>" readonly />
                <?php if (!empty($data['logo1'])): ?>
                  <div class="border rounded-3 p-3 text-center">
                    <img alt="Logo Merek 1" class="img-fluid mb-2" style="max-height:130px" src="<?php echo htmlspecialchars($data['logo1']); ?>" />
                    <div class="text-center mt-2 mb-3">
                      <button class="btn btn-dark btn-sm btn-view"
                        data-src="<?php echo htmlspecialchars($data['logo1']); ?>"
                        data-title="Logo Merek 1">
                        <i class="bi bi-eye me-1"></i>View
                      </button>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($data['nama_merek2'])): ?>
              <div class="col-md-4">
                <div class="mb-2 fw-semibold">Merek Alternatif 2</div>
                <label class="form-label small">Nama Merek Alternatif 2</label>
                <input class="form-control mb-2" value="<?php echo htmlspecialchars($data['nama_merek2']); ?>" readonly />
                <?php if (!empty($data['logo2'])): ?>
                  <div class="border rounded-3 p-3 text-center">
                    <img alt="Logo Merek 2" class="img-fluid mb-2" style="max-height:130px" src="<?php echo htmlspecialchars($data['logo2']); ?>" />
                    <div class="text-center mt-2 mb-3">
                      <button class="btn btn-dark btn-sm btn-view"
                        data-src="<?php echo htmlspecialchars($data['logo2']); ?>"
                        data-title="Logo Merek 2">
                        <i class="bi bi-eye me-1"></i>View
                      </button>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($data['nama_merek3'])): ?>
              <div class="col-md-4">
                <div class="mb-2 fw-semibold">Merek Alternatif 3</div>
                <label class="form-label small">Nama Merek Alternatif 3</label>
                <input class="form-control mb-2" value="<?php echo htmlspecialchars($data['nama_merek3']); ?>" readonly />
                <?php if (!empty($data['logo3'])): ?>
                  <div class="border rounded-3 p-3 text-center">
                    <img alt="Logo Merek 3" class="img-fluid mb-2" style="max-height:130px" src="<?php echo htmlspecialchars($data['logo3']); ?>" />
                    <div class="text-center mt-2 mb-3">
                      <button class="btn btn-dark btn-sm btn-view"
                        data-src="<?php echo htmlspecialchars($data['logo3']); ?>"
                        data-title="Logo Merek 3">
                        <i class="bi bi-eye me-1"></i>View
                      </button>
                    </div>
                  </div>
                <?php endif; ?>
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
    const ID_PENDAFTARAN = <?php echo $id_pendaftaran; ?>;
    const btnBisa = document.getElementById('btnBisa');
    const btnTidakBisa = document.getElementById('btnTidakBisa');
    const alasanBox = document.getElementById('alasanBox');
    const inputAlasan = document.getElementById('inputAlasan');
    const statusButton = document.getElementById('statusButton');
    const reviewFieldset = document.getElementById('reviewFieldset');
    const btnKonfirmasiTidakBisa = document.getElementById('btnKonfirmasiTidakBisa');

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

    // ===== HANDLER UPLOAD SURAT KETERANGAN IKM =====
    const formSuratKeterangan = document.getElementById('formSuratKeterangan');
    if (formSuratKeterangan) {
      const fileInput = document.getElementById('fileSuratKeterangan');
      const btnUpload = document.getElementById('btnUploadSuratKeterangan');

      // Cek apakah sudah pernah upload (dari PHP)
      const sudahUpload = <?php echo ($suratKeterangan && file_exists($suratKeterangan['file_path'])) ? 'true' : 'false'; ?>;

      if (sudahUpload) {
        fileInput.disabled = true;
        btnUpload.disabled = true;
        btnUpload.innerHTML = '<i class="bi bi-check-circle me-2"></i>Sudah Diupload';
        btnUpload.classList.remove('btn-dark');
        btnUpload.classList.add('btn-success');
      }

      // Event ketika file dipilih - langsung tampilkan preview
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
        const allowedExt = ['pdf'];
        const fileExt = file.name.split('.').pop().toLowerCase();

        if (!allowedExt.includes(fileExt)) {
          showAlert('Format file harus PDF');
          this.value = '';
          return;
        }

        // Tampilkan modal preview
        showPreviewModal(file);
      });

      function showPreviewModal(file) {
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
          // Preview PDF
          imageContainer.style.display = 'none';
          pdfContainer.style.display = 'block';
          modalPdf.src = fileURL + '#toolbar=0';
        } else {
          // Preview gambar
          pdfContainer.style.display = 'none';
          imageContainer.style.display = 'block';
          modalImg.src = fileURL;
        }

        // Ganti tombol download dengan tombol konfirmasi upload
        downloadBtn.outerHTML = `
      <button id="btnKonfirmasiUploadIKM" class="btn btn-dark btn-sm">
        <i class="bi bi-upload me-2"></i>Konfirmasi & Upload
      </button>
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
        <i class="bi bi-x me-1"></i>Batal
      </button>
    `;

        modal.show();

        // Handler konfirmasi upload
        document.getElementById('btnKonfirmasiUploadIKM').addEventListener('click', function() {
          modal.hide();
          uploadSuratIKM(file);

          // Cleanup URL object
          URL.revokeObjectURL(fileURL);
        });

        // Cleanup saat modal ditutup
        document.getElementById('imageModal').addEventListener('hidden.bs.modal', function() {
          URL.revokeObjectURL(fileURL);
          modalPdf.src = '';
          modalImg.src = '';

          // Kembalikan tombol download
          const btnConfirm = document.getElementById('btnKonfirmasiUploadIKM');
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
        showConfirm('Apakah Anda yakin ingin mengupload Surat Keterangan IKM ini?', function() {
          // Kode upload HANYA di dalam callback ini
          const formData = new FormData();
          formData.append('ajax_action', 'upload_surat_keterangan');
          formData.append('fileSuratKeterangan', file);

          btnUpload.disabled = true;
          btnUpload.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Mengupload...';

          fetch(window.location.href, {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                showAlert('Surat Keterangan IKM berhasil diupload!', 'success');
                fileInput.disabled = true;
                btnUpload.disabled = true;
                btnUpload.innerHTML = '<i class="bi bi-check-circle me-2"></i>Sudah Diupload';
                btnUpload.classList.remove('btn-dark');
                btnUpload.classList.add('btn-success');
                setTimeout(() => location.reload(), 1000);
              } else {
                showAlert('Gagal: ' + data.message, 'danger');
                fileInput.value = '';
                btnUpload.disabled = false;
                btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Surat Keterangan IKM';
              }
            })
            .catch(error => {
              console.error('Error:', error);
              showAlert('Terjadi kesalahan saat mengupload file.', 'danger');
              fileInput.value = '';
              btnUpload.disabled = false;
              btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Surat Keterangan IKM';
            });
        }, function() {
          // Callback saat user klik "Batal"
          fileInput.value = '';
        });

      }

      // Event submit form (backup jika ada yang langsung submit)
      formSuratKeterangan.addEventListener('submit', function(e) {
        e.preventDefault();

        const file = fileInput.files[0];
        if (file) {
          uploadSuratIKM(file);
        } else {
          showAlert('Silakan pilih file terlebih dahulu!');
        }
      });
    }

    // ===== HANDLER UPLOAD BUKTI PENDAFTARAN =====
    const formBuktiPendaftaran = document.getElementById('formBuktiPendaftaran');
    if (formBuktiPendaftaran) {
      const fileInputBukti = document.getElementById('fileBuktiPendaftaran');
      const btnUploadBukti = document.getElementById('btnUploadBuktiPendaftaran');

      // Cek apakah sudah pernah upload (dari PHP)
      const sudahUploadBukti = <?php echo ($buktiPendaftaran && file_exists($buktiPendaftaran['file_path'])) ? 'true' : 'false'; ?>;

      if (sudahUploadBukti) {
        fileInputBukti.disabled = true;
        btnUploadBukti.disabled = true;
        btnUploadBukti.innerHTML = '<i class="bi bi-check-circle me-2"></i>Sudah Diupload';
        btnUploadBukti.classList.remove('btn-success');
        btnUploadBukti.classList.add('btn-outline-success');
      }

      // Event ketika file dipilih - langsung tampilkan preview
      fileInputBukti.addEventListener('change', function() {
        const file = this.files[0];

        if (!file) return;

        // Validasi ukuran
        if (file.size > 10 * 1024 * 1024) {
          showAlert('Ukuran file maksimal 10MB!');
          this.value = '';
          return;
        }

        // Validasi format
        const allowedExt = ['pdf'];
        const fileExt = file.name.split('.').pop().toLowerCase();

        if (!allowedExt.includes(fileExt)) {
          showAlert('Format file harus PDF');
          this.value = '';
          return;
        }

        // Tampilkan modal preview
        showPreviewModalBukti(file);
      });

      function showPreviewModalBukti(file) {
        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        const modalTitle = document.getElementById('modalTitle');
        const imageContainer = document.getElementById('imageContainer');
        const pdfContainer = document.getElementById('pdfContainer');
        const modalImg = document.getElementById('modalImage');
        const modalPdf = document.getElementById('modalPdf');
        const downloadBtn = document.getElementById('downloadBtn');

        modalTitle.textContent = 'Preview: Bukti Pendaftaran';

        const fileExt = file.name.split('.').pop().toLowerCase();
        const fileURL = URL.createObjectURL(file);

        if (fileExt === 'pdf') {
          // Preview PDF
          imageContainer.style.display = 'none';
          pdfContainer.style.display = 'block';
          modalPdf.src = fileURL + '#toolbar=0';
        } else {
          // Preview gambar
          pdfContainer.style.display = 'none';
          imageContainer.style.display = 'block';
          modalImg.src = fileURL;
        }

        // Ganti tombol download dengan tombol konfirmasi upload
        downloadBtn.outerHTML = `
      <button id="btnKonfirmasiUploadBukti" class="btn btn-success btn-sm">
        <i class="bi bi-upload me-2"></i>Konfirmasi & Upload
      </button>
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
        <i class="bi bi-x me-1"></i>Batal
      </button>
    `;

        modal.show();

        // Handler konfirmasi upload
        document.getElementById('btnKonfirmasiUploadBukti').addEventListener('click', function() {
          modal.hide();
          uploadBuktiPendaftaran(file);

          // Cleanup URL object
          URL.revokeObjectURL(fileURL);
        });

        // Cleanup saat modal ditutup
        document.getElementById('imageModal').addEventListener('hidden.bs.modal', function() {
          URL.revokeObjectURL(fileURL);
          modalPdf.src = '';
          modalImg.src = '';

          // Kembalikan tombol download
          const btnConfirm = document.getElementById('btnKonfirmasiUploadBukti');
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

      function uploadBuktiPendaftaran(file) {
        showConfirm(
          'Apakah Anda yakin ingin mengupload Bukti Pendaftaran ini?<br><br>Status akan otomatis berubah menjadi "Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian".',
          function() {
            // Kode upload HANYA di dalam callback ini
            const formData = new FormData();
            formData.append('ajax_action', 'upload_bukti_pendaftaran');
            formData.append('fileBuktiPendaftaran', file);

            btnUploadBukti.disabled = true;
            btnUploadBukti.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Mengupload...';

            fetch(window.location.href, {
                method: 'POST',
                body: formData
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  showAlert('Bukti Pendaftaran berhasil diupload dan status diperbarui!', 'success');

                  fileInputBukti.disabled = true;
                  btnUploadBukti.disabled = true;
                  btnUploadBukti.innerHTML = '<i class="bi bi-check-circle me-2"></i>Sudah Diupload';
                  btnUploadBukti.classList.remove('btn-success');
                  btnUploadBukti.classList.add('btn-outline-success');

                  setTimeout(() => location.reload(), 1000);
                } else {
                  showAlert('Gagal: ' + data.message, 'danger');
                  fileInputBukti.value = '';
                  btnUploadBukti.disabled = false;
                  btnUploadBukti.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Bukti Pendaftaran';
                }
              })
              .catch(error => {
                console.error('Error:', error);
                showAlert('Terjadi kesalahan saat mengupload file.', 'danger');
                fileInputBukti.value = '';
                btnUploadBukti.disabled = false;
                btnUploadBukti.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Bukti Pendaftaran';
              });
          },
          function() {
            // Callback saat user klik "Batal"
            fileInputBukti.value = '';
          }
        );

      }

      // Event submit form (backup jika ada yang langsung submit)
      formBuktiPendaftaran.addEventListener('submit', function(e) {
        e.preventDefault();

        const file = fileInputBukti.files[0];
        if (file) {
          uploadBuktiPendaftaran(file);
        } else {
          showAlert('Silakan pilih file terlebih dahulu!');
        }
      });
    }

    // Fungsi untuk update status ke server
    function updateStatus(status, alasan = '', merekDipilih = 0) {
      const formData = new FormData();
      formData.append('id_pendaftaran', ID_PENDAFTARAN);
      formData.append('status', status);
      formData.append('alasan', alasan);
      formData.append('merek_dipilih', merekDipilih);

      return fetch('process/update_status.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            console.log('Status berhasil diupdate:', data.new_status);
            setStatus(data.new_status);
            return data;
          } else {
            showAlert('Gagal mengupdate status: ' + data.message);
            throw new Error(data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showAlert('Terjadi kesalahan saat mengupdate status');
          throw error;
        });
    }

    // Handler tombol "Tidak Bisa Difasilitasi"
    if (btnTidakBisa) {
      btnTidakBisa.addEventListener('click', () => {
        alasanBox.style.display = 'block';
        btnTidakBisa.classList.add('active');
        btnBisa.classList.remove('active');
      });
    }

    // Handler konfirmasi tidak bisa difasilitasi
    if (btnKonfirmasiTidakBisa) {
      btnKonfirmasiTidakBisa.addEventListener('click', () => {
        const alasan = inputAlasan.value.trim();

        if (alasan === '') {
          showAlert('Mohon berikan alasan mengapa tidak bisa difasilitasi');
          return;
        }

        showConfirm('Apakah Anda yakin merek ini tidak bisa difasilitasi?', function() {
          updateStatus('Tidak Bisa Difasilitasi', alasan)
            .then(() => {
              renderTidakBisaDifasilitasi(alasan);
            });
        });
      });
    }

    // Handler tombol "Bisa Difasilitasi"
    if (btnBisa) {
      btnBisa.addEventListener('click', () => {
        alasanBox.style.display = 'none';
        inputAlasan.value = '';
        btnBisa.classList.add('active');
        btnTidakBisa.classList.remove('active');

        renderKonfirmasiMerek();
      });
    }

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

    function setStatus(text) {
      const btn = statusButton;
      if (btn) {
        btn.textContent = text;
        btn.className = 'btn btn-pill fw-semibold';

        if (text === 'Pengecekan Berkas' || text === 'Berkas Baru') {
          btn.classList.add('btn-secondary');
        } else if (text === 'Konfirmasi Lanjut') {
          btn.classList.add('btn-primary');
        } else if (text === 'Surat Keterangan Difasilitasi') {
          btn.classList.add('btn-info', 'text-dark');
        } else if (text === 'Menunggu Bukti Pendaftaran') {
          btn.classList.add('btn-warning', 'text-dark');
        } else if (text === 'Diajukan ke Kementerian') {
          btn.classList.add('btn-warning', 'text-dark');
        } else if (text === 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian') {
          btn.classList.add('btn-info', 'text-dark');
        } else if (text === 'Hasil Verifikasi Kementerian') {
          btn.classList.add('btn-success');
        } else if (text === 'Tidak Bisa Difasilitasi') {
          btn.classList.add('btn-danger');
        }
      }
    }

    function renderTidakBisaDifasilitasi(alasan) {
      reviewFieldset.innerHTML = `
        <legend>Tidak Bisa Difasilitasi</legend>
        <div class="alert alert-danger mb-0">
          <strong><i class="bi bi-x-circle me-2"></i>Merek tidak bisa difasilitasi</strong>
          <p class="mb-0 mt-2">Alasan: ${alasan}</p>
        </div>
      `;
    }

    function renderKonfirmasiMerek() {
      reviewFieldset.innerHTML = `
        <legend>Konfirmasi Merek</legend>
        <div class="text-muted-600 small mb-2">
          Silahkan pilih merek mana yang bisa difasilitasi dengan cara menekan tombol dibawah.
          <ul class="mt-2 mb-0">
            <li><strong>Merek 1 (Utama):</strong> Akan langsung lanjut ke tahap upload Surat Keterangan Difasilitasi</li>
            <li><strong>Merek 2 atau 3:</strong> Akan dikirimkan notifikasi ke pemohon untuk konfirmasi lanjut dengan alasan</li>
          </ul>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button id="btnMerek1" type="button" class="btn btn-dark btn-pill">Merek 1 (Utama)</button>
          <button id="btnMerek2" type="button" class="btn btn-outline-dark btn-pill">Merek 2</button>
          <button id="btnMerek3" type="button" class="btn btn-outline-dark btn-pill">Merek 3</button>
        </div>
        <div id="alasanMerekBox" style="display: none;" class="mt-3">
          <label class="form-label small">Berikan alasan mengapa memilih merek alternatif ini:</label>
          <textarea id="inputAlasanMerek" class="form-control mb-2" rows="3" placeholder="Contoh: Merek 1 sudah terdaftar oleh pihak lain, sehingga kami memilih Merek 2 sebagai alternatif..."></textarea>
          <button id="btnKonfirmasiMerek" type="button" class="btn btn-dark">Konfirmasi Pilihan</button>
        </div>
      `;

      let selectedMerek = 0;

      // Handler Merek 1 - Langsung ke Surat Keterangan Difasilitasi
      document.getElementById('btnMerek1').addEventListener('click', function() {
        showConfirm('Apakah Anda yakin memilih Merek 1 (Utama) untuk difasilitasi?', function() {
          updateStatus('Surat Keterangan Difasilitasi', '', 1)
            .then(() => {
              showAlert('Merek 1 (Utama) telah dipilih. Silakan upload Surat Keterangan Difasilitasi.');
              renderSuratKeterangan();
            });
        });
      });

      // Handler Merek 2 - Perlu alasan
      document.getElementById('btnMerek2').addEventListener('click', function() {
        selectedMerek = 2;
        document.getElementById('alasanMerekBox').style.display = 'block';
        document.getElementById('btnMerek2').classList.add('active');
        document.getElementById('btnMerek3').classList.remove('active');
      });

      // Handler Merek 3 - Perlu alasan
      document.getElementById('btnMerek3').addEventListener('click', function() {
        selectedMerek = 3;
        document.getElementById('alasanMerekBox').style.display = 'block';
        document.getElementById('btnMerek3').classList.add('active');
        document.getElementById('btnMerek2').classList.remove('active');
      });

      // Handler konfirmasi pilihan merek dengan alasan
      document.getElementById('btnKonfirmasiMerek').addEventListener('click', function() {
        const alasanMerek = document.getElementById('inputAlasanMerek').value.trim();

        if (!selectedMerek) {
          showAlert('Silakan pilih Merek 2 atau Merek 3 terlebih dahulu');
          return;
        }

        if (alasanMerek === '') {
          showAlert('Mohon berikan alasan mengapa memilih merek alternatif ini');
          return;
        }

        showConfirm('Apakah Anda yakin memilih Merek Alternatif ' + selectedMerek + '?\n\nNotifikasi akan dikirim ke pemohon untuk konfirmasi.', function() {
          updateStatus('Konfirmasi Lanjut', alasanMerek, selectedMerek)
            .then(() => {
              showAlert('Notifikasi berhasil dikirim ke Pemohon. Menunggu konfirmasi dari pemohon untuk melanjutkan proses.');
              renderMenungguKonfirmasi(selectedMerek);
            });
        });
      });
    }

    function renderMenungguKonfirmasi(merek) {
      reviewFieldset.innerHTML = `
        <legend>Menunggu Konfirmasi Pemohon</legend>
        <div class="alert alert-info mb-0">
          <strong><i class="bi bi-clock me-2"></i>Menunggu Konfirmasi Pemohon</strong>
          <p class="mb-0 mt-2">
            Notifikasi telah dikirim ke pemohon untuk konfirmasi melanjutkan proses dengan Merek Alternatif ${merek}.
            Sistem akan otomatis melanjutkan ke tahap berikutnya setelah pemohon mengkonfirmasi.
          </p>
        </div>
      `;
    }

    function renderSuratKeterangan() {
      reviewFieldset.innerHTML = `
    <legend>Menunggu Pemohon Melengkapi Surat</legend>
    <div class="alert alert-info mb-3">
      <strong><i class="bi bi-info-circle me-2"></i>Informasi Proses</strong>
      <p class="mb-0 mt-2">
        Notifikasi telah dikirim ke pemohon untuk melengkapi Surat Keterangan Difasilitasi. 
        Sistem akan otomatis melanjutkan ke tahap berikutnya setelah pemohon mengupload surat yang sudah ditandatangani.
      </p>
    </div>
    
    <div class="alert alert-warning mb-3">
      <strong><i class="bi bi-hourglass-split me-2"></i>Status Saat Ini</strong>
      <ul class="mb-0 mt-2 ps-3">
        <li>Pemohon sedang dalam proses download surat keterangan</li>
        <li>Pemohon akan menandatangani surat di atas materai Rp 10.000</li>
        <li>Pemohon akan mengupload surat yang sudah ditandatangani</li>
        <li>Setelah pemohon upload, Anda akan dapat melanjutkan ke tahap berikutnya</li>
      </ul>
    </div>
  `;
    }

    function renderHasilVerifikasi() {
      // Data dari PHP
      const sertifikatData = <?php echo json_encode($sertifikatMerek); ?>;
      const penolakanData = <?php echo json_encode($suratPenolakan); ?>;

      let sertifikatHTML = '';
      let penolakanHTML = '';

      // Generate HTML untuk Sertifikat
      if (sertifikatData && sertifikatData.file_path) {
        const tglSertifikat = sertifikatData.tgl_upload ? new Date(sertifikatData.tgl_upload).toLocaleString('id-ID', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        }) : '';

        sertifikatHTML = `
      <div class="alert alert-success mb-3">
        <i class="bi bi-check-circle me-2"></i>
        <strong>File Tersedia</strong>
        <p class="mb-0 mt-2 small">
          <i class="bi bi-calendar3 me-1"></i>
          Diupload: ${tglSertifikat} WIB
        </p>
      </div>
      <div class="d-grid gap-2">
        <a href="${sertifikatData.file_path}" 
           class="btn btn-success" 
           download>
          <i class="bi bi-download me-2"></i>Download Sertifikat
        </a>
      </div>
    `;
      } else {
        sertifikatHTML = `
      <div class="alert alert-warning mb-0">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Tidak Tersedia</strong>
        <p class="mb-0 mt-2 small">File sertifikat tidak ditemukan</p>
      </div>
    `;
      }

      // Generate HTML untuk Surat Penolakan
      if (penolakanData && penolakanData.file_path) {
        const tglPenolakan = penolakanData.tgl_upload ? new Date(penolakanData.tgl_upload).toLocaleString('id-ID', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        }) : '';

        penolakanHTML = `
      <div class="alert alert-danger mb-3">
        <i class="bi bi-x-circle me-2"></i>
        <strong>File Tersedia</strong>
        <p class="mb-0 mt-2 small">
          <i class="bi bi-calendar3 me-1"></i>
          Diupload: ${tglPenolakan} WIB
        </p>
      </div>
      <div class="d-grid gap-2">
        <a href="${penolakanData.file_path}" 
           class="btn btn-danger" 
           download>
          <i class="bi bi-download me-2"></i>Download Surat Penolakan
        </a>
      </div>
    `;
      } else {
        penolakanHTML = `
      <div class="alert alert-warning mb-0">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Tidak Tersedia</strong>
        <p class="mb-0 mt-2 small">File surat penolakan tidak ditemukan</p>
      </div>
    `;
      }

      reviewFieldset.innerHTML = `
    <legend>Hasil Verifikasi Kementerian</legend>
    <div class="alert alert-info mb-3">
      <strong><i class="bi bi-info-circle me-2"></i>Proses Selesai</strong>
      <p class="mb-0 mt-2">
        Hasil verifikasi dari kementerian telah diupload. Lihat file yang tersedia di bawah ini.
      </p>
    </div>
    
    <div class="row g-3">
      <!-- Card Sertifikat Merek -->
      <div class="col-md-6">
        <div class="card border-success h-100">
          <div class="card-body">
            <h6 class="fw-bold text-success mb-3">
              <i class="bi bi-award me-2"></i>Sertifikat Merek
            </h6>
            <p class="text-muted small mb-3">Dokumen sertifikat merek yang <strong>DITERIMA</strong></p>
            ${sertifikatHTML}
          </div>
        </div>
      </div>
      
      <!-- Card Surat Penolakan -->
      <div class="col-md-6">
        <div class="card border-danger h-100">
          <div class="card-body">
            <h6 class="fw-bold text-danger mb-3">
              <i class="bi bi-x-circle me-2"></i>Surat Penolakan
            </h6>
            <p class="text-muted small mb-3">Dokumen surat merek yang <strong>DITOLAK</strong></p>
            ${penolakanHTML}
          </div>
        </div>
      </div>
    </div>
       ${generateInfoAlert()}
  `;
    }

    // Fungsi untuk generate alert informasi berdasarkan file yang tersedia
    function generateInfoAlert() {
      const sertifikatData = <?php echo json_encode($sertifikatMerek); ?>;
      const penolakanData = <?php echo json_encode($suratPenolakan); ?>;

   if (sertifikatData && sertifikatData.file_path && sertifikatData.tanggal_terbit) {
     const uploadDate = new Date(sertifikatData.tanggal_terbit);
        const expiryDate = new Date(uploadDate);
        expiryDate.setFullYear(expiryDate.getFullYear() + 10);
        expiryDate.setHours(0, 0, 0, 0); // Set ke akhir hari (23:59:59)

        const expiryFormatted = expiryDate.toLocaleDateString('id-ID', {
          day: '2-digit',
          month: 'long',
          year: 'numeric'
        });

        const countdownId = 'countdown-timer';
        setTimeout(() => updateCountdown(expiryDate, countdownId), 100);

        return `
      <div class="alert alert-success mt-3 mb-0" id="alert-sertifikat">
        <i class="bi bi-check-circle me-2"></i>
        <strong>Informasi:</strong> Sertifikat merek masih berlaku hingga 
        <strong>${expiryFormatted}</strong>.
        <br>
        <small class="mt-2 d-block">
          Sisa waktu berlaku: <strong id="${countdownId}">Menghitung...</strong>
        </small>
      </div>
    `;
      }

      if (penolakanData && penolakanData.file_path) {
        return `
      <div class="alert alert-info mt-3 mb-0">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Informasi:</strong> Terima kasih telah melakukan proses pendaftaran merek. 
        <br><small class="mt-2 d-block">Surat penolakan dan informasi akan dikirimkan ke Pemohon.</small>
      </div>
    `;
      }

      return `
    <div class="alert alert-secondary mt-3 mb-0">
      <i class="bi bi-hourglass-split me-2"></i>
      <strong>Status:</strong> Menunggu hasil verifikasi dari kementerian.
    </div>
  `;
    }


    // 🔁 Fungsi update countdown (detik berjalan, hitung ke jam 00.00)
    function updateCountdown(expiryDate, elementId) {
      const countdownEl = document.getElementById(elementId);
      if (!countdownEl) return;

      const alertEl = document.getElementById("alert-sertifikat");

      function calcRemaining() {
        const now = new Date();
        const distance = expiryDate - now;

        if (distance < 0) {
          countdownEl.innerHTML = "Masa berlaku sertifikat telah berakhir";
          alertEl.classList.remove("alert-success", "alert-warning");
          alertEl.classList.add("alert-danger");
          clearInterval(interval);
          return;
        }

        // Hitung mundur menggunakan date object untuk akurasi bulan
        const currentDate = new Date(now);
        const endDate = new Date(expiryDate);

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

        countdownEl.innerHTML = countdownText.trim();

        // Ubah warna alert jika kurang dari 1 tahun
        const oneYearInMs = 365 * 24 * 60 * 60 * 1000;
        const alertElement = document.getElementById('alert-sertifikat');

        if (distance < oneYearInMs) {
          alertElement.classList.remove('alert-success');
          alertElement.classList.add('alert-danger');
        }
      }

      const interval = setInterval(calcRemaining, 1000);
      calcRemaining();
    }




    // Cek status awal dari PHP dan render UI yang sesuai
    const statusAwal = '<?php echo $data['status_validasi']; ?>';
    const merekDifasilitasi = <?php echo $data['merek_difasilitasi'] ?? 'null'; ?>;
    const alasanTidakDifasilitasi = <?php echo json_encode($data['alasan_tidak_difasilitasi'] ?? ''); ?>;
    const alasanKonfirmasi = <?php echo json_encode($data['alasan_konfirmasi'] ?? ''); ?>;

    if (statusAwal === 'Tidak Bisa Difasilitasi') {
      const alasan = alasanTidakDifasilitasi || 'Lihat detail notifikasi';
      renderTidakBisaDifasilitasi(alasan);
    } else if (statusAwal === 'Konfirmasi Lanjut') {
      renderMenungguKonfirmasi(merekDifasilitasi || '2');
    } else if (statusAwal === 'Surat Keterangan Difasilitasi') {
      renderSuratKeterangan();
    } else if (statusAwal === 'Menunggu Bukti Pendaftaran') {
      // Render UI khusus dengan section download & upload (sudah ada di HTML)
      reviewFieldset.innerHTML = `
        <legend>Menunggu Bukti Pendaftaran</legend>
        <div class="alert alert-info mb-0">
          <strong><i class="bi bi-clock me-2"></i>Proses Upload Dokumen</strong>
          <p class="mb-0 mt-2">
            Pemohon telah mengirim surat bertanda tangan. Silakan download surat tersebut di atas, kemudian upload Surat Keterangan IKM dan Bukti Pendaftaran di bawah.
          </p>
        </div>
      `;
    } else if (statusAwal === 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian') {
      reviewFieldset.innerHTML = `
    <legend>Upload Hasil Verifikasi Kementerian</legend>
    <div class="alert alert-info mb-3">
      <strong><i class="bi bi-info-circle me-2"></i>Informasi</strong>
      <p class="mb-0 mt-2">
        Upload hasil verifikasi dari kementerian. Pilih salah satu: Sertifikat (jika diterima) atau Surat Penolakan (jika ditolak).
      </p>
    </div>
    
    <div class="row g-3">
      <!-- Upload Sertifikat Merek -->
      <div class="col-md-6">
        <div class="card border-success h-100">
          <div class="card-body">
            <h6 class="fw-bold text-success mb-3">
              <i class="bi bi-award me-2"></i>Upload Sertifikat Merek
            </h6>
            <p class="text-muted small mb-3">Upload jika merek <strong>DITERIMA</strong> oleh kementerian</p>
            <form id="formSertifikat" enctype="multipart/form-data">
            <div class="mb-3">
              <label class="form-label small">Tanggal Penerimaan Sertifikat <span class="text-danger">*</span></label>
              <input type="date" 
              class="form-control" 
              id="tanggalSertifikat" 
              required>
             <div class="form-text">Masukkan tanggal saat sertifikat diterima dari kementerian</div>
           </div>
              <div class="mb-3">
                <input type="file" 
                       class="form-control" 
                       id="fileSertifikat" 
                       accept=".pdf">
                <div class="form-text">Format: PDF (Max 10MB)</div>
              </div>
              <button type="submit" class="btn btn-success w-100" id="btnUploadSertifikat">
                <i class="bi bi-upload me-2"></i>Upload Sertifikat
              </button>
            </form>
          </div>
        </div>
      </div>
      
      <!-- Upload Surat Penolakan -->
      <div class="col-md-6">
        <div class="card border-danger h-100">
          <div class="card-body">
            <h6 class="fw-bold text-danger mb-3">
              <i class="bi bi-x-circle me-2"></i>Upload Surat Penolakan
            </h6>
            <p class="text-muted small mb-3">Upload jika merek <strong>DITOLAK</strong> oleh kementerian</p>
            <form id="formPenolakan" enctype="multipart/form-data">
              <div class="mb-3">
                <input type="file" 
                       class="form-control" 
                       id="filePenolakan" 
                       accept=".pdf">
                <div class="form-text">Format: PDF (Max 10MB)</div>
              </div>
              <button type="submit" class="btn btn-danger w-100" id="btnUploadPenolakan">
                <i class="bi bi-upload me-2"></i>Upload Surat Penolakan
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  `;

      // Handler Upload Sertifikat
      const formSertifikat = document.getElementById('formSertifikat');
      if (formSertifikat) {
        const fileInputSertifikat = document.getElementById('fileSertifikat');

        // Event ketika file dipilih - langsung tampilkan preview
        fileInputSertifikat.addEventListener('change', function() {
          const file = this.files[0];

          if (!file) return;

          // Validasi ukuran
          if (file.size > 10 * 1024 * 1024) {
            showAlert('Ukuran file maksimal 10MB!');
            this.value = '';
            return;
          }

          // Validasi format
          const allowedExt = ['pdf'];
          const fileExt = file.name.split('.').pop().toLowerCase();

          if (!allowedExt.includes(fileExt)) {
            showAlert('Format file harus PDF!');
            this.value = '';
            return;
          }

          // Tampilkan modal preview
          showPreviewModalSertifikat(file);
        });

        function showPreviewModalSertifikat(file) {
          const modal = new bootstrap.Modal(document.getElementById('imageModal'));
          const modalTitle = document.getElementById('modalTitle');
          const imageContainer = document.getElementById('imageContainer');
          const pdfContainer = document.getElementById('pdfContainer');
          const modalImg = document.getElementById('modalImage');
          const modalPdf = document.getElementById('modalPdf');
          const downloadBtn = document.getElementById('downloadBtn');

          modalTitle.textContent = 'Preview: Sertifikat Merek';

          const fileExt = file.name.split('.').pop().toLowerCase();
          const fileURL = URL.createObjectURL(file);

          if (fileExt === 'pdf') {
            // Preview PDF
            imageContainer.style.display = 'none';
            pdfContainer.style.display = 'block';
            modalPdf.src = fileURL + '#toolbar=0';
          } else {
            // Preview gambar
            pdfContainer.style.display = 'none';
            imageContainer.style.display = 'block';
            modalImg.src = fileURL;
          }

          // Ganti tombol download dengan tombol konfirmasi upload
          downloadBtn.outerHTML = `
            <button id="btnKonfirmasiUploadSertifikat" class="btn btn-success btn-sm">
              <i class="bi bi-upload me-2"></i>Konfirmasi & Upload
            </button>
            <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
              <i class="bi bi-x me-1"></i>Batal
            </button>
          `;

          modal.show();

          // Handler konfirmasi upload
          document.getElementById('btnKonfirmasiUploadSertifikat').addEventListener('click', function() {
            modal.hide();
            uploadSertifikat(file);
            URL.revokeObjectURL(fileURL);
          });

          // Cleanup saat modal ditutup
          document.getElementById('imageModal').addEventListener('hidden.bs.modal', function() {
            URL.revokeObjectURL(fileURL);
            modalPdf.src = '';
            modalImg.src = '';

            // Kembalikan tombol download
            const btnConfirm = document.getElementById('btnKonfirmasiUploadSertifikat');
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

        function uploadSertifikat(file) {
          showConfirm('Apakah Anda yakin ingin mengupload Sertifikat Merek ini?', function() {
     const tanggalInput = document.getElementById('tanggalSertifikat').value;
     
     if (!tanggalInput) {
       showAlert('Tanggal penerimaan sertifikat wajib diisi!', 'danger');
       return;
     }
            // Kode upload HARUS di dalam callback ini
            const formData = new FormData();
            formData.append('id_pendaftaran', ID_PENDAFTARAN);
            formData.append('id_jenis_file', 7); // 7 = Sertifikat Terbit
            formData.append('file', file);
            formData.append('tanggal_terbit', tanggalInput);

            const btnUpload = document.getElementById('btnUploadSertifikat');
            btnUpload.disabled = true;
            btnUpload.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Mengupload...';

            fetch('process/upload_lampiran.php', {
                method: 'POST',
                body: formData
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  updateStatus('Hasil Verifikasi Kementerian')
                    .then(() => {
                      showAlert('Sertifikat berhasil diupload! Status diperbarui ke "Hasil Verifikasi Kementerian".', 'success');
                      setTimeout(() => location.reload(), 2000);
                    });
                } else {
                  showAlert('Gagal upload: ' + data.message, 'danger');
                  fileInputSertifikat.value = '';
                  btnUpload.disabled = false;
                  btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Sertifikat';
                }
              })
              .catch(error => {
                console.error('Error:', error);
                showAlert('Terjadi kesalahan saat upload file', 'danger');
                fileInputSertifikat.value = '';
                btnUpload.disabled = false;
                btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Sertifikat';
              });
          }, function() {
            // Callback saat user klik "Batal"
            fileInputSertifikat.value = '';
          });
        }

        // Event submit form (backup)
        formSertifikat.addEventListener('submit', function(e) {
          e.preventDefault();
          const file = fileInputSertifikat.files[0];
          if (file) {
            uploadSertifikat(file);
          } else {
            showAlert('Silakan pilih file terlebih dahulu!');
          }
        });
      }

      // Handler Upload Surat Penolakan
      const formPenolakan = document.getElementById('formPenolakan');
      if (formPenolakan) {
        const fileInputPenolakan = document.getElementById('filePenolakan');

        // Event ketika file dipilih - langsung tampilkan preview
        fileInputPenolakan.addEventListener('change', function() {
          const file = this.files[0];

          if (!file) return;

          // Validasi ukuran
          if (file.size > 10 * 1024 * 1024) {
            showAlert('Ukuran file maksimal 10MB!');
            this.value = '';
            return;
          }

          // Validasi format
          const allowedExt = ['pdf'];
          const fileExt = file.name.split('.').pop().toLowerCase();

          if (!allowedExt.includes(fileExt)) {
            showAlert('Format file harus PDF!');
            this.value = '';
            return;
          }

          // Tampilkan modal preview
          showPreviewModalPenolakan(file);
        });

        function showPreviewModalPenolakan(file) {
          const modal = new bootstrap.Modal(document.getElementById('imageModal'));
          const modalTitle = document.getElementById('modalTitle');
          const imageContainer = document.getElementById('imageContainer');
          const pdfContainer = document.getElementById('pdfContainer');
          const modalImg = document.getElementById('modalImage');
          const modalPdf = document.getElementById('modalPdf');
          const downloadBtn = document.getElementById('downloadBtn');

          modalTitle.textContent = 'Preview: Surat Penolakan';

          const fileExt = file.name.split('.').pop().toLowerCase();
          const fileURL = URL.createObjectURL(file);

          if (fileExt === 'pdf') {
            // Preview PDF
            imageContainer.style.display = 'none';
            pdfContainer.style.display = 'block';
            modalPdf.src = fileURL + '#toolbar=0';
          } else {
            // Preview gambar
            pdfContainer.style.display = 'none';
            imageContainer.style.display = 'block';
            modalImg.src = fileURL;
          }

          // Ganti tombol download dengan tombol konfirmasi upload
          downloadBtn.outerHTML = `
            <button id="btnKonfirmasiUploadPenolakan" class="btn btn-danger btn-sm">
              <i class="bi bi-upload me-2"></i>Konfirmasi & Upload
            </button>
            <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
              <i class="bi bi-x me-1"></i>Batal
            </button>
          `;

          modal.show();

          // Handler konfirmasi upload
          document.getElementById('btnKonfirmasiUploadPenolakan').addEventListener('click', function() {
            modal.hide();
            uploadPenolakan(file);
            URL.revokeObjectURL(fileURL);
          });

          // Cleanup saat modal ditutup
          document.getElementById('imageModal').addEventListener('hidden.bs.modal', function() {
            URL.revokeObjectURL(fileURL);
            modalPdf.src = '';
            modalImg.src = '';

            // Kembalikan tombol download
            const btnConfirm = document.getElementById('btnKonfirmasiUploadPenolakan');
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

        function uploadPenolakan(file) {
          showConfirm('Apakah Anda yakin ingin mengupload Surat Penolakan ini?', function() {
            // Kode upload HARUS di dalam callback ini
            const formData = new FormData();
            formData.append('id_pendaftaran', ID_PENDAFTARAN);
            formData.append('id_jenis_file', 8); // 8 = Surat Penolakan
            formData.append('file', file);

            const btnUpload = document.getElementById('btnUploadPenolakan');
            btnUpload.disabled = true;
            btnUpload.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Mengupload...';

            fetch('process/upload_lampiran.php', {
                method: 'POST',
                body: formData
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  updateStatus('Hasil Verifikasi Kementerian')
                    .then(() => {
                      showAlert('Surat Penolakan berhasil diupload! Status diperbarui ke "Hasil Verifikasi Kementerian".', 'success');
                      setTimeout(() => location.reload(), 2000);
                    });
                } else {
                  showAlert('Gagal upload: ' + data.message, 'danger');
                  fileInputPenolakan.value = '';
                  btnUpload.disabled = false;
                  btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Surat Penolakan';
                }
              })
              .catch(error => {
                console.error('Error:', error);
                showAlert('Terjadi kesalahan saat upload file', 'danger');
                fileInputPenolakan.value = '';
                btnUpload.disabled = false;
                btnUpload.innerHTML = '<i class="bi bi-upload me-2"></i>Upload Surat Penolakan';
              });
          }, function() {
            // Callback saat user klik "Batal"
            fileInputPenolakan.value = '';
          });
        }

        // Event submit form (backup)
        formPenolakan.addEventListener('submit', function(e) {
          e.preventDefault();
          const file = fileInputPenolakan.files[0];
          if (file) {
            uploadPenolakan(file);
          } else {
            showAlert('Silakan pilih file terlebih dahulu!');
          }
        });
      }
    } else if (statusAwal === 'Diajukan ke Kementerian') {
      renderDiajukanKementerian();
    } else if (statusAwal === 'Hasil Verifikasi Kementerian') {
      renderHasilVerifikasi();
    }
  </script>
</body>

</html>