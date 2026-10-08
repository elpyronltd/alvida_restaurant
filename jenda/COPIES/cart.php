<?php
session_start();
// Replace with your actual DB connection file
require_once 'include/db.php'; 
// Assuming $pdo exists for this example
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Executive Cart | Alvida</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 py-12">
    <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <h2 class="text-xl font-bold text-slate-800">Your Selection</h2>
        </div>

        <?php if (empty($_SESSION['cart'])): ?>
            <div class="p-10 text-center text-slate-500">Your cart is empty.</div>
        <?php else: ?>
            <table class="w-full text-left">
                <thead>
                    <tr class="text-slate-400 text-sm uppercase tracking-wider border-b">
                        <th class="px-6 py-4">Item</th>
                        <th class="px-6 py-4">Price</th>
                        <th class="px-6 py-4">Quantity</th>
                        <th class="px-6 py-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grand_total = 0;
                    foreach ($_SESSION['cart'] as $id => $item):
                        $total = $item['total_amount'] * $item['quantity'];
                        $grand_total += $total;
                        ?>
                        <tr class="border-b">
                            <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td class="px-6 py-4">$<?php echo number_format($item['price'], 2); ?></td>
                            <td class="px-6 py-4"><?php echo $item['quantity']; ?></td>
                            <td class="px-6 py-4 text-right font-bold">$<?php echo number_format($total, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="p-6 bg-slate-50 flex justify-end items-center gap-8">
                <div class="text-xl font-bold text-slate-900">Total: $<?php echo number_format($grand_total, 2); ?></div>
                <form action="order_processing.php" method="POST">
                    <button type="submit"
                        class="bg-orange-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-orange-700 transition">Place
                        Secure Order</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>