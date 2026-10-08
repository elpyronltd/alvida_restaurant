<?php
session_start();
// require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['cart'])) {
    try {
        // 1. Insert into orders table
        // $stmt = $pdo->prepare("INSERT INTO orders (customer_id, total_amount, payment_method) VALUES (?, ?, ?)");
        // $stmt->execute([$current_user_id, $total, 'card']);
        // $order_id = $pdo->lastInsertId();

        // 2. Loop through session cart and insert into order_items
        foreach ($_SESSION['cart'] as $id => $item) {
            // $stmt_items = $pdo->prepare("INSERT INTO order_items (order_id, food_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            // $stmt_items->execute([$order_id, $id, $item['quantity'], $item['price']]);
        }

        unset($_SESSION['cart']);
        echo "Order placed successfully.";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: cart.php");
}