<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrasi - Disperindag Sidoarjo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/registrasi.css">
</head>
<body>
   Navbar 
  <?php include 'navbar.php' ?>

   Registrasi Section 
  <section class="hero-section">
    <div class="registration-card">
      <h2>Registrasi - Pemohon</h2>

      <form id="formRegistrasi" method="post" enctype="multipart/form-data" action="process/proses_registrasi_with_otp_gate.php" novalidate>
        <div class="mb-3">
          <label for="namaPemilik" class="form-label">Nama Pemilik</label>
          <input type="text" class="form-control" id="namaPemilik" name="nama_pemilik" placeholder="Nama sesuai KTP" required>
        </div>

        <div class="mb-3">
          <label for="nik" class="form-label">NIK</label>
          <input type="text" class="form-control" id="nik" name="nik" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Alamat Pemilik</label>
          <div class="row">
            <div class="col-md-6">
              <input type="text" class="form-control mb-3" id="rtRw" name="rt_rw" placeholder="RT/RW" required>
            </div>
            <div class="col-md-6">
              <input type="text" class="form-control" id="kelurahanDesa" name="kelurahan_desa" placeholder="Kelurahan/Desa" required>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="kecamatan" class="form-label">Kecamatan</label>
          <input type="text" class="form-control" id="kecamatan" name="kecamatan" required>
        </div>

        <div class="mb-3">
          <label for="telepon" class="form-label">Nomor WhatsApp</label>
          <input type="tel" class="form-control" id="telepon" name="telepon" required>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">Email</label>
          <input type="email" class="form-control" id="email" name="email" required>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>

        <div class="mb-3">
          <label class="form-label">File KTP</label>
          <div class="file-upload-container">
            <input type="file" id="fileKTP" name="file_ktp" accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName()" required>
            <label for="fileKTP" class="file-upload-label">Pilih File</label>
            <span id="fileName" class="file-name">Tidak ada file yang dipilih.</span>
          </div>
          <div class="file-info">Upload 1 file (PDF atau image). Maks 1 MB</div>
        </div>

         Hidden flag untuk server-side 
        <input type="hidden" name="otp_verified" id="otpVerified" value="0">

        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-kembali" onclick="window.history.back()">Kembali</button>
          <button type="button" id="btnKirimOtp" class="btn btn-registrasi">Registrasi</button>
        </div>
      </form>
    </div>
  </section>

   Modal OTP 
  <div class="modal fade" id="otpModal" tabindex="-1" aria-labelledby="otpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="otpModalLabel">Verifikasi OTP</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <p>Masukkan kode OTP yang telah dikirim ke email Anda.</p>
          <div class="mb-3">
            <label for="otpInput" class="form-label">Kode OTP (6 digit)</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="form-control" id="otpInput" placeholder="Contoh: 123456" />
            <div class="form-text">Kode berlaku selama 5 menit.</div>
          </div>
          <div id="otpError" class="text-danger small" style="display:none;"></div>
          <div class="d-flex align-items-center gap-3 mt-2">
            <button type="button" id="btnResendOtp" class="btn btn-outline-secondary btn-sm" disabled>Kirim Ulang (60)</button>
            <span id="otpStatus" class="text-muted small"></span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" id="btnVerifyOtp" class="btn btn-primary">Verifikasi</button>
        </div>
      </div>
    </div>
  </div>

   Footer 
  <footer class="footer">
    <div class="container">
      <p>Copyright © 2025. All Rights Reserved.</p>
      <p>Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function updateFileName() {
      const fileInput = document.getElementById('fileKTP');
      const fileName = document.getElementById('fileName');

      if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        if (file.size > 1024 * 1024) { // 1MB limit
          alert('File terlalu besar. Maksimal 1 MB.');
          fileInput.value = '';
          fileName.textContent = 'Tidak ada file yang dipilih.';
          return;
        }
        fileName.textContent = file.name;
      } else {
        fileName.textContent = 'Tidak ada file yang dipilih.';
      }
    }

    (function () {
      const form = document.getElementById('formRegistrasi');
      const btnKirimOtp = document.getElementById('btnKirimOtp');
      const emailInput = document.getElementById('email');
      const otpVerifiedInput = document.getElementById('otpVerified');
      const otpModalEl = document.getElementById('otpModal');
      const otpModal = new bootstrap.Modal(otpModalEl);
      const otpInput = document.getElementById('otpInput');
      const otpError = document.getElementById('otpError');
      const btnVerifyOtp = document.getElementById('btnVerifyOtp');
      const btnResendOtp = document.getElementById('btnResendOtp');
      const otpStatus = document.getElementById('otpStatus');

      let resendCountdown = 60;
      let countdownTimer = null;

      function startResendCountdown() {
        btnResendOtp.disabled = true;
        resendCountdown = 60;
        btnResendOtp.textContent = 'Kirim Ulang (' + resendCountdown + ')';
        if (countdownTimer) clearInterval(countdownTimer);
        countdownTimer = setInterval(() => {
          resendCountdown -= 1;
          btnResendOtp.textContent = 'Kirim Ulang (' + resendCountdown + ')';
          if (resendCountdown <= 0) {
            clearInterval(countdownTimer);
            btnResendOtp.disabled = false;
            btnResendOtp.textContent = 'Kirim Ulang';
          }
        }, 1000);
      }

      async function requestOtp() {
        otpError.style.display = 'none';
        otpStatus.textContent = 'Mengirim OTP...';
        try {
          const resp = await fetch('process/send_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ email: emailInput.value })
          });
          const data = await resp.json();
          if (data.success) {
            otpStatus.textContent = 'OTP telah dikirim ke ' + emailInput.value;
            startResendCountdown();
            otpInput.value = '';
            otpModal.show();
          } else {
            otpStatus.textContent = '';
            alert(data.message || 'Gagal mengirim OTP.');
          }
        } catch (e) {
          otpStatus.textContent = '';
          alert('Terjadi kesalahan jaringan saat mengirim OTP.');
        }
      }

      btnKirimOtp.addEventListener('click', async () => {
        // Gunakan validasi HTML5 dahulu
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }
        btnKirimOtp.disabled = true;
        const originalText = btnKirimOtp.textContent;
        btnKirimOtp.textContent = 'Mengirim OTP...';
        await requestOtp();
        btnKirimOtp.disabled = false;
        btnKirimOtp.textContent = originalText;
      });

      btnResendOtp.addEventListener('click', async () => {
        if (btnResendOtp.disabled) return;
        await requestOtp();
      });

      btnVerifyOtp.addEventListener('click', async () => {
        const otp = (otpInput.value || '').trim();
        if (otp.length !== 6) {
          otpError.textContent = 'Masukkan 6 digit OTP.';
          otpError.style.display = 'block';
          return;
        }
        otpError.style.display = 'none';
        btnVerifyOtp.disabled = true;
        const original = btnVerifyOtp.textContent;
        btnVerifyOtp.textContent = 'Memverifikasi...';

        try {
          const resp = await fetch('process/verify_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ email: emailInput.value, otp })
          });
          const data = await resp.json();
          if (data.success) {
            otpVerifiedInput.value = '1';
            otpModal.hide();
            form.submit();
          } else {
            otpError.textContent = data.message || 'OTP salah.';
            otpError.style.display = 'block';
          }
        } catch (e) {
          otpError.textContent = 'Terjadi kesalahan jaringan saat verifikasi.';
          otpError.style.display = 'block';
        } finally {
          btnVerifyOtp.disabled = false;
          btnVerifyOtp.textContent = original;
        }
      });
    })();
  </script>
</body>
</html>
