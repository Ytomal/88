<?php
require_once 'config.php';
checkLogin();

$branch_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT cb.*, c.company_name 
                       FROM customer_branches cb
                       JOIN customers c ON cb.customer_id = c.id
                       WHERE cb.id = ?");
$stmt->execute([$branch_id]);
$branch = $stmt->fetch();

if(!$branch) {
    header('Location: customers.php');
    exit;
}

$regions = $pdo->query("SELECT * FROM regions WHERE status = 'active' ORDER BY region_name")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الفرع</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <span class="navbar-brand">تعديل الفرع</span>
            <a href="branches.php?customer_id=<?= $branch['customer_id'] ?>" class="btn btn-light btn-sm">العودة</a>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <div class="card-body">
                <form action="branches_action.php" method="POST">
                    <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
                    <input type="hidden" name="customer_id" value="<?= $branch['customer_id'] ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>اسم الفرع *</label>
                            <input type="text" name="branch_name" class="form-control" 
                                   value="<?= htmlspecialchars($branch['branch_name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>كود الفرع</label>
                            <input type="text" name="branch_code" class="form-control" 
                                   value="<?= htmlspecialchars($branch['branch_code'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>المنطقة</label>
                            <select name="region_id" class="form-control">
                                <option value="">اختر المنطقة</option>
                                <?php foreach($regions as $region): ?>
                                    <option value="<?= $region['id'] ?>" <?= $branch['region_id'] == $region['id'] ? 'selected' : '' ?>>
                                        <?= $region['region_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>عدد الفتحات</label>
                            <input type="number" name="shop_fronts_count" class="form-control" 
                                   value="<?= $branch['shop_fronts_count'] ?>" min="1">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>الهاتف</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?= htmlspecialchars($branch['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>البريد</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($branch['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>مدير الفرع</label>
                        <input type="text" name="manager_name" class="form-control" 
                               value="<?= htmlspecialchars($branch['manager_name'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label>العنوان</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($branch['address'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label>رابط خرائط جوجل</label>
                        <input type="url" name="google_maps_url" class="form-control" 
                               value="<?= htmlspecialchars($branch['google_maps_url'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label>وصف المحل</label>
                        <textarea name="shop_description" class="form-control" rows="3"><?= htmlspecialchars($branch['shop_description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label>الحالة</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= $branch['status'] == 'active' ? 'selected' : '' ?>>نشط</option>
                            <option value="inactive" <?= $branch['status'] == 'inactive' ? 'selected' : '' ?>>معطل</option>
                        </select>
                    </div>

                    <button type="submit" name="update_branch" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ التعديلات
                    </button>
                    <a href="branches.php?customer_id=<?= $branch['customer_id'] ?>" class="btn btn-secondary">إلغاء</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>