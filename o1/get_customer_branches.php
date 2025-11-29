<?php
require_once 'config.php';
checkLogin();

header('Content-Type: application/json');

$customer_id = $_GET['customer_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT id, branch_name, branch_code, address 
                           FROM customer_branches 
                           WHERE customer_id = ? AND status = 'active' 
                           ORDER BY branch_name");
    $stmt->execute([$customer_id]);
    $branches = $stmt->fetchAll();
    
    echo json_encode($branches);
} catch(Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>