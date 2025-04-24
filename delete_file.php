<?php
// delete_file.php
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

// Check if file ID is provided
if (!isset($_GET['file_id']) || empty($_GET['file_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'File ID is required']);
    exit();
}

$fileId = $_GET['file_id'];

// Delete the file
try {
    $stmt = $pdo->prepare("DELETE FROM file_storage WHERE id = ?");
    $stmt->execute([$fileId]);
    
    if ($stmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'File not found']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error deleting file: ' . $e->getMessage()]);
}
exit();
?>