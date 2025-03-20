<?php
require_once 'config.php';

try {
    // Check if status column exists in order_items table
    $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'status'");
    $stmt->execute();
    $column_exists = $stmt->rowCount() > 0;
    
    // Add status column if it doesn't exist
    if (!$column_exists) {
        $conn->exec("ALTER TABLE order_items ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pendiente'");
        echo "La columna 'status' ha sido agregada a la tabla 'order_items'.<br>";
    } else {
        echo "La columna 'status' ya existe en la tabla 'order_items'.<br>";
    }
    
    echo "Base de datos actualizada correctamente.";
    
} catch (PDOException $e) {
    echo "Error al actualizar la base de datos: " . $e->getMessage();
}
?>

<p><a href="index.php">Volver al sistema</a></p>

