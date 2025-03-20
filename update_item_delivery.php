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
if ($newStatus != '0' && $newStatus != '1') {
  $_SESSION['error'] = "Estado inválido.";
  header("Location: view_order.php?id=$orderId");
  exit;
}

try {
  // Check if is_delivered column exists in order_items
  $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'is_delivered'");
  $stmt->execute();
  $is_delivered_column_exists = $stmt->rowCount() > 0;

  if (!$is_delivered_column_exists) {
    // Add is_delivered column if it doesn't exist
    $conn->exec("ALTER TABLE order_items ADD COLUMN is_delivered TINYINT(1) NOT NULL DEFAULT 0");
    $_SESSION['success'] = "La columna 'is_delivered' ha sido agregada a la tabla 'order_items'.";
  }

  // Update item delivery status
  $stmt = $conn->prepare("UPDATE order_items SET is_delivered = :status WHERE id = :id");
  $stmt->bindParam(':status', $newStatus);
  $stmt->bindParam(':id', $itemId);
  $stmt->execute();

  // Check if all items are delivered
  $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = :order_id AND is_delivered = 0");
  $stmt->bindParam(':order_id', $orderId);
  $stmt->execute();
  $pendingItems = $stmt->fetchColumn();
  
  // Check if is_delivered column exists in orders table
  $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_delivered'");
  $stmt->execute();
  $order_is_delivered_column_exists = $stmt->rowCount() > 0;
  
  if ($order_is_delivered_column_exists) {
    // If no pending items, mark order as delivered
    if ($pendingItems == 0) {
      $stmt = $conn->prepare("UPDATE orders SET is_delivered = 1 WHERE id = :id");
      $stmt->bindParam(':id', $orderId);
      $stmt->execute();
      $_SESSION['success'] = "Estado de entrega del producto actualizado correctamente. Todos los productos están entregados, el pedido ha sido marcado como entregado.";
    } else {
      // If there are pending items, mark order as not delivered
      $stmt = $conn->prepare("UPDATE orders SET is_delivered = 0 WHERE id = :id");
      $stmt->bindParam(':id', $orderId);
      $stmt->execute();
      $_SESSION['success'] = "Estado de entrega del producto actualizado correctamente.";
    }
  } else {
    $_SESSION['success'] = "Estado de entrega del producto actualizado correctamente.";
  }

  // Redirect back to order view
  header("Location: view_order.php?id=$orderId");
  exit;

} catch (PDOException $e) {
  $_SESSION['error'] = "Error al actualizar el estado de entrega: " . $e->getMessage();
  header("Location: view_order.php?id=$orderId");
  exit;
}
?>

