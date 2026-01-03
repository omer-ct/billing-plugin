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
        $paid = brb_get_paid_amount($bill_id);
        $pending = brb_get_pending_amount($bill_id);
        $status = brb_get_bill_status($bill_id);
        
        $customer = get_userdata($customer_id);
        
        // Generate HTML content
        $html = self::get_pdf_html($bill, $bill_number, $bill_date, $due_date, $customer, $items, $total, $paid, $pending, $status);
        
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
    private static function get_pdf_html($bill, $bill_number, $bill_date, $due_date, $customer, $items, $total, $paid, $pending, $status) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    margin: 0;
                    padding: 20px;
                    color: #333;
                }
                .header {
                    margin-bottom: 30px;
                    border-bottom: 2px solid #333;
                    padding-bottom: 20px;
                }
                .company-info {
                    float: left;
                    width: 50%;
                }
                .bill-info {
                    float: right;
                    width: 45%;
                    text-align: right;
                }
                .clear {
                    clear: both;
                }
                .bill-details {
                    margin: 30px 0;
                }
                .bill-to {
                    background-color: #f5f5f5;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .bill-to h3 {
                    margin-top: 0;
                    font-size: 14px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                table th {
                    background-color: #333;
                    color: #fff;
                    padding: 10px;
                    text-align: left;
                    font-weight: bold;
                }
                table td {
                    padding: 10px;
                    border-bottom: 1px solid #ddd;
                }
                .total-row {
                    font-weight: bold;
                    background-color: #f9f9f9;
                }
                .total-section {
                    margin-top: 20px;
                    text-align: right;
                }
                .total-section table {
                    width: 300px;
                    margin-left: auto;
                }
                .footer {
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    font-size: 10px;
                    color: #666;
                }
                .status {
                    display: inline-block;
                    padding: 5px 10px;
                    border-radius: 3px;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .status-paid {
                    background-color: #00a32a;
                    color: #fff;
                }
                .status-pending {
                    background-color: #d63638;
                    color: #fff;
                }
                .status-draft {
                    background-color: #f0f0f1;
                    color: #50575e;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-info">
                    <h1 style="margin: 0; font-size: 24px;"><?php echo esc_html(get_bloginfo('name')); ?></h1>
                    <p style="margin: 5px 0; color: #666;"><?php echo esc_html(get_bloginfo('description')); ?></p>
                </div>
                <div class="bill-info">
                    <h2 style="margin: 0; font-size: 20px;"><?php _e('BILL', 'black-rock-billing'); ?></h2>
                    <p style="margin: 5px 0;"><strong><?php _e('Bill Number:', 'black-rock-billing'); ?></strong> <?php echo esc_html($bill_number ?: 'N/A'); ?></p>
                    <p style="margin: 5px 0;"><strong><?php _e('Date:', 'black-rock-billing'); ?></strong> <?php echo $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '—'; ?></p>
                    <?php if ($due_date): ?>
                        <p style="margin: 5px 0;"><strong><?php _e('Due Date:', 'black-rock-billing'); ?></strong> <?php echo date_i18n(get_option('date_format'), strtotime($due_date)); ?></p>
                    <?php endif; ?>
                    <p style="margin: 5px 0;">
                        <strong><?php _e('Status:', 'black-rock-billing'); ?></strong>
                        <span class="status status-<?php echo esc_attr($status); ?>"><?php echo esc_html(ucfirst($status)); ?></span>
                    </p>
                </div>
                <div class="clear"></div>
            </div>
            
            <div class="bill-details">
                <div class="bill-to">
                    <h3><?php _e('Bill To:', 'black-rock-billing'); ?></h3>
                    <?php if ($customer): ?>
                        <p style="margin: 5px 0;"><strong><?php echo esc_html($customer->display_name); ?></strong></p>
                        <p style="margin: 5px 0;"><?php echo esc_html($customer->user_email); ?></p>
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
                
                <div class="total-section">
                    <table>
                        <tr>
                            <td><strong><?php _e('Subtotal:', 'black-rock-billing'); ?></strong></td>
                            <td style="text-align: right;"><?php echo brb_format_currency($total); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Paid Amount:', 'black-rock-billing'); ?></td>
                            <td style="text-align: right;"><?php echo brb_format_currency($paid); ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong><?php _e('Balance Due:', 'black-rock-billing'); ?></strong></td>
                            <td style="text-align: right; color: <?php echo $pending > 0 ? '#d63638' : '#00a32a'; ?>;">
                                <strong><?php echo brb_format_currency($pending); ?></strong>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php if (!empty($bill->post_content)): ?>
                    <div style="margin-top: 30px;">
                        <h3 style="font-size: 14px; margin-bottom: 10px;"><?php _e('Notes:', 'black-rock-billing'); ?></h3>
                        <p style="color: #666; line-height: 1.6;"><?php echo wp_kses_post(nl2br($bill->post_content)); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                <p><?php echo esc_html(get_bloginfo('name')); ?> - <?php echo esc_html(get_bloginfo('url')); ?></p>
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
        $pdf->SetTitle('Bill - ' . $bill_number);
        $pdf->SetSubject('Bill');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'bill-' . sanitize_file_name($bill_number) . '.pdf';
        
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
        
        $filename = 'bill-' . sanitize_file_name($bill_number) . '.pdf';
        $dompdf->stream($filename, array('Attachment' => 1));
        exit;
    }
    
    /**
     * Generate print-friendly HTML (fallback - uses browser print to PDF)
     */
    private static function generate_print_html($html, $bill_number) {
        // Add print script and auto-print
        $html = str_replace('</body>', '
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
        </body>', $html);
        
        // Output HTML for browser print-to-PDF
        echo $html;
        exit;
    }
}

