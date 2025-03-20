<?php
require_once 'config.php';
session_start();

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
  header('Location: view_orders.php');
  exit;
}

$orderId = $_GET['id'];
$page_title = "Detalle de Pedido #$orderId - Sistema de Pedidos";

try {
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

  // Add missing columns if needed
  if (!$is_paid_column_exists) {
      $conn->exec("ALTER TABLE orders ADD COLUMN is_paid TINYINT(1) DEFAULT 0");
      $_SESSION['success'] = "La columna 'is_paid' ha sido agregada a la tabla 'orders'.";
      $is_paid_column_exists = true;
  }

  if (!$is_delivered_column_exists) {
      $conn->exec("ALTER TABLE orders ADD COLUMN is_delivered TINYINT(1) DEFAULT 0");
      $_SESSION['success'] = "La columna 'is_delivered' ha sido agregada a la tabla 'orders'.";
      $is_delivered_column_exists = true;
  }

  if (!$variables_column_exists) {
      $conn->exec("ALTER TABLE order_items ADD COLUMN variables TEXT NULL");
      $_SESSION['success'] = "La columna 'variables' ha sido agregada a la tabla 'order_items'.";
      $variables_column_exists = true;
  }

  if (!$statusColumnExists) {
      $conn->exec("ALTER TABLE order_items ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'no pagado'");
      $_SESSION['success'] = "La columna 'status' ha sido agregada a la tabla 'order_items'.";
      $statusColumnExists = true;
  }

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

// Display success message if any
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);

// Incluir el encabezado
include 'template_header.php';
?>

<div class="order-header">
  <h2><i class="fas fa-file-invoice"></i> Pedido #<?= $orderId ?></h2>
  <div class="order-actions">
      <a href="print_order.php?id=<?= $orderId ?>" class="btn-secondary" target="_blank">
          <i class="fas fa-print"></i> Imprimir
      </a>
      <a href="edit_order.php?id=<?= $orderId ?>" class="btn-info">
          <i class="fas fa-edit"></i> Editar
      </a>
      <?php if ($is_paid_column_exists && isset($order['is_paid'])): ?>
      <a href="update_status.php?id=<?= $orderId ?>&status=<?= $order['is_paid'] ? '0' : '1' ?>" class="<?= $order['is_paid'] ? 'btn-warning' : 'btn-success' ?>">
          <i class="fas fa-<?= $order['is_paid'] ? 'times' : 'check' ?>"></i> 
          <?= $order['is_paid'] ? 'Marcar Pendiente' : 'Marcar Pagado' ?>
      </a>
      <?php endif; ?>
      <?php if ($is_delivered_column_exists && isset($order['is_delivered'])): ?>
      <a href="update_delivery_status.php?id=<?= $orderId ?>&status=<?= $order['is_delivered'] ? '0' : '1' ?>" class="<?= $order['is_delivered'] ? 'btn-warning' : 'btn-delivered' ?>">
          <i class="fas fa-<?= $order['is_delivered'] ? 'times' : 'truck' ?>"></i> 
          <?= $order['is_delivered'] ? 'Marcar No Entregado' : 'Marcar Entregado' ?>
      </a>
      <?php endif; ?>
      <a href="delete_order.php?id=<?= $orderId ?>" class="btn-danger" onclick="return confirm('¿Está seguro de eliminar este pedido? Esta acción no se puede deshacer.')">
          <i class="fas fa-trash"></i> Eliminar
      </a>
  </div>
</div>

<div class="order-details">
  <div class="card order-info">
      <h3><i class="fas fa-info-circle"></i> Información del Pedido</h3>
      <table class="no-responsive">
          <tr>
              <th>Cliente:</th>
              <td><?= $order['customer_name'] ?></td>
          </tr>
          <tr>
              <th>Celular:</th>
              <td><?= !empty($order['phone']) ? $order['phone'] : '<em>No proporcionado</em>' ?></td>
          </tr>
          <tr>
              <th>Estados:</th>
              <td>
                  <div class="status-container">
                      <div class="status-row">
                          <span class="status-label">Pago:</span>
                          <span class="status-badge <?= $is_paid_column_exists && isset($order['is_paid']) && $order['is_paid'] ? 'paid' : 'pending' ?>">
                              <?= $is_paid_column_exists && isset($order['is_paid']) && $order['is_paid'] ? 'Pagado' : 'Pendiente' ?>
                          </span>
                      </div>
                      <div class="status-row">
                          <span class="status-label">Entrega:</span>
                          <span class="status-badge <?= $is_delivered_column_exists && isset($order['is_delivered']) && $order['is_delivered'] ? 'delivered' : 'pending' ?>">
                              <?= $is_delivered_column_exists && isset($order['is_delivered']) && $order['is_delivered'] ? 'Entregado' : 'Pendiente' ?>
                          </span>
                      </div>
                  </div>
              </td>
          </tr>
          <tr>
              <th>Fecha:</th>
              <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
          </tr>
          <?php if (isset($order['updated_at']) && $order['updated_at'] != $order['created_at']): ?>
          <tr>
              <th>Última modificación:</th>
              <td><?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></td>
          </tr>
          <?php endif; ?>
      </table>
  </div>
  
  <div class="card order-items">
      <h3><i class="fas fa-shopping-cart"></i> Productos</h3>
      <div class="table-responsive">
          <table>
              <thead>
                  <tr>
                      <th>Producto</th>
                      <th>Cantidad</th>
                      <th>Precio</th>
                      <th>Subtotal</th>
                      <?php if ($statusColumnExists): ?>
                      <th>Estado Pago</th>
                      <?php endif; ?>
                      <?php 
                      // Verificar si existe la columna is_delivered en order_items
                      $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'is_delivered'");
                      $stmt->execute();
                      $is_delivered_item_column_exists = $stmt->rowCount() > 0;
                      ?>
                      <?php if ($is_delivered_item_column_exists): ?>
                      <th>Estado Entrega</th>
                      <?php endif; ?>
                      <th>Acción</th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($orderItems as $item): ?>
                      <tr>
                          <td data-label="Producto">
                              <?= $item['product_name'] ?>
                              <?php if ($variables_column_exists && isset($item['variables']) && !empty($item['variables'])): ?>
                              <div class="item-variables"><?= $item['variables'] ?></div>
                              <?php endif; ?>
                          </td>
                          <td data-label="Cantidad"><?= $item['quantity'] ?></td>
                          <td data-label="Precio">S/. <?= number_format($item['price'], 2) ?></td>
                          <td data-label="Subtotal">S/. <?= number_format($item['subtotal'], 2) ?></td>
                          <?php if ($statusColumnExists): ?>
                          <td data-label="Estado">
                              <span class="status-badge <?= isset($item['status']) && $item['status'] === 'pagado' ? 'paid' : 'pending' ?>">
                                  <?= isset($item['status']) ? ($item['status'] === 'pagado' ? 'Pagado' : 'No Pagado') : 'No Pagado' ?>
                              </span>
                          </td>
                          <?php endif; ?>

                          <?php 
                          // Verificar si existe la columna is_delivered en order_items
                          $stmt = $conn->prepare("SHOW COLUMNS FROM order_items LIKE 'is_delivered'");
                          $stmt->execute();
                          $is_delivered_item_column_exists = $stmt->rowCount() > 0;
                          ?>

                          <?php if ($is_delivered_item_column_exists): ?>
                          <td data-label="Entrega">
                              <span class="status-badge <?= isset($item['is_delivered']) && $item['is_delivered'] ? 'delivered' : 'pending' ?>">
                                  <?= isset($item['is_delivered']) && $item['is_delivered'] ? 'Entregado' : 'Pendiente' ?>
                              </span>
                          </td>
                          <?php endif; ?>

                          <td data-label="Acción">
                              <?php if ($statusColumnExists): ?>
                              <a href="update_item_status.php?id=<?= $item['id'] ?>&status=<?= isset($item['status']) && $item['status'] === 'pagado' ? 'no pagado' : 'pagado' ?>&order_id=<?= $orderId ?>" class="btn-small <?= isset($item['status']) && $item['status'] === 'pagado' ? 'btn-warning' : 'btn-success' ?>">
                                  <i class="fas fa-<?= isset($item['status']) && $item['status'] === 'pagado' ? 'times' : 'check' ?>"></i>
                                  <?= isset($item['status']) && $item['status'] === 'pagado' ? 'No Pagado' : 'Pagado' ?>
                              </a>
                              <?php endif; ?>
                              
                              <?php if ($is_delivered_item_column_exists): ?>
                              <a href="update_item_delivery.php?id=<?= $item['id'] ?>&status=<?= isset($item['is_delivered']) && $item['is_delivered'] ? '0' : '1' ?>&order_id=<?= $orderId ?>" class="btn-small <?= isset($item['is_delivered']) && $item['is_delivered'] ? 'btn-warning' : 'btn-delivered' ?>">
                                  <i class="fas fa-<?= isset($item['is_delivered']) && $item['is_delivered'] ? 'times' : 'truck' ?>"></i>
                                  <?= isset($item['is_delivered']) && $item['is_delivered'] ? 'No Entregado' : 'Entregado' ?>
                              </a>
                              <?php endif; ?>
                          </td>
                      </tr>
                  <?php endforeach; ?>
              </tbody>
              <tfoot>
                  <tr>
                      <td colspan="<?= $statusColumnExists ? '5' : '3' ?>" data-label="Total">Total:</td>
                      <td data-label="Monto">S/. <?= number_format($order['total_amount'], 2) ?></td>
                  </tr>
              </tfoot>
          </table>
      </div>
  </div>
</div>

<div class="back-link">
  <a href="view_orders.php" class="btn-secondary">
      <i class="fas fa-arrow-left"></i> Volver a la lista de pedidos
  </a>
</div>

<?php include 'template_footer.php'; ?>

