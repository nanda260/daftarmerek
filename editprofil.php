<?php
session_start();
require_once 'process/config_db.php';

// Cek apakah user sudah login
if (!isset($_SESSION['NIK_NIP'])) {
    header("Location: login.php");
    exit();
}

$user_nik = $_SESSION['NIK_NIP'];

// Ambil data user menggunakan PDO
$stmt = $pdo->prepare("SELECT * FROM user WHERE NIK_NIP = ?");
$stmt->execute([$user_nik]);
$user = $stmt->fetch();

if (!$user) {
    echo "Data user tidak ditemukan.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil Pemohon - Disperindag Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/registrasi.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>

<body>
    <!-- Navbar -->
    <?php include 'navbar-login.php' ?>

    <!-- Edit Profil Section -->
    <section class="hero-section">
        <div class="registration-card">
            <h2>Edit Profil - Pemohon</h2>
            <form id="editProfilForm" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="namaPemilik" class="form-label">Nama Pemilik</label>
                    <input type="text" name="namaPemilik" class="form-control" id="namaPemilik" value="<?= htmlspecialchars($user['nama_lengkap']); ?>" readonly>
                    <small class="text-muted">Nama tidak dapat diubah</small>
                </div>

                <div class="mb-3">
                    <label for="nik" class="form-label">NIK</label>
                    <input type="text" name="NIK_NIP" class="form-control" id="nik" value="<?= htmlspecialchars($user['NIK_NIP']); ?>" maxlength="16" readonly>
                    <small class="text-muted">NIK tidak dapat diubah</small>
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
                            <select class="form-select select2-dropdown" id="kabupaten" name="kabupaten" required>
                                <option value="">Pilih Kabupaten/Kota</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="kecamatan" class="form-label">Kecamatan</label>
                            <select class="form-select select2-dropdown" id="kecamatan" name="kecamatan" required>
                                <option value="">Pilih Kecamatan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="kel_desa" class="form-label">Kelurahan/Desa</label>
                            <select class="form-select select2-dropdown" id="kel_desa" name="kel_desa" required>
                                <option value="">Pilih Kelurahan/Desa</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label for="rt_rw" class="form-label">RT/RW</label>
                            <input type="text" class="form-control" id="rt_rw" name="rt_rw" placeholder="Contoh: 002/006" maxlength="7" value="<?= htmlspecialchars($user['rt_rw']); ?>" required>
                            <small class="text-muted d-block mt-1">Contoh Format: 002/006</small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="telepon" class="form-label">Nomor WhatsApp</label>
                    <input type="tel" class="form-control" name="telepon" id="telepon" value="<?= htmlspecialchars($user['no_wa']); ?>" required>
                    <small class="text-muted">Contoh: 6281234567890 (digunakan untuk login)</small>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">File KTP</label>
                    <div class="file-upload-container">
                        <div class="file-input-wrapper">
                            <input type="file" id="fileKTP" name="fileKTP" accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName()">
                            <label for="fileKTP" class="file-upload-label">Pilih File Baru</label>
                            <span id="fileName" class="file-name">Tidak ada file yang dipilih.</span>
                        </div>

                        <!-- Preview KTP Saat Ini -->
                        <?php if (!empty($user['foto_ktp'])): ?>
                            <div id="currentKtpPreview" class="ktp-preview-wrapper show">
                                <div class="mb-2">
                                    <strong style="color: #161616;">KTP Saat Ini:</strong>
                                </div>
                                <?php
                                $file_extension = strtolower(pathinfo($user['foto_ktp'], PATHINFO_EXTENSION));
                                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])):
                                ?>
                                    <div class="preview-image-container">
                                        <img src="<?= htmlspecialchars($user['foto_ktp']); ?>" alt="KTP Saat Ini" class="ktp-preview-img show">
                                        <div class="preview-blur-overlay">
                                            <i class="bi bi-lock-fill"></i> Data Pribadi Disamarkan
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="pdf-preview-box show">
                                        <div class="pdf-icon"><i class="bi bi-file-pdf-fill"></i></div>
                                        <p class="mb-0"><strong><?= basename($user['foto_ktp']); ?></strong></p>
                                        <small class="text-muted">File PDF tersimpan</small>
                                    </div>
                                <?php endif; ?>

                                <div class="preview-actions">
                                    <a href="<?= htmlspecialchars($user['foto_ktp']); ?>" target="_blank" class="btn btn-sm" style="background: #0d6efd; color: white; text-decoration: none; padding: 6px 16px; border-radius: 6px; font-size: 14px;">
                                        <i class="bi bi-box-arrow-up-right"></i> Buka File
                                    </a>
                                </div>

                                <div class="security-badge">
                                    <i class="bi bi-shield-fill-check"></i>
                                    Data pribadi Anda dilindungi dengan enkripsi
                                </div>

                            </div>
                        <?php endif; ?>

                        <!-- Preview KTP -->
                        <div id="ktpPreviewContainer" class="ktp-preview-wrapper">
                            <div class="mb-2">
                                <strong style="color: #161616;">Preview KTP Baru:</strong>
                            </div>

                            <div class="preview-image-container">
                                <img id="ktpPreviewImg" class="ktp-preview-img" alt="Preview KTP">
                                <div class="preview-blur-overlay">
                                    <i class="bi bi-lock-fill"></i> Data Pribadi Disamarkan
                                </div>
                            </div>

                            <div id="pdfPreviewBox" class="pdf-preview-box">
                                <div class="pdf-icon"><i class="bi bi-file-pdf-fill"></i></div>
                                <p class="mb-0"><strong id="pdfFileName"></strong></p>
                                <small class="text-muted">File PDF siap diupload</small>
                            </div>

                            <div class="preview-actions">
                                <span class="file-size-info" id="fileSizeInfo"></span>
                                <button type="button" class="btn-remove-file" onclick="clearFilePreview()">Hapus File</button>
                            </div>

                            <div class="security-badge">
                                <i class="bi bi-shield-fill-check"></i>
                                Data pribadi Anda dilindungi dengan enkripsi
                            </div>
                        </div>
                    </div>
                    <div class="file-info">Upload file baru untuk mengganti KTP (Opsional). Maks 1 MB</div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-kembali" onclick="window.history.back()">Kembali</button>
                    <button type="submit" class="btn btn-registrasi" id="btnSubmit">
                        Simpan Perubahan
                        <span class="loading-spinner" id="loadingSpinner" style="display:none;"></span>
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

    <!-- Hidden inputs untuk menyimpan data wilayah dari database -->
    <input type="hidden" id="current_provinsi" value="<?= htmlspecialchars($user['kode_provinsi'] ?? ''); ?>">
    <input type="hidden" id="current_kabupaten" value="<?= htmlspecialchars($user['kode_kabupaten'] ?? ''); ?>">
    <input type="hidden" id="current_kecamatan" value="<?= htmlspecialchars($user['kode_kecamatan'] ?? ''); ?>">
    <input type="hidden" id="current_desa" value="<?= htmlspecialchars($user['kode_kel_desa'] ?? ''); ?>">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>
    <script src="assets/js/edit-profil.js"></script>
</body>

</html>