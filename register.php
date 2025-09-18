<?php
require 'db.php';

$error = '';

if(isset($_POST['register'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO professors (name,email,password) VALUES (?,?,?)");
    if($stmt->execute([$name,$email,$password])){
        header("Location: index.php");
        exit;
    } else {
        $error = "Registration failed";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Register</title>
<style>
    :root {
        --color-blue-300: #93c5fd;
        --color-blue-400: #60a5fa;
        --color-blue-500: #3b82f6;
        --color-blue-600: #2563eb;
        --color-blue-700: #1d4ed8;
        --color-gray-100: #f3f4f6;
        --color-gray-400: #9ca3af;
        --color-gray-500: #6b7280;
        --color-gray-800: #1f2937;
        --color-white: #ffffff;
    }

    *, *::before, *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        min-height: 100vh;
        background: #f0f2f5; 
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .login-container {
        width: 100%;
        max-width: 56rem; /* 896px */
        background-color: var(--color-white);
        border-radius: 1.5rem; /* 24px */
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        display: flex;
        overflow: hidden;
    }

    .decorative-panel {
        width: 41.666667%; /* lg:w-5/12 */
        background-color: var(--color-white);
        padding: 2rem;
        position: relative;
        overflow: hidden;
    }

    .shape {
        position: absolute;
        border-radius: 9999px; /* rounded-full */
        opacity: 0.8;
    }

    /* Blue gradient shapes */
    .shape-1 {
        width: 16rem; height: 16rem;
        background-image: linear-gradient(to bottom right, var(--color-blue-500), var(--color-blue-700));
        top: -2.5rem;
        left: -4rem;
    }
    .shape-2 {
        width: 12rem; height: 12rem;
        background-image: linear-gradient(to bottom right, var(--color-blue-300), var(--color-blue-600));
        top: 6rem;
        right: -5rem;
        opacity: 0.7;
    }
    .shape-3 {
        width: 12rem; height: 12rem;
        background-image: linear-gradient(to bottom right, #60a5fa, #3b82f6);
        bottom: 2rem;
        left: 25%;
        opacity: 0.9;
    }
    .shape-4 {
        width: 8rem; height: 8rem;
        background-image: linear-gradient(to bottom right, var(--color-blue-400), var(--color-blue-500));
        bottom: 6rem;
        left: -2rem;
        opacity: 0.7;
    }

    .login-form-container {
        width: 100%;
        background-color: var(--color-white);
        padding: 2rem; /* p-8 */
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .login-form-container h1 {
        font-size: 1.875rem; /* text-3xl */
        font-weight: 700;
        color: var(--color-gray-800);
        margin-bottom: 2rem;
        text-align: center;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .input-wrapper {
        position: relative;
    }

    .form-input {
        width: 100%;
        background-color: var(--color-gray-100);
        border: 1px solid transparent;
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
        color: var(--color-gray-800);
        transition: box-shadow 0.3s;
        font-size: 1rem;
    }

    .form-input:focus {
        outline: none;
        box-shadow: 0 0 0 2px var(--color-white), 0 0 0 4px var(--color-blue-500);
    }

    .login-button {
        width: 100%;
        margin-top: 0.5rem;
        background-image: linear-gradient(to right, var(--color-blue-600), var(--color-blue-500), var(--color-blue-700));
        color: var(--color-white);
        font-weight: 700;
        padding: 0.75rem 1rem;
        border: none;
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        cursor: pointer;
        transition: opacity 0.3s;
        font-size: 1rem;
    }

    .login-button:hover {
        opacity: 0.9;
    }
    
    .login-button:focus {
        outline: none;
        box-shadow: 0 0 0 2px var(--color-white), 0 0 0 4px var(--color-blue-400);
    }

    .links-container {
        margin-top: 2rem;
        text-align: center;
    }
    
    .links-container p {
        margin-top: 1rem;
    }

    .links-container a {
        font-size: 0.875rem; /* text-sm */
        color: var(--color-blue-600);
        text-decoration: none;
        transition: color 0.3s;
        font-weight: 600;
    }

    .links-container a:hover {
        color: var(--color-blue-700);
    }

    .error-message {
        background-color: #fee2e2;
        color: #b91c1c;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        font-size: 0.9rem;
        text-align: center;
    }
    
    /* Responsive Design */
    @media (min-width: 768px) { /* md breakpoint */
        .decorative-panel {
            display: block;
        }
        .login-form-container {
            width: 58.333333%; /* md:w-7/12 */
            padding: 3rem; /* sm:p-12 */
        }
         .login-form-container h1 {
            text-align: left;
         }
    }
    
    @media (min-width: 1024px) { /* lg breakpoint */
        .login-form-container {
             padding: 4rem; /* lg:p-16 */
        }
    }

    @media (max-width: 767px) {
        .decorative-panel {
            display: none;
        }
    }
</style>
</head>
<body>
    <div class="login-container">
        <div class="decorative-panel" aria-hidden="true">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
        </div>
        <div class="login-form-container">
            <h1>Register</h1>

            <?php if(!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="form-group">
                    <div class="input-wrapper">
                        <label for="name" class="sr-only">Name</label>
                        <input id="name" name="name" type="text" required class="form-input" placeholder="Your full name" autocomplete="name" />
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-wrapper">
                        <label for="email" class="sr-only">Email</label>
                        <input id="email" name="email" type="email" required class="form-input" placeholder="you@example.com" autocomplete="email" />
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-wrapper">
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" type="password" required class="form-input" placeholder="••••••••" autocomplete="new-password" />
                    </div>
                </div>

                <button type="submit" name="register" class="login-button">
                    REGISTER
                </button>

                <div class="links-container">
                    <p>
                        Already have an account?
                        <a href="index.php">&larr; Sign In</a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
