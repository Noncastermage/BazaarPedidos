<?php
require_once 'config.php';
session_start();

// Check if ID and status are provided
if (!isset($_GET['id']) || !isset($_GET['status'])) {
    header('Location: view_orders.php');
    exit;
}

$orderId = $_GET['id'];
$newStatus = $_GET['status'];

// Validate status
if ($newStatus != '0' && $newStatus != '1') {
    $_SESSION['error'] = "Estado inválido.";
    header('Location: view_orders.php');
    exit;
}

try {
    // Check if is_delivered column exists in orders table
    $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_delivered'");
    $stmt->execute();
    $is_delivered_column_exists = $stmt->rowCount() > 0;
    
    // If is_delivered column doesn't exist, add it
    if (!$is_delivered_column_exists) {
        $conn->exec("ALTER TABLE orders ADD COLUMN is_delivered TINYINT(1) DEFAULT 0");
        $_SESSION['success'] = "La columna 'is_delivered' ha sido agregada a la tabla 'orders'.";
    }
    
    // Update order delivery status
    $stmt = $conn->prepare("UPDATE orders SET is_delivered = :status WHERE id = :id");
    $stmt->bindParam(':status', $newStatus);
    $stmt->bindParam(':id', $orderId);
    $stmt->execute();
    
    // Set success message
    $_SESSION['success'] = "Estado de entrega del pedido #$orderId actualizado correctamente.";
    
    // Redirect back to referring page
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'view_orders.php';
    header("Location: $referer");
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al actualizar el estado de entrega: " . $e->getMessage();
    header('Location: view_orders.php');
    exit;
}
?>

