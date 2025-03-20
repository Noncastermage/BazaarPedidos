<?php
require_once 'config.php';
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
  $_SESSION['cart'] = [];
}

// Get products from database
$stmt = $conn->prepare("SELECT * FROM products ORDER BY name");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if products exist
if (count($products) == 0) {
  $_SESSION['error'] = "No hay productos en la base de datos. Por favor, agregue algunos productos o ejecute el <a href='fix_database.php'>script de reparación de la base de datos</a>.";
}

// Check if stock column exists in products table
$stmt = $conn->prepare("SHOW COLUMNS FROM products LIKE 'stock'");
$stmt->execute();
$stock_column_exists = $stmt->rowCount() > 0;

if (!$stock_column_exists) {
  $_SESSION['error'] = "La columna 'stock' no existe en la tabla 'products'. Por favor, ejecute el <a href='update_database_stock.php'>script de actualización de la base de datos</a>.";
}

// Get existing orders
$stmt = $conn->prepare("SELECT * FROM orders ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if is_paid column exists in orders table
$stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_paid'");
$stmt->execute();
$is_paid_column_exists = $stmt->rowCount() > 0;

// Check if is_delivered column exists in orders table
$stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'is_delivered'");
$stmt->execute();
$is_delivered_column_exists = $stmt->rowCount() > 0;

// Check if payment_method column exists in orders table
$stmt = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'payment_method'");
$stmt->execute();
$payment_method_column_exists = $stmt->rowCount() > 0;

// Check if product_variables table exists
$stmt = $conn->prepare("SHOW TABLES LIKE 'product_variables'");
$stmt->execute();
$product_variables_table_exists = $stmt->rowCount() > 0;

// Verificar si la columna has_variables existe en la tabla products
$stmt = $conn->prepare("SHOW COLUMNS FROM products LIKE 'has_variables'");
$stmt->execute();
$has_variables_column_exists = $stmt->rowCount() > 0;

// Si la columna no existe, verificar qué productos tienen variables
$productsWithVariables = [];
if (!$has_variables_column_exists && $product_variables_table_exists) {
    $stmt = $conn->prepare("SELECT DISTINCT product_id FROM product_variables");
    $stmt->execute();
    $productsWithVariables = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get product variables
$productVariables = [];
if ($product_variables_table_exists) {
  try {
      $stmt = $conn->prepare("SELECT * FROM product_variables ORDER BY product_id, name");
      $stmt->execute();
      $variables = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      foreach ($variables as $variable) {
          if (!isset($productVariables[$variable['product_id']])) {
              $productVariables[$variable['product_id']] = [];
          }
          $productVariables[$variable['product_id']][] = $variable;
      }
  } catch (PDOException $e) {
      $_SESSION['error'] = "Error al obtener las variables de productos: " . $e->getMessage();
  }
}

// Get success or error messages
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);

$page_title = "Nuevo Pedido - Sistema de Pedidos";

// Incluir el encabezado
include 'template_header.php';
?>

<div class="page-header">
  <h2><i class="fas fa-plus-circle"></i> Nuevo Pedido</h2>
</div>

<form action="process_order.php" method="post">
  <div class="card">
      <h3><i class="fas fa-user"></i> Información del Cliente</h3>
      <div class="form-group">
          <label for="customer_name">Nombre del Cliente:</label>
          <input type="text" id="customer_name" name="customer_name" required>
      </div>
      
      <?php if ($payment_method_column_exists): ?>
      <div class="form-group">
          <label for="payment_method">Método de Pago:</label>
          <select id="payment_method" name="payment_method">
              <option value="Fisico">Físico</option>
              <option value="Transferencia">Transferencia</option>
          </select>
      </div>
      <?php endif; ?>
      
      <!-- Los estados de pago y entrega se establecen automáticamente como pendientes -->
      <input type="hidden" name="is_paid" value="0">
      <input type="hidden" name="is_delivered" value="0">
  </div>
  
  <div class="card">
      <h3><i class="fas fa-shopping-cart"></i> Seleccionar Productos</h3>
    
    <?php if (count($products) > 0): ?>
    <div class="search-container">
        <input type="text" id="product-search" placeholder="Buscar productos..." class="search-input">
    </div>
    <div class="products-container">
        <div class="table-responsive">
            <table class="products-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Precio</th>
                    <?php if ($stock_column_exists): ?>
                    <th>Stock</th>
                    <?php endif; ?>
                    <th>Cantidad</th>
                    <th>Variable</th>
                    <th>Especificaciones</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody id="products-list">
                <?php foreach ($products as $product): ?>
                <tr class="product-row">
                    <td data-label="Producto"><?= $product['name'] ?></td>
                    <td data-label="Precio" class="product-price">$ <?= number_format($product['price'], 0, ',', '.') ?></td>
                    <?php if ($stock_column_exists): ?>
                    <td data-label="Stock" class="product-stock">
                        <?php 
                        $hasVariables = false;
                        if ($has_variables_column_exists && isset($product['has_variables'])) {
                            $hasVariables = $product['has_variables'];
                        } elseif (in_array($product['id'], $productsWithVariables ?? [])) {
                            $hasVariables = true;
                        }
                        ?>
                        <?php if ($hasVariables): ?>
                            <span class="stock-by-variable">Por variable</span>
                        <?php else: ?>
                            <span class="<?= isset($product['stock']) && $product['stock'] > 0 ? 'stock-available' : 'stock-empty' ?>">
                                <?= isset($product['stock']) ? $product['stock'] : 0 ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td data-label="Cantidad">
                        <input type="number" class="product-quantity" min="1" value="1" <?= (!isset($product['has_variables']) && isset($product['stock']) && $product['stock'] <= 0) ? 'disabled' : '' ?>>
                    </td>
                    <td data-label="Variable">
                        <select class="product-variable-select" <?= (($has_variables_column_exists && isset($product['has_variables']) && $product['has_variables']) || in_array($product['id'], $productsWithVariables ?? [])) ? 'required' : '' ?>>
                            <option value="">-- Seleccionar --</option>
                            <?php if (isset($productVariables[$product['id']])): ?>
                                <?php foreach ($productVariables[$product['id']] as $variable): ?>
                                    <option value="<?= htmlspecialchars($variable['name']) ?>" 
                                            data-stock="<?= isset($variable['stock']) ? $variable['stock'] : 0 ?>"
                                            <?= isset($variable['stock']) && $variable['stock'] <= 0 ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($variable['name']) ?> 
                                        (Stock: <span class="<?= isset($variable['stock']) && $variable['stock'] > 0 ? 'stock-available' : 'stock-empty' ?>"><?= isset($variable['stock']) ? $variable['stock'] : 0 ?></span>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </td>
                    <td data-label="Especificaciones">
                        <input type="text" class="product-specs" placeholder="Especificaciones adicionales" <?= (!isset($product['has_variables']) && isset($product['stock']) && $product['stock'] <= 0) ? 'disabled' : '' ?>>
                    </td>
                    <td data-label="Acción">
                        <button type="button" class="btn-add-product" 
                                data-id="<?= $product['id'] ?>" 
                                data-name="<?= $product['name'] ?>" 
                                data-price="<?= $product['price'] ?>"
                                data-stock="<?= isset($product['stock']) ? $product['stock'] : 0 ?>"
                                data-has-variables="<?= ($has_variables_column_exists && isset($product['has_variables']) && $product['has_variables']) || in_array($product['id'], $productsWithVariables ?? []) ? '1' : '0' ?>"
                                <?= (!($has_variables_column_exists && isset($product['has_variables']) && $product['has_variables']) && !in_array($product['id'], $productsWithVariables ?? []) && isset($product['stock']) && $product['stock'] <= 0) ? 'disabled' : '' ?>>
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
    No hay productos disponibles. Por favor, <a href="manage_products.php">agregue algunos productos</a> o ejecute el <a href="fix_database.php">script de reparación de la base de datos</a>.
</div>
<?php endif; ?>
  </div>
  
  <div class="card cart">
      <h3><i class="fas fa-shopping-basket"></i> Productos en el Pedido</h3>
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
                      <td colspan="3" data-label="Total">Total:</td>
                      <td id="cart-total" data-label="Monto">S/. 0.00</td>
                      <td></td>
                  </tr>
              </tfoot>
          </table>
      </div>
      <input type="hidden" name="cart_items" id="cart-items-input">
      <input type="hidden" name="total_amount" id="total-amount-input">
  </div>
  
  <div class="form-actions">
      <button type="submit" class="btn-primary" <?= count($products) == 0 ? 'disabled' : '' ?>>
          <i class="fas fa-save"></i> Guardar Pedido
      </button>
      <button type="button" id="clear-cart" class="btn-secondary">
          <i class="fas fa-trash"></i> Limpiar Carrito
      </button>
  </div>
</form>

<div class="card">
  <h3><i class="fas fa-history"></i> Pedidos Recientes</h3>
  <?php if (count($recent_orders) > 0): ?>
  <div class="table-responsive">
      <table>
          <thead>
              <tr>
                  <th>ID</th>
                  <th>Cliente</th>
                  <th>Total</th>
                  <th>Estado Pago</th>
                  <?php if ($is_delivered_column_exists): ?>
                  <th>Estado Entrega</th>
                  <?php endif; ?>
                  <th>Fecha</th>
                  <th>Acciones</th>
              </tr>
          </thead>
          <tbody>
              <?php foreach ($recent_orders as $order): ?>
                  <tr>
                      <td data-label="ID"><?= $order['id'] ?></td>
                      <td data-label="Cliente"><?= $order['customer_name'] ?></td>
                      <td data-label="Total">S/. <?= number_format($order['total_amount'], 2) ?></td>
                      <td data-label="Estado Pago">
                          <span class="status-badge <?= isset($order['is_paid']) && $order['is_paid'] ? 'paid' : 'pending' ?>">
                              <?= isset($order['is_paid']) && $order['is_paid'] ? 'Pagado' : 'Pendiente' ?>
                          </span>
                      </td>
                      <?php if ($is_delivered_column_exists): ?>
                      <td data-label="Estado Entrega">
                          <span class="status-badge <?= isset($order['is_delivered']) && $order['is_delivered'] ? 'delivered' : 'pending' ?>">
                              <?= isset($order['is_delivered']) && $order['is_delivered'] ? 'Entregado' : 'Pendiente' ?>
                          </span>
                      </td>
                      <?php endif; ?>
                      <td data-label="Fecha"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                      <td data-label="Acciones" class="actions-cell">
                          <a href="view_order.php?id=<?= $order['id'] ?>" class="btn-small btn-info">
                              <i class="fas fa-eye"></i> Ver
                          </a>
                          <a href="delete_order.php?id=<?= $order['id'] ?>" class="btn-small btn-danger" onclick="return confirm('¿Está seguro de eliminar este pedido?')">
                              <i class="fas fa-trash"></i> Eliminar
                          </a>
                      </td>
                  </tr>
              <?php endforeach; ?>
          </tbody>
      </table>
  </div>
  <?php else: ?>
  <div class="no-results">
      <p>No hay pedidos recientes.</p>
  </div>
  <?php endif; ?>
</div>

<?php
$extra_js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    let cart = [];
    const clearCartBtn = document.getElementById('clear-cart');
    const cartItemsTable = document.getElementById('cart-items').getElementsByTagName('tbody')[0];
    const cartTotalElement = document.getElementById('cart-total');
    const cartItemsInput = document.getElementById('cart-items-input');
    const totalAmountInput = document.getElementById('total-amount-input');
    
    // Función para agregar productos al carrito
    function addProductToCart(button) {
        const row = button.closest('tr');
        const quantityInput = row.querySelector('.product-quantity');
        const variableSelect = row.querySelector('.product-variable-select');
        const specsInput = row.querySelector('.product-specs');
        
        const productId = button.getAttribute('data-id');
        const productName = button.getAttribute('data-name');
        const productPrice = parseFloat(button.getAttribute('data-price'));
        const productStock = parseInt(button.getAttribute('data-stock') || 0);
        const quantity = parseInt(quantityInput.value);
        const selectedVariable = variableSelect ? variableSelect.value : '';
        const specs = specsInput ? specsInput.value.trim() : '';
        
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
        
        // Verificar si el producto tiene variables y si se seleccionó una
        const hasVariables = button.getAttribute('data-has-variables') === '1';
        if (hasVariables && !selectedVariable) {
            alert('Este producto requiere seleccionar una variable');
            return;
        }
        
        // Obtener el stock según si es variable o producto general
        let availableStock = productStock;
        if (hasVariables && selectedVariable) {
            const selectedOption = variableSelect.options[variableSelect.selectedIndex];
            availableStock = parseInt(selectedOption.getAttribute('data-stock') || 0);
        }
        
        // Verificar stock disponible
        if (availableStock <= 0) {
            alert('No hay stock disponible para este producto');
            return;
        }
        
        // Generate a unique key for the cart item that includes variables
        const itemKey = productId + '-' + (variables ? variables.replace(/\\s+/g, '_') : '');
        
        // Check if product with same variables already in cart
        const existingItemIndex = cart.findIndex(cartItem => 
            cartItem.id === productId && cartItem.variables === variables
        );
        
        if (existingItemIndex > -1) {
            // Verificar que la cantidad total no exceda el stock
            const newQuantity = cart[existingItemIndex].quantity + quantity;
            if (newQuantity > availableStock) {
                alert('No hay suficiente stock disponible. Ya tiene ' + cart[existingItemIndex].quantity + ' unidades en el carrito y el stock actual es: ' + availableStock);
                return;
            }
        
            // Update quantity
            cart[existingItemIndex].quantity = newQuantity;
        } else {
            // Verificar que la cantidad no exceda el stock
            if (quantity > availableStock) {
                alert('No hay suficiente stock disponible. Stock actual: ' + availableStock);
                return;
            }
        
            // Add new item
            cart.push({
                id: productId,
                name: productName,
                price: productPrice,
                quantity: quantity,
                variables: variables,
                itemKey: itemKey,
                stock: availableStock,
                hasVariables: hasVariables
            });
        }
    
        updateCartDisplay();
    
        // Reset inputs
        quantityInput.value = 1;
        if (variableSelect) variableSelect.selectedIndex = 0;
        if (specsInput) specsInput.value = '';
    }
    
    // Add event listeners to all "Agregar" buttons
    const addButtons = document.querySelectorAll('.btn-add-product');
    addButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            addProductToCart(this);
        });
    });
    
    // Clear cart
    clearCartBtn.addEventListener('click', function() {
        cart = [];
        updateCartDisplay();
    });
    
    // Update cart display
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
            removeButton.className = 'btn-small btn-danger';
            removeButton.dataset.index = index;
            removeButton.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
            actionCell.appendChild(removeButton);
            
            // Agregar evento al botón de eliminar
            removeButton.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                cart.splice(index, 1);
                updateCartDisplay();
            });
        });
        
        // Update total
        cartTotalElement.textContent = `$ ${total.toFixed(0)}`;
        
        // Update hidden inputs for form submission
        cartItemsInput.value = JSON.stringify(cart);
        totalAmountInput.value = total.toFixed(2);
    }

    // Agregar funcionalidad de búsqueda
    const searchInput = document.getElementById('product-search');
    const productRows = document.querySelectorAll('.product-row');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        productRows.forEach(row => {
            const productName = row.querySelector('td[data-label="Producto"]').textContent.toLowerCase();
            if (productName.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
JS;

include 'template_footer.php';
?>

