<?php
require_once 'config.php';
checkLogin();

header('Content-Type: application/json');

$customer_id = $_GET['customer_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT id, invoice_number, invoice_date, total_amount, 
                           paid_amount, remaining_amount, status 
                           FROM invoices 
                           WHERE customer_id = ? AND status != 'paid' AND remaining_amount > 0
                           ORDER BY invoice_date ASC");
    $stmt->execute([$customer_id]);
    $invoices = $stmt->fetchAll();
    
    echo json_encode($invoices);
} catch(Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>