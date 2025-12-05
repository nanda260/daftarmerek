<?php
// process/generate_surat_permohonan.php

date_default_timezone_set('Asia/Jakarta');
// Prevent any output before JSON
ob_start();

// Jangan tampilkan error ke browser
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

ini_set('pcre.backtrack_limit', '5000000');
ini_set('pcre.recursion_limit', '5000000');

// Ubah warning/notice menjadi Exception
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once 'config_db.php';
require_once '../vendor/autoload.php';

use Mpdf\Mpdf;

// Clear buffer dan set header
ob_end_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'generate_pdf') {
        throw new Exception('Invalid request');
    }

    $nik = trim(htmlspecialchars($_POST['nik'] ?? ''));
    $signature_data = $_POST['signature'] ?? '';

    if (empty($nik)) {
        throw new Exception('Data user tidak ditemukan untuk NIK: ' . $nik . '. Pastikan NIK sesuai dengan yang ada di database.');
    }

    $nama_usaha = trim(htmlspecialchars($_POST['nama_usaha'] ?? ''));
    // Generate alamat_usaha dari komponen
    $kecamatan_usaha = trim(htmlspecialchars($_POST['kecamatan_usaha'] ?? ''));
    $rt_rw_usaha = trim(htmlspecialchars($_POST['rt_rw_usaha'] ?? ''));
    $kel_desa_usaha = trim(htmlspecialchars($_POST['kel_desa_usaha'] ?? ''));

    $alamat_usaha = '';
    if ($kel_desa_usaha) $alamat_usaha .= 'Desa/Kel. ' . $kel_desa_usaha . ', ';
    if ($rt_rw_usaha) $alamat_usaha .= 'RT/RW ' . $rt_rw_usaha . ', ';
    if ($kecamatan_usaha) $alamat_usaha .= 'Kecamatan ' . $kecamatan_usaha . ', ';
    $alamat_usaha .= 'SIDOARJO, JAWA TIMUR';
    $no_telp_perusahaan = trim(htmlspecialchars($_POST['no_telp_perusahaan'] ?? ''));

    // Ambil data pemilik dari database berdasarkan NIK
    $stmt_user = $pdo->prepare("
        SELECT nama_lengkap, rt_rw, kel_desa, kecamatan, nama_kabupaten, nama_provinsi, no_wa, email
        FROM user 
        WHERE NIK_NIP = ?
    ");
    $stmt_user->execute([$nik]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        throw new Exception('Data user tidak ditemukan untuk NIK: ' . $nik);
    }

    // Generate data pemilik dari database
    $nama_pemilik = htmlspecialchars($user_data['nama_lengkap'] ?? '', ENT_QUOTES);
    $alamat_pemilik = ($user_data['kel_desa'] ? 'Desa/Kel. ' . $user_data['kel_desa'] . ', ' : '') .
        ($user_data['rt_rw'] ? 'RT/RW ' . $user_data['rt_rw'] . ', ' : '') .
        ($user_data['kecamatan'] ? 'Kecamatan ' . $user_data['kecamatan'] . ', ' : '') .
        ($user_data['nama_kabupaten'] ? $user_data['nama_kabupaten'] . ', ' : '') .
        ($user_data['nama_provinsi'] ? $user_data['nama_provinsi'] : '');
    $no_telp_pemilik = htmlspecialchars($user_data['no_wa'] ?? '', ENT_QUOTES);
    $email = htmlspecialchars($user_data['email'] ?? '', ENT_QUOTES);

    $jenis_usaha = trim(htmlspecialchars($_POST['jenis_usaha'] ?? ''));
    $produk = trim(htmlspecialchars($_POST['produk'] ?? ''));
    $jml_tenaga_kerja = trim(htmlspecialchars($_POST['jml_tenaga_kerja'] ?? ''));
    $merek = trim(htmlspecialchars($_POST['merek'] ?? ''));

    // Ambil data jenis pemohon dan profil perusahaan
    $jenis_pemohon = trim(htmlspecialchars($_POST['jenis_pemohon'] ?? 'perseorangan'));
    $nama_perusahaan = trim(htmlspecialchars($_POST['nama_perusahaan'] ?? ''));
    $alamat_perusahaan = trim(htmlspecialchars($_POST['alamat_perusahaan'] ?? ''));
    $email_perusahaan = trim(htmlspecialchars($_POST['email_perusahaan'] ?? ''));
    $no_telp_kop = trim(htmlspecialchars($_POST['no_telp_kop'] ?? ''));

    // Handle logo perusahaan jika ada
    $logo_perusahaan_base64 = '';
    if ($jenis_pemohon === 'perusahaan' && isset($_FILES['logo_perusahaan_file']) && $_FILES['logo_perusahaan_file']['error'] === UPLOAD_ERR_OK) {
        $logo_tmp = $_FILES['logo_perusahaan_file']['tmp_name'];
        $logo_type = $_FILES['logo_perusahaan_file']['type'];

        // Validasi tipe file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($logo_type, $allowed_types)) {
            $logo_content = file_get_contents($logo_tmp);
            if ($logo_content !== false) {
                $logo_perusahaan_base64 = 'data:' . $logo_type . ';base64,' . base64_encode($logo_content);
            }
        }
    }

    if (!$nik || !$signature_data || !$nama_usaha) {
        throw new Exception('Data tidak lengkap: nik, signature, dan nama_usaha wajib diisi.');
    }

    // Strip data URI prefix jika ada
    if (strpos($signature_data, 'base64,') !== false) {
        $signature_data = substr($signature_data, strpos($signature_data, 'base64,') + 7);
    }

    // Validasi base64
    $signature_binary = base64_decode($signature_data, true);
    if ($signature_binary === false || strlen($signature_binary) === 0) {
        throw new Exception('Signature base64 tidak valid atau kosong.');
    }

 // Tentukan tipe pengajuan dari parameter
 $tipe_pengajuan = trim(htmlspecialchars($_POST['tipe_pengajuan'] ?? 'pendaftaran'));
 
 // Tentukan folder berdasarkan tipe
 if ($tipe_pengajuan === 'perpanjangan') {
     $upload_dir = __DIR__ . '/../uploads/suratpermohonanperpanjangan/';
 } else {
     $upload_dir = __DIR__ . '/../uploads/suratpermohonanmandiri/';
 }
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Gagal membuat direktori upload: ' . $upload_dir);
        }
    }


    // Simpan signature ke file temporary untuk menghindari base64 besar di HTML
    $temp_signature_path = $upload_dir . 'temp_signature_' . time() . '.png';
    file_put_contents($temp_signature_path, $signature_binary);

    // Gunakan path file instead of base64 (lebih efisien untuk mPDF)
    $signature_for_pdf = $temp_signature_path;

    // Format tanggal Indonesia
    $bulan_indo = [
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
    $tanggal_lengkap = date('d') . ' ' . $bulan_indo[(int)date('m')] . ' ' . date('Y');

    // Format jenis usaha
    $jenis_usaha_display = ucwords(str_replace('_', ' ', $jenis_usaha));

    // Generate KOP Surat jika jenis pemohon = perusahaan
    $kop_surat = '';
    if ($jenis_pemohon === 'perusahaan' && !empty($nama_perusahaan)) {
        $logo_html = '';
        if (!empty($logo_perusahaan_base64)) {
            $logo_html = '<img src="' . $logo_perusahaan_base64 . '" style="max-height: 60px; max-width: 100%; width: auto; height: 60px; object-fit: contain; display: block;">';
        }

        $kop_surat = '
      <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #000; overflow: hidden;">
          <table style="width: 100%; border: none; margin: 0;">
              <tr>
              <td style="width:50px;">
              </td>
            <td style="vertical-align: middle; text-align: center; padding: 0; padding-right: 8px;">
               ' . $logo_html . '
           </td>
                  <td style="vertical-align: middle; text-align: center;">
                      <div style="font-size: 14pt; font-weight: bold; margin-bottom: 3px; line-height: 1.2;">' . strtoupper($nama_perusahaan) . '</div>
                      <div style="font-size: 9pt; line-height: 1.3;">
                          ' . nl2br($alamat_perusahaan) . '<br>
                          Telp: ' . $no_telp_kop . ($email_perusahaan ? ' | Email: ' . $email_perusahaan : '') . '
                      </div>
                  </td>
            <td style="width:50px;">
            </td>
              </tr>
          </table>
        </div>';
    }

    // Create PDF dengan mPDF
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 25,
        'margin_right' => 25,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_header' => 0,
        'margin_footer' => 0
    ]);


    // Split HTML menjadi bagian-bagian kecil untuk menghindari pcre.backtrack_limit
    // Pisahkan berdasarkan section logis
    $html_parts = [];

    // Part 1: Style + Opening body + Kop Surat
    $html_parts[] = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { 
                font-family: Arial, Helvetica, sans-serif;
                font-size: 10pt; 
                line-height: 1.35;
                color: #000;
            }
            .tanggal { 
                margin-bottom: 8px;
                margin-top: 12px;
                margin-left: 50%;
                text-align: left;
                font-size: 10pt;
            }
            .kepada { 
                margin-bottom: 12px; 
                margin-top: 0px;
                margin-left: 50%;
                line-height: 1.25; 
                font-size: 10pt;
            }
            .salam { 
                margin-bottom: 5px;
                margin-top: 10px;
                font-size: 10pt;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 8px 0 12px 30px; 
            }
            table td { 
                padding: 1.5px 0; 
                font-size: 9.5pt;
                vertical-align: top;
                line-height: 1.35;
            }
            table td:first-child { 
                width: 32%; 
            }
            .content { 
                text-align: justify; 
                margin: 10px 0; 
                font-size: 10pt;
                text-indent: 40px;
                line-height: 1.35;
            }
            .signature-section { 
                margin-top: 15px;
                text-align: right;
                margin-right: 50px;
            }
            .signature-section p {
                margin: 2px 0;
                font-size: 10pt;
            }
            .signature-image { 
                width: auto; 
                height: 65px;
                margin: 3px 0;
                margin-left: 50px;
            }
            .signature-name {
                margin-top: 5px;
                font-size: 10pt;
            }
            .lampiran { 
                margin-top: 12px; 
                margin-left: 0px;
                font-size: 10pt;
            }
            .lampiran ul { 
                margin-left: 35px; 
                margin-top: 3px;
                margin-bottom: 0;
                font-size: 9.5pt;
                list-style-type: decimal;
            }
            .lampiran li {
                margin-bottom: 2px;
                line-height: 1.35;
            }
        </style>
    </head>
    <body>
        ' . $kop_surat;

    // Part 2: Kepada & Data Pemohon
    $html_parts[] = '
        <div class="kepada">
            <br>
            Sidoarjo, ' . $tanggal_lengkap . '<br>
            Kepada<br>
            Yth. Kepala Dinas Perindustrian dan Perdagangan<br>
            Kabupaten Sidoarjo<br>
            Di<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Tempat
        </div>
        
        <div class="salam">Dengan hormat,</div>
        <p style="margin-bottom: 8px;">Yang bertanda tangan dibawah ini:</p>
        
        <table>
            <tr>
                <td>1. Nama Usaha</td>
                <td>: ' . $nama_usaha . '</td>
            </tr>
            <tr>
                <td>2. Alamat Usaha</td>
                <td>: ' . $alamat_usaha . '</td>
            </tr>
            <tr>
                <td>3. No. Telp Usaha</td>
                <td>: ' . $no_telp_perusahaan . '</td>
            </tr>
            <tr>
                <td>4. Nama Pemilik</td>
                <td>: ' . $nama_pemilik . '</td>
            </tr>
            <tr>
                <td>5. Alamat Pemilik</td>
                <td>: ' . $alamat_pemilik . '</td>
            </tr>
            <tr>
                <td>6. No. Telp Pemilik</td>
                <td>: ' . $no_telp_pemilik . '</td>
            </tr>
            <tr>
                <td>7. E-mail</td>
                <td>: ' . $email . '</td>
            </tr>
            <tr>
                <td>8. Jenis Usaha</td>
                <td>: ' . $jenis_usaha_display . '</td>
            </tr>
            <tr>
                <td>9. Produk</td>
                <td>: ' . $produk . '</td>
            </tr>
            <tr>
                <td>10. Jumlah tenaga kerja</td>
                <td>: ' . $jml_tenaga_kerja . '</td>
            </tr>
            <tr>
                <td>11. Merek</td>
                <td>: ' . $merek . '</td>
            </tr>
        </table>';

    // Part 3: Content, Lampiran, dan Signature
    $html_parts[] = '
        <div class="content">
            Mengajukan permohonan Surat Keterangan Industri Kecil dan Menengah (IKM) 
            untuk keperluan pengurusan merek "<strong>' . $merek . '</strong>" Di kementerian Hukum dan HAM.
        </div>
        
        <div class="content">
            Demikian untuk menjadikan maklum. Atas perhatian dan bantuannya disampaikan terima kasih.
        </div>
        
        <div class="lampiran">
            <p style="margin-bottom: 5px;"><strong>Lampiran:</strong></p>
            <ul>
                <li>KTP</li>
                <li>Izin usaha (NIB RBA berbasis resiko)</li>
                <li>Nama dan etikat merek</li>
                <li>Foto lokasi, foto produk, dan tempat produksi</li>
                <li>Akta pendirian CV/PT (Khusus CV/PT)</li>
            </ul>
        </div>
        
        <div class="signature-section">
            <p>Hormat kami,</p>
            <br>
            <p>Pemilik</p>
            <img src="' . $signature_for_pdf . '" class="signature-image" alt="Tanda Tangan">
            <br>
            <p class="signature-name">' . $nama_pemilik . '</p>
        </div>
    </body>
    </html>';

    // Write HTML dalam bagian-bagian kecil
    foreach ($html_parts as $part) {
        $mpdf->WriteHTML($part);
    }

    // Save PDF to file
    $filename = 'suratpermohonan_' . $nik . '_' . time() . '.pdf';
    $filepath = $upload_dir . $filename;

    $mpdf->Output($filepath, 'F');

    // Hapus temporary signature file
    if (file_exists($temp_signature_path)) {
        @unlink($temp_signature_path);
    }

    if (!file_exists($filepath)) {
        throw new Exception('PDF gagal disimpan ke: ' . $filepath);
    }

 // Tentukan relative path berdasarkan tipe
 if ($tipe_pengajuan === 'perpanjangan') {
     $relative_path = 'uploads/suratpermohonanperpanjangan/' . $filename;
 } else {
     $relative_path = 'uploads/suratpermohonanmandiri/' . $filename;
 }

    echo json_encode([
        'success' => true,
        'message' => 'PDF berhasil dibuat',
        'file_path' => $relative_path
    ]);
    exit();
} catch (Exception $e) {
    // Tangkap buffer jika ada error
    @ob_end_clean();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit();
}
