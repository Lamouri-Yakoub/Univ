<?php
// get_professor_files.php
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

// Check if professor ID is provided
if (!isset($_GET['professor_id']) || empty($_GET['professor_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Professor ID is required']);
    exit();
}

$professorId = $_GET['professor_id'];

// Get files for the professor
try {
    $stmt = $pdo->prepare("SELECT id, file_name, file_type, file_size, uploaded_at FROM file_storage WHERE professor_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$professorId]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format file information
    $formattedFiles = [];
    foreach ($files as $file) {
        $formattedFiles[] = [
            'id' => $file['id'],
            'name' => $file['file_name'],
            'type' => $file['file_type'],
            'size' => $file['file_size'],
            'uploaded_at' => $file['uploaded_at']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'files' => $formattedFiles
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error retrieving files: ' . $e->getMessage()]);
}
exit();
?>