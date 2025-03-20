<?php
require_once 'config.php';
session_start();

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: view_orders.php');
    exit;
}

$orderId = $_GET['id'];

try {
    // Delete order (will cascade delete order_items due to foreign key constraint)
    $stmt = $conn->prepare("DELETE FROM orders WHERE id = :id");
    $stmt->bindParam(':id', $orderId);
    $stmt->execute();
    
    $_SESSION['success'] = "Pedido #$orderId eliminado correctamente.";
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al eliminar el pedido: " . $e->getMessage();
}

// Redirect back to referring page
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'view_orders.php';
header("Location: $referer");
exit;
?>

