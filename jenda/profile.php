<?php
/**
 * Jenda Restaurant - Customer Profile & Dashboard
 * Handles profile display, details updates, security, and order history.
 */

session_start();
require_once 'include/db.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Ensure non-customers cannot browse the customer profile
if ($_SESSION['role'] !== 'customer') {
    // Redirect admins or chefs to their respective landing dashboards
    if ($_SESSION['role'] === 'admin') {
        header("Location: manage_restaurant_2v6.php");
    } else {
        header("Location: chef.php");
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit;
}

// 1. FETCH UPDATED USER DATA
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error loading profile details: " . $e->getMessage());
}

// 2. HANDLE PROFILE DETAILS UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_details'])) {
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if (empty($contact) || empty($location)) {
        $errors[] = "Contact and Location are mandatory delivery details.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    }

    if (empty($errors)) {
        try {
            // Check if email is already taken by another user
            if (!empty($email)) {
                $chkEmail = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $chkEmail->execute([$email, $user_id]);
                if ($chkEmail->fetchColumn() > 0) {
                    throw new Exception("Email address is already in use by another account.");
                }
            }

            $update = $pdo->prepare("UPDATE users SET email = ?, contact = ?, location = ? WHERE id = ?");
            $update->execute([!empty($email) ? $email : null, $contact, $location, $user_id]);

            // Refresh local variable state
            $user['email'] = $email;
            $user['contact'] = $contact;
            $user['location'] = $location;

            $success_message = "Your details have been successfully updated!";
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// 3. HANDLE SECURE PASSWORD UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $errors[] = "All password fields are required to update credentials.";
    } elseif ($new_pass !== $confirm_pass) {
        $errors[] = "New passwords do not match.";
    } elseif (strlen($new_pass) < 6) {
        $errors[] = "New password must be at least 6 characters long.";
    } else {
        try {
            // Confirm the old password
            if (password_verify($current_pass, $user['password'])) {
                $newHashedPassword = password_hash($new_pass, PASSWORD_BCRYPT);
                $updatePass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updatePass->execute([$newHashedPassword, $user_id]);
                $success_message = "Password updated successfully!";
            } else {
                $errors[] = "The current password you entered is incorrect.";
            }
        } catch (PDOException $e) {
            $errors[] = "Password update failed: " . $e->getMessage();
        }
    }
}

// 4. FETCH ORDER HISTORY WITH ORDER ITEMS
$orders_list = [];
try {
    $orderQuery = $pdo->prepare("
        SELECT id, order_status, payment_status, payment_method, total_amount, delivery_address, order_date 
        FROM orders 
        WHERE customer_id = ? 
        ORDER BY order_date DESC
    ");
    $orderQuery->execute([$user_id]);
    $raw_orders = $orderQuery->fetchAll();

    foreach ($raw_orders as $o) {
        // Fetch detailed order items (food & drinks combined)
        $itemsQuery = $pdo->prepare("
            SELECT oi.quantity, oi.unit_price, f.name AS food_name, d.name AS drink_name 
            FROM order_items oi
            LEFT JOIN foods f ON oi.food_id = f.id
            LEFT JOIN drinks d ON oi.drink_id = d.id
            WHERE oi.order_id = ?
        ");
        $itemsQuery->execute([$o['id']]);
        $o['items'] = $itemsQuery->fetchAll();
        $orders_list[] = $o;
    }
} catch (PDOException $e) {
    $errors[] = "Failed to retrieve order history: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Alvida Restaurant</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        jendaOrange: '#ff7e00',
                        charcoal: '#121212',
                        charcoalLight: '#1c1917',
                    }
                }
            }
        }
    </script>
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #ff7e00;
            border-radius: 3px;
        }
    </style>
</head>

<body class="bg-stone-50 min-h-screen text-stone-900 flex flex-col">

    <!-- Top Navigation -->
    <nav class="bg-charcoal text-white border-b border-stone-800 sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <img src="assets/white_logo.png" alt="Alvida Logo" class="h-7 w-auto">
                </div>
                <!-- Nav Links -->
                <div class="flex items-center space-x-6">
                    <a href="index.php"
                        class="text-stone-300 hover:text-jendaOrange text-sm font-medium transition-colors">
                        <i class="fa-solid fa-utensils mr-1.5"></i>Menu
                    </a>
                    <a href="logout.php"
                        class="bg-jendaOrange text-white hover:bg-opacity-90 px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all shadow-md">
                        <i class="fa-solid fa-sign-out-alt mr-1.5"></i>Log Out
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Workspace Container -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Welcome Jumbotron -->
        <div
            class="bg-charcoal text-white rounded-3xl p-6 sm:p-8 shadow-xl mb-8 relative overflow-hidden flex flex-col md:flex-row items-center gap-6 border border-stone-800">
            <div
                class="absolute right-0 top-0 w-64 h-64 bg-jendaOrange rounded-full filter blur-3xl opacity-10 -mr-20 -mt-20">
            </div>

            <!-- User Profile Picture Frame (Capable of loading Google Sign-in URLs) -->
            <div class="relative group">
                <div
                    class="w-28 h-28 rounded-full ring-4 ring-jendaOrange/40 p-1 bg-charcoal overflow-hidden flex items-center justify-center">
                    <?php
                    $pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'uploads/profiles/default.png';
                    // Check if Google URL or standard local directory path
                    $avatar_url = (filter_var($pic, FILTER_VALIDATE_URL)) ? $pic : $pic;
                    ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile avatar"
                        class="w-full h-full rounded-full object-cover"
                        onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=250&q=80';">
                </div>
                <div
                    class="absolute -bottom-1 -right-1 bg-jendaOrange text-white text-xs w-8 h-8 rounded-full flex items-center justify-center shadow-lg border border-charcoal">
                    <i class="fa-solid fa-crown text-[10px]"></i>
                </div>
            </div>

            <!-- Profile Info Block -->
            <div class="text-center md:text-left flex-grow">
                <div class="flex flex-col md:flex-row md:items-center gap-2 mb-2">
                    <h1 class="text-3xl font-extrabold tracking-tight">
                        <?php echo htmlspecialchars($user['username']); ?></h1>
                    <span
                        class="inline-block self-center bg-jendaOrange/20 text-jendaOrange border border-jendaOrange/30 px-3 py-1 text-[11px] font-bold uppercase tracking-widest rounded-full">
                        <?php echo htmlspecialchars(ucfirst($user['status'] ?? 'Standard')); ?> Member
                    </span>
                </div>
                <p class="text-stone-400 text-sm flex items-center justify-center md:justify-start gap-2">
                    <i class="fa-solid fa-calendar-alt text-jendaOrange"></i> Joined:
                    <?php echo date("F d, Y", strtotime($user['date_created'])); ?>
                </p>
                <p class="text-stone-400 text-sm flex items-center justify-center md:justify-start gap-2 mt-1">
                    <i class="fa-solid fa-map-marker-alt text-jendaOrange"></i> Deliver to: <span
                        class="text-stone-200"><?php echo htmlspecialchars($user['location']); ?></span>
                </p>
            </div>

            <!-- Total Expenditure / Stat card -->
            <div
                class="bg-charcoalLight border border-stone-800 rounded-2xl p-4 w-full md:w-auto text-center min-w-[180px]">
                <span class="text-stone-400 text-xs font-bold uppercase tracking-wider block mb-1">Total Orders
                    Placed</span>
                <span class="text-4xl font-black text-jendaOrange"><?php echo count($orders_list); ?></span>
            </div>
        </div>

        <!-- Success & Error Banners -->
        <?php if (!empty($success_message)): ?>
            <div
                class="bg-emerald-500/10 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded-xl mb-6 flex items-center gap-3">
                <i class="fa-solid fa-circle-check text-emerald-600 text-xl"></i>
                <p class="text-sm font-medium"><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-500/10 border-l-4 border-red-500 text-red-800 p-4 rounded-xl mb-6">
                <div class="flex items-center gap-3 mb-2">
                    <i class="fa-solid fa-circle-exclamation text-red-600 text-xl"></i>
                    <p class="text-sm font-bold">Please correct the following errors:</p>
                </div>
                <ul class="list-disc pl-8 text-sm">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Dashboard Workspace Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- LEFT 2 COLUMNS: Profile Controls & Order History -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Tab Controls for Main Container -->
                <div class="bg-white rounded-2xl shadow-sm border border-stone-100 overflow-hidden">
                    <div class="flex border-b border-stone-100 bg-stone-50">
                        <button id="btn-history" onclick="switchDashboardTab('history')"
                            class="flex-1 py-4 px-6 text-center text-sm font-bold border-b-2 border-jendaOrange text-jendaOrange transition-all focus:outline-none flex items-center justify-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left"></i> Order History
                        </button>
                        <button id="btn-settings" onclick="switchDashboardTab('settings')"
                            class="flex-1 py-4 px-6 text-center text-sm font-bold border-b-2 border-transparent text-stone-500 hover:text-stone-800 transition-all focus:outline-none flex items-center justify-center gap-2">
                            <i class="fa-solid fa-user-gear"></i> Account Settings
                        </button>
                    </div>

                    <!-- TAB CONTENT: ORDER HISTORY -->
                    <div id="tab-history-content" class="p-6 space-y-6">
                        <h2 class="text-xl font-extrabold text-charcoal flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-jendaOrange"></i> Previous Orders
                        </h2>

                        <?php if (empty($orders_list)): ?>
                            <div class="text-center py-12 px-4">
                                <div
                                    class="w-16 h-16 bg-stone-100 text-stone-400 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fa-solid fa-utensils text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-bold text-stone-700">No orders found</h3>
                                <p class="text-sm text-stone-500 max-w-sm mx-auto mt-1">You haven't placed any orders yet.
                                    Head to our menu and grab something delicious!</p>
                                <a href="index.php"
                                    class="inline-block mt-4 bg-jendaOrange text-white px-5 py-2 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-opacity-95 shadow transition-colors">
                                    Explore Menu
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4 max-h-[600px] overflow-y-auto custom-scrollbar pr-1">
                                <?php foreach ($orders_list as $order): ?>
                                    <div
                                        class="bg-stone-50 border border-stone-200/60 rounded-xl p-5 hover:border-jendaOrange/30 transition-all shadow-sm">
                                        <div
                                            class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 mb-3 border-b border-stone-200/60">
                                            <div>
                                                <span class="text-xs font-extrabold text-stone-500">ORDER
                                                    #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                <div class="text-xs text-stone-400 mt-0.5">
                                                    <?php echo date("g:i A, d M Y", strtotime($order['order_date'])); ?></div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <!-- Order status badge -->
                                                <?php
                                                $statusColor = 'bg-stone-100 text-stone-700';
                                                if ($order['order_status'] === 'delivered')
                                                    $statusColor = 'bg-emerald-100 text-emerald-800';
                                                elseif ($order['order_status'] === 'preparing')
                                                    $statusColor = 'bg-amber-100 text-amber-800';
                                                elseif ($order['order_status'] === 'out_for_delivery')
                                                    $statusColor = 'bg-blue-100 text-blue-800';
                                                elseif ($order['order_status'] === 'cancelled')
                                                    $statusColor = 'bg-red-100 text-red-800';
                                                ?>
                                                <span
                                                    class="inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?php echo $statusColor; ?>">
                                                    <?php echo str_replace('_', ' ', $order['order_status']); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Items List -->
                                        <div class="space-y-2 mb-4">
                                            <?php foreach ($order['items'] as $item): ?>
                                                <div class="flex justify-between items-center text-sm text-stone-700">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="font-extrabold text-jendaOrange text-xs bg-jendaOrange/10 px-2 py-0.5 rounded"><?php echo $item['quantity']; ?>x</span>
                                                        <span><?php echo htmlspecialchars($item['food_name'] ?? $item['drink_name'] ?? 'Custom Delicacy'); ?></span>
                                                    </div>
                                                    <span
                                                        class="font-semibold text-stone-900">$<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div
                                            class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-stone-200/60 text-xs">
                                            <div class="text-stone-500">
                                                <span class="font-bold">Address:</span>
                                                <?php echo htmlspecialchars($order['delivery_address']); ?>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="text-right">
                                                    <span
                                                        class="text-stone-400 block text-[10px] uppercase font-bold tracking-wider">Total
                                                        Amount</span>
                                                    <span
                                                        class="text-base font-black text-charcoal">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB CONTENT: ACCOUNT SETTINGS -->
                    <div id="tab-settings-content" class="p-6 hidden space-y-6">
                        <h2 class="text-xl font-extrabold text-charcoal flex items-center gap-2">
                            <i class="fa-solid fa-user-pen text-jendaOrange"></i> Update Profile Details
                        </h2>

                        <form action="profile.php" method="POST" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label
                                        class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1">Username
                                        / Nickname</label>
                                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>"
                                        disabled
                                        class="bg-stone-100 cursor-not-allowed block w-full rounded-xl border border-stone-200 px-4 py-3 text-stone-500 text-sm focus:outline-none">
                                    <span class="text-[10px] text-stone-400 mt-1 block">To edit your profile name,
                                        contact restaurant support.</span>
                                </div>
                                <div>
                                    <label for="profile_email"
                                        class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1">Email
                                        Address</label>
                                    <input type="email" name="email" id="profile_email"
                                        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                        class="block w-full rounded-xl border border-stone-300 px-4 py-3 text-stone-900 placeholder-stone-400 focus:border-jendaOrange focus:outline-none focus:ring-1 focus:ring-jendaOrange text-sm"
                                        placeholder="your_email@gmail.com">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="profile_contact"
                                        class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1">Contact
                                        Phone Number *</label>
                                    <input type="text" name="contact" id="profile_contact" required
                                        value="<?php echo htmlspecialchars($user['contact']); ?>"
                                        class="block w-full rounded-xl border border-stone-300 px-4 py-3 text-stone-900 placeholder-stone-400 focus:border-jendaOrange focus:outline-none focus:ring-1 focus:ring-jendaOrange text-sm"
                                        placeholder="+233 241 234 567">
                                </div>
                                <div>
                                    <label for="profile_location"
                                        class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1">Delivery
                                        Address *</label>
                                    <input type="text" name="location" id="profile_location" required
                                        value="<?php echo htmlspecialchars($user['location']); ?>"
                                        class="block w-full rounded-xl border border-stone-300 px-4 py-3 text-stone-900 placeholder-stone-400 focus:border-jendaOrange focus:outline-none focus:ring-1 focus:ring-jendaOrange text-sm"
                                        placeholder="Apt, Street, City">
                                </div>
                            </div>

                            <div class="pt-2">
                                <button type="submit" name="update_details"
                                    class="w-full md:w-auto bg-jendaOrange text-white font-bold text-xs uppercase tracking-wider px-6 py-3 rounded-xl hover:bg-opacity-95 shadow transition-all duration-200">
                                    Save Details
                                </button>
                            </div>
                        </form>
                    </div>

                </div>

            </div>

            <!-- RIGHT COLUMN: Security/Password & Custom Actions -->
            <div class="space-y-8">

                <!-- SECURITY PANEL -->
                <div class="bg-white rounded-2xl shadow-sm border border-stone-100 p-6">
                    <h3 class="text-lg font-extrabold text-charcoal mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-shield-halved text-jendaOrange"></i> Account Security
                    </h3>

                    <form action="profile.php" method="POST" class="space-y-4">
                        <div>
                            <label for="current_password"
                                class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1">Current
                                Password</label>
                            <div class="relative">
                                <input type="password" name="current_password" id="current_password" required
                                    class="block w-full rounded-xl border border-stone-300 px-4 py-3 text-stone-900 placeholder-stone-400 focus:border-jendaOrange focus:outline-none focus:ring-1 focus:ring-jendaOrange text-sm"
                                    placeholder="••••••••">
                                <button type="button" onclick="toggleLocalVisibility('current_password', 'eye-curr')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-stone-400 hover:text-stone-600 focus:outline-none">
                                    <i id="eye-curr" class="fa-solid fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="new_password"
                                class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1">New
                                Password</label>
                            <div class="relative">
                                <input type="password" name="new_password" id="new_password" required
                                    class="block w-full rounded-xl border border-stone-300 px-4 py-3 text-stone-900 placeholder-stone-400 focus:border-jendaOrange focus:outline-none focus:ring-1 focus:ring-jendaOrange text-sm"
                                    placeholder="••••••••">
                                <button type="button" onclick="toggleLocalVisibility('new_password', 'eye-new')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-stone-400 hover:text-stone-600 focus:outline-none">
                                    <i id="eye-new" class="fa-solid fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="confirm_password"
                                class="block text-xs font-bold text-stone-500 uppercase tracking-wider mb-1">Confirm New
                                Password</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirm_password" required
                                    class="block w-full rounded-xl border border-stone-300 px-4 py-3 text-stone-900 placeholder-stone-400 focus:border-jendaOrange focus:outline-none focus:ring-1 focus:ring-jendaOrange text-sm"
                                    placeholder="••••••••">
                                <button type="button" onclick="toggleLocalVisibility('confirm_password', 'eye-conf')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-stone-400 hover:text-stone-600 focus:outline-none">
                                    <i id="eye-conf" class="fa-solid fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" name="update_password"
                            class="w-full bg-charcoal text-white hover:bg-stone-800 font-bold text-xs uppercase tracking-wider py-3 rounded-xl transition-all duration-200">
                            Update Password
                        </button>
                    </form>
                </div>

                <!-- QUICK ACTIONS CARD -->
                <div class="bg-white rounded-2xl shadow-sm border border-stone-100 p-6 space-y-4">
                    <h3 class="text-lg font-extrabold text-charcoal flex items-center gap-2">
                        <i class="fa-solid fa-bolt text-jendaOrange"></i> Alvida Services
                    </h3>
                    <p class="text-stone-500 text-xs">Looking for high quality dining support or custom reservation
                        requests?</p>

                    <div class="space-y-2">
                        <a href="index.php"
                            class="w-full flex items-center justify-between p-3.5 bg-stone-50 rounded-xl hover:bg-stone-100/80 border border-stone-200/50 text-sm font-bold text-stone-700 transition-all">
                            <span>Browse Culinary Menu</span>
                            <i class="fa-solid fa-arrow-right text-jendaOrange"></i>
                        </a>
                        <a href="#"
                            class="w-full flex items-center justify-between p-3.5 bg-stone-50 rounded-xl hover:bg-stone-100/80 border border-stone-200/50 text-sm font-bold text-stone-700 transition-all">
                            <span>Reserve a Table</span>
                            <i class="fa-solid fa-calendar-plus text-stone-400"></i>
                        </a>
                        <a href="#"
                            class="w-full flex items-center justify-between p-3.5 bg-stone-50 rounded-xl hover:bg-stone-100/80 border border-stone-200/50 text-sm font-bold text-stone-700 transition-all">
                            <span>Support Chat &amp; Help</span>
                            <i class="fa-solid fa-headset text-stone-400"></i>
                        </a>
                    </div>
                </div>

            </div>

        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-charcoal text-stone-500 py-6 border-t border-stone-800 text-center text-xs">
        <p>&copy; 2026 Alvida Restaurant. All Rights Reserved. Experiencing issues? Contact admin support.</p>
    </footer>

    <!-- INTERACTIVE SCRIPTS -->
    <script>
        // Tab toggle handler
        function switchDashboardTab(tab) {
            const historyTab = document.getElementById('tab-history-content');
            const settingsTab = document.getElementById('tab-settings-content');
            const btnHistory = document.getElementById('btn-history');
            const btnSettings = document.getElementById('btn-settings');

            if (tab === 'history') {
                historyTab.classList.remove('hidden');
                settingsTab.classList.add('hidden');
                btnHistory.className = "flex-1 py-4 px-6 text-center text-sm font-bold border-b-2 border-jendaOrange text-jendaOrange transition-all focus:outline-none flex items-center justify-center gap-2";
                btnSettings.className = "flex-1 py-4 px-6 text-center text-sm font-bold border-b-2 border-transparent text-stone-500 hover:text-stone-800 transition-all focus:outline-none flex items-center justify-center gap-2";
            } else {
                historyTab.classList.add('hidden');
                settingsTab.classList.remove('hidden');
                btnSettings.className = "flex-1 py-4 px-6 text-center text-sm font-bold border-b-2 border-jendaOrange text-jendaOrange transition-all focus:outline-none flex items-center justify-center gap-2";
                btnHistory.className = "flex-1 py-4 px-6 text-center text-sm font-bold border-b-2 border-transparent text-stone-500 hover:text-stone-800 transition-all focus:outline-none flex items-center justify-center gap-2";
            }
        }

        // Localized eye password visible toggle
        function toggleLocalVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa-solid fa-eye-slash text-xs';
            } else {
                input.type = 'password';
                icon.className = 'fa-solid fa-eye text-xs';
            }
        }
    </script>
</body>

</html>