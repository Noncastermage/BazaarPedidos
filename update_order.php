<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: view_orders.php');
    exit;
}

// Get form data
$orderId = $_POST['order_id'];
$customerName = $_POST['customer_name'];
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$isPaid = isset($_POST['is_paid']) ? $_POST['is_paid'] : 0;
$newItems = isset($_POST['new_items']) ? json_decode($_POST['new_items'], true) : [];
$removedItems = isset($_POST['removed_items']) ? $_POST['removed_items'] : [];

// Process removed_items data
if (!is_array($removedItems) && !empty($removedItems)) {
    $removedItems = json_decode($removedItems, true);
}

if (!is_array($removedItems) && isset($_POST['removed_items']) && is_array($_POST['removed_items'])) {
    $removedItems = $_POST['removed_items'];
}

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Update order details
    $stmt = $conn->prepare("UPDATE orders SET customer_name = :customer_name, phone = :phone, is_paid = :is_paid WHERE id = :id");
    $stmt->bindParam(':customer_name', $customerName);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':is_paid', $isPaid);
    $stmt->bindParam(':id', $orderId);
    $stmt->execute();
    
    // Remove items if any
    if (!empty($removedItems)) {
        $placeholders = implode(',', array_fill(0, count($removedItems), '?'));
        $stmt = $conn->prepare("DELETE FROM order_items WHERE id IN ($placeholders)");
        
        foreach ($removedItems as $index => $itemId) {
            $stmt->bindValue($index + 1, $itemId);
        }
        
        $stmt->execute();
    }
    
    // Add new items if any
    if (!empty($newItems)) {
        // Check database structure in a single query
        $stmt = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = 'order_items' AND COLUMN_NAME = 'variables') as variables_exists,
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = 'order_items' AND COLUMN_NAME = 'status') as status_exists,
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = 'products' AND COLUMN_NAME = 'stock') as stock_exists
        ");
        $stmt->execute();
        $dbStructure = $stmt->fetch();
        
        $variables_column_exists = $dbStructure['variables_exists'] > 0;
        $status_column_exists = $dbStructure['status_exists'] > 0;
        $stock_column_exists = $dbStructure['stock_exists'] > 0;
        
        // Prepare the SQL statement based on available columns
        if ($status_column_exists && $variables_column_exists) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, status, variables) VALUES (:order_id, :product_id, :quantity, :price, :subtotal, :status, :variables)");
        } elseif ($status_column_exists) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, status) VALUES (:order_id, :product_id, :quantity, :price, :subtotal, :status)");
        } elseif ($variables_column_exists) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal, variables) VALUES (:order_id, :product_id, :quantity, :price, :subtotal, :variables)");
        } else {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (:order_id, :product_id, :quantity, :price, :subtotal)");
        }
        
        foreach ($newItems as $item) {
            $productId = $item['id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            $subtotal = $price * $quantity;
            $variables = isset($item['variables']) ? $item['variables'] : '';
            
            // Verificar stock antes de insertar
            if ($stock_column_exists) {
                // Verificar si el producto tiene variables
                $stmt2 = $conn->prepare("SELECT has_variables FROM products WHERE id = :product_id");
                $stmt2->bindParam(':product_id', $productId);
                $stmt2->execute();
                $hasVariables = $stmt2->fetchColumn();
                
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
                $status = 'pendiente'; // Default status
                $stmt->bindParam(':status', $status);
            }
            
            if ($variables_column_exists) {
                $stmt->bindParam(':variables', $variables);
            }
            
            $stmt->execute();
            
            // Actualizar stock
            if ($stock_column_exists) {
                // Verificar si el producto tiene variables
                $stmt2 = $conn->prepare("SELECT has_variables FROM products WHERE id = :product_id");
                $stmt2->bindParam(':product_id', $productId);
                $stmt2->execute();
                $hasVariables = $stmt2->fetchColumn();
                
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
    }
    
    // Update order total
    $stmt = $conn->prepare("
        UPDATE orders 
        SET total_amount = (
            SELECT COALESCE(SUM(subtotal), 0) 
            FROM order_items 
            WHERE order_id = :order_id
        ) 
        WHERE id = :id
    ");
    $stmt->bindParam(':order_id', $orderId);
    $stmt->bindParam(':id', $orderId);
    $stmt->execute();
    
    // Check if all items are completed and update order status if needed
    if ($isPaid == 0) { // Only check if order is not already marked as paid
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM order_items 
            WHERE order_id = :order_id AND status != 'completado'
        ");
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        $pendingItems = $stmt->fetchColumn();
        
        if ($pendingItems == 0) {
            // All items are completed, mark order as paid
            $stmt = $conn->prepare("UPDATE orders SET is_paid = 1 WHERE id = :id");
            $stmt->bindParam(':id', $orderId);
            $stmt->execute();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success'] = "Pedido #$orderId actualizado correctamente.";
    header("Location: view_order.php?id=$orderId");
    exit;
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    $_SESSION['error'] = "Error al actualizar el pedido: " . $e->getMessage();
    header("Location: edit_order.php?id=$orderId");
    exit;
}
?>

