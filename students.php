<?php
require 'db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only admin can access this page
if(!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header("Location: index.php");
    exit;
}

$msg = '';
$error = '';

// Add Student
if(isset($_POST['add_student'])){
    $student_id = trim($_POST['student_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $guardian_email = trim($_POST['guardian_email']);

    // Basic validation
    if (empty($student_id) || empty($name)) {
        $error = "Student ID and Name are required.";
    } else if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid student email format.";
    } else if (!empty($guardian_email) && !filter_var($guardian_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid guardian email format.";
    } else {
        // Check for duplicate student ID
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "A student with this ID already exists.";
        } else {
            // Check for duplicate student email (if provided)
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "A student with this email already exists.";
                }
            }
        }
    }

    if (empty($error)) {
        $stmt = $pdo->prepare("INSERT INTO students (student_id, name, email, guardian_email) VALUES (?, ?, ?, ?)");
        if($stmt->execute([$student_id, $name, $email, $guardian_email])){
            $msg = "Student added successfully!";
        } else {
            $error = "Failed to add student.";
        }
    }
}

// Activate / Deactivate
if(isset($_GET['toggle']) && isset($_GET['id'])){
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT is_active FROM students WHERE id=?");
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    $new_status = $s['is_active'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE students SET is_active=? WHERE id=?");
    $stmt->execute([$new_status,$id]);
    header("Location: students.php");
    exit;
}

// Delete student (password check)
if(isset($_POST['delete_student'])){
    $student_id_to_delete = $_POST['delete_id'];
    $password = $_POST['admin_password']; // Admin password for deletion

    // Verify admin password
    if ($password === '7vVMx5@') { // Hardcoded admin password
        $stmt = $pdo->prepare("DELETE FROM students WHERE id=?");
        if($stmt->execute([$student_id_to_delete])){
            $msg = "Student deleted successfully.";
        } else {
            $error = "Failed to delete student.";
        }
    } else {
        $error = "Incorrect admin password.";
    }
}

// Fetch Students (all students, as admin manages them)
$students = $pdo->prepare("SELECT * FROM students");
$students->execute();
$students = $students->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin - Register Students</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
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
      display: none; /* Hidden by default */
      position: fixed; /* Stay in place */
      z-index: 1000; /* Sit on top */
      left: 0;
      top: 0;
      width: 100%; /* Full width */
      height: 100%; /* Full height */
      overflow: auto; /* Enable scroll if needed */
      background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
      align-items: center;
      justify-content: center;
  }
  .modal.flex { display: flex; }
  .hidden { display: none !important; }
  .modal-content {
      background-color: #fefefe;
      margin: auto;
      padding: 20px;
      border-radius: 0.75rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      width: 90%;
      max-width: 500px;
      position: relative;
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
</style>
</head>
<body class="bg-[var(--bg-soft)] text-slate-800 antialiased font-sans">
  <div class="min-h-screen flex">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-72 bg-white/90 backdrop-blur border-r border-slate-200 transform transition-transform duration-200 ease-out -translate-x-full md:translate-x-0">
      <div class="h-16 flex items-center gap-3 px-6 border-b border-slate-200">
        <img src="./school.png" alt="Logo" class="h-9 w-9 rounded-md object-cover" />
        <div class="font-semibold text-slate-900 tracking-tight">Admin Portal</div>
      </div>

      <nav class="px-3 py-4 space-y-2">
        <div class="text-[11px] uppercase tracking-wider text-slate-400 px-3 mt-2">Admin Menu</div>

        <a href="./register_professor.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-slate-600 hover:text-red-700 hover:bg-yellow-50 transition">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.143M19.5 7.5a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" />
          </svg>
          Register Professors
        </a>

        <a href="./students.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-bold text-red-700 bg-yellow-50 ring-1 ring-inset ring-red-200" aria-current="page">
          <svg class="w-5 h-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="7" r="4" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
            <path stroke-width="2" stroke-linejoin="round" stroke-linecap="round" d="M5.5 21a6.5 6.5 0 0113 0" />
          </svg>
          Register Students
        </a>
      </nav>

      <div class="absolute bottom-0 inset-x-0 border-t border-slate-200 p-4">
        <div class="flex items-center gap-3">
          <img class="h-10 w-10 rounded-full object-cover ring-1 ring-slate-200" src="./admin.jpg" alt="Admin avatar" />
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900 truncate">Admin</p>
            <p class="text-xs text-slate-500">admin@kabacan.edu.ph</p>
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

    <!-- Main -->
    <div class="flex-1 md:ml-72 flex flex-col">
      <!-- Top bar -->
      <header class="sticky top-0 z-30 backdrop-blur bg-white/70 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center gap-3">
          <!-- Mobile: open sidebar -->
          <button id="sidebarToggle" class="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400" aria-label="Open sidebar">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>

          <!-- Title -->
          <div class="flex items-center gap-2">
            <span class="text-lg font-semibold text-slate-900">Register Students</span>
            <span class="hidden sm:inline-block text-slate-400">/</span>
            <span class="hidden sm:inline-block text-sm text-slate-500">Admin</span>
          </div>

          <div class="ml-auto flex items-center gap-2">
            <button onclick="openModal('addStudentModal')" class="inline-flex items-center gap-2 rounded-xl bg-red-600 text-white text-sm font-bold px-3 py-2 hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400">
              <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/>
              </svg>
              Register Student
            </button>
          </div>
        </div>
      </header>

            <!-- Page content -->
      <main class="flex-1 overflow-y-auto p-8 max-w-7xl mx-auto">
        <?php if(!empty($error)): ?>
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>
        <?php if(!empty($msg)): ?>
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($msg) ?></span>
          </div>
        <?php endif; ?>

        <!-- Students List Table -->
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50/80">
              <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                <th class="px-6 py-3">Student ID</th>
                <th class="px-6 py-3">Name</th>
                <th class="px-6 py-3">Email</th>
                <th class="px-6 py-3">Guardian Email</th>
                <th class="px-6 py-3">Status</th>
                <th class="px-6 py-3">Actions</th>
                <th class="px-6 py-3">QR Code</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php if (count($students) === 0): ?>
                <tr>
                  <td colspan="7" class="px-6 py-4 text-center text-slate-500 italic">No students registered yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach($students as $s): ?>
                  <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-slate-800"><?= htmlspecialchars($s['student_id']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-slate-800"><?= htmlspecialchars($s['name']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-slate-800"><?= htmlspecialchars($s['email']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-slate-800"><?= htmlspecialchars($s['guardian_email']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-slate-800"><?= $s['is_active'] ? "Active" : "Inactive" ?></td>
                    <td class="px-6 py-4 whitespace-nowrap font-medium text-sm">
                      <a href="?toggle=1&id=<?= (int)$s['id'] ?>" class="text-red-600 hover:text-red-900 mr-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 rounded">
                        <?= $s['is_active'] ? "Deactivate" : "Activate" ?>
                      </a>
                      <button onclick="confirmDelete(<?= (int)$s['id'] ?>)" class="text-red-600 hover:text-red-900 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400">Delete</button>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-slate-800">
                      <button
                        onclick="showQR('<?= htmlspecialchars($s['student_id'], ENT_QUOTES) ?>')"
                        class="text-red-600 hover:text-red-900 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
                      >
                        Show QR
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </main>
    </div>

    <!-- Mobile sidebar overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-30 hidden md:hidden"></div>
  </div>

  <!-- Add Student Modal -->
  <div id="addStudentModal" class="modal hidden">
    <div class="modal-content">
      <span class="close-button" onclick="closeModal('addStudentModal')">&times;</span>
      <h2 class="text-2xl font-bold text-slate-900 mb-6">Register New Student</h2>
      <form method="POST" class="space-y-4">
        <div>
          <label for="student_id" class="block text-sm font-medium text-slate-700">Student ID</label>
          <input
            type="text"
            id="student_id"
            name="student_id"
            required
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200"
          />
        </div>
        <div>
          <label for="student_name" class="block text-sm font-medium text-slate-700">Name</label>
          <input
            type="text"
            id="student_name"
            name="name"
            required
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200"
          />
        </div>
        <div>
          <label for="student_email" class="block text-sm font-medium text-slate-700">Email</label>
          <input
            type="email"
            id="student_email"
            name="email"
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200"
          />
        </div>
        <div>
          <label for="guardian_email" class="block text-sm font-medium text-slate-700">Guardian Email</label>
          <input
            type="email"
            id="guardian_email"
            name="guardian_email"
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200"
          />
        </div>
        <button
          type="submit"
          name="add_student"
          class="w-full px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
        >
          Register Student
        </button>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" class="modal hidden">
    <div class="modal-content">
      <span class="close-button" onclick="closeModal('deleteModal')">&times;</span>
      <h3 class="text-xl font-bold text-slate-900 mb-4">Confirm Delete</h3>
      <p class="mb-4">Are you sure you want to delete this student? This action cannot be undone.</p>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="delete_id" id="delete_id" />
        <div>
          <label for="admin_password" class="block text-sm font-medium text-slate-700">Admin Password</label>
          <input
            type="password"
            id="admin_password"
            name="admin_password"
            required
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200"
          />
        </div>
        <div class="flex justify-end space-x-3">
          <button
            type="button"
            onclick="closeModal('deleteModal')"
            class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
          >
            Cancel
          </button>
          <button
            type="submit"
            name="delete_student"
            class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-medium hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
          >
            Delete
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- QR Modal -->
  <div id="qrModal" class="modal hidden">
    <div class="modal-content">
      <span class="close-button" onclick="closeModal('qrModal')">&times;</span>
      <h3 class="text-xl font-bold text-slate-900 mb-4">Student QR Code</h3>
      <div id="qrCodeImage" class="flex justify-center mb-4"></div>
      <div class="flex justify-end">
        <button
          type="button"
          onclick="closeModal('qrModal')"
          class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
        >
          Close
        </button>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      const toggle = document.getElementById('sidebarToggle');

      const userMenuButton = document.getElementById('userMenuButton');
      const userMenuDropdown = document.getElementById('userMenuDropdown');

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
          if (userMenuDropdown && userMenuButton && !userMenuDropdown.classList.contains('hidden')) {
            userMenuDropdown.classList.add('hidden');
            userMenuButton.setAttribute('aria-expanded', 'false');
          }
        }
      });
    });

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

    function confirmDelete(id){
      document.getElementById('delete_id').value = id;
      openModal('deleteModal');
    }

    function showQR(studentID){
      openModal('qrModal');
      QRCode.toDataURL(studentID, { width: 256, height: 256 }).then(url => {
        document.getElementById('qrCodeImage').innerHTML = '<img src="'+url+'" alt="QR Code for Student ID: '+studentID+'">';
      }).catch(err => {
        console.error(err);
        document.getElementById('qrCodeImage').innerHTML = '<p class="text-red-500">Error generating QR code.</p>';
      });
    }

    // Close modals if click outside content
    window.onclick = function(event) {
      ['addStudentModal', 'deleteModal', 'qrModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (event.target == modal) {
          closeModal(id);
        }
      });
    }
  </script>
</body>
</html>
