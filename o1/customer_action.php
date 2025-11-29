<?php
require_once 'config.php';
checkLogin();

// إضافة عميل جديد
if(isset($_POST['add_customer'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO customers (company_name, company_type, owner_name, responsible_person, 
                               phone, email, address, start_date, region_id, has_branches, notes, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        
        $stmt->execute([
            $_POST['company_name'],
            $_POST['company_type'],
            $_POST['owner_name'],
            $_POST['responsible_person'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['email'] ?? null,
            $_POST['address'] ?? null,
            $_POST['start_date'] ?? null,
            $_POST['region_id'],
            isset($_POST['has_branches']) ? 1 : 0,
            $_POST['notes'] ?? null
        ]);
        
        $customer_id = $pdo->lastInsertId();
        
        // إضافة الفروع
        if(isset($_POST['branches']) && is_array($_POST['branches'])) {
            $stmt = $pdo->prepare("INSERT INTO customer_branches (customer_id, branch_name, branch_code, 
                                   region_id, address, phone, manager_name, shop_fronts_count, 
                                   shop_description, google_maps_url, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            
            foreach($_POST['branches'] as $branch) {
                if(!empty($branch['branch_name'])) {
                    $stmt->execute([
                        $customer_id,
                        $branch['branch_name'],
                        $branch['branch_code'] ?? null,
                        $branch['region_id'] ?? null,
                        $branch['address'] ?? null,
                        $branch['phone'] ?? null,
                        $branch['manager_name'] ?? null,
                        $branch['shop_fronts_count'] ?? 1,
                        $branch['shop_description'] ?? null,
                        $branch['google_maps_url'] ?? null
                    ]);
                }
            }
        }
        
        logActivity('إضافة عميل', "تم إضافة العميل: " . $_POST['company_name']);
        
        $pdo->commit();
        header('Location: customers.php?success=added');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header('Location: add_customer.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// تحديث عميل
if(isset($_POST['update_customer'])) {
    try {
        $stmt = $pdo->prepare("UPDATE customers SET 
                               company_name = ?, company_type = ?, owner_name = ?, 
                               responsible_person = ?, phone = ?, email = ?, address = ?, 
                               start_date = ?, region_id = ?, has_branches = ?, notes = ?, status = ?
                               WHERE id = ?");
        
        $stmt->execute([
            $_POST['company_name'],
            $_POST['company_type'],
            $_POST['owner_name'],
            $_POST['responsible_person'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['email'] ?? null,
            $_POST['address'] ?? null,
            $_POST['start_date'] ?? null,
            $_POST['region_id'],
            isset($_POST['has_branches']) ? 1 : 0,
            $_POST['notes'] ?? null,
            $_POST['status'] ?? 'active',
            $_POST['customer_id']
        ]);
        
        logActivity('تحديث عميل', "تم تحديث العميل ID: " . $_POST['customer_id']);
        
        header('Location: view_customer.php?id=' . $_POST['customer_id'] . '&success=updated');
        exit;
        
    } catch(Exception $e) {
        header('Location: edit_customer.php?id=' . $_POST['customer_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// حذف عميل
if(isset($_GET['delete'])) {
    try {
        $id = $_GET['delete'];
        
        $stmt = $pdo->prepare("SELECT company_name FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity('حذف عميل', "تم حذف العميل: " . $customer['company_name']);
        
        header('Location: customers.php?success=deleted');
        exit;
        
    } catch(Exception $e) {
        header('Location: customers.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}
?>