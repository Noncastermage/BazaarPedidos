<?php
require_once 'config.php';
session_start();

// Check if ID, status and order_id are provided
if (!isset($_GET['id']) || !isset($_GET['status']) || !isset($_GET['order_id'])) {
header('Location: view_orders.php');
exit;
}

$itemId = $_GET['id'];
$newStatus = $_GET['status'];
$orderId = $_GET['order_id'];

// Validate status
if ($newStatus != 'no pagado' && $newStatus != 'pagado') {
$_SESSION['error'] = "Estado inválido.";
header("Location: view_order.php?id=$orderId");
exit;
}

try {
// Check if status column exists
$stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'status'");
$stmt->execute();
$statusColumnExists = $stmt->rowCount() > 0;

if (!$statusColumnExists) {
    // Add status column if it doesn't exist
    $conn->exec("ALTER TABLE order_items ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'no pagado'");
    $_SESSION['success'] = "La columna 'status' ha sido agregada a la tabla 'order_items'.";
}

// Update item status
$stmt = $conn->prepare("UPDATE order_items SET status = :status WHERE id = :id");
$stmt->bindParam(':status', $newStatus);
$stmt->bindParam(':id', $itemId);
$stmt->execute();

// If item is marked as no pagado, ensure the order is not marked as paid
if ($newStatus == 'no pagado') {
    // Check if is_paid column exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_paid'");
    $stmt->execute();
    $is_paid_column_exists = $stmt->rowCount() > 0;
    
    if ($is_paid_column_exists) {
        // Get current order status
        $stmt = $conn->prepare("SELECT is_paid FROM orders WHERE id = :id");
        $stmt->bindParam(':id', $orderId);
        $stmt->execute();
        $orderIsPaid = $stmt->fetchColumn();
        
        // If order is paid, mark it as pending
        if ($orderIsPaid) {
            $stmt = $conn->prepare("UPDATE orders SET is_paid = 0 WHERE id = :id");
            $stmt->bindParam(':id', $orderId);
            $stmt->execute();
            $_SESSION['success'] = "Estado del producto actualizado correctamente. El pedido ha sido marcado como pendiente.";
        } else {
            $_SESSION['success'] = "Estado del producto actualizado correctamente.";
        }
    } else {
        $_SESSION['success'] = "Estado del producto actualizado correctamente.";
    }
} else {
    // Item is marked as pagado, check if all items are pagado
    $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = :order_id AND status != 'pagado'");
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    $pendingItems = $stmt->fetchColumn();
    
    // If no pending items, mark order as paid
    if ($pendingItems == 0) {
        $stmt = $conn->prepare("UPDATE orders SET is_paid = 1 WHERE id = :id");
        $stmt->bindParam(':id', $orderId);
        $stmt->execute();
        $_SESSION['success'] = "Estado del producto actualizado correctamente. Todos los productos están pagados, el pedido ha sido marcado como pagado.";
    } else {
        $_SESSION['success'] = "Estado del producto actualizado correctamente.";
    }
}

// Redirect back to order view
header("Location: view_order.php?id=$orderId");
exit;

} catch (PDOException $e) {
$_SESSION['error'] = "Error al actualizar el estado: " . $e->getMessage();
header("Location: view_order.php?id=$orderId");
exit;
}
?>

