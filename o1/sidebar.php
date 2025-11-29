<?php
// sidebar.php - القائمة الجانبية المُحسّنة
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

// الحصول على السنة المالية الحالية
$current_fiscal_year = null;
try {
    $stmt = $pdo->query("SELECT * FROM fiscal_years WHERE status = 'open' ORDER BY start_date DESC LIMIT 1");
    $current_fiscal_year = $stmt->fetch();
} catch(Exception $e) {
    $current_fiscal_year = null;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body { 
        margin: 0; 
        padding: 0;
        overflow-x: hidden;
    }
    
    /* السايد بار الثابت */
    .sidebar {
        width: 260px;
        height: 100vh;
        position: fixed;
        right: 0;
        top: 0;
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        z-index: 1040;
        overflow-y: auto;
        overflow-x: hidden;
        display: flex;
        flex-direction: column;
    }
    
    .sidebar::-webkit-scrollbar { 
        width: 5px; 
    }
    
    .sidebar::-webkit-scrollbar-track { 
        background: rgba(255,255,255,0.1); 
    }
    
    .sidebar::-webkit-scrollbar-thumb { 
        background: rgba(255,255,255,0.3); 
        border-radius: 10px; 
    }
    
    /* رأس السايد بار */
    .sidebar-header {
        padding: 25px 20px;
        background: rgba(0,0,0,0.2);
        color: white;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        flex-shrink: 0;
    }
    
    .sidebar-header h4 { 
        margin: 0; 
        font-size: 18px; 
        font-weight: bold; 
    }
    
    .sidebar-header small { 
        opacity: 0.8; 
        display: block; 
        margin-top: 5px;
        font-size: 12px;
    }
    
    /* السنة المالية */
    .fiscal-year-badge {
        background: rgba(255,255,255,0.2);
        padding: 8px 12px;
        border-radius: 8px;
        margin-top: 10px;
        font-size: 11px;
        border: 1px solid rgba(255,255,255,0.3);
    }
    
    /* قائمة العناصر */
    .sidebar-menu { 
        padding: 15px 0; 
        flex: 1;
        overflow-y: auto;
    }
    
    .menu-item { 
        padding: 0; 
        margin: 3px 10px; 
    }
    
    .menu-item a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: rgba(255,255,255,0.9);
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s;
        position: relative;
        font-size: 14px;
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
        font-size: 16px;
        margin-left: 12px;
        text-align: center;
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
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    /* قدم السايد بار */
    .sidebar-footer {
        padding: 15px;
        background: rgba(0,0,0,0.2);
        border-top: 1px solid rgba(255,255,255,0.1);
        flex-shrink: 0;
        margin-top: auto;
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
        font-size: 18px;
    }
    
    .user-details { 
        flex: 1; 
        color: white; 
        min-width: 0;
        overflow: hidden;
    }
    
    .user-details .name { 
        font-weight: 600; 
        font-size: 14px; 
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis;
        display: block;
    }
    
    .user-details .role { 
        font-size: 11px; 
        opacity: 0.8; 
        display: block;
    }
    
    /* زر الخروج */
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
        font-size: 14px;
    }
    
    .logout-btn:hover {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    /* المحتوى الرئيسي */
    .main-content {
        margin-right: 260px;
        padding: 25px;
        min-height: 100vh;
        background: #f5f7fa;
    }
    
    /* زر القائمة للموبايل */
    .mobile-toggle {
        display: none;
        position: fixed;
        top: 15px;
        right: 15px;
        z-index: 1050;
        background: #667eea;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        cursor: pointer;
    }
    
    /* طبقة التعتيم للموبايل */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1035;
    }
    
    /* التصميم المتجاوب */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .sidebar.show { 
            transform: translateX(0); 
        }
        
        .sidebar-overlay.show {
            display: block;
        }
        
        .main-content { 
            margin-right: 0; 
            padding: 70px 15px 15px;
        }
        
        .mobile-toggle {
            display: block;
        }
    }
    
    /* للطباعة */
    @media print {
        .sidebar,
        .mobile-toggle {
            display: none !important;
        }
        
        .main-content {
            margin-right: 0 !important;
            padding: 0 !important;
        }
    }
</style>

<!-- طبقة التعتيم للموبايل -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- زر القائمة للموبايل -->
<button class="mobile-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- القائمة الجانبية -->
<div class="sidebar" id="sidebar">
    <!-- رأس السايد بار -->
    <div class="sidebar-header">
        <i class="fas fa-building fa-2x mb-2"></i>
        <h4>نظام إدارة العملاء</h4>
        <small>الإصدار 2.0</small>
        
        <?php if($current_fiscal_year): ?>
        <div class="fiscal-year-badge">
            <i class="fas fa-calendar-alt"></i>
            <?= htmlspecialchars($current_fiscal_year['year_name']) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- القائمة -->
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

        <div class="menu-section">المخزون والمبيعات</div>

        <div class="menu-item">
            <a href="products.php" class="<?= $current_page == 'products.php' ? 'active' : '' ?>">
                <i class="fas fa-box"></i>
                <span>المنتجات والمخزون</span>
            </a>
        </div>

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
            <a href="customer_sizes.php" class="<?= $current_page == 'customer_sizes.php' ? 'active' : '' ?>">
                <i class="fas fa-ruler"></i>
                <span>إدارة المقاسات</span>
            </a>
        </div>

        <div class="menu-section">الفروع والمندوبين</div>

        <div class="menu-item">
            <a href="my_branches_reps.php" class="<?= $current_page == 'my_branches_reps.php' ? 'active' : '' ?>">
                <i class="fas fa-building"></i>
                <span>فروعي ومندوبيني</span>
            </a>
        </div>
        
        <div class="menu-section">الزيارات</div>
        
        <div class="menu-item">
            <a href="all_visits.php" class="<?= $current_page == 'all_visits.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i>
                <span>جميع الزيارات</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="scheduled_visits.php" class="<?= $current_page == 'scheduled_visits.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>الزيارات المجدولة</span>
            </a>
        </div>

        <div class="menu-section">التقارير</div>

        <div class="menu-item">
            <a href="statistics.php" class="<?= $current_page == 'statistics.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span>الإحصائيات</span>
            </a>
        </div>

        <div class="menu-section">السنوات المالية</div>
        
        <div class="menu-item">
            <a href="fiscal_years.php" class="<?= $current_page == 'fiscal_years.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i>
                <span>إدارة السنوات المالية</span>
            </a>
        </div>
        
        <?php if($_SESSION['role'] == 'admin'): ?>
        <div class="menu-item">
            <a href="fiscal_year_closing.php" class="<?= $current_page == 'fiscal_year_closing.php' ? 'active' : '' ?>">
                <i class="fas fa-lock"></i>
                <span>إقفال السنة المالية</span>
            </a>
        </div>
        <?php endif; ?>

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

    <!-- قدم السايد بار -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?= mb_substr($_SESSION['full_name'], 0, 1) ?>
            </div>
            <div class="user-details">
                <span class="name" title="<?= htmlspecialchars($_SESSION['full_name']) ?>">
                    <?= htmlspecialchars($_SESSION['full_name']) ?>
                </span>
                <span class="role">
                    <?php
                    $roles = [
                        'admin' => 'مدير النظام',
                        'manager' => 'مدير مبيعات',
                        'employee' => 'موظف'
                    ];
                    echo $roles[$_SESSION['role']] ?? 'مستخدم';
                    ?>
                </span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
        </a>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }

    // إغلاق القائمة عند النقر خارجها (للموبايل)
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.querySelector('.mobile-toggle');
        const overlay = document.getElementById('sidebarOverlay');
        
        if(window.innerWidth < 768) {
            if(!sidebar.contains(event.target) && 
               toggle && !toggle.contains(event.target) &&
               !overlay.contains(event.target)) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        }
    });

    // إغلاق القائمة عند تغيير حجم الشاشة
    window.addEventListener('resize', function() {
        if(window.innerWidth >= 768) {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        }
    });
</script>