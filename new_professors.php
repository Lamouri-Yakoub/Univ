<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['user_logged_in'])) {
    header("Location: login.php");
    exit();
}

require 'config/dbconnect.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate username availability
    $stmt = $pdo->prepare("SELECT id FROM professors WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    if ($stmt->fetch()) {
        $error = "اسم المستخدم موجود مسبقاً، يرجى اختيار اسم آخر";
    } else {
        // Collect and sanitize input data
        $data = [
            'matricule' => $_POST['matricule'],
            'username' => $_POST['username'],
            'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'arabic_name' => $_POST['arabic_name'],
            'social_security' => $_POST['social_security'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'grade' => $_POST['grade'],
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
            'status' => $_POST['status']
        ];

        // Insert into database
        $stmt = $pdo->prepare("INSERT INTO professors (
            matricule, username, password, first_name, last_name, arabic_name, social_security, email, phone, grade,
            gender, birth_date, birth_place, marital_status, children_count, address, specialization,
            hire_date, legal_status, faculty, department, academic_rank, status
        ) VALUES (
            :matricule, :username, :password, :first_name, :last_name, :arabic_name, :social_security, :email, :phone, :grade,
            :gender, :birth_date, :birth_place, :marital_status, :children_count, :address, 'lll',
            :hire_date, :legal_status, :faculty, :department, :academic_rank, :status
        )");

        if ($stmt->execute($data)) {
            $professor_id = $pdo->lastInsertId();
            
            // Handle file upload if provided
            if (!empty($_FILES['professor_file']['name'])) {
                $file = $_FILES['professor_file'];
                
                // File information
                $fileName = basename($file['name']);
                $fileType = $file['type'];
                $fileSize = $file['size'];
                $fileContent = file_get_contents($file['tmp_name']);
                
                // Insert file into database
                $fileStmt = $pdo->prepare("INSERT INTO file_storage 
                                         (professor_id, file_name, file_type, file_size, file_content) 
                                         VALUES (?, ?, ?, ?, ?)");
                $fileStmt->bindParam(1, $professor_id);
                $fileStmt->bindParam(2, $fileName);
                $fileStmt->bindParam(3, $fileType);
                $fileStmt->bindParam(4, $fileSize);
                $fileStmt->bindParam(5, $fileContent, PDO::PARAM_LOB);
                
                if (!$fileStmt->execute()) {
                    // File upload failed, but professor was added
                    $_SESSION['message'] = "تمت إضافة الأستاذ بنجاح ولكن حدث خطأ في رفع الملف";
                    header("Location: professors.php");
                    exit();
                }
            }
            
            $_SESSION['message'] = "تمت إضافة الأستاذ بنجاح";
            header("Location: professors.php");
            exit();
        } else {
            $error = "حدث خطأ أثناء إضافة الأستاذ";
        }
    }
}
define('INCLUDE_PERMISSION', true);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة أستاذ جديد - نظام إدارة الموارد البشرية</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/forms.css">
</head>
<body>
    <div class="container">
        <?php include 'header.php';  ?>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-user-plus"></i> إضافة أستاذ جديد</h1>
                <a href="professors.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> رجوع
                </a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="professor-form" id="professorForm" enctype="multipart/form-data">
                <!-- Login Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-user-lock"></i> معلومات تسجيل الدخول</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">اسم المستخدم</label>
                            <input type="text" id="username" name="username" required>
                            <small class="form-hint">سيستخدمه الأستاذ للدخول للنظام</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">كلمة المرور</label>
                            <div class="password-input">
                                <input type="password" id="password" name="password" required minlength="6">
                                <button type="button" class="toggle-password" onclick="togglePassword()">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="form-hint">يجب أن تحتوي على 6 أحرف على الأقل</small>
                            <div class="password-strength">
                                <span class="strength-bar"></span>
                                <span class="strength-text">قوة كلمة المرور</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-id-card"></i> المعلومات الشخصية</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="matricule">الرقم الجامعي</label>
                            <input type="text" id="matricule" name="matricule" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="social_security">رقم الضمان الاجتماعي</label>
                            <input type="text" id="social_security" name="social_security" required>
                        </div>
                    </div>
                </div>

                <!-- Name Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-user-tag"></i> الاسم</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">الاسم</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">اللقب</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="arabic_name">الاسم بالعربية</label>
                            <input type="text" id="arabic_name" name="arabic_name" required>
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-address-book"></i> معلومات الاتصال</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">البريد الإلكتروني</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">رقم الهاتف</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">العنوان</label>
                            <textarea id="address" name="address" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Personal Details Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-user-edit"></i> التفاصيل الشخصية</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>الجنس</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="gender" value="ذكر" checked> ذكر
                                </label>
                                <label>
                                    <input type="radio" name="gender" value="أنثى"> أنثى
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_date">تاريخ الميلاد</label>
                            <input type="date" id="birth_date" name="birth_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_place">مكان الميلاد</label>
                            <input type="text" id="birth_place" name="birth_place" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="marital_status">الحالة الاجتماعية</label>
                            <select id="marital_status" name="marital_status" required>
                                <option value="أعزب">أعزب</option>
                                <option value="متزوج">متزوج</option>
                                <option value="مطلق">مطلق</option>
                                <option value="أرمل">أرمل</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="children_count">عدد الأطفال</label>
                            <input type="number" id="children_count" name="children_count" min="0" value="0">
                        </div>
                    </div>
                </div>

                <!-- Professional Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-briefcase"></i> المعلومات الوظيفية</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="hire_date">تاريخ التعيين</label>
                            <input type="date" id="hire_date" name="hire_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="legal_status">الوضعية القانونية</label>
                            <select id="legal_status" name="legal_status" required>
                                <option value="مرسم">مرسم</option>
                                <option value="متعاقد">متعاقد</option>
                                <option value="محاضر">محاضر</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="faculty">الكلية</label>
                            <input type="text" id="faculty" name="faculty" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="department">القسم</label>
                            <input type="text" id="department" name="department" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="academic_rank">الرتبة العلمية</label>
                            <select id="academic_rank" name="academic_rank" required>
                                <option value="أستاذ">أستاذ</option>
                                <option value="محاضر قسم أ">أستاذ محاضر قسم أ</option>
                                <option value="محاضر قسم ب">أستاذ محاضر قسم ب</option>
                                <option value="مساعد قسم أ">أستاذ مساعد قسم أ</option>
                                <option value="مساعد قسم ب">أستاذ مساعد قسم ب</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">الحالة</label>
                            <select id="status" name="status" required>
                                <option value="نشط">نشط</option>
                                <option value="متقاعد">متقاعد</option>
                                <option value="إجازة">إجازة</option>
                                <option value="مستقيل">مستقيل</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade">الدرجة</label>
                            <select id="grade" name="grade" default='0'>
                                <option value="0">0</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                                <option value="9">9</option>
                                <option value="10">10</option>
                                <option value="11">11</option>
                                <option value="12">12</option>
                            </select>
                        </div>

                    </div>
                </div>
                <!-- Add this section to your form -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-file-upload"></i> رفع ملف</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="professor_file">ملف الأستاذ</label>
                            <input type="file" id="professor_file" name="professor_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="form-hint">يمكنك رفع ملفات PDF, Word, أو صور (الحد الأقصى: 5MB)</small>
                        </div>
                    </div>
                </div>
                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-add-professor">
                        <i class="fas fa-user-plus"></i> إضافة الأستاذ
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
            
            if (password.length < 6) {
                e.preventDefault();
                alert('كلمة المرور يجب أن تحتوي على 6 أحرف على الأقل');
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>