<?php
require_once 'config.php';

try {
  // Check if status column exists in order_items table
  $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'status'");
  $stmt->execute();
  $status_column_exists = $stmt->rowCount() > 0;
  
  // Add status column if it doesn't exist
  if (!$status_column_exists) {
      $conn->exec("ALTER TABLE order_items ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'no pagado'");
      echo "La columna 'status' ha sido agregada a la tabla 'order_items'.<br>";
  } else {
      echo "La columna 'status' ya existe en la tabla 'order_items'.<br>";
      
      // Actualizar los estados existentes
      $conn->exec("UPDATE order_items SET status = 'pagado' WHERE status = 'completado'");
      $conn->exec("UPDATE order_items SET status = 'no pagado' WHERE status = 'pendiente'");
      echo "Los estados de los productos han sido actualizados (completado -> pagado, pendiente -> no pagado).<br>";
  }
  
  // Check if is_paid column exists in orders table
  $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_paid'");
  $stmt->execute();
  $is_paid_column_exists = $stmt->rowCount() > 0;
  
  // Add is_paid column if it doesn't exist
  if (!$is_paid_column_exists) {
      $conn->exec("ALTER TABLE orders ADD COLUMN is_paid TINYINT(1) DEFAULT 0");
      echo "La columna 'is_paid' ha sido agregada a la tabla 'orders'.<br>";
  } else {
      echo "La columna 'is_paid' ya existe en la tabla 'orders'.<br>";
  }

  // Add updated_at column to orders table
  $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'updated_at'");
  $stmt->execute();
  $updated_at_column_exists = $stmt->rowCount() > 0;

  // Add updated_at column if it doesn't exist
  if (!$updated_at_column_exists) {
      $conn->exec("ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
      echo "La columna 'updated_at' ha sido agregada a la tabla 'orders'.<br>";
  } else {
      echo "La columna 'updated_at' ya existe en la tabla 'orders'.<br>";
  }
  
  // Verificar si hay productos en la tabla
  $stmt = $conn->prepare("SELECT COUNT(*) FROM products");
  $stmt->execute();
  $productCount = $stmt->fetchColumn();

  if ($productCount == 0) {
      echo "No hay productos en la base de datos. Puede agregar productos desde la sección 'Gestionar Productos'.<br>";
  } else {
      echo "Productos existentes: $productCount<br>";
  }
  
  // Verificar y corregir las restricciones de clave foránea
  try {
      // Verificar si hay pedidos con productos que ya no existen
      $stmt = $conn->prepare("
          SELECT oi.id, oi.order_id, oi.product_id 
          FROM order_items oi 
          LEFT JOIN products p ON oi.product_id = p.id 
          WHERE p.id IS NULL
      ");
      $stmt->execute();
      $invalidItems = $stmt->fetchAll();
      
      if (count($invalidItems) > 0) {
          echo "Se encontraron " . count($invalidItems) . " elementos de pedido con referencias a productos que ya no existen.<br>";
          
          // Opción 1: Eliminar los elementos de pedido inválidos
          $stmt = $conn->prepare("DELETE FROM order_items WHERE id = :id");
          foreach ($invalidItems as $item) {
              $stmt->bindParam(':id', $item['id']);
              $stmt->execute();
          }
          echo "Se han eliminado los elementos de pedido inválidos.<br>";
          
          // Actualizar los totales de los pedidos afectados
          $affectedOrders = array_unique(array_column($invalidItems, 'order_id'));
          $stmt = $conn->prepare("
              UPDATE orders o
              SET total_amount = (
                  SELECT COALESCE(SUM(subtotal), 0)
                  FROM order_items
                  WHERE order_id = o.id
              )
              WHERE id = :order_id
          ");
          
          foreach ($affectedOrders as $orderId) {
              $stmt->bindParam(':order_id', $orderId);
              $stmt->execute();
          }
          echo "Se han actualizado los totales de los pedidos afectados.<br>";
      }
  } catch (PDOException $e) {
      echo "Error al verificar las referencias de productos: " . $e->getMessage() . "<br>";
  }
  
  echo "Base de datos actualizada correctamente.";
  
} catch (PDOException $e) {
  echo "Error al actualizar la base de datos: " . $e->getMessage();
}
?>

<p><a href="index.php">Volver al sistema</a></p>

