
<?php
require_once 'config.php';
checkLogin();

$customer_id = $_GET['id'] ?? 0;

// جلب معلومات العميل
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if(!$customer) {
    header('Location: customers.php');
    exit;
}

// جلب الزيارات المستقبلية
$stmt = $pdo->prepare("SELECT v.*, u.full_name as scheduled_by_name
                       FROM scheduled_visits v
                       LEFT JOIN users u ON v.scheduled_by = u.id
                       WHERE v.customer_id = ? AND v.status != 'cancelled'
                       ORDER BY v.visit_date ASC, v.visit_time ASC");
$stmt->execute([$customer_id]);
$scheduled_visits = $stmt->fetchAll();

// جلب الزيارات السابقة
$stmt = $pdo->prepare("SELECT * FROM visits 
                       WHERE customer_id = ? 
                       ORDER BY visit_date DESC, visit_time DESC
                       LIMIT 10");
$stmt->execute([$customer_id]);
$past_visits = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جدول الزيارات - <?= htmlspecialchars($customer['company_name']) ?></title>
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
            transition: all 0.3s;
            border-right: 4px solid #667eea;
        }
        .visit-card:hover {
            transform: translateX(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .visit-card.urgent {
            border-right-color: #dc3545;
            background: #fff5f5;
        }
        .visit-card.upcoming {
            border-right-color: #ffc107;
        }
        .visit-card.completed {
            border-right-color: #28a745;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-calendar-check text-primary"></i> جدول الزيارات</h2>
                    <p class="text-muted"><?= htmlspecialchars($customer['company_name']) ?></p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleVisitModal">
                        <i class="fas fa-plus"></i> جدولة زيارة
                    </button>
                    <a href="view_customer.php?id=<?= $customer_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                </div>
            </div>

            <!-- إحصائيات سريعة -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-plus fa-2x text-primary mb-2"></i>
                            <h3><?= count(array_filter($scheduled_visits, fn($v) => $v['status'] == 'scheduled')) ?></h3>
                            <p class="text-muted mb-0">زيارات مجدولة</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                            <h3>
                                <?php
                                $urgent = 0;
                                foreach($scheduled_visits as $v) {
                                    if($v['status'] == 'scheduled') {
                                        $days = round((strtotime($v['visit_date']) - time()) / 86400);
                                        if($days <= 1) $urgent++;
                                    }
                                }
                                echo $urgent;
                                ?>
                            </h3>
                            <p class="text-muted mb-0">زيارات عاجلة</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h3><?= count(array_filter($scheduled_visits, fn($v) => $v['status'] == 'completed')) ?></h3>
                            <p class="text-muted mb-0">زيارات منفذة</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-history fa-2x text-info mb-2"></i>
                            <h3><?= count($past_visits) ?></h3>
                            <p class="text-muted mb-0">سجل الزيارات</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الزيارات المجدولة -->
            <div class="card mb-4" style="border-radius: 15px;">
                <div class="card-body">
                    <h5 class="mb-4">
                        <i class="fas fa-calendar-alt text-primary"></i> الزيارات المجدولة
                    </h5>

                    <?php if(empty($scheduled_visits)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <h5>لا توجد زيارات مجدولة</h5>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#scheduleVisitModal">
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
                            if($visit['status'] == 'completed') {
                                $card_class .= ' completed';
                            } elseif($days_until <= 1) {
                                $card_class .= ' urgent';
                            } elseif($days_until <= 7) {
                                $card_class .= ' upcoming';
                            }
                            
                            $status_labels = [
                                'scheduled' => 'مجدولة',
                                'completed' => 'منفذة',
                                'cancelled' => 'ملغاة',
                                'postponed' => 'مؤجلة'
                            ];
                            ?>
                            
                            <div class="<?= $card_class ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h6>
                                            <i class="fas fa-calendar"></i>
                                            <?= date('l، d F Y', strtotime($visit['visit_date'])) ?>
                                        </h6>
                                        <p class="mb-2">
                                            <i class="fas fa-clock"></i>
                                            <?= date('h:i A', strtotime($visit['visit_time'])) ?>
                                        </p>
                                        
                                        <?php if($visit['status'] == 'scheduled'): ?>
                                            <?php if($days_until < 0): ?>
                                                <span class="badge bg-danger">متأخرة</span>
                                            <?php elseif($days_until == 0): ?>
                                                <span class="badge bg-warning">اليوم (بعد <?= $hours_until ?> ساعة)</span>
                                            <?php elseif($days_until == 1): ?>
                                                <span class="badge bg-warning">غداً</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">بعد <?= $days_until ?> يوم</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-<?= $visit['status'] == 'completed' ? 'success' : 'secondary' ?>">
                                                <?= $status_labels[$visit['status']] ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if($visit['visit_type']): ?>
                                            <span class="badge bg-primary"><?= htmlspecialchars($visit['visit_type']) ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if($visit['purpose']): ?>
                                            <p class="mt-2 mb-0">
                                                <strong>الهدف:</strong> <?= htmlspecialchars($visit['purpose']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if($visit['notes']): ?>
                                            <p class="text-muted small mb-0">
                                                <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($visit['notes']) ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <small class="text-muted d-block mt-2">
                                            <i class="fas fa-user"></i> جدولها: <?= htmlspecialchars($visit['scheduled_by_name']) ?>
                                            | <i class="fas fa-bell"></i> تنبيه قبل: <?= $visit['reminder_before'] ?> ساعة
                                        </small>
                                    </div>
                                    
                                    <div class="col-md-6 text-end">
                                        <?php if($visit['status'] == 'scheduled'): ?>
                                            <button class="btn btn-sm btn-success mb-1" onclick="completeVisit(<?= $visit['id'] ?>)">
                                                <i class="fas fa-check"></i> تنفيذ
                                            </button>
                                            <button class="btn btn-sm btn-warning mb-1" onclick="editVisit(<?= $visit['id'] ?>)">
                                                <i class="fas fa-edit"></i> تعديل
                                            </button>
                                            <button class="btn btn-sm btn-secondary mb-1" onclick="postponeVisit(<?= $visit['id'] ?>)">
                                                <i class="fas fa-clock"></i> تأجيل
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-danger mb-1" onclick="deleteVisit(<?= $visit['id'] ?>)">
                                            <i class="fas fa-trash"></i> حذف
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- سجل الزيارات السابقة -->
            <div class="card" style="border-radius: 15px;">
                <div class="card-body">
                    <h5 class="mb-4">
                        <i class="fas fa-history text-info"></i> سجل الزيارات السابقة
                    </h5>

                    <?php if(empty($past_visits)): ?>
                        <p class="text-center text-muted">لا توجد زيارات سابقة</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>الوقت</th>
                                        <th>النوع</th>
                                        <th>الملاحظات</th>
                                        <th>بواسطة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($past_visits as $visit): ?>
                                        <tr>
                                            <td><?= date('Y-m-d', strtotime($visit['visit_date'])) ?></td>
                                            <td><?= $visit['visit_time'] ? date('h:i A', strtotime($visit['visit_time'])) : '-' ?></td>
                                            <td><?= htmlspecialchars($visit['visit_type'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($visit['notes'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($visit['created_by'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal جدولة زيارة -->
    <div class="modal fade" id="scheduleVisitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جدولة زيارة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="visits_action.php" method="POST">
                    <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ الزيارة *</label>
                                <input type="date" name="visit_date" class="form-control" required 
                                       min="<?= date('Y-m-d') ?>">
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
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="أي ملاحظات إضافية عن الزيارة..."></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>ملاحظة:</strong> سيتم إرسال تنبيه تلقائي لك قبل موعد الزيارة بالمدة المحددة.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="schedule_visit" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> جدولة الزيارة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal إكمال الزيارة -->
    <div class="modal fade" id="completeVisitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إكمال الزيارة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="visits_action.php" method="POST">
                    <input type="hidden" name="visit_id" id="completeVisitId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">تقرير الزيارة</label>
                            <textarea name="report" class="form-control" rows="5" required 
                                      placeholder="اكتب تقرير مفصل عن الزيارة، ما تم إنجازه، والخطوات القادمة..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" name="complete_visit" class="btn btn-success">
                            <i class="fas fa-check"></i> إكمال الزيارة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function completeVisit(id) {
            document.getElementById('completeVisitId').value = id;
            new bootstrap.Modal(document.getElementById('completeVisitModal')).show();
        }

        function editVisit(id) {
            window.location.href = `edit_scheduled_visit.php?id=${id}`;
        }

        function postponeVisit(id) {
            const days = prompt('تأجيل الزيارة لكم يوم؟', '1');
            if(days) {
                window.location.href = `visits_action.php?postpone=${id}&days=${days}`;
            }
        }

        function deleteVisit(id) {
            if(confirm('هل أنت متأكد من حذف هذه الزيارة المجدولة؟')) {
                window.location.href = `visits_action.php?delete=${id}&customer_id=<?= $customer_id ?>`;
            }
        }
    </script>
</body>
</html>