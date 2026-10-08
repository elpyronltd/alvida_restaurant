<?php
/**
 * Jenda Restaurant - Login & Signup Handler
 * Integrated with local MySQL storage and Firebase Social Auth (Google & Apple)
 */

session_start();
require_once 'include/db.php';

// If already logged in, redirect directly to their dashboard
if (isset($_SESSION['user_id'])) {
    redirectByRole($_SESSION['role']);
}

// Helper to handle role-based redirection
function redirectByRole($role)
{
    switch ($role) {
        case 'admin':
            header("Location: manage_restaurant_2v6.php");
            break;
        case 'cook':
            header("Location: chef.php");
            break;
        case 'customer':
        default:
            header("Location: profile.php");
            break;
    }
    exit;
}

$errors = [];
$success = "";

// --------------------------------------------------------------------
// HANDLE AJAX SOCIAL AUTHENTICATION (Google / Apple)
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'social_auth') {
    header('Content-Type: application/json');

    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $username = trim($_POST['username'] ?? '');
    $profile_picture = trim($_POST['profile_picture'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if (!$email || empty($username) || empty($contact) || empty($location)) {
        echo json_encode(['success' => false, 'message' => 'Missing mandatory fields (Contact and Location are required).']);
        exit;
    }

    try {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // User exists, log them in. Ensure we update their profile picture if retrieved from social auth
            if (!empty($profile_picture) && ($user['profile_picture'] === 'uploads/profiles/default.png' || empty($user['profile_picture']))) {
                $updateStmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $updateStmt->execute([$profile_picture, $user['id']]);
                $user['profile_picture'] = $profile_picture;
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_picture'] = $user['profile_picture'];

            echo json_encode(['success' => true, 'role' => $user['role']]);
            exit;
        } else {
            // User does not exist, create a new "customer"
            // Generate a random secure password since they use OAuth
            $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

            // Check if username is taken, append unique identifier if so
            $checkUsername = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $checkUsername->execute([$username]);
            if ($checkUsername->fetchColumn() > 0) {
                $username = $username . '_' . rand(100, 999);
            }

            $profilePicToSave = !empty($profile_picture) ? $profile_picture : 'uploads/profiles/default.png';

            $insertStmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, location, contact, status, profile_picture) 
                VALUES (?, ?, ?, 'customer', ?, ?, 'standard', ?)
            ");
            $insertStmt->execute([$username, $email, $randomPassword, $location, $contact, $profilePicToSave]);

            $newUserId = $pdo->lastInsertId();

            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'customer';
            $_SESSION['profile_picture'] = $profilePicToSave;

            echo json_encode(['success' => true, 'role' => 'customer']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// --------------------------------------------------------------------
// HANDLE STANDARD FORM SUBMISSIONS (POST)
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {

    // 1. STANDARD LOGIN FLOW
    if (isset($_POST['submit_login'])) {
        $login_identifier = trim($_POST['login_identifier'] ?? ''); // Can be username or email
        $password = $_POST['password'] ?? '';

        if (empty($login_identifier) || empty($password)) {
            $errors[] = "Please fill in all credentials.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$login_identifier, $login_identifier]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Start Session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_picture'] = $user['profile_picture'];

                    redirectByRole($user['role']);
                } else {
                    $errors[] = "Invalid username/email or password.";
                }
            } catch (PDOException $e) {
                $errors[] = "Something went wrong: " . $e->getMessage();
            }
        }
    }

    // 2. STANDARD SIGNUP FLOW
    if (isset($_POST['submit_signup'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $terms = isset($_POST['terms']);

        // Validations
        if (empty($username) || empty($contact) || empty($location) || empty($password)) {
            $errors[] = "All required fields must be completed.";
        }
        if ($password !== $password_confirm) {
            $errors[] = "Passwords do not match.";
        }
        if (!$terms) {
            $errors[] = "You must agree to the Terms and Conditions of Jenda Restaurant.";
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please provide a valid email address.";
        }

        if (empty($errors)) {
            try {
                // Verify uniqueness of username and email
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR (email = ? AND email IS NOT NULL AND email != '')");
                $checkStmt->execute([$username, $email]);

                if ($checkStmt->fetchColumn() > 0) {
                    $errors[] = "Username or Email is already registered.";
                } else {
                    // Process Insertion
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $emailVal = !empty($email) ? $email : null;

                    $insert = $pdo->prepare("
                        INSERT INTO users (username, email, password, role, location, contact, status) 
                        VALUES (?, ?, ?, 'customer', ?, ?, 'standard')
                    ");
                    $insert->execute([$username, $emailVal, $hashedPassword, $location, $contact]);

                    $success = "Registration successful! You can now log in.";
                    // Automatically redirect or switch screen tab
                }
            } catch (PDOException $e) {
                $errors[] = "Registration failed: " . $e->getMessage();
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
    <title>Welcome to Jenda Restaurant</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        amber: {
                            50: '#fffbeb',
                            100: '#fef3c7',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                        },
                        darkAccent: '#1c1917',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-stone-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">

    <!-- Container Card -->
    <div
        class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden border border-stone-100 transition-all duration-300">

        <!-- Header Banner -->
        <div class="bg-darkAccent text-white py-8 px-6 text-center relative overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center opacity-20"
                style="background-image: url('https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=600&q=80');">
            </div>
            <div class="relative z-10">
                <h2 class="text-3xl font-extrabold tracking-tight text-amber-500">Jenda Restaurant</h2>
                <p class="mt-2 text-sm text-stone-300">Savor the flavor, experience modern dining</p>
            </div>
        </div>

        <!-- Toggle Tabs -->
        <div class="flex border-b border-stone-200">
            <button id="tab-login"
                class="w-1/2 py-4 text-center text-sm font-semibold border-b-2 border-amber-500 text-amber-600 transition-all duration-200 focus:outline-none"
                onclick="switchTab('login')">
                <i class="fa-solid fa-sign-in-alt mr-2"></i>Sign In
            </button>
            <button id="tab-signup"
                class="w-1/2 py-4 text-center text-sm font-semibold border-b-2 border-transparent text-stone-500 hover:text-stone-700 transition-all duration-200 focus:outline-none"
                onclick="switchTab('signup')">
                <i class="fa-solid fa-user-plus mr-2"></i>Create Account
            </button>
        </div>

        <!-- Notification Messages -->
        <div class="p-6 pb-0">
            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4 text-sm" role="alert">
                    <ul class="list-disc pl-4">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endstyle; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded-md mb-4 text-sm"
                    role="alert">
                    <p class="font-medium"><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <div id="ajax-error"
                class="hidden bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4 text-sm"
                role="alert"></div>
        </div>

        <!-- Forms Container -->
        <div class="p-6">

            <!-- 1. LOGIN FORM -->
            <form id="form-login" action="login.php" method="POST" class="space-y-4">
                <div>
                    <label for="login_identifier"
                        class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Username or
                        Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                            <i class="fa-solid fa-user text-sm"></i>
                        </span>
                        <input type="text" name="login_identifier" id="login_identifier" required
                            class="pl-10 block w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 placeholder-stone-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                            placeholder="Enter your username or email">
                    </div>
                </div>

                <div>
                    <label for="login_password"
                        class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </span>
                        <input type="password" name="password" id="login_password" required
                            class="pl-10 block w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 placeholder-stone-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                            placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <div class="flex items-center">
                        <input id="remember_me" name="remember_me" type="checkbox"
                            class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-stone-300 rounded">
                        <label for="remember_me" class="ml-2 block text-xs text-stone-600">Remember me</label>
                    </div>
                    <div class="text-xs">
                        <a href="#" class="font-medium text-amber-600 hover:text-amber-700">Forgot password?</a>
                    </div>
                </div>

                <div>
                    <button type="submit" name="submit_login"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-amber-500 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-colors duration-200 shadow-md">
                        Sign In to Your Table
                    </button>
                </div>
            </form>

            <!-- 2. SIGNUP FORM (Hidden by default) -->
            <form id="form-signup" action="login.php" method="POST" class="space-y-4 hidden">
                <div>
                    <label for="signup_name"
                        class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Full Name /
                        Username <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                            <i class="fa-solid fa-user text-sm"></i>
                        </span>
                        <input type="text" name="username" id="signup_name" required
                            class="pl-10 block w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 placeholder-stone-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                            placeholder="e.g., JohnDoe">
                    </div>
                </div>

                <div>
                    <label for="signup_email"
                        class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Email <span
                            class="text-stone-400 text-[10px]">(Optional)</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                            <i class="fa-solid fa-envelope text-sm"></i>
                        </span>
                        <input type="email" name="email" id="signup_email"
                            class="pl-10 block w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 placeholder-stone-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                            placeholder="john@example.com">
                    </div>
                </div>

                <div>
                    <label for="signup_contact"
                        class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Contact Number
                        <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                            <i class="fa-solid fa-phone text-sm"></i>
                        </span>
                        <input type="text" name="contact" id="signup_contact" required
                            class="pl-10 block w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 placeholder-stone-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                            placeholder="+1234567890">
                    </div>
                </div>

                <div>
                    <label for="signup_location"
                        class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Location /
                        Address <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                            <i class="fa-solid fa-location-dot text-sm"></i>
                        </span>
                        <input type="text" name="location" id="signup_location" required
                            class="pl-10 block w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 placeholder-stone-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                            placeholder="Street Name, Apt, City">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="signup_password"
                            class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Password
                            <span class="text-red-500">*</span></label>
                        <input type="password" name="password" id="signup_password" required
                            class="block w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 placeholder-stone-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                            placeholder="••••••••">
                    </div>
                    <div>
                        <label for="signup_confirm"
                            class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Confirm
                            <span class="text-red-500">*</span></label>
                        <input type="password" name="password_confirm" id="signup_confirm" required
                            class="block w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 placeholder-stone-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                            placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-start pt-1">
                    <div class="flex items-center h-5">
                        <input id="terms" name="terms" type="checkbox" required
                            class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-stone-300 rounded">
                    </div>
                    <div class="ml-2 text-xs">
                        <label for="terms" class="font-medium text-stone-600">I agree to the <a href="#"
                                class="text-amber-600 hover:underline">Terms &amp; Conditions</a> of Jenda
                            Restaurant.</label>
                    </div>
                </div>

                <div>
                    <button type="submit" name="submit_signup"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-amber-500 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-colors duration-200 shadow-md">
                        Create Your Account
                    </button>
                </div>
            </form>

            <!-- Social Logins Section -->
            <div class="mt-6">
                <div class="relative flex items-center justify-center my-4">
                    <div class="border-t border-stone-200 w-full"></div>
                    <span class="absolute bg-white px-3 text-xs text-stone-400 font-medium uppercase tracking-wider">Or
                        continue with</span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <button type="button" onclick="loginWithGoogle()"
                        class="w-full flex items-center justify-center gap-2 py-2 px-4 border border-stone-300 rounded-lg text-sm font-medium text-stone-700 bg-white hover:bg-stone-50 focus:outline-none transition-colors duration-200">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" width="24" height="24"
                            xmlns="http://www.w3.org/2000/svg">
                            <g transform="matrix(1, 0, 0, 1, 0, 0)">
                                <path
                                    d="M21.35,11.1H12v2.7h5.38c-0.24,1.28 -0.96,2.37 -2.04,3.1v2.58h3.3c1.93,-1.78 3.04,-4.4 3.04,-7.48C21.68,11.75 21.56,11.4 21.35,11.1z"
                                    fill="#4285F4" />
                                <path
                                    d="M12,20.58c2.43,0 4.47,-0.8 5.96,-2.2l-3.3,-2.58c-0.91,0.61 -2.08,0.98 -3.3,0.98 -2.34,0 -4.32,-1.58 -5.03,-3.7H2.93v2.66C4.41,18.66 7.97,20.58 12,20.58z"
                                    fill="#34A853" />
                                <path
                                    d="M6.97,13.08a5.18,5.18 0 0 1 0,-3.3v-2.66H2.93A8.64,8.64 0 0 0 2,11.43c0,1.37 0.33,2.68 0.93,3.84L6.97,13.08z"
                                    fill="#FBBC05" />
                                <path
                                    d="M12,6.3c1.32,0 2.51,0.45 3.44,1.35l2.58,-2.58C16.46,3.64 14.42,2.82 12,2.82 7.97,2.82 4.41,4.74 2.93,7.4L6.97,10.06C7.68,7.96 9.66,6.3 12,6.3z"
                                    fill="#EA4335" />
                            </g>
                        </svg>
                        Google
                    </button>
                    <button type="button" onclick="loginWithApple()"
                        class="w-full flex items-center justify-center gap-2 py-2 px-4 border border-stone-300 rounded-lg text-sm font-medium text-stone-700 bg-white hover:bg-stone-50 focus:outline-none transition-colors duration-200">
                        <i class="fa-brands fa-apple text-lg"></i>
                        Apple
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- REQUIRED MISSING FIELDS MODAL (For Social Logins) -->
    <div id="social-modal"
        class="hidden fixed inset-0 z-50 overflow-y-auto bg-stone-900 bg-opacity-60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-6 border border-stone-100 transition-all duration-300 transform scale-95 opacity-0"
            id="social-modal-card">

            <div class="text-center mb-6">
                <div
                    class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-amber-50 text-amber-500 mb-3">
                    <i class="fa-solid fa-map-location-dot text-xl"></i>
                </div>
                <h3 class="text-xl font-bold text-stone-900">Welcome to Jenda!</h3>
                <p class="text-xs text-stone-500 mt-1">To complete your registration, we just need a few vital delivery
                    details.</p>
            </div>

            <form id="form-social-details" onsubmit="submitSocialDetails(event)" class="space-y-4">
                <input type="hidden" id="social-email">
                <input type="hidden" id="social-username">
                <input type="hidden" id="social-photo">

                <div>
                    <label for="social_contact"
                        class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Contact Number
                        <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                            <i class="fa-solid fa-phone text-sm"></i>
                        </span>
                        <input type="text" id="social_contact" required
                            class="pl-10 block w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                            placeholder="+1234567890">
                    </div>
                </div>

                <div>
                    <label for="social_location"
                        class="block text-xs font-semibold text-stone-600 uppercase tracking-wider mb-1">Delivery
                        Address / Location <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                            <i class="fa-solid fa-location-dot text-sm"></i>
                        </span>
                        <input type="text" id="social_location" required
                            class="pl-10 block w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-sm"
                            placeholder="Street Name, Apt, City">
                    </div>
                </div>

                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input id="social_terms" type="checkbox" required
                            class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-stone-300 rounded">
                    </div>
                    <div class="ml-2 text-xs">
                        <label for="social_terms" class="font-medium text-stone-600">I agree to the <a href="#"
                                class="text-amber-600 hover:underline">Terms &amp; Conditions</a> of Jenda
                            Restaurant.</label>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeSocialModal()"
                        class="w-1/2 py-2 px-4 border border-stone-300 rounded-lg text-sm font-semibold text-stone-700 bg-white hover:bg-stone-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="w-1/2 py-2 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-amber-500 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-colors shadow-md">
                        Get Started
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FIREBASE INITIALIZATION -->
    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/12.15.0/firebase-app.js";
        import { getAuth, signInWithPopup, GoogleAuthProvider, OAuthProvider } from "https://www.gstatic.com/firebasejs/12.15.0/firebase-auth.js";

        // Your web app's Firebase configuration
        const firebaseConfig = {
            apiKey: "AIzaSyCTPY28gYNHV6TS_YVafSGneXvTvb7ryMA",
            authDomain: "jendarestaurant.firebaseapp.com",
            projectId: "jendarestaurant",
            storageBucket: "jendarestaurant.firebasestorage.app",
            messagingSenderId: "518545519468",
            appId: "1:518545519468:web:cc09a476b011f1a379b949",
            measurementId: "G-RCLGV4BLMS"
        };

        // Initialize Firebase & Auth
        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);

        // Define providers
        const googleProvider = new GoogleAuthProvider();
        const appleProvider = new OAuthProvider('apple.com');

        // Mount authentication functions globally to be used on click events
        window.loginWithGoogle = async function () {
            clearErrors();
            try {
                const result = await signInWithPopup(auth, googleProvider);
                handleSocialLoginResult(result.user);
            } catch (error) {
                showAjaxError("Google Authentication failed: " + error.message);
            }
        };

        window.loginWithApple = async function () {
            clearErrors();
            try {
                const result = await signInWithPopup(auth, appleProvider);
                handleSocialLoginResult(result.user);
            } catch (error) {
                showAjaxError("Apple Authentication failed: " + error.message);
            }
        };
    </script>

    <!-- CLIENT SIDE INTERACTION SCRIPT -->
    <script>
        // Tab switching logic
        function switchTab(tab) {
            const loginForm = document.getElementById('form-login');
            const signupForm = document.getElementById('form-signup');
            const tabLoginBtn = document.getElementById('tab-login');
            const tabSignupBtn = document.getElementById('tab-signup');

            if (tab === 'login') {
                loginForm.classList.remove('hidden');
                signupForm.classList.add('hidden');
                tabLoginBtn.className = "w-1/2 py-4 text-center text-sm font-semibold border-b-2 border-amber-500 text-amber-600 transition-all duration-200 focus:outline-none";
                tabSignupBtn.className = "w-1/2 py-4 text-center text-sm font-semibold border-b-2 border-transparent text-stone-500 hover:text-stone-700 transition-all duration-200 focus:outline-none";
            } else {
                loginForm.classList.add('hidden');
                signupForm.classList.remove('hidden');
                tabSignupBtn.className = "w-1/2 py-4 text-center text-sm font-semibold border-b-2 border-amber-500 text-amber-600 transition-all duration-200 focus:outline-none";
                tabLoginBtn.className = "w-1/2 py-4 text-center text-sm font-semibold border-b-2 border-transparent text-stone-500 hover:text-stone-700 transition-all duration-200 focus:outline-none";
            }
        }

        // Handle Social Login Response
        function handleSocialLoginResult(user) {
            const email = user.email;
            const displayName = user.displayName || email.split('@')[0];
            const photoURL = user.photoURL || '';

            // Check if user is registered in local db already using AJAX
            const formData = new FormData();
            formData.append('action', 'social_auth');
            formData.append('email', email);
            formData.append('username', displayName);
            formData.append('profile_picture', photoURL);

            // We attempt background social validation.
            // If the user already has location/contact saved, backend signs them in immediately.
            // Otherwise, backend sends error/requirement signal.
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        redirectUser(data.role);
                    } else {
                        // Requires Contact & Location
                        openSocialModal(email, displayName, photoURL);
                    }
                })
                .catch(err => {
                    showAjaxError("Authentication request failed. Please check network settings.");
                });
        }

        // Modal Controls
        function openSocialModal(email, username, photo) {
            document.getElementById('social-email').value = email;
            document.getElementById('social-username').value = username;
            document.getElementById('social-photo').value = photo;

            const modal = document.getElementById('social-modal');
            const card = document.getElementById('social-modal-card');

            modal.classList.remove('hidden');
            setTimeout(() => {
                card.classList.remove('scale-95', 'opacity-0');
                card.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeSocialModal() {
            const modal = document.getElementById('social-modal');
            const card = document.getElementById('social-modal-card');

            card.classList.remove('scale-100', 'opacity-100');
            card.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        // Submitting details from the Google/Apple details modal
        function submitSocialDetails(event) {
            event.preventDefault();

            const email = document.getElementById('social-email').value;
            const username = document.getElementById('social-username').value;
            const photo = document.getElementById('social-photo').value;
            const contact = document.getElementById('social_contact').value;
            const location = document.getElementById('social_location').value;

            const formData = new FormData();
            formData.append('action', 'social_auth');
            formData.append('email', email);
            formData.append('username', username);
            formData.append('profile_picture', photo);
            formData.append('contact', contact);
            formData.append('location', location);

            fetch('login.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        redirectUser(data.role);
                    } else {
                        showAjaxError(data.message);
                        closeSocialModal();
                    }
                })
                .catch(err => {
                    showAjaxError("Could not save delivery details. Please try again.");
                    closeSocialModal();
                });
        }

        // Action Redirection matching specified rules
        function redirectUser(role) {
            if (role === 'admin') {
                window.location.href = 'manage_restaurant_2v6.php';
            } else if (role === 'cook') {
                window.location.href = 'chef.php';
            } else {
                window.location.href = 'profile.php';
            }
        }

        function showAjaxError(message) {
            const container = document.getElementById('ajax-error');
            container.innerText = message;
            container.classList.remove('hidden');
        }

        function clearErrors() {
            const container = document.getElementById('ajax-error');
            container.innerText = "";
            container.classList.add('hidden');
        }
    </script>
</body>

</html>