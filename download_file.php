<?php
// download_file.php
session_start();
if (!isset($_SESSION['user_logged_in'])) {
    header('Location: login.php');
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

// Check if file ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("File ID is required");
}

$fileId = $_GET['id'];

// Get file data
try {
    $stmt = $pdo->prepare("SELECT file_name, file_type, file_content FROM file_storage WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        die("File not found");
    }
    
    // Set headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $file['file_type']);
    header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($file['file_content']));
    
    // Output file content
    echo $file['file_content'];
    exit();
} catch (PDOException $e) {
    die("Error retrieving file: " . $e->getMessage());
}
?>