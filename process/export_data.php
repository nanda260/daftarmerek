<?php
session_start();
require_once 'config_db.php';

// Cek login admin
if (!isset($_SESSION['NIK_NIP']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login.php");
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

try {
    // Query untuk mengambil semua data pendaftaran
    $sql = "SELECT 
                p.id_pendaftaran,
                p.NIK,
                u.nama_lengkap,
                u.email,
                u.no_wa,
                u.kel_desa as user_kel_desa,
                u.kecamatan as user_kecamatan,
                u.rt_rw as user_rt_rw,
                du.nama_usaha,
                du.kel_desa as usaha_kel_desa,
                du.kecamatan as usaha_kecamatan,
                du.rt_rw as usaha_rt_rw,
                du.no_telp_perusahaan,
                du.hasil_produk,
                du.jml_tenaga_kerja,
                du.kapasitas_produk,
                du.omset_perbulan,
                du.wilayah_pemasaran,
                du.legalitas,
                m.kelas_merek,
                m.nama_merek1,
                m.nama_merek2,
                m.nama_merek3,
                p.merek_difasilitasi,
                CASE 
                    WHEN p.merek_difasilitasi = 1 THEN m.nama_merek1
                    WHEN p.merek_difasilitasi = 2 THEN m.nama_merek2
                    WHEN p.merek_difasilitasi = 3 THEN m.nama_merek3
                    ELSE '-'
                END as merek_terpilih,
                p.status_validasi,
                p.alasan_tidak_difasilitasi,
                p.alasan_konfirmasi,
                p.tgl_daftar,
                u.tanggal_buat
            FROM pendaftaran p
            INNER JOIN user u ON p.NIK = u.NIK_NIP
            LEFT JOIN datausaha du ON p.id_usaha = du.id_usaha
            LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
            ORDER BY p.tgl_daftar DESC";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Nama file
    $filename = 'Rekap_Pendaftaran_Merek_' . date('Ymd_His') . '.xls';
    
    // Set header untuk download Excel (HTML Table format yang compatible dengan Excel)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Mulai output HTML untuk Excel
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    echo '<!--[if gte mso 9]>';
    echo '<xml>';
    echo '<x:ExcelWorkbook>';
    echo '<x:ExcelWorksheets>';
    echo '<x:ExcelWorksheet>';
    echo '<x:Name>Data Pendaftaran Merek</x:Name>';
    echo '<x:WorksheetOptions>';
    echo '<x:Print>';
    echo '<x:ValidPrinterInfo/>';
    echo '</x:Print>';
    echo '</x:WorksheetOptions>';
    echo '</x:ExcelWorksheet>';
    echo '</x:ExcelWorksheets>';
    echo '</x:ExcelWorkbook>';
    echo '</xml>';
    echo '<![endif]-->';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #000; padding: 8px; text-align: left; }';
    echo 'th { background-color: #4472C4; color: white; font-weight: bold; text-align: center; }';
    echo '.header { font-size: 16pt; font-weight: bold; text-align: center; padding: 10px; }';
    echo '.subheader { font-size: 12pt; font-weight: bold; text-align: center; padding: 5px; }';
    echo '.info { text-align: center; padding: 5px; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Header
    echo '<table>';
    echo '<tr><td colspan="27" class="header">REKAP DATA PENDAFTARAN MEREK</td></tr>';
    echo '<tr><td colspan="27" class="subheader">DINAS PERINDUSTRIAN DAN PERDAGANGAN KABUPATEN SIDOARJO</td></tr>';
    echo '<tr><td colspan="27" class="info">Tanggal Export: ' . date('d/m/Y H:i:s') . '</td></tr>';
    echo '<tr><td colspan="27"></td></tr>'; // Empty row
    
    // Header kolom
    echo '<tr>';
    echo '<th style="width: 40px;">No</th>';
    echo '<th style="width: 80px;">ID Pendaftaran</th>';
    echo '<th style="width: 120px;">NIK</th>';
    echo '<th style="width: 150px;">Nama Lengkap</th>';
    echo '<th style="width: 150px;">Email</th>';
    echo '<th style="width: 100px;">No. WhatsApp</th>';
    echo '<th style="width: 200px;">Alamat Pemohon</th>';
    echo '<th style="width: 150px;">Nama Usaha</th>';
    echo '<th style="width: 200px;">Alamat Usaha</th>';
    echo '<th style="width: 100px;">No. Telp Perusahaan</th>';
    echo '<th style="width: 150px;">Hasil Produk</th>';
    echo '<th style="width: 80px;">Jumlah Tenaga Kerja</th>';
    echo '<th style="width: 100px;">Kapasitas Produk</th>';
    echo '<th style="width: 100px;">Omset/Bulan</th>';
    echo '<th style="width: 150px;">Wilayah Pemasaran</th>';
    echo '<th style="width: 150px;">Legalitas</th>';
    echo '<th style="width: 100px;">Kelas Merek</th>';
    echo '<th style="width: 150px;">Merek Alternatif 1</th>';
    echo '<th style="width: 150px;">Merek Alternatif 2</th>';
    echo '<th style="width: 150px;">Merek Alternatif 3</th>';
    echo '<th style="width: 100px;">Merek Difasilitasi</th>';
    echo '<th style="width: 150px;">Merek Terpilih</th>';
    echo '<th style="width: 150px;">Status Validasi</th>';
    echo '<th style="width: 250px;">Alasan Tidak Difasilitasi</th>';
    echo '<th style="width: 250px;">Alasan Konfirmasi</th>';
    echo '<th style="width: 120px;">Tanggal Daftar Merek</th>';
    echo '<th style="width: 120px;">Tanggal Buat Akun</th>';
    echo '</tr>';
    
    // Data
    $no = 1;
    foreach ($data as $item) {
        // Gabungkan alamat pemohon
        $alamat_pemohon = trim(
            ($item['user_rt_rw'] ? 'RT/RW ' . $item['user_rt_rw'] . ', ' : '') .
            ($item['user_kel_desa'] ? 'Kel/Desa ' . $item['user_kel_desa'] . ', ' : '') .
            ($item['user_kecamatan'] ? 'Kec. ' . $item['user_kecamatan'] : '')
        );
        
        // Gabungkan alamat usaha
        $alamat_usaha = trim(
            ($item['usaha_rt_rw'] ? 'RT/RW ' . $item['usaha_rt_rw'] . ', ' : '') .
            ($item['usaha_kel_desa'] ? 'Kel/Desa ' . $item['usaha_kel_desa'] . ', ' : '') .
            ($item['usaha_kecamatan'] ? 'Kec. ' . $item['usaha_kecamatan'] : '')
        );
        
        // Merek yang difasilitasi
        $merek_difasilitasi = '';
        if ($item['merek_difasilitasi'] == 1) {
            $merek_difasilitasi = 'Merek 1';
        } elseif ($item['merek_difasilitasi'] == 2) {
            $merek_difasilitasi = 'Merek 2';
        } elseif ($item['merek_difasilitasi'] == 3) {
            $merek_difasilitasi = 'Merek 3';
        } else {
            $merek_difasilitasi = '-';
        }
        
        echo '<tr>';
        echo '<td>' . $no . '</td>';
        echo '<td>' . htmlspecialchars($item['id_pendaftaran']) . '</td>';
        echo '<td style="mso-number-format:\@;">' . htmlspecialchars($item['NIK']) . '</td>'; // Format as text
        echo '<td>' . htmlspecialchars(strtoupper($item['nama_lengkap'])) . '</td>';
        echo '<td>' . htmlspecialchars($item['email']) . '</td>';
        echo '<td style="mso-number-format:\@;">' . htmlspecialchars($item['no_wa']) . '</td>';
        echo '<td>' . htmlspecialchars($alamat_pemohon) . '</td>';
        echo '<td>' . htmlspecialchars($item['nama_usaha']) . '</td>';
        echo '<td>' . htmlspecialchars($alamat_usaha) . '</td>';
        echo '<td style="mso-number-format:\@;">' . htmlspecialchars($item['no_telp_perusahaan']) . '</td>';
        echo '<td>' . htmlspecialchars($item['hasil_produk']) . '</td>';
        echo '<td>' . htmlspecialchars($item['jml_tenaga_kerja']) . '</td>';
        echo '<td>' . htmlspecialchars($item['kapasitas_produk']) . '</td>';
        echo '<td>' . htmlspecialchars($item['omset_perbulan']) . '</td>';
        echo '<td>' . htmlspecialchars($item['wilayah_pemasaran']) . '</td>';
        echo '<td>' . htmlspecialchars($item['legalitas']) . '</td>';
        echo '<td>' . htmlspecialchars($item['kelas_merek']) . '</td>';
        echo '<td>' . htmlspecialchars($item['nama_merek1']) . '</td>';
        echo '<td>' . htmlspecialchars($item['nama_merek2']) . '</td>';
        echo '<td>' . htmlspecialchars($item['nama_merek3']) . '</td>';
        echo '<td>' . htmlspecialchars($merek_difasilitasi) . '</td>';
        echo '<td>' . htmlspecialchars($item['merek_terpilih']) . '</td>';
        echo '<td>' . htmlspecialchars($item['status_validasi']) . '</td>';
        echo '<td>' . htmlspecialchars($item['alasan_tidak_difasilitasi'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($item['alasan_konfirmasi'] ?? '-') . '</td>';
        echo '<td>' . ($item['tgl_daftar'] ? date('d/m/Y H:i', strtotime($item['tgl_daftar'])) : '-') . '</td>';
        echo '<td>' . ($item['tanggal_buat'] ? date('d/m/Y H:i', strtotime($item['tanggal_buat'])) : '-') . '</td>';
        echo '</tr>';
        
        $no++;
    }
    
    // Total
    echo '<tr>';
    echo '<td colspan="6" style="font-weight: bold;">TOTAL DATA: ' . ($no - 1) . ' PENDAFTARAN</td>';
    echo '<td colspan="21"></td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    
    exit;
    
} catch (PDOException $e) {
    error_log("Error export data: " . $e->getMessage());
    die("Terjadi kesalahan saat mengekspor data: " . $e->getMessage());
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    die("Terjadi kesalahan: " . $e->getMessage());
}
?>