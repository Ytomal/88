<?php
require_once 'config.php';
checkLogin();

// جدولة زيارة جديدة
if(isset($_POST['schedule_visit'])) {
    try {
        $pdo->beginTransaction();
        
        // إدراج الزيارة المجدولة
        $stmt = $pdo->prepare("INSERT INTO scheduled_visits 
                               (customer_id, branch_id, visit_date, visit_time, visit_type, 
                                purpose, reminder_before, notes, scheduled_by)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['branch_id'] ?? null,
            $_POST['visit_date'],
            $_POST['visit_time'],
            $_POST['visit_type'] ?? null,
            $_POST['purpose'] ?? null,
            $_POST['reminder_before'] ?? 24,
            $_POST['notes'] ?? null,
            $_SESSION['user_id']
        ]);
        
        $visit_id = $pdo->lastInsertId();
        
        // حساب وقت التنبيه
        $visit_datetime = $_POST['visit_date'] . ' ' . $_POST['visit_time'];
        $reminder_hours = intval($_POST['reminder_before'] ?? 24);
        $reminder_time = date('Y-m-d H:i:s', strtotime($visit_datetime) - ($reminder_hours * 3600));
        
        // إنشاء التنبيه
        $stmt = $pdo->prepare("INSERT INTO visit_reminders (scheduled_visit_id, reminder_time)
                               VALUES (?, ?)");
        $stmt->execute([$visit_id, $reminder_time]);
        
        logActivity('جدولة زيارة', "تم جدولة زيارة للعميل ID: {$_POST['customer_id']}");
        
        $pdo->commit();
        header("Location: customer_visits_schedule.php?id={$_POST['customer_id']}&success=scheduled");
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header("Location: customer_visits_schedule.php?id={$_POST['customer_id']}&error=" . urlencode($e->getMessage()));
        exit;
    }
}

// إكمال زيارة
if(isset($_POST['complete_visit'])) {
    try {
        $pdo->beginTransaction();
        
        // تحديث حالة الزيارة المجدولة
        $stmt = $pdo->prepare("UPDATE scheduled_visits 
                               SET status = 'completed', 
                                   completion_report = ?, 
                                   completed_at = NOW()
                               WHERE id = ?");
        $stmt->execute([$_POST['report'], $_POST['visit_id']]);
        
        // جلب بيانات الزيارة
        $stmt = $pdo->prepare("SELECT * FROM scheduled_visits WHERE id = ?");
        $stmt->execute([$_POST['visit_id']]);
        $visit = $stmt->fetch();
        
        // إضافة الزيارة إلى سجل الزيارات المنفذة
        $stmt = $pdo->prepare("INSERT INTO visits 
                               (customer_id, branch_id, visit_date, visit_time, visit_type, 
                                notes, report, created_by)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $visit['customer_id'],
            $visit['branch_id'],
            $visit['visit_date'],
            $visit['visit_time'],
            $visit['visit_type'],
            $visit['notes'],
            $_POST['report'],
            $_SESSION['full_name']
        ]);
        
        logActivity('إكمال زيارة', "تم إكمال زيارة مجدولة ID: {$_POST['visit_id']}");
        
        $pdo->commit();
        header("Location: customer_visits_schedule.php?id={$visit['customer_id']}&success=completed");
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header("Location: customer_visits_schedule.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// تأجيل زيارة
if(isset($_GET['postpone'])) {
    try {
        $visit_id = $_GET['postpone'];
        $days = intval($_GET['days'] ?? 1);
        
        $pdo->beginTransaction();
        
        // جلب بيانات الزيارة
        $stmt = $pdo->prepare("SELECT * FROM scheduled_visits WHERE id = ?");
        $stmt->execute([$visit_id]);
        $visit = $stmt->fetch();
        
        // حساب التاريخ الجديد
        $new_date = date('Y-m-d', strtotime($visit['visit_date'] . " +$days days"));
        
        // تحديث الزيارة
        $stmt = $pdo->prepare("UPDATE scheduled_visits 
                               SET visit_date = ?, 
                                   status = 'postponed'
                               WHERE id = ?");
        $stmt->execute([$new_date, $visit_id]);
        
        // تحديث التنبيه
        $visit_datetime = $new_date . ' ' . $visit['visit_time'];
        $reminder_time = date('Y-m-d H:i:s', strtotime($visit_datetime) - ($visit['reminder_before'] * 3600));
        
        $stmt = $pdo->prepare("UPDATE visit_reminders 
                               SET reminder_time = ?, sent = 0, sent_at = NULL
                               WHERE scheduled_visit_id = ?");
        $stmt->execute([$reminder_time, $visit_id]);
        
        logActivity('تأجيل زيارة', "تم تأجيل الزيارة ID: $visit_id إلى $new_date");
        
        $pdo->commit();
        header("Location: customer_visits_schedule.php?id={$visit['customer_id']}&success=postponed");
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        header("Location: customer_visits_schedule.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// حذف زيارة مجدولة
if(isset($_GET['delete'])) {
    try {
        $visit_id = $_GET['delete'];
        $customer_id = $_GET['customer_id'];
        
        $stmt = $pdo->prepare("UPDATE scheduled_visits SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$visit_id]);
        
        logActivity('إلغاء زيارة', "تم إلغاء الزيارة المجدولة ID: $visit_id");
        
        header("Location: customer_visits_schedule.php?id=$customer_id&success=deleted");
        exit;
        
    } catch(Exception $e) {
        header("Location: customer_visits_schedule.php?id={$_GET['customer_id']}&error=" . urlencode($e->getMessage()));
        exit;
    }
}

// إذا لم تكن هناك إجراءات صحيحة
header('Location: customers.php');
exit;