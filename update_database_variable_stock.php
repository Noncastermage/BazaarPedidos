<?php
require_once 'config.php';
session_start();

try {
    echo "<h1>Actualizando la Base de Datos para Stock de Variables</h1>";
    
    // Verificar si la columna stock existe en la tabla products
    $stmt = $conn->prepare("SHOW COLUMNS FROM products LIKE 'stock'");
    $stmt->execute();
    $stock_column_exists = $stmt->rowCount() > 0;
    
    if (!$stock_column_exists) {
        // Añadir la columna stock a la tabla products
        $conn->exec("ALTER TABLE products ADD COLUMN stock INT DEFAULT 0");
        echo "La columna 'stock' ha sido agregada a la tabla 'products'.<br>";
        $_SESSION['success'] = "La columna 'stock' ha sido agregada a la tabla 'products'.";
    } else {
        echo "La columna 'stock' ya existe en la tabla 'products'.<br>";
    }
    
    // Verificar si la columna payment_method existe en la tabla orders
    $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'payment_method'");
    $stmt->execute();
    $payment_method_column_exists = $stmt->rowCount() > 0;
    
    if (!$payment_method_column_exists) {
        // Añadir la columna payment_method a la tabla orders
        $conn->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Fisico'");
        echo "La columna 'payment_method' ha sido agregada a la tabla 'orders'.<br>";
        $_SESSION['success'] .= " La columna 'payment_method' ha sido agregada a la tabla 'orders'.";
    } else {
        // Actualizar los valores existentes para que sean solo Fisico o Transferencia
        $conn->exec("UPDATE orders SET payment_method = 'Fisico' WHERE payment_method IN ('Efectivo', 'Tarjeta', 'Yape', 'Plin', 'Otro')");
        $conn->exec("UPDATE orders SET payment_method = 'Transferencia' WHERE payment_method = 'Transferencia'");
        echo "La columna 'payment_method' ya existe en la tabla 'orders'. Se han actualizado los valores a 'Fisico' o 'Transferencia'.<br>";
    }
    
    // Verificar si la tabla product_variables existe
    $stmt = $conn->prepare("SHOW TABLES LIKE 'product_variables'");
    $stmt->execute();
    $product_variables_table_exists = $stmt->rowCount() > 0;
    
    if (!$product_variables_table_exists) {
        // Crear la tabla product_variables
        $conn->exec("
            CREATE TABLE product_variables (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                stock INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )
        ");
        echo "La tabla 'product_variables' ha sido creada con la columna 'stock'.<br>";
        $_SESSION['success'] .= " La tabla 'product_variables' ha sido creada con la columna 'stock'.";
    } else {
        // Verificar si la columna stock existe en la tabla product_variables
        $stmt = $conn->prepare("SHOW COLUMNS FROM product_variables LIKE 'stock'");
        $stmt->execute();
        $variable_stock_column_exists = $stmt->rowCount() > 0;
        
        if (!$variable_stock_column_exists) {
            // Añadir la columna stock a la tabla product_variables
            $conn->exec("ALTER TABLE product_variables ADD COLUMN stock INT DEFAULT 0");
            echo "La columna 'stock' ha sido agregada a la tabla 'product_variables'.<br>";
            $_SESSION['success'] .= " La columna 'stock' ha sido agregada a la tabla 'product_variables'.";
        } else {
            echo "La columna 'stock' ya existe en la tabla 'product_variables'.<br>";
        }
    }
    
    // Verificar si la columna has_variables existe en la tabla products
    $stmt = $conn->prepare("SHOW COLUMNS FROM products LIKE 'has_variables'");
    $stmt->execute();
    $has_variables_column_exists = $stmt->rowCount() > 0;
    
    if (!$has_variables_column_exists) {
        // Añadir la columna has_variables a la tabla products
        $conn->exec("ALTER TABLE products ADD COLUMN has_variables TINYINT(1) DEFAULT 0");
        echo "La columna 'has_variables' ha sido agregada a la tabla 'products'.<br>";
        $_SESSION['success'] .= " La columna 'has_variables' ha sido agregada a la tabla 'products'.";
    } else {
        echo "La columna 'has_variables' ya existe en la tabla 'products'.<br>";
    }
    
    // Actualizar la columna has_variables para productos existentes
    $conn->exec("
        UPDATE products p
        SET has_variables = 1
        WHERE EXISTS (
            SELECT 1 FROM product_variables pv
            WHERE pv.product_id = p.id
        )
    ");
    echo "La columna 'has_variables' ha sido actualizada para productos existentes.<br>";
    
    echo "Base de datos actualizada correctamente.";
    
} catch (PDOException $e) {
    echo "Error al actualizar la base de datos: " . $e->getMessage();
    $_SESSION['error'] = "Error al actualizar la base de datos: " . $e->getMessage();
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

