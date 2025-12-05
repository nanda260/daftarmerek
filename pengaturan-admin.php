<?php
session_start();
require_once 'process/config_db.php';

// Cek login admin
if (!isset($_SESSION['NIK_NIP']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit;
}

// Handler untuk update pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'update_contact') {
            $no_telp = trim($_POST['no_telp']);
            
            if (empty($no_telp)) {
                echo json_encode(['success' => false, 'message' => 'Nomor telepon tidak boleh kosong']);
                exit;
            }
            
            // Update atau insert setting
            $stmt = $pdo->prepare("
                INSERT INTO pengaturan (setting_key, setting_value, updated_at) 
                VALUES ('contact_person', ?, NOW())
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                updated_at = NOW()
            ");
            $stmt->execute([$no_telp]);
            
            echo json_encode(['success' => true, 'message' => 'Nomor telepon berhasil diperbarui']);
            exit;
        }
        
        if ($_POST['action'] === 'update_jenis_usaha') {
            $jenis_usaha_json = $_POST['jenis_usaha'];
            
            if (empty($jenis_usaha_json)) {
                echo json_encode(['success' => false, 'message' => 'Data jenis usaha tidak valid']);
                exit;
            }
            
            // Validasi JSON
            $jenis_usaha_array = json_decode($jenis_usaha_json, true);
            if (!is_array($jenis_usaha_array)) {
                echo json_encode(['success' => false, 'message' => 'Format data tidak valid']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO pengaturan (setting_key, setting_value, updated_at) 
                VALUES ('jenis_usaha', ?, NOW())
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                updated_at = NOW()
            ");
            $stmt->execute([$jenis_usaha_json]);
            
            echo json_encode(['success' => true, 'message' => 'Jenis usaha berhasil diperbarui']);
            exit;
        }
        
    } catch (PDOException $e) {
        error_log("Error update settings: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan database']);
        exit;
    }
}

// Ambil data pengaturan saat ini
try {
    // Ambil contact person
    $stmt = $pdo->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'contact_person'");
    $stmt->execute();
    $contact_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $contact_person = $contact_result ? $contact_result['setting_value'] : '';
    
    // Hapus prefix 62 untuk ditampilkan (jika ada)
    $contact_person_display = preg_replace('/^62/', '', $contact_person);
    
    // Ambil jenis usaha
    $stmt = $pdo->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'jenis_usaha'");
    $stmt->execute();
    $jenis_usaha_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $jenis_usaha_json = $jenis_usaha_result ? $jenis_usaha_result['setting_value'] : '[]';
    $jenis_usaha_array = json_decode($jenis_usaha_json, true) ?: [];
    
} catch (PDOException $e) {
    error_log("Error get settings: " . $e->getMessage());
    $contact_person_display = '';
    $jenis_usaha_array = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>

<body>
    <?php include 'navbar-admin.php' ?>

    <main class="container-xxl main-container">
        <div class="mb-3">
            <h6 class="section-title mb-1">Pengaturan Sistem</h6>
            <p class="text-muted small mb-0">Kelola pengaturan aplikasi pendaftaran merek</p>
        </div>

        <!-- Card Contact Person -->
        <div class="card mb-4 border-0" style="box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div class="card-body p-3 p-md-4">
                <h6 class="fw-semibold mb-1">
                    <i class="bi bi-telephone-fill me-2"></i>
                    Contact Person
                </h6>
                <p class="text-muted small mb-3">
                    Nomor telepon yang akan ditampilkan sebagai contact person di aplikasi
                </p>
                
                <form id="formContactPerson">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="no_telp" class="form-label small fw-semibold">Nomor Telepon</label>
                            <div class="input-group">
                                <span class="input-group-text">+62</span>
                                <input type="text" 
                                       class="form-control" 
                                       id="no_telp" 
                                       name="no_telp" 
                                       placeholder="8123456789"
                                       value="<?php echo htmlspecialchars($contact_person_display); ?>"
                                       pattern="[0-9]{9,12}"
                                       required>
                            </div>
                            <div class="form-text small">
                                <i class="bi bi-info-circle me-1"></i>
                                Masukkan nomor tanpa awalan 0 (contoh: 8123456789)
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-dark" id="btnSaveContact">
                                    <i class="bi bi-save me-2"></i>Simpan
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Card Jenis Usaha -->
        <div class="card border-0" style="box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div class="card-body p-3 p-md-4">
                <h6 class="fw-semibold mb-1">
                    <i class="bi bi-building me-2"></i>
                    Jenis Usaha
                </h6>
                <p class="text-muted small mb-3">
                    Kelola daftar jenis usaha yang tersedia di <strong>Form Pendaftaran dan Pengajuan Mandiri </strong>
                </p>
                
                <form id="formJenisUsaha">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Daftar Jenis Usaha</label>
                            <div id="jenisUsahaList" class="mb-3">
                                <?php if (empty($jenis_usaha_array)): ?>
                                    <div class="input-group mb-2 jenis-usaha-item">
                                        <input type="text" 
                                               class="form-control jenis-usaha-input" 
                                               placeholder="Nama jenis usaha"
                                               required>
                                        <button class="btn btn-danger" 
                                                type="button" 
                                                onclick="removeJenisUsaha(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($jenis_usaha_array as $index => $usaha): ?>
                                        <div class="input-group mb-2 jenis-usaha-item">
                                            <input type="text" 
                                                   class="form-control jenis-usaha-input" 
                                                   value="<?php echo htmlspecialchars($usaha); ?>"
                                                   placeholder="Nama jenis usaha"
                                                   required>
                                            <button class="btn btn-danger" 
                                                    type="button" 
                                                    onclick="removeJenisUsaha(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" 
                                    class="btn btn-outline-dark btn-sm mb-3" 
                                    onclick="addJenisUsaha()">
                                <i class="bi bi-plus-circle me-1"></i>Tambah Jenis Usaha
                            </button>
                            
                            <div class="form-text small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                Jenis usaha ini akan muncul sebagai pilihan dropdown di Form Pendaftaran dan Pengajuan Mandiri
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-dark" id="btnSaveJenisUsaha">
                                    <i class="bi bi-save me-2"></i>Simpan
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Back Button -->
        <div class="mt-4">
            <div class="d-flex justify-content-end">
                <a href="dashboard-admin.php" class="btn btn-outline-dark">
                    <i class="bi bi-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>Copyright © 2025. All Rights Reserved.</p>
            <p>Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Alert Modal
        function showAlert(message, type = 'success') {
            const icon = type === 'success' ? '✅' : type === 'danger' ? '❌' : '⚠️';
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

        // Handler Contact Person
        document.getElementById('formContactPerson').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btnSaveContact');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
            
            // Format nomor dengan 62 (tanpa +)
            let noTelp = document.getElementById('no_telp').value.trim();
            // Hilangkan awalan 0 jika ada
            noTelp = noTelp.replace(/^0+/, '');
            // Tambahkan prefix 62 (tanpa +)
            const formattedPhone = '62' + noTelp;
            
            const formData = new FormData();
            formData.append('action', 'update_contact');
            formData.append('no_telp', formattedPhone);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Terjadi kesalahan saat menyimpan', 'danger');
                console.error('Error:', error);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });

        // Handler Jenis Usaha
        document.getElementById('formJenisUsaha').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btnSaveJenisUsaha');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
            
            // Collect all jenis usaha
            const jenisUsahaInputs = document.querySelectorAll('.jenis-usaha-input');
            const jenisUsahaArray = Array.from(jenisUsahaInputs)
                .map(input => input.value.trim())
                .filter(value => value !== '');
            
            if (jenisUsahaArray.length === 0) {
                showAlert('Minimal harus ada satu jenis usaha', 'danger');
                btn.disabled = false;
                btn.innerHTML = originalText;
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_jenis_usaha');
            formData.append('jenis_usaha', JSON.stringify(jenisUsahaArray));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Terjadi kesalahan saat menyimpan', 'danger');
                console.error('Error:', error);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });

        // Add Jenis Usaha
        function addJenisUsaha() {
            const listContainer = document.getElementById('jenisUsahaList');
            const newItem = document.createElement('div');
            newItem.className = 'input-group mb-2 jenis-usaha-item';
            newItem.innerHTML = `
                <input type="text" 
                       class="form-control jenis-usaha-input" 
                       placeholder="Nama jenis usaha"
                       required>
                <button class="btn btn-danger" 
                        type="button" 
                        onclick="removeJenisUsaha(this)">
                    <i class="bi bi-trash"></i>
                </button>
            `;
            listContainer.appendChild(newItem);
        }

        // Remove Jenis Usaha
        function removeJenisUsaha(button) {
            const items = document.querySelectorAll('.jenis-usaha-item');
            if (items.length <= 1) {
                showAlert('Minimal harus ada satu jenis usaha', 'danger');
                return;
            }
            const item = button.closest('.jenis-usaha-item');
            item.remove();
        }
    </script>
</body>
</html>