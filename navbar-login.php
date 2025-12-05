<?php
// Ambil contact person dan jenis usaha dari pengaturan
require_once 'process/config_db.php';

// Ambil contact person dari pengaturan
$stmt_contact = $pdo->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'contact_person'");
$stmt_contact->execute();
$contact_data = $stmt_contact->fetch(PDO::FETCH_ASSOC);
$contact_person = $contact_data ? $contact_data['setting_value'] : '6281235051286'; // Default jika tidak ada
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="icon" href="assets/img/logo.png" type="image/png">

<style>
    * {
        font-family: 'Montserrat', sans-serif;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    .navbar {
        position: fixed;
        width: 100%;
        top: 0;
        z-index: 100000;
    }

    .navbar-brand img {
        height: 40px;
    }

    .navbar-nav .nav-link {
        position: relative;
        display: inline-block;
        margin: 0 1rem;
        font-weight: 500;
        color: #161616;
        transition: color 0.3s ease;
    }

    .navbar-nav .nav-link::after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 2px;
        background-color: #161616;
        transition: width 0.3s ease;
    }

    .navbar-nav .nav-link:hover {
        color: #000;
    }

    .navbar-nav .nav-link:hover::after {
        width: 100%;
    }

    .btn-login {
        background-color: #161616;
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 500;
    }

    .btn-login:hover {
        background-color: #555;
        color: white;
    }

    /* Panel Notifikasi */
    .notif-panel {
        position: fixed;
        top: 0;
        right: -100%;
        height: 100vh;
        background: #fff;
        box-shadow: -2px 0 8px rgba(0, 0, 0, 0.2);
        z-index: 1050;
        transition: right 0.3s ease-in-out;
        overflow-y: auto;
    }

    @media (min-width: 768px) {
        .notif-panel {
            width: 25%;
        }
    }

    @media (max-width: 767.98px) {
        .notif-panel {
            width: 75%;
        }
    }

    .notif-panel.active {
        right: 0;
    }

    /* Notifikasi Item */
    .notif-item {
        padding: 12px;
        border-radius: 8px;
        transition: background-color 0.2s;
        cursor: pointer;
    }

    .notif-item:hover {
        background-color: #f8f9fa;
    }

    .notif-item.unread {
        background-color: #e7f3ff;
        border-left: 3px solid #0d6efd;
    }

    .notif-item.unread:hover {
        background-color: #d4e9ff;
    }

    .notif-empty {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }

    .notif-empty i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    /* Badge notifikasi */
    .notif-badge {
        display: none;
    }

    .notif-badge.show {
        display: inline-block;
    }

    /* Mobile Menu */
    .mobile-menu {
        position: fixed;
        top: 56px;
        right: -300px;
        width: 250px;
        height: calc(100% - 56px);
        background: #fff;
        transition: right 0.3s ease;
        z-index: 1045;
        box-shadow: -2px 0 8px rgba(0, 0, 0, 0.2);
        overflow-y: auto;
    }

    .mobile-menu.active {
        right: 0;
    }

    #overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        z-index: 1040;
        display: none;
    }

    #overlay.active {
        display: block;
    }

    .btn-notif-mobile {
        padding: 0.25rem 0.5rem;
        height: 40px;
        width: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Loading spinner */
    .notif-loading {
        text-align: center;
        padding: 20px;
    }

    .spinner-border-sm {
        width: 1.5rem;
        height: 1.5rem;
    }

    /* WhatsApp Floating Button */
    .whatsapp-float {
        position: fixed;
        bottom: 25px;
        right: 25px;
        width: 60px;
        height: 60px;
        background-color: #25D366;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 1000;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        animation: pulse 2s infinite;
    }

    .whatsapp-float:hover {
        background-color: #20BA5A;
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
        color: white;
    }

    .whatsapp-float i {
        margin-top: 2px;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
        }
        50% {
            box-shadow: 0 4px 20px rgba(37, 211, 102, 0.6);
        }
        100% {
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
        }
    }

    /* Tooltip untuk WhatsApp Button */
    .whatsapp-float::before {
        content: "Hubungi Kami";
        position: absolute;
        right: 70px;
        background-color: #333;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 14px;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
        font-family: 'Montserrat', sans-serif;
    }

    .whatsapp-float::after {
        content: "";
        position: absolute;
        right: 60px;
        border: 6px solid transparent;
        border-left-color: #333;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }

    .whatsapp-float:hover::before,
    .whatsapp-float:hover::after {
        opacity: 1;
    }

    @media (max-width: 768px) {
        .section-title {
            font-size: 1rem;
        }

        h2 {
            font-size: 1.2rem;
        }

        h3 {
            font-size: 1rem;
        }

        p, li {
            font-size: 0.7rem;
        }

        .btn-login {
            margin-top: 10px;
        }

        .whatsapp-float {
            width: 50px;
            height: 50px;
            font-size: 24px;
            bottom: 20px;
            right: 20px;
        }

        .whatsapp-float::before {
            font-size: 12px;
            padding: 6px 10px;
            right: 60px;
        }

        .whatsapp-float::after {
            right: 50px;
        }
    }
</style>

<!-- Navigasi -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="home.php">
            <img src="assets/img/logo.png" alt="Logo" class="me-2" style="height: 40px;">
            <div class="d-none d-lg-block">
                <div style="font-size: 0.8rem; font-weight: 600; color: #161616;">
                    DINAS PERINDUSTRIAN DAN PERDAGANGAN
                </div>
                <div style="font-size: 0.7rem; color: #666;">KABUPATEN SIDOARJO</div>
            </div>
        </a>

        <!-- Desktop Menu -->
        <div class="collapse navbar-collapse d-none d-lg-block" id="navbarNav">
            <ul class="navbar-nav ms-auto me-3 gap-2">
                <li class="nav-item"><a class="nav-link" href="home.php">HOME</a></li>
                <li class="nav-item"><a class="nav-link" href="editprofil.php">EDIT PROFIL PEMOHON</a></li>
            </ul>
            
            <div class="d-flex align-items-center gap-3">
                <a class="btn btn-outline-dark position-relative" id="notifBtn" style="cursor: pointer;">
                    <i class="bi bi-bell"></i>
                    <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-badge">
                        0
                    </span>
                </a>

                <a href="logout.php" class="btn btn-dark">
                    <i class="bi bi-box-arrow-left pe-1"></i> Keluar
                </a>
            </div>
        </div>

        <!-- Mobile buttons (notif & menu) -->
        <div class="d-flex d-lg-none align-items-center gap-2 ms-auto">
            <a class="btn btn-outline-dark btn-sm position-relative btn-notif-mobile" id="notifBtnMobile" style="cursor: pointer;">
                <i class="bi bi-bell"></i>
                <span id="notifBadgeMobile" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notif-badge">
                    0
                </span>
            </a>
            
            <button class="navbar-toggler" type="button" id="menuToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Menu (Offcanvas) -->
<div id="mobileMenu" class="mobile-menu d-lg-none">
    <ul class="navbar-nav p-3">
        <li class="nav-item"><a class="nav-link" href="home.php">HOME</a></li>
        <li class="nav-item"><a class="nav-link" href="editprofil.php">EDIT PROFIL PEMOHON</a></li>
        <li class="mt-3">
            <a href="logout.php" class="btn btn-dark w-100">
                <i class="bi bi-box-arrow-left pe-1"></i> Keluar
            </a>
        </li>
    </ul>
</div>

<!-- Panel Notifikasi -->
<div id="notifPanel" class="notif-panel">
    <div class="notif-header d-flex justify-content-between align-items-center p-3 border-bottom">
        <h5 class="mb-0">Notifikasi</h5>
        <button id="closeNotif" class="btn-close"></button>
    </div>
    <div class="notif-body p-3" id="notifBody">
        <!-- Loading state -->
        <div class="notif-loading">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 mb-0">Memuat notifikasi...</p>
        </div>
    </div>
</div>

<!-- WhatsApp Floating Button -->
<a href="https://wa.me/<?php echo htmlspecialchars($contact_person); ?>?text=Halo%2C%20saya%20ingin%20bertanya%20mengenai%20layanan%20industri" 
   class="whatsapp-float" 
   target="_blank" 
   rel="noopener noreferrer"
   aria-label="Hubungi via WhatsApp">
    <i class="bi bi-whatsapp"></i>
</a>

<!-- Overlay -->
<div id="overlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const menuToggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    const overlay = document.getElementById('overlay');
    const notifBtn = document.getElementById('notifBtn');
    const notifBtnMobile = document.getElementById('notifBtnMobile');
    const notifPanel = document.getElementById('notifPanel');
    const closeNotif = document.getElementById('closeNotif');
    const navbar = document.querySelector('.navbar');
    const notifBody = document.getElementById('notifBody');
    const notifBadge = document.getElementById('notifBadge');
    const notifBadgeMobile = document.getElementById('notifBadgeMobile');

    // Fungsi untuk memformat tanggal
    function formatDate(dateString) {
        const date = new Date(dateString);
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        };
        return date.toLocaleDateString('id-ID', options);
    }

    // Fungsi untuk memuat notifikasi
    async function loadNotifications() {
        try {
            const response = await fetch('process/get_notifications_suket.php');
            const data = await response.json();

            console.log('📥 Loaded notifications:', data);

            if (data.success) {
                displayNotifications(data.notifications, data.unread_count);
            } else {
                notifBody.innerHTML = `
                    <div class="notif-empty">
                        <i class="bi bi-exclamation-circle"></i>
                        <p>Gagal memuat notifikasi</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('❌ Load notification error:', error);
            notifBody.innerHTML = `
                <div class="notif-empty">
                    <i class="bi bi-wifi-off"></i>
                    <p>Tidak dapat terhubung ke server</p>
                </div>
            `;
        }
    }

    // Fungsi untuk menampilkan notifikasi
    function displayNotifications(notifications, unreadCount) {
        // Update badge
        if (unreadCount > 0) {
            notifBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            notifBadgeMobile.textContent = unreadCount > 99 ? '99+' : unreadCount;
            notifBadge.classList.add('show');
            notifBadgeMobile.classList.add('show');
        } else {
            notifBadge.classList.remove('show');
            notifBadgeMobile.classList.remove('show');
        }

        // Display notifications
        if (notifications.length === 0) {
            notifBody.innerHTML = `
                <div class="notif-empty">
                    <i class="bi bi-bell-slash"></i>
                    <p>Tidak ada notifikasi</p>
                </div>
            `;
            return;
        }

        let html = '';
        notifications.forEach((notif, index) => {
            const isUnread = notif.is_read == 0;
            const unreadClass = isUnread ? 'unread' : '';
            
            // Tentukan label berdasarkan tipe notifikasi
            let label = 'Pemberitahuan';
            
            // Cek apakah ini pengajuan surat atau pendaftaran merek
            if (notif.deskripsi && notif.deskripsi.toLowerCase().includes('surat keterangan')) {
                // Ini adalah notifikasi pengajuan surat - gunakan id_pengajuan
                label = `Pendaftaran/Pengajuan Merek #${notif.reference_id || notif.id_notif}`;
            } else if (notif.id_pendaftaran) {
                // Ini adalah notifikasi pendaftaran merek
                label = `Pendaftaran/Pengajuan Merek #${notif.id_pendaftaran}`;
            }
            
            html += `
                <div class="notif-item ${unreadClass} mb-2" 
                     data-id="${notif.id_notif}" 
                     data-read="${notif.is_read}" 
                     data-pendaftaran="${notif.id_pendaftaran || 0}">
                    <div class="d-flex">
                        <i class="bi bi-bell${isUnread ? '-fill' : ''} me-2 mt-1"></i>
                        <div class="flex-grow-1">
                            <strong>${label}</strong>
                            <p class="mb-1">${notif.deskripsi}</p>
                            <small class="text-muted fst-italic">${formatDate(notif.tgl_notif)}</small>
                        </div>
                    </div>
                </div>
                ${index < notifications.length - 1 ? '<hr class="my-2">' : ''}
            `;
        });

        notifBody.innerHTML = html;

        // Add click event dengan redirect ke lihat-pengajuan-fasilitasi.php
        document.querySelectorAll('.notif-item').forEach(item => {
            item.addEventListener('click', function() {
                const notifId = this.getAttribute('data-id');
                const isRead = this.getAttribute('data-read');
                
                console.log('🔔 Notification clicked:', { notifId });
                
                // Mark as read jika belum dibaca
                if (isRead == 0) {
                    markAsRead(notifId, this);
                }
                
                // Redirect ke halaman pengajuan fasilitasi
                console.log('➡️ Redirecting to lihat-pengajuan-fasilitasi.php');
                setTimeout(() => {
                    window.location.href = 'lihat-pengajuan-fasilitasi.php';
                }, 300);
            });
        });
    }

    // Fungsi untuk menandai notifikasi sebagai dibaca
    async function markAsRead(notifId, element) {
        try {
            const response = await fetch('process/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id_notif: notifId })
            });

            const data = await response.json();

            if (data.success) {
                element.classList.remove('unread');
                element.setAttribute('data-read', '1');
                
                // Update badge count
                const currentBadge = parseInt(notifBadge.textContent);
                const newCount = Math.max(0, currentBadge - 1);
                
                if (newCount > 0) {
                    notifBadge.textContent = newCount;
                    notifBadgeMobile.textContent = newCount;
                } else {
                    notifBadge.classList.remove('show');
                    notifBadgeMobile.classList.remove('show');
                }
                
                console.log('✅ Notification marked as read:', notifId);
            }
        } catch (error) {
            console.error('❌ Error marking notification as read:', error);
        }
    }

    // Mobile menu toggle
    menuToggle.addEventListener('click', () => {
        mobileMenu.classList.toggle('active');
        overlay.classList.toggle('active');
        notifPanel.classList.remove('active');
    });

    // Notification panel toggle (desktop)
    if (notifBtn) {
        notifBtn.addEventListener('click', () => {
            notifPanel.classList.add('active');
            overlay.classList.add('active');
            mobileMenu.classList.remove('active');
            loadNotifications();
        });
    }

    // Notification panel toggle (mobile)
    if (notifBtnMobile) {
        notifBtnMobile.addEventListener('click', () => {
            notifPanel.classList.add('active');
            overlay.classList.add('active');
            mobileMenu.classList.remove('active');
            loadNotifications();
        });
    }

    // Close notification panel
    closeNotif.addEventListener('click', () => {
        notifPanel.classList.remove('active');
        overlay.classList.remove('active');
    });

    // Overlay click to close everything
    overlay.addEventListener('click', () => {
        mobileMenu.classList.remove('active');
        notifPanel.classList.remove('active');
        overlay.classList.remove('active');
    });

    // Set mobile menu position based on navbar height
    function setMobileMenuPosition() {
        const navHeight = navbar.offsetHeight;
        mobileMenu.style.top = navHeight + 'px';
        mobileMenu.style.height = `calc(100vh - ${navHeight}px)`;
    }
    
    setMobileMenuPosition();
    window.addEventListener('resize', setMobileMenuPosition);

    // Load notifications on page load
    loadNotifications();

    // Auto-refresh notifikasi setiap 30 detik
    setInterval(() => {
        if (!notifPanel.classList.contains('active')) {
            fetch('process/get_notifications_suket.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.unread_count > 0) {
                        notifBadge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                        notifBadgeMobile.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                        notifBadge.classList.add('show');
                        notifBadgeMobile.classList.add('show');
                    }
                });
        }
    }, 30000);
</script>