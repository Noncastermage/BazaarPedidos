<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
      // Modificar la parte donde se procesa el método de pago
      // Get form data
      $customerName = $_POST['customer_name'];
      $paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Fisico';
      $isPaid = 0; // Siempre pendiente por defecto
      $isDelivered = 0; // Siempre pendiente por defecto
      $cartItems = json_decode($_POST['cart_items'], true);
      $totalAmount = $_POST['total_amount'];
      
      // Validate cart is not empty
      if (empty($cartItems)) {
          $_SESSION['error'] = "El carrito está vacío. Agregue productos antes de guardar el pedido.";
          header('Location: index.php');
          exit;
      }
      
      // Check if payment_method column exists in orders table
      $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'payment_method'");
      $stmt->execute();
      $payment_method_column_exists = $stmt->rowCount() > 0;
      
      // Check if is_paid column exists in orders table
      $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_paid'");
      $stmt->execute();
      $is_paid_column_exists = $stmt->rowCount() > 0;
      
      // Check if is_delivered column exists in orders table
      $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_delivered'");
      $stmt->execute();
      $is_delivered_column_exists = $stmt->rowCount() > 0;
      
      // Check if stock column exists in products table
      $stmt = $conn->prepare("SHOW COLUMNS FROM products LIKE 'stock'");
      $stmt->execute();
      $stock_column_exists = $stmt->rowCount() > 0;
      
      // Begin transaction
      $conn->beginTransaction();
      
      // Insert order
      if ($payment_method_column_exists && $is_paid_column_exists && $is_delivered_column_exists) {
          $stmt = $conn->prepare("INSERT INTO orders (customer_name, payment_method, is_paid, is_delivered, total_amount) VALUES (:customer_name, :payment_method, :is_paid, :is_delivered, :total_amount)");
          $stmt->bindParam(':customer_name', $customerName);
          $stmt->bindParam(':payment_method', $paymentMethod);
          $stmt->bindParam(':is_paid', $isPaid);
          $stmt->bindParam(':is_delivered', $isDelivered);
          $stmt->bindParam(':total_amount', $totalAmount);
      } elseif ($payment_method_column_exists && $is_paid_column_exists) {
          $stmt = $conn->prepare("INSERT INTO orders (customer_name, payment_method, is_paid, total_amount) VALUES (:customer_name, :payment_method, :is_paid, :total_amount)");
          $stmt->bindParam(':customer_name', $customerName);
          $stmt->bindParam(':payment_method', $paymentMethod);
          $stmt->bindParam(':is_paid', $isPaid);
          $stmt->bindParam(':total_amount', $totalAmount);
      } elseif ($is_paid_column_exists && $is_delivered_column_exists) {
          $stmt = $conn->prepare("INSERT INTO orders (customer_name, is_paid, is_delivered, total_amount) VALUES (:customer_name, :is_paid, :is_delivered, :total_amount)");
          $stmt->bindParam(':customer_name', $customerName);
          $stmt->bindParam(':is_paid', $isPaid);
          $stmt->bindParam(':is_delivered', $isDelivered);
          $stmt->bindParam(':total_amount', $totalAmount);
      } elseif ($is_paid_column_exists) {
          $stmt = $conn->prepare("INSERT INTO orders (customer_name, is_paid, total_amount) VALUES (:customer_name, :is_paid, :total_amount)");
          $stmt->bindParam(':customer_name', $customerName);
          $stmt->bindParam(':is_paid', $isPaid);
          $stmt->bindParam(':total_amount', $totalAmount);
      } else {
          $stmt = $conn->prepare("INSERT INTO orders (customer_name, total_amount) VALUES (:customer_name, :total_amount)");
          $stmt->bindParam(':customer_name', $customerName);
          $stmt->bindParam(':total_amount', $totalAmount);
      }
      $stmt->execute();
      
      $orderId = $conn->lastInsertId();
      
      // Check if variables column exists in order_items table
      $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'variables'");
      $stmt->execute();
      $variables_column_exists = $stmt->rowCount() > 0;
      
      // Add variables column if it doesn't exist
      if (!$variables_column_exists) {
          $conn->exec("ALTER TABLE order_items ADD COLUMN variables TEXT NULL");
      }
      
      // Check if status column exists in order_items table
      $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'status'");
      $stmt->execute();
      $status_column_exists = $stmt->rowCount() > 0;
      
      // Check if is_delivered column exists in order_items table
      $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'is_delivered'");
      $stmt->execute();
      $is_delivered_item_column_exists = $stmt->rowCount() > 0;
      
      // Add is_delivered column if it doesn't exist
      if (!$is_delivered_item_column_exists) {
          $conn->exec("ALTER TABLE order_items ADD COLUMN is_delivered TINYINT(1) DEFAULT 0");
      }
      
      // Insert order items
      if ($status_column_exists && $variables_column_exists && $is_delivered_item_column_exists) {
          $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, status, variables, is_delivered) VALUES (:order_id, :product_id, :quantity, :price, :subtotal, :status, :variables, :is_delivered)");
      } elseif ($status_column_exists && $variables_column_exists) {
          $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, status, variables) VALUES (:order_id, :product_id, :quantity, :price, :subtotal, :status, :variables)");
      } elseif ($status_column_exists && $is_delivered_item_column_exists) {
          $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, status, is_delivered) VALUES (:order_id, :product_id, :quantity, :price, :subtotal, :status, :is_delivered)");
      } elseif ($variables_column_exists && $is_delivered_item_column_exists) {
          $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, variables, is_delivered) VALUES (:order_id, :product_id, :quantity, :price, :subtotal, :variables, :is_delivered)");
      } elseif ($status_column_exists) {
          $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, status) VALUES (:order_id, :product_id, :quantity, :price, :subtotal, :status)");
      } elseif ($variables_column_exists) {
          $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, variables) VALUES (:order_id, :product_id, :quantity, :price, :subtotal, :variables)");
      } elseif ($is_delivered_item_column_exists) {
          $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, is_delivered) VALUES (:order_id, :product_id, :quantity, :price, :subtotal, :is_delivered)");
      } else {
          $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (:order_id, :product_id, :quantity, :price, :subtotal)");
      }

      foreach ($cartItems as $item) {
          $productId = $item['id'];
          $quantity = $item['quantity'];
          $price = $item['price'];
          $subtotal = $price * $quantity;
          $variables = isset($item['variables']) ? $item['variables'] : '';
          
          // Verificar stock antes de insertar
          if ($stock_column_exists) {
              // Verificar si el producto tiene variables
              $stmt2 = $conn->prepare("SHOW COLUMNS FROM products LIKE 'has_variables'");
              $stmt2->execute();
              $has_variables_column_exists = $stmt2->rowCount() > 0;

              if ($has_variables_column_exists) {
                  $stmt2 = $conn->prepare("SELECT has_variables FROM products WHERE id = :product_id");
                  $stmt2->bindParam(':product_id', $productId);
                  $stmt2->execute();
                  $hasVariables = $stmt2->fetchColumn();
              } else {
                  // Si la columna no existe, verificamos si hay variables para este producto
                  $stmt2 = $conn->prepare("SELECT COUNT(*) FROM product_variables WHERE product_id = :product_id");
                  $stmt2->bindParam(':product_id', $productId);
                  $stmt2->execute();
                  $hasVariables = $stmt2->fetchColumn() > 0 ? 1 : 0;
              }
              
              if ($hasVariables && !empty($variables)) {
                  // Verificar stock de la variable específica
                  $variableName = $variables;
                  // Si la variable tiene formato "nombre: especificación", extraer solo el nombre
                  if (strpos($variableName, ':') !== false) {
                      $variableName = trim(substr($variableName, 0, strpos($variableName, ':')));
                  }
                  
                  $stmt2 = $conn->prepare("SELECT stock FROM product_variables WHERE product_id = :product_id AND name = :variable_name");
                  $stmt2->bindParam(':product_id', $productId);
                  $stmt2->bindParam(':variable_name', $variableName);
                  $stmt2->execute();
                  $currentStock = $stmt2->fetchColumn();
                  
                  if ($currentStock < $quantity) {
                      throw new Exception("No hay suficiente stock para '{$item['name']} - {$variableName}'. Stock actual: {$currentStock}, Cantidad solicitada: {$quantity}");
                  }
              } else {
                  // Verificar stock del producto general
                  $stmt2 = $conn->prepare("SELECT stock FROM products WHERE id = :product_id");
                  $stmt2->bindParam(':product_id', $productId);
                  $stmt2->execute();
                  $currentStock = $stmt2->fetchColumn();
                  
                  if ($currentStock < $quantity) {
                      throw new Exception("No hay suficiente stock para '{$item['name']}'. Stock actual: {$currentStock}, Cantidad solicitada: {$quantity}");
                  }
              }
          }
          
          $stmt->bindParam(':order_id', $orderId);
          $stmt->bindParam(':product_id', $productId);
          $stmt->bindParam(':quantity', $quantity);
          $stmt->bindParam(':price', $price);
          $stmt->bindParam(':subtotal', $subtotal);
          
          if ($status_column_exists) {
              $status = 'no pagado'; // Default status
              $stmt->bindParam(':status', $status);
          }
          
          if ($variables_column_exists) {
              $stmt->bindParam(':variables', $variables);
          }
          
          if ($is_delivered_item_column_exists) {
              $isDeliveredItem = 0; // Default not delivered
              $stmt->bindParam(':is_delivered', $isDeliveredItem);
          }
          
          $stmt->execute();
          
          // Actualizar stock
          if ($stock_column_exists) {
              // Verificar si el producto tiene variables
              $stmt2 = $conn->prepare("SHOW COLUMNS FROM products LIKE 'has_variables'");
              $stmt2->execute();
              $has_variables_column_exists = $stmt2->rowCount() > 0;

              if ($has_variables_column_exists) {
                  $stmt2 = $conn->prepare("SELECT has_variables FROM products WHERE id = :product_id");
                  $stmt2->bindParam(':product_id', $productId);
                  $stmt2->execute();
                  $hasVariables = $stmt2->fetchColumn();
              } else {
                  // Si la columna no existe, verificamos si hay variables para este producto
                  $stmt2 = $conn->prepare("SELECT COUNT(*) FROM product_variables WHERE product_id = :product_id");
                  $stmt2->bindParam(':product_id', $productId);
                  $stmt2->execute();
                  $hasVariables = $stmt2->fetchColumn() > 0 ? 1 : 0;
              }

              if ($hasVariables && !empty($variables)) {
                  // Actualizar stock de la variable específica
                  $variableName = $variables;
                  // Si la variable tiene formato "nombre: especificación", extraer solo el nombre
                  if (strpos($variableName, ':') !== false) {
                      $variableName = trim(substr($variableName, 0, strpos($variableName, ':')));
                  }
                  
                  $stmt2 = $conn->prepare("UPDATE product_variables SET stock = stock - :quantity 
                                       WHERE product_id = :product_id AND name = :variable_name");
                  $stmt2->bindParam(':quantity', $quantity);
                  $stmt2->bindParam(':product_id', $productId);
                  $stmt2->bindParam(':variable_name', $variableName);
                  $stmt2->execute();
              } else {
                  // Actualizar stock del producto general
                  $stmt2 = $conn->prepare("UPDATE products SET stock = stock - :quantity WHERE id = :product_id");
                  $stmt2->bindParam(':quantity', $quantity);
                  $stmt2->bindParam(':product_id', $productId);
                  $stmt2->execute();
              }
          }
      }
      
      // Commit transaction
      $conn->commit();
      
      // Clear cart
      $_SESSION['cart'] = [];
      
      // Set success message
      $_SESSION['success'] = "Pedido #$orderId guardado correctamente.";
      
      // Redirect to view order
      header("Location: view_order.php?id=$orderId");
      exit;
      
  } catch (PDOException $e) {
      // Rollback transaction on error
      $conn->rollBack();
      $_SESSION['error'] = "Error al guardar el pedido: " . $e->getMessage();
      header('Location: index.php');
      exit;
  } catch (Exception $e) {
      // Rollback transaction on error
      $conn->rollBack();
      $_SESSION['error'] = "Error al guardar el pedido: " . $e->getMessage();
      header('Location: index.php');
      exit;
  }
} else {
  // Not a POST request
  header('Location: index.php');
  exit;
}
?>

