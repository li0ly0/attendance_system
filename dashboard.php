<?php
require 'db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['professor_id'])) header("Location: index.php");

// Fetch subjects for filter dropdown
$subjectStmt = $pdo->prepare("SELECT * FROM subjects WHERE professor_id=?");
$subjectStmt->execute([$_SESSION['professor_id']]);
$subjects = $subjectStmt->fetchAll();

// Initialize filter variables from GET parameters
$date = $_GET['date'] ?? '';
$subjectFilter = $_GET['subject'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build base query and parameters
$query = "SELECT a.*, s.name AS student_name, sub.name AS subject_name
          FROM attendance_records a
          JOIN students s ON a.student_id = s.student_id
          JOIN subjects sub ON a.subject_id = sub.id
          WHERE sub.professor_id = ?";
$params = [$_SESSION['professor_id']];

// Add filters dynamically
if ($date) {
    $query .= " AND DATE(a.scan_time) = ?";
    $params[] = $date;
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

// Determine the date to consider as "today" for counts
$todayDate = $date ?: date('Y-m-d');

// Build query to count statuses for the given date and subject filter
$countQuery = "SELECT a.status, COUNT(*) as count
               FROM attendance_records a
               JOIN students s ON a.student_id = s.student_id
               JOIN subjects sub ON a.subject_id = sub.id
               WHERE sub.professor_id = ? AND DATE(a.scan_time) = ?";

$countParams = [$_SESSION['professor_id'], $todayDate];

if ($subjectFilter) {
    $countQuery .= " AND a.subject_id = ?";
    $countParams[] = $subjectFilter;
}

$countQuery .= " GROUP BY a.status";

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($countParams);
$statusCounts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$absentCount = $statusCounts['Absent'] ?? 0;
$lateCount = $statusCounts['Late'] ?? 0;
$presentCount = $statusCounts['Present'] ?? 0;

// Pagination setup (client-side slice; for large data consider server-side pagination)
$recordsPerPage = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$totalRecords = count($records);
$totalPages = max(1, (int)ceil($totalRecords / $recordsPerPage));
$page = min(max(1, $page), $totalPages);
$offset = ($page - 1) * $recordsPerPage;
$recordsToShow = array_slice($records, $offset, $recordsPerPage);

// Build base query string for pagination links preserving filters except page
$queryParams = $_GET;
unset($queryParams['page']);
$baseQueryString = http_build_query($queryParams);
if ($baseQueryString !== '') {
    $baseQueryString .= '&';
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance Dashboard</title>
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

        <a href="./dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-bold text-red-700 bg-yellow-50 ring-1 ring-inset ring-red-200">
          <svg class="w-5 h-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor">
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
    <img class="h-10 w-10 rounded-full object-cover ring-1 ring-slate-200" src="./professor.jpg" alt="User  avatar" />
    <div class="min-w-0">
      <p class="text-sm font-semibold text-slate-900 truncate"><?= htmlspecialchars($_SESSION['professor_name'] ?? 'User ') ?></p>
      <p class="text-xs text-slate-500">Professor</p>
    </div>
    <!-- Remove dropdown button -->
  </div>
  
  <!-- Place logout link below the profile -->
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
            <span class="hidden sm:inline-block text-sm text-slate-500">Dashboard</span>
          </div>

          <div class="ml-auto flex items-center gap-2">
            <a href="scan.php" class="inline-flex items-center gap-2 rounded-xl bg-red-600 text-white text-sm font-bold px-3 py-2 hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400">
              <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/>
              </svg>
              New Scan
            </a>
          </div>
        </div>
      </header>

      <!-- Page content -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- KPI Cards -->
        <section class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
          <!-- Present -->
          <article class="rounded-2xl bg-white border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-200">
                  <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7"/>
                  </svg>
                </span>
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-500">Present</p>
                  <p class="text-2xl font-semibold text-slate-900"><?= (int)$presentCount ?></p>
                </div>
              </div>
              <span class="text-xs text-slate-400">for <?= htmlspecialchars($todayDate) ?></span>
            </div>
          </article>

          <!-- Absent -->
          <article class="rounded-2xl bg-white border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-inset ring-rose-200">
                  <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/>
                  </svg>
                </span>
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-500">Absent</p>
                  <p class="text-2xl font-semibold text-slate-900"><?= (int)$absentCount ?></p>
                </div>
              </div>
              <span class="text-xs text-slate-400">for <?= htmlspecialchars($todayDate) ?></span>
            </div>
          </article>

          <!-- Late -->
          <article class="rounded-2xl bg-white border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 ring-1 ring-inset ring-amber-200">
                  <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/>
                    <circle cx="12" cy="12" r="9" stroke-width="2"/>
                  </svg>
                </span>
                <div>
                  <p class="text-xs uppercase tracking-wide text-slate-500">Late</p>
                  <p class="text-2xl font-semibold text-slate-900"><?= (int)$lateCount ?></p>
                </div>
              </div>
              <span class="text-xs text-slate-400">for <?= htmlspecialchars($todayDate) ?></span>
            </div>
          </article>
        </section>

                <!-- Filters -->
        <form method="GET" class="mt-8 rounded-2xl bg-white border border-slate-200 shadow-sm p-4 sm:p-5">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <!-- Search -->
            <div class="lg:col-span-2">
              <label for="search" class="sr-only">Search by ID or Name</label>
              <div class="flex items-center rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 focus-within:border-red-600 focus-within:ring-1 focus-within:ring-yellow-200 transition">
                <svg class="w-5 h-5 text-slate-400 mr-2 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 1 10.5 3a7.5 7.5 0 0 1 6.15 13.65z"/>
                </svg>
                <input type="search" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by ID or Name..." class="flex-grow border-0 bg-transparent focus:ring-0 text-sm text-slate-700 placeholder-slate-400" />
              </div>
            </div>

            <!-- Subject -->
            <div>
              <label for="subject" class="sr-only">Filter by Subject</label>
              <select id="subject" name="subject" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $sub): ?>
                  <option value="<?= (int)$sub['id'] ?>" <?= ($subjectFilter == $sub['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sub['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Status -->
            <div>
              <label for="status" class="sr-only">Filter by Status</label>
              <select id="status" name="status" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200">
                <option value="">All Statuses</option>
                <option value="Present" <?= ($statusFilter == 'Present') ? 'selected' : '' ?>>Present</option>
                <option value="Late" <?= ($statusFilter == 'Late') ? 'selected' : '' ?>>Late</option>
                <option value="Absent" <?= ($statusFilter == 'Absent') ? 'selected' : '' ?>>Absent</option>
              </select>
            </div>

            <!-- Date -->
            <div>
              <label for="date" class="sr-only">Filter by Date</label>
              <input type="date" id="date" name="date" value="<?= htmlspecialchars($date) ?>" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-red-600 focus:ring-1 focus:ring-yellow-200" />
            </div>

            <!-- Actions -->
            <div class="sm:col-span-2 lg:col-span-1 flex items-center gap-2 justify-start lg:justify-end">
              <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 text-white text-sm font-bold px-4 py-2 hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400">
                <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1 1 10.5 3a7.5 7.5 0 0 1 6.15 13.65z"/>
                </svg>
                Apply
              </button>
              <a href="<?= htmlspecialchars(basename(__FILE__)) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-semibold px-4 py-2 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400">
                Reset
              </a>
            </div>
          </div>
        </form>

        <!-- Data table card -->
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white shadow-sm">
          <!-- Mobile list -->
          <div class="sm:hidden divide-y divide-slate-200">
            <?php if (count($recordsToShow) === 0): ?>
              <div class="p-6 text-center text-slate-500">No records found.</div>
            <?php else: ?>
              <?php foreach ($recordsToShow as $r): ?>
                <?php
                  $statusClass = 'text-slate-700 bg-slate-100 ring-1 ring-inset ring-slate-200';
                  if ($r['status'] === 'Present') $statusClass = 'text-emerald-700 bg-emerald-50 ring-1 ring-inset ring-emerald-200';
                  elseif ($r['status'] === 'Late') $statusClass = 'text-amber-700 bg-amber-50 ring-1 ring-inset ring-amber-200';
                  elseif ($r['status'] === 'Absent') $statusClass = 'text-rose-700 bg-rose-50 ring-1 ring-inset ring-rose-200';
                ?>
                <div class="p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="font-medium text-slate-900 truncate"><?= htmlspecialchars($r['student_name']) ?></div>
                      <div class="text-xs text-slate-500 mt-0.5">ID: <?= htmlspecialchars($r['student_id']) ?></div>
                      <div class="text-sm text-slate-600 mt-1"><?= htmlspecialchars($r['subject_name']) ?></div>
                      <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($r['scan_time']) ?></div>
                    </div>
                    <span class="shrink-0 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusClass ?>">
                      <?= htmlspecialchars($r['status']) ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Desktop table -->
          <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50/80">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                  <th class="px-6 py-3">Student ID</th>
                  <th class="px-6 py-3">Name</th>
                  <th class="px-6 py-3">Subject</th>
                  <th class="px-6 py-3">Scan Time</th>
                  <th class="px-6 py-3">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (count($recordsToShow) === 0): ?>
                  <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-slate-500">No records found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recordsToShow as $r): ?>
                    <?php
                      $statusClass = 'text-slate-700 bg-slate-100 ring-1 ring-inset ring-slate-200';
                      if ($r['status'] === 'Present') $statusClass = 'text-emerald-700 bg-emerald-50 ring-1 ring-inset ring-emerald-200';
                      elseif ($r['status'] === 'Late') $statusClass = 'text-amber-700 bg-amber-50 ring-1 ring-inset ring-amber-200';
                      elseif ($r['status'] === 'Absent') $statusClass = 'text-rose-700 bg-rose-50 ring-1 ring-inset ring-rose-200';
                    ?>
                    <tr class="hover:bg-slate-50">
                      <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-900"><?= htmlspecialchars($r['student_id']) ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-slate-800"><?= htmlspecialchars($r['student_name']) ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-slate-800"><?= htmlspecialchars($r['subject_name']) ?></td>
                      <td class="px-6 py-4 whitespace-nowrap text-slate-500"><?= htmlspecialchars($r['scan_time']) ?></td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $statusClass ?>">
                          <?= htmlspecialchars($r['status']) ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagination -->
        <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-slate-600">
          <div>Showing <?= (int)count($recordsToShow) ?> of <?= (int)$totalRecords ?> records</div>
          <nav aria-label="Pagination" class="flex items-center gap-1">
            <?php if ($page > 1): ?>
              <a href="?<?= $baseQueryString ?>page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50">Previous</a>
            <?php else: ?>
              <span class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed">Previous</span>
            <?php endif; ?>

            <?php
              $start = max(1, $page - 2);
              $end = min($totalPages, $page + 2);
              if ($start > 1) {
                echo '<a href="?' . $baseQueryString . 'page=1" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50">1</a>';
                if ($start > 2) echo '<span class="px-2 text-slate-400">…</span>';
              }
              for ($i = $start; $i <= $end; $i++) {
                if ($i == $page) {
                  echo '<span class="px-3 py-1.5 rounded-xl border border-red-600 bg-red-600 text-white" aria-current="page">' . $i . '</span>';
                } else {
                  echo '<a href="?' . $baseQueryString . 'page=' . $i . '" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50">' . $i . '</a>';
                }
              }
              if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo '<span class="px-2 text-slate-400">…</span>';
                echo '<a href="?' . $baseQueryString . 'page=' . $totalPages . '" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50">' . $totalPages . '</a>';
              }
            ?>

            <?php if ($page < $totalPages): ?>
              <a href="?<?= $baseQueryString ?>page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50">Next</a>
            <?php else: ?>
              <span class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed">Next</span>
            <?php endif; ?>
          </nav>
        </div>
            </div> <!-- /page content -->
    </div> <!-- /main (content + top bar) -->

    <!-- Mobile sidebar overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-30 hidden md:hidden"></div>
  </div> <!-- /layout container -->

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

      // User menu toggle
      if (userMenuButton && userMenuDropdown) {
        userMenuButton.addEventListener('click', function (e) {
          e.stopPropagation();
          const isHidden = userMenuDropdown.classList.contains('hidden');
          userMenuDropdown.classList.toggle('hidden');
          userMenuButton.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
          if (!userMenuDropdown.classList.contains('hidden')) {
            if (!userMenuDropdown.contains(e.target) && !userMenuButton.contains(e.target)) {
              userMenuDropdown.classList.add('hidden');
              userMenuButton.setAttribute('aria-expanded', 'false');
            }
          }
        });
      }
    });
  </script>
</body>
</html>