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

$error = '';
$success_message = '';

// Add Professor
if(isset($_POST['add_professor'])){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Default password

    // Validate for empty fields
    if (empty($name) || empty($email) || empty($_POST['password'])) {
        $error = "All fields are required.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check for duplicate email
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM professors WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error = "A professor with this email already exists.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO professors (name, email, password) VALUES (?, ?, ?)");
            if($stmt->execute([$name, $email, $password])){
                $success_message = "Professor registered successfully!";
            } else {
                $error = "Registration failed.";
            }
        }
    }
}

// Delete professor (password check)
if(isset($_POST['delete_professor'])){
    $professor_id_to_delete = $_POST['delete_professor_id'];
    $password = $_POST['admin_password_professor']; // Admin password for deletion

    // Verify admin password
    if ($password === '7vVMx5@') { // Hardcoded admin password
        $stmt = $pdo->prepare("DELETE FROM professors WHERE id=?");
        if($stmt->execute([$professor_id_to_delete])){
            $success_message = "Professor deleted successfully.";
        } else {
            $error = "Failed to delete professor.";
        }
    } else {
        $error = "Incorrect admin password.";
    }
}

// *** RESET PASSWORD FEATURE ***
if (isset($_POST['reset_password'])) {
    $professor_id = $_POST['reset_professor_id'];
    $new_password = $_POST['new_password'];
    $admin_password = $_POST['admin_password_reset'];

    // Verify admin password (same hardcoded password)
    if ($admin_password === '7vVMx5@') {
        if (empty($new_password)) {
            $error = "New password cannot be empty.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE professors SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $professor_id])) {
                $success_message = "Password reset successfully.";
            } else {
                $error = "Failed to reset password.";
            }
        }
    } else {
        $error = "Incorrect admin password.";
    }
}
// *** END RESET PASSWORD FEATURE ***

// Fetch Professors
$professors = $pdo->prepare("SELECT id, name, email FROM professors");
$professors->execute();
$professors = $professors->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin - Register Professors</title>
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

        <a href="./register_professor.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-bold text-red-700 bg-yellow-50 ring-1 ring-inset ring-red-200" aria-current="page">
          <svg class="w-5 h-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.143M19.5 7.5a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" />
          </svg>
          Register Professors
        </a>

        <a href="./students.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-slate-600 hover:text-red-700 hover:bg-yellow-50 transition">
          <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
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
            <span class="text-lg font-semibold text-slate-900">Register Professors</span>
          </div>

          <div class="ml-auto flex items-center gap-2">
            <button onclick="openModal('addProfessorModal')" class="inline-flex items-center gap-2 rounded-xl bg-red-600 text-white text-sm font-bold px-3 py-2 hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400">
              <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/>
              </svg>
              Register Professor
            </button>
          </div>
        </div>
      </header>

      <!-- Page content -->
      <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if(!empty($error)): ?>
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>
        <?php if(!empty($success_message)): ?>
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
          </div>
        <?php endif; ?>

        <!-- Professors List Table -->
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50/80 border-b border-slate-200">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Name</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Email</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-100">
              <?php if (count($professors) === 0): ?>
                <tr>
                  <td colspan="3" class="px-6 py-4 text-center text-slate-500 italic">No professors registered yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach($professors as $prof): ?>
                  <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900"><?= htmlspecialchars($prof['name']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900"><?= htmlspecialchars($prof['email']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex gap-3">
                      <button
                        onclick="confirmResetPassword(<?= (int)$prof['id'] ?>)"
                        class="text-yellow-600 hover:text-yellow-900 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
                      >
                        Reset Password
                      </button>
                      <button
                        onclick="confirmDeleteProfessor(<?= (int)$prof['id'] ?>)"
                        class="text-red-600 hover:text-red-900 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
                      >
                        Delete
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

  <!-- Add Professor Modal -->
  <div id="addProfessorModal" class="modal hidden">
    <div class="modal-content">
      <span class="close-button" onclick="closeModal('addProfessorModal')">&times;</span>
      <h2 class="text-2xl font-bold text-slate-900 mb-6">Register New Professor</h2>
      <form method="POST" class="space-y-4">
        <div>
          <label for="prof_name" class="block text-sm font-medium text-slate-700">Name</label>
          <input
            type="text"
            id="prof_name"
            name="name"
            required
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200"
          >
        </div>
        <div>
          <label for="prof_email" class="block text-sm font-medium text-slate-700">Email</label>
          <input
            type="email"
            id="prof_email"
            name="email"
            required
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200"
          >
        </div>
        <div>
          <label for="prof_password" class="block text-sm font-medium text-slate-700">Default Password</label>
          <input
            type="password"
            id="prof_password"
            name="password"
            required
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200"
          >
        </div>
        <button
          type="submit"
          name="add_professor"
          class="w-full px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
        >
          Register Professor
        </button>
      </form>
    </div>
  </div>

    <!-- Delete Professor Modal -->
  <div id="deleteProfessorModal" class="modal hidden">
    <div class="modal-content">
      <span class="close-button" onclick="closeModal('deleteProfessorModal')">&times;</span>
      <h3 class="text-xl font-bold text-slate-900 mb-4">Confirm Delete Professor</h3>
      <p class="mb-4">Are you sure you want to delete this professor? This action cannot be undone.</p>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="delete_professor_id" id="delete_professor_id">
        <div>
          <label for="admin_password_professor" class="block text-sm font-medium text-slate-700">Admin Password</label>
          <input
            type="password"
            id="admin_password_professor"
            name="admin_password_professor"
            required
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200"
          >
        </div>
        <div class="flex justify-end space-x-3">
          <button
            type="button"
            onclick="closeModal('deleteProfessorModal')"
            class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
          >
            Cancel
          </button>
          <button
            type="submit"
            name="delete_professor"
            class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-medium hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
          >
            Delete
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Reset Password Modal -->
  <div id="resetPasswordModal" class="modal hidden">
    <div class="modal-content">
      <span class="close-button" onclick="closeModal('resetPasswordModal')">&times;</span>
      <h3 class="text-xl font-bold text-slate-900 mb-4">Reset Professor Password</h3>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="reset_professor_id" id="reset_professor_id">
        <div>
          <label for="new_password" class="block text-sm font-medium text-slate-700">New Password</label>
          <input
            type="password"
            id="new_password"
            name="new_password"
            required
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-yellow-400 focus:ring-1 focus:ring-yellow-200"
          >
        </div>
        <div>
          <label for="admin_password_reset" class="block text-sm font-medium text-slate-700">Admin Password</label>
          <input
            type="password"
            id="admin_password_reset"
            name="admin_password_reset"
            required
            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-yellow-400 focus:ring-1 focus:ring-yellow-200"
          >
        </div>
        <div class="flex justify-end space-x-3">
          <button
            type="button"
            onclick="closeModal('resetPasswordModal')"
            class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
          >
            Cancel
          </button>
          <button
  type="submit"
  name="reset_password"
  class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-medium hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
>
  Reset Password
</button>
        </div>
      </form>
    </div>
  </div>

  <script>
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

      // Close on Escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          if (!sidebar.classList.contains('-translate-x-full')) {
            closeSidebar();
          }
          // Close modals if open
          ['addProfessorModal', 'deleteProfessorModal', 'resetPasswordModal'].forEach(id => {
            const modal = document.getElementById(id);
            if (modal && modal.classList.contains('flex')) {
              closeModal(id);
            }
          });
        }
      });
    });

    // Modal open/close functions
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

    // Confirm delete professor modal
    function confirmDeleteProfessor(id){
      document.getElementById('delete_professor_id').value = id;
      // Clear previous password input
      document.getElementById('admin_password_professor').value = '';
      openModal('deleteProfessorModal');
    }

    // *** RESET PASSWORD FEATURE ***
    // Confirm reset password modal
    function confirmResetPassword(id){
      document.getElementById('reset_professor_id').value = id;
      // Clear previous inputs
      document.getElementById('new_password').value = '';
      document.getElementById('admin_password_reset').value = '';
      openModal('resetPasswordModal');
    }
    // *** END RESET PASSWORD FEATURE ***

    // Close modal if clicking outside modal content
    window.onclick = function(event) {
      ['addProfessorModal', 'deleteProfessorModal', 'resetPasswordModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (event.target == modal) {
          closeModal(id);
        }
      });
    }
  </script>
</body>
</html>