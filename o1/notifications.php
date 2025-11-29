<?php
require_once 'config.php';
require_once 'auth.php';

check_login();

class NotificationSystem {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // إنشاء إشعار جديد
    public function createNotification($user_id, $customer_id, $type, $title, $message, $action_url = '', $priority = 'medium') {
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
    
    // جلب إشعارات المستخدم
    public function getUserNotifications($user_id, $limit = 20, $unread_only = false) {
        $user_id = intval($user_id);
        $unread_filter = $unread_only ? "AND is_read = FALSE" : "";
        
        $sql = "SELECT n.*, c.company_name, c.owner_name 
                FROM notifications n 
                LEFT JOIN customers c ON n.customer_id = c.id 
                WHERE n.user_id = $user_id $unread_filter 
                ORDER BY n.created_at DESC 
                LIMIT $limit";
        
        return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    
    // عدد الإشعارات غير المقروءة
    public function getUnreadCount($user_id) {
        $user_id = intval($user_id);
        $result = $this->conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = FALSE");
        return $result->fetch_assoc()['count'];
    }
    
    // تحديد إشعار كمقروء
    public function markAsRead($notification_id) {
        $notification_id = intval($notification_id);
        return $this->conn->query("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = $notification_id");
    }
    
    // تحديد جميع الإشعارات كمقروءة
    public function markAllAsRead($user_id) {
        $user_id = intval($user_id);
        return $this->conn->query("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE user_id = $user_id AND is_read = FALSE");
    }
    
    // حذف إشعار
    public function deleteNotification($notification_id) {
        $notification_id = intval($notification_id);
        return $this->conn->query("DELETE FROM notifications WHERE id = $notification_id");
    }
    
    // التحقق من المستندات القريبة من الانتهاء
    public function checkExpiringDocuments() {
        $days = $this->getSettingValue('document_expiry_days', 30);
        $check_date = date('Y-m-d', strtotime("+$days days"));
        
        $sql = "SELECT d.*, c.company_name, c.owner_name 
                FROM official_documents d 
                JOIN customers c ON d.customer_id = c.id 
                WHERE d.expiry_date <= '$check_date' 
                AND d.expiry_date >= CURDATE()";
        
        $documents = $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        
        foreach ($documents as $doc) {
            $days_remaining = (strtotime($doc['expiry_date']) - time()) / (60 * 60 * 24);
            
            $title = "⚠️ مستند قريب من الانتهاء";
            $message = "مستند {$doc['document_type']} للعميل {$doc['company_name']} سينتهي خلال " . round($days_remaining) . " يوم";
            $action_url = "customer_documents.php?customer_id={$doc['customer_id']}";
            
            // إرسال إشعار لجميع المديرين
            $users = $this->conn->query("SELECT id FROM users WHERE role IN ('admin', 'manager') AND status = 'active'");
            while ($user = $users->fetch_assoc()) {
                $this->createNotification($user['id'], $doc['customer_id'], 'document_expiry', $title, $message, $action_url, 'high');
            }
        }
    }
    
    // التحقق من المدفوعات المتأخرة
    public function checkOverduePayments() {
        $sql = "SELECT i.*, c.company_name, c.owner_name 
                FROM invoices i 
                JOIN customers c ON i.customer_id = c.id 
                WHERE i.status != 'paid' 
                AND i.remaining_amount > 0 
                AND DATEDIFF(CURDATE(), i.invoice_date) > 30";
        
        $invoices = $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        
        foreach ($invoices as $invoice) {
            $days_overdue = (time() - strtotime($invoice['invoice_date'])) / (60 * 60 * 24);
            
            $title = "💰 دفعة متأخرة";
            $message = "العميل {$invoice['company_name']} لديه فاتورة متأخرة منذ " . round($days_overdue) . " يوم - المبلغ المتبقي: " . number_format($invoice['remaining_amount'], 2) . " ريال";
            $action_url = "customer_details.php?id={$invoice['customer_id']}";
            
            $users = $this->conn->query("SELECT id FROM users WHERE role IN ('admin', 'manager') AND status = 'active'");
            while ($user = $users->fetch_assoc()) {
                $this->createNotification($user['id'], $invoice['customer_id'], 'overdue_payment', $title, $message, $action_url, 'high');
            }
        }
    }
    
    // إرسال تذكير بالدفع
    public function sendPaymentReminder($customer_id) {
        $customer = $this->conn->query("SELECT * FROM customers WHERE id = " . intval($customer_id))->fetch_assoc();
        $unpaid = $this->conn->query("SELECT SUM(remaining_amount) as total FROM invoices WHERE customer_id = " . intval($customer_id) . " AND status != 'paid'")->fetch_assoc();
        
        if ($unpaid['total'] > 0) {
            $title = "📧 تذكير بالدفع";
            $message = "تم إرسال تذكير للعميل {$customer['company_name']} بمبلغ " . number_format($unpaid['total'], 2) . " ريال";
            
            // يمكن إضافة كود إرسال بريد إلكتروني أو رسالة نصية هنا
            // $this->sendEmail($customer['email'], $title, $message);
            // $this->sendSMS($customer['phone'], $message);
            
            $users = $this->conn->query("SELECT id FROM users WHERE status = 'active'");
            while ($user = $users->fetch_assoc()) {
                $this->createNotification($user['id'], $customer_id, 'payment_reminder', $title, $message, "", 'medium');
            }
            
            return true;
        }
        return false;
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
    
    // إرسال بريد إلكتروني (مثال)
    private function sendEmail($to, $subject, $message) {
        // استخدام PHPMailer أو mail() function
        // mail($to, $subject, $message, "From: noreply@example.com");
        return true;
    }
    
    // إرسال رسالة نصية (مثال)
    private function sendSMS($phone, $message) {
        // استخدام API مثل Twilio أو Nexmo
        // curl_post("https://api.sms.com/send", ['phone' => $phone, 'message' => $message]);
        return true;
    }
}

// معالجة الطلبات
$notif = new NotificationSystem($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $notif->markAsRead($_POST['notification_id']);
        echo json_encode(['success' => true]);
        exit();
    }
    
    if (isset($_POST['mark_all_read'])) {
        $notif->markAllAsRead($_SESSION['user_id']);
        echo json_encode(['success' => true]);
        exit();
    }
    
    if (isset($_POST['delete'])) {
        $notif->deleteNotification($_POST['notification_id']);
        echo json_encode(['success' => true]);
        exit();
    }
}

// جلب الإشعارات
$notifications = $notif->getUserNotifications($_SESSION['user_id']);
$unread_count = $notif->getUnreadCount($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإشعارات</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .notification-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-right: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateX(-5px);
        }
        
        .notification-item.unread {
            background: #f0f4ff;
            border-right-color: #dc3545;
        }
        
        .notification-item.high-priority {
            border-right-color: #dc3545;
            border-right-width: 6px;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .notification-title {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
        }
        
        .notification-time {
            font-size: 0.85em;
            color: #999;
        }
        
        .notification-message {
            color: #666;
            line-height: 1.6;
            margin: 10px 0;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔔 الإشعارات</h1>
            <p>إدارة الإشعارات والتنبيهات</p>
        </header>

        <?php display_user_bar(); ?>

        <div class="card">
            <a href="index.php" class="btn btn-info">⬅️ العودة للصفحة الرئيسية</a>
            <button class="btn btn-success" onclick="markAllAsRead()">✅ تحديد الكل كمقروء</button>
            <button class="btn btn-warning" onclick="runChecks()">🔄 تحديث الإشعارات</button>
        </div>

        <div class="card">
            <h2>الإشعارات (<?php echo $unread_count; ?> غير مقروء)</h2>
            
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notif_item): ?>
                    <div class="notification-item <?php echo !$notif_item['is_read'] ? 'unread' : ''; ?> <?php echo $notif_item['priority'] == 'high' ? 'high-priority' : ''; ?>" id="notif_<?php echo $notif_item['id']; ?>">
                        <div class="notification-header">
                            <div class="notification-title">
                                <?php echo htmlspecialchars($notif_item['title']); ?>
                                <?php if (!$notif_item['is_read']): ?>
                                    <span class="badge badge-danger">جديد</span>
                                <?php endif; ?>
                            </div>
                            <div class="notification-time">
                                <?php 
                                $time_diff = time() - strtotime($notif_item['created_at']);
                                if ($time_diff < 3600) {
                                    echo round($time_diff / 60) . ' دقيقة';
                                } elseif ($time_diff < 86400) {
                                    echo round($time_diff / 3600) . ' ساعة';
                                } else {
                                    echo round($time_diff / 86400) . ' يوم';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="notification-message">
                            <?php echo nl2br(htmlspecialchars($notif_item['message'])); ?>
                        </div>
                        
                        <?php if ($notif_item['company_name']): ?>
                            <div style="margin-top: 10px;">
                                <span class="badge badge-info">
                                    👤 <?php echo htmlspecialchars($notif_item['company_name']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="notification-actions">
                            <?php if ($notif_item['action_url']): ?>
                                <a href="<?php echo htmlspecialchars($notif_item['action_url']); ?>" class="btn btn-primary btn-sm">عرض التفاصيل</a>
                            <?php endif; ?>
                            
                            <?php if (!$notif_item['is_read']): ?>
                                <button class="btn btn-success btn-sm" onclick="markAsRead(<?php echo $notif_item['id']; ?>)">✅ تحديد كمقروء</button>
                            <?php endif; ?>
                            
                            <button class="btn btn-danger btn-sm" onclick="deleteNotification(<?php echo $notif_item['id']; ?>)">🗑️ حذف</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    لا توجد إشعارات
                </div>
            <?php endif; ?>
        </div>

        <footer>
            <p>&copy; 2025 نظام إدارة العملاء - جميع الحقوق محفوظة</p>
        </footer>
    </div>

    <script>
        function markAsRead(id) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'mark_read=1&notification_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('notif_' + id).classList.remove('unread');
                    location.reload();
                }
            });
        }

        function markAllAsRead() {
            fetch('notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'mark_all_read=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function deleteNotification(id) {
            if (confirm('هل أنت متأكد من الحذف؟')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'delete=1&notification_id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('notif_' + id).remove();
                    }
                });
            }
        }

        function runChecks() {
            window.location.href = 'cron_notifications.php?manual=1';
        }
    </script>
</body>
</html>