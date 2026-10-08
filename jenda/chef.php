<?php
/**
 * chef.php
 * Chef Control Panel for Jenda Restaurant Platform
 * Built with PHP, MySQL, and Tailwind CSS.
 */

// Start session
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jenda_restaurant_db');

// Attempt database connection
try {
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
// DATABASE UPGRADES (Ensure Tables exist cleanly)
// ----------------------------------------------------
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Ensure Reservations table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `reservations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `customer_id` int(11) NOT NULL,
        `reservation_name` varchar(150) NOT NULL,
        `reservation_date` date NOT NULL,
        `reservation_time` time NOT NULL,
        `guests` int(11) NOT NULL,
        `status` enum('pending','confirmed','denied') DEFAULT 'pending',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Ensure Chefs table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `chefs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `specialty` varchar(100) DEFAULT 'General Chef',
        `status` enum('active','on_leave','suspended') DEFAULT 'active',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Ensure orders has fulfilled_by column
    $checkCol = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'fulfilled_by'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `fulfilled_by` int(11) DEFAULT NULL;");
        $pdo->exec("ALTER TABLE `orders` ADD CONSTRAINT `fk_orders_chefs_chef_php` FOREIGN KEY (`fulfilled_by`) REFERENCES `chefs`(`id`) ON DELETE SET NULL;");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
} catch (PDOException $e) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    } catch (Exception $ex) {
    }
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

function setMessage($text, $type = 'success')
{
    $_SESSION['chef_message'] = $text;
    $_SESSION['chef_message_type'] = $type;
}

if (isset($_SESSION['chef_message'])) {
    $message = $_SESSION['chef_message'];
    $messageType = $_SESSION['chef_message_type'];
    unset($_SESSION['chef_message']);
    unset($_SESSION['chef_message_type']);
}

// Action Router
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle Chef profile log-in or quick register
    if ($action === 'select_profile') {
        $chef_id = intval($_POST['chef_id'] ?? 0);
        if ($chef_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM `chefs` WHERE `id` = ?");
            $stmt->execute([$chef_id]);
            $chef = $stmt->fetch();
            if ($chef) {
                if ($chef['status'] === 'suspended') {
                    setMessage("This account is currently suspended. Please contact the administrator.", "error");
                } else {
                    $_SESSION['chef_logged'] = true;
                    $_SESSION['chef_id'] = $chef['id'];
                    $_SESSION['chef_name'] = $chef['name'];
                    setMessage("Welcome back, " . htmlspecialchars($chef['name']) . "!");
                    header("Location: chef.php");
                    exit;
                }
            }
        }
    }

    if ($action === 'register_chef') {
        $name = trim($_POST['name'] ?? '');
        $specialty = trim($_POST['specialty'] ?? 'General Chef');
        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO `chefs` (`name`, `specialty`, `status`) VALUES (?, ?, 'active')");
            $stmt->execute([$name, $specialty]);
            $new_id = $pdo->lastInsertId();
            $_SESSION['chef_logged'] = true;
            $_SESSION['chef_id'] = $new_id;
            $_SESSION['chef_name'] = $name;
            setMessage("Chef profile created successfully!");
            header("Location: chef.php");
            exit;
        }
    }

    // Guard actions for authenticated Chefs
    if (isset($_SESSION['chef_logged']) && $_SESSION['chef_logged'] === true) {

        // --- UPDATE ORDER STATUS AND / OR CLAIM WORK ---
        if ($action === 'update_order') {
            $order_id = intval($_POST['order_id'] ?? 0);
            $new_status = $_POST['order_status'] ?? '';
            $claim = isset($_POST['claim_fulfillment']) && $_POST['claim_fulfillment'] == '1';

            if ($order_id > 0 && !empty($new_status)) {
                try {
                    if ($claim) {
                        $stmt = $pdo->prepare("UPDATE `orders` SET `order_status` = ?, `fulfilled_by` = ? WHERE `id` = ?");
                        $stmt->execute([$new_status, $_SESSION['chef_id'], $order_id]);
                        setMessage("Order #$order_id marked as " . strtoupper($new_status) . " and claimed by you.");
                    } else {
                        $stmt = $pdo->prepare("UPDATE `orders` SET `order_status` = ? WHERE `id` = ?");
                        $stmt->execute([$new_status, $order_id]);
                        setMessage("Order #$order_id status updated to " . strtoupper($new_status));
                    }
                    header("Location: chef.php?tab=orders");
                    exit;
                } catch (PDOException $e) {
                    setMessage("Error updating order: " . $e->getMessage(), "error");
                }
            }
        }

        // --- MANAGE RESERVATION BOOKINGS ---
        if ($action === 'update_reservation') {
            $res_id = intval($_POST['reservation_id'] ?? 0);
            $new_status = $_POST['status'] ?? '';

            if ($res_id > 0 && in_array($new_status, ['confirmed', 'denied'])) {
                try {
                    $stmt = $pdo->prepare("UPDATE `reservations` SET `status` = ? WHERE `id` = ?");
                    $stmt->execute([$new_status, $res_id]);
                    setMessage("Reservation #$res_id has been " . strtoupper($new_status));
                    header("Location: chef.php?tab=reservations");
                    exit;
                } catch (PDOException $e) {
                    setMessage("Error updating reservation: " . $e->getMessage(), "error");
                }
            }
        }

        // --- CATALOG: ADD ITEM ---
        if ($action === 'add_item') {
            $table = $_POST['item_table'] ?? 'foods';
            $name = trim($_POST['name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            $picture_paths = [null, null, null];
            $uploaded_ok = true;

            for ($i = 1; $i <= 3; $i++) {
                $file_key = "picture_" . $i;
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES[$file_key]['tmp_name'];
                    $original_name = basename($_FILES[$file_key]['name']);
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (in_array($ext, $allowed)) {
                        $new_filename = uniqid('chef_item_', true) . '_' . $i . '.' . $ext;
                        $destination = $uploadDir . '/' . $new_filename;
                        if (move_uploaded_file($tmp_name, $destination)) {
                            $picture_paths[$i - 1] = 'uploads/' . $new_filename;
                        } else {
                            $uploaded_ok = false;
                        }
                    } else {
                        $uploaded_ok = false;
                        setMessage("Invalid file extension for Picture $i.", "error");
                        break;
                    }
                }
            }

            if ($uploaded_ok && empty($picture_paths[0])) {
                $uploaded_ok = false;
                setMessage("Primary Display Picture (Picture 1) is required.", "error");
            }

            if ($uploaded_ok && !empty($name) && !empty($category) && $price > 0) {
                try {
                    $sql = "INSERT INTO `$table` (`name`, `category`, `price`, `picture_1`, `picture_2`, `picture_3`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$name, $category, $price, $picture_paths[0], $picture_paths[1], $picture_paths[2], $description]);
                    setMessage("Successfully added menu item.");
                    header("Location: chef.php?tab=" . ($table === 'foods' ? 'food' : 'drinks'));
                    exit;
                } catch (PDOException $e) {
                    setMessage("Error saving item: " . $e->getMessage(), "error");
                }
            } else if ($uploaded_ok) {
                setMessage("Please complete all mandatory fields correctly.", "error");
            }
        }

        // --- CATALOG: UPDATE ITEM ---
        if ($action === 'update_item') {
            $table = $_POST['item_table'] ?? 'foods';
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($id > 0 && !empty($name) && !empty($category) && $price > 0) {
                try {
                    $stmt_current = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
                    $stmt_current->execute([$id]);
                    $current_item = $stmt_current->fetch();

                    if ($current_item) {
                        $picture_paths = [$current_item['picture_1'], $current_item['picture_2'], $current_item['picture_3']];

                        for ($i = 1; $i <= 3; $i++) {
                            $file_key = "picture_" . $i;
                            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                                $tmp_name = $_FILES[$file_key]['tmp_name'];
                                $original_name = basename($_FILES[$file_key]['name']);
                                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                                if (in_array($ext, $allowed)) {
                                    $new_filename = uniqid('chef_update_', true) . '_' . $i . '.' . $ext;
                                    $destination = $uploadDir . '/' . $new_filename;
                                    if (move_uploaded_file($tmp_name, $destination)) {
                                        if (!empty($picture_paths[$i - 1]) && file_exists(__DIR__ . '/' . $picture_paths[$i - 1])) {
                                            @unlink(__DIR__ . '/' . $picture_paths[$i - 1]);
                                        }
                                        $picture_paths[$i - 1] = 'uploads/' . $new_filename;
                                    }
                                }
                            }
                        }

                        $sql = "UPDATE `$table` SET `name` = ?, `category` = ?, `price` = ?, `picture_1` = ?, `picture_2` = ?, `picture_3` = ?, `description` = ? WHERE `id` = ?";
                        $stmt_update = $pdo->prepare($sql);
                        $stmt_update->execute([$name, $category, $price, $picture_paths[0], $picture_paths[1], $picture_paths[2], $description, $id]);

                        setMessage("Catalog item updated successfully.");
                        header("Location: chef.php?tab=" . ($table === 'foods' ? 'food' : 'drinks'));
                        exit;
                    }
                } catch (PDOException $e) {
                    setMessage("Error updating item: " . $e->getMessage(), "error");
                }
            }
        }

        // --- CATALOG: DELETE ITEM ---
        if ($action === 'delete_item') {
            $table = $_POST['item_table'] ?? 'foods';
            $id = intval($_POST['id'] ?? 0);

            if ($id > 0) {
                try {
                    $stmt_current = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
                    $stmt_current->execute([$id]);
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
                    setMessage("Item deleted successfully.");
                    header("Location: chef.php?tab=" . ($table === 'foods' ? 'food' : 'drinks'));
                    exit;
                } catch (PDOException $e) {
                    setMessage("Error deleting item: " . $e->getMessage(), "error");
                }
            }
        }
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: chef.php");
    exit;
}

$current_tab = $_GET['tab'] ?? 'orders';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jenda Kitchen Center</title>
    <!-- Tailwind Play CDN -->
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
                        // Brand Orange (#ff7e00)
                        brandOrange: {
                            50: '#fff6ed',
                            100: '#ffead4',
                            200: '#ffd1a8',
                            300: '#ffb270',
                            400: '#ff8f38',
                            500: '#ff7e00',
                            600: '#e66700',
                            700: '#bf4e00',
                            800: '#993a00',
                            900: '#7d2f02',
                        },
                        charcoal: {
                            800: '#1c1c1e',
                            900: '#121212',
                            950: '#0a0a0b',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>

<body class="h-full text-slate-800 antialiased flex flex-col bg-slate-50">

    <!-- SELECT PROFILE / LOGIN SCREEN -->
    <?php if (!isset($_SESSION['chef_logged']) || $_SESSION['chef_logged'] !== true): ?>
        <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-charcoal-900 bg-cover bg-blend-overlay bg-center"
            style="background-image: url('https://images.unsplash.com/photo-1577219491135-ce391730fb2c?auto=format&fit=crop&w=1920&q=80')">
            <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
                <div
                    class="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-brandOrange-500 text-white shadow-xl shadow-brandOrange-500/20 mb-4">
                    <i data-lucide="cooking-pot" class="h-9 w-9"></i>
                </div>
                <h2 class="text-3xl font-extrabold text-white tracking-tight">Jenda Restaurant</h2>
                <p class="mt-2 text-sm text-slate-300">Kitchen Operations & Chef Hub</p>
            </div>

            <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-lg px-4 sm:px-0">
                <div
                    class="bg-white/95 backdrop-blur-md py-8 px-6 shadow-2xl rounded-3xl border border-white/20 sm:px-10 grid grid-cols-1 md:grid-cols-2 gap-8">

                    <!-- Left Column: Choose existing Profile -->
                    <div>
                        <h3 class="text-base font-bold text-slate-900 mb-4">Select Kitchen Profile</h3>
                        <form action="chef.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="select_profile">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Active
                                    Chef Name</label>
                                <select name="chef_id" required
                                    class="block w-full border border-slate-200 rounded-xl bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brandOrange-500">
                                    <option value="">Select your profile...</option>
                                    <?php
                                    $chefs_list = $pdo->query("SELECT * FROM `chefs` ORDER BY `name` ASC")->fetchAll();
                                    foreach ($chefs_list as $chef):
                                        ?>
                                        <option value="<?php echo $chef['id']; ?>" <?php echo $chef['status'] === 'suspended' ? 'disabled' : ''; ?>>
                                            <?php echo htmlspecialchars($chef['name']); ?>
                                            (<?php echo htmlspecialchars($chef['specialty']); ?>)
                                            <?php echo $chef['status'] === 'suspended' ? '[SUSPENDED]' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit"
                                class="w-full flex justify-center py-2.5 px-4 rounded-xl shadow-md text-sm font-semibold text-white bg-brandOrange-500 hover:bg-brandOrange-600 focus:outline-none transition-all cursor-pointer">
                                Access Kitchen
                            </button>
                        </form>
                    </div>

                    <!-- Right Column: Quick Register Profile -->
                    <div class="border-t md:border-t-0 md:border-l border-slate-200 pt-6 md:pt-0 md:pl-8">
                        <h3 class="text-base font-bold text-slate-900 mb-4">New Chef Profile</h3>
                        <form action="chef.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="register_chef">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Your
                                    Full Name</label>
                                <input type="text" name="name" required placeholder="e.g. Chef Paul"
                                    class="block w-full border border-slate-200 rounded-xl bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brandOrange-500">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Specialty</label>
                                <input type="text" name="specialty" placeholder="e.g. Desserts, Roast"
                                    class="block w-full border border-slate-200 rounded-xl bg-slate-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brandOrange-500">
                            </div>
                            <button type="submit"
                                class="w-full flex justify-center py-2.5 px-4 rounded-xl shadow-md text-sm font-semibold text-white bg-slate-900 hover:bg-slate-800 focus:outline-none transition-all cursor-pointer">
                                Create Account
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        <!-- MAIN KITCHEN CONSOLE (AUTHENTICATED) -->
    <?php else: ?>

        <!-- Header Navigation -->
        <header class="bg-charcoal-900 border-b border-charcoal-800 sticky top-0 z-40 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 flex items-center gap-3">
                            <div
                                class="h-10 w-10 bg-brandOrange-500 rounded-xl flex items-center justify-center text-white shadow-md shadow-brandOrange-500/20">
                                <i data-lucide="cooking-pot" class="h-6 w-6"></i>
                            </div>
                            <div>
                                <span class="text-lg font-bold tracking-tight text-white block leading-tight">Jenda
                                    Kitchen</span>
                                <span
                                    class="text-xs font-semibold text-brandOrange-500 uppercase tracking-widest block">Operations
                                    Hub</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div
                            class="flex items-center gap-2 px-3 py-1.5 bg-charcoal-800 rounded-lg text-slate-300 text-sm font-medium border border-charcoal-700">
                            <i data-lucide="chef-hat" class="h-4 w-4 text-brandOrange-500"></i>
                            <span>Chef: <strong
                                    class="text-white"><?php echo htmlspecialchars($_SESSION['chef_name']); ?></strong></span>
                        </div>
                        <a href="chef.php?logout=1"
                            class="inline-flex items-center gap-2 bg-rose-950/40 hover:bg-rose-900/60 text-rose-400 hover:text-rose-300 transition px-3.5 py-1.5 rounded-lg text-sm font-semibold cursor-pointer border border-rose-900/50">
                            <i data-lucide="log-out" class="h-4 w-4"></i>
                            <span>Exit</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col md:flex-row gap-8">

            <!-- Sidebar Navigation Menu -->
            <aside class="w-full md:w-64 shrink-0">
                <nav class="space-y-1.5 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Kitchen Workflows</div>

                    <a href="chef.php?tab=orders"
                        class="group flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition duration-150 <?php echo $current_tab === 'orders' ? 'bg-brandOrange-50 text-brandOrange-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                        <i data-lucide="shopping-bag"
                            class="mr-3 h-5 w-5 shrink-0 <?php echo $current_tab === 'orders' ? 'text-brandOrange-500' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <span class="truncate">Order Pipeline</span>
                    </a>

                    <a href="chef.php?tab=reservations"
                        class="group flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition duration-150 <?php echo $current_tab === 'reservations' ? 'bg-brandOrange-50 text-brandOrange-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                        <i data-lucide="calendar"
                            class="mr-3 h-5 w-5 shrink-0 <?php echo $current_tab === 'reservations' ? 'text-brandOrange-500' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <span class="truncate">Reservations</span>
                    </a>

                    <div class="pt-4 px-3 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Catalog Inventory
                    </div>

                    <a href="chef.php?tab=food"
                        class="group flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition duration-150 <?php echo $current_tab === 'food' ? 'bg-brandOrange-50 text-brandOrange-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                        <i data-lucide="pizza"
                            class="mr-3 h-5 w-5 shrink-0 <?php echo $current_tab === 'food' ? 'text-brandOrange-500' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <span class="truncate">Food Registry</span>
                    </a>

                    <a href="chef.php?tab=drinks"
                        class="group flex items-center px-3 py-2.5 text-sm font-semibold rounded-xl transition duration-150 <?php echo $current_tab === 'drinks' ? 'bg-brandOrange-50 text-brandOrange-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                        <i data-lucide="cup-soda"
                            class="mr-3 h-5 w-5 shrink-0 <?php echo $current_tab === 'drinks' ? 'text-brandOrange-500' : 'text-slate-400 group-hover:text-slate-500'; ?>"></i>
                        <span class="truncate">Drink Registry</span>
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
                <!-- TAB: KITCHEN ORDER WORKFLOWS (ACTIVE PIPELINE) -->
                <!-- ========================================================================= -->
                <?php if ($current_tab === 'orders'): ?>
                    <?php
                    // Retrieve all active orders from the database
                    $orders_list = $pdo->query("SELECT * FROM `orders` ORDER BY `order_date` DESC")->fetchAll();
                    ?>
                    <div class="space-y-6">
                        <div class="border-b border-slate-200 pb-5">
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Active Pipeline & Orders</h1>
                            <p class="mt-1 text-sm text-slate-500">Update status states, track customer orders, and claim
                                fulfillments dynamically.</p>
                        </div>

                        <?php if (empty($orders_list)): ?>
                            <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl">
                                <div
                                    class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-slate-100 text-slate-400 mb-3">
                                    <i data-lucide="shopping-cart" class="h-6 w-6"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-900">No active kitchen prep orders</h3>
                                <p class="mt-1 text-xs text-slate-500">Orders placed by customers will stream directly into this
                                    layout.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php foreach ($orders_list as $order): ?>
                                    <div
                                        class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4 flex flex-col justify-between">
                                        <div class="space-y-3">
                                            <div class="flex justify-between items-start gap-4">
                                                <span class="text-xs font-bold bg-slate-100 text-slate-800 px-3 py-1 rounded-lg">ID:
                                                    #<?php echo $order['id']; ?></span>

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
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize <?php echo $statusColor; ?>">
                                                    Status: <?php echo htmlspecialchars($order['order_status']); ?>
                                                </span>
                                            </div>

                                            <div class="text-xs space-y-1 text-slate-600">
                                                <p><strong>Customer Ref:</strong>
                                                    #<?php echo htmlspecialchars($order['customer_id']); ?></p>
                                                <p><strong>Date Placed:</strong>
                                                    <?php echo date('M d, Y - H:i A', strtotime($order['order_date'])); ?></p>
                                                <p><strong>Delivery Address:</strong>
                                                    <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                                                <p><strong>Fulfillment Claim:</strong>
                                                    <?php if (!empty($order['fulfilled_by'])):
                                                        $stmt_name = $pdo->prepare("SELECT name FROM `chefs` WHERE id = ?");
                                                        $stmt_name->execute([$order['fulfilled_by']]);
                                                        $cf_name = $stmt_name->fetchColumn();
                                                        ?>
                                                        <span class="text-emerald-700 font-bold">Claimed by
                                                            <?php echo htmlspecialchars($cf_name ?: 'Unknown'); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-slate-400 italic">Unclaimed</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                            <div>
                                                <span
                                                    class="block text-[10px] text-slate-400 uppercase tracking-wider font-bold">Invoice
                                                    Cost</span>
                                                <span
                                                    class="text-lg font-black text-slate-900">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                            </div>

                                            <!-- Interactive workflow triggers -->
                                            <form action="chef.php" method="POST" class="flex flex-wrap items-center gap-2">
                                                <input type="hidden" name="action" value="update_order">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">

                                                <select name="order_status" required
                                                    class="text-xs bg-slate-50 border border-slate-200 rounded-lg p-2 font-bold cursor-pointer focus:outline-none">
                                                    <option value="pending" <?php echo $order['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="preparing" <?php echo $order['order_status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                                    <option value="ready" <?php echo $order['order_status'] === 'ready' ? 'selected' : ''; ?>>Ready</option>
                                                    <option value="out_for_delivery" <?php echo $order['order_status'] === 'out_for_delivery' ? 'selected' : ''; ?>>Out for
                                                        Delivery</option>
                                                    <option value="delivered" <?php echo $order['order_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="cancelled" <?php echo $order['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>

                                                <!-- Auto Claim Checkbox -->
                                                <?php if ($order['fulfilled_by'] != $_SESSION['chef_id']): ?>
                                                    <label
                                                        class="inline-flex items-center gap-1.5 text-xs text-slate-600 font-semibold cursor-pointer">
                                                        <input type="checkbox" name="claim_fulfillment" value="1" checked
                                                            class="rounded border-slate-300 text-brandOrange-500 focus:ring-brandOrange-500 h-4 w-4">
                                                        <span>Claim Work</span>
                                                    </label>
                                                <?php endif; ?>

                                                <button type="submit"
                                                    class="bg-brandOrange-500 hover:bg-brandOrange-600 text-white font-bold text-xs px-3.5 py-2 rounded-lg transition shadow-sm">
                                                    Submit
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ========================================================================= -->
                    <!-- TAB: TABLE RESERVATIONS AND BOOKINGS -->
                    <!-- ========================================================================= -->
                <?php elseif ($current_tab === 'reservations'): ?>
                    <?php
                    $reservations = $pdo->query("SELECT * FROM `reservations` ORDER BY `reservation_date` DESC, `reservation_time` ASC")->fetchAll();
                    ?>
                    <div class="space-y-6">
                        <div class="border-b border-slate-200 pb-5">
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Reservations & Bookings</h1>
                            <p class="mt-1 text-sm text-slate-500">Confirm or deny customer table reservation booking requests.
                            </p>
                        </div>

                        <?php if (empty($reservations)): ?>
                            <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl">
                                <div
                                    class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-slate-100 text-slate-400 mb-3">
                                    <i data-lucide="calendar" class="h-6 w-6"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-900">No reservations currently booked</h3>
                                <p class="mt-1 text-xs text-slate-500">Bookings placed through client applications populate here.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-500 tracking-wider">
                                        <tr>
                                            <th class="px-6 py-4 text-left">Res ID</th>
                                            <th class="px-6 py-4 text-left">Reservation Contact</th>
                                            <th class="px-6 py-4 text-left">Schedule</th>
                                            <th class="px-6 py-4 text-left">Guests Count</th>
                                            <th class="px-6 py-4 text-left">Approval State</th>
                                            <th class="px-6 py-4 text-center">Interactive Choices</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 text-sm">
                                        <?php foreach ($reservations as $res): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-900">
                                                    #<?php echo $res['id']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap font-semibold">
                                                    <?php echo htmlspecialchars($res['reservation_name']); ?>
                                                    <span class="block text-xs text-slate-400">Cust ID:
                                                        #<?php echo htmlspecialchars($res['customer_id']); ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php echo date('M d, Y', strtotime($res['reservation_date'])); ?>
                                                    <span class="block text-xs text-slate-400">at
                                                        <?php echo date('h:i A', strtotime($res['reservation_time'])); ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap font-bold"><?php echo intval($res['guests']); ?>
                                                    guests</td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                                                    $bColor = 'bg-slate-100 text-slate-800';
                                                    if ($res['status'] === 'confirmed')
                                                        $bColor = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                                                    else if ($res['status'] === 'denied')
                                                        $bColor = 'bg-rose-50 text-rose-700 border border-rose-100';
                                                    ?>
                                                    <span
                                                        class="px-3 py-1 text-xs rounded-full font-bold capitalize border <?php echo $bColor; ?>">
                                                        <?php echo htmlspecialchars($res['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                                    <div class="inline-flex gap-2">
                                                        <!-- Confirm Form -->
                                                        <form action="chef.php" method="POST">
                                                            <input type="hidden" name="action" value="update_reservation">
                                                            <input type="hidden" name="reservation_id"
                                                                value="<?php echo $res['id']; ?>">
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button type="submit"
                                                                class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs px-3 py-1.5 rounded-lg font-bold transition cursor-pointer border border-emerald-200">
                                                                Confirm
                                                            </button>
                                                        </form>

                                                        <!-- Deny Form -->
                                                        <form action="chef.php" method="POST">
                                                            <input type="hidden" name="action" value="update_reservation">
                                                            <input type="hidden" name="reservation_id"
                                                                value="<?php echo $res['id']; ?>">
                                                            <input type="hidden" name="status" value="denied">
                                                            <button type="submit"
                                                                class="bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs px-3 py-1.5 rounded-lg font-bold transition cursor-pointer border border-rose-200">
                                                                Deny
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ========================================================================= -->
                    <!-- TAB: FOOD AND DRINK INVENTORIES -->
                    <!-- ========================================================================= -->
                <?php elseif ($current_tab === 'food' || $current_tab === 'drinks'): ?>
                    <?php
                    $is_food = ($current_tab === 'food');
                    $table_name = $is_food ? 'foods' : 'drinks';
                    $items_list = $pdo->query("SELECT * FROM `$table_name` ORDER BY `category` ASC, `name` ASC")->fetchAll();

                    $grouped_items = [];
                    foreach ($items_list as $item) {
                        $grouped_items[$item['category']][] = $item;
                    }
                    ?>
                    <div class="space-y-6">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5 gap-4">
                            <div>
                                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                                    <?php echo $is_food ? 'Food' : 'Drinks'; ?> Catalog Registry</h1>
                                <p class="mt-1 text-sm text-slate-500">Categorized kitchen registry management. Update prices,
                                    descriptions, and pictures.</p>
                            </div>
                            <button onclick="toggleModal('item-modal-add')"
                                class="inline-flex items-center gap-2 bg-brandOrange-500 hover:bg-brandOrange-600 text-white font-semibold py-2.5 px-4 rounded-xl text-sm transition-all duration-150 cursor-pointer shadow-md shadow-brandOrange-500/10">
                                <i data-lucide="plus-circle" class="h-5 w-5"></i>
                                <span>Add New <?php echo $is_food ? 'Food' : 'Drink'; ?></span>
                            </button>
                        </div>

                        <?php if (empty($grouped_items)): ?>
                            <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl">
                                <div
                                    class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-slate-100 text-slate-400 mb-3">
                                    <i data-lucide="package-search" class="h-6 w-6"></i>
                                </div>
                                <h3 class="text-sm font-semibold text-slate-900">No items configured</h3>
                                <p class="mt-1 text-xs text-slate-500">Please use the onboarding button above to register first
                                    catalog items.</p>
                            </div>
                        <?php else:
                            foreach ($grouped_items as $category => $items): ?>
                                <div class="space-y-4">
                                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2 border-b border-slate-200 pb-2">
                                        <span class="h-2.5 w-2.5 rounded-full bg-brandOrange-500"></span>
                                        <span><?php echo htmlspecialchars($category); ?></span>
                                        <span
                                            class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full"><?php echo count($items); ?>
                                            items</span>
                                    </h2>

                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                        <?php foreach ($items as $item): ?>
                                            <div
                                                class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm flex flex-col justify-between group">
                                                <div>
                                                    <div class="relative h-44 bg-slate-100 overflow-hidden">
                                                        <img src="<?php echo !empty($item['picture_1']) ? htmlspecialchars($item['picture_1']) : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=400&q=80'; ?>"
                                                            alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                            id="gallery-<?php echo $item['id']; ?>"
                                                            class="w-full h-full object-cover group-hover:scale-105 transition duration-500">

                                                        <div
                                                            class="absolute bottom-2 left-2 flex gap-1.5 bg-charcoal-900/80 backdrop-blur-md px-2 py-1 rounded-md">
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

                                                    <div class="p-5">
                                                        <h3 class="text-base font-bold text-slate-900 truncate">
                                                            <?php echo htmlspecialchars($item['name']); ?></h3>
                                                        <p class="mt-2 text-xs text-slate-500 line-clamp-3 leading-relaxed">
                                                            <?php echo !empty($item['description']) ? htmlspecialchars($item['description']) : '<em>No description details registered.</em>'; ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="bg-slate-50 px-5 py-3 border-t border-slate-100 flex gap-2 justify-end">
                                                    <button
                                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>, '<?php echo $table_name; ?>')"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                                                        <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                                                        <span>Modify</span>
                                                    </button>

                                                    <form action="chef.php" method="POST"
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
                                </div>
                            <?php endforeach; endif; ?>
                    </div>

                    <!-- ADD ITEM MODAL -->
                    <div id="item-modal-add"
                        class="hidden fixed inset-0 z-50 overflow-y-auto bg-charcoal-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                        <div
                            class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-100 transform transition-all">
                            <div
                                class="bg-charcoal-900 text-white px-6 py-4 flex justify-between items-center border-b border-charcoal-800">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="plus-circle" class="h-5 w-5 text-brandOrange-500"></i>
                                    <h3 class="text-base font-bold">Register Menu Item</h3>
                                </div>
                                <button onclick="toggleModal('item-modal-add')"
                                    class="text-slate-400 hover:text-white cursor-pointer transition">
                                    <i data-lucide="x" class="h-5 w-5"></i>
                                </button>
                            </div>
                            <form action="chef.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                                <input type="hidden" name="action" value="add_item">
                                <input type="hidden" name="item_table" value="<?php echo $table_name; ?>">

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Item
                                            Title / Name *</label>
                                        <input type="text" name="name" required
                                            class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brandOrange-500">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Category
                                            Label *</label>
                                        <input type="text" name="category" required placeholder="e.g. Appetizers, Desserts"
                                            class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brandOrange-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Price
                                        Billing ($) *</label>
                                    <input type="number" step="0.01" name="price" required
                                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brandOrange-500">
                                </div>

                                <div class="p-4 bg-slate-50 rounded-xl space-y-3 border border-slate-100">
                                    <span class="block text-xs font-bold uppercase tracking-widest text-slate-600 mb-1">Picture
                                        Portfolio</span>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500 mb-1">Primary Display Picture *
                                            (Required)</label>
                                        <input type="file" name="picture_1" required
                                            class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brandOrange-50 file:text-brandOrange-700 hover:file:bg-brandOrange-100 cursor-pointer">
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
                                        class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Description
                                        (Optional)</label>
                                    <textarea name="description" rows="3"
                                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brandOrange-500"
                                        placeholder="Recipe details, calories, etc..."></textarea>
                                </div>

                                <div class="flex gap-3 pt-2">
                                    <button type="button" onclick="toggleModal('item-modal-add')"
                                        class="flex-1 py-2.5 bg-slate-100 text-slate-700 font-semibold rounded-xl text-sm hover:bg-slate-200 transition cursor-pointer text-center">Cancel</button>
                                    <button type="submit"
                                        class="flex-1 py-2.5 bg-brandOrange-500 text-white font-semibold rounded-xl text-sm hover:bg-brandOrange-600 transition cursor-pointer text-center">Save
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
                                    <i data-lucide="edit-3" class="h-5 w-5 text-brandOrange-500"></i>
                                    <h3 class="text-base font-bold">Modify Catalog Item</h3>
                                </div>
                                <button onclick="toggleModal('item-modal-edit')"
                                    class="text-slate-400 hover:text-white cursor-pointer transition">
                                    <i data-lucide="x" class="h-5 w-5"></i>
                                </button>
                            </div>
                            <form action="chef.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                                <input type="hidden" name="action" value="update_item">
                                <input type="hidden" name="item_table" id="edit-table">
                                <input type="hidden" name="id" id="edit-id">

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Item
                                            Title / Name *</label>
                                        <input type="text" name="name" id="edit-name" required
                                            class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brandOrange-500">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Category
                                            Label *</label>
                                        <input type="text" name="category" id="edit-category" required
                                            class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brandOrange-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Price
                                        Billing ($) *</label>
                                    <input type="number" step="0.01" name="price" id="edit-price" required
                                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brandOrange-500">
                                </div>

                                <div class="p-4 bg-slate-50 rounded-xl space-y-3 border border-slate-100">
                                    <span class="block text-xs font-bold uppercase tracking-widest text-slate-600 mb-1">Update
                                        Picture Assets</span>
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
                                        or Culinary Description (Optional)</label>
                                    <textarea name="description" id="edit-description" rows="3"
                                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brandOrange-500"></textarea>
                                </div>

                                <div class="flex gap-3 pt-2">
                                    <button type="button" onclick="toggleModal('item-modal-edit')"
                                        class="flex-1 py-2.5 bg-slate-100 text-slate-700 font-semibold rounded-xl text-sm hover:bg-slate-200 transition cursor-pointer text-center">Cancel</button>
                                    <button type="submit"
                                        class="flex-1 py-2.5 bg-brandOrange-500 text-white font-semibold rounded-xl text-sm hover:bg-brandOrange-600 transition cursor-pointer text-center">Update
                                        Item</button>
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
                <?php endif; ?>

            </main>
        </div>

        <!-- Page Footer -->
        <footer class="bg-white border-t border-slate-200 py-6 mt-auto">
            <div class="max-w-7xl mx-auto px-4 text-center text-xs text-slate-400">
                &copy; 2026 Jenda Restaurant Platform &bull; Chef Kitchen Console Operational System.
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

    <script>
        lucide.createIcons();
    </script>
</body>

</html>