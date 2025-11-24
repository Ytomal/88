<?php
require_once 'config.php';
require_once 'auth.php';

check_login();

// استخدام مكتبة TCPDF أو FPDF
// هنا مثال باستخدام HTML2PDF (يجب تثبيت المكتبة عبر Composer)

class PDFExporter {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // تصدير فاتورة إلى PDF
    public function exportInvoice($invoice_id) {
        $invoice = $this->getInvoiceData($invoice_id);
        if (!$invoice) {
            die("الفاتورة غير موجودة");
        }
        
        $html = $this->generateInvoiceHTML($invoice);
        $this->convertHTMLToPDF($html, "invoice_{$invoice['invoice_number']}.pdf");
    }
    
    // تصدير تقرير العميل
    public function exportCustomerReport($customer_id) {
        $customer = $this->getCustomerData($customer_id);
        if (!$customer) {
            die("العميل غير موجود");
        }
        
        $html = $this->generateCustomerReportHTML($customer);
        $this->convertHTMLToPDF($html, "customer_report_{$customer_id}.pdf");
    }
    
    // تصدير التقرير الشهري
    public function exportMonthlyReport($year, $month) {
        $data = $this->getMonthlyData($year, $month);
        $html = $this->generateMonthlyReportHTML($data, $year, $month);
        $this->convertHTMLToPDF($html, "monthly_report_{$year}_{$month}.pdf");
    }
    
    // جلب بيانات الفاتورة
    private function getInvoiceData($invoice_id) {
        $sql = "SELECT i.*, c.company_name, c.owner_name, c.phone, c.address 
                FROM invoices i 
                JOIN customers c ON i.customer_id = c.id 
                WHERE i.id = " . intval($invoice_id);
        $invoice = $this->conn->query($sql)->fetch_assoc();
        
        if ($invoice) {
            // جلب تفاصيل الفاتورة
            $items_sql = "SELECT ii.*, p.product_name 
                         FROM invoice_items ii 
                         JOIN products p ON ii.product_id = p.id 
                         WHERE ii.invoice_id = " . intval($invoice_id);
            $invoice['items'] = $this->conn->query($items_sql)->fetch_all(MYSQLI_ASSOC);
        }
        
        return $invoice;
    }
    
    // جلب بيانات العميل
    private function getCustomerData($customer_id) {
        $customer_id = intval($customer_id);
        
        $customer = $this->conn->query("SELECT * FROM customers WHERE id = $customer_id")->fetch_assoc();
        
        if ($customer) {
            $customer['invoices'] = $this->conn->query("SELECT * FROM invoices WHERE customer_id = $customer_id ORDER BY invoice_date DESC")->fetch_all(MYSQLI_ASSOC);
            $customer['payments'] = $this->conn->query("SELECT * FROM payments WHERE customer_id = $customer_id ORDER BY payment_date DESC")->fetch_all(MYSQLI_ASSOC);
            $customer['visits'] = $this->conn->query("SELECT * FROM visits WHERE customer_id = $customer_id ORDER BY visit_date DESC")->fetch_all(MYSQLI_ASSOC);
        }
        
        return $customer;
    }
    
    // جلب البيانات الشهرية
    private function getMonthlyData($year, $month) {
        $start_date = "$year-$month-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $data = [];
        $data['total_sales'] = $this->conn->query("SELECT IFNULL(SUM(total_amount), 0) as total FROM invoices WHERE invoice_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
        $data['total_payments'] = $this->conn->query("SELECT IFNULL(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
        $data['invoices'] = $this->conn->query("SELECT i.*, c.company_name FROM invoices i JOIN customers c ON i.customer_id = c.id WHERE i.invoice_date BETWEEN '$start_date' AND '$end_date' ORDER BY i.invoice_date")->fetch_all(MYSQLI_ASSOC);
        
        return $data;
    }
    
    // توليد HTML للفاتورة
    private function generateInvoiceHTML($invoice) {
        $html = '
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; direction: rtl; text-align: right; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #667eea; padding-bottom: 20px; }
                .header h1 { color: #667eea; margin: 0; }
                .info-table { width: 100%; margin-bottom: 30px; }
                .info-table td { padding: 8px; }
                .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                .items-table th { background-color: #667eea; color: white; padding: 12px; text-align: right; }
                .items-table td { border: 1px solid #ddd; padding: 10px; }
                .total-row { background-color: #f8f9fa; font-weight: bold; }
                .footer { text-align: center; margin-top: 50px; color: #666; border-top: 2px solid #ddd; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🚗 نظام إدارة العملاء</h1>
                <h2>فاتورة بيع</h2>
            </div>
            
            <table class="info-table">
                <tr>
                    <td><strong>رقم الفاتورة:</strong></td>
                    <td>' . htmlspecialchars($invoice['invoice_number']) . '</td>
                    <td><strong>التاريخ:</strong></td>
                    <td>' . $invoice['invoice_date'] . '</td>
                </tr>
                <tr>
                    <td><strong>اسم العميل:</strong></td>
                    <td colspan="3">' . htmlspecialchars($invoice['company_name']) . ' - ' . htmlspecialchars($invoice['owner_name']) . '</td>
                </tr>
                <tr>
                    <td><strong>الهاتف:</strong></td>
                    <td>' . htmlspecialchars($invoice['phone']) . '</td>
                    <td><strong>العنوان:</strong></td>
                    <td>' . htmlspecialchars($invoice['address']) . '</td>
                </tr>
            </table>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>م</th>
                        <th>اسم المنتج</th>
                        <th>الكمية</th>
                        <th>سعر الوحدة</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>';
        
        $num = 1;
        foreach ($invoice['items'] as $item) {
            $html .= '
                    <tr>
                        <td>' . $num++ . '</td>
                        <td>' . htmlspecialchars($item['product_name']) . '</td>
                        <td>' . $item['quantity'] . '</td>
                        <td>' . number_format($item['unit_price'], 2) . ' ريال</td>
                        <td>' . number_format($item['total_price'], 2) . ' ريال</td>
                    </tr>';
        }
        
        $html .= '
                    <tr class="total-row">
                        <td colspan="4">الإجمالي:</td>
                        <td>' . number_format($invoice['total_amount'], 2) . ' ريال</td>
                    </tr>
                    <tr>
                        <td colspan="4">المبلغ المدفوع:</td>
                        <td>' . number_format($invoice['paid_amount'], 2) . ' ريال</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4">المتبقي:</td>
                        <td>' . number_format($invoice['remaining_amount'], 2) . ' ريال</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="footer">
                <p>شكراً لتعاملكم معنا</p>
                <p>تم الإصدار في: ' . date('Y-m-d H:i:s') . '</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    // توليد HTML لتقرير العميل
    private function generateCustomerReportHTML($customer) {
        $total_purchases = array_sum(array_column($customer['invoices'], 'total_amount'));
        $total_paid = array_sum(array_column($customer['payments'], 'amount'));
        
        $html = '
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; direction: rtl; text-align: right; }
                .header { text-align: center; margin-bottom: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; }
                .section { margin-bottom: 30px; }
                .section h3 { color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th { background-color: #667eea; color: white; padding: 10px; text-align: right; }
                td { border: 1px solid #ddd; padding: 8px; }
                .stats-box { background: #f8f9fa; padding: 15px; border-radius: 10px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>تقرير شامل عن العميل</h1>
                <h2>' . htmlspecialchars($customer['company_name']) . '</h2>
            </div>
            
            <div class="section">
                <h3>📋 المعلومات الأساسية</h3>
                <div class="stats-box">
                    <p><strong>اسم المالك:</strong> ' . htmlspecialchars($customer['owner_name']) . '</p>
                    <p><strong>الهاتف:</strong> ' . htmlspecialchars($customer['phone']) . '</p>
                    <p><strong>العنوان:</strong> ' . htmlspecialchars($customer['address']) . '</p>
                    <p><strong>تاريخ التعامل:</strong> ' . $customer['start_date'] . '</p>
                </div>
            </div>
            
            <div class="section">
                <h3>💰 الملخص المالي</h3>
                <div class="stats-box">
                    <p><strong>إجمالي المشتريات:</strong> ' . number_format($total_purchases, 2) . ' ريال</p>
                    <p><strong>إجمالي المدفوعات:</strong> ' . number_format($total_paid, 2) . ' ريال</p>
                    <p><strong>المبلغ المتبقي:</strong> ' . number_format($total_purchases - $total_paid, 2) . ' ريال</p>
                </div>
            </div>
            
            <div class="section">
                <h3>📊 الفواتير</h3>
                <table>
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>التاريخ</th>
                            <th>المبلغ</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($customer['invoices'] as $inv) {
            $status = $inv['status'] == 'paid' ? 'مدفوعة' : ($inv['status'] == 'partial' ? 'جزئي' : 'غير مدفوعة');
            $html .= '
                        <tr>
                            <td>' . htmlspecialchars($inv['invoice_number']) . '</td>
                            <td>' . $inv['invoice_date'] . '</td>
                            <td>' . number_format($inv['total_amount'], 2) . ' ريال</td>
                            <td>' . $status . '</td>
                        </tr>';
        }
        
        $html .= '
                    </tbody>
                </table>
            </div>
            
            <p style="text-align: center; margin-top: 50px; color: #666;">تم إنشاء التقرير في: ' . date('Y-m-d H:i:s') . '</p>
        </body>
        </html>';
        
        return $html;
    }
    
    // توليد HTML للتقرير الشهري
    private function generateMonthlyReportHTML($data, $year, $month) {
        $month_names = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];
        
        $html = '
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; direction: rtl; text-align: right; }
                .header { text-align: center; margin-bottom: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; }
                .summary { display: flex; justify-content: space-around; margin: 30px 0; }
                .summary-box { background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center; flex: 1; margin: 0 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #667eea; color: white; padding: 12px; text-align: right; }
                td { border: 1px solid #ddd; padding: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>📊 التقرير الشهري</h1>
                <h2>' . $month_names[$month] . ' ' . $year . '</h2>
            </div>
            
            <div class="summary">
                <div class="summary-box">
                    <h3>إجمالي المبيعات</h3>
                    <h2>' . number_format($data['total_sales'], 2) . ' ريال</h2>
                </div>
                <div class="summary-box">
                    <h3>إجمالي المدفوعات</h3>
                    <h2>' . number_format($data['total_payments'], 2) . ' ريال</h2>
                </div>
                <div class="summary-box">
                    <h3>عدد الفواتير</h3>
                    <h2>' . count($data['invoices']) . '</h2>
                </div>
            </div>
            
            <h3 style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px;">تفاصيل الفواتير</h3>
            <table>
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>العميل</th>
                        <th>التاريخ</th>
                        <th>المبلغ</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($data['invoices'] as $inv) {
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($inv['invoice_number']) . '</td>
                        <td>' . htmlspecialchars($inv['company_name']) . '</td>
                        <td>' . $inv['invoice_date'] . '</td>
                        <td>' . number_format($inv['total_amount'], 2) . ' ريال</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            
            <p style="text-align: center; margin-top: 50px; color: #666;">تم إنشاء التقرير في: ' . date('Y-m-d H:i:s') . '</p>
        </body>
        </html>';
        
        return $html;
    }
    
    // تحويل HTML إلى PDF
    private function convertHTMLToPDF($html, $filename) {
        // طريقة 1: استخدام wkhtmltopdf
        // exec("wkhtmltopdf - $filename", $html);
        
        // طريقة 2: استخدام DomPDF (بسيطة ولا تحتاج تثبيت خارجي)
        // require_once 'dompdf/autoload.inc.php';
        // $dompdf = new \Dompdf\Dompdf();
        // $dompdf->loadHtml($html);
        // $dompdf->render();
        // $dompdf->stream($filename);
        
        // طريقة 3: عرض HTML مباشرة للطباعة
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        echo '<script>window.print();</script>';
    }
}

// معالجة الطلبات
if (isset($_GET['action'])) {
    $exporter = new PDFExporter($conn);
    
    switch ($_GET['action']) {
        case 'invoice':
            if (isset($_GET['id'])) {
                $exporter->exportInvoice($_GET['id']);
            }
            break;
            
        case 'customer':
            if (isset($_GET['id'])) {
                $exporter->exportCustomerReport($_GET['id']);
            }
            break;
            
        case 'monthly':
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? date('m');
            $exporter->exportMonthlyReport($year, $month);
            break;
    }
}
?>