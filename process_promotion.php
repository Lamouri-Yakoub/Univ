<?php
session_start();
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

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    header("Location: promotions.php");
    exit();
}

// Validate required fields
$requiredFields = ['professor_id', 'previous_grade', 'new_grade', 'effective_date', 'eligibility_date', 'dur', 'mark', 'remaining_seniority', 'total_seniority', 'activity_date'];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field])) {
        $_SESSION['error'] = "Missing required field: " . $field;
        header("Location: promotions.php");
        exit();
    }
}


$professorId = $_POST['professor_id'];
$previousGrade = (int)$_POST['previous_grade'];
$newGrade = (int)$_POST['new_grade'];
$effectiveDate = $_POST['effective_date'];
$eligibilityDate = $_POST['eligibility_date'];
$duration = $_POST['dur'];
$mark = $_POST['mark'];
$totalSeniority = $_POST['total_seniority'];
$activityDate = $_POST['activity_date'];
$remainingSeniority = $_POST['remaining_seniority'];
$note = $_POST['note'];

// Validate grades
if ($newGrade <= $previousGrade || $newGrade > 12) {
    $_SESSION['error'] = "Invalid grade progression";
    header("Location: promotions.php");
    exit();
}

// Get professor data
$stmt = $pdo->prepare("SELECT * FROM professors WHERE id = ?");
$stmt->execute([$professorId]);
$professor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$professor) {
    $_SESSION['error'] = "Professor not found";
    header("Location: promotions.php");
    exit();
}


// Calculate how many grades to promote
$gradesToPromote = $newGrade - $previousGrade;

// Define required durations for each rank
$requiredDurations = [
    'أستاذ' => 2.5,
    'محاضر' => 2.5,
    'مساعد' => 3,
];

try {
    $pdo->beginTransaction();
    
    // Calculate new grade points (assuming 84 points per grade)
    // $newGradeP = 1680 + ($newGrade * 84);
    
    // Calculate next eligibility date
    // $nextEligibilityDate = date('Y-m-d', strtotime("+".$requiredDurations[$professor['academic_rank']]." years"));
    
    // Update professor record
    $updateStmt = $pdo->prepare("
        UPDATE professors 
        SET 
            grade = :grade,
            last_promotion_date = :promotion_date
        WHERE id = :id
    ");
    
    
    if ($updateStmt->execute([
        ':grade' => $newGrade,
        ':promotion_date' => $eligibilityDate,
        ':id' => $professorId
    ])) {
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
                $fileStmt->bindParam(1, $professorId);
                $fileStmt->bindParam(2, $fileName);
                $fileStmt->bindParam(3, $fileType);
                $fileStmt->bindParam(4, $fileSize);
                $fileStmt->bindParam(5, $fileContent, PDO::PARAM_LOB);
                
                if (!$fileStmt->execute()) {
                    // File upload failed, but professor was added
                    $_SESSION['message'] = "تمت ترقية الأستاذ بنجاح ولكن حدث خطأ في رفع الملف";
                }
            }
            
            $_SESSION['message'] = "تمت ترقية الأستاذ بنجاح";
        } else {
            $error = "حدث خطأ أثناء ترقية الأستاذ";
        }
    // Log the promotion in history
    $historyStmt = $pdo->prepare("
        INSERT INTO promotion_history 
        (professor_id, previous_grade, new_grade, promotion_date, note, mark,effective_date, duration, total_seniority, activity_date, remaining_seniority) 
        VALUES (:professor_id, :previous_grade, :new_grade, :this_year, :note, :mark , :effective_date, :duration, :total_seniority, :activity_date, :remaining_seniority)
    ");
    
    $thisYear = date('Y');
    $historyStmt->execute([
        ':professor_id' => $professorId,
        ':previous_grade' => $previousGrade,
        ':new_grade' => $newGrade,
        ':this_year' => $thisYear,
        ':mark' => $mark,
        ':effective_date' => $effectiveDate,
        ':duration' => $duration,
        ':remaining_seniority' => $remainingSeniority,
        ':activity_date' => $activityDate,
        ':total_seniority' => $totalSeniority,
        ':note' => $note
    ]);
    
    $pdo->commit();
    
    $_SESSION['message'] = sprintf(
        "تمت ترقية الأستاذ %s من الدرجة %d إلى الدرجة %d بنجاح",
        htmlspecialchars($professor['first_name'] . ' ' . $professor['last_name']),
        $previousGrade,
        $newGrade
    );
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error processing promotion: " . $e->getMessage();
}

header("Location: promotions.php");
exit();
?>