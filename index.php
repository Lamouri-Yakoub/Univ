<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['user_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Database connection (same as login.php)
$host = 'localhost';
$dbname = 'university_hr';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION['user_logged_in'] = false;
    session_destroy();
    header("Location: login.php");
    exit();
}

// Define this constant to allow header.php inclusion
define('INCLUDE_PERMISSION', true);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - نظام إدارة الموارد البشرية</title>
    <link rel="stylesheet" href="css/icons.css">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="container">
        <?php include 'header.php'; ?>

        <main class="main-content">
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3 class="stat-title">إجمالي الأساتذة</h3>
                    <p class="stat-value">53</p>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-title">الإجازات هذا الشهر</h3>
                    <p class="stat-value">8</p>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-title">الترقيات هذا العام</h3>
                    <p class="stat-value">7</p>
                    <div class="stat-sub">(من أصل 23 طلب)</div>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-title">العقود المنتهية</h3>
                    <p class="stat-value">2</p>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <h2>النشاط الأخير</h2>
                <ul class="activity-list">
                    <li class="activity-item">
                        <i class="fas fa-user-plus"></i>
                        تمت إضافة أستاذ جديد - د. أحمد محمد
                    </li>
                    <li class="activity-item">
                        <i class="fas fa-file-signature"></i>
                        تجديد عقد د. فاطمة الزهراء
                    </li>
                    <li class="activity-item">
                        <i class="fas fa-calendar-times"></i>
                        تسجيل غياب د. محمد علي - 15/04/2025
                    </li>
                    <li class="activity-item">
                        <i class="fas fa-file-alt"></i>
                        تحديث بيانات د. نور حسن
                    </li>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>