<?php
require_once 'config.php';

// Get all products
$stmt = $conn->prepare("SELECT * FROM products ORDER BY name");
$stmt->execute();
$products = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="productos_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV header
fputcsv($output, ['Nombre', 'Precio']);

// Add products data
foreach ($products as $product) {
    fputcsv($output, [
        $product['name'],
        $product['price']
    ]);
}

// Close output stream
fclose($output);
exit;
?>

