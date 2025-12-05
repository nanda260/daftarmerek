<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil Pemohon - Disperindag Sidoarjo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/registrasi.css">
</head>

<body>
    <!-- Navbar -->
    <?php include 'navbar-admin.php' ?>

    <!-- Registrasti Section -->
    <section class="hero-section1">
        <div class="registration-card">
            <h2>Detail Data Pemohon</h2>

            <div class="mb-3">
                <label for="namaPemilik" class="form-label">Nama Pemilik</label>
                <input type="text" class="form-control" id="namaPemilik" value="KHOIRUL ANAM">
            </div>

            <div class="mb-3">
                <label for="nik" class="form-label">NIK</label>
                <input type="text" class="form-control" id="nik" value="3575152983921744	">
            </div>

            <div class="mb-3">
                <label class="form-label">Alamat Pemilik</label>
                <div class="row">
                    <div class="col-md-6">
                        <input type="text" class="form-control mb-3" value="002/005">
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control" value="Sidokumpul" required>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="kecamatan" class="form-label">Kecamatan</label>
                <input type="text" class="form-control" id="kecamatan" value="Sidoarjo">
            </div>

            <div class="mb-3">
                <label for="telepon" class="form-label">Nomor WhatsApp</label>
                <input type="tel" class="form-control" id="telepon" value="085233499260">
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" value="khoirulanam12@gmail.com">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" value="anamhoka123">
            </div>

            <div class="mb-3">
                <label class="form-label">File KTP</label>
                <div class="file-upload-container">
                    <img class="attach-img" alt="Foto Produk 3" src="https://picsum.photos/seed/ktp/600/400" />
                    <div class="text-start mt-2 mb-3">
                        <button class="btn btn-dark btn-sm btn-view"
                            data-src="https://picsum.photos/seed/ktp/600/400"
                            data-title="Foto Produk">
                            <i class="bi bi-eye me-1"></i>View
                        </button>
                    </div>
                </div>
                <div class="file-info">Upload 1 file (PDF atau image). Maks 1 MB</div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-kembali" onclick="window.history.back()">Kembali</button>
            </div>
        </div>
    </section>

    <!-- Modal View Foto -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-white border-0">
                <div class="modal-body text-center position-relative">
                    <button type="button" class="btn-close btn-close-dark position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    <h6 class="text-dark mb-3" id="modalTitle"></h6>
                    <img id="modalImage" src="" alt="Preview" class="img-fluid rounded mb-3" />
                    <div>
                        <a id="downloadBtn" href="#" download class="btn btn-success">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
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

        document.querySelectorAll('.btn-view').forEach(btn => {
            btn.addEventListener('click', () => {
                const src = btn.getAttribute('data-src');
                const title = btn.getAttribute('data-title');
                const modalImg = document.getElementById('modalImage');
                const modalTitle = document.getElementById('modalTitle');
                const downloadBtn = document.getElementById('downloadBtn');

                modalImg.src = src;
                modalTitle.textContent = title;
                downloadBtn.href = src;

                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
            });
        });
    </script>
</body>

</html>