<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrasi - Disperindag Sidoarjo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
  <link rel="stylesheet" href="assets/css/registrasi.css">
  <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>

<body>
  <!-- Navbar -->
  <?php include 'navbar.php' ?>

  <!-- Registrasi Section -->
  <section class="hero-section">
    <div class="registration-card">
      <h2>Registrasi - Pemohon</h2>
      <form method="post" enctype="multipart/form-data" id="registrationForm">
        <div class="mb-3">
          <label for="namaPemilik" class="form-label">Nama Pemilik</label>
          <input type="text" class="form-control" id="namaPemilik" name="namaPemilik" placeholder="Nama sesuai KTP" required>
        </div>

        <div class="mb-3">
          <label for="nik" class="form-label">NIK</label>
          <input type="text" class="form-control" id="nik" name="nik" maxlength="16" placeholder="16 digit NIK" required>
          <small class="text-muted">Masukkan 16 digit NIK sesuai KTP</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Alamat Pemilik</label>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="provinsi" class="form-label">Provinsi</label>
              <select class="form-select select2-dropdown" id="provinsi" name="provinsi" required>
                <option value="">Pilih Provinsi</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="kabupaten" class="form-label">Kabupaten/Kota</label>
              <select class="form-select select2-dropdown" id="kabupaten" name="kabupaten" required disabled>
                <option value="">Pilih Kabupaten/Kota</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="kecamatan" class="form-label">Kecamatan</label>
              <select class="form-select select2-dropdown" id="kecamatan" name="kecamatan" required disabled>
                <option value="">Pilih Kecamatan</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="kel_desa" class="form-label">Kelurahan/Desa</label>
              <select class="form-select select2-dropdown" id="kel_desa" name="kel_desa" required disabled>
                <option value="">Pilih Kelurahan/Desa</option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <label for="rt_rw" class="form-label">RT/RW</label>
              <input type="text" class="form-control" id="rt_rw" name="rt_rw" placeholder="Contoh: 002/006" maxlength="7" required>
              <small class="text-muted d-block mt-1">Contoh Format: 002/006</small>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="telepon" class="form-label">Nomor WhatsApp</label>
          <input type="tel" class="form-control" id="telepon" name="telepon" required>
          <small class="text-muted">Contoh: 6281234567890 (digunakan untuk login)</small>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" id="email" name="email" placeholder="email@example.com" required>
        </div>

        <div class="mb-3">
          <label class="form-label">File KTP</label>
          <div class="file-upload-container">
            <div class="file-input-wrapper">
              <input type="file" id="fileKTP" name="fileKTP" accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName()" required>
              <label for="fileKTP" class="file-upload-label">Pilih File</label>
              <span id="fileName" class="file-name">Tidak ada file yang dipilih.</span>
            </div>
            
            <div id="ktpPreviewContainer" class="ktp-preview-wrapper">
              <img id="ktpPreviewImg" class="ktp-preview-img" alt="Preview KTP">
              
              <div id="pdfPreviewBox" class="pdf-preview-box">
                <div class="pdf-icon">📄</div>
                <p class="mb-0"><strong id="pdfFileName"></strong></p>
                <small class="text-muted">File PDF siap diupload</small>
              </div>

              <div class="preview-actions">
                <span class="file-size-info" id="fileSizeInfo"></span>
                <button type="button" class="btn-remove-file" onclick="clearFilePreview()">Hapus File</button>
              </div>
            </div>
          </div>
          <div class="file-info">Upload 1 file (PDF atau gambar). Maks 1 MB</div>
        </div>

        <div class="alert alert-info">
          <strong>Informasi:</strong> Setelah registrasi, Anda dapat langsung login menggunakan email atau nomor WhatsApp. Kode OTP akan dikirim saat login.
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-kembali" onclick="window.history.back()">Kembali</button>
          <button type="submit" class="btn btn-registrasi" id="btnSubmit">
            Daftar
            <span class="loading-spinner" id="loadingSpinner"></span>
          </button>
        </div>
      </form>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <p>Copyright © 2025. All Rights Reserved.</p>
      <p>Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
    </div>
  </footer>

  <!-- Modal Success -->
  <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-3">
        <div class="modal-header border-0">
          <h5 class="modal-title text-success" id="successModalLabel">
            <i class="bi bi-check-circle-fill"></i> Registrasi Berhasil!
          </h5>
        </div>
        <div class="modal-body">
          <div class="alert alert-success">
            <strong>✓ Akun Anda telah berhasil dibuat!</strong>
            <p class="mb-0 mt-2">Silakan login menggunakan email atau nomor WhatsApp yang telah didaftarkan.</p>
          </div>
          <p class="text-muted small mb-0">Anda akan diarahkan ke halaman login dalam 3 detik...</p>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-primary w-100" onclick="window.location.href='login.php'">
            Login Sekarang
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
 <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>
  <script src="assets/js/registrasi.js"></script>

</body>

</html>