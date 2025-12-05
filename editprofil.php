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
    <link rel="stylesheet" href="assets/css/registrasi.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <style>
        .preview-container {
            margin-top: 10px;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background-color: #f8f9fa;
            display: none;
        }
        .preview-container.show {
            display: block;
        }
        .preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .btn-remove-preview {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-remove-preview:hover {
            background: #c82333;
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
            padding: 5px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
    </style>
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
                    <input type="text" name="namaPemilik" class="form-control" id="namaPemilik" value="<?= htmlspecialchars($user['nama_lengkap']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="nik" class="form-label">NIK</label>
                    <input type="text" name="NIK_NIP" class="form-control" id="nik" value="<?= htmlspecialchars($user['NIK_NIP']); ?>" maxlength="16" required>
                    <small class="text-muted">16 digit NIK</small>
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
                            <input type="text" class="form-control" name="rt_rw" id="rt_rw" placeholder="Contoh: 002/006" value="<?= htmlspecialchars($user['rt_rw']); ?>" maxlength="7" required>
                            <small class="text-muted d-block mt-1">Format otomatis: 002/006</small>
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
                    <label for="fileKTP" class="form-label">Upload KTP (opsional)</label>
                    <input type="file" id="fileKTP" name="fileKTP" accept=".pdf,.jpg,.jpeg,.png" class="form-control">
                    
                    <!-- Preview KTP Saat Ini -->
                    <?php if (!empty($user['foto_ktp'])): ?>
                        <div id="currentKtpPreview" class="preview-container show" style="display: block;">
                            <div class="preview-header">
                                <strong>KTP Saat Ini:</strong>
                                <a href="<?= htmlspecialchars($user['foto_ktp']); ?>" target="_blank" class="btn btn-sm btn-primary">Buka di Tab Baru</a>
                            </div>
                            <?php 
                            $file_extension = strtolower(pathinfo($user['foto_ktp'], PATHINFO_EXTENSION));
                            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): 
                            ?>
                                <img src="<?= htmlspecialchars($user['foto_ktp']); ?>" alt="KTP Saat Ini" class="preview-image">
                            <?php else: ?>
                                <div class="text-center p-3">
                                    <i class="bi bi-file-earmark-pdf" style="font-size: 48px;"></i>
                                    <p class="mb-0 mt-2"><strong>File PDF:</strong> <?= basename($user['foto_ktp']); ?></p>
                                    <small class="text-muted">Klik "Buka di Tab Baru" untuk melihat file</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Preview Container untuk KTP Baru -->
                    <div id="previewContainer" class="preview-container">
                        <div class="preview-header">
                            <strong>Preview KTP Baru:</strong>
                            <button type="button" class="btn-remove-preview" onclick="removePreview()">Hapus</button>
                        </div>
                        <img id="previewImage" src="" alt="Preview KTP" class="preview-image">
                        <div id="pdfPreview" style="display:none;">
                            <p class="mb-0"><strong>File PDF:</strong> <span id="pdfFileName"></span></p>
                            <small class="text-muted">Preview PDF tidak tersedia. File akan diupload saat Anda menyimpan.</small>
                        </div>
                    </div>
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
    <script src="assets/js/edit-profil.js"></script>
</body>

</html>