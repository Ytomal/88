<?php
require_once 'config.php';
checkLogin();

// إضافة فرع
if(isset($_POST['add_branch'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO customer_branches 
                               (customer_id, branch_name, branch_code, region_id, address, phone, 
                               email, manager_name, shop_fronts_count, shop_description, 
                               google_maps_url, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['branch_name'],
            $_POST['branch_code'] ?? null,
            $_POST['region_id'] ?? null,
            $_POST['address'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['email'] ?? null,
            $_POST['manager_name'] ?? null,
            $_POST['shop_fronts_count'] ?? 1,
            $_POST['shop_description'] ?? null,
            $_POST['google_maps_url'] ?? null
        ]);
        
        // تحديث has_branches
        $stmt = $pdo->prepare("UPDATE customers SET has_branches = 1 WHERE id = ?");
        $stmt->execute([$_POST['customer_id']]);
        
        logActivity('إضافة فرع', "تم إضافة فرع: " . $_POST['branch_name']);
        
        header('Location: branches.php?customer_id=' . $_POST['customer_id'] . '&success=added');
        exit;
        
    } catch(Exception $e) {
        header('Location: branches.php?customer_id=' . $_POST['customer_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// تحديث فرع
if(isset($_POST['update_branch'])) {
    try {
        $stmt = $pdo->prepare("UPDATE customer_branches SET 
                               branch_name = ?, branch_code = ?, region_id = ?, address = ?, 
                               phone = ?, email = ?, manager_name = ?, shop_fronts_count = ?, 
                               shop_description = ?, google_maps_url = ?, status = ?
                               WHERE id = ?");
        
        $stmt->execute([
            $_POST['branch_name'],
            $_POST['branch_code'] ?? null,
            $_POST['region_id'] ?? null,
            $_POST['address'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['email'] ?? null,
            $_POST['manager_name'] ?? null,
            $_POST['shop_fronts_count'] ?? 1,
            $_POST['shop_description'] ?? null,
            $_POST['google_maps_url'] ?? null,
            $_POST['status'] ?? 'active',
            $_POST['branch_id']
        ]);
        
        logActivity('تحديث فرع', "تم تحديث فرع ID: " . $_POST['branch_id']);
        
        header('Location: branches.php?customer_id=' . $_POST['customer_id'] . '&success=updated');
        exit;
        
    } catch(Exception $e) {
        header('Location: edit_branch.php?id=' . $_POST['branch_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// حذف فرع
if(isset($_GET['delete'])) {
    try {
        $branchId = $_GET['delete'];
        $customerId = $_GET['customer_id'];
        
        $stmt = $pdo->prepare("DELETE FROM customer_branches WHERE id = ?");
        $stmt->execute([$branchId]);
        
        // التحقق من وجود فروع أخرى
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_branches WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $count = $stmt->fetchColumn();
        
        if($count == 0) {
            $stmt = $pdo->prepare("UPDATE customers SET has_branches = 0 WHERE id = ?");
            $stmt->execute([$customerId]);
        }
        
        logActivity('حذف فرع', "تم حذف فرع ID: " . $branchId);
        
        header('Location: branches.php?customer_id=' . $customerId . '&success=deleted');
        exit;
        
    } catch(Exception $e) {
        header('Location: branches.php?customer_id=' . $_GET['customer_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}
?>