<?php
session_start();
include 'process/config_db.php';

// Redirect jika sudah login
if (isset($_SESSION['NIK_NIP'])) {
    header("Location: home.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Disperindag Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <style>
        .loading-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #007bff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
            pointer-events: none;
        }

        .otp-input {
            font-size: 24px;
            letter-spacing: 10px;
            text-align: center;
            font-weight: bold;
        }

        .countdown-text {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include 'navbar.php' ?>

    <!-- Login Section -->
    <div class="login-container">
        <div class="login-card">
            <h2 class="login-title">Login</h2>
            <p class="login-subtitle">
                Masukkan email atau nomor WhatsApp Anda untuk menerima kode OTP
            </p>

            <div id="alertBox" class="alert d-none" role="alert"></div>

            <!-- Form Request OTP -->
            <form id="requestOtpForm">
                <div class="mb-3">
                    <label for="identifier" class="form-label">Email atau Nomor WhatsApp</label>
                    <input type="text" class="form-control" id="identifier" name="identifier" required>
                    <small class="text-muted">Gunakan email atau nomor WA yang terdaftar (format: 62xxx)</small>
                </div>

                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <a href="registrasi.php" class="btn-register">Registrasi</a>
                    <button type="submit" class="login" id="btnRequestOtp">
                        Kirim OTP
                        <span class="loading-spinner" id="spinnerRequest"></span>
                    </button>
                </div>
            </form>

            <!-- Form Verifikasi OTP (Hidden by default) -->
            <form id="verifyOtpForm" style="display: none;">
                <div class="mb-3">
                    <label for="otpCode" class="form-label">Kode OTP</label>
                    <input type="text" class="form-control otp-input" id="otpCode" name="otpCode" 
                           maxlength="6" placeholder="000000" required inputmode="numeric">
                    <small class="text-muted">Masukkan 6 digit kode OTP</small>
                    <div class="countdown-text text-center" id="countdown"></div>
                </div>

                <input type="hidden" id="hiddenIdentifier" name="hiddenIdentifier">

                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="btnBack">
                        Kembali
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnResend" disabled>
                        Kirim Ulang OTP
                    </button>
                    <button type="submit" class="login" id="btnVerifyOtp">
                        Verifikasi
                        <span class="loading-spinner" id="spinnerVerify"></span>
                    </button>
                </div>
            </form>
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
    <script>
        let countdownInterval;
        let resendTimeout;

        // Format nomor telepon saat blur
        document.getElementById('identifier').addEventListener('blur', function() {
            let value = this.value.trim();
            
            // Cek apakah bukan email
            if (value && !value.includes('@')) {
                // Hapus semua karakter non-digit
                value = value.replace(/\D/g, '');
                
                // Jika diawali 0, ganti dengan 62
                if (value.startsWith('0')) {
                    value = '62' + value.substring(1);
                }
                // Jika tidak diawali 62 dan tidak kosong, tambahkan 62
                else if (!value.startsWith('62') && value.length > 0) {
                    value = '62' + value;
                }
                
                this.value = value;
            }
        });

        // Request OTP
        document.getElementById('requestOtpForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const identifier = document.getElementById('identifier').value.trim();
            const btnRequest = document.getElementById('btnRequestOtp');
            const spinner = document.getElementById('spinnerRequest');

            if (!identifier) {
                showAlert('warning', 'Email atau nomor WhatsApp harus diisi!');
                return;
            }

            btnRequest.disabled = true;
            btnRequest.classList.add('btn-disabled');
            spinner.style.display = 'inline-block';
            hideAlert();

            try {
                const response = await fetch('process/request_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({ identifier: identifier })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', result.message);
                    
                    // Simpan identifier
                    document.getElementById('hiddenIdentifier').value = identifier;
                    
                    // Switch ke form OTP
                    document.getElementById('requestOtpForm').style.display = 'none';
                    document.getElementById('verifyOtpForm').style.display = 'block';
                    document.getElementById('otpCode').focus();
                    
                    // Start countdown
                    startCountdown(300); // 5 menit
                    
                    // Enable resend after 60 seconds
                    startResendTimer(60);
                } else {
                    showAlert('danger', result.message);
                }
            } catch (err) {
                console.error('Error:', err);
                showAlert('danger', 'Gagal menghubungi server. Silakan coba lagi.');
            } finally {
                btnRequest.disabled = false;
                btnRequest.classList.remove('btn-disabled');
                spinner.style.display = 'none';
            }
        });

        // Verify OTP
        document.getElementById('verifyOtpForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const identifier = document.getElementById('hiddenIdentifier').value;
            const otp = document.getElementById('otpCode').value.trim();
            const btnVerify = document.getElementById('btnVerifyOtp');
            const spinner = document.getElementById('spinnerVerify');

            if (!otp || otp.length !== 6) {
                showAlert('warning', 'Kode OTP harus 6 digit!');
                return;
            }

            if (!/^\d{6}$/.test(otp)) {
                showAlert('warning', 'Kode OTP hanya boleh berisi angka!');
                return;
            }

            btnVerify.disabled = true;
            btnVerify.classList.add('btn-disabled');
            spinner.style.display = 'inline-block';
            hideAlert();

            try {
                const response = await fetch('process/verify_otp_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        identifier: identifier,
                        otp: otp
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', '✓ Login berhasil! Mengalihkan...');
                    
                    setTimeout(() => {
                        window.location.href = result.redirect || 'home.php';
                    }, 1500);
                } else {
                    showAlert('danger', result.message);
                    document.getElementById('otpCode').value = '';
                    document.getElementById('otpCode').focus();
                }
            } catch (err) {
                console.error('Error:', err);
                showAlert('danger', 'Gagal verifikasi. Silakan coba lagi.');
            } finally {
                btnVerify.disabled = false;
                btnVerify.classList.remove('btn-disabled');
                spinner.style.display = 'none';
            }
        });

        // Back button
        document.getElementById('btnBack').addEventListener('click', () => {
            clearInterval(countdownInterval);
            clearTimeout(resendTimeout);
            
            document.getElementById('verifyOtpForm').style.display = 'none';
            document.getElementById('requestOtpForm').style.display = 'block';
            document.getElementById('otpCode').value = '';
            hideAlert();
        });

        // Resend OTP
        document.getElementById('btnResend').addEventListener('click', async () => {
            const identifier = document.getElementById('hiddenIdentifier').value;
            const btnResend = document.getElementById('btnResend');
            
            btnResend.disabled = true;
            hideAlert();

            try {
                const response = await fetch('process/request_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({ identifier: identifier })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', 'Kode OTP baru telah dikirim!');
                    startCountdown(300);
                    startResendTimer(60);
                } else {
                    showAlert('danger', result.message);
                    btnResend.disabled = false;
                }
            } catch (err) {
                showAlert('danger', 'Gagal mengirim ulang OTP.');
                btnResend.disabled = false;
            }
        });

        // Countdown timer
        function startCountdown(seconds) {
            clearInterval(countdownInterval);
            const countdownEl = document.getElementById('countdown');
            
            countdownInterval = setInterval(() => {
                const minutes = Math.floor(seconds / 60);
                const secs = seconds % 60;
                countdownEl.textContent = `Kode berlaku: ${minutes}:${secs.toString().padStart(2, '0')}`;
                
                seconds--;
                
                if (seconds < 0) {
                    clearInterval(countdownInterval);
                    countdownEl.textContent = 'Kode OTP telah kedaluwarsa';
                    countdownEl.style.color = '#d9534f';
                }
            }, 1000);
        }

        // Resend timer
        function startResendTimer(seconds) {
            const btnResend = document.getElementById('btnResend');
            btnResend.disabled = true;
            
            clearTimeout(resendTimeout);
            
            resendTimeout = setTimeout(() => {
                btnResend.disabled = false;
            }, seconds * 1000);
        }

        // Alert functions
        function showAlert(type, message) {
            const alertBox = document.getElementById('alertBox');
            alertBox.className = `alert alert-${type}`;
            alertBox.textContent = message;
            alertBox.classList.remove('d-none');
        }

        function hideAlert() {
            const alertBox = document.getElementById('alertBox');
            alertBox.classList.add('d-none');
        }

        // Only allow numbers in OTP input
        document.getElementById('otpCode').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>