<?php
require_once 'config.php';
session_start();

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
  $_SESSION['error'] = "ID de pedido no proporcionado.";
  header('Location: view_orders.php');
  exit;
}

$orderId = $_GET['id'];
$page_title = "Editar Pedido #$orderId - Sistema de Pedidos";

try {
  // Get order details
  $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id");
  $stmt->bindParam(':id', $orderId);
  $stmt->execute();
  $order = $stmt->fetch();

  // Check if order exists
  if (!$order) {
      $_SESSION['error'] = "El pedido no existe.";
      header('Location: view_orders.php');
      exit;
  }

  // Get order items with product names in a single query
  $stmt = $conn->prepare("
      SELECT oi.*, p.name as product_name 
      FROM order_items oi
      JOIN products p ON oi.product_id = p.id
      WHERE oi.order_id = :order_id
  ");
  $stmt->bindParam(':order_id', $orderId);
  $stmt->execute();
  $orderItems = $stmt->fetchAll();

  // Get all products
  $stmt = $conn->prepare("SELECT * FROM products ORDER BY name");
  $stmt->execute();
  $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Check database structure in a single query
  $stmt = $conn->prepare("
      SELECT 
          (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
           WHERE TABLE_NAME = 'orders' AND COLUMN_NAME = 'is_paid') as is_paid_exists,
          (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
           WHERE TABLE_NAME = 'orders' AND COLUMN_NAME = 'is_delivered') as is_delivered_exists,
          (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
           WHERE TABLE_NAME = 'product_variables') as product_variables_exists,
          (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
           WHERE TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_method') as payment_method_exists
  ");
  $stmt->execute();
  $dbStructure = $stmt->fetch();
  
  $is_paid_column_exists = $dbStructure['is_paid_exists'] > 0;
  $is_delivered_column_exists = $dbStructure['is_delivered_exists'] > 0;
  $product_variables_table_exists = $dbStructure['product_variables_exists'] > 0;
  $payment_method_column_exists = $dbStructure['payment_method_exists'] > 0;

  // Get product variables if table exists
  $productVariables = [];
  if ($product_variables_table_exists > 0) {
      $stmt = $conn->prepare("SELECT * FROM product_variables ORDER BY product_id, name");
      $stmt->execute();
      $variables = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      foreach ($variables as $variable) {
          if (!isset($productVariables[$variable['product_id']])) {
              $productVariables[$variable['product_id']] = [];
          }
          $productVariables[$variable['product_id']][] = $variable;
      }
  }
  
  // Actualizar estados antiguos a nuevos estados
  foreach ($orderItems as $item) {
      if (isset($item['status']) && ($item['status'] == 'completado' || $item['status'] == 'pendiente')) {
          $newStatus = ($item['status'] == 'completado') ? 'pagado' : 'no pagado';
          $stmt = $conn->prepare("UPDATE order_items SET status = :status WHERE id = :id");
          $stmt->bindParam(':status', $newStatus);
          $stmt->bindParam(':id', $item['id']);
          $stmt->execute();
      }
  }
  
  // Volver a obtener los items con los estados actualizados
  $stmt = $conn->prepare("
      SELECT oi.*, p.name as product_name 
      FROM order_items oi
      JOIN products p ON oi.product_id = p.id
      WHERE oi.order_id = :order_id
  ");
  $stmt->bindParam(':order_id', $orderId);
  $stmt->execute();
  $orderItems = $stmt->fetchAll();
  
} catch (PDOException $e) {
  $_SESSION['error'] = "Error al cargar los datos: " . $e->getMessage();
  header('Location: view_orders.php');
  exit;
}

// Get success or error messages
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);

// Incluir el encabezado
include 'template_header.php';
?>

<div class="page-header">
  <h2>Editar Pedido #<?= $orderId ?></h2>
  <div class="back-link">
      <a href="view_order.php?id=<?= $orderId ?>" class="btn-secondary">
          <i class="fas fa-arrow-left"></i> Volver al Pedido
      </a>
  </div>
</div>

<form action="update_order.php" method="post" id="edit-order-form">
  <input type="hidden" name="order_id" value="<?= $orderId ?>">
  
  <div class="card">
      <h3><i class="fas fa-user"></i> Información del Cliente</h3>
      <div class="form-group">
          <label for="customer_name">Nombre del Cliente:</label>
          <input type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars($order['customer_name']) ?>" required>
      </div>
      
      <div class="form-group">
          <label for="phone">Celular (opcional):</label>
          <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($order['phone']) ?>">
      </div>
      
      <div class="form-group">
          <label for="is_paid">Estado de Pago:</label>
          <select id="is_paid" name="is_paid">
              <option value="0" <?= (!$is_paid_column_exists || !$order['is_paid']) ? 'selected' : '' ?>>Pendiente</option>
              <option value="1" <?= ($is_paid_column_exists && $order['is_paid']) ? 'selected' : '' ?>>Pagado</option>
          </select>
      </div>
      
      <div class="form-group">
          <label for="is_delivered">Estado de Entrega:</label>
          <select id="is_delivered" name="is_delivered">
              <option value="0" <?= (!$is_delivered_column_exists || !$order['is_delivered']) ? 'selected' : '' ?>>Pendiente</option>
              <option value="1" <?= ($is_delivered_column_exists && $order['is_delivered']) ? 'selected' : '' ?>>Entregado</option>
          </select>
      </div>
      <?php if ($payment_method_column_exists): ?>
      <div class="form-group">
          <label for="payment_method">Método de Pago:</label>
          <select id="payment_method" name="payment_method">
              <option value="Fisico" <?= (!isset($order['payment_method']) || $order['payment_method'] == 'Fisico') ? 'selected' : '' ?>>Físico</option>
              <option value="Transferencia" <?= (isset($order['payment_method']) && $order['payment_method'] == 'Transferencia') ? 'selected' : '' ?>>Transferencia</option>
          </select>
      </div>
      <?php endif; ?>
  </div>
  
  <div class="card current-items">
      <h3><i class="fas fa-shopping-cart"></i> Productos Actuales en el Pedido</h3>
      <div class="table-responsive">
          <table>
              <thead>
                  <tr>
                      <th>Producto</th>
                      <th>Cantidad</th>
                      <th>Precio</th>
                      <th>Subtotal</th>
                      <th>Estado</th>
                      <th>Acción</th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($orderItems as $item): ?>
                      <tr>
                          <td data-label="Producto">
                              <?= $item['product_name'] ?>
                              <?php if (isset($item['variables']) && !empty($item['variables'])): ?>
                                  <div class="cart-variables"><?= $item['variables'] ?></div>
                              <?php endif; ?>
                          </td>
                          <td data-label="Cantidad"><?= $item['quantity'] ?></td>
                          <td data-label="Precio">S/. <?= number_format($item['price'], 2) ?></td>
                          <td data-label="Subtotal">S/. <?= number_format($item['subtotal'], 2) ?></td>
                          <td data-label="Estado"><?= isset($item['status']) ? ($item['status'] === 'pagado' ? 'Pagado' : 'No Pagado') : 'No Pagado' ?></td>
                          <td data-label="Acción">
                              <button type="button" class="btn-remove-item btn-small btn-danger" data-id="<?= $item['id'] ?>">
                                  <i class="fas fa-trash"></i> Eliminar
                              </button>
                          </td>
                      </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
      </div>
      
      <div id="removed-items-container"></div>
  </div>
  
  <div class="card">
      <h3><i class="fas fa-plus-circle"></i> Agregar Nuevos Productos</h3>
      
      <?php if (count($products) > 0): ?>
      <div class="products-container">
          <div class="table-responsive">
              <table class="products-table">
                  <thead>
                      <tr>
                          <th>Producto</th>
                          <th>Precio</th>
                          <th>Cantidad</th>
                          <th>Variable</th>
                          <th>Especificaciones</th>
                          <th>Acción</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php foreach ($products as $product): ?>
                      <tr>
                          <td data-label="Producto"><?= $product['name'] ?></td>
                          <td data-label="Precio" class="product-price">S/. <?= number_format($product['price'], 2) ?></td>
                          <td data-label="Cantidad">
                              <input type="number" class="product-quantity" min="1" value="1">
                          </td>
                          <td data-label="Variable">
                              <select class="product-variable-select">
                                  <option value="">-- Seleccionar --</option>
                                  <?php if (isset($productVariables[$product['id']])): ?>
                                      <?php foreach ($productVariables[$product['id']] as $variable): ?>
                                          <option value="<?= htmlspecialchars($variable['name']) ?>"><?= htmlspecialchars($variable['name']) ?></option>
                                      <?php endforeach; ?>
                                  <?php endif; ?>
                              </select>
                          </td>
                          <td data-label="Especificaciones">
                              <input type="text" class="product-specs" placeholder="Especificaciones adicionales">
                          </td>
                          <td data-label="Acción">
                              <button type="button" class="btn-add-product" 
                                      data-id="<?= $product['id'] ?>" 
                                      data-name="<?= htmlspecialchars($product['name']) ?>" 
                                      data-price="<?= $product['price'] ?>">
                                  <i class="fas fa-plus"></i> Agregar
                              </button>
                          </td>
                      </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
      </div>
      <?php else: ?>
      <div class="alert error">
          No hay productos disponibles. Por favor, <a href="manage_products.php">agregue algunos productos</a>.
      </div>
      <?php endif; ?>
  </div>
  
  <div class="card cart">
      <h3><i class="fas fa-shopping-basket"></i> Nuevos Productos a Agregar</h3>
      <div class="table-responsive">
          <table id="cart-items">
              <thead>
                  <tr>
                      <th>Producto</th>
                      <th>Cantidad</th>
                      <th>Precio</th>
                      <th>Subtotal</th>
                      <th>Acción</th>
                  </tr>
              </thead>
              <tbody>
                  <!-- Cart items will be added here dynamically -->
              </tbody>
              <tfoot>
                  <tr>
                      <td colspan="3" data-label="Total">Total Nuevos Productos:</td>
                      <td id="cart-total" data-label="Monto">S/. 0.00</td>
                      <td></td>
                  </tr>
              </tfoot>
          </table>
      </div>
      <input type="hidden" name="new_items" id="cart-items-input">
      <input type="hidden" name="removed_items" id="removed-items-input">
  </div>
  
  <div class="form-actions">
      <button type="submit" class="btn-primary">
          <i class="fas fa-save"></i> Guardar Cambios
      </button>
      <a href="view_order.php?id=<?= $orderId ?>" class="btn-secondary">
          <i class="fas fa-times"></i> Cancelar
      </a>
  </div>
</form>

<?php
$extra_js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    let cart = [];
    let removedItems = [];
    const cartItemsTable = document.getElementById('cart-items').getElementsByTagName('tbody')[0];
    const cartTotalElement = document.getElementById('cart-total');
    const cartItemsInput = document.getElementById('cart-items-input');
    const removedItemsInput = document.getElementById('removed-items-input');
    const removedItemsContainer = document.getElementById('removed-items-container');
    
    // Add event listeners to all "Agregar" buttons
    document.querySelectorAll('.btn-add-product').forEach(button => {
        button.addEventListener('click', addProductToCart);
    });
    
    // Add event listeners to all "Eliminar" buttons for existing items
    document.querySelectorAll('.btn-remove-item').forEach(button => {
        button.addEventListener('click', removeExistingItem);
    });
    
    
    function addProductToCart() {
        const row = this.closest('tr');
        const quantityInput = row.querySelector('.product-quantity');
        const variableSelect = row.querySelector('.product-variable-select');
        const specsInput = row.querySelector('.product-specs');
        
        const productId = this.dataset.id;
        const productName = this.dataset.name;
        const productPrice = parseFloat(this.dataset.price);
        const quantity = parseInt(quantityInput.value);
        const selectedVariable = variableSelect.value;
        const specs = specsInput.value.trim();
        
        // Combine variable and specs
        let variables = '';
        if (selectedVariable) {
            variables = selectedVariable;
            if (specs) {
                variables += ': ' + specs;
            }
        } else if (specs) {
            variables = specs;
        }
        
        if (isNaN(quantity) || quantity < 1) {
            alert('Por favor ingrese una cantidad válida');
            return;
        }
        
        // Verificar stock disponible (solo si hay opciones disponibles)
        if (selectedVariable && variableSelect.options[variableSelect.selectedIndex].disabled) {
            alert('No hay stock disponible para esta variable');
            return;
        }
        
        // Generate a unique key for the cart item that includes variables
        const itemKey = productId + '-' + (variables ? variables.replace(/\\s+/g, '_') : '');
        
        // Check if product with same variables already in cart
        const existingItemIndex = cart.findIndex(item => 
            item.id === productId && item.variables === variables
        );
        
        if (existingItemIndex > -1) {
            // Update quantity
            cart[existingItemIndex].quantity += quantity;
        } else {
            // Add new item
            cart.push({
                id: productId,
                name: productName,
                price: productPrice,
                quantity: quantity,
                variables: variables,
                itemKey: itemKey
            });
        }
        
        updateCartDisplay();
        
        // Reset inputs
        quantityInput.value = 1;
        variableSelect.selectedIndex = 0;
        specsInput.value = '';
    }
    
    function removeExistingItem() {
        const itemId = this.dataset.id;
        removedItems.push(itemId);
        
        // Create hidden input for removed item
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'removed_items[]';
        hiddenInput.value = itemId;
        removedItemsContainer.appendChild(hiddenInput);
        
        // Hide the row
        this.closest('tr').style.display = 'none';
        
        // Update hidden input
        removedItemsInput.value = JSON.stringify(removedItems);
    }
    
    function updateCartDisplay() {
    // Clear current items
    cartItemsTable.innerHTML = '';
    
    let total = 0;
    
    // Add each item
    cart.forEach((cartItem, index) => {
        const subtotal = cartItem.price * cartItem.quantity;
        total += subtotal;
        
        const row = cartItemsTable.insertRow();
        
        // Create product name cell with variables if they exist
        const nameCell = row.insertCell();
        nameCell.setAttribute('data-label', 'Producto');
        nameCell.innerHTML = cartItem.name;
        
        if (cartItem.variables) {
            const variablesDiv = document.createElement('div');
            variablesDiv.className = 'cart-variables';
            variablesDiv.textContent = cartItem.variables;
            nameCell.appendChild(variablesDiv);
        }
        
        // Add other cells
        const quantityCell = row.insertCell();
        quantityCell.setAttribute('data-label', 'Cantidad');
        quantityCell.textContent = cartItem.quantity;
        
        const priceCell = row.insertCell();
        priceCell.setAttribute('data-label', 'Precio');
        priceCell.textContent = `$ ${cartItem.price.toFixed(0)}`;
        
        const subtotalCell = row.insertCell();
        subtotalCell.setAttribute('data-label', 'Subtotal');
        subtotalCell.textContent = `$ ${subtotal.toFixed(0)}`;
        
        // Add remove button
        const actionCell = row.insertCell();
        actionCell.setAttribute('data-label', 'Acción');
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn-remove btn-small btn-danger';
        removeButton.dataset.index = index;
        removeButton.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
        actionCell.appendChild(removeButton);
    });
    
    // Update total
    cartTotalElement.textContent = `$ ${total.toFixed(0)}`;
    
    // Update hidden input for form submission
    cartItemsInput.value = JSON.stringify(cart);
    
    // Add event listeners to remove buttons
    document.querySelectorAll('.btn-remove').forEach(button => {
        button.addEventListener('click', function() {
            const index = parseInt(this.dataset.index);
            cart.splice(index, 1);
            updateCartDisplay();
        });
    });
}
});
JS;

include 'template_footer.php';
?>

