<?php
require_once 'config.php';
session_start();

try {
    // Verificar si la columna is_delivered existe en la tabla orders
    $stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_delivered'");
    $stmt->execute();
    $is_delivered_exists = $stmt->rowCount() > 0;
    
    if (!$is_delivered_exists) {
        // Añadir la columna is_delivered a la tabla orders
        $conn->exec("ALTER TABLE orders ADD COLUMN is_delivered TINYINT(1) DEFAULT 0");
        echo "La columna 'is_delivered' ha sido agregada a la tabla 'orders'.<br>";
        $_SESSION['success'] = "La columna 'is_delivered' ha sido agregada a la tabla 'orders'.";
    } else {
        echo "La columna 'is_delivered' ya existe en la tabla 'orders'.<br>";
    }
    
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

