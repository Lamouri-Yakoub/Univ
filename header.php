<?php
// Prevent direct access to this file
if (!defined('INCLUDE_PERMISSION')) {
    die('Direct access not permitted');
}
?>
<!-- Navigation Header -->
<header class="main-header">
    <div class="logo-container">
        <img src="images/university-logo.png" alt="شعار الجامعة">
    </div>
    
    <button class="mobile-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <nav class="main-nav" id="mainNav">
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>الرئيسية</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="professors.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'professors.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>إدارة الأساتذة</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="promotions.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'promotions.php') ? 'active' : ''; ?>">
                    <i class="fas fa-award"></i>
                    <span>ترقيات الأساتذة</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="attendance.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance.php') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>الحضور</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>التقارير</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="user-menu">
        <div class="user-info">
            <span>مرحباً، المسؤول</span>
        </div>
        <div class="dropdown">
            <button class="dropdown-toggle">
                <i class="fas fa-user-circle"></i>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="dropdown-menu">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    <span>الملف الشخصي</span>
                </a>
                <a href="settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i>
                    <span>الإعدادات</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="?logout" class="dropdown-item logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </div>
    </div>
</header>

<script>
    // Toggle mobile menu
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('mainNav').classList.toggle('open');
    });
    
    // Toggle user dropdown
    document.querySelector('.dropdown-toggle').addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelector('.dropdown-menu').classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        document.querySelector('.dropdown-menu').classList.remove('show');
    });
    
    // Prevent dropdown from closing when clicking inside it
    document.querySelector('.dropdown-menu').addEventListener('click', function(e) {
        e.stopPropagation();
    });
</script>