<?php
require_once 'config.php';
session_start();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if file was uploaded without errors
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file_name = $_FILES['csv_file']['name'];
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check if file is CSV
        if ($file_ext === 'csv') {
            // Open uploaded CSV file with read-only mode
            $csvFile = fopen($file_tmp, 'r');
            
            // Skip first line (header)
            fgetcsv($csvFile);
            
            // Count successful and failed imports
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Prepare statement for inserting products
                $stmt = $conn->prepare("INSERT INTO products (name, price) VALUES (:name, :price)");
                
                // Parse data from CSV file line by line
                while (($getData = fgetcsv($csvFile, 10000, ",")) !== FALSE) {
                    // Check if we have at least 2 columns (name and price)
                    if (count($getData) >= 2) {
                        $name = trim($getData[0]);
                        $price = floatval(str_replace(',', '.', $getData[1]));
                        
                        // Validate data
                        if (empty($name)) {
                            $errorCount++;
                            $errors[] = "Fila con precio $price: El nombre está vacío";
                            continue;
                        }
                        
                        if ($price <= 0) {
                            $errorCount++;
                            $errors[] = "Producto '$name': El precio debe ser mayor que cero";
                            continue;
                        }
                        
                        // Insert product
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':price', $price);
                        $stmt->execute();
                        $successCount++;
                    } else {
                        $errorCount++;
                        $errors[] = "Fila inválida: " . implode(', ', $getData);
                    }
                }
                
                // Close opened CSV file
                fclose($csvFile);
                
                // Commit transaction
                $conn->commit();
                
                // Set success message
                $_SESSION['success'] = "Importación completada. $successCount productos importados correctamente.";
                
                if ($errorCount > 0) {
                    $_SESSION['error'] = "$errorCount productos no pudieron ser importados. Detalles: " . implode('; ', $errors);
                }
                
                header('Location: manage_products.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $_SESSION['error'] = "Error al importar productos: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Por favor suba un archivo CSV válido.";
        }
    } else {
        $_SESSION['error'] = "Error al subir el archivo. Código: " . $_FILES['csv_file']['error'];
    }
}

// Get error message if any
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Productos - Sistema de Pedidos</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Sistema de Pedidos</h1>
            <nav>
                <a href="index.php">Nuevo Pedido</a>
                <a href="view_orders.php">Ver Pedidos</a>
                <a href="manage_products.php">Gestionar Productos</a>
            </nav>
        </header>

        <main>
            <?php if (!empty($errorMessage)): ?>
                <div class="alert error">
                    <?= $errorMessage ?>
                </div>
            <?php endif; ?>
            
            <div class="page-header">
                <h2>Importar Productos desde CSV</h2>
            </div>
            
            <div class="info-box">
                <h3>Instrucciones</h3>
                <p>Suba un archivo CSV con el siguiente formato:</p>
                <ul>
                    <li>La primera línea debe contener los encabezados (será ignorada)</li>
                    <li>Cada línea siguiente debe tener el nombre del producto y el precio</li>
                    <li>Ejemplo: <code>Chicha Morada (1L),10.00</code></li>
                </ul>
                <p>Puede descargar una <a href="download_template.php">plantilla de ejemplo aquí</a>.</p>
            </div>
            
            <form action="import_products.php" method="post" enctype="multipart/form-data" class="form-container">
                <div class="form-group">
                    <label for="csv_file">Archivo CSV:</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Importar Productos</button>
                    <a href="manage_products.php" class="btn-secondary">Cancelar</a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>

