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

// Get professor ID from URL
if (!isset($_GET['id'])) {
    header("Location: professors.php");
    exit();
}

$professorId = $_GET['id'];

// Fetch professor details
$stmt = $pdo->prepare("SELECT * FROM professors WHERE id = ?");
$stmt->execute([$professorId]);
$professor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$professor) {
    header("Location: professors.php");
    exit();
}

// Fetch professor files
$filesStmt = $pdo->prepare("SELECT * FROM file_storage WHERE professor_id = ?");
$filesStmt->execute([$professorId]);
$files = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to format file sizes
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

// Define this constant to allow header.php inclusion
define('INCLUDE_PERMISSION', true);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الأستاذ - نظام إدارة الموارد البشرية</title>
    <link rel="stylesheet" href="css/view_professor.css">
    <link rel="stylesheet" href="css/icons.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/photo_profile.css">
    <style>
        /* Files Section Styles */
.files-section {
    margin-top: 30px;
}

.files-list {
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.files-section .files-table {
    width: 100%;
    border-collapse: collapse;
}

.files-table th, .files-table td {
    padding: 12px 15px;
    text-align: right;
    border-bottom: 1px solid #ddd;
}

.files-table th {
    background-color: #f5f5f5;
    font-weight: 600;
}

.files-table tr:hover {
    background-color: #f9f9f9;
}

.actions {
    display: flex;
    gap: 10px;
}

.btn-download, .btn-delete {
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
}

.btn-download {
    background-color: #4CAF50;
    color: white;
}

.btn-delete {
    background-color: #f44336;
    color: white;
}

.no-files {
    padding: 20px;
    text-align: center;
    color: #777;
}

.form-hint {
    display: block;
    color: #666;
    font-size: 13px;
    margin-top: 5px;
}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include the header -->
        <?php include 'header.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-main">
            <h1 class="dashboard-title">الملف الشخصي للأستاذ</h1>
            
            <div class="employee-profile">
                
                <div class="profile-section">
                    <h2><i class="fas fa-user"></i> المعلومات الشخصية</h2>
                    <div class="info-row">
                        <span class="label">الاسم:</span>
                        <span class="value"><?= htmlspecialchars($professor['first_name'] . ' ' . $professor['last_name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">الاسم بالعربية:</span>
                        <span class="value"><?= htmlspecialchars($professor['arabic_name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">الرقم الجامعي:</span>
                        <span class="value"><?= htmlspecialchars($professor['matricule']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">اسم المستخدم:</span>
                        <span class="value"><?= htmlspecialchars($professor['username']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">البريد الإلكتروني:</span>
                        <span class="value"><?= htmlspecialchars($professor['email']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">رقم الهاتف:</span>
                        <span class="value"><?= htmlspecialchars($professor['phone']) ?></span>
                    </div>
                    <div class="photo-container">
                        <?php if (!empty($professor['picture'])): ?>
                            <img src="<?= htmlspecialchars($professor['picture']) ?>" alt="صورة الأستاذ" class="professor-image">
                        <?php else: ?>
                            <div class="default-photo">
                                <i class="fas fa-user-circle"></i>
                                <span>لا توجد صورة</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-section">
                    <h2><i class="fas fa-graduation-cap"></i> المعلومات الأكاديمية</h2>
                    <div class="info-row">
                        <span class="label">الكلية:</span>
                        <span class="value"><?= htmlspecialchars($professor['faculty']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">القسم:</span>
                        <span class="value"><?= htmlspecialchars($professor['department']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">التخصص:</span>
                        <span class="value"><?= htmlspecialchars($professor['specialization']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">الرتبة العلمية:</span>
                        <span class="value"><?= htmlspecialchars($professor['academic_rank']) ?></span>
                    </div>
                </div>

                <div class="profile-section">
                    <h2><i class="fas fa-briefcase"></i> المعلومات الوظيفية</h2>
                    <div class="info-row">
                        <span class="label">تاريخ التعيين:</span>
                        <span class="value"><?= date('d/m/Y', strtotime($professor['hire_date'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">الحالة الوظيفية:</span>
                        <span class="value status-badge <?= strtolower(str_replace(' ', '-', $professor['status'])) ?>">
                            <?= htmlspecialchars($professor['status']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">تاريخ الإنشاء:</span>
                        <span class="value"><?= date('d/m/Y H:i', strtotime($professor['created_at'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">آخر تحديث:</span>
                        <span class="value"><?= date('d/m/Y H:i', strtotime($professor['updated_at'])) ?></span>
                    </div>
                </div>
                <!-- Files Section -->
                <div class="profile-section files-section">
                    <h2><i class="fas fa-file-alt"></i> ملفات الأستاذ</h2>
                    
                    <!-- Files List -->
                    <div class="files-list">
                        <?php if (empty($files)): ?>
                            <div class="no-files">لا توجد ملفات مرفوعة لهذا الأستاذ</div>
                        <?php else: ?>
                            <table class="files-table">
                                <thead>
                                    <tr>
                                        <th>اسم الملف</th>
                                        <th>النوع</th>
                                        <th>الحجم</th>
                                        <th>تاريخ الرفع</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $file): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($file['file_name']) ?></td>
                                            <td><?= htmlspecialchars($file['file_type']) ?></td>
                                            <td><?= formatFileSize($file['file_size']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($file['uploaded_at'])) ?></td>
                                            <td class="actions">
                                                <a href="download_file.php?id=<?= $file['id'] ?>" class="btn btn-download" title="تحميل">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>