<?php
require_once 'config.php';
session_start();

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
  $_SESSION['error'] = "ID de producto no proporcionado.";
  header('Location: manage_products.php');
  exit;
}

$productId = $_GET['id'];

// Get product details
$stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
$stmt->bindParam(':id', $productId);
$stmt->execute();
$product = $stmt->fetch();

// Check if product exists
if (!$product) {
  $_SESSION['error'] = "El producto no existe.";
  header('Location: manage_products.php');
  exit;
}

// Check if product_variables table exists
$stmt = $conn->prepare("SHOW TABLES LIKE 'product_variables'");
$stmt->execute();
$product_variables_table_exists = $stmt->rowCount() > 0;

// Create product_variables table if it doesn't exist
if (!$product_variables_table_exists) {
    try {
        $conn->exec("
            CREATE TABLE product_variables (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )
        ");
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al crear la tabla de variables de productos: " . $e->getMessage();
    }
}

// Get product variables
$productVariables = [];
try {
    $stmt = $conn->prepare("SELECT * FROM product_variables WHERE product_id = :product_id ORDER BY name");
    $stmt->bindParam(':product_id', $productId);
    $stmt->execute();
    $productVariables = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al obtener las variables del producto: " . $e->getMessage();
}

// Modificar la parte donde se actualiza el producto para manejar el caso donde la columna stock no existe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_product') {
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
              // Update product with stock
              $stmt = $conn->prepare("UPDATE products SET name = :name, price = :price, stock = :stock WHERE id = :id");
              $stmt->bindParam(':name', $name);
              $stmt->bindParam(':price', $price);
              $stmt->bindParam(':stock', $stock);
              $stmt->bindParam(':id', $productId);
          } else {
              // Update product without stock
              $stmt = $conn->prepare("UPDATE products SET name = :name, price = :price WHERE id = :id");
              $stmt->bindParam(':name', $name);
              $stmt->bindParam(':price', $price);
              $stmt->bindParam(':id', $productId);
              
              // Mostrar mensaje de advertencia
              $_SESSION['warning'] = "La columna 'stock' no existe en la tabla 'products'. Por favor, ejecute el script de actualización de la base de datos.";
          }
          
          $stmt->execute();
          
          // Actualizar el campo has_variables
          $stmt = $conn->prepare("SELECT COUNT(*) FROM product_variables WHERE product_id = :product_id");
          $stmt->bindParam(':product_id', $productId);
          $stmt->execute();
          $hasVariables = ($stmt->fetchColumn() > 0) ? 1 : 0;
          
          // Verificar si la columna has_variables existe
          $stmt = $conn->prepare("SHOW COLUMNS FROM products LIKE 'has_variables'");
          $stmt->execute();
          $has_variables_column_exists = $stmt->rowCount() > 0;
          
          if ($has_variables_column_exists) {
              $stmt = $conn->prepare("UPDATE products SET has_variables = :has_variables WHERE id = :id");
              $stmt->bindParam(':has_variables', $hasVariables);
              $stmt->bindParam(':id', $productId);
              $stmt->execute();
          }
          
          $_SESSION['success'] = "Producto actualizado correctamente.";
          header('Location: manage_products.php');
          exit;
          
      } catch (PDOException $e) {
          $_SESSION['error'] = "Error al actualizar el producto: " . $e->getMessage();
      }
  }
}

// Process form submission for adding a variable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_variable') {
  $variableName = trim($_POST['variable_name']);
  $variableStock = isset($_POST['variable_stock']) ? intval($_POST['variable_stock']) : 0;
  
  if (empty($variableName)) {
      $_SESSION['error'] = "El nombre de la variable es obligatorio.";
  } elseif ($variableStock < 0) {
      $_SESSION['error'] = "El stock no puede ser negativo.";
  } else {
      try {
          // Modificar la consulta para verificar si existe la columna stock
          try {
              // Verificar si la columna stock existe en product_variables
              $stmt = $conn->prepare("SHOW COLUMNS FROM product_variables LIKE 'stock'");
              $stmt->execute();
              $stock_column_exists = $stmt->rowCount() > 0;
              
              if ($stock_column_exists) {
                  $stmt = $conn->prepare("INSERT INTO product_variables (product_id, name, stock) VALUES (:product_id, :name, :stock)");
                  $stmt->bindParam(':product_id', $productId);
                  $stmt->bindParam(':name', $variableName);
                  $stmt->bindParam(':stock', $variableStock);
              } else {
                  // Si la columna stock no existe, no la incluimos en la consulta
                  $stmt = $conn->prepare("INSERT INTO product_variables (product_id, name) VALUES (:product_id, :name)");
                  $stmt->bindParam(':product_id', $productId);
                  $stmt->bindParam(':name', $variableName);
              }
              $stmt->execute();
          } catch (PDOException $e) {
              $_SESSION['error'] = "Error al agregar la variable: " . $e->getMessage();
          }
          
          // Actualizar el campo has_variables del producto
          $stmt = $conn->prepare("UPDATE products SET has_variables = 1 WHERE id = :id");
          $stmt->bindParam(':id', $productId);
          $stmt->execute();
          
          $_SESSION['success'] = "Variable agregada correctamente.";
          header("Location: edit_product.php?id=$productId");
          exit;
      } catch (PDOException $e) {
          $_SESSION['error'] = "Error al agregar la variable: " . $e->getMessage();
      }
  }
}

// Process form submission for deleting a variable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_variable') {
    $variableId = $_POST['variable_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM product_variables WHERE id = :id AND product_id = :product_id");
        $stmt->bindParam(':id', $variableId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        
        $_SESSION['success'] = "Variable eliminada correctamente.";
        header("Location: edit_product.php?id=$productId");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al eliminar la variable: " . $e->getMessage();
    }
}

// Process form submission for updating variable stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_variable_stock') {
  $variableId = $_POST['variable_id'];
  $variableStock = isset($_POST['variable_stock']) ? intval($_POST['variable_stock']) : 0;
  
  if ($variableStock < 0) {
      $_SESSION['error'] = "El stock no puede ser negativo.";
  } else {
      try {
          $stmt = $conn->prepare("UPDATE product_variables SET stock = :stock WHERE id = :id AND product_id = :product_id");
          $stmt->bindParam(':stock', $variableStock);
          $stmt->bindParam(':id', $variableId);
          $stmt->bindParam(':product_id', $productId);
          $stmt->execute();
          
          $_SESSION['success'] = "Stock de variable actualizado correctamente.";
          header("Location: edit_product.php?id=$productId");
          exit;
      } catch (PDOException $e) {
          $_SESSION['error'] = "Error al actualizar el stock de la variable: " . $e->getMessage();
      }
  }
}

// Get error message if any
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$warningMessage = isset($_SESSION['warning']) ? $_SESSION['warning'] : '';
unset($_SESSION['error']);
unset($_SESSION['success']);
unset($_SESSION['warning']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Producto - Sistema de Pedidos</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .variables-section {
        margin-top: 30px;
        border-top: 1px solid #eee;
        padding-top: 20px;
    }
    
    .variables-list {
        list-style: none;
        padding: 0;
        margin: 15px 0;
    }
    
    .variable-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .variable-item:last-child {
        border-bottom: none;
    }
    
    .variable-actions {
        display: flex;
        gap: 5px;
    }
    
    .btn-delete-variable {
        background-color: #f44336;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 5px 10px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .btn-delete-variable:hover {
        background-color: #d32f2f;
    }
    
    .add-variable-form {
        margin-top: 15px;
        padding: 15px;
        background-color: #f8f8f8;
        border-radius: 4px;
    }
    
    .form-row {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .form-row input {
        flex: 1;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .form-row button {
        padding: 8px 15px;
    }
  
    .variable-info {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .variable-name {
        font-weight: bold;
    }
    
    .variable-stock {
        font-size: 0.9em;
        color: #666;
    }
    
    .stock-input {
        width: 60px;
        padding: 3px 5px;
        border: 1px solid #ddd;
        border-radius: 3px;
    }
    
    .btn-update-stock {
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 3px;
        padding: 3px 8px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .btn-update-stock:hover {
        background-color: #45a049;
    }
    
    .inline-form {
        display: inline-flex;
        align-items: center;
        gap: 5px;
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
          
          <?php if (!empty($successMessage)): ?>
              <div class="alert success">
                  <?= $successMessage ?>
              </div>
          <?php endif; ?>

          <?php if (!empty($warningMessage)): ?>
              <div class="alert warning">
                  <?= $warningMessage ?>
              </div>
          <?php endif; ?>
          
          <div class="page-header">
              <h2>Editar Producto</h2>
          </div>
          
          <form action="edit_product.php?id=<?= $productId ?>" method="post" class="form-container">
              <input type="hidden" name="action" value="update_product">
              <div class="form-group">
                  <label for="name">Nombre del Producto:</label>
                  <input type="text" id="name" name="name" required value="<?= htmlspecialchars($product['name']) ?>">
              </div>
              
              <div class="form-group">
                  <label for="price">Precio (S/.):</label>
                  <input type="number" id="price" name="price" step="0.01" min="0.01" required value="<?= htmlspecialchars($product['price']) ?>">
              </div>

              <div class="form-group">
                  <label for="stock">Stock:</label>
                  <input type="number" id="stock" name="stock" min="0" value="<?= isset($product['stock']) ? htmlspecialchars($product['stock']) : '0' ?>">
              </div>
              
              <div class="form-actions">
                  <button type="submit" class="btn-primary">Actualizar Producto</button>
                  <a href="manage_products.php" class="btn-secondary">Cancelar</a>
              </div>
          </form>
          
          <div class="variables-section">
              <h3>Variables del Producto</h3>
              <p>Estas variables estarán disponibles para seleccionar cuando se agregue este producto al carrito.</p>
              
              <?php if (count($productVariables) > 0): ?>
                  <ul class="variables-list">
                      <?php foreach ($productVariables as $variable): ?>
                          <li class="variable-item">
                              <div class="variable-info">
                                  <span class="variable-name"><?= htmlspecialchars($variable['name']) ?></span>
                                  <span class="variable-stock">Stock: 
                                      <form action="edit_product.php?id=<?= $productId ?>" method="post" class="inline-form">
                                          <input type="hidden" name="action" value="update_variable_stock">
                                          <input type="hidden" name="variable_id" value="<?= $variable['id'] ?>">
                                          <input type="number" name="variable_stock" value="<?= isset($variable['stock']) ? $variable['stock'] : 0 ?>" min="0" class="stock-input">
                                          <button type="submit" class="btn-update-stock">Actualizar</button>
                                      </form>
                                  </span>
                              </div>
                              <div class="variable-actions">
                                  <form action="edit_product.php?id=<?= $productId ?>" method="post" onsubmit="return confirm('¿Está seguro de eliminar esta variable?')">
                                      <input type="hidden" name="action" value="delete_variable">
                                      <input type="hidden" name="variable_id" value="<?= $variable['id'] ?>">
                                      <button type="submit" class="btn-delete-variable">Eliminar</button>
                                  </form>
                              </div>
                          </li>
                      <?php endforeach; ?>
                  </ul>
              <?php else: ?>
                  <p>No hay variables definidas para este producto.</p>
              <?php endif; ?>
              
              <div class="add-variable-form">
                  <h4>Agregar Nueva Variable</h4>
                  <form action="edit_product.php?id=<?= $productId ?>" method="post">
                      <input type="hidden" name="action" value="add_variable">
                      <div class="form-row">
                          <input type="text" name="variable_name" placeholder="Nombre de la variable (ej: Grande, Con hielo, etc.)" required>
                          <input type="number" name="variable_stock" placeholder="Stock" min="0" value="0" required>
                          <button type="submit" class="btn-primary">Agregar Variable</button>
                      </div>
                  </form>
              </div>
          </div>
      </main>
  </div>
</body>
</html>

