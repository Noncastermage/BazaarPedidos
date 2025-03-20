<?php
require_once 'config.php';
session_start();

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de pedido no proporcionado.";
    header('Location: view_orders.php');
    exit;
}

$orderId = $_GET['id'];

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

// Get all products
$stmt = $conn->prepare("
    SELECT p.*, 
           COALESCE(p.has_variants, 0) as has_variants,
           GROUP_CONCAT(CONCAT(pv.id, ':', pv.name, ':', pv.price_adjustment) SEPARATOR '|') as variants
    FROM products p
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    GROUP BY p.id
    ORDER BY p.name
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Get cart items from form
        $cartItems = json_decode($_POST['cart_items'], true);
        
        if (empty($cartItems)) {
            throw new Exception("No se han seleccionado productos para agregar.");
        }
        
        // Insert order items
        $stmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, variant_id, quantity, price, subtotal, item_status) 
            VALUES (:order_id, :product_id, :variant_id, :quantity, :price, :subtotal, :item_status)
        ");

        $totalAdded = 0;

        // Determine the status to use for new items (match existing items)
        $statusStmt = $conn->prepare("
            SELECT item_status FROM order_items WHERE order_id = :order_id LIMIT 1
        ");
        $statusStmt->bindParam(':order_id', $orderId);
        $statusStmt->execute();
        $existingStatus = $statusStmt->fetchColumn();

        // If no existing items or couldn't determine status, use the order status
        if (!$existingStatus) {
            $existingStatus = ($order['status'] === 'paid' || $order['status'] === 'delivered') ? 'paid' : 'pending';
        }

        foreach ($cartItems as $item) {
            $productId = $item['id'];
            $variantId = !empty($item['variant_id']) ? $item['variant_id'] : null;
            $quantity = $item['quantity'];
            $price = $item['price'];
            $subtotal = $price * $quantity;
            
            $stmt->bindParam(':order_id', $orderId);
            $stmt->bindParam(':product_id', $productId);
            $stmt->bindParam(':variant_id', $variantId);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':subtotal', $subtotal);
            $stmt->bindParam(':item_status', $existingStatus);
            $stmt->execute();
            
            $totalAdded += $subtotal;
        }
        
        // Update order total amount
        $stmt = $conn->prepare("
            UPDATE orders 
            SET total_amount = total_amount + :total_added 
            WHERE id = :order_id
        ");
        $stmt->bindParam(':total_added', $totalAdded);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Productos agregados al pedido #$orderId correctamente.";
        header("Location: view_order.php?id=$orderId");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error'] = "Error al agregar productos: " . $e->getMessage();
    }
}

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
    <title>Agregar Productos al Pedido #<?= $orderId ?> - Sistema de Pedidos</title>
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
                <h2>Agregar Productos al Pedido #<?= $orderId ?></h2>
                <p>Cliente: <?= $order['customer_name'] ?></p>
            </div>
            
            <form action="add_to_order.php?id=<?= $orderId ?>" method="post">
                <div class="order-content-vertical">
                    <div class="products-section">
                        <h3>Productos Disponibles</h3>
                        
                        <div class="product-controls">
                            <div class="product-search">
                                <input type="text" id="product-search-input" placeholder="Buscar productos...">
                                <button type="button" id="clear-search">✕</button>
                            </div>
                        </div>
                        
                        <div class="products-table-container">
                            <table class="products-table">
                                <thead>
                                    <tr>
                                        <th class="id-col">ID</th>
                                        <th>Producto</th>
                                        <th>Precio</th>
                                        <th>Variante</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <?php 
                                            $hasVariants = isset($product['has_variants']) ? (bool)$product['has_variants'] : false;
                                            $variantsData = [];
                                            
                                            if ($hasVariants && !empty($product['variants'])) {
                                                $variantsList = explode('|', $product['variants']);
                                                foreach ($variantsList as $variantInfo) {
                                                    if (empty($variantInfo)) continue;
                                                    $parts = explode(':', $variantInfo);
                                                    if (count($parts) >= 3) {
                                                        list($variantId, $variantName, $priceAdjustment) = $parts;
                                                        $variantsData[] = [
                                                            'id' => $variantId,
                                                            'name' => $variantName,
                                                            'price_adjustment' => $priceAdjustment
                                                        ];
                                                    }
                                                }
                                            }
                                        ?>
                                        <tr class="product-row" 
                                            data-id="<?= $product['id'] ?>" 
                                            data-price="<?= $product['price'] ?>" 
                                            data-name="<?= $product['name'] ?>"
                                            data-has-variants="<?= $hasVariants ? 'true' : 'false' ?>">
                                            <td class="product-id"><?= $product['id'] ?></td>
                                            <td class="product-name">
                                                <?= $product['name'] ?>
                                            </td>
                                            <td class="product-price">S/. <?= number_format($product['price'], 2) ?></td>
                                            <td class="product-variant">
                                                <?php if ($hasVariants && !empty($variantsData)): ?>
                                                    <select class="variant-select">
                                                        <option value="">Seleccionar variante</option>
                                                        <?php foreach ($variantsData as $variant): ?>
                                                            <?php $finalPrice = $product['price'] + $variant['price_adjustment']; ?>
                                                            <option value="<?= $variant['id'] ?>" 
                                                                    data-name="<?= $variant['name'] ?>" 
                                                                    data-price="<?= $finalPrice ?>">
                                                                <?= $variant['name'] ?> (S/. <?= number_format($finalPrice, 2) ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <span class="no-variant">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-actions">
                                                <div class="add-actions">
                                                    <input type="number" class="quantity-input" value="1" min="1" max="99">
                                                    <button type="button" class="btn-add-to-cart">Agregar</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="cart-section">
                        <h3>Productos a Agregar</h3>
                        <div class="cart">
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
                                        <td colspan="3">Total:</td>
                                        <td id="cart-total">S/. 0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <input type="hidden" name="cart_items" id="cart-items-input">
                            
                            <div class="cart-actions">
                                <button type="submit" class="btn-primary">Agregar al Pedido</button>
                                <button type="button" id="clear-cart" class="btn-secondary">Limpiar</button>
                                <a href="view_order.php?id=<?= $orderId ?>" class="btn-secondary">Cancelar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let cart = [];
            const clearCartBtn = document.getElementById('clear-cart');
            const cartItemsTable = document.getElementById('cart-items').getElementsByTagName('tbody')[0];
            const cartTotalElement = document.getElementById('cart-total');
            const cartItemsInput = document.getElementById('cart-items-input');
            const searchInput = document.getElementById('product-search-input');
            const clearSearchBtn = document.getElementById('clear-search');
            
            // Product search functionality
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                // Filter table rows
                document.querySelectorAll('.product-row').forEach(row => {
                    const productName = row.dataset.name.toLowerCase();
                    if (productName.includes(searchTerm) || searchTerm === '') {
                        row.style.display = 'table-row';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
            
            // Clear search
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                document.querySelectorAll('.product-row').forEach(row => {
                    row.style.display = 'table-row';
                });
                searchInput.focus();
            });
            
            // Function to add item to cart
            function addToCart(productId, productName, price, quantity, variantId = null, variantName = '') {
                // Check if product already in cart
                const existingItemIndex = cart.findIndex(item => 
                    item.id === productId && item.variant_id === variantId
                );
                
                if (existingItemIndex > -1) {
                    // Update quantity
                    cart[existingItemIndex].quantity += quantity;
                } else {
                    // Add new item
                    cart.push({
                        id: productId,
                        name: productName,
                        price: price,
                        quantity: quantity,
                        variant_id: variantId,
                        variant_name: variantName
                    });
                }
                
                // Guardar la selección de variante en el elemento del DOM
                const productRow = document.querySelector(`.product-row[data-id="${productId}"]`);
                if (productRow && variantId) {
                    productRow.dataset.selectedVariantId = variantId;
                    productRow.dataset.selectedVariantName = variantName;
                    productRow.dataset.selectedVariantPrice = price;
                    
                    // Asegurarse de que el select muestre la variante seleccionada
                    const variantSelect = productRow.querySelector('.variant-select');
                    if (variantSelect) {
                        variantSelect.value = variantId;
                        variantSelect.classList.add('has-selection');
                    }
                }
                
                updateCartDisplay();
            }
            
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
                cart.forEach((item, index) => {
                    const subtotal = item.price * item.quantity;
                    total += subtotal;
                    
                    const displayName = item.variant_name 
                        ? `${item.name} (${item.variant_name})` 
                        : item.name;
                    
                    const row = cartItemsTable.insertRow();
                    row.innerHTML = `
                        <td>${displayName}</td>
                        <td>
                            <div class="quantity-control">
                                <button type="button" class="btn-quantity" data-action="decrease" data-index="${index}">-</button>
                                <span>${item.quantity}</span>
                                <button type="button" class="btn-quantity" data-action="increase" data-index="${index}">+</button>
                            </div>
                        </td>
                        <td>S/. ${item.price.toFixed(2)}</td>
                        <td>S/. ${subtotal.toFixed(2)}</td>
                        <td>
                            <button type="button" class="btn-remove" data-index="${index}">Eliminar</button>
                        </td>
                    `;
                });
                
                // Update total
                cartTotalElement.textContent = `S/. ${total.toFixed(2)}`;
                
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
                
                // Add event listeners to quantity buttons
                document.querySelectorAll('.btn-quantity').forEach(button => {
                    button.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        const action = this.dataset.action;
                        
                        if (action === 'increase') {
                            cart[index].quantity += 1;
                        } else if (action === 'decrease') {
                            if (cart[index].quantity > 1) {
                                cart[index].quantity -= 1;
                            } else {
                                cart.splice(index, 1);
                            }
                        }
                        
                        updateCartDisplay();
                    });
                });
            }
            
            // Add event listeners to variant selects
            document.querySelectorAll('.variant-select').forEach(select => {
                select.addEventListener('change', function() {
                    const productRow = this.closest('.product-row');
                    const selectedOption = this.options[this.selectedIndex];
                    
                    if (selectedOption.value) {
                        // Guardar la selección en el dataset del producto
                        const variantId = selectedOption.value;
                        const variantName = selectedOption.dataset.name;
                        const variantPrice = parseFloat(selectedOption.dataset.price);
                        
                        productRow.dataset.selectedVariantId = variantId;
                        productRow.dataset.selectedVariantName = variantName;
                        productRow.dataset.selectedVariantPrice = variantPrice;
                        
                        // Actualizar el precio mostrado en la fila
                        productRow.dataset.currentPrice = variantPrice;
                        
                        // Habilitar el botón de agregar
                        const addButton = productRow.querySelector('.btn-add-to-cart');
                        addButton.disabled = false;
                        
                        // Actualizar estilo del select
                        this.classList.add('has-selection');
                    } else {
                        this.classList.remove('has-selection');
                    }
                });
            });
            
            // Add event listeners to add buttons
            document.querySelectorAll('.btn-add-to-cart').forEach(button => {
                button.addEventListener('click', function() {
                    const productRow = this.closest('.product-row');
                    const productId = productRow.dataset.id;
                    const productName = productRow.dataset.name;
                    const hasVariants = productRow.dataset.hasVariants === 'true';
                    const quantity = parseInt(productRow.querySelector('.quantity-input').value);
                    
                    if (hasVariants) {
                        const variantSelect = productRow.querySelector('.variant-select');
                        if (!variantSelect.value) {
                            alert('Por favor seleccione una variante.');
                            return;
                        }
                        
                        const selectedOption = variantSelect.options[variantSelect.selectedIndex];
                        const variantId = variantSelect.value;
                        const variantName = selectedOption.dataset.name;
                        const price = parseFloat(selectedOption.dataset.price);
                        
                        // Guardar la selección de variante en el dataset del producto
                        productRow.dataset.selectedVariantId = variantId;
                        productRow.dataset.selectedVariantName = variantName;
                        productRow.dataset.selectedVariantPrice = price;
                        
                        addToCart(productId, productName, price, quantity, variantId, variantName);
                    } else {
                        const price = parseFloat(productRow.dataset.price);
                        addToCart(productId, productName, price, quantity);
                    }
                    
                    // Visual feedback
                    productRow.classList.add('added');
                    setTimeout(() => {
                        productRow.classList.remove('added');
                    }, 500);
                });
            });
        });
    </script>
</body>
</html>

