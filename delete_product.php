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

try {
    // Check if product is used in any order
    $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :id");
    $stmt->bindParam(':id', $productId);
    $stmt->execute();
    $usageCount = $stmt->fetchColumn();
    
    if ($usageCount > 0) {
        $_SESSION['error'] = "No se puede eliminar el producto porque está siendo utilizado en $usageCount pedido(s). Debe eliminar los pedidos relacionados primero o marcar el producto como inactivo.";
        header('Location: manage_products.php');
        exit;
    }
    
    // Delete product
    $stmt = $conn->prepare("DELETE FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId);
    $stmt->execute();
    
    $_SESSION['success'] = "Producto eliminado correctamente.";
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al eliminar el producto: " . $e->getMessage();
}

header('Location: manage_products.php');
exit;
?>

