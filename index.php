<?php
require_once 'process/config_db.php';

$total_kuota = 100;

$tahun_sekarang = date('Y');

try {
    $query_difasilitasi = "SELECT COUNT(*) as jumlah_difasilitasi 
                           FROM pendaftaran 
                           WHERE merek_difasilitasi IS NOT NULL 
                           AND YEAR(tgl_daftar) = :tahun";
    
    $stmt = $pdo->prepare($query_difasilitasi);
    $stmt->execute(['tahun' => $tahun_sekarang]);
    $data = $stmt->fetch();
    $jumlah_difasilitasi = $data['jumlah_difasilitasi'];
    
    $kuota_tersedia = $total_kuota - $jumlah_difasilitasi;
    
    // Pastikan kuota tersedia tidak negatif
    if ($kuota_tersedia < 0) {
        $kuota_tersedia = 0;
    }
} catch (PDOException $e) {
    // Jika terjadi error, set nilai default
    $jumlah_difasilitasi = 0;
    $kuota_tersedia = $total_kuota;
    error_log("Error mengambil data kuota: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Merek - Disperindag</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
</head>

<body>
    <?php include 'navbar.php' ?>


    <!-- Hero -->
    <section class="hero-section" id="hero">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="hero-title">SELAMAT DATANG<br>DI LAYANAN FASILITASI PENDAFTARAN MEREK</h1>
                    <p class="hero-subtitle">DINAS PERINDUSTRIAN DAN PERDAGANGAN KABUPATEN SIDOARJO</p>
                    <a class="btn-register" data-bs-toggle="modal" data-bs-target="#daftarModal">DAFTAR MEREK SEKARANG!</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Visi Misi -->
    <section class="vision-mission" id="visimisi">
        <div class="container">
            <h2 class="section-title">DINAS PERINDUSTRIAN DAN PERDAGANGAN KABUPATEN SIDOARJO</h2>
            <div class="row">
                <div class="col-md-6">
                    <h5 style="font-weight: 600; margin-bottom: 1rem;">Visi</h5>
                    <p>Terwujudnya Kabupaten Sidoarjo yang sejahtera, maju, berkarakter, dan berkelanjutan.</p>
                </div>
                <div class="col-md-6">
                    <h5 style="font-weight: 600; margin-bottom: 1rem;">Misi</h5>
                    <ul class="ps-3">
                        <li class="mb-2">Mewujudkan tata kelola pemerintahan yang bersih, transparan, dan tanggap melalui digitalisasi untuk meningkatkan kualitas pelayanan publik.</li>
                        <li class="mb-2">Membangkitkan pertumbuhan ekonomi dengan fokus pada kemandirian lokal berbasis UMKM, koperasi, pertanian, perikanan, sektor jasa, dan industri untuk membuka lapangan pekerjaan dan mengurangi kemiskinan.</li>
                        <li class="mb-2">Membangun infrastruktur ekonomi dan sosial yang modern dan berkelanjutan dengan memperhatikan keberlanjutan lingkungan.</li>
                        <li class="mb-2">Membangun SDM unggul dan berkarakter melalui peningkatan akses pelayanan bidang pendidikan, kesehatan, serta kebutuhan dasar lainnya.</li>
                        <li class="mb-2">Mewujudkan masyarakat religius yang berpegeng teguh pada nilai-nilai keagamaan, serta mampu menjaga kerukunan sosial antar warga.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Info -->
    <section class="info-section" id="info">
        <div class="container">
            <h2 class="section-title">APA SIH MEREK ITU?</h2>
            <div class="row g-4 d-flex align-items-stretch">
                <div class="col-12 col-md-6">
                    <div class="info-card h-100">
                        <h5>Informasi Merek</h5>
                        <p>Merek adalah tanda berupa nama, simbol, logo, huruf, angka, susunan warna, atau kombinasi dari semuanya yang digunakan untuk membedakan barang dan/atau jasa yang diproduksi oleh seseorang atau beberapa orang secara bersama-sama atau badan hukum lainnya. Pendaftaran merek di Indonesia diatur oleh <strong>Undang-Undang Nomor 20 Tahun 2016 tentang Merek dan Indikasi Geografis.</strong></p>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="info-card h-100">
                        <h5>Manfaat Pendaftaran Merek</h5>
                        <ul class="ps-3">
                            <li class="mb-2">Memberikan perlindungan hukum terhadap merek usaha.</li>
                            <li class="mb-2">Menjadi identitas dan pembeda produk/jasa dengan menambah nilai komersial dan daya saing usaha.</li>
                            <li class="mb-2">Menjadi aset berharga yang bisa dilisensikan atau dialihkan.</li>
                            <li class="mb-2">Memperkuat strategi promosi dan pemasaran.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Kuota -->
    <section class="kuota-section" id="kuota">
    <div class="container">
        <h2 class="section-title">KUOTA PENDAFTARAN MEREK</h2>
        <div class="row justify-content-center">
            <div class="col-md-3">
                <div class="kuota-card blue">
                    <div class="kuota-nomor"><?php echo $total_kuota; ?></div>
                    <div class="kuota-text">Jumlah kuota<br>per tahun</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kuota-card green">
                    <div class="kuota-nomor"><?php echo $kuota_tersedia; ?></div>
                    <div class="kuota-text">Jumlah kuota<br>tersedia</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kuota-card red">
                    <div class="kuota-nomor"><?php echo $jumlah_difasilitasi; ?></div>
                    <div class="kuota-text">Merek sudah<br>difasilitasi</div>
                </div>
            </div>
        </div>
    </div>
</section>

    <section class="cta-section">
        <div class="container">
            <p style="font-size: 1.1rem; margin-bottom: 2rem; color: #161616;">AMANKAN IDENTITAS BISNIS ANDA, DAFTARKAN MEREK SEKARANG!</p>
            <a class="btn-register" data-bs-toggle="modal" data-bs-target="#daftarModal" style="background-color: #161616; color: white;">DAFTAR MEREK SEKARANG!</a>
        </div>
    </section>

    <!-- Modal -->
    <div class="modal fade" id="daftarModal" tabindex="-1" aria-labelledby="daftarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4" style="border-radius: 12px;">
                <div class="modal-body">
                    <h4 class="mb-3" style="font-weight: 700;">Apakah Sudah Memiliki Akun?</h4>
                    <p>Jika sudah memiliki akun, tekan <strong>Sudah</strong>,
                        dan jika belum tekan <strong>Registrasi</strong> untuk mengisi data diri.</p>
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <a href="registrasi.php" class="btn px-4 py-2" style="border: 1px solid #161616; color: #161616;">
                            Registrasi
                        </a>
                        <a href="login.php" class="btn px-4 py-2" style="background-color: #161616; color: white;">
                            Sudah
                        </a>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer  -->
    <footer class="footer">
        <div class="container">
            <p>Copyright © 2025. All Rights Reserved.</p>
            <p>Dikelola oleh Dinas Perindustrian dan Perdagangan Kabupaten Sidoarjo</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>