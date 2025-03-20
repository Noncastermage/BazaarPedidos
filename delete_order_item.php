<?php
require_once 'config.php';
session_start();

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['order_id']) || empty($_GET['order_id'])) {
    $_SESSION['error'] = "Información del ítem no proporcionada.";
    header('Location: view_orders.php');
    exit;
}

$itemId = $_GET['id'];
$orderId = $_GET['order_id'];

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Get item details before deleting
    $stmt = $conn->prepare("
        SELECT oi.*, 
               p.name as product_name, 
               pv.name as variant_name,
               oi.product_deleted_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_variants pv ON oi.variant_id = pv.id
        WHERE oi.id = :id
    ");
    $stmt->bindParam(':id', $itemId);
    $stmt->execute();
    $item = $stmt->fetch();
    
    if (!$item) {
        throw new Exception("El ítem no existe.");
    }
    
    // Get product name for the message
    $productName = $item['product_deleted_name'] ?: $item['product_name'];
    if (!empty($item['variant_name'])) {
        $productName .= ' (' . $item['variant_name'] . ')';
    }
    
    // Delete the item
    $stmt = $conn->prepare("DELETE FROM order_items WHERE id = :id");
    $stmt->bindParam(':id', $itemId);
    $stmt->execute();
    
    // Update order total amount
    $stmt = $conn->prepare("
        UPDATE orders o
        SET o.total_amount = (
            SELECT COALESCE(SUM(oi.subtotal), 0)
            FROM order_items oi
            WHERE oi.order_id = :order_id
        )
        WHERE o.id = :order_id
    ");
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    
    // Check if there are any items left in the order
    $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = :order_id");
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    $itemCount = $stmt->fetchColumn();
    
    // If no items left, ask if user wants to delete the entire order
    if ($itemCount == 0) {
        // We'll handle this with a JavaScript confirmation on the client side
        $_SESSION['warning'] = "El pedido no tiene productos. Considere eliminar el pedido completo.";
    } else {
        // Check and update order status based on remaining items
        $stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN item_status = 'paid' THEN 1 ELSE 0 END) as paid
    FROM order_items 
    WHERE order_id = :order_id
");
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        $result = $stmt->fetch();

        // Update order status based on item statuses
        if ($result['total'] > 0) {
            if ($result['total'] == $result['paid']) {
                $newStatus = 'paid';
            } else {
                $newStatus = 'pending';
            }

            $stmt = $conn->prepare("UPDATE orders SET status = :status WHERE id = :id");
            $stmt->bindParam(':status', $newStatus);
            $stmt->bindParam(':id', $orderId);
            $stmt->execute();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success'] = "Producto \"$productName\" eliminado del pedido correctamente.";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    $_SESSION['error'] = "Error al eliminar el producto: " . $e->getMessage();
}

// Redirect back to order view
header("Location: view_order.php?id=" . $orderId);
exit;
?>

