<?php
require_once 'config.php';
checkLogin();

// إضافة منطقة
if(isset($_POST['add_region'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO regions (region_name, region_code, description, status) 
                               VALUES (?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['region_name'],
            $_POST['region_code'] ?? null,
            $_POST['description'] ?? null,
            $_POST['status'] ?? 'active'
        ]);
        
        logActivity('إضافة منطقة', "تم إضافة المنطقة: " . $_POST['region_name']);
        
        header('Location: regions.php?success=added');
        exit;
        
    } catch(Exception $e) {
        header('Location: regions.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// تحديث منطقة
if(isset($_POST['update_region'])) {
    try {
        $stmt = $pdo->prepare("UPDATE regions SET 
                               region_name = ?, region_code = ?, description = ?, status = ?
                               WHERE id = ?");
        
        $stmt->execute([
            $_POST['region_name'],
            $_POST['region_code'] ?? null,
            $_POST['description'] ?? null,
            $_POST['status'],
            $_POST['region_id']
        ]);
        
        logActivity('تحديث منطقة', "تم تحديث المنطقة ID: " . $_POST['region_id']);
        
        header('Location: regions.php?success=updated');
        exit;
        
    } catch(Exception $e) {
        header('Location: regions.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// حذف منطقة
if(isset($_GET['delete'])) {
    try {
        // التحقق من عدم وجود عملاء
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE region_id = ?");
        $stmt->execute([$_GET['delete']]);
        $count = $stmt->fetchColumn();
        
        if($count > 0) {
            header('Location: regions.php?error=has_customers');
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM regions WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        
        logActivity('حذف منطقة', "تم حذف المنطقة ID: " . $_GET['delete']);
        
        header('Location: regions.php?success=deleted');
        exit;
        
    } catch(Exception $e) {
        header('Location: regions.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}
?>