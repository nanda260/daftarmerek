<?php
session_start();
require_once 'process/config_db.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['NIK_NIP']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

// Proses tambah admin baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nip = trim($_POST['nip']);
    $email = trim($_POST['email']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $no_wa = trim($_POST['no_wa']);

    $errors = [];

    if (empty($nip)) {
        $errors[] = "NIP tidak boleh kosong";
    }

    if (empty($nama_lengkap)) {
        $errors[] = "Nama lengkap tidak boleh kosong";
    }

    if (empty($email)) {
        $errors[] = "Email tidak boleh kosong";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }

    if (empty($no_wa)) {
        $errors[] = "Nomor WhatsApp tidak boleh kosong";
    } elseif (!preg_match('/^(08|62)\d{8,12}$/', $no_wa)) {
        $errors[] = "Format nomor WhatsApp tidak valid (contoh: 081234567890 atau 6281234567890)";
    } else {
        if (substr($no_wa, 0, 1) === '0') {
            $no_wa = '62' . substr($no_wa, 1);
        }
    }


    // Cek NIP sudah terdaftar atau belum
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT NIK_NIP FROM user WHERE NIK_NIP = ?");
        $stmt->execute([$nip]);
        if ($stmt->fetch()) {
            $errors[] = "NIP sudah terdaftar";
        }
    }

    // Cek email sudah terdaftar atau belum
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT NIK_NIP FROM user WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email sudah terdaftar";
        }
    }

    // Cek nomor WhatsApp sudah terdaftar atau belum
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT NIK_NIP FROM user WHERE no_wa = ?");
        $stmt->execute([$no_wa]);
        if ($stmt->fetch()) {
            $errors[] = "Nomor WhatsApp sudah terdaftar";
        }
    }

    if (empty($errors)) {
        $role = 'Admin';
        $is_verified = 1;
        $tanggal_buat = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("INSERT INTO user (NIK_NIP, nama_lengkap, email, no_wa, role, is_verified, tanggal_buat) VALUES (?, ?, ?, ?, ?, ?, ?)");

        if ($stmt->execute([$nip, $nama_lengkap, $email, $no_wa, $role, $is_verified, $tanggal_buat])) {
            $_SESSION['success_message'] = "Admin baru berhasil ditambahkan";
            header('Location: kelola-admin.php');
            exit();
        } else {
            $errors[] = "Gagal menambahkan admin";
        }
    }

    $_SESSION['errors'] = $errors;
}

// Proses hapus admin
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['nip'])) {
    $admin_nip = trim($_GET['nip']);

    if ($admin_nip == $_SESSION['NIK_NIP']) {
        $_SESSION['errors'] = ["Anda tidak dapat menghapus akun Anda sendiri"];
    } else {
        $stmt = $pdo->prepare("DELETE FROM user WHERE NIK_NIP = ? AND role = 'Admin'");

        if ($stmt->execute([$admin_nip])) {
            $_SESSION['success_message'] = "Admin berhasil dihapus";
        } else {
            $_SESSION['errors'] = ["Gagal menghapus admin"];
        }
    }

    header('Location: kelola-admin.php');
    exit();
}

// Proses update admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $admin_nip = trim($_POST['admin_nip']);
    $email = trim($_POST['email']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $no_wa = trim($_POST['no_wa']);

    $errors = [];

    if (empty($nama_lengkap)) {
        $errors[] = "Nama lengkap tidak boleh kosong";
    }

    if (empty($email)) {
        $errors[] = "Email tidak boleh kosong";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }

    if (empty($no_wa)) {
        $errors[] = "Nomor WhatsApp tidak boleh kosong";
    } elseif (!preg_match('/^(08|62)\d{8,12}$/', $no_wa)) {
        $errors[] = "Format nomor WhatsApp tidak valid (contoh: 081234567890 atau 6281234567890)";
    } else {
        if (substr($no_wa, 0, 1) === '0') {
            $no_wa = '62' . substr($no_wa, 1);
        }
    }
    // Cek email sudah digunakan admin lain atau belum
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT NIK_NIP FROM user WHERE email = ? AND NIK_NIP != ?");
        $stmt->execute([$email, $admin_nip]);
        if ($stmt->fetch()) {
            $errors[] = "Email sudah digunakan oleh admin lain";
        }
    }

    // Cek nomor WhatsApp sudah digunakan admin lain atau belum
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT NIK_NIP FROM user WHERE no_wa = ? AND NIK_NIP != ?");
        $stmt->execute([$no_wa, $admin_nip]);
        if ($stmt->fetch()) {
            $errors[] = "Nomor WhatsApp sudah digunakan oleh admin lain";
        }
    }

    if (empty($errors)) {
        $updated_at = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("UPDATE user SET nama_lengkap = ?, email = ?, no_wa = ?, updated_at = ? WHERE NIK_NIP = ? AND role = 'Admin'");
        $result = $stmt->execute([$nama_lengkap, $email, $no_wa, $updated_at, $admin_nip]);

        if ($result) {
            $_SESSION['success_message'] = "Data admin berhasil diupdate";
        } else {
            $errors[] = "Gagal mengupdate admin";
        }
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
    }

    header('Location: kelola-admin.php');
    exit();
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM user WHERE role = 'Admin' AND (email LIKE ? OR nama_lengkap LIKE ? OR NIK_NIP LIKE ? OR no_wa LIKE ?)");
    $search_param = "%$search%";
    $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
} else {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user WHERE role = 'Admin'");
}
$total_result = $stmt->fetch(PDO::FETCH_ASSOC);
$total_admins = $total_result['total'];
$total_pages = ceil($total_admins / $limit);


if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT NIK_NIP, nama_lengkap, email, no_wa, tanggal_buat FROM user WHERE role = 'Admin' AND (email LIKE ? OR nama_lengkap LIKE ? OR NIK_NIP LIKE ? OR no_wa LIKE ?) ORDER BY tanggal_buat DESC LIMIT ? OFFSET ?");
    $search_param = "%$search%";
    $stmt->execute([$search_param, $search_param, $search_param, $search_param, $limit, $offset]);
} else {
    $stmt = $pdo->prepare("SELECT NIK_NIP, nama_lengkap, email, no_wa, tanggal_buat FROM user WHERE role = 'Admin' ORDER BY tanggal_buat DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
}
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Kelola Data Admin - Pendaftaran Merek</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/kelola-akun-admin.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>

<body>
    <?php include 'navbar-admin.php' ?>

    <main class="container-xxl main-container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-7">
                <div class="mb-3">
                    <div class="section-title">Kelola Data Admin</div>
                    <div class="section-desc">Gunakan fitur pencarian untuk menemukan data admin. Klik aksi untuk mengedit atau menghapus.</div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['errors'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($_SESSION['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['errors']); ?>
                <?php endif; ?>

                <!-- Search  -->
                <form method="GET" action="kelola-admin.php" class="search-wrap mb-3 mb-md-4">
                    <div class="input-group">
                        <input id="searchAdmin" name="search" type="text" class="form-control" placeholder="Cari data admin (NIP, Nama, Email, WhatsApp)" value="<?php echo htmlspecialchars($search); ?>" />
                        <button type="submit" class="btn btn-dark"><i class="bi bi-search"></i></button>
                    </div>
                </form>

                <div class="admin-list d-flex flex-column gap-3" style="margin-bottom: 50px;">
                    <?php if (empty($admins)): ?>
                        <div class="card-lite p-4 text-center text-muted">
                            Tidak ada data admin<?php echo !empty($search) ? ' yang sesuai dengan pencarian' : ''; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <div class="card-lite p-3 p-md-4 admin-item" data-admin-nip="<?php echo htmlspecialchars($admin['NIK_NIP']); ?>">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($admin['nama_lengkap']); ?></h6>
                                        <div class="admin-meta mb-1">
                                            <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($admin['email']); ?>
                                        </div>
                                        <div class="admin-meta mb-1">
                                            <i class="bi bi-whatsapp me-1"></i><?php echo htmlspecialchars($admin['no_wa']); ?>
                                        </div>
                                        <div class="admin-meta mb-1">
                                            <i class="bi bi-card-text me-1"></i>NIP: <?php echo htmlspecialchars($admin['NIK_NIP']); ?>
                                        </div>
                                        <div class="admin-meta">
                                            <i class="bi bi-calendar-check me-1"></i>Didaftarkan pada <?php echo date('d/m/Y H:i:s', strtotime($admin['tanggal_buat'])); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-success btn-edit"
                                        data-nip="<?php echo htmlspecialchars($admin['NIK_NIP']); ?>"
                                        data-nama="<?php echo htmlspecialchars($admin['nama_lengkap']); ?>"
                                        data-email="<?php echo htmlspecialchars($admin['email']); ?>"
                                        data-whatsapp="<?php echo htmlspecialchars($admin['no_wa']); ?>">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </button>
                                    <?php if ($admin['NIK_NIP'] != $_SESSION['NIK_NIP']): ?>
                                        <button class="btn btn-danger btn-delete" data-nip="<?php echo htmlspecialchars($admin['NIK_NIP']); ?>" data-nama="<?php echo htmlspecialchars($admin['nama_lengkap']); ?>">
                                            <i class="bi bi-trash3 me-1"></i> Hapus akun
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="bi bi-person-check me-1"></i> Akun Anda
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Navigasi halaman" class="mb-5">
                            <ul class="pagination pagination-sm mb-0 justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Sebelumnya">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Berikutnya">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5 mb-5">
                <div class="card-lite p-3 p-md-4">
                    <h6 class="fw-bold mb-3" id="formTitle">Tambah Data Admin</h6>

                    <form method="POST" action="kelola-admin.php" id="adminForm">
                        <input type="hidden" name="action" value="add" id="formAction">
                        <input type="hidden" name="admin_nip" value="" id="adminNip">

                        <div class="mb-3">
                            <label for="nip" class="form-label">Nomor Induk Pegawai (NIP)</label>
                            <input type="text" class="form-control" id="nip" name="nip" placeholder="Masukkan NIP" required />
                            <small class="form-text text-muted">NIP akan digunakan sebagai identitas</small>
                        </div>

                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required />
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="email@example.com" required />
                            <small class="form-text text-muted">Digunakan untuk login dengan OTP</small>
                        </div>

                        <div class="mb-4">
                            <label for="no_wa" class="form-label">Nomor WhatsApp</label>
                            <input type="text" class="form-control" id="no_wa" name="no_wa" placeholder="081234567890" required />
                            <small class="form-text text-muted">Digunakan untuk login dengan OTP (contoh: 081234567890)</small>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-submit1" id="submitBtn">
                                <i class="bi bi-person-plus me-1"></i> Daftar Akun
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;">
                                <i class="bi bi-x-circle me-1"></i> Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus akun admin:</p>
                    <p class="fw-bold mb-0" id="deleteAdminName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="#" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash3 me-1"></i> Hapus
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
    <script src="assets/js/kelola-admin.js"></script>
</body>

</html>