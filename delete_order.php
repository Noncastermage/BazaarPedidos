<?php
require_once 'config.php';
session_start();

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
  $_SESSION['error'] = "ID de pedido no proporcionado.";
  header('Location: view_orders.php');
  exit;
}

$orderId = $_GET['id'];

try {
  // Iniciar transacción
  $conn->beginTransaction();
  
  // Verificar si existe la columna stock en products
  $stmt = $conn->prepare("SHOW COLUMNS FROM products LIKE 'stock'");
  $stmt->execute();
  $stock_column_exists = $stmt->rowCount() > 0;
  
  // Si existe la columna stock, restaurar el stock de los productos
  if ($stock_column_exists) {
    // Obtener los items del pedido
    $stmt = $conn->prepare("
  SELECT oi.*, 
    CASE 
      WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'products' AND COLUMN_NAME = 'has_variables')
      THEN p.has_variables 
      ELSE (SELECT COUNT(*) > 0 FROM product_variables WHERE product_id = p.id) 
    END as has_variables
  FROM order_items oi
  JOIN products p ON oi.product_id = p.id
  WHERE oi.order_id = :order_id
");
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    $orderItems = $stmt->fetchAll();
    
    // Restaurar el stock de cada producto
    foreach ($orderItems as $item) {
      $productId = $item['product_id'];
      $quantity = $item['quantity'];
      $variables = isset($item['variables']) ? $item['variables'] : '';
      $hasVariables = isset($item['has_variables']) ? (int)$item['has_variables'] : 0;
      
      if ($hasVariables && !empty($variables)) {
        // Restaurar stock de la variable específica
        $variableName = $variables;
        // Si la variable tiene formato "nombre: especificación", extraer solo el nombre
        if (strpos($variableName, ':') !== false) {
          $variableName = trim(substr($variableName, 0, strpos($variableName, ':')));
        }
        
        $stmt = $conn->prepare("UPDATE product_variables SET stock = stock + :quantity 
                             WHERE product_id = :product_id AND name = :variable_name");
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':product_id', $productId);
        $stmt->bindParam(':variable_name', $variableName);
        $stmt->execute();
      } else {
        // Restaurar stock del producto general
        $stmt = $conn->prepare("UPDATE products SET stock = stock + :quantity WHERE id = :product_id");
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
      }
    }
  }
  
  // Eliminar los elementos del pedido (no es necesario hacerlo explícitamente debido a ON DELETE CASCADE,
  // pero lo hacemos por claridad)
  $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = :order_id");
  $stmt->bindParam(':order_id', $orderId);
  $stmt->execute();
  
  // Eliminar el pedido
  $stmt = $conn->prepare("DELETE FROM orders WHERE id = :id");
  $stmt->bindParam(':id', $orderId);
  $stmt->execute();
  
  // Confirmar transacción
  $conn->commit();
  
  $_SESSION['success'] = "Pedido #$orderId eliminado correctamente y stock restaurado.";
  
} catch (PDOException $e) {
  // Revertir transacción en caso de error
  $conn->rollBack();
  $_SESSION['error'] = "Error al eliminar el pedido: " . $e->getMessage();
}

// Redirigir a la lista de pedidos
header('Location: view_orders.php');
exit;
?>

