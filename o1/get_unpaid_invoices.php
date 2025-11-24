<?php
require_once 'config.php';

header('Content-Type: application/json');

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($customer_id > 0) {
    $sql = "SELECT id, invoice_number, invoice_date, total_amount, paid_amount, remaining_amount 
            FROM invoices 
            WHERE customer_id = $customer_id AND status != 'paid' 
            ORDER BY invoice_date DESC";
    
    $result = $conn->query($sql);
    $invoices = [];
    
    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }
    
    echo json_encode($invoices);
} else {
    echo json_encode([]);
}
?>