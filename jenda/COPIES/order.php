<?php
session_start();
// Logic to receive form data: food_id, quantity, etc.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $quantity = (int) $_POST['quantity'];

    if (!isset($_SESSION['cart']))
        $_SESSION['cart'] = [];

    // Add or update item in cart session
    $_SESSION['cart'][$id] = [
        'name' => $name,
        'price' => $price,
        'quantity' => $quantity
    ];
}
header("Location: cart.php");
exit;