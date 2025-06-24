<?php
session_start();

// Redirect to dashboard if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php'); // We'll create dashboard.php next
    exit();
}

require_once 'includes/config.php'; // Contains $conn
require_once 'includes/user.php'; // Contains User class

$error = '';
$success_message = '';
$show_login_form = true; // Default to showing the login form

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = new User($conn);

    if (isset($_POST['register_submit'])) {
        // --- Registration Logic ---
        $username = trim($_POST['reg_username'] ?? '');
        $email = trim($_POST['reg_email'] ?? '');
        $password = $_POST['reg_password'] ?? '';
        $confirm_password = $_POST['reg_confirm_password'] ?? '';

        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = 'Please fill in all registration fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            $result = $user->register($username, $email, $password);
            if ($result['success']) {
                $success_message = 'Registration successful! You can now log in.';
                // Set to true to show login form after successful registration
                $show_login_form = true; 
            } else {
                $error = $result['message'];
                // Keep registration form active on error
                $show_login_form = false; 
            }
        }
    } elseif (isset($_POST['login_submit'])) {
        // --- Login Logic ---
        $username_or_email = trim($_POST['login_username_email'] ?? '');
        $password = $_POST['login_password'] ?? '';

        if (empty($username_or_email) || empty($password)) {
            $error = 'Please fill in all login fields.';
        } else {
            $result = $user->login($username_or_email, $password);
            if ($result['success']) {
                // Set session variables and redirect to dashboard
                $_SESSION['user_id'] = $result['user_id'];
                $_SESSION['username'] = $result['username'];
				$_SESSION['xp'] = $result['xp']; // Store XP in session
				$_SESSION['current_streak_days'] = $result['current_streak_days']; // Store streak in session
				$_SESSION['last_activity_date'] = $result['last_activity_date']; // Store last activity date in session
                header('Location: dashboard.php');
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Tree Hero Challenge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Optional: Add a smooth transition for form switching */
        .form-container {
            transition: opacity 0.3s ease-in-out;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-100 min-h-screen flex items-center justify-center font-sans text-gray-800">
    <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Tree Hero Challenge</h1>
            <p class="text-gray-600">Join us in making the world greener!</p>
        </div>

        <?php if (isset($success_message) && !empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error) && !empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Toggle Buttons -->
        <div class="flex justify-center gap-4 mb-6">
            <button id="showLoginBtn" class="px-6 py-2 rounded-lg font-semibold transition duration-300
                    <?php echo $show_login_form ? 'bg-green-600 text-white shadow-md' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                Login
            </button>
            <button id="showRegisterBtn" class="px-6 py-2 rounded-lg font-semibold transition duration-300
                    <?php echo !$show_login_form ? 'bg-green-600 text-white shadow-md' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                Register
            </button>
        </div>

        <!-- Login Form -->
        <div id="loginForm" class="form-container <?php echo $show_login_form ? '' : 'hidden opacity-0'; ?>">
            <h2 class="text-xl font-semibold text-gray-900 mb-4 text-center">Login to Your Account</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label for="login_username_email" class="block text-sm font-medium text-gray-700 mb-1">Username or Email</label>
                    <input type="text" id="login_username_email" name="login_username_email" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label for="login_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="login_password" name="login_password" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <button type="submit" name="login_submit"
                        class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-2 px-4 rounded-md font-semibold
                               hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Login
                </button>
                <div class="text-center mt-4">
                    <a href="reset_password.php" class="text-green-600 hover:underline text-sm">Forgot Password?</a>
                </div>
            </form>
        </div>

        <!-- Registration Form -->
        <div id="registerForm" class="form-container <?php echo !$show_login_form ? '' : 'hidden opacity-0'; ?>">
            <h2 class="text-xl font-semibold text-gray-900 mb-4 text-center">Create a New Account</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label for="reg_username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="reg_username" name="reg_username" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label for="reg_email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="reg_email" name="reg_email" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label for="reg_password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="reg_password" name="reg_password" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label for="reg_confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" id="reg_confirm_password" name="reg_confirm_password" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <button type="submit" name="register_submit"
                        class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-2 px-4 rounded-md font-semibold
                               hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Register
                </button>
            </form>
        </div>
    </div>

    <script>
        const showLoginBtn = document.getElementById('showLoginBtn');
        const showRegisterBtn = document.getElementById('showRegisterBtn');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');

        // Function to toggle form visibility and button styles
        function toggleForms(showLogin) {
            if (showLogin) {
                loginForm.classList.remove('hidden', 'opacity-0');
                registerForm.classList.add('hidden', 'opacity-0');
                showLoginBtn.classList.add('bg-green-600', 'text-white', 'shadow-md');
                showLoginBtn.classList.remove('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
                showRegisterBtn.classList.remove('bg-green-600', 'text-white', 'shadow-md');
                showRegisterBtn.classList.add('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
            } else {
                loginForm.classList.add('hidden', 'opacity-0');
                registerForm.classList.remove('hidden', 'opacity-0');
                showRegisterBtn.classList.add('bg-green-600', 'text-white', 'shadow-md');
                showRegisterBtn.classList.remove('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
                showLoginBtn.classList.remove('bg-green-600', 'text-white', 'shadow-md');
                showLoginBtn.classList.add('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
            }
        }

        // Event listeners for the toggle buttons
        showLoginBtn.addEventListener('click', () => toggleForms(true));
        showRegisterBtn.addEventListener('click', () => toggleForms(false));

        // Initial form display based on PHP logic (if an error occurred on registration, stay on register form)
        window.onload = function() {
            <?php if (!$show_login_form): ?>
                toggleForms(false); // Show register form if registration failed
            <?php else: ?>
                toggleForms(true); // Otherwise, show login form
            <?php endif; ?>
        };
    </script>
</body>
</html>
