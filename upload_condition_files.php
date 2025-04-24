<?php
// upload_condition_files.php
session_start();
if (!isset($_SESSION['user_logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Check if files were uploaded
if (!isset($_FILES['condition_files']) || empty($_FILES['condition_files']['name'][0])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No files uploaded']);
    exit();
}

// Check if professor ID is provided
if (!isset($_POST['professor_id']) || empty($_POST['professor_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Professor ID is required']);
    exit();
}

$professorId = $_POST['professor_id'];
$uploadedFiles = [];

// Process each uploaded file
for ($i = 0; $i < count($_FILES['condition_files']['name']); $i++) {
    $fileName = $_FILES['condition_files']['name'][$i];
    $fileType = $_FILES['condition_files']['type'][$i];
    $fileSize = $_FILES['condition_files']['size'][$i];
    $fileTmpName = $_FILES['condition_files']['tmp_name'][$i];
    $fileError = $_FILES['condition_files']['error'][$i];
    
    // Check for errors
    if ($fileError !== UPLOAD_ERR_OK) {
        continue;
    }
    
    // Read file content
    $fileContent = file_get_contents($fileTmpName);
    
    // Insert into database
    try {
        $stmt = $pdo->prepare("INSERT INTO file_storage (professor_id, file_name, file_type, file_size, file_content, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$professorId, $fileName, $fileType, $fileSize, $fileContent]);
        
        $fileId = $pdo->lastInsertId();
        
        $uploadedFiles[] = [
            'id' => $fileId,
            'name' => $fileName,
            'type' => $fileType,
            'size' => $fileSize
        ];
    } catch (PDOException $e) {
        // Log error but continue with other files
        error_log('Error storing file: ' . $e->getMessage());
    }
}

// Return success response with file information
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'message' => 'Files uploaded successfully',
    'files' => $uploadedFiles
]);
exit();
?>