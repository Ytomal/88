<?php
require_once 'config.php';
checkLogin();

// إضافة دفعة
if(isset($_POST['add_payment'])) {
    try {
        $pdo->beginTransaction();
        
        // إدراج الدفعة
        $stmt = $pdo->prepare("INSERT INTO payments 
            (customer_id, payment_date, amount, payment_method, my_branch_id, received_by, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['payment_date'],
            $_POST['amount'],
            $_POST['payment_method'],
            $_POST['my_branch_id'] ?: null,
            $_SESSION['user_id'],
            $_POST['notes'] ?? null
        ]);
        
        $payment_id = $pdo->lastInsertId();
        $remaining_amount = floatval($_POST['amount']);
        
        // ربط الدفعة بالفواتير
        if(isset($_POST['invoices']) && is_array($_POST['invoices'])) {
            foreach($_POST['invoices'] as $invoice_id => $allocated) {
                $allocated = floatval($allocated);
                if($allocated > 0 && $remaining_amount > 0) {
                    $allocated = min($allocated, $remaining_amount);
                    
                    // إدراج الربط
                    $stmt = $pdo->prepare("INSERT INTO payment_invoice_link 
                        (payment_id, invoice_id, allocated_amount) VALUES (?, ?, ?)");
                    $stmt->execute([$payment_id, $invoice_id, $allocated]);
                    
                    // تحديث الفاتورة
                    $stmt = $pdo->prepare("SELECT paid_amount, total_amount FROM invoices WHERE id = ?");
                    $stmt->execute([$invoice_id]);
                    $invoice = $stmt->fetch();
                    
                    $new_paid = $invoice['paid_amount'] + $allocated;
                    $new_remaining = $invoice['total_amount'] - $new_paid;
                    
                    $status = 'unpaid';
                    if($new_remaining <= 0.01) {
                        $status = 'paid';
                        $new_remaining = 0;
                    } elseif($new_paid > 0) {
                        $status = 'partial';
                    }
                    
                    $stmt = $pdo->prepare("UPDATE invoices SET 
                        paid_amount = ?, remaining_amount = ?, status = ? 
                        WHERE id = ?");
                    $stmt->execute([$new_paid, $new_remaining, $status, $invoice_id]);
                    
                    $remaining_amount -= $allocated;
                }
            }
        }
        
        $pdo->commit();
        logActivity('إضافة دفعة', "مبلغ: {$_POST['amount']} ريال للعميل ID: {$_POST['customer_id']}");
        
        header("Location: customer_payments.php?id={$_POST['customer_id']}&success=added");
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header("Location: customer_payments.php?id={$_POST['customer_id']}&error=" . urlencode($e->getMessage()));
        exit;
    }
}
?>