<?php
require_once 'config.php';
session_start();

$page_title = "Ver Pedidos - Sistema de Pedidos";

try {
    // Check database structure in a single query
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME = 'orders' AND COLUMN_NAME = 'is_paid') as is_paid_exists,
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME = 'orders' AND COLUMN_NAME = 'is_delivered') as is_delivered_exists
    ");
    $stmt->execute();
    $dbStructure = $stmt->fetch();
    
    $is_paid_column_exists = $dbStructure['is_paid_exists'] > 0;
    $is_delivered_column_exists = $dbStructure['is_delivered_exists'] > 0;

    // If columns don't exist, add them
    if (!$is_paid_column_exists) {
        $conn->exec("ALTER TABLE orders ADD COLUMN is_paid TINYINT(1) DEFAULT 0");
        $_SESSION['success'] = "La columna 'is_paid' ha sido agregada a la tabla 'orders'.";
        header('Location: update_database_full.php');
        exit;
    }

    if (!$is_delivered_column_exists) {
        $conn->exec("ALTER TABLE orders ADD COLUMN is_delivered TINYINT(1) DEFAULT 0");
        $_SESSION['success'] = "La columna 'is_delivered' ha sido agregada a la tabla 'orders'.";
        header('Location: update_database_delivered.php');
        exit;
    }

    // Get filter parameters
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $delivery = isset($_GET['delivery']) ? $_GET['delivery'] : 'all';
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // Build query
    $query = "SELECT * FROM orders WHERE 1=1";
    $params = [];

    if ($status !== 'all') {
        $query .= " AND is_paid = :status";
        $params[':status'] = ($status === 'paid') ? 1 : 0;
    }

    if ($delivery !== 'all') {
        $query .= " AND is_delivered = :delivery";
        $params[':delivery'] = ($delivery === 'delivered') ? 1 : 0;
    }

    if (!empty($date)) {
        $query .= " AND DATE(created_at) = :date";
        $params[':date'] = $date;
    }

    if (!empty($search)) {
        $query .= " AND (customer_name LIKE :search OR phone LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $query .= " ORDER BY created_at DESC";

    // Prepare and execute query
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $orders = $stmt->fetchAll();

    // Get total sales
    $queryTotal = "SELECT SUM(total_amount) as total FROM orders WHERE is_paid = 1";
    $paramsTotal = [];
    
    if (!empty($date)) {
        $queryTotal .= " AND DATE(created_at) = :date";
        $paramsTotal[':date'] = $date;
    }

    $stmtTotal = $conn->prepare($queryTotal);
    foreach ($paramsTotal as $key => $value) {
        $stmtTotal->bindValue($key, $value);
    }
    $stmtTotal->execute();
    $totalSales = $stmtTotal->fetch()['total'] ?? 0;

} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar los datos: " . $e->getMessage();
}

// Get success or error messages
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);

// Incluir el encabezado
include 'template_header.php';
?>

<div class="orders-header">
    <h2><i class="fas fa-list"></i> Lista de Pedidos</h2>
    <div class="sales-summary">
        <strong>Total Ventas:</strong> S/. <?= number_format($totalSales, 2) ?>
    </div>
</div>

<div class="card filters">
    <form action="" method="get" class="filter-form">
        <div class="filter-group">
            <label for="status">Estado de Pago:</label>
            <select name="status" id="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Pagados</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendientes</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="delivery">Estado de Entrega:</label>
            <select name="delivery" id="delivery">
                <option value="all" <?= $delivery === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="delivered" <?= $delivery === 'delivered' ? 'selected' : '' ?>>Entregados</option>
                <option value="pending" <?= $delivery === 'pending' ? 'selected' : '' ?>>No Entregados</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="date">Fecha:</label>
            <input type="date" name="date" id="date" value="<?= $date ?>">
        </div>
        
        <div class="filter-group">
            <label for="search">Buscar:</label>
            <input type="text" name="search" id="search" placeholder="Nombre o teléfono" value="<?= $search ?>">
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn-primary">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="view_orders.php" class="btn-secondary">
                <i class="fas fa-sync-alt"></i> Limpiar
            </a>
        </div>
    </form>
</div>

<div class="orders-list">
    <?php if (count($orders) > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Celular</th>
                        <th>Total</th>
                        <th>Estado Pago</th>
                        <th>Estado Entrega</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td data-label="ID"><?= $order['id'] ?></td>
                            <td data-label="Cliente"><?= $order['customer_name'] ?></td>
                            <td data-label="Celular"><?= !empty($order['phone']) ? $order['phone'] : '<em>-</em>' ?></td>
                            <td data-label="Total">S/. <?= number_format($order['total_amount'], 2) ?></td>
                            <td data-label="Estado Pago">
                                <span class="status-badge <?= isset($order['is_paid']) && $order['is_paid'] ? 'paid' : 'pending' ?>">
                                    <?= isset($order['is_paid']) && $order['is_paid'] ? 'Pagado' : 'Pendiente' ?>
                                </span>
                            </td>
                            <td data-label="Estado Entrega">
                                <span class="status-badge <?= isset($order['is_delivered']) && $order['is_delivered'] ? 'delivered' : 'pending' ?>">
                                    <?= isset($order['is_delivered']) && $order['is_delivered'] ? 'Entregado' : 'Pendiente' ?>
                                </span>
                            </td>
                            <td data-label="Fecha"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                            <td data-label="Acciones" class="actions-cell">
                                <a href="view_order.php?id=<?= $order['id'] ?>" class="btn-small btn-info">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <a href="edit_order.php?id=<?= $order['id'] ?>" class="btn-small btn-primary">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <a href="update_status.php?id=<?= $order['id'] ?>&status=<?= $order['is_paid'] ? '0' : '1' ?>" class="btn-small <?= $order['is_paid'] ? 'btn-warning' : 'btn-success' ?>">
                                    <i class="fas fa-<?= $order['is_paid'] ? 'times' : 'check' ?>"></i>
                                    <?= $order['is_paid'] ? 'No Pagado' : 'Pagado' ?>
                                </a>
                                <a href="update_delivery_status.php?id=<?= $order['id'] ?>&status=<?= $order['is_delivered'] ? '0' : '1' ?>" class="btn-small <?= $order['is_delivered'] ? 'btn-warning' : 'btn-delivered' ?>">
                                    <i class="fas fa-<?= $order['is_delivered'] ? 'times' : 'truck' ?>"></i>
                                    <?= $order['is_delivered'] ? 'No Entregado' : 'Entregado' ?>
                                </a>
                                <a href="delete_order.php?id=<?= $order['id'] ?>" class="btn-small btn-danger" onclick="return confirm('¿Está seguro de eliminar este pedido? Esta acción no se puede deshacer.')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="no-results card">
            <p><i class="fas fa-info-circle"></i> No se encontraron pedidos con los filtros seleccionados.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'template_footer.php'; ?>

