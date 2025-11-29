<?php
require_once 'config.php';
checkLogin();

// جلب المناطق
$regions = $pdo->query("SELECT * FROM regions WHERE status = 'active' ORDER BY region_name")->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة عميل جديد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            margin-right: 260px;
            padding: 25px;
        }
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-user-plus text-primary"></i> إضافة عميل جديد</h2>
                    <p class="text-muted">أدخل بيانات العميل الجديد</p>
                </div>
                <a href="customers.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i> العودة
                </a>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="customer_action.php" method="POST" enctype="multipart/form-data">
                <!-- المعلومات الأساسية -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-info-circle"></i>
                        <h5 class="mb-0">المعلومات الأساسية</h5>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">اسم الشركة/المؤسسة</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-building"></i></span>
                                <input type="text" name="company_name" class="form-control" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label required">نوع الشركة</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                <select name="company_type" class="form-control" required>
                                    <option value="individual_institution">مؤسسة فردية</option>
                                    <option value="large_institution">مؤسسة كبيرة</option>
                                    <option value="company">شركة</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">اسم المالك</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" name="owner_name" class="form-control" required>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">الشخص المسؤول</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                <input type="text" name="responsible_person" class="form-control">
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label required">المنطقة</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <select name="region_id" class="form-control" required>
                                    <option value="">اختر المنطقة</option>
                                    <?php foreach($regions as $region): ?>
                                        <option value="<?= $region['id'] ?>"><?= $region['region_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="text" name="phone" class="form-control" placeholder="05xxxxxxxx">
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control">
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">تاريخ البداية</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">العنوان التفصيلي</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-location-dot"></i></span>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="has_branches" value="1" class="form-check-input" id="hasBranches">
                        <label class="form-check-label" for="hasBranches">
                            <i class="fas fa-building"></i> لدى العميل فروع متعددة
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-sticky-note"></i></span>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <!-- المعلومات المالية -->
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-dollar-sign"></i>
                        <h5 class="mb-0">المعلومات المالية</h5>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">نوع الدفع</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-money-bill"></i></span>
                                <select name="payment_type" class="form-control" required id="paymentType">
                                    <option value="cash">نقداً</option>
                                    <option value="credit">آجل</option>
                                    <option value="both" selected>نقداً وآجل</option>
                                </select>
                            </div>
                            <small class="text-muted">طريقة الدفع المفضلة للعميل</small>
                        </div>

                        <div class="col-md-4 mb-3" id="creditLimitField">
                            <label class="form-label">الحد الائتماني (ريال)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-credit-card"></i></span>
                                <input type="number" name="credit_limit" class="form-control" step="0.01" value="0.00" min="0">
                            </div>
                            <small class="text-muted">الحد الأقصى للمديونية المسموح بها</small>
                        </div>

                        <div class="col-md-4 mb-3" id="paymentTermsField">
                            <label class="form-label">مدة السداد (أيام)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar-days"></i></span>
                                <input type="number" name="payment_terms" class="form-control" value="30" min="0">
                            </div>
                            <small class="text-muted">عدد الأيام المسموح بها للسداد</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الرصيد الافتتاحي (ريال)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-balance-scale"></i></span>
                                <input type="number" name="opening_balance" class="form-control" step="0.01" value="0.00">
                            </div>
                            <small class="text-muted">الرصيد الحالي عند بداية التعامل (موجب = مدين، سالب = دائن)</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">حالة العميل</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-toggle-on"></i></span>
                                <select name="status" class="form-control">
                                    <option value="active" selected>نشط</option>
                                    <option value="inactive">معطل</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>ملاحظة:</strong> يمكنك تعديل الحد الائتماني ومدة السداد لاحقاً حسب سلوك الدفع للعميل.
                    </div>
                </div>

                <!-- أزرار الحفظ -->
                <div class="text-center">
                    <button type="submit" name="add_customer" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> حفظ العميل
                    </button>
                    <a href="customers.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // إظهار/إخفاء حقول الائتمان حسب نوع الدفع
        document.getElementById('paymentType').addEventListener('change', function() {
            const creditField = document.getElementById('creditLimitField');
            const termsField = document.getElementById('paymentTermsField');
            
            if(this.value === 'cash') {
                creditField.style.display = 'none';
                termsField.style.display = 'none';
                document.querySelector('input[name="credit_limit"]').value = '0.00';
                document.querySelector('input[name="payment_terms"]').value = '0';
            } else {
                creditField.style.display = 'block';
                termsField.style.display = 'block';
                if(document.querySelector('input[name="payment_terms"]').value == '0') {
                    document.querySelector('input[name="payment_terms"]').value = '30';
                }
            }
        });

        // تحقق من القيمة الأولية عند تحميل الصفحة
        document.getElementById('paymentType').dispatchEvent(new Event('change'));
    </script>
</body>
</html>