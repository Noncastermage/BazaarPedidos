<?php
require_once 'config.php';
session_start();

// Get all products
$stmt = $conn->prepare("SELECT * FROM products ORDER BY name");
$stmt->execute();
$products = $stmt->fetchAll();

// Get success or error messages
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Sistema de Pedidos</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .product-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .product-card h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .product-price {
            font-size: 18px;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-edit {
            background-color: #2196F3;
            color: white;
        }
        
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        
        .add-product-card {
            border: 2px dashed #ddd;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 150px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .add-product-card:hover {
            border-color: #4CAF50;
            background-color: #f9f9f9;
        }
        
        .add-product-card i {
            font-size: 48px;
            color: #4CAF50;
            margin-bottom: 10px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
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
                <a href="manage_products.php" class="active">Gestionar Productos</a>
            </nav>
        </header>

        <main>
            <?php if (!empty($successMessage)): ?>
                <div class="alert success">
                    <?= $successMessage ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert error">
                    <?= $errorMessage ?>
                </div>
            <?php endif; ?>
            
            <div class="page-header">
                <h2>Gestión de Productos</h2>
                <div class="action-buttons">
                    <a href="add_product.php" class="btn-primary">Agregar Nuevo Producto</a>
                    <a href="import_products.php" class="btn-secondary">Importar CSV</a>
                    <a href="export_products.php" class="btn-secondary">Exportar CSV</a>
                    <a href="bulk_update_prices.php" class="btn-secondary">Actualizar Precios</a>
                </div>
            </div>
            
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <h3><?= $product['name'] ?></h3>
                        <div class="product-price">S/. <?= number_format($product['price'], 2) ?></div>
                        <div class="product-date">Creado: <?= date('d/m/Y', strtotime($product['created_at'])) ?></div>
                        <div class="product-actions">
                            <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn-small btn-edit">Editar</a>
                            <a href="delete_product.php?id=<?= $product['id'] ?>" class="btn-small btn-delete" onclick="return confirm('¿Está seguro de eliminar este producto?')">Eliminar</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <a href="add_product.php" class="product-card add-product-card">
                    <div class="add-icon">+</div>
                    <div>Agregar Nuevo Producto</div>
                </a>
            </div>
            
        </main>
    </div>
</body>
</html>

