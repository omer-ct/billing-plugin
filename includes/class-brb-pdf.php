<?php
/**
 * PDF Generation Class
 *
 * @package Black_Rock_Billing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BRB_PDF {
    
    /**
     * Generate PDF for a bill
     */
    public static function generate_pdf($bill_id) {
        $bill = get_post($bill_id);
        if (!$bill || $bill->post_type !== 'brb_bill') {
            return false;
        }
        
        $bill_number = get_post_meta($bill_id, '_brb_bill_number', true);
        $bill_date = get_post_meta($bill_id, '_brb_bill_date', true);
        $due_date = get_post_meta($bill_id, '_brb_due_date', true);
        $customer_id = get_post_meta($bill_id, '_brb_customer_id', true);
        $items = brb_get_bill_items($bill_id);
        $total = brb_get_bill_total($bill_id);
        $return_items = brb_get_return_items($bill_id);
        $return_total = brb_get_return_total($bill_id);
        $adjusted_total = brb_get_adjusted_bill_total($bill_id);
        $paid = brb_get_paid_amount($bill_id);
        $pending = brb_get_pending_amount($bill_id);
        $refund_due = brb_get_refund_due($bill_id);
        $status = brb_get_bill_status($bill_id);
        
        $customer = get_userdata($customer_id);
        
        // Generate HTML content
        $html = self::get_pdf_html($bill, $bill_number, $bill_date, $due_date, $customer, $items, $total, $return_items, $return_total, $adjusted_total, $paid, $pending, $refund_due, $status);
        
        // Use DomPDF if available, otherwise use print-friendly HTML
        if (class_exists('Dompdf\Dompdf')) {
            return self::generate_with_dompdf($html, $bill_number);
        } elseif (class_exists('TCPDF')) {
            return self::generate_with_tcpdf($html, $bill_number);
        } else {
            return self::generate_print_html($html, $bill_number);
        }
    }
    
    /**
     * Get PDF HTML content
     */
    private static function get_pdf_html($bill, $bill_number, $bill_date, $due_date, $customer, $items, $total, $return_items, $return_total, $adjusted_total, $paid, $pending, $refund_due, $status) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    margin: 0;
                }
                * {
                    margin: 0;
                    padding: 0;
                }
                body {
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 12px;
                    line-height: 1.5;
                    margin: 0;
                    padding: 30px;
                    color: #000000;
                }
                @media print {
                    @page {
                        margin: 0;
                    }
                    body {
                        margin: 0;
                        padding: 30px;
                    }
                }
                a {
                    color: inherit;
                    text-decoration: none;
                    pointer-events: none;
                }
                .header {
                    margin-bottom: 25px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #000000;
                }
                .company-info {
                    float: left;
                    width: 50%;
                }
                .company-info h1 {
                    font-size: 18px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .company-info p {
                    font-size: 11px;
                    color: #666666;
                }
                .bill-info {
                    float: right;
                    width: 45%;
                    text-align: right;
                }
                .bill-info h2 {
                    font-size: 20px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .bill-info p {
                    margin: 3px 0;
                    font-size: 11px;
                }
                .clear {
                    clear: both;
                }
                .bill-details {
                    margin: 20px 0;
                }
                .bill-to {
                    margin-bottom: 20px;
                }
                .bill-to h3 {
                    font-size: 12px;
                    font-weight: bold;
                    margin-bottom: 8px;
                }
                .bill-to p {
                    margin: 3px 0;
                    font-size: 11px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                }
                table thead th {
                    background-color: #000000;
                    color: #ffffff;
                    padding: 8px;
                    text-align: left;
                    font-weight: bold;
                    font-size: 11px;
                    border: 1px solid #000000;
                }
                table tbody td {
                    padding: 8px;
                    border: 1px solid #cccccc;
                    font-size: 11px;
                }
                .total-section {
                    margin-top: 20px;
                    text-align: right;
                }
                .total-section table {
                    width: 300px;
                    margin-left: auto;
                    border: 1px solid #cccccc;
                }
                .total-section table td {
                    padding: 8px;
                    border: 1px solid #cccccc;
                    font-size: 11px;
                }
                .total-row {
                    font-weight: bold;
                    background-color: #f0f0f0;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 1px solid #cccccc;
                    font-size: 10px;
                    color: #666666;
                    text-align: center;
                }
                .status {
                    display: inline-block;
                    padding: 2px 6px;
                    font-weight: bold;
                    font-size: 10px;
                }
                .return-section {
                    margin-top: 20px;
                }
                .return-section h3 {
                    font-size: 12px;
                    font-weight: bold;
                    margin-bottom: 10px;
                    color: #dc2626;
                }
                .return-table thead th {
                    background-color: #000000;
                    color: #ffffff;
                }
                .notes-section {
                    margin-top: 20px;
                }
                .notes-section h3 {
                    font-size: 12px;
                    font-weight: bold;
                    margin-bottom: 8px;
                }
                .notes-section p {
                    font-size: 11px;
                    line-height: 1.5;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-info">
                    <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
                    <?php if (get_bloginfo('description')): ?>
                        <p><?php echo esc_html(get_bloginfo('description')); ?></p>
                    <?php endif; ?>
                </div>
                <div class="bill-info">
                    <p><strong><?php _e('Invoice Number:', 'black-rock-billing'); ?></strong> <?php echo esc_html($bill_number ?: 'N/A'); ?></p>
                    <p><strong><?php _e('Date:', 'black-rock-billing'); ?></strong> <?php echo $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '—'; ?></p>
                    <?php if ($due_date): ?>
                        <p><strong><?php _e('Due Date:', 'black-rock-billing'); ?></strong> <?php echo date_i18n(get_option('date_format'), strtotime($due_date)); ?></p>
                    <?php endif; ?>
                    <p><strong><?php _e('Status:', 'black-rock-billing'); ?></strong> <?php echo esc_html(ucfirst($status)); ?></p>
                </div>
                <div class="clear"></div>
            </div>
            
            <div class="bill-details">
                <div class="bill-to">
                    <h3><?php _e('Invoice To:', 'black-rock-billing'); ?></h3>
                    <?php if ($customer): ?>
                        <p><strong><?php echo esc_html($customer->display_name); ?></strong></p>
                        <p><?php echo esc_html($customer->user_email); ?></p>
                    <?php endif; ?>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th><?php _e('Description', 'black-rock-billing'); ?></th>
                            <th style="text-align: center;"><?php _e('Quantity', 'black-rock-billing'); ?></th>
                            <th style="text-align: right;"><?php _e('Rate', 'black-rock-billing'); ?></th>
                            <th style="text-align: right;"><?php _e('Total', 'black-rock-billing'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['description']); ?></td>
                                    <td style="text-align: center;"><?php echo esc_html($item['quantity']); ?></td>
                                    <td style="text-align: right;"><?php echo brb_format_currency($item['rate']); ?></td>
                                    <td style="text-align: right;"><?php echo brb_format_currency(floatval($item['quantity']) * floatval($item['rate'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center;"><?php _e('No items found.', 'black-rock-billing'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if (!empty($return_items)): ?>
                <div class="return-section">
                    <h3><?php _e('Return Items', 'black-rock-billing'); ?></h3>
                    <table class="return-table">
                        <thead>
                            <tr>
                                <th><?php _e('Description', 'black-rock-billing'); ?></th>
                                <th style="text-align: center;"><?php _e('Quantity', 'black-rock-billing'); ?></th>
                                <th style="text-align: right;"><?php _e('Rate', 'black-rock-billing'); ?></th>
                                <th style="text-align: right;"><?php _e('Total', 'black-rock-billing'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($return_items as $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['description']); ?></td>
                                    <td style="text-align: center;"><?php echo esc_html($item['quantity']); ?></td>
                                    <td style="text-align: right;"><?php echo brb_format_currency($item['rate']); ?></td>
                                    <td style="text-align: right;">-<?php echo brb_format_currency(floatval($item['quantity']) * floatval($item['rate'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3" style="color: #dc2626;"><strong><?php _e('Total Returns:', 'black-rock-billing'); ?></strong></td>
                                <td style="text-align: right; color: #dc2626;"><strong>-<?php echo brb_format_currency($return_total); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
                
                <div class="total-section">
                    <table>
                        <tr>
                            <td><strong><?php _e('Subtotal:', 'black-rock-billing'); ?></strong></td>
                            <td style="text-align: right;"><?php echo brb_format_currency($total); ?></td>
                        </tr>
                        <?php if (!empty($return_items) && $return_total > 0): ?>
                        <tr>
                            <td style="color: #dc2626;"><?php _e('Return Total:', 'black-rock-billing'); ?></td>
                            <td style="text-align: right; color: #dc2626;">-<?php echo brb_format_currency($return_total); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Adjusted Total:', 'black-rock-billing'); ?></strong></td>
                            <td style="text-align: right;"><strong><?php echo brb_format_currency($adjusted_total); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><?php _e('Paid Amount:', 'black-rock-billing'); ?></td>
                            <td style="text-align: right;"><?php echo brb_format_currency($paid); ?></td>
                        </tr>
                        <tr class="total-row">
                            <?php if ($refund_due > 0): ?>
                                <td><strong><?php _e('Refund Due to Customer:', 'black-rock-billing'); ?></strong></td>
                                <td style="text-align: right;"><strong><?php echo brb_format_currency($refund_due); ?></strong></td>
                            <?php else: ?>
                                <td><strong><?php _e('Balance Due:', 'black-rock-billing'); ?></strong></td>
                                <td style="text-align: right;"><strong><?php echo brb_format_currency($pending); ?></strong></td>
                            <?php endif; ?>
                        </tr>
                    </table>
                </div>
                
                <?php if (!empty($bill->post_content)): ?>
                    <div class="notes-section">
                        <h3><?php _e('Notes:', 'black-rock-billing'); ?></h3>
                        <p><?php echo wp_kses_post(nl2br($bill->post_content)); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                <p><?php _e('This is a computer-generated document. No signature is required.', 'black-rock-billing'); ?></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate PDF using TCPDF
     */
    private static function generate_with_tcpdf($html, $bill_number) {
        require_once BRB_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('Black Rock Billing');
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('Invoice - ' . $bill_number);
        $pdf->SetSubject('Invoice');
        
        // Remove timestamps
        $pdf->SetCreationDate('');
        $pdf->SetModDate('');
        
        // Disable all headers and footers
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetHeaderData('', 0, '', '');
        $pdf->SetFooterData('', 0, '', '');
        
        // Set margins to remove any header/footer space
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'invoice-' . sanitize_file_name($bill_number) . '.pdf';
        
        $pdf->Output($filename, 'D');
        exit;
    }
    
    /**
     * Generate with DomPDF
     */
    private static function generate_with_dompdf($html, $bill_number) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Remove timestamps from metadata if possible
        try {
            $canvas = $dompdf->getCanvas();
            if (method_exists($canvas, 'get_cpdf')) {
                $cpdf = $canvas->get_cpdf();
                if ($cpdf && method_exists($cpdf, 'setInfo')) {
                    $cpdf->setInfo('CreationDate', '');
                    $cpdf->setInfo('ModDate', '');
                }
            }
        } catch (Exception $e) {
            // Ignore if metadata removal fails
        }
        
        $filename = 'invoice-' . sanitize_file_name($bill_number) . '.pdf';
        $dompdf->stream($filename, array('Attachment' => 1));
        exit;
    }
    
    /**
     * Generate print-friendly HTML (fallback - uses browser print to PDF)
     */
    private static function generate_print_html($html, $bill_number) {
        // Add print script and auto-print with settings to hide URL
        $html = str_replace('</body>', '
        <script>
            window.onload = function() {
                // Hide any URL that might appear
                var style = document.createElement("style");
                style.textContent = "@media print { @page { margin: 0; } body { margin: 0 !important; padding: 30px !important; } }";
                document.head.appendChild(style);
                window.print();
            };
        </script>
        </body>', $html);
        
        // Output HTML for browser print-to-PDF
        echo $html;
        exit;
    }
}

