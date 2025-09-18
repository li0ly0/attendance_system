<?php
require 'db.php';
if(!isset($_SESSION['professor_id'])) header("Location: index.php");

// Fetch subjects for filter dropdown
$subjectStmt = $pdo->prepare("SELECT * FROM subjects WHERE professor_id=?");
$subjectStmt->execute([$_SESSION['professor_id']]);
$subjects = $subjectStmt->fetchAll();

// Initialize filter variables from GET parameters
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$subjectFilter = $_GET['subject'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build base query and parameters
$query = "SELECT a.*, s.name AS student_name, sub.name AS subject_name
          FROM attendance_records a
          JOIN students s ON a.student_id = s.student_id
          JOIN subjects sub ON a.subject_id = sub.id
          WHERE s.professor_id = ?";
$params = [$_SESSION['professor_id']];

// Add filters dynamically
if ($startDate) {
    $query .= " AND DATE(a.scan_time) >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $query .= " AND DATE(a.scan_time) <= ?";
    $params[] = $endDate;
}
if ($subjectFilter) {
    $query .= " AND a.subject_id = ?";
    $params[] = $subjectFilter;
}
if ($statusFilter) {
    $query .= " AND a.status = ?";
    $params[] = $statusFilter;
}
if ($search) {
    $query .= " AND (s.student_id LIKE ? OR s.name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY a.scan_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();
?>

<h2>Attendance Records</h2>

<form method="GET" style="margin-bottom:20px;">
    <label>
        Start Date:
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
    </label>
    &nbsp;
    <label>
        End Date:
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
    </label>
    &nbsp;
    <label>
        Subject:
        <select name="subject">
            <option value="">-- All Subjects --</option>
            <?php foreach($subjects as $sub): ?>
                <option value="<?= $sub['id'] ?>" <?= ($subjectFilter == $sub['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sub['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    &nbsp;
    <label>
        Status:
        <select name="status">
            <option value="">-- All Statuses --</option>
            <option value="Present" <?= ($statusFilter == 'Present') ? 'selected' : '' ?>>Present</option>
            <option value="Late" <?= ($statusFilter == 'Late') ? 'selected' : '' ?>>Late</option>
            <option value="Absent" <?= ($statusFilter == 'Absent') ? 'selected' : '' ?>>Absent</option>
        </select>
    </label>
    &nbsp;
    <label>
        Search (ID or Name):
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Student ID or Name">
    </label>
    &nbsp;
    <button type="submit">Filter</button>
    &nbsp;
    <a href="<?= basename(__FILE__) ?>">Reset</a>
</form>

<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">
    <thead>
        <tr>
            <th>Student ID</th>
            <th>Name</th>
            <th>Subject</th>
            <th>Scan Time</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    <?php if (count($records) === 0): ?>
        <tr><td colspan="5" style="text-align:center;">No records found.</td></tr>
    <?php else: ?>
        <?php foreach($records as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['student_id']) ?></td>
            <td><?= htmlspecialchars($r['student_name']) ?></td>
            <td><?= htmlspecialchars($r['subject_name']) ?></td>
            <td><?= htmlspecialchars($r['scan_time']) ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<a href="dashboard.php" style="display:inline-block; margin-top: 15px;">Back to Dashboard</a>
