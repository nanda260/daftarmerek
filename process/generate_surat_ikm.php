<?php
session_start();
require_once 'config_db.php';
require_once 'crypto_helper.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Style\Font;

// Cek login
if (!isset($_SESSION['NIK_NIP']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../login.php");
    exit;
}

// Ambil token dari URL dan decrypt
$encrypted_token = isset($_GET['token']) ? $_GET['token'] : '';
$id_pengajuan = 0;

if (!empty($encrypted_token)) {
    $decrypted_id = decryptId($encrypted_token);
    $id_pengajuan = $decrypted_id !== false ? intval($decrypted_id) : 0;
}

if ($id_pengajuan == 0) {
    die("ID Pengajuan tidak valid");
}

try {
    // Ambil data pengajuan
    $stmt = $pdo->prepare("SELECT * FROM pengajuansurat WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Data pengajuan tidak ditemukan");
    }

    // Format tanggal untuk surat (TIDAK DIGUNAKAN DI OUTPUT, HANYA UNTUK PLACEHOLDER NAMA)
    $tanggal_surat = formatTanggalSurat(date('Y-m-d'));

    // Generate nomor surat (TIDAK DIGUNAKAN DI OUTPUT, HANYA UNTUK PLACEHOLDER NAMA)
    $nomor_surat = sprintf("%03d/SURATIKM/DISPERINDAG/%s", $id_pengajuan, date('Y'));
    
    // MODIFIKASI: Placeholder untuk nomor surat yang diminta ('Nomor : ${nomor}')
    // Menggunakan string literal sesuai permintaan
    $nomor_surat_display = 'Nomor : ${nomor}';

    // MODIFIKASI: Placeholder untuk tanggal surat di TTD ('Sidoarjo, ${tanggal_surat}')
    // Menggunakan string literal sesuai permintaan
    $tanggal_surat_display = 'Sidoarjo, ${tanggal_surat}';

    // Generate DOCX menggunakan PHPWord
    generateSuratKeteranganDOCX($nomor_surat_display, $tanggal_surat_display, $data);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

/**
 * Format tanggal dari string menjadi format "dd Bulan yyyy".
 * @param string $date_str Tanggal dalam format Y-m-d.
 * @return string Tanggal terformat.
 */
function formatTanggalSurat($date_str)
{
    $bulan = array(
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    );

    $timestamp = strtotime($date_str);
    $tgl = date('d', $timestamp);
    $bln = $bulan[date('n', $timestamp)];
    $thn = date('Y', $timestamp);

    return $tgl . ' ' . $bln . ' ' . $thn;
}

/**
 * Format alamat pemilik dari raw string menjadi format lengkap.
 * Format: Desa/Kel. ....., RT/RW ......, Kecamatan ...., ......, .......
 * @param string $alamat_raw Alamat mentah dari database.
 * @return string Alamat terformat.
 */
function formatAlamatPemilik($alamat_raw)
{
    $parts = explode(',', $alamat_raw);
    $parts = array_map('trim', $parts);
    
    // Asumsi struktur: [Desa/Kel], [RT/RW], [Kecamatan], [Kab/Kota], [Provinsi]
    // Sesuaikan dengan struktur data Anda
    
    $formatted = array();
    
    if (isset($parts[0]) && !empty($parts[0])) {
        $formatted[] = 'Desa/Kel. ' . $parts[0];
    }
    
    if (isset($parts[1]) && !empty($parts[1])) {
        $formatted[] = 'RT/RW ' . $parts[1];
    }
    
    if (isset($parts[2]) && !empty($parts[2])) {
        $formatted[] = 'Kecamatan ' . $parts[2];
    }
    
    // Untuk Kab/Kota dan Provinsi (2 bagian terakhir), uppercase
    if (isset($parts[3]) && !empty($parts[3])) {
        $formatted[] = strtoupper($parts[3]);
    }
    
    if (isset($parts[4]) && !empty($parts[4])) {
        $formatted[] = strtoupper($parts[4]);
    }
    
    return implode(', ', $formatted);
}

/**
 * Generate dokumen Surat Keterangan IKM dalam format DOCX.
 * @param string $nomor_display Nomor surat yang sudah diformat untuk tampilan (placeholder).
 * @param string $tanggal_display Tanggal surat yang sudah diformat (placeholder).
 * @param array $data Data pengajuan.
 * @throws \PhpOffice\PhpWord\Exception\Exception
 */
function generateSuratKeteranganDOCX($nomor_display, $tanggal_display, $data)
{
    $phpWord = new PhpWord();

    // Set default font
    $phpWord->setDefaultFontName('Arial');
    $phpWord->setDefaultFontSize(11);

    // Style untuk Teks Data
    $dataTextStyle = ['size' => 11];

    // Margin dokumen standar
    $section = $phpWord->addSection([
        'marginLeft' => Converter::cmToTwip(2.5),
        'marginRight' => Converter::cmToTwip(2),
        'marginTop' => Converter::cmToTwip(2),
        'marginBottom' => Converter::cmToTwip(2)
    ]);
    
    // Lebar area konten: 21 cm (A4) - 2.5 cm (L) - 2 cm (R) = 16.5 cm
    $contentWidthCm = 16.5; 

    // ===== HEADER KOP SURAT (Logo di Kiri Saja) =====
    $headerTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT
    ]);

    $headerTable->addRow();
    
    // Kolom 1: Logo kiri (sekitar 2.5 cm)
    $cell1 = $headerTable->addCell(Converter::cmToTwip(2.5), ['valign' => 'center']); 
    $logo_path = __DIR__ . '/../assets/img/logo-blackwhite.png';
    if (file_exists($logo_path)) {
        $cell1->addImage($logo_path, [
            'width' => 80,
            'height' => 80,
            'alignment' => Jc::CENTER
        ]);
    }

    // Kolom 2: Text header (sisanya)
    $cell2 = $headerTable->addCell(Converter::cmToTwip(14), ['valign' => 'center']);
    
    $kopBold14 = ['bold' => true, 'size' => 14];
    $kopBold13 = ['bold' => true, 'size' => 13];
    $kopSize10 = ['size' => 10];
    $kopSize9 = ['size' => 9];
    $kopParagraphStyle = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];
    
    $cell2->addText('PEMERINTAH KABUPATEN SIDOARJO', $kopBold14, $kopParagraphStyle);
    $cell2->addText('DINAS PERINDUSTRIAN DAN PERDAGANGAN', $kopBold13, $kopParagraphStyle);
    $cell2->addText('Jalan Jaksa Agung R. Suprapto No. 9 Sidoarjo Kode Pos 61218', $kopSize10, $kopParagraphStyle);
    $cell2->addText('Telepon (031) 8949717 Faks (031) 8949717', $kopSize10, $kopParagraphStyle);
    $cell2->addText('Email : disperindag@sidoarjokab.go.id Website : www.disperindag.sidoarjokab.go.id', $kopSize9, $kopParagraphStyle);

    // Garis pemisah horizontal
    $section->addText('', [], ['borderBottomSize' => 18, 'borderBottomColor' => '000000', 'spaceAfter' => 200]);

    // ===== JUDUL SURAT & NOMOR =====
    $section->addText(
        'SURAT KETERANGAN',
        ['bold' => true, 'size' => 14, 'underline' => 'single'],
        ['alignment' => Jc::CENTER, 'spaceAfter' => 100]
    );

    // Nomor surat (Menggunakan placeholder)
    $section->addText(
        $nomor_display,
        $dataTextStyle,
        ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
    );

    // ===== ISI SURAT - INTRO & DATA =====
    $section->addText(
        'Bersama ini menerangkan bahwa :',
        $dataTextStyle,
        ['spaceAfter' => 100]
    );

    $alamat_pemilik_formatted = formatAlamatPemilik($data['alamat_pemilik']);

    // Table untuk data dengan spacing yang lebih kecil
    $dataTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'cellSpacing' => 0
    ]);

    // Kolom 1: Label (3000), Kolom 2: Titik Dua (300), Kolom 3: Value (5700) - Total width 9000
    $col1Width = 3000; 
    $col2Width = 300; 
    $col3Width = 5700; 

    // Helper function untuk add row dengan spacing minimal
    $addDataRow = function($label, $value) use ($dataTable, $col1Width, $col2Width, $col3Width, $dataTextStyle) {
        $dataTable->addRow(null, ['tblHeader' => false]);
        $dataTable->addCell($col1Width, ['valign' => 'top'])->addText($label, $dataTextStyle, ['spaceAfter' => 0, 'spaceBefore' => 0, 'spacing' => 0]);
        $dataTable->addCell($col2Width, ['valign' => 'top'])->addText(':', $dataTextStyle, ['spaceAfter' => 0, 'spaceBefore' => 0, 'spacing' => 0]);
        $dataTable->addCell($col3Width, ['valign' => 'top'])->addText($value, $dataTextStyle, ['spaceAfter' => 0, 'spaceBefore' => 0, 'spacing' => 0]);
    };

    // Data rows
    $addDataRow('a) Nama Perusahaan', htmlspecialchars($data['nama_usaha']));
    $addDataRow('b) Alamat Perusahaan', htmlspecialchars($data['alamat_usaha']));
    $addDataRow('c) No Telp Perusahaan', htmlspecialchars($data['no_telp_perusahaan']));
    $addDataRow('d) Pemilik', htmlspecialchars($data['nama_pemilik']));
    $addDataRow('e) Alamat Pemilik', $alamat_pemilik_formatted);
    $addDataRow('f) No Telp Pemilik', htmlspecialchars($data['no_telp_pemilik']));
    $addDataRow('g) Jenis Usaha', htmlspecialchars($data['jenis_usaha']));
    $komoditi_text = htmlspecialchars($data['produk']) . ' (kelas ' . htmlspecialchars($data['kelas_merek']) . ')';
    $addDataRow('h) Komoditi', $komoditi_text);

    // Maksud/Tujuan
    $dataTable->addRow();
    $dataTable->addCell($col1Width, ['valign' => 'top'])->addText('i) Maksud / Tujuan', $dataTextStyle, ['spaceAfter' => 0, 'spaceBefore' => 0]);
    $dataTable->addCell($col2Width, ['valign' => 'top'])->addText(':', $dataTextStyle, ['spaceAfter' => 0, 'spaceBefore' => 0]);
    
    $cellTujuan = $dataTable->addCell($col3Width, ['valign' => 'top']);
    
    $merekBoldStyle = array_merge($dataTextStyle, ['bold' => true]);
    $textrun = $cellTujuan->addTextRun(['alignment' => Jc::BOTH, 'spaceAfter' => 0, 'spaceBefore' => 0]);
    $textrun->addText('Pengajuan pendaftaran merek "', $dataTextStyle);
    $textrun->addText(htmlspecialchars($data['merek']), $merekBoldStyle); // Merek di bold
    $textrun->addText('" di Kementerian Hukum dan HAM (menurut PP No. 45 tahun 2016 tentang perubahan kedua atas PP No. 45 tahun 2014 tentang jenis dan tarif atas jenis penerimaan negara bukan pajak yang berlaku pada Kementerian Hukum dan HAM)', $dataTextStyle);

    // Pernyataan & Penutup
    $section->addText(
        'Yang bersangkutan merupakan Industri Kecil Menengah (IKM) dan berdomisili di wilayah Kabupaten Sidoarjo.',
        $dataTextStyle,
        ['alignment' => Jc::BOTH, 'spaceAfter' => 100, 'spaceBefore' => 200]
    );

    $section->addText(
        'Demikian Surat Keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.',
        $dataTextStyle,
        ['alignment' => Jc::BOTH, 'spaceAfter' => 600]
    );

    // ===== TTD (Menggunakan tabel untuk margin kiri 55% dari area konten 16.5cm) =====
    
    // Area konten: 16.5 cm
    // 55% dari 16.5 cm = 9.075 cm (padding)
    // Sisa (45%) = 7.425 cm (TTD content)

    $paddingCm = 9.075;
    $ttdCm = 7.425;
    
    $signatureTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT
    ]);
    
    $signatureTable->addRow();
    
    // Kolom 1: Padding kosong 55%
    $signatureTable->addCell(Converter::cmToTwip($paddingCm), ['valign' => 'top']); 

    // Kolom 2: Konten TTD (45% sisa)
    $ttdCell = $signatureTable->addCell(Converter::cmToTwip($ttdCm), ['valign' => 'top']); 
    
    // Kota dan Tanggal (Menggunakan placeholder)
    $ttdCell->addText(
        $tanggal_display,
        $dataTextStyle,
        ['alignment' => Jc::LEFT, 'spaceAfter' => 0]
    );
    
    // Jabatan
    $ttdCell->addText(
        'KEPALA DINAS',
        ['bold' => true, 'size' => 11],
        ['alignment' => Jc::LEFT, 'spaceAfter' => 0]
    );
    
    $ttdCell->addText(
        'PERINDUSTRIAN DAN PERDAGANGAN',
        ['bold' => true, 'size' => 11],
        ['alignment' => Jc::LEFT, 'spaceAfter' => 0]
    );
    
    $ttdCell->addText(
        'KABUPATEN SIDOARJO',
        ['bold' => true, 'size' => 11],
        ['alignment' => Jc::LEFT, 'spaceAfter' => 400]
    );

    // QR Code placeholder (Hitam)
    $ttdCell->addText(
        '${qrcode}',
        ['size' => 9, 'color' => '000000'], 
        ['alignment' => Jc::LEFT, 'spaceAfter' => 200]
    );
    
    // Nama dan NIP
    $ttdCell->addText(
        'WIDIYANTORO BASUKI, S.H.',
        ['bold' => true, 'size' => 11, 'underline' => 'single'],
        ['alignment' => Jc::LEFT, 'spaceAfter' => 0]
    );
    
    $ttdCell->addText(
        'Pembina Utama Muda',
        $dataTextStyle,
        ['alignment' => Jc::LEFT, 'spaceAfter' => 0]
    );
    
    $ttdCell->addText(
        'NIP.19660228 199602 1 001',
        $dataTextStyle,
        ['alignment' => Jc::LEFT]
    );

    // ===== FOOTER (Tanpa Border) =====
    $footer = $section->addFooter();
    
    // Table footer untuk logo dan teks (TANPA BORDER)
    $footerTable = $footer->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'cellMargin' => 0
    ]);

    $footerTable->addRow();
    
    // Kolom Logo (tanpa border)
    $cellFooterLogo = $footerTable->addCell(Converter::cmToTwip(2.5), [
        'valign' => 'center',
        'borderTopSize' => 0,
        'borderRightSize' => 0,
        'borderBottomSize' => 0,
        'borderLeftSize' => 0,
        'borderTopColor' => 'FFFFFF',
        'borderRightColor' => 'FFFFFF',
        'borderBottomColor' => 'FFFFFF',
        'borderLeftColor' => 'FFFFFF'
    ]);
    $bsre_logo_path = __DIR__ . '/../assets/img/bsre_logo.png';
    if (file_exists($bsre_logo_path)) {
        $cellFooterLogo->addImage($bsre_logo_path, [
            'height' => 25,
            'alignment' => Jc::CENTER
        ]);
    }
    
    // Text footer (tanpa border)
    $cellFooterText = $footerTable->addCell(Converter::cmToTwip(14), [
        'valign' => 'center',
        'borderTopSize' => 0,
        'borderRightSize' => 0,
        'borderBottomSize' => 0,
        'borderLeftSize' => 0,
        'borderTopColor' => 'FFFFFF',
        'borderRightColor' => 'FFFFFF',
        'borderBottomColor' => 'FFFFFF',
        'borderLeftColor' => 'FFFFFF'
    ]);
    $cellFooterText->addText(
        'Dokumen ini telah ditandatangani secara elektronik menggunakan sertifikat elektronik yang diterbitkan oleh BSrE sesuai dengan Undang-Undang No 11 Tahun 2008 tentang Informasi dan Transaksi Elektronik. Tandatangan secara elektronik memiliki kekuatan hukum dan akibat hukum yang sah',
        ['size' => 7],
        ['alignment' => Jc::CENTER]
    );

    // ===== SAVE & DOWNLOAD =====
    $filename = 'Surat_Keterangan_IKM_' . htmlspecialchars($data['NIK']) . '.docx';
    $temp_file = sys_get_temp_dir() . '/' . $filename;

    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($temp_file);

    // Download file
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($temp_file));

    ob_clean();
    flush();

    readfile($temp_file);
    unlink($temp_file);

    exit;
}