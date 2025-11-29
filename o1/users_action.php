<?php
require_once 'config.php';
checkAdmin();

// إضافة مستخدم
if(isset($_POST['add_user'])) {
    try {
        // التحقق من عدم تكرار اسم المستخدم
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$_POST['username']]);
        
        if($stmt->fetch()) {
            header('Location: users.php?error=username_exists');
            exit;
        }
        
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['username'],
            $hashedPassword,
            $_POST['full_name'],
            $_POST['email'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['role'],
            $_POST['status'] ?? 'active'
        ]);
        
        logActivity('إضافة مستخدم', "تم إضافة المستخدم: " . $_POST['username']);
        
        header('Location: users.php?success=added');
        exit;
        
    } catch(Exception $e) {
        header('Location: users.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// تحديث مستخدم
if(isset($_POST['update_user'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET 
                               full_name = ?, email = ?, phone = ?, role = ?, status = ?
                               WHERE id = ?");
        
        $stmt->execute([
            $_POST['full_name'],
            $_POST['email'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['role'],
            $_POST['status'],
            $_POST['user_id']
        ]);
        
        logActivity('تحديث مستخدم', "تم تحديث المستخدم ID: " . $_POST['user_id']);
        
        header('Location: users.php?success=updated');
        exit;
        
    } catch(Exception $e) {
        header('Location: edit_user.php?id=' . $_POST['user_id'] . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// إعادة تعيين كلمة المرور
if(isset($_GET['reset_password'])) {
    try {
        $userId = $_GET['reset_password'];
        $newPassword = '123456';
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        logActivity('إعادة تعيين كلمة مرور', "تم إعادة تعيين كلمة مرور المستخدم ID: " . $userId);
        
        header('Location: users.php?success=password_reset&password=' . $newPassword);
        exit;
        
    } catch(Exception $e) {
        header('Location: users.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// حذف مستخدم
if(isset($_GET['delete'])) {
    try {
        $userId = $_GET['delete'];
        
        if($userId == $_SESSION['user_id']) {
            header('Location: users.php?error=cannot_delete_self');
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        logActivity('حذف مستخدم', "تم حذف المستخدم ID: " . $userId);
        
        header('Location: users.php?success=deleted');
        exit;
        
    } catch(Exception $e) {
        header('Location: users.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}
?>