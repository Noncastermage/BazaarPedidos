<?php
// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="plantilla_productos.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV header
fputcsv($output, ['Nombre', 'Precio']);

// Add sample data
fputcsv($output, ['Chicha Morada (1L)', '10.00']);
fputcsv($output, ['Chicha de Jora (1L)', '12.00']);
fputcsv($output, ['Mazamorra Morada', '8.00']);

// Close output stream
fclose($output);
exit;
?>

