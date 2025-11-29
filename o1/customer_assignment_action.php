<?php
require_once 'config.php';
checkLogin();

// إضافة ربط
if(isset($_POST['add_assignment'])) {
    try {
        $pdo->beginTransaction();
        
        // إذا كان المسؤول الرئيسي، إلغاء الأساسي من الآخرين
        if(isset($_POST['is_primary']) && $_POST['is_primary'] == 1) {
            $stmt = $pdo->prepare("UPDATE customer_branch_rep_assignments 
                                   SET is_primary = 0 
                                   WHERE customer_id = ?");
            $stmt->execute([$_POST['customer_id']]);
        }
        
        $stmt = $pdo->prepare("INSERT INTO customer_branch_rep_assignments 
                               (customer_id, my_branch_id, sales_rep_id, is_primary, notes) 
                               VALUES (?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['my_branch_id'] ?: null,
            $_POST['sales_rep_id'] ?: null,
            isset($_POST['is_primary']) ? 1 : 0,
            $_POST['notes'] ?? null
        ]);
        
        $pdo->commit();
        logActivity('إضافة ربط عميل', "تم ربط العميل ID: {$_POST['customer_id']}");
        
        header('Location: view_customer.php?id=' . $_POST['customer_id'] . '&success=assignment_added');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header('Location: view_customer.php?id=' . $_POST['customer_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// حذف ربط
if(isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM customer_branch_rep_assignments WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        
        logActivity('حذف ربط عميل', "تم حذف الربط ID: {$_GET['delete']}");
        
        header('Location: view_customer.php?id=' . $_GET['customer_id'] . '&success=assignment_deleted');
        exit;
        
    } catch(Exception $e) {
        header('Location: view_customer.php?id=' . $_GET['customer_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}
?>