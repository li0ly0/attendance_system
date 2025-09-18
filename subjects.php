<?php
require 'db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION['professor_id'])) header("Location: index.php");

$error = '';

// Handle Add Subject
if(isset($_POST['add_subject'])){
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO subjects (professor_id,name) VALUES (?,?)");
        $stmt->execute([$_SESSION['professor_id'],$name]);
        header("Location: subjects.php");
        exit;
    }
}

// Handle Delete Subject
if (isset($_POST['delete_subject'])) {
    $subject_id = $_POST['delete_subject_id'] ?? null;
    $entered_name = trim($_POST['confirm_subject_name'] ?? '');

    if ($subject_id) {
        $stmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ? AND professor_id = ?");
        $stmt->execute([$subject_id, $_SESSION['professor_id']]);
        $subject = $stmt->fetch();

        if ($subject && strtolower($subject['name']) === strtolower($entered_name)) {
            // Delete subject
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ? AND professor_id = ?");
            $stmt->execute([$subject_id, $_SESSION['professor_id']]);

            // Delete enrollments
            $stmt = $pdo->prepare("DELETE FROM subject_enrollments WHERE subject_id = ?");
            $stmt->execute([$subject_id]);

            header("Location: subjects.php?msg=Subject+deleted+successfully");
            exit;
        } else {
            $error = "Subject name did not match. Please type the correct subject name.";
        }
    }
}

// Enroll/Unenroll students via AJAX
if(isset($_POST['ajax_enroll']) && isset($_POST['subject_id']) && isset($_POST['student_id']) && isset($_POST['action'])){
    $subject_id = $_POST['subject_id'];
    $student_id = $_POST['student_id'];
    $action = $_POST['action'];

    if($action === 'enroll'){
        $stmt = $pdo->prepare("INSERT IGNORE INTO subject_enrollments (subject_id, student_id) VALUES (?, ?)");
        $stmt->execute([$subject_id, $student_id]);
        echo json_encode(['success'=>true]);
    } elseif($action === 'unenroll'){
        $stmt = $pdo->prepare("DELETE FROM subject_enrollments WHERE subject_id=? AND student_id=?");
        $stmt->execute([$subject_id, $student_id]);
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false, 'msg'=>'Invalid action']);
    }
    exit;
}

// Fetch Subjects
$subjects = $pdo->prepare("SELECT * FROM subjects WHERE professor_id=?");
$subjects->execute([$_SESSION['professor_id']]);
$subjects = $subjects->fetchAll();

// Fetch all active students for enrollment search
$all_students_stmt = $pdo->prepare("SELECT * FROM students WHERE is_active=1");
$all_students_stmt->execute();
$all_students = $all_students_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Subjects</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Base theming */
    :root {
      --bg-soft: #faf9f6;
    }
    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: rgba(2,6,23,.06); }
    ::-webkit-scrollbar-thumb { background: #dc2626; border-radius: 9999px; } /* red-600 */
    ::-webkit-scrollbar-thumb:hover { background: #ca8a04; } /* yellow-600 */

    /* Modal styles */
    .modal {
        display: none; /* hidden by default */
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
        align-items: center;
        justify-content: center;
    }
    .modal.flex {
        display: flex;
    }
    .modal-content {
        background-color: #fff;
        margin: auto;
        padding: 24px;
        border-radius: 1rem; /* rounded-xl */
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.05), 0 1px 3px rgb(0 0 0 / 0.1);
        width: 90%;
        max-width: 700px;
        position: relative;
        border: 1px solid #e5e7eb; /* gray-200 */
    }
    .close-button {
        color: #aaa;
        position: absolute;
        top: 10px;
        right: 20px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    .close-button:hover,
    .close-button:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }
    #suggestionsList {
        position: absolute;
        z-index: 50;
        width: calc(100% - 4rem);
        max-height: 12rem;
        overflow-y: auto;
        background: white;
        border: 1px solid #facc15; /* yellow-400 */
        border-radius: 0.75rem; /* rounded-xl */
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        list-style: none;
        margin-top: 0.25rem;
        padding-left: 0;
    }
    #suggestionsList li {
        padding: 0.5rem 1rem;
        cursor: pointer;
    }
    #suggestionsList li:hover {
        background-color: #fde68a; /* yellow-300 */
    }
  </style>
</head>
<body class="bg-[var(--bg-soft)] text-slate-800 antialiased font-sans">
  <div class="min-h-screen flex">
    <!-- Sidebar -->
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

        <a href="scan.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-slate-600 hover:text-red-700 hover:bg-yellow-50 transition">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <rect x="3" y="4" width="18" height="16" rx="2" stroke-width="2"/>
            <path d="M3 10h18M7 16h0" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Scanner
        </a>

        <a href="subjects.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-bold text-red-700 bg-yellow-50 ring-1 ring-inset ring-red-200" aria-current="page">
          <svg class="w-5 h-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
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
          <img class="h-10 w-10 rounded-full object-cover ring-1 ring-slate-200" src="./professor.jpg" alt="User   avatar" />
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900 truncate"><?= htmlspecialchars($_SESSION['professor_name'] ?? 'User  ') ?></p>
            <p class="text-xs text-slate-500">Professor</p>
          </div>
          <!-- Remove dropdown button -->
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

    <!-- Main -->
    <div class="flex-1 md:ml-72">
      <!-- Top bar -->
      <header class="sticky top-0 z-30 backdrop-blur bg-white/70 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center gap-3">
          <!-- Mobile: open sidebar -->
          <button id="sidebarToggle" class="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400" aria-label="Open sidebar">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>

          <!-- Title -->
          <div class="flex items-center gap-2">
            <span class="text-lg font-semibold text-slate-900">Overview</span>
            <span class="hidden sm:inline-block text-slate-400">/</span>
            <span class="hidden sm:inline-block text-sm text-slate-500">Subjects</span>
          </div>

          <div class="ml-auto flex items-center gap-2">
            <button onclick="openModal('addSubjectModal')" class="inline-flex items-center gap-2 rounded-xl bg-red-600 text-white text-sm font-bold px-3 py-2 hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400">
              <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/>
              </svg>
              Add Subject
            </button>
          </div>
        </div>
      </header>

            <!-- Page content -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (!empty($error)): ?>
          <div class="bg-rose-50 border border-rose-300 text-rose-700 px-4 py-3 rounded-xl mb-4" role="alert">
            <strong class="font-bold">Error!</strong> <?= htmlspecialchars($error) ?>
          </div>
        <?php elseif (!empty($_GET['msg'])): ?>
          <div class="bg-yellow-50 border border-yellow-300 text-yellow-700 px-4 py-3 rounded-xl mb-4" role="alert">
            <?= htmlspecialchars($_GET['msg']) ?>
          </div>
        <?php endif; ?>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php if (count($subjects) === 0): ?>
            <p class="col-span-full text-center text-slate-500 italic">No subjects added yet.</p>
          <?php else: ?>
            <?php foreach($subjects as $sub): ?>
              <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
                <div>
                  <h2 class="text-xl font-semibold text-slate-900 mb-2"><?= htmlspecialchars($sub['name']) ?></h2>
                </div>
                <div class="mt-4 flex gap-2">
                  <button onclick="openEnrollModal(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['name'])) ?>')"
                          class="flex-1 px-4 py-2 rounded-xl bg-yellow-50 text-yellow-700 text-sm font-bold hover:bg-yellow-100 focus:outline-none focus:ring-1 focus:ring-yellow-400 focus:ring-offset-1 transition">
                    Enroll Students
                  </button>
                  <button onclick="openDeleteModal(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['name'])) ?>')"
                          class="flex-1 px-4 py-2 rounded-xl bg-rose-50 text-rose-700 text-sm font-semibold hover:bg-rose-100 focus:outline-none focus:ring-1 focus:ring-rose-400 focus:ring-offset-1 transition">
                    Delete
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div> <!-- /main -->

  </div> <!-- /layout container -->

    <!-- Add Subject Modal -->
  <div id="addSubjectModal" class="modal hidden">
    <div class="modal-content">
      <span class="close-button" onclick="closeModal('addSubjectModal')">&times;</span>
      <h2 class="text-2xl font-bold text-slate-900 mb-6">Add New Subject</h2>
      <form method="POST" class="space-y-4">
        <div>
          <label for="subject_name" class="block text-sm font-medium text-slate-700">Subject Name</label>
          <input type="text" id="subject_name" name="name" required
                 class="mt-1 block w-full rounded-xl border border-slate-300 shadow-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200 sm:text-sm p-2" />
        </div>
        <button type="submit" name="add_subject"
                class="w-full px-4 py-2 rounded-xl bg-yellow-50 text-yellow-700 text-sm font-bold shadow-sm hover:bg-yellow-100 focus:outline-none focus:ring-1 focus:ring-yellow-400 focus:ring-offset-1 transition">
          Add Subject
        </button>
      </form>
    </div>
  </div>

  <!-- Enrollment Modal -->
  <div id="enrollModal" class="modal hidden">
    <div class="modal-content relative">
      <span class="close-button" onclick="closeModal('enrollModal')">&times;</span>
      <h3 class="text-2xl font-bold text-slate-900 mb-4">Enroll Students in <span id="modalSubjectName" class="text-red-700"></span></h3>
      <div class="relative mb-4">
        <input type="text" id="studentSearch" placeholder="Search students by name or ID"
               class="w-full p-2 rounded-xl border border-slate-300 shadow-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200" autocomplete="off" />
        <button id="enrollButton" disabled
                class="absolute right-2 top-1/2 -translate-y-1/2 px-4 py-1 rounded-xl bg-yellow-50 text-yellow-700 text-sm font-bold hover:bg-yellow-100 focus:outline-none focus:ring-1 focus:ring-yellow-400 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed">
          Enroll
        </button>
        <ul id="suggestionsList" class="hidden absolute z-50 w-full bg-white border border-yellow-400 rounded-xl max-h-48 overflow-auto mt-1 shadow-sm"></ul>
      </div>

      <h4 class="font-semibold mb-2">Enrolled Students</h4>
      <div class="overflow-x-auto border border-slate-200 rounded-xl shadow-sm max-h-64 overflow-y-auto">
        <table class="min-w-full divide-y divide-slate-100">
          <thead class="bg-white border-b border-slate-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Student ID</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Name</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Email</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Action</th>
            </tr>
          </thead>
          <tbody id="enrolledStudentsBody" class="bg-white divide-y divide-slate-100">
            <!-- Filled by JS -->
          </tbody>
        </table>
      </div>
      <div class="mt-6 flex justify-end">
        <button onclick="closeModal('enrollModal')"
                class="px-4 py-2 border border-slate-300 rounded-xl shadow-sm text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-1 focus:ring-yellow-400 focus:ring-offset-1">
          Close
        </button>
      </div>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <span onclick="closeModal('deleteModal')" class="float-right cursor-pointer">&times;</span>
      <h2 class="text-xl font-bold text-rose-600 mb-4">Confirm Delete</h2>
      <p class="mb-3">Type the subject name to confirm deletion: <strong id="deleteModalSubjectName"></strong></p>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="delete_subject_id" id="deleteSubjectId" />
        <input type="text" name="confirm_subject_name" id="confirm_subject_name" placeholder="Enter subject name" required class="w-full border rounded p-2" />
        <button type="submit" name="delete_subject" id="deleteBtn" disabled class="px-4 py-2 bg-rose-500 text-white rounded hover:bg-rose-600 disabled:bg-slate-400">
          Delete
        </button>
      </form>
    </div>
  </div>

  <script>
    // JavaScript code for modals, enrollment, search, user menu, and delete confirmation
    let currentSubjectId = null;
    let allStudents = <?= json_encode($all_students) ?>;
    let enrolledStudents = new Map();
    let selectedStudent = null;

    function openModal(modalId) {
      const modal = document.getElementById(modalId);
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeModal(modalId) {
      const modal = document.getElementById(modalId);
      modal.classList.remove('flex');
      modal.classList.add('hidden');
    }

    function openEnrollModal(subjectId, subjectName){
      currentSubjectId = subjectId;
      document.getElementById('modalSubjectName').innerText = subjectName;
      openModal('enrollModal');
      document.getElementById('studentSearch').value = '';
      selectedStudent = null;
      document.getElementById('enrollButton').disabled = true;
      document.getElementById('suggestionsList').innerHTML = '';
      document.getElementById('suggestionsList').classList.add('hidden');
      fetchEnrolledStudents(subjectId);
    }

    function fetchEnrolledStudents(subjectId){
      fetch('fetch_enrolled_students.php?subject_id=' + subjectId)
      .then(res => res.json())
      .then(data => {
        if(data.success){
          enrolledStudents.clear();
          data.enrolled.forEach(s => enrolledStudents.set(s.id, s));
          renderEnrolledStudents();
        } else {
          alert('Failed to fetch enrolled students: ' + data.msg);
        }
      })
      .catch(error => {
        console.error('Error fetching enrolled students:', error);
        alert('An error occurred while fetching enrolled students.');
      });
    }

    function renderEnrolledStudents(){
      const tbody = document.getElementById('enrolledStudentsBody');
      tbody.innerHTML = '';
      enrolledStudents.forEach(student => {
        const tr = document.createElement('tr');
        tr.classList.add('hover:bg-slate-50', 'transition-colors');

        const tdId = document.createElement('td');
        tdId.className = 'px-6 py-4 whitespace-nowrap text-sm text-slate-900';
        tdId.textContent = student.student_id;
        tr.appendChild(tdId);

        const tdName = document.createElement('td');
        tdName.className = 'px-6 py-4 whitespace-nowrap text-sm text-slate-900';
        tdName.textContent = student.name;
        tr.appendChild(tdName);

        const tdEmail = document.createElement('td');
        tdEmail.className = 'px-6 py-4 whitespace-nowrap text-sm text-slate-900';
        tdEmail.textContent = student.email;
        tr.appendChild(tdEmail);

        const tdAction = document.createElement('td');
        tdAction.className = 'px-6 py-4 whitespace-nowrap text-sm text-slate-900';
        const dropOutBtn = document.createElement('button');
        dropOutBtn.textContent = 'Unenroll';
        dropOutBtn.className = 'px-3 py-1 rounded-xl bg-rose-50 text-rose-700 text-xs font-semibold hover:bg-rose-100 focus:outline-none focus:ring-1 focus:ring-rose-400 focus:ring-offset-1';
        dropOutBtn.onclick = () => unenrollStudent(student.id);
        tdAction.appendChild(dropOutBtn);
        tr.appendChild(tdAction);

        tbody.appendChild(tr);
      });
    }

    function unenrollStudent(studentId){
      if(!confirm('Are you sure you want to drop out this student?')) return;
      fetch('subjects.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_enroll=1&subject_id=' + encodeURIComponent(currentSubjectId) + '&student_id=' + encodeURIComponent(studentId) + '&action=unenroll'
      })
      .then(res => res.json())
      .then(data => {
        if(data.success){
          enrolledStudents.delete(studentId);
          renderEnrolledStudents();
        } else {
          alert('Failed to drop out student: ' + data.msg);
        }
      })
      .catch(() => alert('An error occurred while dropping out student.'));
    }

    const studentSearchInput = document.getElementById('studentSearch');
    const suggestionsList = document.getElementById('suggestionsList');
    const enrollButton = document.getElementById('enrollButton');

    studentSearchInput.addEventListener('input', () => {
      const query = studentSearchInput.value.trim().toLowerCase();
      selectedStudent = null;
      enrollButton.disabled = true;
      suggestionsList.innerHTML = '';
      if(query.length === 0){
        suggestionsList.classList.add('hidden');
        return;
      }
      const matches = allStudents.filter(s =>
        s.name.toLowerCase().includes(query) || s.student_id.toLowerCase().includes(query)
      ).filter(s => !enrolledStudents.has(s.id));
      if(matches.length === 0){
        suggestionsList.classList.add('hidden');
        return;
      }
      matches.slice(0, 10).forEach(student => {
        const li = document.createElement('li');
        li.textContent = `${student.student_id} - ${student.name}`;
        li.className = 'px-4 py-2 cursor-pointer hover:bg-yellow-300';
        li.onclick = () => {
          selectedStudent = student;
          studentSearchInput.value = `${student.student_id} - ${student.name}`;
          suggestionsList.classList.add('hidden');
          enrollButton.disabled = false;
        };
        suggestionsList.appendChild(li);
      });
      suggestionsList.classList.remove('hidden');
    });

    enrollButton.addEventListener('click', () => {
      if(!selectedStudent) return;
      fetch('subjects.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_enroll=1&subject_id=' + encodeURIComponent(currentSubjectId) + '&student_id=' + encodeURIComponent(selectedStudent.id) + '&action=enroll'
      })
      .then(res => res.json())
      .then(data => {
        if(data.success){
          enrolledStudents.set(selectedStudent.id, selectedStudent);
          renderEnrolledStudents();
          studentSearchInput.value = '';
          selectedStudent = null;
          enrollButton.disabled = true;
        } else {
          alert('Failed to enroll student: ' + data.msg);
        }
      })
      .catch(() => alert('An error occurred while enrolling student.'));
    });

    // Close suggestions if clicking outside
    document.addEventListener('click', (e) => {
      if (!studentSearchInput.contains(e.target) && !suggestionsList.contains(e.target)) {
        suggestionsList.classList.add('hidden');
      }
    });

    /* ---------------- DELETE SUBJECT ---------------- */
    function openDeleteModal(subjectId, subjectName){
      document.getElementById('deleteSubjectId').value = subjectId;
      document.getElementById('deleteModalSubjectName').innerText = subjectName;
      document.getElementById('confirm_subject_name').value = '';
      document.getElementById('deleteBtn').disabled = true;
      openModal('deleteModal');

      // live validation
      document.getElementById('confirm_subject_name').oninput = function(){
        document.getElementById('deleteBtn').disabled = 
          this.value.trim().toLowerCase() !== subjectName.trim().toLowerCase();
      }
    }

    // Sidebar toggle for mobile
    document.addEventListener('DOMContentLoaded', function () {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.createElement('div');
      overlay.id = 'sidebarOverlay';
      overlay.className = 'fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-30 hidden md:hidden';
      document.body.appendChild(overlay);

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

      // Close on Escape
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