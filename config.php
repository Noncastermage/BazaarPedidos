<?php
$host = 'localhost';
$dbname = 'tienda_pedidos';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Modify the createTables function to ensure it's compatible with existing databases
function createTables($conn) {
  // Products table
  $conn->exec("CREATE TABLE IF NOT EXISTS products (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(255) NOT NULL,
      price DECIMAL(10,2) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
  
  // Orders table - Create basic structure first
  $conn->exec("CREATE TABLE IF NOT EXISTS orders (
      id INT AUTO_INCREMENT PRIMARY KEY,
      customer_name VARCHAR(255) NOT NULL,
      phone VARCHAR(20) NOT NULL,
      total_amount DECIMAL(10,2) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
  
  // Check if is_paid column exists in orders table
  $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_paid'");
  $stmt->execute();
  $is_paid_column_exists = $stmt->rowCount() > 0;
  
  // Add is_paid column if it doesn't exist
  if (!$is_paid_column_exists) {
      $conn->exec("ALTER TABLE orders ADD COLUMN is_paid TINYINT(1) DEFAULT 0");
  }
  
  // Order items table - Create basic structure first
  $conn->exec("CREATE TABLE IF NOT EXISTS order_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      order_id INT NOT NULL,
      product_id INT NOT NULL,
      quantity INT NOT NULL,
      price DECIMAL(10,2) NOT NULL,
      subtotal DECIMAL(10,2) NOT NULL,
      FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
      FOREIGN KEY (product_id) REFERENCES products(id)
  )");
  
  // Check if status column exists in order_items table
  $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'status'");
  $stmt->execute();
  $status_column_exists = $stmt->rowCount() > 0;
  
  // Add status column if it doesn't exist
  if (!$status_column_exists) {
      $conn->exec("ALTER TABLE order_items ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pendiente'");
  }
}

// Create tables
createTables($conn);
?>

