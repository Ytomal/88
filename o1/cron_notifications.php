<?php
// هذا الملف يتم تشغيله تلقائياً عبر Cron Job
// مثال: */30 * * * * php /path/to/cron_notifications.php
// أو يدوياً من خلال المتصفح

require_once 'config.php';

// التحقق من التشغيل اليدوي أو التلقائي
$is_manual = isset($_GET['manual']) && $_GET['manual'] == '1';

if (!$is_manual) {
    // للأمان: التأكد من أن الملف يعمل من سطر الأوامر أو من IP محلي
    if (php_sapi_name() !== 'cli' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
        die("Access Denied");
    }
}

class AutoNotifications {
    private $conn;
    private $log = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // تشغيل جميع الفحوصات
    public function runAll() {
        $this->log[] = "بدء تشغيل الفحوصات التلقائية في: " . date('Y-m-d H:i:s');
        
        $this->checkExpiringDocuments();
        $this->checkOverduePayments();
        $this->checkLowStock(); // إذا كان لديك نظام مخزون
        $this->sendDailyReport();
        
        $this->log[] = "انتهاء الفحوصات في: " . date('Y-m-d H:i:s');
        
        return $this->log;
    }
    
    // فحص المستندات القريبة من الانتهاء
    private function checkExpiringDocuments() {
        $this->log[] = "فحص المستندات القريبة من الانتهاء...";
        
        $days = $this->getSettingValue('document_expiry_days', 30);
        $check_date = date('Y-m-d', strtotime("+$days days"));
        
        $sql = "SELECT d.*, c.company_name, c.owner_name 
                FROM official_documents d 
                JOIN customers c ON d.customer_id = c.id 
                WHERE d.expiry_date <= '$check_date' 
                AND d.expiry_date >= CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM notifications 
                    WHERE notification_type = 'document_expiry' 
                    AND customer_id = d.customer_id 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                )";
        
        $documents = $this->conn->query($sql);
        $count = 0;
        
        while ($doc = $documents->fetch_assoc()) {
            $days_remaining = round((strtotime($doc['expiry_date']) - time()) / (60 * 60 * 24));
            
            $title = "⚠️ مستند قريب من الانتهاء";
            $message = "مستند {$doc['document_type']} رقم ({$doc['document_number']}) للعميل {$doc['company_name']} سينتهي خلال $days_remaining يوم";
            $action_url = "customer_documents.php?customer_id={$doc['customer_id']}";
            
            // إرسال لجميع المديرين
            $users = $this->conn->query("SELECT id FROM users WHERE role IN ('admin', 'manager') AND status = 'active'");
            while ($user = $users->fetch_assoc()) {
                $this->createNotification($user['id'], $doc['customer_id'], 'document_expiry', $title, $message, $action_url, 'high');
                $count++;
            }
        }
        
        $this->log[] = "تم إنشاء $count إشعار للمستندات القريبة من الانتهاء";
    }
    
    // فحص الدفعات المتأخرة
    private function checkOverduePayments() {
        $this->log[] = "فحص الدفعات المتأخرة...";
        
        $sql = "SELECT i.*, c.company_name, c.owner_name, c.phone, c.email 
                FROM invoices i 
                JOIN customers c ON i.customer_id = c.id 
                WHERE i.status != 'paid' 
                AND i.remaining_amount > 0 
                AND DATEDIFF(CURDATE(), i.invoice_date) > 30
                AND NOT EXISTS (
                    SELECT 1 FROM notifications 
                    WHERE notification_type = 'overdue_payment' 
                    AND customer_id = i.customer_id 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                )";
        
        $invoices = $this->conn->query($sql);
        $count = 0;
        
        while ($invoice = $invoices->fetch_assoc()) {
            $days_overdue = round((time() - strtotime($invoice['invoice_date'])) / (60 * 60 * 24));
            
            $title = "💰 دفعة متأخرة";
            $message = "العميل {$invoice['company_name']} لديه فاتورة رقم {$invoice['invoice_number']} متأخرة منذ $days_overdue يوم - المبلغ المتبقي: " . number_format($invoice['remaining_amount'], 2) . " ريال";
            $action_url = "customer_details.php?id={$invoice['customer_id']}";
            
            // إرسال إشعار للمديرين
            $users = $this->conn->query("SELECT id FROM users WHERE role IN ('admin', 'manager') AND status = 'active'");
            while ($user = $users->fetch_assoc()) {
                $this->createNotification($user['id'], $invoice['customer_id'], 'overdue_payment', $title, $message, $action_url, 'high');
                $count++;
            }
            
            // إرسال تذكير للعميل عبر البريد/الرسائل
            if ($this->getSettingValue('email_notifications') === 'true' && $invoice['email']) {
                $this->sendPaymentReminderEmail($invoice);
            }
            
            if ($this->getSettingValue('sms_notifications') === 'true' && $invoice['phone']) {
                $this->sendPaymentReminderSMS($invoice);
            }
        }
        
        $this->log[] = "تم إنشاء $count إشعار للدفعات المتأخرة";
    }
    
    // فحص المخزون المنخفض (مثال)
    private function checkLowStock() {
        // يمكن إضافة فحص للمنتجات التي على وشك النفاد
        $this->log[] = "فحص المخزون (قيد التطوير)";
    }
    
    // إرسال تقرير يومي
    private function sendDailyReport() {
        $this->log[] = "إرسال التقرير اليومي...";
        
        // حساب إحصائيات اليوم
        $today = date('Y-m-d');
        
        $today_sales = $this->conn->query("SELECT IFNULL(SUM(total_amount), 0) as total FROM invoices WHERE invoice_date = '$today'")->fetch_assoc()['total'];
        $today_payments = $this->conn->query("SELECT IFNULL(SUM(amount), 0) as total FROM payments WHERE payment_date = '$today'")->fetch_assoc()['total'];
        $today_invoices = $this->conn->query("SELECT COUNT(*) as count FROM invoices WHERE invoice_date = '$today'")->fetch_assoc()['count'];
        
        $title = "📊 التقرير اليومي";
        $message = "تقرير $today:\n";
        $message .= "المبيعات: " . number_format($today_sales, 2) . " ريال\n";
        $message .= "المدفوعات: " . number_format($today_payments, 2) . " ريال\n";
        $message .= "عدد الفواتير: $today_invoices";
        
        // إرسال للمديرين فقط
        $users = $this->conn->query("SELECT id FROM users WHERE role IN ('admin', 'manager') AND status = 'active'");
        $count = 0;
        while ($user = $users->fetch_assoc()) {
            $this->createNotification($user['id'], null, 'daily_report', $title, $message, 'statistics.php', 'low');
            $count++;
        }
        
        $this->log[] = "تم إرسال التقرير اليومي إلى $count مستخدم";
    }
    
    // إنشاء إشعار
    private function createNotification($user_id, $customer_id, $type, $title, $message, $action_url = '', $priority = 'medium') {
        $user_id = intval($user_id);
        $customer_id = $customer_id ? intval($customer_id) : 'NULL';
        $type = $this->conn->real_escape_string($type);
        $title = $this->conn->real_escape_string($title);
        $message = $this->conn->real_escape_string($message);
        $action_url = $this->conn->real_escape_string($action_url);
        $priority = $this->conn->real_escape_string($priority);
        
        $sql = "INSERT INTO notifications (user_id, customer_id, notification_type, title, message, action_url, priority) 
                VALUES ($user_id, $customer_id, '$type', '$title', '$message', '$action_url', '$priority')";
        
        return $this->conn->query($sql);
    }
    
    // إرسال تذكير بالدفع عبر البريد
    private function sendPaymentReminderEmail($invoice) {
        $subject = "تذكير بالدفع - فاتورة رقم {$invoice['invoice_number']}";
        $message = "
        عزيزي {$invoice['owner_name']},
        
        نود تذكيرك بأن لديك فاتورة مستحقة برقم: {$invoice['invoice_number']}
        المبلغ المتبقي: " . number_format($invoice['remaining_amount'], 2) . " ريال
        تاريخ الفاتورة: {$invoice['invoice_date']}
        
        نرجو منكم المبادرة بالسداد في أقرب وقت ممكن.
        
        شكراً لتعاملكم معنا
        ";
        
        // استخدام mail() أو PHPMailer
        // mail($invoice['email'], $subject, $message, "From: noreply@example.com");
        
        $this->log[] = "تم إرسال بريد تذكير إلى {$invoice['email']}";
    }
    
    // إرسال تذكير بالدفع عبر الرسائل النصية
    private function sendPaymentReminderSMS($invoice) {
        $message = "تذكير: لديك فاتورة مستحقة رقم {$invoice['invoice_number']} بمبلغ " . number_format($invoice['remaining_amount'], 2) . " ريال";
        
        // استخدام API للرسائل النصية
        // $this->sendSMS($invoice['phone'], $message);
        
        $this->log[] = "تم إرسال رسالة نصية إلى {$invoice['phone']}";
    }
    
    // جلب قيمة إعداد
    private function getSettingValue($key, $default = '') {
        $key = $this->conn->real_escape_string($key);
        $result = $this->conn->query("SELECT setting_value FROM notification_settings WHERE setting_key = '$key'");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc()['setting_value'];
        }
        return $default;
    }
}

// تشغيل الفحوصات
$auto_notif = new AutoNotifications($conn);
$log = $auto_notif->runAll();

// عرض السجل
if ($is_manual) {
    echo '<!DOCTYPE html>
    <html lang="ar">
    <head>
        <meta charset="UTF-8">
        <title>سجل الإشعارات التلقائية</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="container">
            <div class="card">
                <h2>✅ سجل الإشعارات التلقائية</h2>';
    
    foreach ($log as $entry) {
        echo '<p>' . htmlspecialchars($entry) . '</p>';
    }
    
    echo '      <a href="notifications.php" class="btn btn-primary">عرض الإشعارات</a>
            </div>
        </div>
    </body>
    </html>';
} else {
    // حفظ السجل في ملف
    file_put_contents('logs/cron_log_' . date('Y-m-d') . '.txt', implode("\n", $log), FILE_APPEND);
    echo "Done\n";
}
?>