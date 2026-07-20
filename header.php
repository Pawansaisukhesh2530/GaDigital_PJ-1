<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CPVIA - Advanced Statistical Programming For Global Clinical Studies">
    <title>CPVIA | Advanced Statistical Programming</title>
    <link rel="icon" type="image/webp" href="assets/images/cpvia-fav-icon.webp">
    <link rel="stylesheet" href="assets/CSS/style.css">
    <link rel="stylesheet" href="assets/CSS/home.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,500;1,600&family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
</head>

<body>
    <header class="header">
        <div class="logo">
            <a href="/" style="display: flex; align-items: center;"><img src="assets/images/header-logo.png"
                    alt="CPVIA Logo" class="header-logo"></a>
        </div>

        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>

        <nav class="nav" id="mainNav">
            <?php
            $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $current_page = trim($request_uri, '/');
            ?>
            <ul>
                <li><a href="/"
                        class="<?= ($current_page == '' || $current_page == 'index' || $current_page == 'index.php') ? 'active' : '' ?>">HOME</a>
                </li>
                <li><a href="about" class="<?= ($current_page == 'about' || $current_page == 'about.php') ? 'active' : '' ?>">ABOUT US</a></li>
                <li><a href="medicalservices"
                        class="<?= ($current_page == 'medicalservices' || $current_page == 'medicalservices.php') ? 'active' : '' ?>">MEDICAL DEVICES</a></li>
                <li><a href="expertise"
                        class="<?= ($current_page == 'expertise.php' || $current_page == 'expertise') ? 'active' : '' ?>">EXPERTISE</a></li>
                <li><a href="careers"
                        class="<?= ($current_page == 'careers.php' || $current_page == 'careers') ? 'active' : '' ?>">CAREERS</a></li>
                <li class="mobile-only-nav-item"><a href="contact"
                        class="<?= ($current_page == 'contact.php' || $current_page == 'contact') ? 'active' : '' ?>">CONTACT US</a></li>
            </ul>
        </nav>
        <a href="contact" class="btn btn-primary contact-btn">CONTACT US &gt;</a>
    </header>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mainNav = document.getElementById('mainNav');

            if (mobileMenuBtn && mainNav) {
                mobileMenuBtn.addEventListener('click', function () {
                    this.classList.toggle('active');
                    mainNav.classList.toggle('active');
                });
            }
        });
    </script>