<?php
$host = 'localhost';
$db   = 'attendance_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    echo "DB Connection failed: " . $e->getMessage();
    exit;
}
session_start();

// Admin login check (hardcoded for demonstration)
if (isset($_POST['login']) && $_POST['email'] === 'admin@kabacan.edu.ph' && $_POST['password'] === '7vVMx5@') {
    $_SESSION['admin_logged_in'] = true;
    header("Location: register_professor.php"); // Redirect admin to professor registration
    exit;
}
?>