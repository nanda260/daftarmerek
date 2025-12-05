<?php
// Prevent any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['NIK_NIP'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'config_db.php';

$nik = $_SESSION['NIK_NIP'];
$input = json_decode(file_get_contents('php://input'), true);

$type = $input['type'] ?? '';
$id = intval($input['id'] ?? 0);

if (!$type || !$id) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit();
}

try {
    $data = [];

    if ($type === 'pendaftaran') {
        // Ambil data dari pendaftaran beserta merek yang difasilitasi
        $stmt = $pdo->prepare("
            SELECT 
                p.id_pendaftaran,
                p.merek_difasilitasi,
                du.nama_usaha,
                du.rt_rw,
                du.kel_desa,
                du.kecamatan,
                du.no_telp_perusahaan,
                du.hasil_produk,
                du.jml_tenaga_kerja,
                du.jenis_pemohon,
                m.kelas_merek,
                m.nama_merek1,
                m.nama_merek2,
                m.nama_merek3,
                m.logo1,
                m.logo2,
                m.logo3
            FROM pendaftaran p
            JOIN datausaha du ON p.id_usaha = du.id_usaha
            JOIN merek m ON m.id_pendaftaran = p.id_pendaftaran
            WHERE p.id_pendaftaran = ? AND p.NIK = ?
        ");
        $stmt->execute([$id, $nik]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Data pendaftaran tidak ditemukan');
        }

        // Tentukan merek dan logo yang difasilitasi
        $merek_difasilitasi = $row['merek_difasilitasi'] ?? 1;
        $merek_field = 'nama_merek' . $merek_difasilitasi;
        $logo_field = 'logo' . $merek_difasilitasi;
        
        $merek_terpilih = $row[$merek_field] ?? '';
        $logo_terpilih = $row[$logo_field] ?? '';

        $data = [
            'id_pendaftaran' => $row['id_pendaftaran'],
            'nama_usaha' => $row['nama_usaha'],
            'rt_rw' => $row['rt_rw'],
            'kel_desa' => $row['kel_desa'],
            'kecamatan' => $row['kecamatan'],
            'no_telp_perusahaan' => $row['no_telp_perusahaan'] ?? '',
            'produk' => $row['hasil_produk'],
            'jml_tenaga_kerja' => $row['jml_tenaga_kerja'],
            'merek' => $merek_terpilih,
            'kelas_merek' => $row['kelas_merek'],
            'jenis_usaha' => '',
            'jenis_pemohon' => $row['jenis_pemohon'] ?? 'perseorangan',
            'nama_perusahaan' => null,
            'logo_perusahaan' => null,
            'alamat_perusahaan' => null,
            'email_perusahaan' => null,
            'lampiran' => [
                'logo' => $logo_terpilih ? [$logo_terpilih] : [],
                'logo_perusahaan' => [],
                'nib' => [],
                'produk' => [],
                'proses' => [],
                'akta' => []
            ]
        ];

        // Ambil lampiran dari tabel lampiran
        $stmt_lampiran = $pdo->prepare("
            SELECT mfl.nama_jenis_file, l.file_path
            FROM lampiran l
            JOIN masterfilelampiran mfl ON l.id_jenis_file = mfl.id_jenis_file
            WHERE l.id_pendaftaran = ?
        ");
        $stmt_lampiran->execute([$id]);
        $lampiran_rows = $stmt_lampiran->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lampiran_rows as $lamp) {
            $jenis = strtolower($lamp['nama_jenis_file']);
            
            if (strpos($jenis, 'nib') !== false || strpos($jenis, 'nomor induk') !== false) {
                $data['lampiran']['nib'][] = $lamp['file_path'];
            } elseif (strpos($jenis, 'foto produk') !== false) {
                $data['lampiran']['produk'][] = $lamp['file_path'];
            } elseif (strpos($jenis, 'proses') !== false) {
                $data['lampiran']['proses'][] = $lamp['file_path'];
            } elseif (strpos($jenis, 'akta') !== false) {
                $data['lampiran']['akta'][] = $lamp['file_path'];
            }
        }

    } elseif ($type === 'pengajuan') {
        // Ambil data dari pengajuansurat
        $stmt = $pdo->prepare("
            SELECT * FROM pengajuansurat
            WHERE id_pengajuan = ? AND NIK = ? AND tipe_pengajuan = 'mandiri'
        ");
        $stmt->execute([$id, $nik]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Data pengajuan tidak ditemukan');
        }

        // Parse JSON lampiran
        $nib_paths = json_decode($row['nib_file'], true) ?? [];
        $produk_paths = json_decode($row['foto_produk'], true) ?? [];
        $proses_paths = json_decode($row['foto_proses'], true) ?? [];

        $data = [
            'id_pendaftaran' => $row['id_pendaftaran'],
            'nama_usaha' => $row['nama_usaha'],
            'rt_rw' => '',
            'kel_desa' => '',
            'kecamatan' => '',
            'jenis_usaha' => $row['jenis_usaha'],
            'produk' => $row['produk'],
            'jml_tenaga_kerja' => $row['jml_tenaga_kerja'],
            'merek' => $row['merek'],
            'kelas_merek' => $row['kelas_merek'],
            'jenis_pemohon' => $row['jenis_pemohon'] ?? 'perseorangan',
            'nama_perusahaan' => $row['nama_perusahaan'] ?? null,
            'logo_perusahaan' => $row['logo_perusahaan'] ?? null,
            'alamat_perusahaan' => $row['alamat_perusahaan'] ?? null,
            'no_telp_perusahaan' => $row['no_telp_perusahaan'] ?? '',
            'no_telp_kop' => $row['no_telp_kop'] ?? null,
            'email_perusahaan' => $row['email_perusahaan'] ?? null,
            'lampiran' => [
                'logo' => $row['logo_merek'] ? [$row['logo_merek']] : [],
                'logo_perusahaan' => $row['logo_perusahaan'] ? [$row['logo_perusahaan']] : [],
                'nib' => $nib_paths,
                'produk' => $produk_paths,
                'proses' => $proses_paths,
                'akta' => $row['akta_file'] ? [$row['akta_file']] : []
            ]
        ];

        // Extract kecamatan dan kel_desa dari alamat_usaha jika ada
        // Contoh format: "Desa/Kel. Punggul, RT/RW 002/005, Kecamatan Gedangan, SIDOARJO, JAWA TIMUR"
        if ($row['alamat_usaha']) {
            preg_match('/Desa\/Kel\.\s+([^,]+)/', $row['alamat_usaha'], $kel_match);
            preg_match('/Kecamatan\s+([^,]+)/', $row['alamat_usaha'], $kec_match);
            preg_match('/RT\/RW\s+([^,]+)/', $row['alamat_usaha'], $rtrw_match);

            if (!empty($kel_match[1])) $data['kel_desa'] = trim($kel_match[1]);
            if (!empty($kec_match[1])) $data['kecamatan'] = trim($kec_match[1]);
            if (!empty($rtrw_match[1])) $data['rt_rw'] = trim($rtrw_match[1]);
        }

    } else {
        throw new Exception('Tipe data tidak valid');
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}