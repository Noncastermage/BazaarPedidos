<?php
// Determinar la página actual para resaltar el enlace de navegación
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'Sistema de Pedidos' ?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php if (isset($extra_css)): ?>
    <style>
        <?= $extra_css ?>
    </style>
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1>Sistema de Pedidos</h1>
                <button type="button" class="mobile-menu-toggle" aria-label="Menú">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <nav id="main-nav">
                <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-plus-circle"></i> Nuevo Pedido
                </a>
                <a href="view_orders.php" class="<?= $current_page == 'view_orders.php' || $current_page == 'view_order.php' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> Ver Pedidos
                </a>
                <a href="manage_products.php" class="<?= $current_page == 'manage_products.php' || $current_page == 'add_product.php' || $current_page == 'edit_product.php' ? 'active' : '' ?>">
                    <i class="fas fa-box"></i> Gestionar Productos
                </a>
            </nav>
        </header>

        <main>
            <?php if (isset($successMessage) && !empty($successMessage)): ?>
                <div class="alert success">
                    <?= $successMessage ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage) && !empty($errorMessage)): ?>
                <div class="alert error">
                    <?= htmlspecialchars_decode($errorMessage) ?>
                </div>
            <?php endif; ?>

