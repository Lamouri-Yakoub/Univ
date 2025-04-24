<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['user_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Database connection
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
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle professor deletion
if (isset($_GET['delete'])) {
    $professorId = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM professors WHERE id = ?");
    $stmt->execute([$professorId]);
    $_SESSION['message'] = "تم حذف الأستاذ بنجاح";
    header("Location: professors.php");
    exit();
}

// Fetch all professors
$stmt = $pdo->query("SELECT * FROM professors ORDER BY last_name, first_name");
$professors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define this constant to allow header.php inclusion
define('INCLUDE_PERMISSION', true);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأساتذة - نظام إدارة الموارد البشرية</title>
    <link rel="stylesheet" href="css/icons.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/professors.css">
    <style>
        .status-badge.نشط {
    background-color: rgba(10, 110, 49, 0.1);
    color: var(--primary-green);
}

.status-badge.إجازة {
    background-color: rgba(255, 165, 0, 0.1);
    color: #ffa500;
}

.status-badge.متقاعد {
    background-color: rgba(230, 58, 58, 0.1);
    color: var(--secondary-red);
}

.status-badge.مستقيل {
    background-color: rgba(128, 128, 128, 0.1);
    color: #808080;
}

    </style>
</head>
<body>
    <div class="container">
        <!-- Include the header -->
        <?php include 'header.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1>إدارة الأساتذة</h1>
                <a href="new_professors.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> إضافة أستاذ جديد
                </a>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <div class="professors-table-container">
                <table class="professors-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الرقم الجامعي</th>
                            <th>الاسم</th>
                            <th>الاسم بالعربية</th>
                            <th>القسم</th>
                            <th>الكلية</th>
                            <th>الرتبة العلمية</th>
                            <th>تاريخ التعيين</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($professors as $index => $professor): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($professor['matricule']) ?></td>
                            <td><?= htmlspecialchars($professor['first_name'] . ' ' . $professor['last_name']) ?></td>
                            <td><?= htmlspecialchars($professor['arabic_name']) ?></td>
                            <td><?= htmlspecialchars($professor['department']) ?></td>
                            <td><?= htmlspecialchars($professor['faculty']) ?></td>
                            <td><?= htmlspecialchars($professor['academic_rank']) ?></td>
                            <td><?= date('Y-m-d', strtotime($professor['hire_date'])) ?></td>
                            <td>
                                <span class="status-badge <?= strtolower(str_replace(' ', '-', $professor['status'])) ?>">
                                    <?= htmlspecialchars($professor['status']) ?>
                                </span>
                            </td> 
                            <td class="actions">
                                <a href="view_professor.php?id=<?= $professor['id'] ?>" class="btn btn-view" title="عرض">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_professor.php?id=<?= $professor['id'] ?>" class="btn btn-edit" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="professors.php?delete=<?= $professor['id'] ?>" class="btn btn-delete" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذا الأستاذ؟');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>