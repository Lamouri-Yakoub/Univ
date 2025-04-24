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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/'; // تأكد أن المجلد موجود وله صلاحيات الكتابة
        $fileName = uniqid() . '_' . basename($_FILES['picture']['name']);
        $targetPath = $uploadDir . $fileName;

        // نقل الملف
        if (move_uploaded_file($_FILES['picture']['tmp_name'], $targetPath)) {
            $picturePath = $targetPath;
        } else {
            $picturePath = null; // أو ضع مسار افتراضي
        }
    } else {
        $picturePath = null; // إذا لم يتم رفع صورة
    }
    // Collect and sanitize input data
    $data = [
        'id' => $_POST['id'],
        'matricule' => $_POST['matricule'],
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name'],
        'arabic_name' => $_POST['arabic_name'],
        'social_security' => $_POST['social_security'],
        'email' => $_POST['email'],
        'phone' => $_POST['phone'],
        'gender' => $_POST['gender'],
        'birth_date' => $_POST['birth_date'],
        'birth_place' => $_POST['birth_place'],
        'marital_status' => $_POST['marital_status'],
        'children_count' => $_POST['children_count'],
        'address' => $_POST['address'],
        'hire_date' => $_POST['hire_date'],
        'legal_status' => $_POST['legal_status'],
        'faculty' => $_POST['faculty'],
        'department' => $_POST['department'],
        'academic_rank' => $_POST['academic_rank'],
        'picture' => $picturePath,
        'status' => $_POST['status']
    ];

    // Update password if provided
    if (!empty($_POST['password'])) {
        $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $passwordUpdate = ", password = :password";
    } else {
        $passwordUpdate = "";
    }

    // Update database
    $stmt = $pdo->prepare("UPDATE professors SET
        matricule = :matricule,
        first_name = :first_name,
        last_name = :last_name,
        arabic_name = :arabic_name,
        social_security = :social_security,
        email = :email,
        phone = :phone,
        gender = :gender,
        birth_date = :birth_date,
        birth_place = :birth_place,
        marital_status = :marital_status,
        children_count = :children_count,
        address = :address,
        hire_date = :hire_date,
        legal_status = :legal_status,
        faculty = :faculty,
        department = :department,
        academic_rank = :academic_rank,
        picture = :picture,
        status = :status
        {$passwordUpdate}
        WHERE id = :id");

    if ($stmt->execute($data)) {
        $_SESSION['message'] = "تم تحديث بيانات الأستاذ بنجاح";
        header("Location: professors.php");
        exit();
    } else {
        $error = "حدث خطأ أثناء تحديث بيانات الأستاذ";
    }
}

// Get professor data
if (!isset($_GET['id'])) {
    header("Location: professors.php");
    exit();
}

$professorId = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM professors WHERE id = ?");
$stmt->execute([$professorId]);
$professor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$professor) {
    header("Location: professors.php");
    exit();
}

define('INCLUDE_PERMISSION', true);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل بيانات الأستاذ - نظام إدارة الموارد البشرية</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/forms.css">
    <link rel="stylesheet" href="css/edit_professor.css">
</head>
<body>
    <div class="container">
        <?php include 'header.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-user-edit"></i> تعديل بيانات الأستاذ</h1>
                <a href="professors.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> رجوع
                </a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="professor-form" id="professorForm">
                <input type="hidden" name="id" value="<?= $professor['id'] ?>">

                <!-- Name Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-id-card"></i> المعلومات الشخصية</h2>
                    <div class="form-grid">
                        <div class="file-input-wrapper">
                            <label class="file-input-label">
                                <i class="fas fa-upload"></i> اختر صورة
                                <input type="file" id="picture" name="picture" accept="image/*" onchange="previewImage(this)">
                            </label>
                            <small class="form-hint">الصيغ المسموحة: JPG, PNG, GIF (الحد الأقصى 2MB)</small>

                        </div>
                        
                        <div class="form-group">
                            <label for="first_name">الاسم</label>
                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($professor['first_name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">اللقب</label>
                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($professor['last_name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="arabic_name">الاسم بالعربية</label>
                            <input type="text" id="arabic_name" name="arabic_name" value="<?= htmlspecialchars($professor['arabic_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>الجنس</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="gender" value="ذكر" <?= $professor['gender'] === 'ذكر' ? 'checked' : '' ?>> ذكر
                                </label>
                                <label>
                                    <input type="radio" name="gender" value="أنثى" <?= $professor['gender'] === 'أنثى' ? 'checked' : '' ?>> أنثى
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_date">تاريخ الميلاد</label>
                            <input type="date" id="birth_date" name="birth_date" value="<?= htmlspecialchars($professor['birth_date']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_place">مكان الميلاد</label>
                            <input type="text" id="birth_place" name="birth_place" value="<?= htmlspecialchars($professor['birth_place']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="marital_status">الحالة الاجتماعية</label>
                            <select id="marital_status" name="marital_status" required>
                                <option value="أعزب" <?= $professor['marital_status'] === 'أعزب' ? 'selected' : '' ?>>أعزب</option>
                                <option value="متزوج" <?= $professor['marital_status'] === 'متزوج' ? 'selected' : '' ?>>متزوج</option>
                                <option value="مطلق" <?= $professor['marital_status'] === 'مطلق' ? 'selected' : '' ?>>مطلق</option>
                                <option value="أرمل" <?= $professor['marital_status'] === 'أرمل' ? 'selected' : '' ?>>أرمل</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="children_count">عدد الأطفال</label>
                            <input type="number" id="children_count" name="children_count" min="0" value="<?= htmlspecialchars($professor['children_count']) ?>">
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-address-book"></i> معلومات الاتصال</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">البريد الإلكتروني</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($professor['email']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">رقم الهاتف</label>
                            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($professor['phone']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">العنوان</label>
                            <textarea id="address" name="address" rows="2"><?= htmlspecialchars($professor['address']) ?></textarea>
                        </div>
                    </div>
                </div>

                
                <!-- Professional Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-briefcase"></i> المعلومات الوظيفية</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="matricule">الرقم الجامعي</label>
                            <input type="text" id="matricule" name="matricule" value="<?= htmlspecialchars($professor['matricule']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="social_security">رقم الضمان الاجتماعي</label>
                            <input type="text" id="social_security" name="social_security" value="<?= htmlspecialchars($professor['social_security']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="hire_date">تاريخ التعيين</label>
                            <input type="date" id="hire_date" name="hire_date" value="<?= htmlspecialchars($professor['hire_date']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="legal_status">الوضعية القانونية</label>
                            <select id="legal_status" name="legal_status" required>
                                <option value="مرسم" <?= $professor['legal_status'] === 'مرسم' ? 'selected' : '' ?>>مرسم</option>
                                <option value="متعاقد" <?= $professor['legal_status'] === 'متعاقد' ? 'selected' : '' ?>>متعاقد</option>
                                <option value="محاضر" <?= $professor['legal_status'] === 'محاضر' ? 'selected' : '' ?>>محاضر</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="faculty">الكلية</label>
                            <input type="text" id="faculty" name="faculty" value="<?= htmlspecialchars($professor['faculty']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="department">القسم</label>
                            <input type="text" id="department" name="department" value="<?= htmlspecialchars($professor['department']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="academic_rank">الرتبة العلمية</label>
                            <select id="academic_rank" name="academic_rank" required>
                                <option value="أستاذ" <?= $professor['academic_rank'] === 'أستاذ' ? 'selected' : '' ?>>أستاذ</option>
                                <option value="أستاذ محاضر" <?= $professor['academic_rank'] === 'أستاذ محاضر' ? 'selected' : '' ?>>أستاذ محاضر</option>
                                <option value="محاضر" <?= $professor['academic_rank'] === 'محاضر' ? 'selected' : '' ?>>محاضر</option>
                                <option value="مساعد" <?= $professor['academic_rank'] === 'مساعد' ? 'selected' : '' ?>>مساعد</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select id="status" name="status" required>
                                <option value="نشط" <?= $professor['status'] === 'نشط' ? 'selected' : '' ?>>نشط</option>
                                <option value="متقاعد" <?= $professor['status'] === 'متقاعد' ? 'selected' : '' ?>>متقاعد</option>
                                <option value="إجازة" <?= $professor['status'] === 'إجازة' ? 'selected' : '' ?>>إجازة</option>
                                <option value="مستقيل" <?= $professor['status'] === 'مستقيل' ? 'selected' : '' ?>>مستقيل</option>
                            </select>
                        </div>
                    </div>
                </div>
                <!-- Password Update Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-key"></i> تحديث كلمة المرور</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="password">كلمة المرور الجديدة</label>
                            <div class="password-input">
                                <input type="password" id="password" name="password" minlength="6">
                                <button type="button" class="toggle-password" onclick="togglePassword()">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="form-hint">اتركه فارغاً إذا كنت لا تريد تغيير كلمة المرور</small>
                            <div class="password-strength">
                                <span class="strength-bar"></span>
                                <span class="strength-text">قوة كلمة المرور</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-save"></i> حفظ التغييرات
                    </button>
                    <button type="reset" class="btn btn-reset">
                        <i class="fas fa-undo"></i> إعادة تعيين
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleBtn.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.querySelector('.strength-bar');
            const strengthText = document.querySelector('.strength-text');
            
            // Reset classes
            strengthBar.className = 'strength-bar';
            strengthText.className = 'strength-text';
            
            if (password.length === 0) {
                strengthText.textContent = 'قوة كلمة المرور';
                return;
            }
            
            if (password.length < 6) {
                strengthBar.classList.add('weak');
                strengthText.textContent = 'ضعيفة';
                strengthText.classList.add('weak');
            } else if (password.length < 10) {
                strengthBar.classList.add('medium');
                strengthText.textContent = 'متوسطة';
                strengthText.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
                strengthText.textContent = 'قوية';
                strengthText.classList.add('strong');
            }
        });

        // Form validation
        document.getElementById('professorForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            
            if (password.length > 0 && password.length < 6) {
                e.preventDefault();
                alert('كلمة المرور يجب أن تحتوي على 6 أحرف على الأقل');
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>