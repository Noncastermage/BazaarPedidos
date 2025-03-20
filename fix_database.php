<?php
require_once 'config.php';

try {
  echo "<h1>Reparación de la Base de Datos</h1>";
  
  // 1. Verificar la estructura de las tablas
  echo "<h2>Verificando estructura de tablas...</h2>";
  
  // Verificar tabla products
  $conn->exec("
      CREATE TABLE IF NOT EXISTS products (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(255) NOT NULL,
          price DECIMAL(10,2) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
  ");
  echo "Tabla 'products' verificada.<br>";
  
  // Verificar tabla orders
  $conn->exec("
      CREATE TABLE IF NOT EXISTS orders (
          id INT AUTO_INCREMENT PRIMARY KEY,
          customer_name VARCHAR(255) NOT NULL,
          phone VARCHAR(20) NOT NULL,
          total_amount DECIMAL(10,2) NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
  ");
  echo "Tabla 'orders' verificada.<br>";
  
  // Verificar columna is_paid en orders
  $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_paid'");
  $stmt->execute();
  if ($stmt->rowCount() == 0) {
      $conn->exec("ALTER TABLE orders ADD COLUMN is_paid TINYINT(1) DEFAULT 0");
      echo "Columna 'is_paid' agregada a la tabla 'orders'.<br>";
  }

  // Verificar columna updated_at en orders
  $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'updated_at'");
  $stmt->execute();
  if ($stmt->rowCount() == 0) {
      $conn->exec("ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
      echo "Columna 'updated_at' agregada a la tabla 'orders'.<br>";
  }
  
  // Verificar tabla order_items
  $conn->exec("
      CREATE TABLE IF NOT EXISTS order_items (
          id INT AUTO_INCREMENT PRIMARY KEY,
          order_id INT NOT NULL,
          product_id INT NOT NULL,
          quantity INT NOT NULL,
          price DECIMAL(10,2) NOT NULL,
          subtotal DECIMAL(10,2) NOT NULL
      )
  ");
  echo "Tabla 'order_items' verificada.<br>";
  
  // Verificar columna status en order_items
  $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'status'");
  $stmt->execute();
  if ($stmt->rowCount() == 0) {
      $conn->exec("ALTER TABLE order_items ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pendiente'");
      echo "Columna 'status' agregada a la tabla 'order_items'.<br>";
  }
  
  // Verificar columna variables en order_items
  $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'variables'");
  $stmt->execute();
  if ($stmt->rowCount() == 0) {
      $conn->exec("ALTER TABLE order_items ADD COLUMN variables TEXT NULL");
      echo "Columna 'variables' agregada a la tabla 'order_items'.<br>";
  }
  
  // Verificar tabla product_variables
  $stmt = $conn->prepare("SHOW TABLES LIKE 'product_variables'");
  $stmt->execute();
  $product_variables_table_exists = $stmt->rowCount() > 0;
  if ($product_variables_table_exists == 0) {
      $conn->exec("
          CREATE TABLE product_variables (
              id INT AUTO_INCREMENT PRIMARY KEY,
              product_id INT NOT NULL,
              name VARCHAR(255) NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
          )
      ");
      echo "Tabla 'product_variables' creada.<br>";
  }
  
  // Verificar si la columna stock existe en la tabla product_variables
  if ($product_variables_table_exists) {
      $stmt = $conn->prepare("SHOW COLUMNS FROM product_variables LIKE 'stock'");
      $stmt->execute();
      $variable_stock_column_exists = $stmt->rowCount() > 0;
      
      if (!$variable_stock_column_exists) {
          // Añadir la columna stock a la tabla product_variables
          $conn->exec("ALTER TABLE product_variables ADD COLUMN stock INT DEFAULT 0");
          echo "La columna 'stock' ha sido agregada a la tabla 'product_variables'.<br>";
      } else {
          echo "La columna 'stock' ya existe en la tabla 'product_variables'.<br>";
      }
  }
  
  // 2. Verificar y corregir las claves foráneas
  echo "<h2>Verificando claves foráneas...</h2>";
  
  // Eliminar claves foráneas existentes para recrearlas
  try {
      $conn->exec("ALTER TABLE order_items DROP FOREIGN KEY order_items_ibfk_1");
      echo "Clave foránea order_items_ibfk_1 eliminada.<br>";
  } catch (PDOException $e) {
      echo "No se encontró la clave foránea order_items_ibfk_1 o ya fue eliminada.<br>";
  }
  
  try {
      $conn->exec("ALTER TABLE order_items DROP FOREIGN KEY order_items_ibfk_2");
      echo "Clave foránea order_items_ibfk_2 eliminada.<br>";
  } catch (PDOException $e) {
      echo "No se encontró la clave foránea order_items_ibfk_2 o ya fue eliminada.<br>";
  }
  
  // Verificar y limpiar referencias inválidas
  echo "<h2>Verificando referencias inválidas...</h2>";
  
  // Buscar elementos de pedido con referencias a productos que no existen
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
      
      // Eliminar los elementos de pedido inválidos
      $stmt = $conn->prepare("DELETE FROM order_items WHERE id = :id");
      foreach ($invalidItems as $item) {
          $stmt->bindParam(':id', $item['id']);
          $stmt->execute();
          echo "Eliminado elemento de pedido ID " . $item['id'] . " (producto_id: " . $item['product_id'] . ").<br>";
      }
      
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
          echo "Actualizado total del pedido ID " . $orderId . ".<br>";
      }
  } else {
      echo "No se encontraron referencias inválidas.<br>";
  }
  
  // Buscar elementos de pedido con referencias a pedidos que no existen
  $stmt = $conn->prepare("
      SELECT oi.id, oi.order_id, oi.product_id 
      FROM order_items oi 
      LEFT JOIN orders o ON oi.order_id = o.id 
      WHERE o.id IS NULL
  ");
  $stmt->execute();
  $invalidItems = $stmt->fetchAll();
  
  if (count($invalidItems) > 0) {
      echo "Se encontraron " . count($invalidItems) . " elementos de pedido con referencias a pedidos que ya no existen.<br>";
      
      // Eliminar los elementos de pedido inválidos
      $stmt = $conn->prepare("DELETE FROM order_items WHERE id = :id");
      foreach ($invalidItems as $item) {
          $stmt->bindParam(':id', $item['id']);
          $stmt->execute();
          echo "Eliminado elemento de pedido ID " . $item['id'] . " (order_id: " . $item['order_id'] . ").<br>";
      }
  } else {
      echo "No se encontraron referencias inválidas a pedidos.<br>";
  }
  
  // 3. Recrear las claves foráneas con ON DELETE CASCADE
  echo "<h2>Recreando claves foráneas...</h2>";
  
  $conn->exec("
      ALTER TABLE order_items
      ADD CONSTRAINT order_items_ibfk_1
      FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
  ");
  echo "Clave foránea order_items_ibfk_1 recreada con ON DELETE CASCADE.<br>";
  
  $conn->exec("
      ALTER TABLE order_items
      ADD CONSTRAINT order_items_ibfk_2
      FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
  ");
  echo "Clave foránea order_items_ibfk_2 recreada con ON DELETE RESTRICT.<br>";
  
  // 4. Verificar si hay productos en la tabla
  $stmt = $conn->prepare("SELECT COUNT(*) FROM products");
  $stmt->execute();
  $productCount = $stmt->fetchColumn();

  if ($productCount == 0) {
      echo "<h2>No hay productos en la base de datos</h2>";
      echo "<p>Puede agregar productos desde la sección 'Gestionar Productos'.</p>";
  } else {
      echo "<h2>Productos existentes: $productCount</h2>";
  }
  
  echo "<h2>¡Base de datos reparada correctamente!</h2>";
  
} catch (PDOException $e) {
  echo "<h2>Error al reparar la base de datos:</h2>";
  echo "<p>" . $e->getMessage() . "</p>";
}
?>

<p><a href="index.php" class="btn-primary">Volver al sistema</a></p>

<style>
  body {
      font-family: Arial, sans-serif;
      line-height: 1.6;
      margin: 20px;
      padding: 0;
      color: #333;
  }
  h1 {
      color: #2c3e50;
      border-bottom: 2px solid #3498db;
      padding-bottom: 10px;
  }
  h2 {
      color: #2980b9;
      margin-top: 20px;
  }
  p {
      margin: 10px 0;
  }
  .btn-primary {
      display: inline-block;
      background-color: #3498db;
      color: white;
      padding: 10px 15px;
      text-decoration: none;
      border-radius: 4px;
      margin-top: 20px;
  }
  .btn-primary:hover {
      background-color: #2980b9;
  }
</style>

