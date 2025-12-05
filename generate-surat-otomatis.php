<?php
session_start();
require_once 'process/config_db.php';
require_once __DIR__ . '/vendor/autoload.php';

// Cek login
if (!isset($_SESSION['NIK_NIP'])) {
    header("Location: login.php");
    exit;
}

$NIK = $_SESSION['NIK_NIP'];

// Ambil id_pendaftaran dari parameter
$id_pendaftaran = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id_pendaftaran) {
    die("ID Pendaftaran tidak valid");
}

try {
    // Ambil data lengkap pemohon dan pendaftaran
    $stmt = $pdo->prepare("
        SELECT 
            p.id_pendaftaran,
            p.tgl_daftar,
            u.NIK_NIP,
            u.nama_lengkap,
            u.nama_provinsi,
            u.nama_kabupaten,
            u.kel_desa,
            u.kecamatan,
            u.rt_rw,
            du.nama_usaha,
            du.kel_desa as kel_desa_usaha,
            du.kecamatan as kecamatan_usaha,
            du.rt_rw as rt_rw_usaha,
            du.jenis_pemohon,
            m.nama_merek1,
            m.nama_merek2,
            m.nama_merek3,
            m.kelas_merek,
            m.logo1,
            m.logo2,
            m.logo3,
            p.merek_difasilitasi
        FROM pendaftaran p
        JOIN user u ON p.NIK = u.NIK_NIP
        JOIN datausaha du ON p.id_usaha = du.id_usaha
        LEFT JOIN merek m ON p.id_pendaftaran = m.id_pendaftaran
        WHERE p.id_pendaftaran = ? AND p.NIK = ?
    ");
    $stmt->execute([$id_pendaftaran, $NIK]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Data tidak ditemukan atau akses ditolak");
    }

    // Tentukan merek yang difasilitasi
    $merek_terpilih = '';
    $logo_terpilih = '';

    if ($data['merek_difasilitasi'] == 1) {
        $merek_terpilih = $data['nama_merek1'];
        $logo_terpilih = $data['logo1'];
    } elseif ($data['merek_difasilitasi'] == 2) {
        $merek_terpilih = $data['nama_merek2'];
        $logo_terpilih = $data['logo2'];
    } elseif ($data['merek_difasilitasi'] == 3) {
        $merek_terpilih = $data['nama_merek3'];
        $logo_terpilih = $data['logo3'];
    }

    // Format tanggal Indonesia
    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    $tgl = date('d', strtotime($data['tgl_daftar']));
    $bln = $bulan[(int)date('m', strtotime($data['tgl_daftar']))];
    $thn = date('Y', strtotime($data['tgl_daftar']));
    $tanggal_surat = "$tgl $bln $thn";

    // Jika jenis pemohon perusahaan, ambil data perusahaan dari tabel pengajuansurat
    $data_perusahaan = null;
    if ($data['jenis_pemohon'] === 'perusahaan') {
        $stmt_perusahaan = $pdo->prepare("
           SELECT alamat_perusahaan, nama_perusahaan
           FROM pengajuansurat
           WHERE NIK = ? AND nama_usaha = ?
           ORDER BY tgl_daftar DESC
           LIMIT 1
       ");
        $stmt_perusahaan->execute([$NIK, $data['nama_usaha']]);
        $data_perusahaan = $stmt_perusahaan->fetch(PDO::FETCH_ASSOC);
    }

    // Format alamat
    $alamat_pemohon = "Desa/Kel. " . ($data['kel_desa'] ? $data['kel_desa'] : '-') .
        ", RT/RW " . ($data['rt_rw'] ? $data['rt_rw'] : '-') .
        ", Kec. " . ($data['kecamatan'] ? $data['kecamatan'] : '-') .
        ", " . ($data['nama_kabupaten'] ? strtoupper($data['nama_kabupaten']) : '-') .
        ", " . ($data['nama_provinsi'] ? strtoupper($data['nama_provinsi']) : '-');

    // Alamat usaha berbeda untuk perseorangan dan perusahaan
    if ($data['jenis_pemohon'] === 'perusahaan' && $data_perusahaan) {
       $alamat_usaha = $data_perusahaan['alamat_perusahaan'] ?: 
           "Desa/Kel. " . ($data['kel_desa_usaha'] ? $data['kel_desa_usaha'] : '-') .
           ", RT/RW " . ($data['rt_rw_usaha'] ? $data['rt_rw_usaha'] : '-') .
           ", Kec. " . ($data['kecamatan_usaha'] ? $data['kecamatan_usaha'] : '-') .
           ", KAB. SIDOARJO, JAWA TIMUR";
    } else {
        $alamat_usaha = "Desa/Kel. " . ($data['kel_desa_usaha'] ? $data['kel_desa_usaha'] : '-') .
            ", RT/RW " . ($data['rt_rw_usaha'] ? $data['rt_rw_usaha'] : '-') .
            ", Kec. " . ($data['kecamatan_usaha'] ? $data['kecamatan_usaha'] : '-') .
            ", KAB. SIDOARJO, JAWA TIMUR";
    }

    // Konversi logo ke base64 untuk embed di PDF
    $logo_base64 = '';
    if ($logo_terpilih && file_exists($logo_terpilih)) {
        $image_data = file_get_contents($logo_terpilih);
        $image_type = pathinfo($logo_terpilih, PATHINFO_EXTENSION);
        $logo_base64 = 'data:image/' . $image_type . ';base64,' . base64_encode($image_data);
    }

    // Inisialisasi mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => [215.9, 330.2], // F4/Folio size in mm
        'margin_left' => 25,
        'margin_right' => 25,
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);

    // CSS untuk styling
    $stylesheet = '
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
        }
        .title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 20px 0 15px 0;
            font-size: 13pt;
        }
        .brand-name {
            margin-bottom: 8px;
            text-align: left;
        }
        .logo-box {
    border: 1px solid #000;
    width: 260px;
    height: 260px;
    margin: 8px auto;
    padding: 5%;
    text-align: center;
    display: flex;
    justify-content: center;
    align-items: center;
}

.logo-box img {
    max-width: 250px;
    max-height: 250px;
    width: auto;
    height: auto;
}

        table.info {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
        }
        table.info td {
            vertical-align: top;
            padding: 2px 0;
            line-height: 1.4;
        }
        table.info td:first-child {
            width: 140px;
        }
        table.info td:nth-child(2) {
            width: 15px;
            text-align: center;
        }
        .statement {
            margin: 15px 0;
            text-align: justify;
        }
        .signature-section {
            margin-top: 30px;
            text-align: left;
            margin-left: 60%;
        }
        .signature-content {
            display: inline-block;
            min-width: 200px;
        }
        .signature-placeholder {
            margin: 100px 0 5px 0;
            font-weight: bold;
        }
        .page-break {
            page-break-before: always;
        }
        .intro-text {
            margin: 15px 0;
        }
        
        .company-name {
           margin-top: 8px;
           font-weight: normal;
       }
       .signature-space {
           margin: 90px 0 5px 0;
       }
       .kuasa-name {
           font-weight: bold;
       }
       .kuasa-label {
           margin-top: -5px;
           font-style: italic;
       }
    </style>
    ';

    // Tentukan nama pemohon berdasarkan jenis
    $nama_pemohon_display = $data['jenis_pemohon'] === 'perusahaan'
        ? strtoupper(htmlspecialchars($data['nama_usaha']))
        : strtoupper(htmlspecialchars($data['nama_lengkap']));

    // HALAMAN 1: Surat Pernyataan Permohonan Pendaftaran Merek
    $html1 = $stylesheet . '
    <div>
        <div class="title">SURAT PERNYATAAN PERMOHONAN PENDAFTARAN MEREK</div>
        
        <div class="brand-name" style="margin-left: 30px;">
            Merek: <strong>' . strtoupper(htmlspecialchars($merek_terpilih)) . '</strong>
        </div>
        
        <div class="logo-box">';

    if ($logo_base64) {
        $html1 .= '<img src="' . $logo_base64 . '" alt="Logo Merek">';
    } else {
        $html1 .= '[Logo]';
    }

    $html1 .= '</div>
        
        <div class="intro-text" style="margin-left: 30px;">
            Yang diajukan untuk permohonan pendaftaran merek oleh:
        </div>
        
        <table class="info" style="margin-left: 30px;">
            <tr>
                <td >Nama Pemohon</td>
                <td>:</td>
                <td>' . $nama_pemohon_display . '</td>
            </tr>';
    // Jika perusahaan, tambahkan Nama Kuasa dan Alamat Kuasa
    if ($data['jenis_pemohon'] === 'perusahaan') {
        $html1 .= '
           <tr>
               <td>Nama Kuasa</td>
               <td>:</td>
               <td>' . strtoupper(htmlspecialchars($data['nama_lengkap'])) . '</td>
           </tr>
           <tr>
               <td>Alamat Kuasa</td>
               <td>:</td>
               <td>' . $alamat_pemohon . '</td>
           </tr>';
    } else {
        $html1 .= '
            <tr>
                <td>Alamat Pemohon</td>
                <td>:</td>
                <td>' . $alamat_pemohon . '</td>
            </tr>';
    }
    $html1 .= '
            <tr>
                <td>Alamat Usaha</td>
                <td>:</td>
                <td>' . $alamat_usaha . '</td>
            </tr>
        </table>
        
        <div class="statement">
            Dengan ini menyatakan bahwa merek tersebut merupakan milik pemohon dan tidak meniru merek milik pihak lain.
        </div>
        
        <div class="signature-section">
            <div class="signature-content">
                <div>Sidoarjo, ' . $tanggal_surat . '</div>
               <div style="margin-top: 8px; font-weight: bold;">Pemohon</div>';

    if ($data['jenis_pemohon'] === 'perusahaan') {
        $html1 .= '
               <div class="company-name">' . $nama_pemohon_display . '</div>
               <div class="signature-space kuasa-name">(' . strtoupper(htmlspecialchars($data['nama_lengkap'])) . ')</div>
               <div class="kuasa-label">Kuasa</div>';
    } else {
        $html1 .= '
               <div class="signature-placeholder">(' . strtoupper(htmlspecialchars($data['nama_lengkap'])) . ')</div>';
    }

    $html1 .= '
                </div>
        </div>
    </div>
    ';

    // HALAMAN 2: Surat Pernyataan UKM
    $html2 = $stylesheet . '
    <div class="page-break">
        <div class="title">SURAT PERNYATAAN UKM</div>
        
        <div style="margin: 15px 0;">
            Yang Bertanda tangan di bawah ini :
        </div>
        
        <table class="info">
            <tr>
                <td>Nama Pemohon</td>
                <td>:</td>
                <td>' . $nama_pemohon_display . '</td>
            </tr>';

    if ($data['jenis_pemohon'] === 'perusahaan') {
        $html2 .= '
           <tr>
               <td>Nama Kuasa</td>
               <td>:</td>
               <td>' . strtoupper(htmlspecialchars($data['nama_lengkap'])) . '</td>
           </tr>
           <tr>
               <td>Alamat Kuasa</td>
               <td>:</td>
               <td>' . $alamat_pemohon . '</td>
           </tr>';
    } else {
        $html2 .= '
            <tr>
                <td>Alamat Pemohon</td>
                <td>:</td>
                <td>' . $alamat_pemohon . '</td>
            </tr>';
    }
    $html2 .= '
            <tr>
                <td>Alamat Usaha</td>
                <td>:</td>
                <td>' . $alamat_usaha . '</td>
            </tr>
            <tr>
                <td>Merek</td>
                <td>:</td>
                <td><strong>' . strtoupper(htmlspecialchars($merek_terpilih)) . '</strong></td>
            </tr>
            <tr>
                <td>Kelas Merek</td>
                <td>:</td>
                <td>' . htmlspecialchars($data['kelas_merek']) . '</td>
            </tr>
        </table>
        
        <div class="statement">
            Dengan ini menyatakan bahwa Surat Rekomendasi Usaha Kecil Mikro yang saya lampirkan adalah benar. Apabila dikemudian hari terbukti tidak benar / palsu, maka saya bersedia untuk dilakukan tindakan <strong>Ditarik Kembali</strong> dan <strong>Dihapus</strong> oleh Kantor Direktorat Jenderal Kekayaan Intelektual terhadap Pengajuan Permohonan Merek saya.
        </div>
        
        <div class="statement">
            Demikian surat pernyataan ini saya buat dengan sebenarnya dan untuk digunakan sebagai mestinya.
        </div>
        
        <div class="signature-section">
            <div class="signature-content">
                <div>Sidoarjo, ' . $tanggal_surat . '</div>
               <div style="margin-top: 8px; font-weight: bold;">Pemohon</div>';

    if ($data['jenis_pemohon'] === 'perusahaan') {
        $html2 .= '
               <div class="company-name">' . $nama_pemohon_display . '</div>
               <div class="signature-space kuasa-name">(' . strtoupper(htmlspecialchars($data['nama_lengkap'])) . ')</div>
               <div class="kuasa-label">Kuasa</div>';
    } else {
        $html2 .= '
               <div class="signature-placeholder">(' . strtoupper(htmlspecialchars($data['nama_lengkap'])) . ')</div>';
    }

    $html2 .= '
            </div>
        </div>
    </div>
    ';

    // HALAMAN 3: Tanda Tangan Besar
    $html3 = $stylesheet . '
    <div class="page-break">
        <div style="text-align: center; text-decoration: underline; margin-top: 40px;">
            Tanda tangan besar
        </div>
        
        <div style="margin-top: 250px; text-align: center;">
            <div class="signature-content">
                <div class="signature-placeholder" style="margin: 0;">(' . strtoupper(htmlspecialchars($data['nama_lengkap'])) . ')</div>
            </div>
        </div>
    </div>
    ';

    // Gabungkan semua halaman
    $mpdf->WriteHTML($html1);
    $mpdf->WriteHTML($html2);
    $mpdf->WriteHTML($html3);

    // Output PDF
   $filename = "Syarat Pendaftaran Merek " . strtoupper(preg_replace('/[^A-Za-z0-9_\-]/', '_', $merek_terpilih)) . ".pdf";
    $mpdf->Output($filename, 'D'); // 'D' untuk download, 'I' untuk preview di browser

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
} catch (\Mpdf\MpdfException $e) {
    die("Error PDF: " . $e->getMessage());
}
