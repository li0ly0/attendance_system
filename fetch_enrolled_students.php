<?php
require 'db.php';
if(!isset($_SESSION['professor_id'])) {
    echo json_encode(['success'=>false, 'msg'=>'Unauthorized']);
    exit;
}

if(!isset($_GET['subject_id'])){
    echo json_encode(['success'=>false, 'msg'=>'Missing subject_id']);
    exit;
}

$subject_id = (int)$_GET['subject_id'];

// Verify subject belongs to professor
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE id=? AND professor_id=?");
$stmt->execute([$subject_id, $_SESSION['professor_id']]);
$subject = $stmt->fetch();
if(!$subject){
    echo json_encode(['success'=>false, 'msg'=>'Subject not found']);
    exit;
}

// Fetch enrolled students
$stmt = $pdo->prepare("SELECT s.* FROM students s JOIN subject_enrollments se ON s.id=se.student_id WHERE se.subject_id=? AND s.is_active=1");
$stmt->execute([$subject_id]);
$enrolled = $stmt->fetchAll();

echo json_encode(['success'=>true, 'enrolled'=>$enrolled]);
exit;
?>
