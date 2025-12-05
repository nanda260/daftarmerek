<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
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
    }
</style>
<!-- Navigasi  -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
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
                <li class="nav-item"><a class="nav-link" href="index.php#hero">HOME</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#visimisi">PROFIL</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#info">TENTANG MEREK</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#kuota">KUOTA PENDAFTARAN</a></li>
            </ul>
            <a href="login.php" class="btn btn-dark">Masuk</a>
        </div>
    </div>
</nav>

<!-- menu offcanvas utk HP -->
<div id="mobileMenu" class="mobile-menu d-lg-none">
    <ul class="navbar-nav p-3">
        <li class="nav-item"><a class="nav-link" href="index.php#hero">HOME</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#visimisi">PROFIL</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#info">TENTANG MEREK</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#kuota">KUOTA PENDAFTARAN</a></li>
        <li class="mt-3"><a href="login.php" class="btn btn-dark w-100">Masuk</a></li>
    </ul>
</div>
<div id="overlay"></div>


<div id="mobileMenu" class="mobile-menu">
    <ul class="navbar-nav p-3">
        <li class="nav-item"><a class="nav-link" href="index.php">HOME</a></li>
        <li class="nav-item"><a class="nav-link" href="#visimisi">PROFIL</a></li>
        <li class="nav-item"><a class="nav-link" href="#info">TENTANG MEREK</a></li>
        <li class="nav-item"><a class="nav-link" href="#kuota">KUOTA PENDAFTARAN</a></li>
        <li class="mt-3"><a href="login.php" class="btn btn-dark w-100">Masuk</a></li>
    </ul>
</div>
<div id="overlay"></div>

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


    function setOverlayPosition() {
        const navHeight = navbar.offsetHeight;
        mobileMenu.style.top = navHeight + 'px';
        overlay.style.top = navHeight + 'px';
        overlay.style.height = `calc(100% - ${navHeight}px)`;
    }
    setOverlayPosition();
    window.addEventListener('resize', setOverlayPosition);
</script>