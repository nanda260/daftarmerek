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
    // Query untuk mengambil semua data pengajuan surat keterangan
    $sql = "SELECT 
                ps.id_pengajuan,
                ps.NIK,
                ps.tgl_daftar,
                ps.status_validasi,
                ps.tipe_pengajuan,
                ps.nama_usaha,
                ps.alamat_usaha,
                ps.no_telp_usaha,
                ps.nama_pemilik,
                ps.alamat_pemilik,
                ps.no_telp_pemilik,
                ps.email,
                ps.jenis_usaha,
                ps.produk,
                ps.jml_tenaga_kerja,
                ps.merek,
                ps.kelas_merek,
                ps.logo_merek,
                ps.nib_file,
                ps.foto_produk,
                ps.foto_proses,
                ps.akta_file,
                ps.suratpermohonan_file,
                ps.file_surat_keterangan,
                ps.tgl_update,
                ps.id_pendaftaran,
                u.nama_lengkap,
                u.email as email_user,
                u.no_wa,
                u.kel_desa,
                u.kecamatan,
                u.rt_rw,
                u.tanggal_buat,
                p.id_pendaftaran as ref_pendaftaran_id
            FROM pengajuansurat ps
            INNER JOIN user u ON ps.NIK = u.NIK_NIP
            LEFT JOIN pendaftaran p ON ps.id_pendaftaran = p.id_pendaftaran
            ORDER BY ps.tgl_daftar DESC";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Nama file
    $filename = 'Rekap_Pengajuan_Surat_Keterangan_' . date('Ymd_His') . '.xls';
    
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
    echo '<x:Name>Data Pengajuan Surat</x:Name>';
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
    echo '<tr><td colspan="29" class="header">REKAP DATA PENGAJUAN SURAT KETERANGAN IKM</td></tr>';
    echo '<tr><td colspan="29" class="subheader">DINAS PERINDUSTRIAN DAN PERDAGANGAN KABUPATEN SIDOARJO</td></tr>';
    echo '<tr><td colspan="29" class="info">Tanggal Export: ' . date('d/m/Y H:i:s') . '</td></tr>';
    echo '<tr><td colspan="29"></td></tr>'; // Empty row
    
    // Header kolom
    echo '<tr>';
    echo '<th style="width: 40px;">No</th>';
    echo '<th style="width: 100px;">ID Pengajuan</th>';
    echo '<th style="width: 120px;">NIK</th>';
    echo '<th style="width: 150px;">Nama Lengkap (User)</th>';
    echo '<th style="width: 150px;">Email User</th>';
    echo '<th style="width: 100px;">No. WhatsApp</th>';
    echo '<th style="width: 200px;">Alamat User</th>';
    echo '<th style="width: 100px;">Tipe Pengajuan</th>';
    echo '<th style="width: 100px;">ID Pendaftaran Ref</th>';
    echo '<th style="width: 150px;">Nama Pemilik</th>';
    echo '<th style="width: 200px;">Alamat Pemilik</th>';
    echo '<th style="width: 100px;">No. Telp Pemilik</th>';
    echo '<th style="width: 150px;">Email Pemilik</th>';
    echo '<th style="width: 150px;">Nama Usaha</th>';
    echo '<th style="width: 200px;">Alamat Usaha</th>';
    echo '<th style="width: 100px;">No. Telp Usaha</th>';
    echo '<th style="width: 150px;">Jenis Usaha</th>';
    echo '<th style="width: 150px;">Produk</th>';
    echo '<th style="width: 80px;">Jumlah Tenaga Kerja</th>';
    echo '<th style="width: 150px;">Merek</th>';
    echo '<th style="width: 80px;">Kelas Merek</th>';
    echo '<th style="width: 200px;">Logo Merek</th>';
    echo '<th style="width: 200px;">File NIB</th>';
    echo '<th style="width: 200px;">Akta Pendirian</th>';
    echo '<th style="width: 200px;">Surat Permohonan</th>';
    echo '<th style="width: 150px;">Status Validasi</th>';
    echo '<th style="width: 200px;">File Surat Keterangan</th>';
    echo '<th style="width: 120px;">Tanggal Pengajuan</th>';
    echo '<th style="width: 120px;">Tanggal Update</th>';
    echo '</tr>';
    
    // Data
    $no = 1;
    foreach ($data as $item) {
        // Gabungkan alamat user
        $alamat_user = trim(
            ($item['rt_rw'] ? 'RT/RW ' . $item['rt_rw'] . ', ' : '') .
            ($item['kel_desa'] ? 'Kel/Desa ' . $item['kel_desa'] . ', ' : '') .
            ($item['kecamatan'] ? 'Kec. ' . $item['kecamatan'] : '')
        );
        
        // Status validasi display
        $status_display = [
            'Menunggu Surat Terbit' => 'MENUNGGU SURAT TERBIT',
            'Surat Keterangan Terbit' => 'SURAT KETERANGAN TERBIT'
        ];
        $status_text = isset($status_display[$item['status_validasi']]) 
            ? $status_display[$item['status_validasi']] 
            : strtoupper($item['status_validasi']);
        
        // Tipe pengajuan display
        $tipe_display = [
            'mandiri' => 'MANDIRI',
            'perpanjangan' => 'PERPANJANGAN'
        ];
        $tipe_text = isset($tipe_display[strtolower($item['tipe_pengajuan'])]) 
            ? $tipe_display[strtolower($item['tipe_pengajuan'])] 
            : strtoupper($item['tipe_pengajuan']);
        
        // Parse JSON untuk foto produk (ambil file pertama saja untuk display)
        $foto_produk_display = '-';
        if (!empty($item['foto_produk'])) {
            $foto_produk_array = json_decode($item['foto_produk'], true);
            if (is_array($foto_produk_array) && count($foto_produk_array) > 0) {
                $foto_produk_display = basename($foto_produk_array[0]) . ' (+' . (count($foto_produk_array) - 1) . ' lainnya)';
            }
        }
        
        // Parse JSON untuk foto proses (ambil file pertama saja untuk display)
        $foto_proses_display = '-';
        if (!empty($item['foto_proses'])) {
            $foto_proses_array = json_decode($item['foto_proses'], true);
            if (is_array($foto_proses_array) && count($foto_proses_array) > 0) {
                $foto_proses_display = basename($foto_proses_array[0]) . ' (+' . (count($foto_proses_array) - 1) . ' lainnya)';
            }
        }
        
        // NIB file display
        $nib_display = '-';
        if (!empty($item['nib_file'])) {
            $nib_array = json_decode($item['nib_file'], true);
            if (is_array($nib_array) && count($nib_array) > 0) {
                $nib_display = basename($nib_array[0]);
            } else {
                $nib_display = basename($item['nib_file']);
            }
        }
        
        echo '<tr>';
        echo '<td>' . $no . '</td>';
        echo '<td>' . htmlspecialchars($item['id_pengajuan']) . '</td>';
        echo '<td style="mso-number-format:\@;">' . htmlspecialchars($item['NIK']) . '</td>';
        echo '<td>' . htmlspecialchars(strtoupper($item['nama_lengkap'])) . '</td>';
        echo '<td>' . htmlspecialchars($item['email_user']) . '</td>';
        echo '<td style="mso-number-format:\@;">' . htmlspecialchars($item['no_wa'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($alamat_user) . '</td>';
        echo '<td>' . htmlspecialchars($tipe_text) . '</td>';
        echo '<td>' . htmlspecialchars($item['id_pendaftaran'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars(strtoupper($item['nama_pemilik'])) . '</td>';
        echo '<td>' . htmlspecialchars($item['alamat_pemilik'] ?? '-') . '</td>';
        echo '<td style="mso-number-format:\@;">' . htmlspecialchars($item['no_telp_pemilik'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($item['email'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($item['nama_usaha']) . '</td>';
        echo '<td>' . htmlspecialchars($item['alamat_usaha'] ?? '-') . '</td>';
        echo '<td style="mso-number-format:\@;">' . htmlspecialchars($item['no_telp_usaha'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($item['jenis_usaha'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($item['produk'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($item['jml_tenaga_kerja'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($item['merek']) . '</td>';
        echo '<td>' . htmlspecialchars($item['kelas_merek'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($item['logo_merek'] ? basename($item['logo_merek']) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($nib_display) . '</td>';
        echo '<td>' . htmlspecialchars($item['akta_file'] ? basename($item['akta_file']) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($item['suratpermohonan_file'] ? basename($item['suratpermohonan_file']) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($status_text) . '</td>';
        echo '<td>' . htmlspecialchars($item['file_surat_keterangan'] ? basename($item['file_surat_keterangan']) : '-') . '</td>';
        echo '<td>' . ($item['tgl_daftar'] ? date('d/m/Y H:i', strtotime($item['tgl_daftar'])) : '-') . '</td>';
        echo '<td>' . ($item['tgl_update'] ? date('d/m/Y H:i', strtotime($item['tgl_update'])) : '-') . '</td>';
        echo '</tr>';
        
        $no++;
    }
    
    // Total
    echo '<tr>';
    echo '<td colspan="8" style="font-weight: bold;">TOTAL DATA: ' . ($no - 1) . ' PENGAJUAN</td>';
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