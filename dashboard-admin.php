<?php
session_start();

// Set timezone WIB
date_default_timezone_set('Asia/Jakarta');

include 'process/config_db.php';

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
                                FROM pendaftaran 
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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query untuk statistik
try {
    $total_kuota = 100;

    $kuota_statuses = [
        'Surat Keterangan Difasilitasi',
        'Menunggu Bukti Pendaftaran',
        'Bukti Pendaftaran Terbit dan Diajukan ke Kementerian',
        'Hasil Verifikasi Kementerian'
    ];

    $placeholders = str_repeat('?,', count($kuota_statuses) - 1) . '?';
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pendaftaran WHERE status_validasi IN ($placeholders)");
    $stmt->execute($kuota_statuses);
    $total_pendaftar = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $kuota_tersedia = $total_kuota - $total_pendaftar;

    // Hitung berdasarkan status - UPDATE nama status
    $stats = [
        'Pengecekan Berkas' => 0,
        'Tidak Bisa Difasilitasi' => 0,
        'Konfirmasi Lanjut' => 0,
        'Surat Keterangan Difasilitasi' => 0,
        'Menunggu Bukti Pendaftaran' => 0,
        'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian' => 0,
        'Hasil Verifikasi Kementerian' => 0
    ];

    $stmt = $pdo->query("SELECT status_validasi, COUNT(*) as jumlah FROM pendaftaran GROUP BY status_validasi");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['status_validasi'];
        $jumlah = $row['jumlah'];

        if (isset($stats[$status])) {
            $stats[$status] = $jumlah;
        }
    }
} catch (PDOException $e) {
    error_log("Error statistik: " . $e->getMessage());
    $kuota_tersedia = $total_kuota;
    $total_pendaftar = 0;
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Query data pendaftaran dengan JOIN
try {
    $sql = "SELECT p.id_pendaftaran, p.NIK, p.tgl_daftar, p.status_validasi, 
                   u.nama_lengkap, u.NIK_NIP
            FROM pendaftaran p
            INNER JOIN user u ON p.NIK = u.NIK_NIP
            WHERE 1=1";

    $params = [];

    if (!empty($filter_tahun)) {
        $sql .= " AND YEAR(p.tgl_daftar) = ?";
        $params[] = $filter_tahun;
    }

    if (!empty($filter_status)) {
        $sql .= " AND p.status_validasi = ?";
        $params[] = $filter_status;
    }

    if (!empty($search)) {
        $sql .= " AND u.nama_lengkap LIKE ?";
        $params[] = "%$search%";
    }

    $count_sql = "SELECT COUNT(*) as total FROM ($sql) as subquery";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    $sql .= " ORDER BY p.tgl_daftar DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pendaftaran_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error query pendaftaran: " . $e->getMessage());
    $pendaftaran_list = [];
    $total_pages = 0;
}

// PERUBAHAN: Function untuk badge status - UPDATE warna badge
function getBadgeClass($status)
{
    $badges = [
        'Pengecekan Berkas' => 'scan',
        'Tidak Bisa Difasilitasi' => 'dangerish',
        'Konfirmasi Lanjut' => 'violet',
        'Surat Keterangan Difasilitasi' => 'infoish',
        'Menunggu Bukti Pendaftaran' => 'emerald',
        'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian' => 'yellow',
        'Hasil Verifikasi Kementerian' => 'mint'
    ];
    return $badges[$status] ?? 'secondary';
}

// Function untuk display text status - UPDATE label
function getDisplayStatus($status)
{
    $displayText = [
        'Pengecekan Berkas' => 'BERKAS BARU',
        'Tidak Bisa Difasilitasi' => 'TIDAK BISA DIFASILITASI',
        'Konfirmasi Lanjut' => 'KONFIRMASI LANJUT',
        'Surat Keterangan Difasilitasi' => 'SURAT KETERANGAN DIFASILITASI',
        'Menunggu Bukti Pendaftaran' => 'MENUNGGU BUKTI PENDAFTARAN',
        'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian' => 'BUKTI PENDAFTARAN TERBIT',
        'Hasil Verifikasi Kementerian' => 'HASIL VERIFIKASI KEMENTERIAN'
    ];
    return $displayText[$status] ?? strtoupper($status);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Pendaftaran Merek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <style>
        .badge.text-bg-scan {
            background-color: #6C757D !important;
            color: #fff !important;
        }

        .badge.text-bg-dangerish {
            background-color: #dc3545 !important;
            color: #fff !important;
        }

        .badge.text-bg-violet {
            background-color: #5b21b6 !important;
            color: #fff !important;
        }

        .badge.text-bg-infoish {
            background-color: #0891b2 !important;
            color: #fff !important;
        }

        .badge.text-bg-emerald {
            background-color: #FFA239 !important;
            color: #fff !important;
        }

        .badge.text-bg-yellow {
            background-color: #ca8a04 !important;
            color: #fff !important;
        }

        .badge.text-bg-mint {
            background-color: #059669 !important;
            color: #fff !important;
        }
    </style>
</head>

<body>
    <?php include 'navbar-admin.php' ?>

    <main class="container-xxl main-container">
        <div class="col-12 col-lg-12">
            <h6 class="section-title mb-3">Rangkuman Data Pendaftaran</h6>
            <div class="card border-0 quota-card mb-4">
                <div class="card-body">
                    <p class="fw-semibold mb-2">Kuota Pertahun yang Sudah Digunakan</p>
                    <div class="progress-outer">
                        <div class="progress-inner" style="width: <?php echo ($total_pendaftar / $total_kuota * 100); ?>%;" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $total_pendaftar; ?>"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span>0</span><span>50</span><span>100</span>
                    </div>
                    <div class="small text-muted mt-2"><?php echo $kuota_tersedia; ?> dari <?php echo $total_kuota; ?> kuota masih tersedia</div>
                </div>
            </div>

            <!-- Stat Cards - UPDATE label -->
            <div class="row g-3 mb-4 row-cols-2 row-cols-md-3 row-cols-lg-4">
                <div class="col">
                    <div class="card stat-card bg-scan h-100">
                        <div class="card-body">
                            <div class="stat-title">Berkas Baru</div>
                            <div class="display-6 fw-bold"><?php echo $stats['Pengecekan Berkas']; ?></div>
                            <div class="stat-sub">pendaftar</div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card stat-card bg-dangerish h-100">
                        <div class="card-body">
                            <div class="stat-title">Tidak Bisa Difasilitasi</div>
                            <div class="display-6 fw-bold"><?php echo $stats['Tidak Bisa Difasilitasi']; ?></div>
                            <div class="stat-sub">pendaftar</div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card stat-card bg-violet h-100">
                        <div class="card-body">
                            <div class="stat-title">Konfirmasi Lanjut</div>
                            <div class="display-6 fw-bold"><?php echo $stats['Konfirmasi Lanjut']; ?></div>
                            <div class="stat-sub">pendaftar</div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card stat-card bg-infoish h-100">
                        <div class="card-body">
                            <div class="stat-title">Surat Keterangan Difasilitasi</div>
                            <div class="display-6 fw-bold"><?php echo $stats['Surat Keterangan Difasilitasi']; ?></div>
                            <div class="stat-sub">pendaftar</div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card stat-card bg-emerald h-100">
                        <div class="card-body">
                            <div class="stat-title">Menunggu Bukti Pendaftaran</div>
                            <div class="display-6 fw-bold"><?php echo $stats['Menunggu Bukti Pendaftaran']; ?></div>
                            <div class="stat-sub">pendaftar</div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card stat-card bg-yellow h-100">
                        <div class="card-body">
                            <div class="stat-title">Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian</div>
                            <div class="display-6 fw-bold"><?php echo $stats['Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian']; ?></div>
                            <div class="stat-sub">pendaftar</div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card stat-card bg-mint h-100">
                        <div class="card-body">
                            <div class="stat-title">Hasil Verifikasi Kementerian</div>
                            <div class="display-6 fw-bold"><?php echo $stats['Hasil Verifikasi Kementerian']; ?></div>
                            <div class="stat-sub">pendaftar</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kelola Data -->
            <div class="mb-4">
                <h6 class="section-title">Kelola Data Pendaftaran</h6>
                <p class="text-muted small mb-3">
                    Gunakan fitur pencarian untuk menemukan data pendaftaran berdasarkan nama pemilik.
                    Klik icon lihat untuk menampilkan data pendaftaran.
                </p>

                <form method="GET" action="">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-9">
                            <label for="search" class="form-label small fw-semibold">Pencarian</label>
                            <div class="input-group">
                                <span class="input-group-text" id="search-icon"><i class="bi bi-search"></i></span>
                                <input id="search" name="search" type="search" class="form-control" placeholder="Cari data pendaftaran berdasarkan nama pemilik" value="<?php echo htmlspecialchars($search); ?>">
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
                                <option value="Pengecekan Berkas" <?php echo $filter_status == 'Pengecekan Berkas' ? 'selected' : ''; ?>>Berkas Baru</option>
                                <option value="Tidak Bisa Difasilitasi" <?php echo $filter_status == 'Tidak Bisa Difasilitasi' ? 'selected' : ''; ?>>Tidak Bisa Difasilitasi</option>
                                <option value="Konfirmasi Lanjut" <?php echo $filter_status == 'Konfirmasi Lanjut' ? 'selected' : ''; ?>>Konfirmasi Lanjut</option>
                                <option value="Surat Keterangan Difasilitasi" <?php echo $filter_status == 'Surat Keterangan Difasilitasi' ? 'selected' : ''; ?>>Surat Keterangan Difasilitasi</option>
                                <option value="Menunggu Bukti Pendaftaran" <?php echo $filter_status == 'Menunggu Bukti Pendaftaran' ? 'selected' : ''; ?>>Menunggu Bukti Pendaftaran</option>
                                <option value="Bukti Pendaftaran Terbit dan Diajukan ke Kementerian" <?php echo $filter_status == 'Bukti Pendaftaran Terbit dan Diajukan Ke Kementerian' ? 'selected' : ''; ?>>Bukti Pendaftaran Terbit</option>
                                <option value="Hasil Verifikasi Kementerian" <?php echo $filter_status == 'Hasil Verifikasi Kementerian' ? 'selected' : ''; ?>>Hasil Verifikasi Kementerian</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="d-block form-label small opacity-0">Reset</label>
                            <a href="dashboard-admin.php" class="btn btn-outline-dark btn-sm">
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
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th class="text-center" style="width:120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (empty($pendaftaran_list)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-2">Tidak ada data pendaftaran</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendaftaran_list as $row): ?>
                                    <tr>
                                        <td><input class="form-check-input row-checkbox" type="checkbox" value="<?php echo $row['id_pendaftaran']; ?>"></td>
                                        <td class="text-nowrap">
                                            <span class="avatar-dot me-2"><?php echo strtoupper(substr($row['nama_lengkap'], 0, 1)); ?></span>
                                            <?php echo strtoupper(htmlspecialchars($row['nama_lengkap'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-<?php echo getBadgeClass($row['status_validasi']); ?>">
                                                <?php echo getDisplayStatus($row['status_validasi']); ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap"><?php echo formatTanggalIndonesia($row['tgl_daftar']); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="detail-pendaftar.php?id=<?php echo $row['id_pendaftaran']; ?>" class="btn btn-primary btn-icon">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button class="btn btn-danger btn-icon" onclick="confirmDelete(<?php echo $row['id_pendaftaran']; ?>)">
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&tahun=<?php echo $filter_tahun; ?>&status=<?php echo $filter_status; ?>&search=<?php echo $search; ?>">
                                    <span>&laquo;</span>
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&tahun=<?php echo $filter_tahun; ?>&status=<?php echo $filter_status; ?>&search=<?php echo $search; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&tahun=<?php echo $filter_tahun; ?>&status=<?php echo $filter_status; ?>&search=<?php echo $search; ?>">
                                    <span>&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <!-- Button Download -->
                    <a href="process/export_data.php" class="btn btn-dark btn-sm">
                        <i class="bi bi-download me-2"></i>Download Rekap Data Pendaftaran
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
        function confirmDelete(id) {
            const confirmModal = `
            <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-body text-center p-4">
                            <div class="fs-1 mb-3">⚠️</div>
                            <h5 class="mb-3">Konfirmasi Hapus</h5>
                            <p class="mb-0">Apakah Anda yakin ingin menghapus data pendaftaran ini?</p>
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
                window.location.href = 'process/delete_pendaftaran.php?id=' + id;
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