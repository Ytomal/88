<?php
require_once 'config.php';
checkLogin();

// جلب الزيارات المجدولة
$filter = $_GET['filter'] ?? 'upcoming';

$where = ["1=1"];
$params = [];

if($filter == 'upcoming') {
    $where[] = "v.visit_date >= CURDATE() AND v.status = 'scheduled'";
} elseif($filter == 'today') {
    $where[] = "v.visit_date = CURDATE() AND v.status = 'scheduled'";
} elseif($filter == 'completed') {
    $where[] = "v.status = 'completed'";
}

$where_clause = implode(' AND ', $where);

$sql = "SELECT v.*, c.company_name, c.owner_name,
        cb.branch_name as customer_branch_name,
        u.full_name as scheduled_by_name
        FROM scheduled_visits v
        JOIN customers c ON v.customer_id = c.id
        LEFT JOIN customer_branches cb ON v.branch_id = cb.id
        LEFT JOIN users u ON v.scheduled_by = u.id
        WHERE $where_clause
        ORDER BY v.visit_date ASC, v.visit_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$scheduled_visits = $stmt->fetchAll();

// الإحصائيات
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM scheduled_visits WHERE status = 'scheduled'")->fetchColumn(),
    'today' => $pdo->query("SELECT COUNT(*) FROM scheduled_visits WHERE visit_date = CURDATE() AND status = 'scheduled'")->fetchColumn(),
    'this_week' => $pdo->query("SELECT COUNT(*) FROM scheduled_visits WHERE visit_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'scheduled'")->fetchColumn()
];

// جلب العملاء النشطين
$customers = $pdo->query("SELECT id, company_name FROM customers WHERE status='active' ORDER BY company_name")->fetchAll();

// معالجة الإضافة
if(isset($_POST['add_scheduled_visit'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO scheduled_visits 
            (customer_id, branch_id, visit_date, visit_time, visit_type, purpose, 
             reminder_before, notes, scheduled_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['branch_id'] ?: null,
            $_POST['visit_date'],
            $_POST['visit_time'],
            $_POST['visit_type'] ?: null,
            $_POST['purpose'] ?: null,
            $_POST['reminder_before'] ?? 24,
            $_POST['notes'] ?: null,
            $_SESSION['user_id']
        ]);
        
        $visit_id = $pdo->lastInsertId();
        
        // إنشاء تذكير
        $visit_datetime = $_POST['visit_date'] . ' ' . $_POST['visit_time'];
        $reminder_hours = intval($_POST['reminder_before'] ?? 24);
        $reminder_time = date('Y-m-d H:i:s', strtotime($visit_datetime) - ($reminder_hours * 3600));
        
        $stmt = $pdo->prepare("INSERT INTO visit_reminders (scheduled_visit_id, reminder_time) VALUES (?, ?)");
        $stmt->execute([$visit_id, $reminder_time]);
        
        logActivity('جدولة زيارة', "تم جدولة زيارة للعميل ID: {$_POST['customer_id']}");
        
        $pdo->commit();
        header('Location: scheduled_visits.php?success=added');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// حذف زيارة مجدولة
if(isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("UPDATE scheduled_visits SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        
        logActivity('إلغاء زيارة', "تم إلغاء الزيارة المجدولة ID: {$_GET['delete']}");
        
        header('Location: scheduled_visits.php?success=deleted');
        exit;
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جدولة الزيارات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .main-content {
            margin-right: 260px;
            padding: 25px;
        }
        .visit-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-right: 4px solid #667eea;
            transition: all 0.3s;
        }
        .visit-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .visit-card.urgent {
            border-right-color: #dc3545;
            background: linear-gradient(to left, #fff 0%, #fff5f5 100%);
        }
        .visit-card.today {
            border-right-color: #ffc107;
            background: linear-gradient(to left, #fff 0%, #fffbf0 100%);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-clock text-primary"></i> جدولة الزيارات</h2>
                    <p class="text-muted">تخطيط وجدولة زيارات العملاء المستقبلية</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVisitModal">
                    <i class="fas fa-plus"></i> جدولة زيارة جديدة
                </button>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    تمت العملية بنجاح!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- الفلاتر -->
            <div class="card mb-4" style="border-radius: 15px;">
                <div class="card-body">
                    <div class="btn-group" role="group">
                        <a href="scheduled_visits.php?filter=upcoming" class="btn btn-<?= $filter=='upcoming'?'primary':'outline-primary' ?>">
                            <i class="fas fa-calendar-day"></i> القادمة
                        </a>
                        <a href="scheduled_visits.php?filter=today" class="btn btn-<?= $filter=='today'?'warning':'outline-warning' ?>">
                            <i class="fas fa-calendar-check"></i> اليوم
                        </a>
                        <a href="scheduled_visits.php?filter=completed" class="btn btn-<?= $filter=='completed'?'success':'outline-success' ?>">
                            <i class="fas fa-check-circle"></i> المنفذة
                        </a>
                    </div>
                </div>
            </div>

            <!-- الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-plus fa-2x text-primary mb-2"></i>
                            <h3><?= $stats['total'] ?></h3>
                            <p class="text-muted mb-0">زيارات مجدولة</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-day fa-2x text-warning mb-2"></i>
                            <h3><?= $stats['today'] ?></h3>
                            <p class="text-muted mb-0">زيارات اليوم</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-week fa-2x text-info mb-2"></i>
                            <h3><?= $stats['this_week'] ?></h3>
                            <p class="text-muted mb-0">هذا الأسبوع</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- قائمة الزيارات المجدولة -->
            <?php if(empty($scheduled_visits)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h4>لا توجد زيارات مجدولة</h4>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addVisitModal">
                        جدولة أول زيارة
                    </button>
                </div>
            <?php else: ?>
                <?php foreach($scheduled_visits as $visit): ?>
                    <?php
                    $visit_datetime = strtotime($visit['visit_date'] . ' ' . $visit['visit_time']);
                    $now = time();
                    $days_until = round(($visit_datetime - $now) / 86400);
                    $hours_until = round(($visit_datetime - $now) / 3600);
                    
                    $card_class = 'visit-card';
                    if($days_until == 0) {
                        $card_class .= ' today';
                    } elseif($days_until <= 1) {
                        $card_class .= ' urgent';
                    }
                    ?>
                    
                    <div class="<?= $card_class ?>">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5>
                                    <i class="fas fa-building text-primary"></i>
                                    <?= htmlspecialchars($visit['company_name']) ?>
                                </h5>
                                <div class="mb-2">
                                    <span class="badge bg-info">
                                        <i class="fas fa-calendar"></i> <?= date('Y-m-d', strtotime($visit['visit_date'])) ?>
                                    </span>
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($visit['visit_time'])) ?>
                                    </span>
                                    
                                    <?php if($days_until < 0): ?>
                                        <span class="badge bg-danger">متأخرة</span>
                                    <?php elseif($days_until == 0): ?>
                                        <span class="badge bg-warning">اليوم (بعد <?= $hours_until ?> ساعة)</span>
                                    <?php elseif($days_until == 1): ?>
                                        <span class="badge bg-warning">غداً</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">بعد <?= $days_until ?> يوم</span>
                                    <?php endif; ?>
                                    
                                    <?php if($visit['visit_type']): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($visit['visit_type']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if($visit['purpose']): ?>
                                    <p class="mb-2"><strong>الهدف:</strong> <?= htmlspecialchars($visit['purpose']) ?></p>
                                <?php endif; ?>

                                <?php if($visit['notes']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($visit['notes']) ?>
                                    </small>
                                <?php endif; ?>

                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> بواسطة: <?= htmlspecialchars($visit['scheduled_by_name']) ?>
                                    </small>
                                </div>
                            </div>

                            <div class="col-md-4 text-end">
                                <?php if($visit['status'] == 'scheduled'): ?>
                                    <a href="customer_visits.php?id=<?= $visit['customer_id'] ?>&scheduled_id=<?= $visit['id'] ?>" 
                                       class="btn btn-sm btn-success mb-1">
                                        <i class="fas fa-check"></i> تنفيذ
                                    </a>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-danger mb-1" onclick="deleteVisit(<?= $visit['id'] ?>)">
                                    <i class="fas fa-trash"></i> إلغاء
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal إضافة زيارة مجدولة -->
    <div class="modal fade" id="addVisitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جدولة زيارة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">العميل *</label>
                                <select name="customer_id" class="form-control" required>
                                    <option value="">اختر العميل</option>
                                    <?php foreach($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">فرع العميل</label>
                                <select name="branch_id" class="form-control">
                                    <option value="">الفرع الرئيسي</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ الزيارة *</label>
                                <input type="date" name="visit_date" class="form-control" 
                                       min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">وقت الزيارة *</label>
                                <input type="time" name="visit_time" class="form-control" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نوع الزيارة</label>
                                <select name="visit_type" class="form-control">
                                    <option value="">اختر النوع</option>
                                    <option value="زيارة تعريفية">زيارة تعريفية</option>
                                    <option value="زيارة متابعة">زيارة متابعة</option>
                                    <option value="زيارة تحصيل">زيارة تحصيل</option>
                                    <option value="زيارة صيانة">زيارة صيانة</option>
                                    <option value="زيارة توريد">زيارة توريد</option>
                                    <option value="أخرى">أخرى</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">التنبيه قبل الموعد *</label>
                                <select name="reminder_before" class="form-control" required>
                                    <option value="1">ساعة واحدة</option>
                                    <option value="2">ساعتين</option>
                                    <option value="3">3 ساعات</option>
                                    <option value="6">6 ساعات</option>
                                    <option value="12">12 ساعة</option>
                                    <option value="24" selected>24 ساعة (يوم)</option>
                                    <option value="48">48 ساعة (يومين)</option>
                                    <option value="72">72 ساعة (3 أيام)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الهدف من الزيارة</label>
                            <input type="text" name="purpose" class="form-control" 
                                   placeholder="مثال: متابعة طلب، تحصيل دفعة، عرض منتجات جديدة">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>ملاحظة:</strong> سيتم إرسال تنبيه تلقائي لك قبل موعد الزيارة بالمدة المحددة.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_scheduled_visit" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> جدولة الزيارة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteVisit(id) {
            if(confirm('هل أنت متأكد من إلغاء هذه الزيارة المجدولة؟')) {
                window.location.href = 'scheduled_visits.php?delete=' + id;
            }
        }
    </script>
</body>
</html>