<?php
// Optional while debugging (remove on production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

require 'db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['professor_id'])) {
    header("Location: index.php");
    exit;
}

// Load Composer autoloader for PHPMailer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    error_log('vendor/autoload.php not found. PHPMailer will not work.');
    die('Required dependencies not found. Please run composer install.');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Helper function to get subject name by ID
function getSubjectNameById(PDO $pdo, int $subjectId): ?string {
    $stmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
    $stmt->execute([$subjectId]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
    return $subject ? $subject['name'] : null;
}

/**
 * Send attendance emails via SMTP using PHPMailer (preferred),
 * falls back to native mail() if PHPMailer isn't available or fails.
 */
function sendAttendanceEmails(array $recipients, string $studentName, string $subjectName, string $status, string $scanDateTime): array
{
    $recipients = array_values(array_unique(array_filter($recipients, function ($e) {
        return filter_var($e, FILTER_VALIDATE_EMAIL);
    })));

    if (empty($recipients)) {
        error_log('No valid email recipients.');
        return ['ok' => false, 'error' => 'No valid email recipients'];
    }

    error_log('Sending email to: ' . implode(', ', $recipients));

    $subject = "Attendance Notification for {$studentName}";

$text = "This is to inform you that the attendance record for {$studentName} "
      . "in the subject \"{$subjectName}\" has been marked as: {$status}.\n"
      . "Date and Time: {$scanDateTime}\n\n"
      . "Best regards,\nKabacan College";

$html = "<p>This is to inform you that the attendance record for <strong>{$studentName}</strong> "
      . "in the subject <strong>\"{$subjectName}\"</strong> has been marked as: <strong>{$status}</strong>.</p>"
      . "<p>Date and Time: {$scanDateTime}</p>"
      . "<p>Best regards,<br>Kabacan National High School</p>";

    if (class_exists(PHPMailer::class)) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'beverlycionrespecia@gmail.com';
            $mail->Password   = 'cvkk ppme lkxy bjll'; // App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('beverlycionrespecia@gmail.com', 'Attendance Monitoring');
            $mail->addReplyTo('beverlycionrespecia@gmail.com', 'Attendance Monitoring');

            foreach ($recipients as $email) {
                $mail->addAddress($email);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text;

            $mail->send();
            error_log('Email sent successfully.');
            return ['ok' => true, 'sent' => $recipients];
        } catch (Exception $e) {
            error_log('PHPMailer SMTP failed: ' . $mail->ErrorInfo);
            error_log('Exception message: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    error_log('PHPMailer class not found, falling back to mail()');

    $headers  = "From: Attendance Monitoring <no-reply@yourschool.edu>\r\n";
    $headers .= "Reply-To: no-reply@yourschool.edu\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $failed = [];
    foreach ($recipients as $email) {
        if (!mail($email, $subject, $text, $headers)) {
            $failed[] = $email;
            error_log("mail() failed for: {$email}");
        }
    }

    if (empty($failed)) {
        return ['ok' => true, 'sent' => $recipients];
    }
    return ['ok' => false, 'failed' => $failed, 'error' => 'mail() failed for some recipients'];
}


// Start scan session
if (isset($_POST['start_scan'])) {
    $subject_id = $_POST['subject_id'];
    $_SESSION['scan_subject'] = $subject_id;
    $_SESSION['scan_start_time'] = time();
    $_SESSION['scanned_students'] = [];

    // Fetch and store subject name in session
    $_SESSION['scan_subject_name'] = getSubjectNameById($pdo, (int)$subject_id) ?? 'Unknown Subject';
}

// Stop scan session and mark absences with email notification
if (isset($_POST['stop_scan']) && isset($_SESSION['scan_subject'])) {
    $subject_id = $_SESSION['scan_subject'];
    $scanned_students = $_SESSION['scanned_students'] ?? [];

    // Get all enrolled active students with their emails and guardian emails
    $stmt = $pdo->prepare("SELECT s.student_id, s.name, s.email, s.guardian_email FROM students s
        JOIN subject_enrollments se ON s.id = se.student_id
        WHERE se.subject_id = ? AND s.is_active = 1");
    $stmt->execute([$subject_id]);
    $enrolled_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $now = date('Y-m-d H:i:s');
    $insertStmt = $pdo->prepare("INSERT INTO attendance_records (student_id, subject_id, scan_time, status) VALUES (?, ?, ?, ?)");
    $checkStmt = $pdo->prepare("SELECT 1 FROM attendance_records WHERE student_id = ? AND subject_id = ? AND DATE(scan_time) = CURDATE()");

    // Get subject name once
    $subjectName = getSubjectNameById($pdo, (int)$subject_id) ?? 'Unknown Subject';

    foreach ($enrolled_students as $student) {
        if (!in_array($student['student_id'], $scanned_students, true)) {
            // Mark absent if not scanned
            $checkStmt->execute([$student['student_id'], $subject_id]);
            if (!$checkStmt->fetch()) {
                $insertStmt->execute([$student['student_id'], $subject_id, $now, 'Absent']);
            }

            // Prepare recipient emails
            $toEmails = [];
            if (!empty($student['email'])) {
                $toEmails[] = $student['email'];
            }
            if (!empty($student['guardian_email'])) {
                $toEmails[] = $student['guardian_email'];
            }

            if (!empty($toEmails)) {
                // Send absence email with subject name
                $emailResult = sendAttendanceEmails(
                    $toEmails,
                    $student['name'],
                    $subjectName,
                    'Absent',
                    $now
                );
                if (!$emailResult['ok']) {
                    error_log('Absence email failed for student_id ' . $student['student_id'] . ': ' . ($emailResult['error'] ?? 'unknown error'));
                }
            }
        }
    }

    unset($_SESSION['scan_subject'], $_SESSION['scan_start_time'], $_SESSION['scanned_students'], $_SESSION['scan_subject_name']);
}

// Record scan via AJAX
if (isset($_POST['ajax_scan']) && isset($_SESSION['scan_subject'])) {
    $student_id = $_POST['student_id'];
    $subject_id = $_SESSION['scan_subject'];
    $scan_time = time();
    $start_time = $_SESSION['scan_start_time'];

    if (in_array($student_id, $_SESSION['scanned_students'], true)) {
        echo json_encode(['success' => false, 'msg' => 'Student already scanned']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id=? AND is_active=1");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'msg' => 'Invalid or inactive student ID']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM subject_enrollments WHERE subject_id=? AND student_id=?");
    $stmt->execute([$subject_id, $student['id']]); // uses internal numeric id for enrollment check
    $enrolled = $stmt->fetchColumn();

    if (!$enrolled) {
        echo json_encode(['success' => false, 'msg' => 'You are not enrolled in this subject.']);
        exit;
    }

    $status = ($scan_time - $start_time <= 900) ? 'Present' : 'Late';

    // Optional: prevent duplicate record for same day
    $dup = $pdo->prepare("SELECT 1 FROM attendance_records WHERE student_id=? AND subject_id=? AND DATE(scan_time)=CURDATE()");
    $dup->execute([$student_id, $subject_id]);
    if (!$dup->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO attendance_records (student_id, subject_id, scan_time, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$student_id, $subject_id, date('Y-m-d H:i:s', $scan_time), $status]);
    }

    $_SESSION['scanned_students'][] = $student_id;

    // Build recipient list: student and guardian emails
    $toEmails = [];
    if (!empty($student['email'])) {
        $toEmails[] = $student['email'];
    }
    if (!empty($student['guardian_email'])) {
        $toEmails[] = $student['guardian_email'];
    }

    if (!empty($toEmails)) {
        $scanDateTime = date('Y-m-d H:i:s', $scan_time);
        $subjectName = getSubjectNameById($pdo, (int)$subject_id) ?? 'Unknown Subject';

        $emailResult = sendAttendanceEmails($toEmails, $student['name'], $subjectName, $status, $scanDateTime);
        if (!$emailResult['ok']) {
            error_log('Attendance email failed for student_id ' . $student_id . ': ' . ($emailResult['error'] ?? 'unknown error'));
        }
    }

    echo json_encode(['success' => true, 'status' => $status, 'student' => $student['name']]);
    exit;
}

$subjects = $pdo->prepare("SELECT * FROM subjects WHERE professor_id=?");
$subjects->execute([$_SESSION['professor_id']]);
$subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Attendance Scanner</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  :root { --bg-soft: #faf9f6; }
  ::-webkit-scrollbar { width: 8px; height: 8px; }
  ::-webkit-scrollbar-track { background: rgba(2,6,23,.06); }
  ::-webkit-scrollbar-thumb { background: #dc2626; border-radius: 9999px; }
  ::-webkit-scrollbar-thumb:hover { background: #ca8a04; }
  #previewContainer { position: relative; width: 320px; max-width: 100%; aspect-ratio: 1 / 1; border-radius: 0.75rem; overflow: hidden; box-shadow: 0 0 12px rgb(220 38 38 / 0.3); }
  #preview { width: 100%; height: 100%; object-fit: cover; }
  #scanGuide { position: absolute; top: 50%; left: 50%; width: 180px; height: 180px; border: 3px solid #b45309; border-radius: 0.5rem; transform: translate(-50%, -50%); pointer-events: none; box-shadow: 0 0 12px 3px rgb(180 83 9 / 0.5); }
  #scanResult { min-height: 1.5rem; }
  #subjectSuggestions { position: absolute; z-index: 50; width: 100%; max-height: 12rem; overflow-y: auto; background: white; border: 1px solid #e5e7eb; border-radius: 0.75rem; box-shadow: 0 4px 8px rgb(0 0 0 / 0.1); list-style: none; margin-top: 0.25rem; padding-left: 0; }
  #subjectSuggestions li { padding: 0.5rem 1rem; cursor: pointer; transition: background-color 0.15s ease-in-out; }
  #subjectSuggestions li:hover { background-color: #fde68a; }
</style>
</head>
<body class="bg-[var(--bg-soft)] text-slate-800 antialiased font-sans min-h-screen">
  <div class="min-h-screen flex">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-72 bg-white/90 backdrop-blur border-r border-slate-200 transform transition-transform duration-200 ease-out -translate-x-full md:translate-x-0">
      <div class="h-16 flex items-center gap-3 px-6 border-b border-slate-200">
        <img src="./school.png" alt="Logo" class="h-9 w-9 rounded-md object-cover" />
        <div class="font-semibold text-slate-900 tracking-tight">Attendance Portal</div>
      </div>

      <nav class="px-3 py-4 space-y-2">
        <div class="text-[11px] uppercase tracking-wider text-slate-400 px-3 mt-2">Overview</div>

        <a href="./dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-slate-600 hover:text-red-700 hover:bg-yellow-50 transition">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M4 10v10h6v-6h4v6h6V10"/>
          </svg>
          Dashboard
        </a>

        <a href="scan.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-bold text-red-700 bg-yellow-50 ring-1 ring-inset ring-red-200" aria-current="page">
          <svg class="w-5 h-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <rect x="3" y="4" width="18" height="16" rx="2" stroke-width="2"/>
            <path d="M3 10h18M7 16h0" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Scanner
        </a>

        <a href="subjects.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-slate-600 hover:text-red-700 hover:bg-yellow-50 transition">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <rect x="3" y="4" width="18" height="16" rx="2" stroke-width="2"/>
            <path d="M8 9h8M8 13h8M8 17h8" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Subjects
        </a>

        <div class="pt-2">
          <div class="text-[11px] uppercase tracking-wider text-slate-400 px-3 mt-3">Support</div>
          <a href="./help.html" target="_blank" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-slate-600 hover:text-red-700 hover:bg-yellow-50 transition">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.23 9C8.78 7.83 10.26 7 12 7c2.21 0 4 1.34 4 3 0 1.4-1.28 2.57-3 2.9-.54.1-1 .55-1 1.1v.5M12 17h.01"/>
              <circle cx="12" cy="12" r="9" stroke-width="2"/>
            </svg>
            Help
          </a>
        </div>
      </nav>

      <div class="absolute bottom-0 inset-x-0 border-t border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <img class="h-10 w-10 rounded-full object-cover ring-1 ring-slate-200" src="./professor.jpg" alt="User    avatar" />
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900 truncate"><?= htmlspecialchars($_SESSION['professor_name'] ?? 'User   ') ?></p>
            <p class="text-xs text-slate-500">Professor</p>
          </div>
        </div>
        <div class="mt-3">
          <a href="logout.php" 
             class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-red-600 hover:bg-red-50 transition">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1m0-9V7"/>
            </svg>
            Logout
          </a>
        </div>
      </div>
    </aside>

    <!-- Main content area -->
    <div class="flex-1 md:ml-72 flex flex-col min-h-screen">
      <!-- Header -->
      <header class="sticky top-0 z-30 backdrop-blur bg-white/70 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center gap-3">
          <!-- Mobile: open sidebar -->
          <button id="sidebarToggle" aria-label="Open sidebar" aria-expanded="false" aria-controls="sidebar" class="md:hidden p-2 rounded-md hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-yellow-400">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <line x1="3" y1="12" x2="21" y2="12" />
              <line x1="3" y1="6" x2="21" y2="6" />
              <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
          </button>
          <h1 class="text-xl font-semibold text-slate-900">Attendance Scanner</h1>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto p-6">
        <?php if (!isset($_SESSION['scan_subject'])): ?>
          <!-- Subject selection form -->
          <section class="max-w-md mx-auto">
            <form method="POST" id="startScanForm" class="relative">
              <label for="subjectSearch" class="block text-sm font-medium text-slate-700 mb-2">Select Subject</label>
              <input type="text" id="subjectSearch" autocomplete="off" placeholder="Search subjects..."
                     class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base shadow-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200 transition"
                     aria-autocomplete="list" aria-controls="subjectSuggestions" aria-expanded="false" aria-haspopup="listbox" />
              <ul id="subjectSuggestions" role="listbox" class="hidden absolute z-50 w-full bg-white border border-slate-300 rounded-xl max-h-48 overflow-y-auto mt-1 shadow-lg"></ul>
              <input type="hidden" name="subject_id" id="subjectId" required />
              <button type="submit" name="start_scan"
                      class="mt-6 w-full rounded-xl bg-red-600 text-white text-lg font-bold py-3 shadow-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:ring-offset-1 transition disabled:opacity-50 disabled:cursor-not-allowed"
                      id="startScanBtn" disabled>
                Start Scanning
              </button>
            </form>
          </section>
        <?php else: ?>
          <!-- Scanning interface -->
          <section class="flex flex-col items-center gap-6 w-full max-w-md mx-auto">
            <p class="text-lg font-medium text-slate-800">
              Scanning for subject: <span class="font-semibold text-red-700"><?= htmlspecialchars($_SESSION['scan_subject_name'] ?? 'Unknown Subject') ?></span>
            </p>

            <div id="previewContainer" aria-label="Camera preview with scanning guide">
              <video id="preview" autoplay muted playsinline></video>
              <div id="scanGuide" aria-hidden="true"></div>
            </div>

            <p id="scanResult" class="min-h-[1.5rem] text-center text-slate-700 font-medium" aria-live="polite" aria-atomic="true"></p>
            <p id="scanInstructions" class="text-sm text-slate-500 text-center max-w-xs">
              Position the QR code inside the <span class="font-bold text-yellow-600">yellow</span> box for faster scanning.
            </p>

            <div class="flex gap-4 w-full max-w-xs">
              <button id="startScanBtn" class="flex-1 rounded-xl bg-red-600 text-white text-base font-bold py-3 shadow-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:ring-offset-1 transition">
                Start Scan
              </button>
              <button id="stopScanBtn" disabled class="flex-1 rounded-xl bg-red-700 text-white text-base font-bold py-3 shadow-md hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-1 transition">
                Stop Scan
              </button>
            </div>

            <form method="POST" class="w-full max-w-xs">
              <button type="submit" name="stop_scan"
                      class="w-full mt-4 rounded-xl bg-gray-100 text-gray-700 text-base font-semibold py-3 shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-1 transition">
                End Scan Session
              </button>
            </form>
          </section>

          <script src="https://unpkg.com/@zxing/library@latest"></script>
          <script>
            const codeReader = new ZXing.BrowserQRCodeReader();
            let scanning = false;
            let canScan = true;
            let scannedStudentsClient = new Set();

            const startBtn = document.getElementById('startScanBtn');
            const stopBtn = document.getElementById('stopScanBtn');
            const scanResult = document.getElementById('scanResult');

            async function startScanner() {
              if (scanning) return;
              scanning = true;
              canScan = true;
              startBtn.disabled = true;
              stopBtn.disabled = false;
              scanResult.textContent = 'Scanning...';

              try {
                const devices = await codeReader.getVideoInputDevices();
                if (devices.length === 0) {
                  scanResult.textContent = 'No camera found';
                  scanning = false;
                  startBtn.disabled = false;
                  stopBtn.disabled = true;
                  return;
                }
                const selectedDeviceId = devices[0].deviceId;

                codeReader.decodeFromVideoDevice(selectedDeviceId, 'preview', (result, err) => {
                  if (result && canScan) {
                    canScan = false;
                    const studentId = result.text.trim();

                    if (scannedStudentsClient.has(studentId)) {
                      scanResult.textContent = 'Student already scanned';
                      setTimeout(() => {
                        scanResult.textContent = 'Scanning...';
                        canScan = true;
                      }, 1500);
                      return;
                    }

                    scannedStudentsClient.add(studentId);

                    fetch('<?= basename(__FILE__) ?>', {
                      method: 'POST',
                      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                      body: 'ajax_scan=1&student_id=' + encodeURIComponent(studentId)
                    })
                    .then(res => res.json())
                    .then(data => {
                      if (data.success) {
                        scanResult.textContent = `${data.student} marked as ${data.status}`;
                      } else {
                        scanResult.textContent = data.msg;
                        if (data.msg !== 'Student already scanned') {
                          scannedStudentsClient.delete(studentId);
                        }
                      }
                      setTimeout(() => {
                        scanResult.textContent = 'Scanning...';
                        canScan = true;
                      }, 1500);
                    })
                    .catch(() => {
                      scanResult.textContent = 'Error sending scan data';
                      scannedStudentsClient.delete(studentId);
                      setTimeout(() => {
                        scanResult.textContent = 'Scanning...';
                        canScan = true;
                      }, 1500);
                    });
                  }
                  if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error(err);
                  }
                });
              } catch (e) {
                console.error(e);
                scanResult.textContent = 'Camera access error: ' + e.message;
                scanning = false;
                startBtn.disabled = false;
                stopBtn.disabled = true;
              }
            }

            function stopScanner() {
              if (!scanning) return;
              codeReader.reset();
              scanning = false;
              canScan = false;
              startBtn.disabled = false;
              stopBtn.disabled = true;
              scanResult.textContent = 'Scanning stopped.';
              scannedStudentsClient.clear();
            }

            startBtn.addEventListener('click', startScanner);
            stopBtn.addEventListener('click', stopScanner);
          </script>
        <?php endif; ?>
      </main>
    </div>

    <!-- Mobile sidebar overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-30 hidden md:hidden"></div>
  </div>

  <script>
    const subjects = <?= json_encode($subjects) ?>;
    const subjectSearchInput = document.getElementById('subjectSearch');
    const subjectSuggestions = document.getElementById('subjectSuggestions');
    const subjectIdInput = document.getElementById('subjectId');
    const startScanBtn = document.getElementById('startScanBtn');

    if (subjectSearchInput) {
      subjectSearchInput.addEventListener('input', () => {
        const query = subjectSearchInput.value.trim().toLowerCase();
        subjectIdInput.value = '';
        startScanBtn.disabled = true;
        subjectSuggestions.innerHTML = '';
        subjectSuggestions.classList.add('hidden');
        subjectSuggestions.setAttribute('aria-expanded', 'false');

        if (query.length === 0) return;

        const matches = subjects.filter(sub => (sub.name || '').toLowerCase().includes(query));
        if (matches.length === 0) return;

        matches.slice(0, 10).forEach(sub => {
          const li = document.createElement('li');
          li.textContent = sub.name;
          li.className = 'px-4 py-2 cursor-pointer hover:bg-yellow-300';
          li.setAttribute('role', 'option');
          li.setAttribute('tabindex', '-1');
          li.onclick = () => {
            subjectSearchInput.value = sub.name;
            subjectIdInput.value = sub.id;
            subjectSuggestions.classList.add('hidden');
            subjectSuggestions.setAttribute('aria-expanded', 'false');
            startScanBtn.disabled = false;
          };
          subjectSuggestions.appendChild(li);
        });
        subjectSuggestions.classList.remove('hidden');
        subjectSuggestions.setAttribute('aria-expanded', 'true');
      });

      document.addEventListener('click', (e) => {
        if (!subjectSearchInput.contains(e.target) && !subjectSuggestions.contains(e.target)) {
          subjectSuggestions.classList.add('hidden');
          subjectSuggestions.setAttribute('aria-expanded', 'false');
        }
      });

      let focusedIndex = -1;
      subjectSearchInput.addEventListener('keydown', (e) => {
        const items = subjectSuggestions.querySelectorAll('li');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
          e.preventDefault();
          focusedIndex = (focusedIndex + 1) % items.length;
          items.forEach((item, i) => {
            if (i === focusedIndex) {
              item.classList.add('bg-yellow-300');
              item.focus();
            } else {
              item.classList.remove('bg-yellow-300');
            }
          });
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          focusedIndex = (focusedIndex - 1 + items.length) % items.length;
          items.forEach((item, i) => {
            if (i === focusedIndex) {
              item.classList.add('bg-yellow-300');
              item.focus();
            } else {
              item.classList.remove('bg-yellow-300');
            }
          });
        } else if (e.key === 'Enter') {
          e.preventDefault();
          if (focusedIndex >= 0 && focusedIndex < items.length) {
            items[focusedIndex].click();
            focusedIndex = -1;
          }
        } else if (e.key === 'Escape') {
          subjectSuggestions.classList.add('hidden');
          subjectSuggestions.setAttribute('aria-expanded', 'false');
          focusedIndex = -1;
        }
      });
    }

    // Sidebar toggle for mobile
    document.addEventListener('DOMContentLoaded', function () {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      const toggle = document.getElementById('sidebarToggle');

      const openSidebar = () => {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
      };

      const closeSidebar = () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
      };

      if (toggle) {
        toggle.addEventListener('click', (e) => {
          e.preventDefault();
          const isClosed = sidebar.classList.contains('-translate-x-full');
          isClosed ? openSidebar() : closeSidebar();
        });
      }

      if (overlay) {
        overlay.addEventListener('click', closeSidebar);
      }

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          if (!sidebar.classList.contains('-translate-x-full')) {
            closeSidebar();
          }
        }
      });
    });
  </script>
</body>
</html>
