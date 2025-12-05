<?php
session_start();

// Set timezone WIB
date_default_timezone_set('Asia/Jakarta');

include 'process/config_db.php';
require_once 'process/crypto_helper.php';

// Fungsi format tanggal Indonesia
function formatTanggalIndonesia($tanggal)
{
    $bulan = array(
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
    );

    $timestamp = strtotime($tanggal);
    $tgl = date('d', $timestamp);
    $bln = $bulan[date('n', $timestamp)];
    $thn = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);

    return $tgl . ' ' . $bln . ' ' . $thn . ', ' . $jam;
}

// Cek login admin
if (!isset($_SESSION['NIK_NIP']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit;
}

// Ambil daftar tahun yang tersedia di database
try {
    $stmt_tahun = $pdo->query("SELECT DISTINCT YEAR(tgl_daftar) as tahun 
                                FROM pengajuansurat 
                                WHERE tgl_daftar IS NOT NULL 
                                ORDER BY tahun DESC");
    $tahun_list = $stmt_tahun->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error get tahun: " . $e->getMessage());
    $tahun_list = [];
}

// Ambil filter dari URL
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_tipe = isset($_GET['tipe']) ? $_GET['tipe'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query untuk statistik
try {
    // Hitung berdasarkan status
    $stats = [
        'Menunggu Surat Terbit' => 0,
        'Surat Keterangan Terbit' => 0
    ];

    $stmt = $pdo->query("SELECT status_validasi, COUNT(*) as jumlah FROM pengajuansurat GROUP BY status_validasi");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['status_validasi'];
        $jumlah = $row['jumlah'];

        if (isset($stats[$status])) {
            $stats[$status] = $jumlah;
        }
    }

    // Hitung berdasarkan tipe pengajuan
    $stats_tipe = [
        'mandiri' => 0,
        'perpanjangan' => 0
    ];

    $stmt = $pdo->query("SELECT tipe_pengajuan, COUNT(*) as jumlah FROM pengajuansurat GROUP BY tipe_pengajuan");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tipe = strtolower($row['tipe_pengajuan']);
        $jumlah = $row['jumlah'];

        if (isset($stats_tipe[$tipe])) {
            $stats_tipe[$tipe] = $jumlah;
        }
    }
} catch (PDOException $e) {
    error_log("Error statistik: " . $e->getMessage());
    $stats = [];
    $stats_tipe = [];
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Query data pengajuan dengan JOIN
try {
    $sql = "SELECT ps.id_pengajuan, ps.NIK, ps.tgl_daftar, ps.status_validasi, ps.tipe_pengajuan,
                   ps.nama_usaha, ps.nama_pemilik, ps.merek,
                   u.nama_lengkap, u.NIK_NIP
            FROM pengajuansurat ps
            INNER JOIN user u ON ps.NIK = u.NIK_NIP
            WHERE 1=1";

    $params = [];

    if (!empty($filter_tahun)) {
        $sql .= " AND YEAR(ps.tgl_daftar) = ?";
        $params[] = $filter_tahun;
    }

    if (!empty($filter_status)) {
        $sql .= " AND ps.status_validasi = ?";
        $params[] = $filter_status;
    }

    if (!empty($filter_tipe)) {
        $sql .= " AND ps.tipe_pengajuan = ?";
        $params[] = $filter_tipe;
    }

    if (!empty($search)) {
        $sql .= " AND (u.nama_lengkap LIKE ? OR ps.nama_usaha LIKE ? OR ps.merek LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $count_sql = "SELECT COUNT(*) as total FROM ($sql) as subquery";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    $sql .= " ORDER BY ps.tgl_daftar DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pengajuan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error query pengajuan: " . $e->getMessage());
    $pengajuan_list = [];
    $total_pages = 0;
}

// Function untuk badge status
function getBadgeClass($status)
{
    $badges = [
        'Menunggu Surat Terbit' => 'warning',
        'Surat Keterangan Terbit' => 'success'
    ];
    return $badges[$status] ?? 'secondary';
}

// Function untuk display text status
function getDisplayStatus($status)
{
    $displayText = [
        'Menunggu Surat Terbit' => 'MENUNGGU SURAT TERBIT',
        'Surat Keterangan Terbit' => 'SURAT KETERANGAN TERBIT'
    ];
    return $displayText[$status] ?? strtoupper($status);
}

// Function untuk display tipe pengajuan
function getDisplayTipe($tipe)
{
    $displayText = [
        'mandiri' => 'MANDIRI',
        'perpanjangan' => 'PERPANJANGAN'
    ];
    return $displayText[$tipe] ?? strtoupper($tipe);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Surat Keterangan - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <style>
        .badge.text-bg-warning {
            background-color: #f59e0b !important;
            color: #fff !important;
        }

        .badge.text-bg-success {
            background-color: #10b981 !important;
            color: #fff !important;
        }

        .badge.text-bg-danger {
            background-color: #ef4444 !important;
            color: #fff !important;
        }

        .stat-card.bg-warning {
            background: linear-gradient(135deg, #f0741bff 0%, #502100ff 100%);
            color: #fff;
        }

        .stat-card.bg-success {
            background: linear-gradient(135deg, #059669 0%, #00281bff 100%);
            color: #fff;
        }

        .stat-card.bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff;
        }

        .stat-card.bg-info {
            background: linear-gradient(135deg, #05339C 0%, #1055C9 100%);
            color: #fff;
        }
    </style>
</head>

<body>
    <?php include 'navbar-admin.php' ?>

    <main class="container-xxl main-container">
        <div class="col-12 col-lg-12">
            <h6 class="section-title mb-3">Rangkuman Data Pengajuan Surat Keterangan</h6>
            
            <!-- Stat Cards Status -->
            <div class="row g-3 mb-4 row-cols-2 row-cols-md-3 row-cols-lg-3">
                <div class="col">
                    <div class="card stat-card bg-warning h-100">
                        <div class="card-body">
                            <div class="stat-title">Menunggu Surat Terbit</div>
                            <div class="display-6 fw-bold"><?php echo $stats['Menunggu Surat Terbit']; ?></div>
                            <div class="stat-sub">pengajuan</div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card stat-card bg-success h-100">
                        <div class="card-body">
                            <div class="stat-title">Surat Keterangan Terbit</div>
                            <div class="display-6 fw-bold"><?php echo $stats['Surat Keterangan Terbit']; ?></div>
                            <div class="stat-sub">pengajuan</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stat Cards Tipe -->
            <div class="row g-3 mb-4 row-cols-2 row-cols-md-2">
                <div class="col">
                    <div class="card stat-card bg-info h-100">
                        <div class="card-body">
                            <div class="stat-title">Pengajuan Mandiri</div>
                            <div class="display-6 fw-bold"><?php echo $stats_tipe['mandiri']; ?></div>
                            <div class="stat-sub">pengajuan</div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card stat-card bg-info h-100">
                        <div class="card-body">
                            <div class="stat-title">Pengajuan Perpanjangan</div>
                            <div class="display-6 fw-bold"><?php echo $stats_tipe['perpanjangan']; ?></div>
                            <div class="stat-sub">pengajuan</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kelola Data -->
            <div class="mb-4">
                <h6 class="section-title">Kelola Data Pengajuan Surat Keterangan</h6>
                <p class="text-muted small mb-3">
                    Gunakan fitur pencarian untuk menemukan data pengajuan berdasarkan nama pemilik, nama usaha, atau merek.
                    Klik icon lihat untuk menampilkan detail pengajuan.
                </p>

                <form method="GET" action="">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-9">
                            <label for="search" class="form-label small fw-semibold">Pencarian</label>
                            <div class="input-group">
                                <span class="input-group-text" id="search-icon"><i class="bi bi-search"></i></span>
                                <input id="search" name="search" type="search" class="form-control" placeholder="Cari berdasarkan nama pemilik, usaha, atau merek" value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-dark" type="submit"><i class="bi bi-arrow-right"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 align-items-end mt-3 filters mb-4">
                        <div class="col-12">
                            <div class="small text-muted"><strong>Filter</strong></div>
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-1">Berdasarkan Tahun</label>
                            <select name="tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                <?php foreach ($tahun_list as $tahun): ?>
                                    <option value="<?php echo $tahun; ?>" <?php echo $filter_tahun == $tahun ? 'selected' : ''; ?>>
                                        <?php echo $tahun; ?>
                                    </option>
                                <?php endforeach; ?>

                                <?php if (empty($tahun_list)): ?>
                                    <option disabled>Tidak ada data</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-1">Berdasarkan Status</label>
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                <option value="Menunggu Surat Terbit" <?php echo $filter_status == 'Menunggu Surat Terbit' ? 'selected' : ''; ?>>Menunggu Surat Terbit</option>
                                <option value="Surat Keterangan Terbit" <?php echo $filter_status == 'Surat Keterangan Terbit' ? 'selected' : ''; ?>>Surat Keterangan Terbit</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-1">Berdasarkan Tipe</label>
                            <select name="tipe" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Semua</option>
                                <option value="mandiri" <?php echo $filter_tipe == 'mandiri' ? 'selected' : ''; ?>>Mandiri</option>
                                <option value="perpanjangan" <?php echo $filter_tipe == 'perpanjangan' ? 'selected' : ''; ?>>Perpanjangan</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="d-block form-label small opacity-0">Reset</label>
                            <a href="pengajuan-suket.php" class="btn btn-outline-dark btn-sm">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:48px;">
                                    <input class="form-check-input" type="checkbox" aria-label="Pilih semua baris" id="checkAll">
                                </th>
                                <th>Pemohon</th>
                                <th>Usaha / Merek</th>
                                <th>Tipe</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th class="text-center" style="width:120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (empty($pengajuan_list)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-2">Tidak ada data pengajuan</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pengajuan_list as $row): ?>
                                    <tr>
                                        <td><input class="form-check-input row-checkbox" type="checkbox" value="<?php echo $row['id_pengajuan']; ?>"></td>
                                        <td class="text-nowrap">
                                            <span class="avatar-dot me-2"><?php echo strtoupper(substr($row['nama_pemilik'], 0, 1)); ?></span>
                                            <?php echo strtoupper(htmlspecialchars($row['nama_pemilik'])); ?>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div class="fw-semibold"><?php echo htmlspecialchars($row['nama_usaha']); ?></div>
                                                <div class="text-muted"><?php echo htmlspecialchars($row['merek']); ?></div>
                                            </div>
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="badge text-bg-light text-dark">
                                                <?php echo getDisplayTipe($row['tipe_pengajuan']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-<?php echo getBadgeClass($row['status_validasi']); ?>">
                                                <?php echo getDisplayStatus($row['status_validasi']); ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap"><?php echo formatTanggalIndonesia($row['tgl_daftar']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="detail-pengajuan-suket.php?token=<?php echo urlencode(encryptId($row['id_pengajuan'])); ?>" class="btn btn-primary btn-icon">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button class="btn btn-danger btn-icon" onclick="confirmDelete('<?php echo urlencode(encryptId($row['id_pengajuan'])); ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                    <!-- Pagination -->
                    <nav aria-label="Navigasi halaman">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&tahun=<?php echo $filter_tahun; ?>&status=<?php echo $filter_status; ?>&tipe=<?php echo $filter_tipe; ?>&search=<?php echo $search; ?>">
                                    <span>&laquo;</span>
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&tahun=<?php echo $filter_tahun; ?>&status=<?php echo $filter_status; ?>&tipe=<?php echo $filter_tipe; ?>&search=<?php echo $search; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&tahun=<?php echo $filter_tahun; ?>&status=<?php echo $filter_status; ?>&tipe=<?php echo $filter_tipe; ?>&search=<?php echo $search; ?>">
                                    <span>&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <!-- Button Download -->
                    <a href="process/export_pengajuan_suket.php" class="btn btn-dark btn-sm">
                        <i class="bi bi-download me-2"></i>Download Rekap Pengajuan
                    </a>
                </div>
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
        // Bootstrap Alert Modal
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

        // Konfirmasi hapus dengan modal
        function confirmDelete(token) {
            const confirmModal = `
            <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-body text-center p-4">
                            <div class="fs-1 mb-3">⚠️</div>
                            <h5 class="mb-3">Konfirmasi Hapus</h5>
                            <p class="mb-0">Apakah Anda yakin ingin menghapus data pengajuan ini?</p>
                        </div>
                        <div class="modal-footer border-0 justify-content-center gap-2">
                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Batal</button>
                            <button type="button" class="btn btn-danger px-4" id="confirmDeleteAction">
                                <i class="bi bi-trash me-1"></i> Hapus
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

            const existingModal = document.getElementById('confirmDeleteModal');
            if (existingModal) existingModal.remove();

            document.body.insertAdjacentHTML('beforeend', confirmModal);
            const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            modal.show();

            document.getElementById('confirmDeleteAction').addEventListener('click', function() {
                window.location.href = 'process/delete_pengajuan_suket.php?token=' + encodeURIComponent(token);
            });

            document.getElementById('confirmDeleteModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Check All functionality
        document.getElementById('checkAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });
    </script>
</body>

</html>