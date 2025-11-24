<?php
// sidebar.php - القائمة الجانبية
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// الحصول على عدد الإشعارات غير المقروءة
$unread_notifications = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetchColumn();
} catch(Exception $e) {
    $unread_notifications = 0;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    body { margin: 0; padding: 0; }
    
    .sidebar {
        width: 260px;
        height: 100vh;
        position: fixed;
        right: 0;
        top: 0;
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        z-index: 1000;
        overflow-y: auto;
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 10px; }
    
    .sidebar-header {
        padding: 25px 20px;
        background: rgba(0,0,0,0.2);
        color: white;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-header h4 { margin: 0; font-size: 20px; font-weight: bold; }
    .sidebar-header small { opacity: 0.8; display: block; margin-top: 5px; }
    
    .sidebar-menu { padding: 15px 0; }
    
    .menu-item { padding: 0; margin: 3px 10px; }
    
    .menu-item a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: rgba(255,255,255,0.9);
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s;
        position: relative;
    }
    
    .menu-item a:hover {
        background: rgba(255,255,255,0.15);
        color: white;
        transform: translateX(-3px);
    }
    
    .menu-item a.active {
        background: rgba(255,255,255,0.2);
        color: white;
        font-weight: 600;
    }
    
    .menu-item a i {
        width: 25px;
        font-size: 18px;
        margin-left: 12px;
    }
    
    .menu-badge {
        position: absolute;
        left: 15px;
        background: #dc3545;
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .menu-section {
        padding: 15px 20px 8px;
        color: rgba(255,255,255,0.6);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .sidebar-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 15px;
        background: rgba(0,0,0,0.2);
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        margin-bottom: 10px;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #667eea;
        font-weight: bold;
        flex-shrink: 0;
    }
    
    .user-details { flex: 1; color: white; min-width: 0; }
    .user-details .name { font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-details .role { font-size: 11px; opacity: 0.8; }
    
    .main-content {
        margin-right: 260px;
        padding: 25px;
        min-height: 100vh;
        background: #f5f7fa;
    }
    
    .logout-btn {
        width: 100%;
        padding: 10px;
        background: rgba(255,255,255,0.1);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 8px;
        text-align: center;
        text-decoration: none;
        display: block;
        transition: all 0.3s;
    }
    
    .logout-btn:hover {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(100%);
            transition: transform 0.3s;
        }
        
        .sidebar.show { transform: translateX(0); }
        
        .main-content { margin-right: 0; }
        
        .mobile-toggle {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 999;
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
    }
</style>

<!-- زر القائمة للموبايل -->
<button class="mobile-toggle d-md-none" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- القائمة الجانبية -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-building fa-2x mb-2"></i>
        <h4>نظام إدارة العملاء</h4>
        <small>الإصدار 2.0</small>
    </div>

    <div class="sidebar-menu">
        <div class="menu-section">القائمة الرئيسية</div>
        
        <div class="menu-item">
            <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                <span>الرئيسية</span>
            </a>
        </div>

        <div class="menu-item">
            <a href="customers.php" class="<?= $current_page == 'customers.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>العملاء</span>
            </a>
        </div>

        <div class="menu-item">
            <a href="regions.php" class="<?= $current_page == 'regions.php' ? 'active' : '' ?>">
                <i class="fas fa-map-marker-alt"></i>
                <span>المناطق</span>
            </a>
        </div>

        <div class="menu-section">المالية</div>

        <div class="menu-item">
            <a href="invoices.php" class="<?= $current_page == 'invoices.php' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice"></i>
                <span>الفواتير</span>
            </a>
        </div>

        <div class="menu-item">
            <a href="payments.php" class="<?= $current_page == 'payments.php' ? 'active' : '' ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>المدفوعات</span>
            </a>
        </div>

        <div class="menu-item">
            <a href="statistics.php" class="<?= $current_page == 'statistics.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span>الإحصائيات</span>
            </a>
        </div>

        <div class="menu-section">الإدارة</div>

        <div class="menu-item">
            <a href="notifications.php" class="<?= $current_page == 'notifications.php' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i>
                <span>الإشعارات</span>
                <?php if($unread_notifications > 0): ?>
                    <span class="menu-badge"><?= $unread_notifications ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if($_SESSION['role'] == 'admin'): ?>
        <div class="menu-item">
            <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i>
                <span>المستخدمين</span>
            </a>
        </div>
        <?php endif; ?>

        <div class="menu-section">إضافات</div>

        <div class="menu-item">
            <a href="add_customer.php" class="<?= $current_page == 'add_customer.php' ? 'active' : '' ?>">
                <i class="fas fa-user-plus"></i>
                <span>إضافة عميل</span>
            </a>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?= mb_substr($_SESSION['full_name'], 0, 1) ?>
            </div>
            <div class="user-details">
                <div class="name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="role">
                    <?php
                    $roles = [
                        'admin' => 'مدير النظام',
                        'manager' => 'مدير مبيعات',
                        'employee' => 'موظف'
                    ];
                    echo $roles[$_SESSION['role']] ?? 'مستخدم';
                    ?>
                </div>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
        </a>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
    }

    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.querySelector('.mobile-toggle');
        
        if(window.innerWidth < 768) {
            if(!sidebar.contains(event.target) && toggle && !toggle.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
</script>