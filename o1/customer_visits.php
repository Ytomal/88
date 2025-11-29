<?php
require_once 'config.php';
checkLogin();

$customer_id = $_GET['id'] ?? 0;

// جلب معلومات العميل
$stmt = $pdo->prepare("SELECT c.*, r.region_name 
                       FROM customers c
                       LEFT JOIN regions r ON c.region_id = r.id
                       WHERE c.id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if(!$customer) {
    header('Location: customers.php');
    exit;
}

// جلب الزيارات مع معلومات الفرع
$visits = $pdo->prepare("SELECT v.*, cb.branch_name 
                        FROM visits v
                        LEFT JOIN customer_branches cb ON v.branch_id = cb.id
                        WHERE v.customer_id = ?
                        ORDER BY v.visit_date DESC, v.visit_time DESC");
$visits->execute([$customer_id]);
$visits = $visits->fetchAll();

// جلب الفروع للعميل
$branches = $pdo->prepare("SELECT * FROM customer_branches WHERE customer_id = ? AND status = 'active'");
$branches->execute([$customer_id]);
$branches = $branches->fetchAll();

// معالجة إضافة زيارة
if(isset($_POST['add_visit'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO visits (customer_id, branch_id, visit_date, visit_time, visit_type, notes, report, created_by) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $customer_id,
            $_POST['branch_id'] ?? null,
            $_POST['visit_date'],
            $_POST['visit_time'] ?? null,
            $_POST['visit_type'] ?? null,
            $_POST['notes'] ?? null,
            $_POST['report'] ?? null,
            $_SESSION['full_name']
        ]);
        
        logActivity('إضافة زيارة', "تم إضافة زيارة للعميل: {$customer['company_name']}");
        
        header("Location: customer_visits.php?id=$customer_id&success=added");
        exit;
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// معالجة حذف زيارة
if(isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM visits WHERE id = ? AND customer_id = ?");
        $stmt->execute([$_GET['delete'], $customer_id]);
        
        logActivity('حذف زيارة', "تم حذف زيارة من العميل: {$customer['company_name']}");
        
        header("Location: customer_visits.php?id=$customer_id&success=deleted");
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
    <title>إدارة الزيارات - <?= htmlspecialchars($customer['company_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="professional_style.css">
    <style>
        .visit-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        .visit-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .visit-date {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
        }
        .visit-date .day {
            font-size: 32px;
            font-weight: bold;
            display: block;
        }
        .visit-date .month-year {
            font-size: 14px;
            opacity: 0.9;
        }
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline::before {
            content: '';
            position: absolute;
            right: 50px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            padding-right: 80px;
            margin-bottom: 30px;
        }
        .timeline-marker {
            position: absolute;
            right: 41px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #e9ecef;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>
                        <i class="fas fa-calendar-alt text-primary"></i>
                        إدارة الزيارات
                    </h2>
                    <p class="text-muted">
                        <?= htmlspecialchars($customer['company_name']) ?>
                        <?php if($customer['region_name']): ?>
                            - <i class="fas fa-map-marker-alt"></i> <?= $customer['region_name'] ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVisitModal">
                        <i class="fas fa-plus"></i> إضافة زيارة جديدة
                    </button>
                    <a href="customer_info.php?id=<?= $customer_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                </div>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php
                    $messages = [
                        'added' => 'تم إضافة الزيارة بنجاح',
                        'deleted' => 'تم حذف الزيارة بنجاح'
                    ];
                    echo $messages[$_GET['success']] ?? 'تمت العملية بنجاح';
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- إحصائيات الزيارات -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-check fa-3x text-primary mb-3"></i>
                            <h3><?= count($visits) ?></h3>
                            <p class="text-muted mb-0">إجمالي الزيارات</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-day fa-3x text-success mb-3"></i>
                            <h3>
                                <?php
                                $this_month = date('Y-m');
                                echo count(array_filter($visits, fn($v) => date('Y-m', strtotime($v['visit_date'])) == $this_month));
                                ?>
                            </h3>
                            <p class="text-muted mb-0">زيارات هذا الشهر</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-clock fa-3x text-info mb-3"></i>
                            <h3>
                                <?= !empty($visits) ? date('Y-m-d', strtotime($visits[0]['visit_date'])) : '-' ?>
                            </h3>
                            <p class="text-muted mb-0">آخر زيارة</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- قائمة الزيارات -->
            <?php if(empty($visits)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">لا توجد زيارات مسجلة</h4>
                    <p class="text-muted">ابدأ بإضافة أول زيارة لهذا العميل</p>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addVisitModal">
                        <i class="fas fa-plus"></i> إضافة أول زيارة
                    </button>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-4"><i class="fas fa-history"></i> سجل الزيارات</h5>
                        <div class="timeline">
                            <?php foreach($visits as $visit): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="visit-card">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="visit-date">
                                                    <span class="day"><?= date('d', strtotime($visit['visit_date'])) ?></span>
                                                    <span class="month-year">
                                                        <?php
                                                        $months = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
                                                        echo $months[(int)date('m', strtotime($visit['visit_date']))];
                                                        echo ' ' . date('Y', strtotime($visit['visit_date']));
                                                        ?>
                                                    </span>
                                                    <?php if($visit['visit_time']): ?>
                                                        <small class="d-block mt-2">
                                                            <i class="fas fa-clock"></i>
                                                            <?= date('h:i A', strtotime($visit['visit_time'])) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-9">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <?php if($visit['visit_type']): ?>
                                                            <span class="badge bg-primary mb-2">
                                                                <i class="fas fa-tag"></i>
                                                                <?= htmlspecialchars($visit['visit_type']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if($visit['branch_name']): ?>
                                                            <span class="badge bg-success mb-2">
                                                                <i class="fas fa-building"></i>
                                                                <?= htmlspecialchars($visit['branch_name']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteVisit(<?= $visit['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>

                                                <?php if($visit['notes']): ?>
                                                    <div class="mb-3">
                                                        <strong><i class="fas fa-sticky-note text-warning"></i> الملاحظات:</strong>
                                                        <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($visit['notes'])) ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if($visit['report']): ?>
                                                    <div class="alert alert-info mb-0">
                                                        <strong><i class="fas fa-file-alt"></i> التقرير:</strong>
                                                        <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($visit['report'])) ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="text-muted small mt-3">
                                                    <i class="fas fa-user"></i> أضيفت بواسطة: <?= htmlspecialchars($visit['created_by'] ?? 'غير معروف') ?>
                                                    | <i class="fas fa-clock"></i> <?= date('Y-m-d H:i', strtotime($visit['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- روابط سريعة -->
            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-link"></i> روابط سريعة</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="customer_details.php?id=<?= $customer_id ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-list"></i> التفاصيل
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="customer_documents.php?id=<?= $customer_id ?>" class="btn btn-outline-success w-100">
                                <i class="fas fa-file-contract"></i> السندات
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="customer_sizes.php?id=<?= $customer_id ?>" class="btn btn-outline-warning w-100">
                                <i class="fas fa-ruler"></i> المقاسات
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="branches.php?customer_id=<?= $customer_id ?>" class="btn btn-outline-info w-100">
                                <i class="fas fa-building"></i> الفروع
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal إضافة زيارة -->
    <div class="modal fade" id="addVisitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة زيارة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ الزيارة *</label>
                                <input type="date" name="visit_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">وقت الزيارة</label>
                                <input type="time" name="visit_time" class="form-control">
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
                                <label class="form-label">الفرع</label>
                                <select name="branch_id" class="form-control">
                                    <option value="">الفرع الرئيسي</option>
                                    <?php foreach($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>">
                                            <?= htmlspecialchars($branch['branch_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ملاحظات الزيارة</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="أدخل ملاحظاتك عن الزيارة..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">تقرير الزيارة</label>
                            <textarea name="report" class="form-control" rows="4" 
                                      placeholder="اكتب تقريراً تفصيلياً عن الزيارة، ما تم إنجازه، والخطوات القادمة..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="add_visit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ الزيارة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteVisit(id) {
            if(confirm('هل أنت متأكد من حذف هذه الزيارة؟')) {
                window.location.href = 'customer_visits.php?id=<?= $customer_id ?>&delete=' + id;
            }
        }
    </script>
</body>
</html>