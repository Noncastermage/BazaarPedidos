<?php
require_once 'config.php';
session_start();

// Get all products
$stmt = $conn->prepare("SELECT * FROM products ORDER BY name");
$stmt->execute();
$products = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Prepare statement for updating product prices
        $stmt = $conn->prepare("UPDATE products SET price = :price WHERE id = :id");
        
        // Update each product price
        foreach ($_POST['prices'] as $id => $price) {
            $price = floatval(str_replace(',', '.', $price));
            
            // Validate price
            if ($price <= 0) {
                throw new Exception("El precio del producto ID $id debe ser mayor que cero.");
            }
            
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Precios actualizados correctamente.";
        header('Location: manage_products.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error'] = "Error al actualizar precios: " . $e->getMessage();
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
    <title>Actualización Masiva de Precios - Sistema de Pedidos</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .price-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .price-table th, .price-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .price-table input {
            width: 100px;
        }
        
        .price-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        
        .price-adjustment {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f8f8;
            border-radius: 4px;
        }
        
        .adjustment-controls {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .adjustment-controls input {
            width: 80px;
        }
    </style>
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
                <h2>Actualización Masiva de Precios</h2>
            </div>
            
            <div class="price-adjustment">
                <h3>Ajuste Rápido</h3>
                <p>Aplica un porcentaje de aumento o descuento a todos los productos.</p>
                
                <div class="adjustment-controls">
                    <div class="form-group">
                        <label for="adjustment">Porcentaje:</label>
                        <input type="number" id="adjustment" step="0.1" value="0">
                    </div>
                    <button type="button" id="apply-increase" class="btn-primary">Aumentar</button>
                    <button type="button" id="apply-decrease" class="btn-secondary">Disminuir</button>
                </div>
            </div>
            
            <form action="bulk_update_prices.php" method="post">
                <table class="price-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Precio Actual (S/.)</th>
                            <th>Nuevo Precio (S/.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= $product['name'] ?></td>
                                <td><?= number_format($product['price'], 2) ?></td>
                                <td>
                                    <input type="number" name="prices[<?= $product['id'] ?>]" step="0.01" min="0.01" value="<?= $product['price'] ?>" required>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="price-actions">
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                    <a href="manage_products.php" class="btn-secondary">Cancelar</a>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const applyIncreaseBtn = document.getElementById('apply-increase');
            const applyDecreaseBtn = document.getElementById('apply-decrease');
            const adjustmentInput = document.getElementById('adjustment');
            const priceInputs = document.querySelectorAll('input[name^="prices"]');
            
            // Store original prices
            const originalPrices = Array.from(priceInputs).map(input => parseFloat(input.value));
            
            // Apply increase
            applyIncreaseBtn.addEventListener('click', function() {
                const percentage = parseFloat(adjustmentInput.value);
                if (isNaN(percentage)) return;
                
                priceInputs.forEach((input, index) => {
                    const originalPrice = originalPrices[index];
                    const newPrice = originalPrice * (1 + percentage / 100);
                    input.value = newPrice.toFixed(2);
                });
            });
            
            // Apply decrease
            applyDecreaseBtn.addEventListener('click', function() {
                const percentage = parseFloat(adjustmentInput.value);
                if (isNaN(percentage)) return;
                
                priceInputs.forEach((input, index) => {
                    const originalPrice = originalPrices[index];
                    const newPrice = originalPrice * (1 - percentage / 100);
                    input.value = Math.max(0.01, newPrice).toFixed(2);
                });
            });
        });
    </script>
</body>
</html>

