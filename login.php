<?php
include 'config/dbconnect.php';
session_start();

if (isset($_SESSION['user_logged_in'])) {
    header("Location: index.php");
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $user_type = $_POST['user_type']; // 'admin' or 'professor'

    if ($user_type === 'admin') {
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM professors WHERE email = ?");
    }
    
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_type'] = $user_type;
        $_SESSION['user_id'] = $user['id'];
        
        if ($user_type === 'admin') {
            header("Location: index.php");
        } else {
            header("Location: professor_dashboard.php");
        }
        exit();
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الموارد البشرية - جامعة 8 ماي 1945</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="images/university-logo.png" alt="شعار الجامعة" class="logo-img">
            <div class="logo-text">
                <div class="logo-text-arabic">جامعة 8 ماي 1945 قالمة</div>
                <div class="logo-text-french">Université 8 Mai 1945 Guelma</div>
            </div>
        </div>
        
        <h2>تسجيل الدخول</h2>
        
        <?php if(isset($error)): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">اسم المستخدم/البريد الإلكتروني</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group user-type-group">
                <label>نوع المستخدم:</label>
                <div class="user-type-options">
                    <label class="user-type-option">
                        <input type="radio" name="user_type" value="admin" checked>
                        <span>مسؤول النظام</span>
                    </label>
                    <label class="user-type-option">
                        <input type="radio" name="user_type" value="professor">
                        <span>أستاذ</span>
                    </label>
                </div>
            </div>
            
            <button type="submit">دخول النظام <i class="fas fa-sign-in-alt"></i></button>
        </form>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            if (!username || !password) {
                e.preventDefault();
                alert('يرجى ملء جميع الحقول');
            }
        });
    </script>
</body>
</html>