<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<style>
    * {
        font-family: 'Montserrat', sans-serif;
    }

    .navbar {
        position: fixed;
        width: 100%;
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

    /* Dropdown Styles */
    .nav-item.dropdown {
        position: relative;
    }

    .nav-item.dropdown > .nav-link::after {
        content: "\f078";
        font-family: "bootstrap-icons";
        font-size: 0.7rem;
        margin-left: 0.5rem;
        transition: content 0.3s ease;
    }

    .nav-item.dropdown:hover > .nav-link::after {
        content: "\f077";
    }

    .dropdown-menu-custom {
        position: absolute;
        top: 100%;
        left: 0;
        background: white;
        min-width: 280px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        margin-top: 0.5rem;
        z-index: 1000;
    }

    .nav-item.dropdown:hover .dropdown-menu-custom {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-menu-custom a {
        display: block;
        padding: 0.75rem 1rem;
        color: #161616;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        border-left: 3px solid transparent;
    }

    .dropdown-menu-custom a:hover {
        background-color: #f5f5f5;
        color: #000;
        border-left-color: #161616;
        padding-left: 1.2rem;
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

    .mobile-menu {
        position: fixed;
        top: 56px;
        right: -300px;
        width: 250px;
        height: 100%;
        background: #fff;
        transition: right 0.3s ease;
        z-index: 1050;
        overflow-y: auto;
    }

    .mobile-menu.active {
        right: 0;
    }

    #overlay {
        position: fixed;
        top: 56px;
        left: 0;
        width: 100%;
        height: calc(100% - 56px);
        background: rgba(0, 0, 0, 0.3);
        display: none;
        z-index: 1040;
    }

    #overlay.active {
        display: block;
    }

    .navbar-nav .nav-link {
        color: #161616;
        font-weight: 500;
    }

    /* Mobile Dropdown */
    .dropdown-toggle-mobile::after {
        content: "\f078";
        font-family: "bootstrap-icons";
        font-size: 0.7rem;
        margin-left: 0.5rem;
        display: inline-block;
    }

    .dropdown-menu-mobile {
        display: none;
        padding-left: 1rem;
    }

    .dropdown-menu-mobile.active {
        display: block;
    }

    .dropdown-menu-mobile a {
        display: block;
        padding: 0.5rem 0;
        color: #161616;
        text-decoration: none;
        font-weight: 400;
        font-size: 0.9rem;
        border-left: 2px solid transparent;
        padding-left: 1rem;
        transition: all 0.3s ease;
    }

    .dropdown-menu-mobile a:hover {
        color: #000;
        border-left-color: #161616;
        padding-left: 1.2rem;
    }

    /* Floating Settings Button - Simple */
    .floating-settings-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: #161616e7;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 1000;
        text-decoration: none;
    }

    .floating-settings-btn:hover {
        background: #333333cd;
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        color: white;
    }

    @media (max-width: 768px) {
        .floating-settings-btn {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
            bottom: 20px;
            right: 20px;
        }
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

        p,
        li {
            font-size: 0.7rem;
        }

        .btn-login {
            margin-top: 10px;
        }

        .navbar-nav .nav-link {
            margin: 0;
        }
    }
</style>

<!-- Navigasi -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center">
            <img src="assets/img/logo.png" alt="Logo" class="me-2">
            <div class="d-none d-lg-block">
                <div style="font-size: 0.8rem; font-weight: 600; color: #161616;">DINAS PERINDUSTRIAN DAN PERDAGANGAN</div>
                <div style="font-size: 0.7rem; color: #666;">KABUPATEN SIDOARJO</div>
            </div>
        </a>

        <!-- navbar mobile -->
        <button class="navbar-toggler d-lg-none" type="button" id="menuToggle">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- desktop -->
        <div class="collapse navbar-collapse d-none d-lg-block" id="navbarNav">
            <ul class="navbar-nav ms-auto me-3 gap-2">
                <li class="nav-item"><a class="nav-link" href="dashboard-admin.php">DATA PENDAFTARAN</a></li>
                <li class="nav-item"><a class="nav-link" href="pengajuan-suket.php">PENGAJUAN SUKET</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link">KELOLA DATA PENGGUNA</a>
                    <div class="dropdown-menu-custom">
                        <a href="kelola-data-pemohon.php">KELOLA DATA PEMOHON</a>
                        <a href="kelola-admin.php">KELOLA DATA ADMIN</a>
                    </div>
                </li>
            </ul>
            <a href="logout.php" class="btn btn-dark"><i class="bi bi-box-arrow-left pe-1"></i> Keluar</a>
        </div>
    </div>
</nav>

<!-- menu offcanvas utk HP -->
<div id="mobileMenu" class="mobile-menu d-lg-none">
    <ul class="navbar-nav p-3">
        <li class="nav-item"><a class="nav-link" href="dashboard-admin.php">DATA PENDAFTARAN</a></li>
        <li class="nav-item"><a class="nav-link" href="pengajuan-suket.php">PENGAJUAN SUKET</a></li>
        <li class="nav-item">
            <a class="nav-link dropdown-toggle-mobile" href="javascript:void(0)" onclick="toggleDropdown(event)">KELOLA DATA PENGGUNA</a>
            <div class="dropdown-menu-mobile">
                <a href="kelola-data-pemohon.php">KELOLA DATA PEMOHON</a>
                <a href="kelola-admin.php">KELOLA DATA ADMIN</a>
            </div>
        </li>
        <li class="mt-3"><a href="logout.php" class="btn btn-dark w-100"><i class="bi bi-box-arrow-left pe-1"></i> Keluar</a></li>
    </ul>
</div>
<div id="overlay"></div>

<!-- Floating Settings Button -->
<a href="pengaturan-admin.php" class="floating-settings-btn" title="Pengaturan">
    <i class="bi bi-gear-fill"></i>
</a>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const menuToggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    const overlay = document.getElementById('overlay');
    const navbar = document.querySelector('.navbar');

    menuToggle.addEventListener('click', () => {
        mobileMenu.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
        mobileMenu.classList.remove('active');
        overlay.classList.remove('active');
    });

    function toggleDropdown(event) {
        event.preventDefault();
        const link = event.target;
        const dropdownMenu = link.nextElementSibling;
        
        link.classList.toggle('active');
        dropdownMenu.classList.toggle('active');
    }

    function setOverlayPosition() {
        const navHeight = navbar.offsetHeight;
        mobileMenu.style.top = navHeight + 'px';
        overlay.style.top = navHeight + 'px';
        overlay.style.height = `calc(100% - ${navHeight}px)`;
    }
    setOverlayPosition();
    window.addEventListener('resize', setOverlayPosition);
</script>