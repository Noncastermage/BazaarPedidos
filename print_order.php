<?php
require_once 'config.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
  die("ID de pedido no proporcionado.");
}

$orderId = $_GET['id'];

// Check database structure in a single query
$stmt = $conn->prepare("
  SELECT 
      (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
       WHERE TABLE_NAME = 'orders' AND COLUMN_NAME = 'is_paid') as is_paid_exists,
      (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
       WHERE TABLE_NAME = 'orders' AND COLUMN_NAME = 'is_delivered') as is_delivered_exists,
      (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
       WHERE TABLE_NAME = 'order_items' AND COLUMN_NAME = 'variables') as variables_exists,
      (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
       WHERE TABLE_NAME = 'order_items' AND COLUMN_NAME = 'status') as status_exists
");
$stmt->execute();
$dbStructure = $stmt->fetch();

$is_paid_column_exists = $dbStructure['is_paid_exists'] > 0;
$is_delivered_column_exists = $dbStructure['is_delivered_exists'] > 0;
$variables_column_exists = $dbStructure['variables_exists'] > 0;
$statusColumnExists = $dbStructure['status_exists'] > 0;

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id");
$stmt->bindParam(':id', $orderId);
$stmt->execute();
$order = $stmt->fetch();

// Check if order exists
if (!$order) {
  die("El pedido no existe.");
}

// Get order items
$stmt = $conn->prepare("
  SELECT oi.*, p.name as product_name 
  FROM order_items oi
  JOIN products p ON oi.product_id = p.id
  WHERE oi.order_id = :order_id
");
$stmt->bindParam(':order_id', $orderId);
$stmt->execute();
$orderItems = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Imprimir Pedido #<?= $orderId ?></title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="receipt">
      <div class="header">
          <h1>Tienda Chicha</h1>
          <p>Av. Principal 123, Lima</p>
          <p>Tel: 01-234-5678</p>
      </div>
      
      <div class="order-info">
          <h2>PEDIDO #<?= $orderId ?></h2>
          <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
          <?php if (isset($order['updated_at']) && $order['updated_at'] != $order['created_at']): ?>
          <p><strong>Última modificación:</strong> <?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></p>
          <?php endif; ?>
          <p><strong>Cliente:</strong> <?= $order['customer_name'] ?></p>
          <?php if (!empty($order['phone'])): ?>
          <p><strong>Celular:</strong> <?= $order['phone'] ?></p>
          <?php endif; ?>
          <?php if (isset($order['payment_method'])): ?>
        <p><strong>Método de Pago:</strong> <?= $order['payment_method'] ?></p>
        <?php endif; ?>
          <p><strong>Estado de Pago:</strong> <?= $is_paid_column_exists && isset($order['is_paid']) && $order['is_paid'] ? 'PAGADO' : 'PENDIENTE' ?></p>
          <p><strong>Estado de Entrega:</strong> <?= $is_delivered_column_exists && isset($order['is_delivered']) && $order['is_delivered'] ? 'ENTREGADO' : 'PENDIENTE' ?></p>
      </div>
      
      <table>
          <thead>
              <tr>
                  <th>Producto</th>
                  <th>Cant.</th>
                  <th>Precio</th>
                  <th>Subtotal</th>
                  <?php if ($statusColumnExists): ?>
                  <th>Estado</th>
                  <?php endif; ?>
              </tr>
          </thead>
          <tbody>
              <?php foreach ($orderItems as $item): ?>
                  <tr>
                      <td>
                          <?= $item['product_name'] ?>
                          <?php if ($variables_column_exists && isset($item['variables']) && !empty($item['variables'])): ?>
                          <div class="item-variables"><?= $item['variables'] ?></div>
                          <?php endif; ?>
                      </td>
                      <td><?= $item['quantity'] ?></td>
                      <td>S/. <?= number_format($item['price'], 0, ',', '.') ?></td>
                      <td>S/. <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                      <?php if ($statusColumnExists && isset($item['status'])): ?>
                      <td><?= $item['status'] === 'pagado' ? 'Pagado' : 'No Pagado' ?></td>
                      <?php endif; ?>
                  </tr>
              <?php endforeach; ?>
              <tr class="total">
                  <td colspan="<?= $statusColumnExists ? '4' : '3' ?>">TOTAL:</td>
                  <td>S/. <?= number_format($order['total_amount'], 0, ',', '.') ?></td>
              </tr>
          </tbody>
      </table>
      
      <div class="footer">
          <p>¡Gracias por su compra!</p>
      </div>
  </div>

  <div class="no-print" style="text-align: center; margin-top: 20px;">
      <button onclick="window.print()">Imprimir</button>
      <button onclick="window.close()">Cerrar</button>
      <a href="index.php" style="display: inline-block; margin-top: 10px;">Volver al Sistema</a>
  </div>

  <script>
      window.onload = function() {
          // Auto print when page loads
          window.print();
      }
  </script>
</body>
</html>

