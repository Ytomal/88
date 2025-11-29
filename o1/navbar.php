<?php
// القائمة الجانبية الرئيسية
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'employee';

// حساب الإشعارات غير المقروءة
$unread_notifications = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $notif_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = FALSE");
    if ($notif_result) {
        $unread_notifications = $notif_result->fetch_assoc()['count'];
    }
}
?>

<!-- السايد بار الرئيسي -->
<aside class="main-sidebar">
    <!-- رأس السايد بار -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div style="width: 40px; height: 40px; background: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                🚗
            </div>
            <div class="sidebar-logo-text">
                <h3>نظام إدارة العملاء</h3>
                <p>الإطارات والبطاريات</p>
            </div>
        </div>
    </div>

    <!-- قائمة الروابط -->
    <div class="sidebar-menu">
        <!-- القسم الرئيسي -->
        <div class="menu-section">
            <div class="menu-section-title">القائمة الرئيسية</div>
            
            <div class="menu-item">
                <a href="dashboard.php" class="menu-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    <span>لوحة التحكم</span>
                </a>
            </div>

            <div class="menu-item">
                <a href="customers.php" class="menu-link <?php echo $current_page == 'customers.php' ? 'active' : ''; ?>">
                    <span class="icon">👥</span>
                    <span>العملاء</span>
                </a>
            </div>

            <div class="menu-item">
                <a href="invoices.php" class="menu-link <?php echo $current_page == 'invoices.php' ? 'active' : ''; ?>">
                    <span class="icon">💰</span>
                    <span>الفواتير</span>
                </a>
            </div>

            <div class="menu-item">
                <a href="payments.php" class="menu-link <?php echo $current_page == 'payments.php' ? 'active' : ''; ?>">
                    <span class="icon">💵</span>
                    <span>الدفعات</span>
                </a>
            </div>

            <div class="menu-item">
                <a href="products.php" class="menu-link <?php echo $current_page == 'products.php' ? 'active' : ''; ?>">
                    <span class="icon">📦</span>
                    <span>المنتجات</span>
                </a>
            </div>
        </div>

        <!-- قسم الإدارة -->
        <?php if (in_array($user_role, ['admin', 'manager'])): ?>
        <div class="menu-section">
            <div class="menu-section-title">الإدارة</div>
            
            <div class="menu-item">
                <a href="branches.php" class="menu-link <?php echo $current_page == 'branches.php' ? 'active' : ''; ?>">
                    <span class="icon">🏢</span>
                    <span>الفروع</span>
                </a>
            </div>

            <div class="menu-item">
                <a href="regions.php" class="menu-link <?php echo $current_page == 'regions.php' ? 'active' : ''; ?>">
                    <span class="icon">📍</span>
                    <span>المناطق</span>
                </a>
            </div>

            <div class="menu-item">
                <a href="users.php" class="menu-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                    <span class="icon">👤</span>
                    <span>المستخدمين</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- قسم التقارير -->
        <div class="menu-section">
            <div class="menu-section-title">التقارير</div>
            
            <div class="menu-item">
                <a href="reports.php" class="menu-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                    <span class="icon">📈</span>
                    <span>التقارير المالية</span>
                </a>
            </div>

            <div class="menu-item">
                <a href="statistics.php" class="menu-link <?php echo $current_page == 'statistics.php' ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    <span>الإحصائيات</span>
                </a>
            </div>
        </div>

        <!-- قسم الإعدادات -->
        <?php if ($user_role == 'admin'): ?>
        <div class="menu-section">
            <div class="menu-section-title">النظام</div>
            
            <div class="menu-item">
                <a href="settings.php" class="menu-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                    <span class="icon">⚙️</span>
                    <span>الإعدادات</span>
                </a>
            </div>

            <div class="menu-item">
                <a href="activity_log.php" class="menu-link <?php echo $current_page == 'activity_log.php' ? 'active' : ''; ?>">
                    <span class="icon">📝</span>
                    <span>سجل الأنشطة</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</aside>

<!-- الشريط العلوي -->
<nav class="top-navbar">
    <div class="navbar-left">
        <button class="navbar-icon-btn" id="sidebarToggle">
            <span>☰</span>
        </button>
        
        <div class="breadcrumb">
            <a href="dashboard.php">الرئيسية</a>
            <span>/</span>
            <span>العملاء</span>
        </div>
    </div>

    <div class="navbar-right">
        <!-- الإشعارات -->
        <button class="navbar-icon-btn" onclick="window.location.href='notifications.php'">
            <span>🔔</span>
            <?php if ($unread_notifications > 0): ?>
                <span class="badge badge-danger"><?php echo $unread_notifications; ?></span>
            <?php endif; ?>
        </button>

        <!-- البحث -->
        <button class="navbar-icon-btn" onclick="openSearchModal()">
            <span>🔍</span>
        </button>

        <!-- قائمة المستخدم -->
        <div class="user-menu" onclick="toggleUserMenu()">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name'] ?? 'مستخدم'; ?></div>
                <div class="user-role">
                    <?php 
                    $roles = ['admin' => 'مدير', 'manager' => 'مدير', 'accountant' => 'محاسب', 'sales' => 'مبيعات', 'employee' => 'موظف'];
                    echo $roles[$user_role] ?? 'موظف';
                    ?>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
// Toggle Sidebar
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.querySelector('.main-sidebar').classList.toggle('show');
});

// Toggle User Menu
function toggleUserMenu() {
    // يمكن إضافة dropdown menu هنا
    if (confirm('هل تريد تسجيل الخروج؟')) {
        window.location.href = 'logout.php';
    }
}

// فتح نافذة البحث
function openSearchModal() {
    alert('نافذة البحث قيد التطوير');
}
</script>