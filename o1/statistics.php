<?php
// sidebar.php - مصحح حسب الملفات الموجودة
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// جلب عدد الإشعارات غير المقروءة
$unread_notifications = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetchColumn();
} catch(Exception $e) {
    $unread_notifications = 0;
}
?>
<style>
.sidebar {
    position: fixed;
    right: 0;
    top: 0;
    width: 260px;
    height: 100vh;
    background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
    padding: 20px 0;
    overflow-y: auto;
    box-shadow: -2px 0 10px rgba(0,0,0,0.1);
    z-index: 1000;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 3px;
}

.sidebar-header {
    padding: 0 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    margin-bottom: 20px;
}

.sidebar-header h3 {
    color: white;
    font-size: 18px;
    margin: 0 0 5px 0;
}

.sidebar-header .user-info {
    color: rgba(255,255,255,0.7);
    font-size: 13px;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.menu-item {
    margin-bottom: 2px;
}

.menu-link {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: all 0.3s;
    position: relative;
}

.menu-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    padding-right: 25px;
}

.menu-link.active {
    background: rgba(52, 152, 219, 0.3);
    color: white;
    border-right: 4px solid #3498db;
}

.menu-link i {
    width: 24px;
    margin-left: 12px;
    text-align: center;
}

.menu-badge {
    margin-right: auto;
    background: #e74c3c;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: bold;
}

.menu-section {
    padding: 15px 20px 5px;
    color: rgba(255,255,255,0.5);
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.submenu {
    display: none;
    list-style: none;
    padding: 0;
    margin: 0;
    background: rgba(0,0,0,0.2);
}

.submenu.active {
    display: block;
}

.submenu .menu-link {
    padding-right: 45px;
    font-size: 14px;
}

.submenu .menu-link i {
    font-size: 12px;
}

.menu-toggle {
    cursor: pointer;
}

.menu-toggle::after {
    content: '\f078';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    left: 20px;
    transition: transform 0.3s;
}

.menu-toggle.active::after {
    transform: rotate(180deg);
}
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <h3>📊 إدارة العملاء</h3>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['full_name']) ?>
            <br>
            <small><?= $_SESSION['role'] == 'admin' ? 'مدير' : 'موظف' ?></small>
        </div>
    </div>

    <ul class="sidebar-menu">
        <!-- الرئيسية -->
        <li class="menu-item">
            <a href="index.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                <span>الرئيسية</span>
            </a>
        </li>

        <!-- الإشعارات -->
        <li class="menu-item">
            <a href="notifications.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i>
                <span>الإشعارات</span>
                <?php if($unread_notifications > 0): ?>
                    <span class="menu-badge"><?= $unread_notifications ?></span>
                <?php endif; ?>
            </a>
        </li>

        <div class="menu-section">إدارة العملاء</div>

        <!-- العملاء -->
        <li class="menu-item">
            <a href="customers.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>العملاء</span>
            </a>
        </li>

        <!-- زيارات العملاء -->
        <li class="menu-item">
            <a href="#" class="menu-link menu-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['scheduled_visits.php', 'customer_visits.php', 'customer_visits_schedule.php']) ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i>
                <span>زيارات العملاء</span>
            </a>
            <ul class="submenu <?= in_array(basename($_SERVER['PHP_SELF']), ['scheduled_visits.php', 'customer_visits.php', 'customer_visits_schedule.php']) ? 'active' : '' ?>">
                <li class="menu-item">
                    <a href="scheduled_visits.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'scheduled_visits.php' ? 'active' : '' ?>">
                        <i class="fas fa-clock"></i>
                        <span>جدولة زيارات</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="customer_visits.php" class="menu-link <?= in_array(basename($_SERVER['PHP_SELF']), ['customer_visits.php', 'customer_visits_schedule.php']) ? 'active' : '' ?>">
                        <i class="fas fa-list"></i>
                        <span>سجل الزيارات</span>
                    </a>
                </li>
            </ul>
        </li>

        <!-- المستندات الرسمية -->
        <li class="menu-item">
            <a href="customer_documents.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'customer_documents.php' ? 'active' : '' ?>">
                <i class="fas fa-file-contract"></i>
                <span>المستندات الرسمية</span>
            </a>
        </li>

        <!-- المقاسات والإحصائيات -->
        <li class="menu-item">
            <a href="customer_sizes.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'customer_sizes.php' ? 'active' : '' ?>">
                <i class="fas fa-ruler"></i>
                <span>المقاسات والإحصائيات</span>
            </a>
        </li>

        <div class="menu-section">المبيعات والمالية</div>

        <!-- الفواتير -->
        <li class="menu-item">
            <a href="invoices.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice"></i>
                <span>الفواتير</span>
            </a>
        </li>

        <!-- الدفعات -->
        <li class="menu-item">
            <a href="payments.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : '' ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>الدفعات</span>
            </a>
        </li>

        <div class="menu-section">التقارير</div>

        <!-- الإحصائيات والتقارير -->
        <li class="menu-item">
            <a href="statistics.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span>الإحصائيات والتقارير</span>
            </a>
        </li>

        <div class="menu-section">الإعدادات</div>

        <!-- المناطق -->
        <li class="menu-item">
            <a href="regions.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'regions.php' ? 'active' : '' ?>">
                <i class="fas fa-map-marked-alt"></i>
                <span>المناطق</span>
            </a>
        </li>

        <!-- فروعنا -->
        <li class="menu-item">
            <a href="branches.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'branches.php' ? 'active' : '' ?>">
                <i class="fas fa-store"></i>
                <span>فروعنا</span>
            </a>
        </li>

        <!-- المندوبين -->
        <li class="menu-item">
            <a href="sales_reps.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'sales_reps.php' ? 'active' : '' ?>">
                <i class="fas fa-user-tie"></i>
                <span>المندوبين</span>
            </a>
        </li>

        <!-- المنتجات -->
        <li class="menu-item">
            <a href="products.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">
                <i class="fas fa-box"></i>
                <span>المنتجات</span>
            </a>
        </li>

        <!-- السنوات المالية -->
        <li class="menu-item">
            <a href="fiscal_years.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'fiscal_years.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>السنوات المالية</span>
            </a>
        </li>

        <?php if($_SESSION['role'] == 'admin'): ?>
        <!-- المستخدمين -->
        <li class="menu-item">
            <a href="users.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i>
                <span>المستخدمين</span>
            </a>
        </li>

        <!-- سجل النشاطات -->
        <li class="menu-item">
            <a href="activity_log.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'activity_log.php' ? 'active' : '' ?>">
                <i class="fas fa-history"></i>
                <span>سجل النشاطات</span>
            </a>
        </li>
        <?php endif; ?>

        <div class="menu-section"></div>

        <!-- تسجيل الخروج -->
        <li class="menu-item">
            <a href="logout.php" class="menu-link" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
                <i class="fas fa-sign-out-alt"></i>
                <span>تسجيل الخروج</span>
            </a>
        </li>
    </ul>
</div>

<script>
// تفعيل القوائم الفرعية
document.querySelectorAll('.menu-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        this.classList.toggle('active');
        const submenu = this.nextElementSibling;
        if(submenu && submenu.classList.contains('submenu')) {
            submenu.classList.toggle('active');
        }
    });
});
</script>