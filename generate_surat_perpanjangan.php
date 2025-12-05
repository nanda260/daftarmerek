<?php
error_log("=== GENERATOR SCRIPT STARTED ===");

// Cek apakah dipanggil langsung atau dari include
$is_direct_call = !defined('GENERATE_FROM_PERPANJANGAN');
error_log("Is direct call: " . ($is_direct_call ? 'YES' : 'NO'));

if ($is_direct_call) {
    session_start();
    require_once __DIR__ . '/vendor/autoload.php';
    date_default_timezone_set('Asia/Jakarta');
    include 'process/config_db.php';

    if (!isset($_SESSION['NIK_NIP'])) {
        header("Location: login.php");
        exit();
    }

    $NIK = $_SESSION['NIK_NIP'];
    $id_perpanjangan = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$id_perpanjangan) {
        die("ID Perpanjangan tidak valid");
    }
} else {
    // Dipanggil dari perpanjangan.php (included)
    $id_perpanjangan = $GLOBALS['id_perpanjangan_global'] ?? 0;
    $pdo = $GLOBALS['pdo_global'] ?? $pdo;
    $NIK = $GLOBALS['NIK_global'] ?? $NIK;

    error_log("ID Perpanjangan: " . $id_perpanjangan);
    error_log("NIK: " . $NIK);
}

if (!$id_perpanjangan || !$NIK) {
    throw new Exception("Missing required variables: id_perpanjangan or NIK");
}

try {
    // Suppress any accidental output
    ob_start();
    
    error_log("Fetching perpanjangan data...");

    // Ambil data perpanjangan
    $stmt = $pdo->prepare("
        SELECT perp.*, 
               p.merek_difasilitasi,
               u.nama_usaha, u.rt_rw AS usaha_rt_rw, u.kel_desa, u.kecamatan, u.no_telp_perusahaan,
               u.hasil_produk, u.jml_tenaga_kerja,
               m.nama_merek1, m.nama_merek2, m.nama_merek3,
               usr.nama_lengkap, usr.kel_desa AS user_kel_desa, usr.kecamatan AS user_kecamatan, 
               usr.rt_rw AS user_rt_rw, usr.no_wa, usr.email,
               usr.nama_kabupaten, usr.nama_provinsi
        FROM perpanjangan perp
        LEFT JOIN pendaftaran p ON perp.id_pendaftaran_lama = p.id_pendaftaran
        LEFT JOIN datausaha u ON perp.id_usaha = u.id_usaha
        LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
        LEFT JOIN user usr ON perp.NIK = usr.NIK_NIP
        WHERE perp.id_perpanjangan = :id_perpanjangan AND perp.NIK = :nik
    ");
    $stmt->execute(['id_perpanjangan' => $id_perpanjangan, 'nik' => $NIK]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception("Data tidak ditemukan atau Anda tidak memiliki akses");
    }

    error_log("✅ Data perpanjangan ditemukan");

    // Ambil tanda tangan dari tabel perpanjangan
    $ttd_path = null;
    if (isset($data['file_ttd']) && !empty($data['file_ttd'])) {
        $ttd_path = $data['file_ttd'];
        error_log("TTD Path: " . $ttd_path);
        error_log("TTD Exists: " . (file_exists($ttd_path) ? 'YES' : 'NO'));
    }

    // Buat alamat lengkap usaha
    $alamat_usaha = $data['kel_desa'] . ', RT/RW ' . $data['usaha_rt_rw'] . ', ' .
        $data['kecamatan'] . ', Kabupaten Sidoarjo';

    // Buat alamat lengkap pemilik
    $alamat_pemilik = $data['user_kel_desa'] . ', RT/RW ' . $data['user_rt_rw'] . ', ' .
        $data['user_kecamatan'] . ', ' . $data['nama_kabupaten'] . ', ' . $data['nama_provinsi'];

    // Tentukan merek yang difasilitasi
    if ($data['merek_difasilitasi'] == 1) {
        $merek_difasilitasi = $data['nama_merek1'];
    } elseif ($data['merek_difasilitasi'] == 2) {
        $merek_difasilitasi = $data['nama_merek2'];
    } elseif ($data['merek_difasilitasi'] == 3) {
        $merek_difasilitasi = $data['nama_merek3'];
    } else {
        $merek_difasilitasi = $data['nama_merek1'];
    }

    // Format tanggal
    $bulan_indonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $tanggal = date('d');
    $bulan = $bulan_indonesia[(int)date('m')];
    $tahun = date('Y');
    $tanggal_surat = "Sidoarjo, $tanggal $bulan $tahun";

    error_log("Creating PDF...");

    // ===== BUAT PDF =====
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo');
    $pdf->SetAuthor($data['nama_lengkap']);
    $pdf->SetTitle('Surat Permohonan Perpanjangan Merek');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(TRUE, 20);
    $pdf->AddPage();
    $pdf->SetFont('times', '', 12);

    // KOP Surat
    $pdf->SetFont('times', 'B', 14);
    $pdf->Cell(0, 7, strtoupper($data['nama_usaha']), 0, 1, 'C');
    $pdf->SetFont('times', '', 10);
    $pdf->Cell(0, 5, $alamat_usaha, 0, 1, 'C');
    $pdf->Cell(0, 5, 'Telp: ' . $data['no_telp_perusahaan'], 0, 1, 'C');

    // Garis pembatas
    $pdf->Ln(2);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->SetLineWidth(0.2);
    $pdf->Ln(1);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(8);

    // Tanggal dan Tujuan
    $pdf->SetFont('times', '', 12);
    $pdf->Cell(0, 6, $tanggal_surat, 0, 1, 'R');
    $pdf->Ln(4);

    $pdf->Cell(40, 6, 'Kepada', 0, 0);
    $pdf->Cell(5, 6, ':', 0, 1);
    $pdf->Cell(40, 6, 'Yth. Kepala Dinas Perindustrian dan Perdagangan', 0, 1);
    $pdf->Cell(40, 6, 'Kabupaten Sidoarjo', 0, 1);
    $pdf->Cell(40, 6, 'Di', 0, 1);
    $pdf->Cell(40, 6, 'Tempat', 0, 1);
    $pdf->Ln(4);

    // Salam Pembuka
    $pdf->SetFont('times', 'B', 12);
    $pdf->Cell(0, 6, 'Dengan hormat,', 0, 1);
    $pdf->Ln(2);

    // Isi Surat
    $pdf->SetFont('times', '', 12);
    $pdf->MultiCell(0, 6, 'Yang bertanda tangan di bawah ini:', 0, 'L');
    $pdf->Ln(2);

    // Data dalam tabel
    $col1 = 50; $col2 = 5; $col3 = 115;
    $pdf->SetFont('times', '', 11);

    $pdf->Cell($col1, 6, '1. Nama Usaha', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->Cell($col3, 6, $data['nama_usaha'], 0, 1);

    $pdf->Cell($col1, 6, '2. Alamat Usaha', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->MultiCell($col3, 6, $alamat_usaha, 0, 'L');

    $pdf->Cell($col1, 6, '3. No. Telp Perusahaan', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->Cell($col3, 6, $data['no_telp_perusahaan'], 0, 1);

    $pdf->Cell($col1, 6, '4. Nama Pemilik', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->Cell($col3, 6, $data['nama_lengkap'], 0, 1);

    $pdf->Cell($col1, 6, '5. Alamat Pemilik', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->MultiCell($col3, 6, $alamat_pemilik, 0, 'L');

    $pdf->Cell($col1, 6, '6. No. Telp Pemilik', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->Cell($col3, 6, $data['no_wa'], 0, 1);

    $pdf->Cell($col1, 6, '7. E-mail', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->Cell($col3, 6, $data['email'], 0, 1);

    $pdf->Cell($col1, 6, '8. Jenis Usaha', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->Cell($col3, 6, 'Industri Kecil', 0, 1);

    $pdf->Cell($col1, 6, '9. Produk', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->Cell($col3, 6, $data['hasil_produk'], 0, 1);

    $pdf->Cell($col1, 6, '10. Jumlah tenaga kerja', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->Cell($col3, 6, $data['jml_tenaga_kerja'] . ' orang', 0, 1);

    $pdf->Cell($col1, 6, '11. Merek', 0, 0);
    $pdf->Cell($col2, 6, ':', 0, 0);
    $pdf->Cell($col3, 6, $merek_difasilitasi, 0, 1);

    $pdf->Ln(4);

    // Permohonan
    $pdf->SetFont('times', '', 12);
    $permohonan_text = 'Mengajukan permohonan Surat Keterangan Industri Kecil dan Menengah (IKM) untuk keperluan PERPANJANGAN pengurusan merek "' . $merek_difasilitasi . '" di Kementerian Hukum dan HAM.';
    $pdf->MultiCell(0, 6, $permohonan_text, 0, 'J');
    $pdf->Ln(2);

    $pdf->MultiCell(0, 6, 'Demikian untuk menjadikan maklum. Atas perhatian dan bantuannya disampaikan terima kasih.', 0, 'J');
    $pdf->Ln(6);

    // Lampiran
    $pdf->SetFont('times', 'B', 12);
    $pdf->Cell(0, 6, 'Lampiran:', 0, 1);
    $pdf->SetFont('times', '', 11);
    $pdf->Cell(10, 6, '1.', 0, 0);
    $pdf->Cell(0, 6, 'Sertifikat Merek yang akan diperpanjang', 0, 1);
    $pdf->Cell(10, 6, '2.', 0, 0);
    $pdf->Cell(0, 6, 'Izin usaha (NIB/Surat Izin Usaha)', 0, 1);
    $pdf->Cell(10, 6, '3.', 0, 0);
    $pdf->Cell(0, 6, 'Foto lokasi dan tempat produksi', 0, 1);
    $pdf->Ln(6);

    // Penutup dan Tanda Tangan
    $pdf->SetFont('times', '', 12);
    $pdf->Cell(100, 6, '', 0, 0);
    $pdf->Cell(0, 6, 'Hormat kami,', 0, 1, 'L');
    $pdf->Cell(100, 6, '', 0, 0);
    $pdf->Cell(0, 6, 'Pemilik', 0, 1, 'L');
    $pdf->Ln(2);

    // Tanda Tangan Digital
    if ($ttd_path && file_exists($ttd_path)) {
        $pdf->Cell(100, 6, '', 0, 0);
        $pdf->Image($ttd_path, $pdf->GetX() + 105, $pdf->GetY(), 40, 20);
        $pdf->Ln(22);
    } else {
        $pdf->Ln(20);
    }

    $pdf->Cell(100, 6, '', 0, 0);
    $pdf->SetFont('times', 'BU', 12);
    $pdf->Cell(0, 6, $data['nama_lengkap'], 0, 1, 'L');

    // ===== SIMPAN PDF KE FILE =====
    $folder_surat = "uploads/surat_perpanjangan/surat_{$NIK}/";

    if (!file_exists($folder_surat)) {
        if (!mkdir($folder_surat, 0777, true)) {
            throw new Exception("Gagal membuat folder: " . $folder_surat);
        }
    }

    $filename_surat = "surat_perpanjangan_{$NIK}_" . time() . ".pdf";
    $filepath_surat = $folder_surat . $filename_surat;

    $pdf->Output($filepath_surat, 'F');

    if (!file_exists($filepath_surat)) {
        throw new Exception("PDF tidak berhasil disimpan ke: " . $filepath_surat);
    }

    $filesize = filesize($filepath_surat);
    error_log("✅ PDF tersimpan di: " . $filepath_surat . " (Size: " . $filesize . " bytes)");

    if ($filesize < 1000) {
        throw new Exception("File PDF terlalu kecil: " . $filesize . " bytes");
    }

    // ===== SIMPAN KE TABEL LAMPIRAN (id_jenis_file = 17) =====
    $tgl_upload_surat = date('Y-m-d H:i:s');

    // ✅ Hapus surat lama jika ada (berdasarkan id_perpanjangan)
    $stmt = $pdo->prepare("DELETE FROM lampiran WHERE id_pendaftaran = ? AND id_jenis_file = 17");
    $stmt->execute([$id_perpanjangan]);

    // ✅ Insert surat baru dengan id_perpanjangan POSITIF
    $stmt = $pdo->prepare("
        INSERT INTO lampiran (id_pendaftaran, id_jenis_file, tgl_upload, file_path) 
        VALUES (?, 17, ?, ?)
    ");
    $result_surat = $stmt->execute([
        $id_perpanjangan,  // ✅ Gunakan positif
        $tgl_upload_surat,
        $filepath_surat
    ]);

    if (!$result_surat) {
        throw new Exception('Gagal menyimpan surat ke lampiran');
    }

    $id_lampiran_baru = $pdo->lastInsertId();
    error_log("✅ Surat disimpan ke lampiran ID: " . $id_lampiran_baru);
    error_log("✅ id_pendaftaran (id_perpanjangan): " . $id_perpanjangan);
    error_log("✅ id_jenis_file: 17");
    error_log("✅ file_path: " . $filepath_surat);

    // Set global variable untuk digunakan di perpanjangan.php
    $GLOBALS['filepath_surat_perpanjangan'] = $filepath_surat;

    // Jika direct call, output PDF untuk download
    if ($is_direct_call) {
        $pdf->Output($filename_surat, 'I');
        exit();
    }

    error_log("=== PDF GENERATION COMPLETED SUCCESSFULLY ===");

} catch (Exception $e) {
    error_log("❌ ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    if ($is_direct_call) {
        die("Error: " . $e->getMessage());
    } else {
        throw $e;
    }
}