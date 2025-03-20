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
    // Check if is_paid column exists in orders table
    $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_paid'");
    $stmt->execute();
    $is_paid_column_exists = $stmt->rowCount() > 0;
    
    // If is_paid column doesn't exist, add it
    if (!$is_paid_column_exists) {
        $conn->exec("ALTER TABLE orders ADD COLUMN is_paid TINYINT(1) DEFAULT 0");
        $_SESSION['success'] = "La columna 'is_paid' ha sido agregada a la tabla 'orders'.";
    }
    
    // If trying to mark as paid, check if all items are completed
    if ($newStatus == '1') {
        // Check if status column exists
        $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'status'");
        $stmt->execute();
        $statusColumnExists = $stmt->rowCount() > 0;
        
        if ($statusColumnExists) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = :order_id AND status = 'pendiente'");
            $stmt->bindParam(':order_id', $orderId);
            $stmt->execute();
            $pendingItems = $stmt->fetchColumn();
            
            if ($pendingItems > 0) {
                $_SESSION['error'] = "No se puede marcar el pedido como pagado porque tiene productos pendientes.";
                
                // Redirect back to referring page
                $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'view_orders.php';
                header("Location: $referer");
                exit;
            }
        }
    }
    
    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET is_paid = :status WHERE id = :id");
    $stmt->bindParam(':status', $newStatus);
    $stmt->bindParam(':id', $orderId);
    $stmt->execute();
    
    // Set success message
    $_SESSION['success'] = "Estado del pedido #$orderId actualizado correctamente.";
    
    // Redirect back to referring page
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'view_orders.php';
    header("Location: $referer");
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al actualizar el estado: " . $e->getMessage();
    header('Location: view_orders.php');
    exit;
}
?>

