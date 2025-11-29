<?php
require_once 'config.php';
checkLogin();

// الفلاتر
$filter_type = $_GET['filter'] ?? 'all';
$filter_entity_id = $_GET['entity_id'] ?? 0;
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// بناء الاستعلام
$where = ["1=1"];
$params = [];

if($filter_type == 'branch' && $filter_entity_id) {
    $where[] = "EXISTS (
        SELECT 1 FROM customer_branch_rep_assignments cbra
        WHERE cbra.customer_id = v.customer_id 
        AND cbra.my_branch_id = ? 
        AND cbra.status = 'active'
    )";
    $params[] = $filter_entity_id;
} elseif($filter_type == 'rep' && $filter_entity_id) {
    $where[] = "EXISTS (
        SELECT 1 FROM customer_branch_rep_assignments cbra
        WHERE cbra.customer_id = v.customer_id 
        AND cbra.sales_rep_id = ? 
        AND cbra.status = 'active'
    )";
    $params[] = $filter_entity_id;
} elseif($filter_type == 'customer' && $filter_entity_id) {
    $where[] = "v.customer_id = ?";
    $params[] = $filter_entity_id;
}

$where[] = "v.visit_date BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;

$where_clause = implode(' AND ', $where);

// جلب الزيارات
$sql = "SELECT v.*, c.company_name, c.owner_name,
        cb.branch_name as customer_branch_name,
        u.full_name as created_by_name
        FROM visits v
        JOIN customers c ON v.customer_id = c.id
        LEFT JOIN customer_branches cb ON v.branch_id = cb.id
        LEFT JOIN users u ON v.created_by_user_id = u.id
        WHERE $where_clause
        ORDER BY v.visit_date DESC, v.visit_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll();

// الإحصائيات
$stats = [
    'total' => count($visits),
    'this_month' => count(array_filter($visits, fn($v) => date('Y-m', strtotime($v['visit_date'])) == date('Y-m'))),
    'last_week' => count(array_filter($visits, fn($v) => strtotime($v['visit_date']) >= strtotime('-7 days')))
];

// القوائم للفلاتر
$branches = $pdo->query("SELECT * FROM my_branches WHERE status='active' ORDER BY branch_name")->fetchAll();
$reps = $pdo->query("SELECT * FROM sales_representatives WHERE status='active' ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT id, company_name FROM customers WHERE status='active' ORDER BY company_name LIMIT 100")->fetchAll();

// معالجة الإضافة
if(isset($_POST['add_visit'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO visits 
            (customer_id, branch_id, visit_date, visit_time, visit_type, notes, report, created_by, created_by_user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['branch_id'] ?: null,
            $_POST['visit_date'],
            $_POST['visit_time'] ?: null,
            $_POST['visit_type'] ?: null,
            $_POST['notes'] ?: null,
            $_POST['report'] ?: null,
            $_SESSION['full_name'],
            $_SESSION['user_id']
        ]);
        
        $visit_id = $pdo->lastInsertId();
        
        // رفع الصور
        if(isset($_FILES['visit_images']) && !empty($_FILES['visit_images']['name'][0])) {
            $uploadDir = 'uploads/visits/';
            if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $filesCount = count($_FILES['visit_images']['name']);
            for($i = 0; $i < $filesCount; $i++) {
                if($_FILES['visit_images']['error'][$i] === 0) {
                    $file = [
                        'name' => $_FILES['visit_images']['name'][$i],
                        'type' => $_FILES['visit_images']['type'][$i],
                        'tmp_name' => $_FILES['visit_images']['tmp_name'][$i],
                        'error' => $_FILES['visit_images']['error'][$i],
                        'size' => $_FILES['visit_images']['size'][$i]
                    ];
                    
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $fileName = time() . '_' . uniqid() . '.' . $extension;
                    $targetPath = $uploadDir . $fileName;
                    
                    if(move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $stmt = $pdo->prepare("INSERT INTO visit_attachments 
                            (visit_id, file_name, file_path, file_type, file_size, uploaded_by) 
                            VALUES (?, ?, ?, ?, ?, ?)");
                        
                        $stmt->execute([
                            $visit_id,
                            $file['name'],
                            $targetPath,
                            $extension,
                            $file['size'],
                            $_SESSION['user_id']
                        ]);
                    }
                }
            }
        }
        
        logActivity('إضافة زيارة', "تمت إضافة زيارة للعميل ID: {$_POST['customer_id']}");
        
        $pdo->commit();
        header('Location: visits.php?success=added');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// حذف زيارة
if(isset($_GET['delete'])) {
    try {
        $visit_id = $_GET['delete'];
        
        // حذف الملفات
        $stmt = $pdo->prepare("SELECT file_path FROM visit_attachments WHERE visit_id = ?");
        $stmt->execute([$visit_id]);
        $files = $stmt->fetchAll();
        
        foreach($files as $file) {
            if(file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM visits WHERE id = ?");
        $stmt->execute([$visit_id]);
        
        header('Location: visits.php?success=deleted');
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
    <title>إدارة الزيارات</title>
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
        .visit-images {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .visit-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .visit-image:hover {
            transform: scale(1.1);
        }
        .file-upload-area {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .file-upload-area:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-calendar-check text-primary"></i> إدارة الزيارات</h2>
                    <p class="text-muted">سجل وإدارة زيارات العملاء</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVisitModal">
                    <i class="fas fa-plus"></i> إضافة زيارة
                </button>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php
                    $messages = ['added'=>'تمت الإضافة', 'deleted'=>'تم الحذف'];
                    echo $messages[$_GET['success']] ?? 'تمت العملية بنجاح';
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- الفلاتر -->
            <div class="card mb-4" style="border-radius: 15px;">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-filter"></i> الفلاتر</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <select name="filter" class="form-control" onchange="updateEntitySelect(this.value)">
                                <option value="all" <?= $filter_type=='all'?'selected':'' ?>>الكل</option>
                                <option value="branch" <?= $filter_type=='branch'?'selected':'' ?>>حسب الفرع</option>
                                <option value="rep" <?= $filter_type=='rep'?'selected':'' ?>>حسب المندوب</option>
                                <option value="customer" <?= $filter_type=='customer'?'selected':'' ?>>حسب العميل</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="entity_select_container">
                            <?php if($filter_type == 'branch'): ?>
                                <select name="entity_id" class="form-control">
                                    <option value="">اختر الفرع</option>
                                    <?php foreach($branches as $b): ?>
                                        <option value="<?= $b['id'] ?>" <?= $filter_entity_id==$b['id']?'selected':'' ?>>
                                            <?= htmlspecialchars($b['branch_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif($filter_type == 'rep'): ?>
                                <select name="entity_id" class="form-control">
                                    <option value="">اختر المندوب</option>
                                    <?php foreach($reps as $r): ?>
                                        <option value="<?= $r['id'] ?>" <?= $filter_entity_id==$r['id']?'selected':'' ?>>
                                            <?= htmlspecialchars($r['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif($filter_type == 'customer'): ?>
                                <select name="entity_id" class="form-control">
                                    <option value="">اختر العميل</option>
                                    <?php foreach($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $filter_entity_id==$c['id']?'selected':'' ?>>
                                            <?= htmlspecialchars($c['company_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> عرض
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-list fa-2x text-primary mb-2"></i>
                            <h3><?= $stats['total'] ?></h3>
                            <p class="text-muted mb-0">إجمالي الزيارات</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-day fa-2x text-success mb-2"></i>
                            <h3><?= $stats['this_month'] ?></h3>
                            <p class="text-muted mb-0">هذا الشهر</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-clock fa-2x text-info mb-2"></i>
                            <h3><?= $stats['last_week'] ?></h3>
                            <p class="text-muted mb-0">آخر أسبوع</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- قائمة الزيارات -->
            <?php if(empty($visits)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h4>لا توجد زيارات</h4>
                </div>
            <?php else: ?>
                <?php foreach($visits as $visit): ?>
                    <div class="visit-card">
                        <div class="row">
                            <div class="col-md-8">
                                <h5>
                                    <i class="fas fa-building text-primary"></i>
                                    <?= htmlspecialchars($visit['company_name']) ?>
                                </h5>
                                <div class="mb-2">
                                    <span class="badge bg-info">
                                        <i class="fas fa-calendar"></i> <?= date('Y-m-d', strtotime($visit['visit_date'])) ?>
                                    </span>
                                    <?php if($visit['visit_time']): ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($visit['visit_time'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if($visit['visit_type']): ?>
                                        <span class="badge bg-primary"><?= htmlspecialchars($visit['visit_type']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if($visit['notes']): ?>
                                    <p class="mb-2"><strong>الملاحظات:</strong> <?= nl2br(htmlspecialchars($visit['notes'])) ?></p>
                                <?php endif; ?>

                                <?php if($visit['report']): ?>
                                    <div class="alert alert-info mb-2">
                                        <strong>التقرير:</strong> <?= nl2br(htmlspecialchars($visit['report'])) ?>
                                    </div>
                                <?php endif; ?>

                                <?php
                                // جلب الصور
                                $stmt = $pdo->prepare("SELECT * FROM visit_attachments WHERE visit_id = ?");
                                $stmt->execute([$visit['id']]);
                                $images = $stmt->fetchAll();
                                
                                if(!empty($images)):
                                ?>
                                    <div class="visit-images">
                                        <?php foreach($images as $img): ?>
                                            <img src="<?= htmlspecialchars($img['file_path']) ?>" 
                                                 class="visit-image" 
                                                 alt="صورة الزيارة"
                                                 onclick="viewImage('<?= htmlspecialchars($img['file_path']) ?>')">
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-user"></i> بواسطة: <?= htmlspecialchars($visit['created_by_name'] ?? $visit['created_by']) ?>
                                </small>
                            </div>

                            <div class="col-md-4 text-end">
                                <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-primary mb-1">
                                    <i class="fas fa-eye"></i> عرض
                                </a>
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

    <!-- Modal إضافة زيارة -->
    <div class="modal fade" id="addVisitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة زيارة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
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
                                <input type="date" name="visit_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">وقت الزيارة</label>
                                <input type="time" name="visit_time" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
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

                        <div class="mb-3">
                            <label class="form-label">ملاحظات الزيارة</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">تقرير الزيارة</label>
                            <textarea name="report" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رفع الصور</label>
                            <div class="file-upload-area" onclick="document.getElementById('visit_images').click()">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <h5>اسحب وأفلت الصور هنا</h5>
                                <p class="text-muted">أو انقر للاختيار</p>
                                <input type="file" name="visit_images[]" id="visit_images" multiple accept="image/*" style="display: none;">
                            </div>
                            <div id="preview" class="visit-images mt-3"></div>
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

    <!-- Modal عرض الصورة -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-0">
                    <img id="modalImage" src="" alt="صورة الزيارة" style="width: 100%; height: auto;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const branches = <?= json_encode($branches) ?>;
        const reps = <?= json_encode($reps) ?>;
        const customers = <?= json_encode($customers) ?>;

        function updateEntitySelect(filterType) {
            const container = document.getElementById('entity_select_container');
            let html = '';
            
            if(filterType === 'branch') {
                html = '<select name="entity_id" class="form-control"><option value="">اختر الفرع</option>';
                branches.forEach(b => html += `<option value="${b.id}">${b.branch_name}</option>`);
                html += '</select>';
            } else if(filterType === 'rep') {
                html = '<select name="entity_id" class="form-control"><option value="">اختر المندوب</option>';
                reps.forEach(r => html += `<option value="${r.id}">${r.name}</option>`);
                html += '</select>';
            } else if(filterType === 'customer') {
                html = '<select name="entity_id" class="form-control"><option value="">اختر العميل</option>';
                customers.forEach(c => html += `<option value="${c.id}">${c.company_name}</option>`);
                html += '</select>';
            }
            
            container.innerHTML = html;
        }

        // معاينة الصور
        document.getElementById('visit_images').addEventListener('change', function(e) {
            const preview = document.getElementById('preview');
            preview.innerHTML = '';
            
            Array.from(e.target.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = document.createElement('img');
                    img.src = event.target.result;
                    img.className = 'visit-image';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        });

        function viewImage(src) {
            document.getElementById('modalImage').src = src;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        function deleteVisit(id) {
            if(confirm('هل أنت متأكد من حذف هذه الزيارة؟')) {
                window.location.href = 'visits.php?delete=' + id;
            }
        }
    </script>
</body>
</html>