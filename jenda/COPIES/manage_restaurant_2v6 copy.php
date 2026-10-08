<?php
/**
 * manage_restaurant_2v6.php
 * Administrative Control Center for Jenda Restaurant Platform
 * Built with PHP, MySQL, and Tailwind CSS.
 */

// Start secure session
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jenda_restaurant_db');

// Attempt database connection
try {
    // Connect without DB name first to check/create it
    $pdo_init = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Now connect to the database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("<div style='font-family: sans-serif; padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin: 40px auto; max-width: 600px; border: 1px solid #fca5a5;'>
        <h3 style='margin-top: 0;'>Database Connection Failure</h3>
        <p>Could not connect to the database. Please verify your MySQL server is running and configuration is correct.</p>
        <code style='background: #fecaca; padding: 4px 8px; border-radius: 4px; display: block; overflow-x: auto;'>" . htmlspecialchars($e->getMessage()) . "</code>
    </div>");
}

// ----------------------------------------------------
// DATABASE INITIALIZATION (Image-compliant Schemas)
// ----------------------------------------------------
try {
    // 1. Foods Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `foods` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(150) NOT NULL,
        `category` varchar(100) NOT NULL,
        `price` decimal(10,2) NOT NULL,
        `picture_1` varchar(255) NOT NULL,
        `picture_2` varchar(255) DEFAULT NULL,
        `picture_3` varchar(255) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Drinks Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `drinks` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(150) NOT NULL,
        `category` varchar(100) NOT NULL,
        `price` decimal(10,2) NOT NULL,
        `picture_1` varchar(255) NOT NULL,
        `picture_2` varchar(255) DEFAULT NULL,
        `picture_3` varchar(255) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Orders Table (Matching exactly the enum/structure of uploaded image)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `customer_id` int(11) NOT NULL,
        `order_status` enum('pending','preparing','ready','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
        `payment_status` enum('unpaid','paid','refunded') DEFAULT 'unpaid',
        `payment_method` enum('cash_on_delivery','card','mobile_money') NOT NULL,
        `total_amount` decimal(10,2) NOT NULL,
        `delivery_address` text NOT NULL,
        `order_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Chefs Table for oversight feature
    $pdo->exec("CREATE TABLE IF NOT EXISTS `chefs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `specialty` varchar(100) DEFAULT 'General Chef',
        `status` enum('active','on_leave','suspended') DEFAULT 'active',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. Chef Activity Tracker
    $pdo->exec("CREATE TABLE IF NOT EXISTS `chef_activities` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `chef_id` int(11) NOT NULL,
        `order_id` int(11) NOT NULL,
        `activity_detail` varchar(255) NOT NULL,
        `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        PRIMARY KEY (`id`),
        FOREIGN KEY (`chef_id`) REFERENCES `chefs` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. Admins Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL UNIQUE,
        `password` varchar(255) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Insert default admin if table is empty (admin / admin123)
    $stmt = $pdo->query("SELECT COUNT(*) FROM `admins`");
    if ($stmt->fetchColumn() == 0) {
        $defaultPassword = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO `admins` (`username`, `password`) VALUES ('admin', ?)")->execute([$defaultPassword]);
    }

    // Insert some mock orders for display if table is empty
    $stmtOrders = $pdo->query("SELECT COUNT(*) FROM `orders`");
    if ($stmtOrders->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO `orders` (`customer_id`, `order_status`, `payment_status`, `payment_method`, `total_amount`, `delivery_address`) VALUES 
            (101, 'pending', 'unpaid', 'cash_on_delivery', 24.50, 'Apt 4B, Sunflower Valley Estates'),
            (102, 'preparing', 'paid', 'mobile_money', 12.99, 'Block C, Ring Road West'),
            (103, 'ready', 'paid', 'card', 45.00, 'House No. 12, Ocean View Crescent')");
    }

    // Insert some default Chefs if empty
    $stmtChefs = $pdo->query("SELECT COUNT(*) FROM `chefs`");
    if ($stmtChefs->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO `chefs` (`name`, `specialty`, `status`) VALUES 
            ('Chef Antonio', 'Italian & Pastas', 'active'),
            ('Chef Kwame', 'Traditional Grill & Desserts', 'active'),
            ('Chef Ling', 'Asian Fusion & Beverages', 'active')");
    }

} catch (PDOException $e) {
    die("Database initialization failed: " . htmlspecialchars($e->getMessage()));
}

// Ensure Uploads folder exists
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ----------------------------------------------------
// CONTROLLER LOGIC (Authentication & Actions)
// ----------------------------------------------------

$message = '';
$messageType = ''; // 'success' or 'error'

// Helper function to handle response message toast alerts
function setMessage($text, $type = 'success')
{
    $_SESSION['sys_message'] = $text;
    $_SESSION['sys_message_type'] = $type;
}

// Retrieve flash message from session if exists
if (isset($_SESSION['sys_message'])) {
    $message = $_SESSION['sys_message'];
    $messageType = $_SESSION['sys_message_type'];
    unset($_SESSION['sys_message']);
    unset($_SESSION['sys_message_type']);
}

// Admin Action Router
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle Authentication
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!empty($username) && !empty($password)) {
            $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `username` = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged'] = true;
                $_SESSION['admin_user'] = $admin['username'];
                $_SESSION['admin_id'] = $admin['id'];
                setMessage("Successfully logged into management portal.");
                header("Location: manage_restaurant_2v6.php");
                exit;
            } else {
                $message = "Invalid username or password configuration.";
                $messageType = "error";
            }
        } else {
            $message = "Please enter both credentials.";
            $messageType = "error";
        }
    }

    // Guard actions for logged-in users only
    if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {

        // --- ADD FOOD OR DRINK ITEM ---
        if ($action === 'add_item') {
            $table = $_POST['item_table'] ?? 'foods'; // 'foods' or 'drinks'
            $name = trim($_POST['name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            // File upload logic (Up to 3 pictures)
            $picture_paths = [null, null, null];
            $uploaded_ok = true;

            for ($i = 1; $i <= 3; $i++) {
                $file_key = "picture_" . $i;
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES[$file_key]['tmp_name'];
                    $original_name = basename($_FILES[$file_key]['name']);
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                    // Validate extension
                    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (in_array($ext, $allowed)) {
                        $new_filename = uniqid('item_', true) . '_' . $i . '.' . $ext;
                        $destination = $uploadDir . '/' . $new_filename;
                        if (move_uploaded_file($tmp_name, $destination)) {
                            $picture_paths[$i - 1] = 'uploads/' . $new_filename;
                        } else {
                            $uploaded_ok = false;
                        }
                    } else {
                        $uploaded_ok = false;
                        setMessage("Invalid file extension for Picture $i. Allowed: JPG, PNG, WEBP, GIF.", "error");
                        break;
                    }
                }
            }

            // Picture 1 is Mandatory on brand-new upload
            if ($uploaded_ok && empty($picture_paths[0])) {
                $uploaded_ok = false;
                setMessage("Primary Picture (Picture 1) is required to register items.", "error");
            }

            if ($uploaded_ok && !empty($name) && !empty($category) && $price > 0) {
                try {
                    $sql = "INSERT INTO `$table` (`name`, `category`, `price`, `picture_1`, `picture_2`, `picture_3`, `description`) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$name, $category, $price, $picture_paths[0], $picture_paths[1], $picture_paths[2], $description]);
                    setMessage("Successfully created new item in " . htmlspecialchars($table) . " catalog.");
                    header("Location: manage_restaurant_2v6.php?tab=" . ($table === 'foods' ? 'food' : 'drinks'));
                    exit;
                } catch (PDOException $e) {
                    setMessage("Error saving item: " . $e->getMessage(), "error");
                }
            } else if ($uploaded_ok) {
                setMessage("Please fill out Name, Category, and positive numeric price values.", "error");
            }
        }

        // --- UPDATE FOOD OR DRINK ITEM ---
        if ($action === 'update_item') {
            $table = $_POST['item_table'] ?? 'foods';
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($id > 0 && !empty($name) && !empty($category) && $price > 0) {
                try {
                    // Fetch current item pictures to retain if no new upload
                    $stmt_current = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
                    $stmt_current->execute([$id]);
                    $current_item = $stmt_current->fetch();

                    if ($current_item) {
                        $picture_paths = [
                            $current_item['picture_1'],
                            $current_item['picture_2'],
                            $current_item['picture_3']
                        ];

                        // Process file uploads for updates
                        for ($i = 1; $i <= 3; $i++) {
                            $file_key = "picture_" . $i;
                            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                                $tmp_name = $_FILES[$file_key]['tmp_name'];
                                $original_name = basename($_FILES[$file_key]['name']);
                                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                                if (in_array($ext, $allowed)) {
                                    $new_filename = uniqid('item_update_', true) . '_' . $i . '.' . $ext;
                                    $destination = $uploadDir . '/' . $new_filename;
                                    if (move_uploaded_file($tmp_name, $destination)) {
                                        // Unlink old picture if exists
                                        if (!empty($picture_paths[$i - 1]) && file_exists(__DIR__ . '/' . $picture_paths[$i - 1])) {
                                            @unlink(__DIR__ . '/' . $picture_paths[$i - 1]);
                                        }
                                        $picture_paths[$i - 1] = 'uploads/' . $new_filename;
                                    }
                                }
                            }
                        }

                        // SQL Update Query
                        $sql = "UPDATE `$table` SET 
                                `name` = ?, 
                                `category` = ?, 
                                `price` = ?, 
                                `picture_1` = ?, 
                                `picture_2` = ?, 
                                `picture_3` = ?, 
                                `description` = ?
                                WHERE `id` = ?";
                        $stmt_update = $pdo->prepare($sql);
                        $stmt_update->execute([$name, $category, $price, $picture_paths[0], $picture_paths[1], $picture_paths[2], $description, $id]);

                        setMessage("Catalog item successfully updated.");
                        header("Location: manage_restaurant_2v6.php?tab=" . ($table === 'foods' ? 'food' : 'drinks'));
                        exit;
                    } else {
                        setMessage("Item not found.", "error");
                    }
                } catch (PDOException $e) {
                    setMessage("Error updating item: " . $e->getMessage(), "error");
                }
            } else {
                setMessage("Please fill out all mandatory fields correctly.", "error");
            }
        }

        // --- DELETE FOOD OR DRINK ITEM ---
        if ($action === 'delete_item') {
            $table = $_POST['item_table'] ?? 'foods';
            $id = intval($_POST['id'] ?? 0);

            if ($id > 0) {
                try {
                    // Delete files physically from uploads
                    $stmt_current = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
                    $stmt_current->execute([$id]); // FIXED: Changed [id] to [$id]
                    $current_item = $stmt_current->fetch();
                    if ($current_item) {
                        for ($i = 1; $i <= 3; $i++) {
                            $pic = $current_item['picture_' . $i];
                            if (!empty($pic) && file_exists(__DIR__ . '/' . $pic)) {
                                @unlink(__DIR__ . '/' . $pic);
                            }
                        }
                    }

                    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
                    $stmt->execute([$id]);
                    setMessage("Successfully deleted item from catalog registry.");
                    header("Location: manage_restaurant_2v6.php?tab=" . ($table === 'foods' ? 'food' : 'drinks'));
                    exit;
                } catch (PDOException $e) {
                    setMessage("Unable to delete item: " . $e->getMessage(), "error");
                }
            }
        }

        // --- UPDATE ORDER DETAILS (STATUS & PAYMENT) ---
        if ($action === 'update_order') {
            $order_id = intval($_POST['order_id'] ?? 0);
            $order_status = $_POST['order_status'] ?? '';
            $payment_status = $_POST['payment_status'] ?? '';

            if ($order_id > 0 && !empty($order_status) && !empty($payment_status)) {
                try {
                    $stmt = $pdo->prepare("UPDATE `orders` SET `order_status` = ?, `payment_status` = ? WHERE `id` = ?");
                    $stmt->execute([$order_status, $payment_status, $order_id]);
                    setMessage("Order #$order_id updated successfully.");
                    header("Location: manage_restaurant_2v6.php?tab=orders");
                    exit;
                } catch (PDOException $e) {
                    setMessage("Unable to update order state: " . $e->getMessage(), "error");
                }
            }
        }

        // --- ADD NEW CHEF ---
        if ($action === 'add_chef') {
            $name = trim($_POST['name'] ?? '');
            $specialty = trim($_POST['specialty'] ?? 'General Chef');

            if (!empty($name)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO `chefs` (`name`, `specialty`, `status`) VALUES (?, ?, 'active')");
                    $stmt->execute([$name, $specialty]);
                    setMessage("Chef successfully onboarded.");
                    header("Location: manage_restaurant_2v6.php?tab=chefs");
                    exit;
                } catch (PDOException $e) {
                    setMessage("Failed to add Chef: " . $e->getMessage(), "error");
                }
            }
        }

        // --- ASSIGN ORDER TO CHEF (LOG ACTIVITY) ---
        if ($action === 'log_chef_activity') {
            $chef_id = intval($_POST['chef_id'] ?? 0);
            $order_id = intval($_POST['order_id'] ?? 0);
            $activity = trim($_POST['activity_detail'] ?? '');

            if ($chef_id > 0 && $order_id > 0 && !empty($activity)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO `chef_activities` (`chef_id`, `order_id`, `activity_detail`) VALUES (?, ?, ?)");
                    $stmt->execute([$chef_id, $order_id, $activity]);

                    // Mark order status to preparing automatically if not already
                    $stmt_ord = $pdo->prepare("UPDATE `orders` SET `order_status` = 'preparing' WHERE `id` = ?");
                    $stmt_ord->execute([$order_id]);

                    setMessage("Activity assigned to chef successfully. Order status synchronized.");
                    header("Location: manage_restaurant_2v6.php?tab=chefs");
                    exit;
                } catch (PDOException $e) {
                    setMessage("Failed to register chef activity: " . $e->getMessage(), "error");
                }
            }
        }

        // --- UPDATE SECURITY PASSWORD ---
        if ($action === 'change_password') {
            $current_pw = $_POST['current_password'] ?? '';
            $new_pw = $_POST['new_password'] ?? '';
            $confirm_pw = $_POST['confirm_password'] ?? '';

            if (!empty($current_pw) && !empty($new_pw) && !empty($confirm_pw)) {
                if ($new_pw !== $confirm_pw) {
                    setMessage("New password and confirmation do not match.", "error");
                } else {
                    // Fetch admin
                    $admin_id = $_SESSION['admin_id'];
                    $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `id` = ?");
                    $stmt->execute([$admin_id]);
                    $admin = $stmt->fetch();

                    if ($admin && password_verify($current_pw, $admin['password'])) {
                        $hashed_new = password_hash($new_pw, PASSWORD_BCRYPT);
                        $stmt_up = $pdo->prepare("UPDATE `admins` SET `password` = ? WHERE `id` = ?");
                        $stmt_up->execute([$hashed_new, $admin_id]);
                        setMessage("Security password changed successfully.");
                        header("Location: manage_restaurant_2v6.php?tab=settings");
                        exit;
                    } else {
                        setMessage("Current password verified is incorrect.", "error");
                    }
                }
            } else {
                setMessage("Please complete all password configuration fields.", "error");
            }
        }
    }
}

// Handle GET Actions (e.g., Logout)
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: manage_restaurant_2v6.php");
    exit;
}

// Current tab context from GET query parameter
$current_tab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jenda Restro Admin Hub</title>
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    colors: {
                        // Brand Primary #ff7e00 mapping
                        amber: {
                            50: '#fffcf0',
                            100: '#ffeed6',
                            200: '#ffd099',
                            300: '#ffb25c',
                            400: '#ff951f',
                            500: '#ff7e00', // Brand Main Color
                            600: '#e06b00',
                            700: '#b85400',
                            800: '#8f3f00',
                            900: '#662b00',
                        },
                        // Charcoal Black Custom Mappings
                        charcoal: {
                            800: '#1c1c1e',
                            900: '#111112',
                            950: '#0a0a0b',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons via CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>

<body class="h-full text-slate-800 antialiased flex flex-col bg-slate-50">

    <!-- LOGIN SCREEN -->
    <?php if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true): ?>
        <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-charcoal-900 bg-cover bg-blend-overlay bg-center"
            style="background-image: url('https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=1920&q=80')">
            <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
                <div
                    class="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-amber-500 text-white shadow-xl shadow-amber-500/20 mb-4">
                    <i data-lucide="chef-hat" class="h-9 w-9"></i>
                </div>
                <h2 class="text-3xl font-extrabold text-white tracking-tight">Jenda Restaurant</h2>
                <p class="mt-2 text-sm text-slate-300">Administrative Central Control Panel</p>
            </div>

            <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
                <div class="bg-white/95 backdrop-blur-md py-8 px-6 shadow-2xl rounded-3xl border border-white/20 sm:px-10">
                    <?php if (!empty($message)): ?>
                        <div
                            class="mb-4 p-4 rounded-xl border flex items-start gap-3 <?php echo $messageType === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'; ?>">
                            <i data-lucide="<?php echo $messageType === 'error' ? 'alert-circle' : 'check-circle'; ?>"
                                class="h-5 w-5 shrink-0 mt-0.5"></i>
                            <span class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="manage_restaurant_2v6.php" method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="login">

                        <div>
                            <label for="username"
                                class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Username</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="user" class="h-4 w-4"></i>
                                </div>
                                <input type="text" id="username" name="username" required value="admin"
                                    class="block w-full pl-10 pr-3 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-sm transition-all duration-200"
                                    placeholder="Enter admin username">
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between items-center">
                                <label for="password"
                                    class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Password</label>
                                <span class="text-xs text-slate-400 select-none">Default: admin123</span>
                            </div>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="lock" class="h-4 w-4"></i>
                                </div>
                                <input type="password" id="password" name="password" required value="admin123"
                                    class="block w-full pl-10 pr-3 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-sm transition-all duration-200"
                                    placeholder="••••••••">
                            </div>
                        </div>

                        <div>
                            <button type="submit"
                                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-md text-sm font-semibold text-white bg-amber-500 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all duration-200 cursor-pointer">
                                Access Administrator Console
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MAIN DASHBOARD SCREEN (AUTHENTICATED) -->
    <?php else: ?>

        <!-- Top Header Navigation Bar -->
        <header class="bg-charcoal-900 border-b border-charcoal-800 sticky top-0 z-40 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 flex items-center gap-3">
                            <div
                                class="h-10 w-10 bg-amber-500 rounded-xl flex items-center justify-center text-white shadow-md shadow-amber-500/20">
                                <i data-lucide="chef-hat" class="h-6 w-6"></i>
                            </div>
                            <div>
                                <span class="text-lg font-bold tracking-tight text-white block leading-tight">Jenda
                                    Admin</span>
                                <span class="text-xs font-semibold text-amber-500 uppercase tracking-widest block">Restro
                                    Center</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div
                            class="flex items-center gap-2 px-3 py-1.5 bg-charcoal-800 rounded-lg text-slate-300 text-sm font-medium border border-charcoal-700">
                            <i data-lucide="user-check" class="h-4 w-4 text-amber-500"></i>
                            <span>Admin Session: <strong
                                    class="text-white"><?php echo htmlspecialchars($_SESSION['admin_user']); ?></strong></span>
                        </div>
                        <a href="manage_restaurant_2v6.php?logout=1"
                            class="inline-flex items-center gap-2 bg-rose-950/40 hover:bg-rose-900/60 text-rose-400 hover:text-rose-300 transition px-3.5 py-1.5 rounded-lg text-sm font-semibold cursor-pointer border border-rose-900/50">
                            <i data-lucide="log-out" class="h-4 w-4"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col md:flex-row gap-8">

            <!-- Sidebar Navigation Menu -->
            <aside class="w-full md:w-64 shrink-0">
                <nav class="space-y-1.5 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Operational Console
                    </div>

                    <a href="manage_restaurant_2v6.php?tab=dashboard"
                        class="group flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition duration-150 <?php echo $current_tab === 'dashboard' ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                        <i data-lucide="layout-dashboard"
                            class="mr-3 h-5 w-5 shrink-0 <?php echo $current_tab === 'dashboard' ? 'text-amber-500' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <span class="truncate">Live Dashboard</span>
                    </a>

                    <a href="manage_restaurant_2v6.php?tab=food"
                        class="group flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition duration-150 <?php echo $current_tab === 'food' ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                        <i data-lucide="pizza"
                            class="mr-3 h-5 w-5 shrink-0 <?php echo $current_tab === 'food' ? 'text-amber-500' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <span class="truncate">Food Registry</span>
                    </a>

                    <a href="manage_restaurant_2v6.php?tab=drinks"
                        class="group flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition duration-150 <?php echo $current_tab === 'drinks' ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                        <i data-lucide="cup-soda"
                            class="mr-3 h-5 w-5 shrink-0 <?php echo $current_tab === 'drinks' ? 'text-amber-500' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <span class="truncate">Drink Registry</span>
                    </a>

                    <a href="manage_restaurant_2v6.php?tab=orders"
                        class="group flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition duration-150 <?php echo $current_tab === 'orders' ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                        <i data-lucide="shopping-bag"
                            class="mr-3 h-5 w-5 shrink-0 <?php echo $current_tab === 'orders' ? 'text-amber-500' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <span class="truncate">Order Dispatch</span>
                        <?php
                        $pending_count = $pdo->query("SELECT COUNT(*) FROM `orders` WHERE `order_status` IN ('pending', 'preparing', 'ready')")->fetchColumn();
                        if ($pending_count > 0):
                            ?>
                            <span
                                class="ml-auto bg-amber-500 text-white rounded-full px-2 py-0.5 text-xs font-bold"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>

                    <div class="pt-4 px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Human Resources
                    </div>

                    <a href="manage_restaurant_2v6.php?tab=chefs"
                        class="group flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition duration-150 <?php echo $current_tab === 'chefs' ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                        <i data-lucide="chef-hat"
                            class="mr-3 h-5 w-5 shrink-0 <?php echo $current_tab === 'chefs' ? 'text-amber-500' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <span class="truncate">Chef Activities</span>
                    </a>

                    <div class="pt-4 px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Access Controls
                    </div>

                    <a href="manage_restaurant_2v6.php?tab=settings"
                        class="group flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition duration-150 <?php echo $current_tab === 'settings' ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                        <i data-lucide="shield-check"
                            class="mr-3 h-5 w-5 shrink-0 <?php echo $current_tab === 'settings' ? 'text-amber-500' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <span class="truncate">Security Gate</span>
                    </a>
                </nav>
            </aside>

            <!-- Main Display Content Body -->
            <main class="flex-1 min-w-0">

                <!-- Toast notification messages -->
                <?php if (!empty($message)): ?>
                    <div
                        class="mb-6 p-4 rounded-xl border flex items-start gap-3 shadow-sm <?php echo $messageType === 'error' ? 'bg-red-50 text-red-800 border-red-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200'; ?>">
                        <i data-lucide="<?php echo $messageType === 'error' ? 'alert-triangle' : 'check-circle-2'; ?>"
                            class="h-5 w-5 shrink-0 mt-0.5 <?php echo $messageType === 'error' ? 'text-red-500' : 'text-emerald-500'; ?>"></i>
                        <div class="flex-1">
                            <p class="text-sm font-semibold"><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ========================================================================= -->
                <!-- TAB: LIVE DASHBOARD OVERVIEW -->
                <!-- ========================================================================= -->
                <?php if ($current_tab === 'dashboard'): ?>
                    <?php
                    // Compute statistics
                    $food_count = $pdo->query("SELECT COUNT(*) FROM `foods`")->fetchColumn();
                    $drink_count = $pdo->query("SELECT COUNT(*) FROM `drinks`")->fetchColumn();
                    $order_count = $pdo->query("SELECT COUNT(*) FROM `orders`")->fetchColumn();
                    $revenue = $pdo->query("SELECT SUM(total_amount) FROM `orders` WHERE `payment_status` = 'paid'")->fetchColumn() ?? 0;
                    ?>
                    <div class="space-y-6">
                        <div class="border-b border-slate-200 pb-5">
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Live Management Dashboard</h1>
                            <p class="mt-1 text-sm text-slate-500">Real-time telemetry and overview metrics across all catalog
                                units and physical customer transactions.</p>
                        </div>

                        <!-- Statistics Cards Grid -->
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            <div
                                class="bg-white overflow-hidden shadow-sm rounded-2xl border border-slate-200 p-5 flex items-center gap-4">
                                <div class="p-3.5 bg-amber-50 rounded-xl text-amber-500">
                                    <i data-lucide="pizza" class="h-7 w-7"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Foods Count</p>
                                    <p class="text-2xl font-extrabold text-slate-900"><?php echo $food_count; ?> Varieties</p>
                                </div>
                            </div>

                            <div
                                class="bg-white overflow-hidden shadow-sm rounded-2xl border border-slate-200 p-5 flex items-center gap-4">
                                <div class="p-3.5 bg-sky-50 rounded-xl text-sky-500">
                                    <i data-lucide="cup-soda" class="h-7 w-7"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Drinks Count</p>
                                    <p class="text-2xl font-extrabold text-slate-900"><?php echo $drink_count; ?> Options</p>
                                </div>
                            </div>

                            <div
                                class="bg-white overflow-hidden shadow-sm rounded-2xl border border-slate-200 p-5 flex items-center gap-4">
                                <div class="p-3.5 bg-indigo-50 rounded-xl text-indigo-500">
                                    <i data-lucide="shopping-bag" class="h-7 w-7"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Total Orders</p>
                                    <p class="text-2xl font-extrabold text-slate-900"><?php echo $order_count; ?> Placed</p>
                                </div>
                            </div>

                            <div
                                class="bg-white overflow-hidden shadow-sm rounded-2xl border border-slate-200 p-5 flex items-center gap-4">
                                <div class="p-3.5 bg-emerald-50 rounded-xl text-emerald-500">
                                    <i data-lucide="dollar-sign" class="h-7 w-7"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Paid Sales</p>
                                    <p class="text-2xl font-extrabold text-slate-900">$<?php echo number_format($revenue, 2); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Layout of Quick Actions & Recent Orders -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                            <!-- Recent Activity / Info -->
                            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 lg:col-span-2">
                                <div class="flex justify-between items-center mb-4">
                                    <h2 class="text-lg font-bold text-slate-900">Latest Active Transactions</h2>
                                    <a href="manage_restaurant_2v6.php?tab=orders"
                                        class="text-xs font-semibold text-amber-500 hover:text-amber-600 uppercase tracking-wider flex items-center gap-1">
                                        <span>Manage Dispatches</span>
                                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                                    </a>
                                </div>

                                <div class="flow-root">
                                    <ul role="list" class="-my-5 divide-y divide-slate-100">
                                        <?php
                                        $latest_orders = $pdo->query("SELECT * FROM `orders` ORDER BY `order_date` DESC LIMIT 4")->fetchAll();
                                        if (empty($latest_orders)):
                                            ?>
                                            <li class="py-5 text-center text-slate-400 text-sm">No transaction occurrences currently
                                                saved.</li>
                                        <?php else:
                                            foreach ($latest_orders as $order): ?>
                                                <li class="py-4">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center gap-3">
                                                            <div
                                                                class="h-9 w-9 bg-slate-100 rounded-lg flex items-center justify-center text-slate-600 font-bold text-sm">
                                                                #<?php echo $order['id']; ?>
                                                            </div>
                                                            <div>
                                                                <p class="text-sm font-semibold text-slate-900">Cust ID:
                                                                    <?php echo htmlspecialchars($order['customer_id']); ?></p>
                                                                <p class="text-xs text-slate-400">
                                                                    <?php echo date('M d, H:i A', strtotime($order['order_date'])); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-3">
                                                            <span
                                                                class="text-sm font-bold text-slate-900">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                                            <!-- Status badges -->
                                                            <?php
                                                            $statusColor = 'bg-slate-100 text-slate-800';
                                                            if ($order['order_status'] === 'pending')
                                                                $statusColor = 'bg-amber-100 text-amber-800';
                                                            else if ($order['order_status'] === 'preparing')
                                                                $statusColor = 'bg-indigo-100 text-indigo-800';
                                                            else if ($order['order_status'] === 'ready')
                                                                $statusColor = 'bg-purple-100 text-purple-800';
                                                            else if ($order['order_status'] === 'out_for_delivery')
                                                                $statusColor = 'bg-sky-100 text-sky-800';
                                                            else if ($order['order_status'] === 'delivered')
                                                                $statusColor = 'bg-emerald-100 text-emerald-800';
                                                            else if ($order['order_status'] === 'cancelled')
                                                                $statusColor = 'bg-rose-100 text-rose-800';
                                                            ?>
                                                            <span
                                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?php echo $statusColor; ?>">
                                                                <?php echo htmlspecialchars($order['order_status']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <!-- System Configuration summary (Charcoal and Orange style) -->
                            <div
                                class="bg-gradient-to-br from-charcoal-900 to-charcoal-800 text-white rounded-2xl p-6 shadow-md flex flex-col justify-between border border-charcoal-800">
                                <div>
                                    <h3 class="text-lg font-bold">Quick Utility Guides</h3>
                                    <p class="text-xs text-slate-300 mt-1">Easily configure systems and view direct settings
                                        details.</p>

                                    <div class="mt-6 space-y-4">
                                        <div class="flex gap-3">
                                            <div
                                                class="h-8 w-8 rounded-lg bg-white/5 flex items-center justify-center shrink-0">
                                                <i data-lucide="image" class="h-4 w-4 text-amber-500"></i>
                                            </div>
                                            <div>
                                                <h4 class="text-sm font-semibold">Multiple Picture Support</h4>
                                                <p class="text-xs text-slate-400">You can add up to 3 optional pictures when
                                                    adding catalog items.</p>
                                            </div>
                                        </div>

                                        <div class="flex gap-3">
                                            <div
                                                class="h-8 w-8 rounded-lg bg-white/5 flex items-center justify-center shrink-0">
                                                <i data-lucide="user-cog" class="h-4 w-4 text-amber-500"></i>
                                            </div>
                                            <div>
                                                <h4 class="text-sm font-semibold">Kitchen Assigning</h4>
                                                <p class="text-xs text-slate-400">Assign any order with the status 'pending' to
                                                    a cook directly under Chefs section.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="mt-8 pt-4 border-t border-white/5 flex justify-between items-center text-xs text-slate-400">
                                    <span>Platform Version: 2.6v</span>
                                    <span class="text-amber-500 font-bold">● Active Online</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================================= -->
                    <!-- TAB: MANAGE FOOD CATALOG -->
                    <!-- ========================================================================= -->
                <?php elseif ($current_tab === 'food' || $current_tab === 'drinks'): ?>
                    <?php
                    $is_food = ($current_tab === 'food');
                    $table_name = $is_food ? 'foods' : 'drinks';
                    $items_list = $pdo->query("SELECT * FROM `$table_name` ORDER BY `id` DESC")->fetchAll();
                    ?>
                    <div class="space-y-6">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5 gap-4">
                            <div>
                                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                                    <?php echo $is_food ? 'Food' : 'Drinks'; ?> Catalog Inventory</h1>
                                <p class="mt-1 text-sm text-slate-500">Register new recipes, configure multi-picture arrays,
                                    adjust billing pricing, and purge retired inventory elements.</p>
                            </div>
                            <button onclick="toggleModal('item-modal-add')"
                                class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 px-4 rounded-xl text-sm transition-all duration-150 cursor-pointer shadow-md shadow-amber-500/10">
                                <i data-lucide="plus-circle" class="h-5 w-5"></i>
                                <span>Add New <?php echo $is_food ? 'Food' : 'Drink'; ?></span>
                            </button>
                        </div>

                        <!-- Catalog Items List Grid -->
                        <?php if (empty($items_list)): ?>
                            <div class="text-center py-16 bg-white border border-dashed border-slate-300 rounded-2xl">
                                <div
                                    class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-slate-100 text-slate-400 mb-3">
                                    <i data-lucide="package-search" class="h-6 w-6"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-900">No items registered</h3>
                                <p class="mt-1 text-xs text-slate-500">Create entries to start supplying restaurant dishes.</p>
                                <button onclick="toggleModal('item-modal-add')"
                                    class="mt-4 inline-flex items-center text-xs font-semibold text-amber-500 hover:text-amber-600 uppercase tracking-widest gap-1">
                                    <span>Onboard items now</span>
                                    <i data-lucide="plus" class="h-3 w-3"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                <?php foreach ($items_list as $item): ?>
                                    <div
                                        class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm flex flex-col justify-between group transition hover:border-slate-300">
                                        <div>
                                            <!-- Image Display Slider Mock-up -->
                                            <div class="relative h-44 bg-slate-100 overflow-hidden select-none">
                                                <img src="<?php echo !empty($item['picture_1']) ? htmlspecialchars($item['picture_1']) : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80'; ?>"
                                                    alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                    id="gallery-<?php echo $item['id']; ?>"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-500">

                                                <!-- Mini Gallery Indicators -->
                                                <div
                                                    class="absolute bottom-2 left-2 flex gap-1.5 bg-charcoal-900/85 backdrop-blur-md px-2 py-1 rounded-md">
                                                    <button
                                                        onclick="changeDisplayImage('gallery-<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['picture_1']); ?>')"
                                                        class="w-3.5 h-3.5 rounded-full bg-white border border-black/10 transition"
                                                        title="Pic 1"></button>
                                                    <?php if (!empty($item['picture_2'])): ?>
                                                        <button
                                                            onclick="changeDisplayImage('gallery-<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['picture_2']); ?>')"
                                                            class="w-3.5 h-3.5 rounded-full bg-slate-400 hover:bg-white border border-black/10 transition"
                                                            title="Pic 2"></button>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['picture_3'])): ?>
                                                        <button
                                                            onclick="changeDisplayImage('gallery-<?php echo $item['id']; ?>', '<?php echo htmlspecialchars($item['picture_3']); ?>')"
                                                            class="w-3.5 h-3.5 rounded-full bg-slate-400 hover:bg-white border border-black/10 transition"
                                                            title="Pic 3"></button>
                                                    <?php endif; ?>
                                                </div>

                                                <div
                                                    class="absolute top-2 right-2 bg-white/95 backdrop-blur-sm px-3 py-1 rounded-xl shadow text-sm font-bold text-slate-900 border border-slate-100">
                                                    $<?php echo number_format($item['price'], 2); ?>
                                                </div>
                                            </div>

                                            <!-- Content Block -->
                                            <div class="p-5">
                                                <div class="flex justify-between items-start gap-2">
                                                    <h3 class="text-base font-bold text-slate-900 truncate">
                                                        <?php echo htmlspecialchars($item['name']); ?></h3>
                                                    <span
                                                        class="inline-flex shrink-0 items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                                        <?php echo htmlspecialchars($item['category']); ?>
                                                    </span>
                                                </div>
                                                <p class="mt-2 text-xs text-slate-500 line-clamp-3 leading-relaxed">
                                                    <?php echo !empty($item['description']) ? htmlspecialchars($item['description']) : '<em>No description details filled out for this recipe entry.</em>'; ?>
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Bottom Action footer -->
                                        <div class="bg-slate-50 px-5 py-3 border-t border-slate-100 flex gap-2 justify-end">
                                            <!-- Edit Trigger -->
                                            <button
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>, '<?php echo $table_name; ?>')"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer">
                                                <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                                                <span>Modify</span>
                                            </button>

                                            <!-- Delete Trigger Form -->
                                            <form action="manage_restaurant_2v6.php" method="POST"
                                                onsubmit="return confirm('Confirm deletion of this menu entry? This deletes images on disk permanently.');">
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="item_table" value="<?php echo $table_name; ?>">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                <button type="submit"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-lg text-xs font-semibold transition cursor-pointer border border-rose-100">
                                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                                    <span>Purge</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ADD ITEM MODAL -->
                    <div id="item-modal-add"
                        class="hidden fixed inset-0 z-50 overflow-y-auto bg-charcoal-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                        <div
                            class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-100 transform transition-all">
                            <div
                                class="bg-charcoal-900 text-white px-6 py-4 flex justify-between items-center border-b border-charcoal-800">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="plus-circle" class="h-5 w-5 text-amber-500"></i>
                                    <h3 class="text-base font-bold">Register Menu Dish</h3>
                                </div>
                                <button onclick="toggleModal('item-modal-add')"
                                    class="text-slate-400 hover:text-white cursor-pointer transition">
                                    <i data-lucide="x" class="h-5 w-5"></i>
                                </button>
                            </div>
                            <form action="manage_restaurant_2v6.php" method="POST" enctype="multipart/form-data"
                                class="p-6 space-y-4">
                                <input type="hidden" name="action" value="add_item">
                                <input type="hidden" name="item_table" value="<?php echo $table_name; ?>">

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Item
                                            Title / Name *</label>
                                        <input type="text" name="name" required
                                            class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Food
                                            Category Label *</label>
                                        <input type="text" name="category" required
                                            placeholder="e.g. Appetizers, Soups, Desserts"
                                            class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Price
                                        Billing ($) *</label>
                                    <input type="number" step="0.01" name="price" required
                                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <!-- Photo Array Section -->
                                <div class="p-4 bg-slate-50 rounded-xl space-y-3 border border-slate-100">
                                    <span class="block text-xs font-bold uppercase tracking-widest text-slate-600 mb-1">Picture
                                        Portfolio</span>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500 mb-1">Primary Display Picture *
                                            (Required)</label>
                                        <input type="file" name="picture_1" required
                                            class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 cursor-pointer">
                                    </div>
                                    <hr class="border-slate-200">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500 mb-1">Secondary Angle Picture
                                            (Optional)</label>
                                        <input type="file" name="picture_2"
                                            class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                                    </div>
                                    <hr class="border-slate-200">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500 mb-1">Alternate Table Angle
                                            Picture (Optional)</label>
                                        <input type="file" name="picture_3"
                                            class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Ingredients
                                        or Culinary Description (Optional)</label>
                                    <textarea name="description" rows="3"
                                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500"
                                        placeholder="Highlight culinary origins, organic components, or allergies..."></textarea>
                                </div>

                                <div class="flex gap-3 pt-2">
                                    <button type="button" onclick="toggleModal('item-modal-add')"
                                        class="flex-1 py-2.5 bg-slate-100 text-slate-700 font-semibold rounded-xl text-sm hover:bg-slate-200 transition cursor-pointer text-center">Cancel</button>
                                    <button type="submit"
                                        class="flex-1 py-2.5 bg-amber-500 text-white font-semibold rounded-xl text-sm hover:bg-amber-600 transition cursor-pointer text-center">Save
                                        Item</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- EDIT ITEM MODAL -->
                    <div id="item-modal-edit"
                        class="hidden fixed inset-0 z-50 overflow-y-auto bg-charcoal-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                        <div
                            class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-100 transform transition-all">
                            <div
                                class="bg-charcoal-900 text-white px-6 py-4 flex justify-between items-center border-b border-charcoal-800">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="edit-3" class="h-5 w-5 text-amber-500"></i>
                                    <h3 class="text-base font-bold">Modify Registry Dish</h3>
                                </div>
                                <button onclick="toggleModal('item-modal-edit')"
                                    class="text-slate-400 hover:text-white cursor-pointer transition">
                                    <i data-lucide="x" class="h-5 w-5"></i>
                                </button>
                            </div>
                            <form action="manage_restaurant_2v6.php" method="POST" enctype="multipart/form-data"
                                class="p-6 space-y-4">
                                <input type="hidden" name="action" value="update_item">
                                <input type="hidden" name="item_table" id="edit-table">
                                <input type="hidden" name="id" id="edit-id">

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Item
                                            Title / Name *</label>
                                        <input type="text" name="name" id="edit-name" required
                                            class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Category
                                            Label *</label>
                                        <input type="text" name="category" id="edit-category" required
                                            class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Price
                                        Billing ($) *</label>
                                    <input type="number" step="0.01" name="price" id="edit-price" required
                                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <!-- Photo Array Section with edit instruction labels -->
                                <div class="p-4 bg-slate-50 rounded-xl space-y-3 border border-slate-100">
                                    <span class="block text-xs font-bold uppercase tracking-widest text-slate-600 mb-1">Update
                                        Picture Assets</span>
                                    <p class="text-[10px] text-slate-400">Uploading new files will overwrite old stored images.
                                    </p>

                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500 mb-1">Primary Display
                                            Picture</label>
                                        <input type="file" name="picture_1"
                                            class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                                    </div>
                                    <hr class="border-slate-200">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500 mb-1">Secondary Angle
                                            Picture</label>
                                        <input type="file" name="picture_2"
                                            class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                                    </div>
                                    <hr class="border-slate-200">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500 mb-1">Alternate Table Angle
                                            Picture</label>
                                        <input type="file" name="picture_3"
                                            class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Ingredients
                                        & Description</label>
                                    <textarea name="description" id="edit-description" rows="3"
                                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500"></textarea>
                                </div>

                                <div class="flex gap-3 pt-2">
                                    <button type="button" onclick="toggleModal('item-modal-edit')"
                                        class="flex-1 py-2.5 bg-slate-100 text-slate-700 font-semibold rounded-xl text-sm hover:bg-slate-200 transition cursor-pointer text-center">Cancel</button>
                                    <button type="submit"
                                        class="flex-1 py-2.5 bg-amber-500 text-white font-semibold rounded-xl text-sm hover:bg-amber-600 transition cursor-pointer text-center">Update
                                        Details</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <script>
                        function changeDisplayImage(imgId, src) {
                            const img = document.getElementById(imgId);
                            if (img && src) {
                                img.src = src;
                            }
                        }

                        function openEditModal(item, tableName) {
                            document.getElementById('edit-table').value = tableName;
                            document.getElementById('edit-id').value = item.id;
                            document.getElementById('edit-name').value = item.name;
                            document.getElementById('edit-category').value = item.category;
                            document.getElementById('edit-price').value = item.price;
                            document.getElementById('edit-description').value = item.description || '';

                            toggleModal('item-modal-edit');
                        }
                    </script>

                    <!-- ========================================================================= -->
                    <!-- TAB: MANAGE ORDERS (DISPATCH ENGINE) -->
                    <!-- ========================================================================= -->
                <?php elseif ($current_tab === 'orders'): ?>
                    <?php
                    $orders_list = $pdo->query("SELECT * FROM `orders` ORDER BY `order_date` DESC")->fetchAll();
                    ?>
                    <div class="space-y-6">
                        <div class="border-b border-slate-200 pb-5">
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Order Dispatch Engine</h1>
                            <p class="mt-1 text-sm text-slate-500">Oversee ongoing culinary workflows, mark stages of prep,
                                deliver packages, and log customer payment states.</p>
                        </div>

                        <!-- Live Dispatch Grid -->
                        <?php if (empty($orders_list)): ?>
                            <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl">
                                <div
                                    class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-slate-100 text-slate-400 mb-3">
                                    <i data-lucide="shopping-cart" class="h-6 w-6"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-900">No active dispatches</h3>
                                <p class="mt-1 text-xs text-slate-500">When customers place food & drink orders, they populate here.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($orders_list as $order): ?>
                                    <div
                                        class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">

                                        <!-- Left details -->
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-3">
                                                <span class="text-xs font-bold bg-slate-100 text-slate-800 px-3 py-1 rounded-lg">ID:
                                                    #<?php echo $order['id']; ?></span>
                                                <span class="text-xs font-semibold text-slate-400">Placed:
                                                    <?php echo date('M d, Y - h:i A', strtotime($order['order_date'])); ?></span>
                                            </div>
                                            <div class="space-y-1">
                                                <p class="text-sm font-semibold text-slate-800">Customer Registered: <span
                                                        class="text-amber-600 font-bold">#<?php echo htmlspecialchars($order['customer_id']); ?></span>
                                                </p>
                                                <p class="text-xs text-slate-500 max-w-md leading-relaxed">
                                                    <strong class="text-slate-700">Delivery Address:</strong>
                                                    <?php echo htmlspecialchars($order['delivery_address']); ?>
                                                </p>
                                            </div>

                                            <!-- Sub Status Badge lists -->
                                            <div class="flex gap-2 pt-1">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200 capitalize">
                                                    Method: <?php echo str_replace('_', ' ', $order['payment_method']); ?>
                                                </span>
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold <?php echo $order['payment_status'] === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'; ?>">
                                                    Paid Status: <?php echo strtoupper($order['payment_status']); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Right pricing & Actions panel -->
                                        <div
                                            class="w-full lg:w-auto flex flex-col sm:flex-row lg:flex-col sm:items-center lg:items-end justify-between border-t lg:border-t-0 pt-4 lg:pt-0 gap-4">
                                            <div class="text-left lg:text-right">
                                                <span class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Total
                                                    Invoice Price</span>
                                                <span
                                                    class="text-xl font-extrabold text-slate-950">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                            </div>

                                            <!-- Update Form element inline -->
                                            <form action="manage_restaurant_2v6.php" method="POST"
                                                class="flex flex-wrap items-center gap-2">
                                                <input type="hidden" name="action" value="update_order">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">

                                                <div>
                                                    <select name="order_status"
                                                        class="text-xs bg-slate-100 border border-slate-200 rounded-lg px-2.5 py-2 font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500 cursor-pointer">
                                                        <option value="pending" <?php echo $order['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending Action</option>
                                                        <option value="preparing" <?php echo $order['order_status'] === 'preparing' ? 'selected' : ''; ?>>Preparing in Kitchen</option>
                                                        <option value="ready" <?php echo $order['order_status'] === 'ready' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                                        <option value="out_for_delivery" <?php echo $order['order_status'] === 'out_for_delivery' ? 'selected' : ''; ?>>Out for
                                                            Delivery</option>
                                                        <option value="delivered" <?php echo $order['order_status'] === 'delivered' ? 'selected' : ''; ?>>Package Delivered</option>
                                                        <option value="cancelled" <?php echo $order['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Order Cancelled</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <select name="payment_status"
                                                        class="text-xs bg-slate-100 border border-slate-200 rounded-lg px-2.5 py-2 font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500 cursor-pointer">
                                                        <option value="unpaid" <?php echo $order['payment_status'] === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                                        <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                        <option value="refunded" <?php echo $order['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                                    </select>
                                                </div>

                                                <button type="submit"
                                                    class="bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs px-4 py-2 rounded-lg transition cursor-pointer">
                                                    Update
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ========================================================================= -->
                    <!-- TAB: CHEF OVERSIGHT & ACTIVITIES -->
                    <!-- ========================================================================= -->
                <?php elseif ($current_tab === 'chefs'): ?>
                    <?php
                    $chefs = $pdo->query("SELECT * FROM `chefs` ORDER BY `name` ASC")->fetchAll();
                    $active_pending_orders = $pdo->query("SELECT `id`, `customer_id` FROM `orders` WHERE `order_status` IN ('pending', 'preparing') ORDER BY `id` DESC")->fetchAll();
                    ?>
                    <div class="space-y-6">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5 gap-4">
                            <div>
                                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Chef Oversight Console</h1>
                                <p class="mt-1 text-sm text-slate-500">Monitor culinary staff resources, register active kitchen
                                    assignments, and track logging timestamps.</p>
                            </div>
                            <button onclick="toggleModal('modal-add-chef')"
                                class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 px-4 rounded-xl text-sm transition-all duration-150 cursor-pointer shadow-sm">
                                <i data-lucide="plus" class="h-4 w-4"></i>
                                <span>Register New Chef</span>
                            </button>
                        </div>

                        <!-- Overview Layout -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                            <!-- Left Chef List Profile Cards -->
                            <div class="lg:col-span-1 space-y-4">
                                <h3 class="text-sm font-extrabold uppercase tracking-widest text-slate-400">Staff Kitchen Roster
                                </h3>

                                <?php if (empty($chefs)): ?>
                                    <div
                                        class="p-6 bg-white border border-slate-200 rounded-2xl text-center text-slate-400 text-xs">
                                        No active kitchen crew cataloged.</div>
                                <?php else:
                                    foreach ($chefs as $chef): ?>
                                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-3">
                                            <div class="flex justify-between items-start">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="h-10 w-10 bg-charcoal-900 text-white rounded-xl flex items-center justify-center font-bold text-sm">
                                                        <?php echo substr($chef['name'], 5, 2) ?: substr($chef['name'], 0, 2); ?>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-sm font-bold text-slate-900">
                                                            <?php echo htmlspecialchars($chef['name']); ?></h4>
                                                        <span
                                                            class="text-xs text-slate-400 block"><?php echo htmlspecialchars($chef['specialty']); ?></span>
                                                    </div>
                                                </div>
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800 capitalize">
                                                    <?php echo htmlspecialchars($chef['status']); ?>
                                                </span>
                                            </div>

                                            <!-- Assign Task Trigger Form -->
                                            <div class="border-t border-slate-100 pt-3">
                                                <span
                                                    class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Assign
                                                    Kitchen Prep Task</span>
                                                <?php if (empty($active_pending_orders)): ?>
                                                    <span class="block text-[11px] text-slate-400 italic">No pending orders to
                                                        assign.</span>
                                                <?php else: ?>
                                                    <form action="manage_restaurant_2v6.php" method="POST" class="space-y-2">
                                                        <input type="hidden" name="action" value="log_chef_activity">
                                                        <input type="hidden" name="chef_id" value="<?php echo $chef['id']; ?>">

                                                        <div class="flex gap-1.5">
                                                            <select name="order_id" required
                                                                class="flex-1 text-[11px] bg-slate-50 border border-slate-200 rounded-lg p-1.5 focus:outline-none focus:ring-1 focus:ring-amber-500 cursor-pointer">
                                                                <option value="">Choose Order ID...</option>
                                                                <?php foreach ($active_pending_orders as $o): ?>
                                                                    <option value="<?php echo $o['id']; ?>">Order #<?php echo $o['id']; ?> (Cust
                                                                        #<?php echo $o['customer_id']; ?>)</option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <input type="text" name="activity_detail" required
                                                                placeholder="e.g. Grilling steaks"
                                                                class="flex-1 text-[11px] bg-slate-50 border border-slate-200 rounded-lg p-1.5 focus:outline-none focus:ring-1 focus:ring-amber-500">
                                                            <button type="submit"
                                                                class="bg-amber-500 text-white font-bold text-xs p-1.5 rounded-lg hover:bg-amber-600 transition cursor-pointer shrink-0"
                                                                title="Assign">
                                                                Go
                                                            </button>
                                                        </div>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; endif; ?>
                            </div>

                            <!-- Right Live Activity Logging Grid -->
                            <div class="lg:col-span-2 space-y-4">
                                <h3 class="text-sm font-extrabold uppercase tracking-widest text-slate-400">Real-Time Kitchen
                                    Activity Timestamps</h3>

                                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                                    <div class="flow-root">
                                        <ul role="list" class="-mb-8">
                                            <?php
                                            $activities = $pdo->query("SELECT ca.*, c.name as chef_name FROM `chef_activities` ca JOIN `chefs` c ON ca.chef_id = c.id ORDER BY ca.timestamp DESC LIMIT 8")->fetchAll();
                                            if (empty($activities)):
                                                ?>
                                                <li class="py-12 text-center text-slate-400 text-xs">No culinary activity
                                                    registered. Use the prep panel to assign tasks to onboarded chefs.</li>
                                            <?php else:
                                                foreach ($activities as $act): ?>
                                                    <li>
                                                        <div class="relative pb-8">
                                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200"
                                                                aria-hidden="true"></span>
                                                            <div class="relative flex space-x-3">
                                                                <div>
                                                                    <span
                                                                        class="h-8 w-8 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center ring-8 ring-white">
                                                                        <i data-lucide="cooking-pot" class="h-4 w-4"></i>
                                                                    </span>
                                                                </div>
                                                                <div class="flex-1 min-w-0 pt-1.5 flex justify-between space-x-4">
                                                                    <div>
                                                                        <p class="text-xs text-slate-500">
                                                                            <strong
                                                                                class="text-slate-900 font-bold"><?php echo htmlspecialchars($act['chef_name']); ?></strong>
                                                                            assigned on Order <strong
                                                                                class="text-amber-600 font-bold">#<?php echo $act['order_id']; ?></strong>:
                                                                            <span
                                                                                class="text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md text-[11px] inline-block mt-1 font-medium"><?php echo htmlspecialchars($act['activity_detail']); ?></span>
                                                                        </p>
                                                                    </div>
                                                                    <div class="text-right text-xs whitespace-nowrap text-slate-400">
                                                                        <time
                                                                            datetime="<?php echo $act['timestamp']; ?>"><?php echo date('h:i A', strtotime($act['timestamp'])); ?></time>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ADD CHEF MODAL -->
                    <div id="modal-add-chef"
                        class="hidden fixed inset-0 z-50 overflow-y-auto bg-charcoal-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                        <div
                            class="bg-white rounded-2xl max-w-sm w-full overflow-hidden shadow-2xl border border-slate-100 transform transition-all">
                            <div
                                class="bg-charcoal-900 text-white px-6 py-4 flex justify-between items-center border-b border-charcoal-800">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="chef-hat" class="h-5 w-5 text-amber-500"></i>
                                    <h3 class="text-base font-bold">Register Chef Profile</h3>
                                </div>
                                <button onclick="toggleModal('modal-add-chef')"
                                    class="text-slate-400 hover:text-white cursor-pointer transition">
                                    <i data-lucide="x" class="h-5 w-5"></i>
                                </button>
                            </div>
                            <form action="manage_restaurant_2v6.php" method="POST" class="p-6 space-y-4">
                                <input type="hidden" name="action" value="add_chef">

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Full
                                        Chef Name *</label>
                                    <input type="text" name="name" required placeholder="e.g. Chef Oliver"
                                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Culinary
                                        Specialty</label>
                                    <input type="text" name="specialty" placeholder="e.g. Pastries, Roast Meats"
                                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <div class="flex gap-3 pt-2">
                                    <button type="button" onclick="toggleModal('modal-add-chef')"
                                        class="flex-1 py-2.5 bg-slate-100 text-slate-700 font-semibold rounded-xl text-sm hover:bg-slate-200 transition cursor-pointer text-center">Cancel</button>
                                    <button type="submit"
                                        class="flex-1 py-2.5 bg-amber-500 text-white font-semibold rounded-xl text-sm hover:bg-amber-600 transition cursor-pointer text-center">Onboard
                                        Chef</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ========================================================================= -->
                    <!-- TAB: SECURITY GATE (CHANGE PASSWORD) -->
                    <!-- ========================================================================= -->
                <?php elseif ($current_tab === 'settings'): ?>
                    <div class="space-y-6">
                        <div class="border-b border-slate-200 pb-5">
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Security Gate & Settings</h1>
                            <p class="mt-1 text-sm text-slate-500">Update admin login criteria, manage database authorizations,
                                and set password security parameters.</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm max-w-xl">
                            <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                                <i data-lucide="key" class="text-amber-500 h-5 w-5"></i>
                                <span>Update System Password</span>
                            </h2>

                            <form action="manage_restaurant_2v6.php" method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="change_password">

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Current
                                        Password Verified</label>
                                    <input type="password" name="current_password" required
                                        class="w-full text-sm border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Brand
                                        New Password</label>
                                    <input type="password" name="new_password" required
                                        class="w-full text-sm border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Confirm
                                        New Password Entry</label>
                                    <input type="password" name="confirm_password" required
                                        class="w-full text-sm border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                </div>

                                <div class="pt-2">
                                    <button type="submit"
                                        class="bg-amber-500 hover:bg-amber-600 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition cursor-pointer">
                                        Change Admin Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </main>
        </div>

        <!-- Page Footer -->
        <footer class="bg-white border-t border-slate-200 py-6 mt-auto">
            <div class="max-w-7xl mx-auto px-4 text-center text-xs text-slate-400">
                &copy; 2026 Jenda Restaurant Platform &bull; Built Securely using Standard PDO PHP Implementation.
            </div>
        </footer>

        <!-- Universal Modal toggler script -->
        <script>
            function toggleModal(id) {
                const modal = document.getElementById(id);
                if (modal) {
                    modal.classList.toggle('hidden');
                }
            }
        </script>
    <?php endif; ?>

    <!-- Initialize Lucide icons -->
    <script>
        lucide.createIcons();
    </script>
</body>

</html>