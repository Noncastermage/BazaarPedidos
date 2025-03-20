<?php
require_once 'config.php';
session_start();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate input
  $name = trim($_POST['name']);
  $price = floatval($_POST['price']);
  $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
  
  if (empty($name)) {
      $_SESSION['error'] = "El nombre del producto es obligatorio.";
  } elseif ($price <= 0) {
      $_SESSION['error'] = "El precio debe ser mayor que cero.";
  } elseif ($stock < 0) {
      $_SESSION['error'] = "El stock no puede ser negativo.";
  } else {
      try {
          // Verificar si la columna stock existe
          $stmt = $conn->prepare("SHOW COLUMNS FROM products LIKE 'stock'");
          $stmt->execute();
          $stock_column_exists = $stmt->rowCount() > 0;
          
          if ($stock_column_exists) {
              // Insert new product with stock
              $stmt = $conn->prepare("INSERT INTO products (name, price, stock) VALUES (:name, :price, :stock)");
              $stmt->bindParam(':name', $name);
              $stmt->bindParam(':price', $price);
              $stmt->bindParam(':stock', $stock);
          } else {
              // Insert new product without stock
              $stmt = $conn->prepare("INSERT INTO products (name, price) VALUES (:name, :price)");
              $stmt->bindParam(':name', $name);
              $stmt->bindParam(':price', $price);
              
              // Mostrar mensaje de advertencia
              $_SESSION['warning'] = "La columna 'stock' no existe en la tabla 'products'. Por favor, ejecute el script de actualización de la base de datos.";
          }
          
          $stmt->execute();
          
          $_SESSION['success'] = "Producto agregado correctamente.";
          header('Location: manage_products.php');
          exit;
          
      } catch (PDOException $e) {
          $_SESSION['error'] = "Error al agregar el producto: " . $e->getMessage();
      }
  }
}

// Get error message if any
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);

// Get warning message if any
$warningMessage = isset($_SESSION['warning']) ? $_SESSION['warning'] : '';
unset($_SESSION['warning']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Producto - Sistema de Pedidos</title>
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

            <?php if (!empty($warningMessage)): ?>
                <div class="alert warning">
                    <?= $warningMessage ?>
                </div>
            <?php endif; ?>
            
            <div class="page-header">
                <h2>Agregar Nuevo Producto</h2>
            </div>
            
            <form action="add_product.php" method="post" class="form-container">
                <div class="form-group">
                    <label for="name">Nombre del Producto:</label>
                    <input type="text" id="name" name="name" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="price">Precio (S/.):</label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" required value="<?= isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="stock">Stock:</label>
                    <input type="number" id="stock" name="stock" min="0" value="<?= isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '0' ?>">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Guardar Producto</button>
                    <a href="manage_products.php" class="btn-secondary">Cancelar</a>
                </div>
            </form>
        </main>
    </div>
</body>
</html>

