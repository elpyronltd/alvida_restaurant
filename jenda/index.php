<?php


// Start session
session_start();

require_once 'include/db.php'; // Include database connection file

// Handle Reservation Form Submission
$reserve_msg = '';
$reserve_status = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_reservation') {
    $name = trim($_POST['reservation_name'] ?? '');
    $date = trim($_POST['reservation_date'] ?? '');
    $time = trim($_POST['reservation_time'] ?? '');
    $guests = intval($_POST['guests'] ?? 1);
    $customer_id = rand(1000, 9999); // Generate a temporary randomized customer reference ID

    if (!empty($name) && !empty($date) && !empty($time) && $guests > 0) {
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `reservations` (`customer_id`, `reservation_name`, `reservation_date`, `reservation_time`, `guests`, `status`) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$customer_id, $name, $date, $time, $guests]);
                $reserve_msg = "Your reservation request under '$name' has been received! Our chefs will review and approve shortly.";
                $reserve_status = 'success';
            } catch (PDOException $e) {
                $reserve_msg = "Error booking reservation. Please try again.";
                $reserve_status = 'error';
            }
        } else {
            $reserve_msg = "Database offline. Booking simulation succeeded! (Ref: #$customer_id)";
            $reserve_status = 'success';
        }
    } else {
        $reserve_msg = "Please fill in all booking fields correctly.";
        $reserve_status = 'error';
    }
}

// Fetch Popular Dishes from Database
$popular_dishes = [];
if ($pdo) {
    try {
        $stmt_dishes = $pdo->query("SELECT * FROM `foods` ORDER BY `id` DESC LIMIT 4");
        $popular_dishes = $stmt_dishes->fetchAll();
    } catch (PDOException $e) {
        // Fallback
    }
}

// Fallback high-res static images if database holds no items yet
if (empty($popular_dishes)) {
    $popular_dishes = [
        [
            'id' => 1,
            'name' => 'Alvida Mixed Grill',
            'category' => 'Main Dishes',
            'price' => 24.99,
            'picture_1' => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=600&q=80',
            'description' => 'A flavorful assortment of our finest grilled meat selections seasoned with specialty spices.'
        ],
        [
            'id' => 2,
            'name' => 'Truffle Pasta',
            'category' => 'Main Dishes',
            'price' => 18.99,
            'picture_1' => 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=600&q=80',
            'description' => 'Linguine tossed in rich white truffle cream sauce finished with shaved aged parmesan.'
        ],
        [
            'id' => 3,
            'name' => 'Grilled Salmon',
            'category' => 'Main Dishes',
            'price' => 22.99,
            'picture_1' => 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?auto=format&fit=crop&w=600&q=80',
            'description' => 'Seasoned Atlantic salmon grilled to flaky perfection served alongside fresh garden veggies.'
        ],
        [
            'id' => 4,
            'name' => 'Chocolate Lava Sensation',
            'category' => 'Desserts',
            'price' => 11.99,
            'picture_1' => 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&w=600&q=80',
            'description' => 'Warm, decadent molten chocolate cake served with vanilla bean ice cream scoop.'
        ]
    ];
}

// Fetch Popular Drinks from Database
$popular_drinks = [];
if ($pdo) {
    try {
        $stmt_drinks = $pdo->query("SELECT * FROM `drinks` ORDER BY `id` DESC LIMIT 4");
        $popular_drinks = $stmt_drinks->fetchAll();
    } catch (PDOException $e) {
        // Fallback
    }
}

if (empty($popular_drinks)) {
    $popular_drinks = [
        [
            'id' => 1,
            'name' => 'Exotic Passion Fruit Martini',
            'category' => 'Signature Cocktails',
            'price' => 14.50,
            'picture_1' => 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?auto=format&fit=crop&w=600&q=80',
            'description' => 'A sweet and sour medley of organic passion fruit liqueur, fresh lime juice, and premium vodka.'
        ],
        [
            'id' => 2,
            'name' => 'Classic Smoked Old Fashioned',
            'category' => 'Signature Cocktails',
            'price' => 16.00,
            'picture_1' => 'https://images.unsplash.com/photo-1514362545857-3bc16c4c7d1b?auto=format&fit=crop&w=600&q=80',
            'description' => 'Matured bourbon whiskey infused with artisanal bitters served in an oak-wood smoked decanter.'
        ],
        [
            'id' => 3,
            'name' => 'Organic Fresh Mint Mojito',
            'category' => 'Refreshments',
            'price' => 9.50,
            'picture_1' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=600&q=80',
            'description' => 'Crisp mint leaves muddled with cane sugar, crushed ice, white rum, and sparkling soda.'
        ],
        [
            'id' => 4,
            'name' => 'Italian Velvet Cabernet',
            'category' => 'Wines',
            'price' => 18.00,
            'picture_1' => 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?auto=format&fit=crop&w=600&q=80',
            'description' => 'A glass of premium, full-bodied Tuscan red wine notes of wild berries and velvet oak.'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alvida - Experience Extraordinary Dining</title>
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
                        brandOrange: {
                            50: '#fff6ed',
                            100: '#ffead4',
                            500: '#ff7e00', // Premium Alvida Orange
                            600: '#e66700',
                            700: '#bf4e00',
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

<body
    class="bg-white text-slate-800 antialiased selection:bg-brandOrange-100 selection:text-brandOrange-700 pb-20 md:pb-0">

    <!-- DESKTOP TOP HEADER (HIDDEN ON MOBILE) -->
    <header
        class="hidden md:block bg-white/95 backdrop-blur-md sticky top-0 z-50 border-b border-slate-100 transition-all">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">

                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-1 shrink-0 select-none">
                    <img src="assets/logo.png" alt="Alvida Logo" class="h-8 w-auto">
                </a>

                <!-- Nav Links -->
                <nav class="flex items-center gap-8">
                    <a href="index.php"
                        class="text-xs font-bold uppercase tracking-widest text-brandOrange-500 border-b-2 border-brandOrange-500 pb-1">Home</a>
                    <a href="#menu-dishes"
                        class="text-xs font-bold uppercase tracking-widest text-slate-600 hover:text-brandOrange-500 transition">Menu</a>
                    <a href="#reservations"
                        class="text-xs font-bold uppercase tracking-widest text-slate-600 hover:text-brandOrange-500 transition">Reservations</a>
                    <a href="#menu-dishes"
                        class="text-xs font-bold uppercase tracking-widest text-slate-600 hover:text-brandOrange-500 transition">Order
                        Online</a>
                    <a href="#private-dining"
                        class="text-xs font-bold uppercase tracking-widest text-slate-600 hover:text-brandOrange-500 transition">Private
                        Dining</a>
                    <a href="#about"
                        class="text-xs font-bold uppercase tracking-widest text-slate-600 hover:text-brandOrange-500 transition">About
                        Us</a>
                    <a href="#contact"
                        class="text-xs font-bold uppercase tracking-widest text-slate-600 hover:text-brandOrange-500 transition">Contact</a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-6">
                    <!-- Shopping Cart Link -->
                    <button class="relative p-2 text-slate-700 hover:text-brandOrange-500 transition shrink-0">
                        <i data-lucide="shopping-cart" class="h-6 w-6"></i>
                        <span
                            class="absolute top-0 right-0 h-4 w-4 bg-brandOrange-500 text-white rounded-full flex items-center justify-center text-[10px] font-bold">2</span>
                    </button>
                    <!-- Sign In Capsule -->
                    <a href="manage_restaurant_2v6.php"
                        class="inline-flex items-center justify-center px-6 py-2 border border-brandOrange-500 text-brandOrange-500 hover:bg-brandOrange-500 hover:text-white rounded-full text-xs font-bold uppercase tracking-widest transition duration-150">
                        Sign In
                    </a>
                </div>

            </div>
        </div>
    </header>

    <!-- MOBILE TOP HEADER (HIDDEN ON DESKTOP) -->
    <header
        class="md:hidden bg-white/95 backdrop-blur-md sticky top-0 z-50 border-b border-slate-100 py-4 px-6 flex justify-between items-center">
        <a href="index.php" class="text-2xl font-extrabold tracking-tight text-brandOrange-500"
            style="letter-spacing: -0.05em;">
            <img src="assets/logo.png" alt="Alvida Logo" class="h-7 w-auto">
        </a>

        <div class="flex items-center gap-4">
            <button class="relative p-2 text-slate-700">
                <i data-lucide="shopping-cart" class="h-6 w-6"></i>
                <span
                    class="absolute top-0 right-0 h-4 w-4 bg-brandOrange-500 text-white rounded-full flex items-center justify-center text-[10px] font-bold">2</span>
            </button>
            <button onclick="toggleMobileOverlayMenu()" class="p-2 text-slate-700">
                <i data-lucide="menu" class="h-7 w-7"></i>
            </button>
        </div>
    </header>

    <!-- MOBILE OVERLAY MENU -->
    <div id="mobile-overlay"
        class="hidden fixed inset-0 bg-charcoal-950/95 z-50 flex flex-col justify-between p-8 text-white">
        <div class="flex justify-between items-center">
            <span class="text-3xl font-extrabold tracking-tight text-brandOrange-500"
                style="letter-spacing: -0.05em;">
                <img src="assets/white_logo.png" alt="Alvida Logo" class="h-7 w-auto">
            </span>
            <button onclick="toggleMobileOverlayMenu()" class="text-white">
                <i data-lucide="x" class="h-8 w-8"></i>
            </button>
        </div>

        <nav class="flex flex-col gap-6 text-xl font-bold uppercase tracking-widest mt-12">
            <a href="index.php" onclick="toggleMobileOverlayMenu()" class="text-brandOrange-500">Home</a>
            <a href="#menu-dishes" onclick="toggleMobileOverlayMenu()" class="hover:text-brandOrange-500">Menu</a>
            <a href="#reservations" onclick="toggleMobileOverlayMenu()"
                class="hover:text-brandOrange-500">Reservations</a>
            <a href="#menu-dishes" onclick="toggleMobileOverlayMenu()" class="hover:text-brandOrange-500">Order
                Online</a>
            <a href="#private-dining" onclick="toggleMobileOverlayMenu()" class="hover:text-brandOrange-500">Private
                Dining</a>
            <a href="#about" onclick="toggleMobileOverlayMenu()" class="hover:text-brandOrange-500">About Us</a>
            <a href="#contact" onclick="toggleMobileOverlayMenu()" class="hover:text-brandOrange-500">Contact</a>
        </nav>

        <div class="border-t border-white/10 pt-8 space-y-4">
            <a href="manage_restaurant_2v6.php"
                class="block text-center py-3 bg-brandOrange-500 text-white font-bold rounded-xl uppercase tracking-widest text-sm">
                Portal Login
            </a>
            <p class="text-center text-xs text-slate-400">&copy; 2026 Alvida Dining Standard</p>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HERO SECTION -->
    <!-- ========================================================================= -->
    <section class="relative overflow-hidden bg-white py-12 md:py-24 border-b border-slate-50">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">

                <!-- Left Content -->
                <div class="lg:col-span-6 space-y-6 md:space-y-8 text-center lg:text-left z-10">
                    <div class="inline-flex items-center gap-2">
                        <span
                            class="text-xs md:text-sm font-extrabold uppercase tracking-widest text-brandOrange-500 border-b-2 border-brandOrange-500 pb-1">
                            Welcome to Alvida
                        </span>
                    </div>

                    <h1 class="text-4xl md:text-6xl lg:text-7xl font-extrabold tracking-tight text-slate-900 leading-[1.05]"
                        style="letter-spacing: -0.03em;">
                        EXPERIENCE <br class="hidden md:inline">
                        <span class="text-brandOrange-500">EXTRAORDINARY</span>
                    </h1>

                    <p class="text-sm md:text-base lg:text-lg text-slate-500 leading-relaxed max-w-lg mx-auto lg:mx-0">
                        Alvida is where exquisite flavors, impeccable service, and modern elegance come together to
                        create unforgettable dining moments.
                    </p>

                    <!-- Interactive Action Triggers -->
                    <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                        <a href="#reservations"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-brandOrange-500 hover:bg-brandOrange-600 text-white font-extrabold px-8 py-4 rounded-xl text-xs uppercase tracking-widest shadow-lg shadow-brandOrange-500/20 transition duration-150">
                            <i data-lucide="calendar" class="h-4.5 w-4.5"></i>
                            <span>Reserve a Table</span>
                        </a>

                        <a href="#menu-dishes"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white border border-slate-200 text-slate-800 hover:border-brandOrange-500 hover:text-brandOrange-500 font-extrabold px-8 py-4 rounded-xl text-xs uppercase tracking-widest transition duration-150">
                            <i data-lucide="shopping-bag" class="h-4.5 w-4.5"></i>
                            <span>Order Online</span>
                        </a>

                        <!-- Play Video trigger -->
                        <button onclick="toggleModal('video-modal')"
                            class="inline-flex items-center gap-3 text-xs font-extrabold uppercase tracking-widest hover:text-brandOrange-500 transition py-2 group">
                            <span
                                class="h-10 w-10 bg-brandOrange-50 text-brandOrange-500 group-hover:bg-brandOrange-500 group-hover:text-white rounded-full flex items-center justify-center shadow-md transition duration-200 shrink-0">
                                <i data-lucide="play" class="h-4 w-4 fill-current ml-0.5"></i>
                            </span>
                            <span>Watch Our Story</span>
                        </button>
                    </div>
                </div>

                <!-- Right Gorgeous Plated Lamb Dish Display -->
                <div class="lg:col-span-6 flex justify-center items-center relative">
                    <!-- Elegant background plate glow ring -->
                    <div
                        class="absolute inset-0 bg-gradient-to-tr from-brandOrange-500/5 to-transparent rounded-full blur-3xl scale-95 pointer-events-none">
                    </div>

                    <div class="relative w-full max-w-md md:max-w-lg aspect-square">
                        <!-- Custom lamb rack image aligning with template -->
                        <img src="https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=800&q=80"
                            alt="Exquisite Roasted Lamb Chops Plate"
                            class="w-full h-full object-cover rounded-[2.5rem] shadow-2xl shadow-charcoal-950/10 border-4 border-white transform hover:scale-[1.02] transition duration-500">

                        <!-- Floating culinary status badge -->
                        <div
                            class="absolute -bottom-4 -left-4 bg-white p-4 rounded-2xl shadow-xl flex items-center gap-3 border border-slate-100">
                            <div
                                class="h-10 w-10 rounded-xl bg-brandOrange-50 flex items-center justify-center text-brandOrange-500">
                                <i data-lucide="award" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Award Winning</span>
                                <span class="text-xs font-black text-slate-800">5-Star Standard</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- FEATURES BANNER -->
    <!-- ========================================================================= -->
    <section class="bg-slate-50 py-16 border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">

                <!-- Feature 1 -->
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex gap-4 items-start">
                    <div class="p-3 bg-brandOrange-50 rounded-xl text-brandOrange-500 shrink-0">
                        <i data-lucide="chef-hat" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-1">Exquisite Cuisine
                        </h3>
                        <p class="text-xs text-slate-500 leading-relaxed">A blend of traditional flavors and modern
                            culinary innovation.</p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex gap-4 items-start">
                    <div class="p-3 bg-brandOrange-50 rounded-xl text-brandOrange-500 shrink-0">
                        <i data-lucide="wine" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-1">Elegant Ambience</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">Sophisticated spaces designed for
                            unforgettable experiences.</p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex gap-4 items-start">
                    <div class="p-3 bg-brandOrange-50 rounded-xl text-brandOrange-500 shrink-0">
                        <i data-lucide="calendar" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-1">Easy Reservations
                        </h3>
                        <p class="text-xs text-slate-500 leading-relaxed">Book your tables instantly in just a few quick
                            clicks.</p>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex gap-4 items-start">
                    <div class="p-3 bg-brandOrange-50 rounded-xl text-brandOrange-500 shrink-0">
                        <i data-lucide="truck" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-1">Fast Delivery</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">Enjoy your favorite premium dishes delivered
                            hot to your door.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- POPULAR DISHES SECTION -->
    <!-- ========================================================================= -->
    <section id="menu-dishes" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 space-y-12">

            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-widest text-brandOrange-500">From Our
                        Kitchen</span>
                    <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 mt-1">Popular Culinary Specialties
                    </h2>
                </div>
                <a href="#reservations"
                    class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-widest text-brandOrange-500 hover:text-brandOrange-600 transition">
                    <span>Reserve Dining Seat</span>
                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
            </div>

            <!-- Culinary Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach ($popular_dishes as $dish): ?>
                    <div
                        class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs flex flex-col justify-between group transition duration-300 hover:border-slate-300">
                        <div>
                            <div class="relative h-56 bg-slate-100 overflow-hidden">
                                <img src="<?php echo htmlspecialchars($dish['picture_1']); ?>"
                                    alt="<?php echo htmlspecialchars($dish['name']); ?>"
                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-500">

                                <!-- Fast order plus circle icon mapped exactly to template -->
                                <button
                                    class="absolute top-4 right-4 h-10 w-10 bg-brandOrange-500 hover:bg-brandOrange-600 text-white rounded-full flex items-center justify-center shadow-md transform hover:rotate-90 transition duration-150">
                                    <i data-lucide="plus" class="h-5 w-5 font-bold"></i>
                                </button>

                                <span
                                    class="absolute bottom-4 left-4 bg-white/95 backdrop-blur-sm px-3 py-1 rounded-xl text-xs font-bold text-slate-900 shadow">
                                    <?php echo htmlspecialchars($dish['category']); ?>
                                </span>
                            </div>

                            <div class="p-5 space-y-2">
                                <div class="flex justify-between items-start gap-2">
                                    <h3 class="text-base font-bold text-slate-900 tracking-tight leading-snug">
                                        <?php echo htmlspecialchars($dish['name']); ?></h3>
                                    <span
                                        class="text-base font-extrabold text-brandOrange-500 shrink-0">$<?php echo number_format($dish['price'], 2); ?></span>
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed line-clamp-3">
                                    <?php echo htmlspecialchars($dish['description']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- POPULAR DRINKS SECTION -->
    <!-- ========================================================================= -->
    <section id="menu-drinks" class="py-20 bg-slate-50 border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 space-y-12">

            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-widest text-brandOrange-500">At the Bar</span>
                    <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 mt-1">Exquisite Beverage & Drinks
                    </h2>
                </div>
                <span
                    class="inline-flex items-center gap-1.5 text-xs font-extrabold uppercase tracking-widest text-brandOrange-500">
                    Premium Selections
                </span>
            </div>

            <!-- Drinks Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php foreach ($popular_drinks as $drink): ?>
                    <div
                        class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs flex flex-col justify-between group transition duration-300 hover:border-slate-300">
                        <div>
                            <div class="relative h-56 bg-slate-100 overflow-hidden">
                                <img src="<?php echo htmlspecialchars($drink['picture_1']); ?>"
                                    alt="<?php echo htmlspecialchars($drink['name']); ?>"
                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-500">

                                <button
                                    class="absolute top-4 right-4 h-10 w-10 bg-brandOrange-500 hover:bg-brandOrange-600 text-white rounded-full flex items-center justify-center shadow-md transform hover:rotate-90 transition duration-150">
                                    <i data-lucide="plus" class="h-5 w-5 font-bold"></i>
                                </button>

                                <span
                                    class="absolute bottom-4 left-4 bg-white/95 backdrop-blur-sm px-3 py-1 rounded-xl text-xs font-bold text-slate-900 shadow">
                                    <?php echo htmlspecialchars($drink['category']); ?>
                                </span>
                            </div>

                            <div class="p-5 space-y-2">
                                <div class="flex justify-between items-start gap-2">
                                    <h3 class="text-base font-bold text-slate-900 tracking-tight leading-snug">
                                        <?php echo htmlspecialchars($drink['name']); ?></h3>
                                    <span
                                        class="text-base font-extrabold text-brandOrange-500 shrink-0">$<?php echo number_format($drink['price'], 2); ?></span>
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed line-clamp-3">
                                    <?php echo htmlspecialchars($drink['description']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- RESERVATIONS TAB/SECTION -->
    <!-- ========================================================================= -->
    <section id="reservations" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div
                class="bg-charcoal-900 rounded-[2.5rem] overflow-hidden shadow-2xl grid grid-cols-1 lg:grid-cols-12 border border-charcoal-800">

                <!-- Form Area -->
                <div class="lg:col-span-7 p-8 md:p-12 lg:p-16 space-y-6 text-white">
                    <span class="text-xs font-extrabold uppercase tracking-widest text-brandOrange-500">Booking
                        Engine</span>
                    <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight">Reserve Your Executive Table</h2>
                    <p class="text-slate-400 text-sm leading-relaxed max-w-md">
                        Guarantee your culinary experiences with friends and corporate delegates. Real-time review and
                        feedback by restaurant kitchen chefs.
                    </p>

                    <!-- Alert message -->
                    <?php if (!empty($reserve_msg)): ?>
                        <div
                            class="p-4 rounded-xl border <?php echo $reserve_status === 'success' ? 'bg-emerald-950/50 border-emerald-500/30 text-emerald-300' : 'bg-rose-950/50 border-rose-500/30 text-rose-300'; ?> text-xs font-semibold">
                            <?php echo htmlspecialchars($reserve_msg); ?>
                        </div>
                    <?php endif; ?>

                    <form action="index.php#reservations" method="POST" class="space-y-4 pt-2">
                        <input type="hidden" name="action" value="submit_reservation">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Reservation
                                    Name *</label>
                                <input type="text" name="reservation_name" required
                                    placeholder="e.g. John Doe / VIP Group"
                                    class="w-full bg-charcoal-800 border border-charcoal-800 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-brandOrange-500 text-white transition">
                            </div>

                            <div>
                                <label
                                    class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Guests
                                    Volume *</label>
                                <select name="guests" required
                                    class="w-full bg-charcoal-800 border border-charcoal-800 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-brandOrange-500 text-white cursor-pointer transition">
                                    <option value="1">1 Person</option>
                                    <option value="2" selected>2 Guests</option>
                                    <option value="4">4 Guests</option>
                                    <option value="6">6 Guests</option>
                                    <option value="8">8 Guests</option>
                                    <option value="12">Executive Board / VIP (12+)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Date
                                    *</label>
                                <input type="date" name="reservation_date" required
                                    class="w-full bg-charcoal-800 border border-charcoal-800 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-brandOrange-500 text-white transition">
                            </div>

                            <div>
                                <label
                                    class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Arrival
                                    Time *</label>
                                <input type="time" name="reservation_time" required
                                    class="w-full bg-charcoal-800 border border-charcoal-800 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-brandOrange-500 text-white transition">
                            </div>
                        </div>

                        <button type="submit"
                            class="w-full py-4 bg-brandOrange-500 hover:bg-brandOrange-600 text-white font-extrabold rounded-xl uppercase tracking-widest text-xs transition duration-150 cursor-pointer">
                            Request Dining Space
                        </button>
                    </form>
                </div>

                <!-- Right Cover Image Area -->
                <div class="hidden lg:block lg:col-span-5 relative bg-charcoal-900">
                    <img src="https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=800&q=80"
                        alt="Dining Space Environment"
                        class="w-full h-full object-cover mix-blend-luminosity opacity-40">
                    <div class="absolute inset-0 bg-gradient-to-r from-charcoal-900 via-transparent to-transparent">
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- PRIVATE DINING / EXECUTIVE SPACE DETAILS -->
    <!-- ========================================================================= -->
    <section id="private-dining" class="py-20 bg-slate-50 border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

                <!-- Left Gallery Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-4">
                        <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=400&q=80"
                            alt="Alvida interior lounge" class="rounded-3xl shadow-sm object-cover h-64 w-full">
                        <img src="https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=400&q=80"
                            alt="Executive bar drinks" class="rounded-3xl shadow-sm object-cover h-40 w-full">
                    </div>
                    <div class="space-y-4 pt-8">
                        <img src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=400&q=80"
                            alt="Table layout close up" class="rounded-3xl shadow-sm object-cover h-40 w-full">
                        <img src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=400&q=80"
                            alt="Elegant dining experience" class="rounded-3xl shadow-sm object-cover h-64 w-full">
                    </div>
                </div>

                <!-- Right Content Area -->
                <div class="space-y-6">
                    <span class="text-xs font-bold uppercase tracking-widest text-brandOrange-500">Unmatched
                        Experiences</span>
                    <h2 class="text-3xl font-extrabold tracking-tight text-slate-900">Custom Luxury Private Dining</h2>
                    <p class="text-sm text-slate-500 leading-relaxed">
                        At Alvida, we cater unique, customized, private gourmet menus for corporate boards, milestone
                        banquets, and intimate luxury celebrations.
                    </p>
                    <ul class="space-y-3 text-xs font-bold uppercase tracking-wider text-slate-700">
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="h-4 w-4 text-brandOrange-500"></i>
                            <span>Personalized Chef-Driven Menu Curations</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="h-4 w-4 text-brandOrange-500"></i>
                            <span>Acoustic Isolations & Dedicated AV Technology</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i data-lucide="check" class="h-4 w-4 text-brandOrange-500"></i>
                            <span>Exclusive Sommelier & Premium Wine Access</span>
                        </li>
                    </ul>
                    <a href="#reservations"
                        class="inline-flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-bold uppercase tracking-widest px-8 py-3.5 rounded-xl text-xs">
                        <span>Book Private Space</span>
                    </a>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- GALLERY & GOOGLE REVIEWS GRID -->
    <!-- ========================================================================= -->
    <section id="about" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 space-y-12">

            <div class="text-center space-y-2">
                <span class="text-xs font-bold uppercase tracking-widest text-brandOrange-500">Community & Social</span>
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900">Our Guest Experience Reviews</h2>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">See how local epicureans and culinary enthusiasts
                    rate their experiences.</p>
            </div>

            <!-- Authentic Simulated Guest Reviews Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 flex flex-col justify-between">
                    <p class="text-xs text-slate-600 leading-relaxed italic">
                        "The Smoked Old Fashioned and roasted lamb chops were mind-blowing. Truly exceptional executive
                        level attention."
                    </p>
                    <div class="flex items-center gap-3 pt-4 border-t border-slate-200/50 mt-4">
                        <div class="h-10 w-10 bg-slate-200 rounded-full overflow-hidden">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&q=80"
                                alt="Sophia Mitchell" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Sophia Mitchell</span>
                            <span class="text-[10px] text-brandOrange-500 font-bold">★★★★★ Google Review</span>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 flex flex-col justify-between">
                    <p class="text-xs text-slate-600 leading-relaxed italic">
                        "Uncompromising ingredient selection. The truffle pasta tasted exactly like the fine dining
                        rooms of Florence."
                    </p>
                    <div class="flex items-center gap-3 pt-4 border-t border-slate-200/50 mt-4">
                        <div class="h-10 w-10 bg-slate-200 rounded-full overflow-hidden">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80"
                                alt="Marcus Chen" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Marcus Chen</span>
                            <span class="text-[10px] text-brandOrange-500 font-bold">★★★★★ Google Review</span>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 flex flex-col justify-between">
                    <p class="text-xs text-slate-600 leading-relaxed italic">
                        "Our anniversary dinner was executed to perfection. Seamless reservations, elegant seating, and
                        perfect dessert endings."
                    </p>
                    <div class="flex items-center gap-3 pt-4 border-t border-slate-200/50 mt-4">
                        <div class="h-10 w-10 bg-slate-200 rounded-full overflow-hidden">
                            <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&q=80"
                                alt="Elena Rostova" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Elena Rostova</span>
                            <span class="text-[10px] text-brandOrange-500 font-bold">★★★★★ Google Review</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- WALK TO JENDA (LOCATION & MAP) -->
    <!-- ========================================================================= -->
    <section id="contact" class="py-20 bg-slate-50 border-t border-slate-100">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 space-y-12">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

                <!-- Info block -->
                <div class="space-y-6">
                    <span class="text-xs font-bold uppercase tracking-widest text-brandOrange-500">How to Find Us</span>
                    <h2 class="text-3xl font-extrabold tracking-tight text-slate-900">Walk into Alvida Dining Room</h2>
                    <p class="text-sm text-slate-500 leading-relaxed">
                        We are located in the historic cultural heart of the city center. Accessible by secure private
                        underground car park or premium neighborhood transit.
                    </p>

                    <div class="space-y-4">
                        <div class="flex gap-4 items-start">
                            <div
                                class="p-2.5 bg-white border border-slate-200 text-brandOrange-500 rounded-lg shrink-0">
                                <i data-lucide="map-pin" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold uppercase text-slate-400">Street Address</h3>
                                <p class="text-sm font-semibold text-slate-900">772 Alvida Avenue, Premium Boulevard
                                    Sector, Accra</p>
                            </div>
                        </div>

                        <div class="flex gap-4 items-start">
                            <div
                                class="p-2.5 bg-white border border-slate-200 text-brandOrange-500 rounded-lg shrink-0">
                                <i data-lucide="phone" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold uppercase text-slate-400">Executive Reception Desk</h3>
                                <p class="text-sm font-semibold text-slate-900">+233 24 555 0198 /
                                    reservations@alvida.com</p>
                            </div>
                        </div>

                        <div class="flex gap-4 items-start">
                            <div
                                class="p-2.5 bg-white border border-slate-200 text-brandOrange-500 rounded-lg shrink-0">
                                <i data-lucide="clock" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold uppercase text-slate-400">Dining Room Schedule</h3>
                                <p class="text-sm font-semibold text-slate-900">Tue - Sun: 11:30 AM - 11:00 PM (Monday
                                    Closed)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Mock Map Embedding area conforming with instructions -->
                <div class="bg-white rounded-3xl overflow-hidden h-[340px] shadow-lg border border-slate-200 relative">
                    <!-- Beautiful interactive stylized landscape Map graphic -->
                    <iframe class="w-full h-full filter saturate-75 contrast-125"
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15883.351543632238!2d-0.18731518114389025!3d5.580198089307792!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xfdf9a3f9e9cf299%3A0xc3f8e5837db5bd49!2sAirport%20Residential%20Area%2C%20Accra!5e0!3m2!1sen!2sgh!4v1718000000000!5m2!1sen!2sgh"
                        style="border:0;" allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- CHROME FOOTER SECTION -->
    <!-- ========================================================================= -->
    <footer class="bg-charcoal-900 text-slate-300 py-12 md:py-16 border-t border-charcoal-800">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">

            <div class="space-y-4">
                <span class="text-2xl font-extrabold tracking-tight text-brandOrange-500"
                    style="letter-spacing: -0.05em;">ALV<span class="text-white">i</span>DA</span>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Uncompromising execution. Outstanding culinary techniques. Alvida dining standard centers only.
                </p>
                <!-- Google Rating integration -->
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-white">4.9 / 5.0</span>
                    <span class="text-xs text-brandOrange-500 font-bold">★★★★★</span>
                    <span class="text-[10px] text-slate-400">(840 Google Reviews)</span>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-extrabold uppercase tracking-widest text-white mb-4">Culinary Selection</h4>
                <ul class="space-y-2 text-xs text-slate-400">
                    <li><a href="#menu-dishes" class="hover:text-brandOrange-500 transition">Roasted Lamb Portions</a>
                    </li>
                    <li><a href="#menu-dishes" class="hover:text-brandOrange-500 transition">Imported Italian Pasta</a>
                    </li>
                    <li><a href="#menu-drinks" class="hover:text-brandOrange-500 transition">Smoked Bourbon Mixes</a>
                    </li>
                    <li><a href="#menu-dishes" class="hover:text-brandOrange-500 transition">Chocolate Molten Cake</a>
                    </li>
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-extrabold uppercase tracking-widest text-white mb-4">System Utilities</h4>
                <ul class="space-y-2 text-xs text-slate-400">
                    <li><a href="#reservations" class="hover:text-brandOrange-500 transition">Request Table Seat</a>
                    </li>
                    <li><a href="chef.php" class="hover:text-brandOrange-500 transition">Chef Kitchen Desk</a></li>
                    <li><a href="login.php" class="hover:text-brandOrange-500 transition">Admin Portal
                            Gate</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-xs font-extrabold uppercase tracking-widest text-white mb-4">Corporate Office</h4>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Alvida Corporate Headquarters &bull; West Airport Executive Block, Accra, Ghana.
                </p>
            </div>

        </div>

        <div
            class="max-w-7xl mx-auto px-6 lg:px-8 border-t border-white/5 pt-8 text-center text-xs text-slate-500 flex flex-col md:flex-row justify-between items-center gap-4">
            <span>&copy; 2026 Alvida Dining Standard. All rights reserved.</span>
            <div class="flex gap-4">
                <a href="#" class="hover:text-brandOrange-500">Privacy Policy</a>
                <span>&bull;</span>
                <a href="#" class="hover:text-brandOrange-500">Terms of Use</a>
            </div>
        </div>
    </footer>

    <!-- ========================================================================= -->
    <!-- MOBILE BOTTOM NAVIGATION CURVED FLOATING BAR (EXACTLY MATCHING MOBILE HOMEPAGE.PNG) -->
    <!-- ========================================================================= -->
    <nav
        class="md:hidden fixed bottom-4 left-4 right-4 bg-white/90 backdrop-blur-md border border-slate-200/50 rounded-2xl shadow-xl z-40 px-6 py-2.5 flex justify-between items-center transition-transform">

        <!-- Home -->
        <a href="index.php" class="flex flex-col items-center gap-0.5 text-brandOrange-500">
            <i data-lucide="home" class="h-5 w-5"></i>
            <span class="text-[9px] font-bold uppercase tracking-wider">Home</span>
            <!-- Active indicator dot -->
            <span class="h-1 w-1 bg-brandOrange-500 rounded-full"></span>
        </a>

        <!-- Menu -->
        <a href="#menu-dishes"
            class="flex flex-col items-center gap-0.5 text-slate-400 hover:text-brandOrange-500 transition">
            <i data-lucide="book-open" class="h-5 w-5"></i>
            <span class="text-[9px] font-bold uppercase tracking-wider">Menu</span>
        </a>

        <!-- RESERVE (ELEVATED PROMINENT CENTER BUTTON) -->
        <div class="relative -mt-6">
            <a href="#reservations"
                class="h-12 w-12 bg-brandOrange-500 text-white rounded-full flex items-center justify-center shadow-lg shadow-brandOrange-500/30 hover:bg-brandOrange-600 transition">
                <i data-lucide="calendar" class="h-6 w-6"></i>
            </a>
        </div>

        <!-- Order -->
        <a href="#menu-dishes"
            class="flex flex-col items-center gap-0.5 text-slate-400 hover:text-brandOrange-500 transition">
            <i data-lucide="shopping-bag" class="h-5 w-5"></i>
            <span class="text-[9px] font-bold uppercase tracking-wider">Order</span>
        </a>

        <!-- Account -->
        <a href="login.php"
            class="flex flex-col items-center gap-0.5 text-slate-400 hover:text-brandOrange-500 transition">
            <i data-lucide="user" class="h-5 w-5"></i>
            <span class="text-[9px] font-bold uppercase tracking-wider">Account</span>
        </a>

    </nav>

    <!-- STORY VIDEO MODAL (Simulated Media Player) -->
    <div id="video-modal"
        class="hidden fixed inset-0 z-50 bg-charcoal-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div
            class="bg-charcoal-900 border border-charcoal-800 rounded-2xl max-w-2xl w-full overflow-hidden shadow-2xl relative">
            <div class="bg-charcoal-950 text-white px-4 py-3 flex justify-between items-center">
                <span class="text-xs font-bold uppercase tracking-widest text-brandOrange-500">Watch Our Story</span>
                <button onclick="toggleModal('video-modal')" class="text-slate-400 hover:text-white cursor-pointer">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <!-- Beautiful simulated movie player content -->
            <div class="relative aspect-video bg-charcoal-950 flex items-center justify-center group">
                <img src="https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=800&q=80"
                    alt="Video Placeholder Cover"
                    class="absolute inset-0 w-full h-full object-cover opacity-60 mix-blend-luminosity">

                <div class="text-center z-10 p-6 space-y-4">
                    <span
                        class="inline-flex h-16 w-16 bg-brandOrange-500 text-white rounded-full items-center justify-center shadow-lg transform group-hover:scale-105 transition cursor-pointer">
                        <i data-lucide="play" class="h-6 w-6 fill-current ml-1"></i>
                    </span>
                    <h3 class="text-lg font-bold text-white">Alvida's Culinary Legacy (A Movie Documentary)</h3>
                    <p class="text-xs text-slate-300 max-w-sm mx-auto">Click to watch the creation of our master kitchen
                        and gourmet ingredients sourcing.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripting Controls -->
    <script>
        function toggleModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden');
        }

        function toggleMobileOverlayMenu() {
            const overlay = document.getElementById('mobile-overlay');
            if (overlay) overlay.classList.toggle('hidden');
        }
    </script>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>