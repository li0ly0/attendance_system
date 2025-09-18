<?php
require 'db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error = '';

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // TODO: Avoid hard-coded credentials in production
    // Check for admin login first
    if ($email === 'admin@kabacan.edu.ph' && $password === '7vVMx5@') {
        $_SESSION['admin_logged_in'] = true;
        header("Location: register_professor.php");
        exit;
    }

    // Then check for professor login
    $stmt = $pdo->prepare("SELECT * FROM professors WHERE email=?");
    $stmt->execute([$email]);
    $prof = $stmt->fetch();

    if ($prof && password_verify($password, $prof['password'])) {
        $_SESSION['professor_id'] = $prof['id'];
        $_SESSION['professor_name'] = $prof['name'];
        $_SESSION['professor_email'] = $prof['email']; // Store email for sidebar
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid credentials";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login</title>
<style>
  :root {
    /* Make the accent bolder and cohesive with the red theme */
    --blue: #dc2626;      /* primary (now red) */
    --blue-dark: #991b1b; /* hover (darker red) */

    --gray-light: #fafbfc;
    --gray-lighter: #f7f9fc;
    --gray-medium: #6b7280;
    --gray-dark: #1f2937;
    --error-bg: #fee2e2;
    --error-text: #b91c1c;
    --border-radius: 0.5rem;
    --font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    --transition: 0.25s ease;
  }

  *, *::before, *::after {
    box-sizing: border-box;
  }

  /* Minimal, bold red+yellow animated background */
  body {
    margin: 0;
    font-family: var(--font-family);

    /* Two soft, bold blobs + warm base */
    background-image:
      radial-gradient(circle, rgba(220, 38, 38, 0.32), transparent 60%),  /* red blob */
      radial-gradient(circle, rgba(234, 179, 8, 0.30), transparent 60%),  /* yellow blob */
      linear-gradient(180deg, #fff7ed 0%, #fff1f2 100%);                  /* warm base */
    background-repeat: no-repeat;
    background-size: 140% 140%, 130% 130%, cover;
    background-position: 0% 0%, 100% 100%, center;

    /* Soft, slow drift animation */
    animation: blobDrift 22s ease-in-out infinite;
    will-change: background-position;

    /* Layout */
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 1rem;
    color: var(--gray-dark);
  }

  @keyframes blobDrift {
    0%   { background-position: 0% 0%,   100% 100%, center; }
    50%  { background-position: 12% 6%,  88% 94%,  center; }
    100% { background-position: 0% 0%,   100% 100%, center; }
  }

  /* Respect users who prefer reduced motion */
  @media (prefers-reduced-motion: reduce) {
    body {
      animation: none;
      background-position: 0% 0%, 100% 100%, center;
    }
  }

  .login-container {
    /* Clean “glass” card over the bold background */
    background: rgba(255, 255, 255, 0.86);
    backdrop-filter: blur(10px) saturate(140%);
    -webkit-backdrop-filter: blur(10px) saturate(140%);
    border-radius: var(--border-radius);
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    max-width: 320px;
    width: 100%;
    padding: 2.5rem 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .logo {
    margin-bottom: 1.5rem;
    max-width: 100px;
    user-select: none;
  }

  h1 {
    font-size: 1.5rem;
    font-weight: 700; /* slightly bolder title */
    margin-bottom: 1.5rem;
    text-align: center;
    color: var(--gray-dark);
  }

  .error-message {
    background-color: var(--error-bg);
    color: var(--error-text);
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1.25rem;
    font-size: 0.9rem;
    width: 100%;
    text-align: center;
    box-shadow: 0 1px 4px rgba(185, 28, 28, 0.15);
  }

  form {
    width: 100%;
  }

  .form-group {
    margin-bottom: 1.25rem;
  }

  label.sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0,0,0,0) !important;
    white-space: nowrap !important;
    border: 0 !important;
  }

  input[type="email"],
  input[type="password"] {
    width: 100%;
    padding: 0.65rem 1rem;
    font-size: 1rem;
    border-radius: var(--border-radius);
    border: 1.25px solid transparent;
    background-color: var(--gray-light);
    color: var(--gray-dark);
    font-weight: 500;
    transition: border-color var(--transition), box-shadow var(--transition);
    font-family: var(--font-family);
  }

  input::placeholder {
    color: var(--gray-medium);
    font-weight: 400;
  }

  input:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 6px rgba(220, 38, 38, 0.35);
    background-color: var(--gray-lighter);
  }

  button.login-button {
    width: 100%;
    padding: 0.85rem 0;
    font-size: 1rem;
    font-weight: 700; /* bolder button text */
    color: #ffffff;
    background-color: var(--blue); /* now red */
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    box-shadow: 0 6px 14px rgba(220, 38, 38, 0.35);
    transition: background-color var(--transition), box-shadow var(--transition), transform var(--transition);
    letter-spacing: 0.04em;
    user-select: none;
  }

  button.login-button:hover,
  button.login-button:focus {
    background-color: var(--blue-dark);
    box-shadow: 0 10px 20px rgba(153, 27, 27, 0.45);
    outline: none;
    transform: translateY(-1px);
  }

  .links-container {
    margin-top: 1.5rem;
    text-align: center;
  }

  .links-container a {
    font-size: 0.85rem;
    color: var(--blue);
    text-decoration: none;
    font-weight: 600;
    transition: color var(--transition);
  }

  .links-container a:hover,
  .links-container a:focus {
    color: var(--blue-dark);
    outline: none;
    text-decoration: underline;
  }

  /* Modal styles */
  .modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(31, 41, 55, 0.75); /* var(--gray-dark) with opacity */
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
  }

  .modal-overlay.active {
    display: flex;
  }

  .modal {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem 2rem;
    max-width: 320px;
    width: 90%;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
    text-align: center;
    font-family: var(--font-family);
    color: var(--gray-dark);
  }

  .modal h2 {
    margin-top: 0;
    margin-bottom: 1rem;
  }

  .modal p {
    margin-bottom: 1.5rem;
    font-size: 1rem;
  }

  .modal button.close-modal {
    background-color: var(--blue);
    color: white;
    border: none;
    border-radius: var(--border-radius);
    padding: 0.5rem 1.25rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 6px 14px rgba(220, 38, 38, 0.35);
    transition: background-color var(--transition), box-shadow var(--transition);
  }

  .modal button.close-modal:hover,
  .modal button.close-modal:focus {
    background-color: var(--blue-dark);
    box-shadow: 0 10px 20px rgba(153, 27, 27, 0.45);
    outline: none;
  }

  @media (max-width: 480px) {
    .login-container {
      padding: 2rem 1.5rem;
      max-width: 100%;
    }
    h1 {
      font-size: 1.25rem;
      margin-bottom: 1rem;
    }
  }
</style>
</head>
<body>
  <main class="login-container" role="main" aria-label="Login form">
    <!-- Replace with your logo image -->
    <img src="./school.png" alt="Logo" class="logo" />

    <h1>Login</h1>

    <?php if (!empty($error)): ?>
      <div class="error-message" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="" method="POST" novalidate>
      <div class="form-group">
        <label for="email" class="sr-only">Email</label>
        <input id="email" name="email" type="email" required placeholder="you@example.com" autocomplete="email" />
      </div>
      <div class="form-group">
        <label for="password" class="sr-only">Password</label>
        <input id="password" name="password" type="password" required placeholder="••••••••" autocomplete="current-password" />
      </div>

      <button type="submit" name="login" class="login-button">LOGIN</button>
    </form>

    <div class="links-container">
      <a href="#" id="forgot-password-link">Forgot Username / Password?</a>
    </div>
  </main>

  <!-- Modal -->
  <div class="modal-overlay" id="forgot-password-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-desc">
    <div class="modal">
      <h2 id="modal-title">Forgot Username / Password</h2>
      <p id="modal-desc">Please contact the admin or go to the faculty for assistance.</p>
      <button class="close-modal" id="close-modal-btn" aria-label="Close modal">Close</button>
    </div>
  </div>

  <script>
    const modal = document.getElementById('forgot-password-modal');
    const openModalBtn = document.getElementById('forgot-password-link');
    const closeModalBtn = document.getElementById('close-modal-btn');

    openModalBtn.addEventListener('click', function(event) {
      event.preventDefault();
      modal.classList.add('active');
    });

    closeModalBtn.addEventListener('click', function() {
      modal.classList.remove('active');
    });

    // Close modal on clicking outside modal content
    modal.addEventListener('click', function(event) {
      if (event.target === modal) {
        modal.classList.remove('active');
      }
    });

    // Close modal on pressing Escape key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape' && modal.classList.contains('active')) {
        modal.classList.remove('active');
      }
    });
  </script>
</body>
</html>
