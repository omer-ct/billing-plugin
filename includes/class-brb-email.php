<?php
/**
 * Email Notifications Class
 *
 * @package Black_Rock_Billing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BRB_Email {
    
    /**
     * Send bill notification email
     */
    public static function send_bill_notification($bill_id, $type = 'created') {
        $bill = get_post($bill_id);
        if (!$bill || $bill->post_type !== 'brb_bill') {
            return false;
        }
        
        $customer_id = get_post_meta($bill_id, '_brb_customer_id', true);
        $customer = get_userdata($customer_id);
        
        if (!$customer || !$customer->user_email) {
            return false;
        }
        
        $bill_number = get_post_meta($bill_id, '_brb_bill_number', true);
        $bill_date = get_post_meta($bill_id, '_brb_bill_date', true);
        $total = brb_get_bill_total($bill_id);
        $status = brb_get_bill_status($bill_id);
        
        $subject = '';
        $message = '';
        
        switch ($type) {
            case 'created':
                $subject = sprintf(__('New Bill Created - %s', 'black-rock-billing'), $bill_number);
                $message = self::get_bill_created_email($bill_id, $customer, $bill_number, $bill_date, $total);
                break;
                
            case 'sent':
                $subject = sprintf(__('Bill Sent - %s', 'black-rock-billing'), $bill_number);
                $message = self::get_bill_sent_email($bill_id, $customer, $bill_number, $bill_date, $total);
                break;
                
            case 'paid':
                $subject = sprintf(__('Bill Paid - %s', 'black-rock-billing'), $bill_number);
                $message = self::get_bill_paid_email($bill_id, $customer, $bill_number, $bill_date, $total);
                break;
                
            case 'overdue':
                $subject = sprintf(__('Bill Overdue - %s', 'black-rock-billing'), $bill_number);
                $message = self::get_bill_overdue_email($bill_id, $customer, $bill_number, $bill_date, $total);
                break;
                
            case 'updated':
                $subject = sprintf(__('Bill Updated - %s', 'black-rock-billing'), $bill_number);
                $message = self::get_bill_updated_email($bill_id, $customer, $bill_number, $bill_date, $total);
                break;
        }
        
        if (empty($subject) || empty($message)) {
            return false;
        }
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $from_email = get_option('admin_email');
        $from_name = get_bloginfo('name');
        
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        
        return wp_mail($customer->user_email, $subject, $message, $headers);
    }
    
    /**
     * Get bill created email template
     */
    private static function get_bill_created_email($bill_id, $customer, $bill_number, $bill_date, $total) {
        $bill_url = home_url('/billing-dashboard/bill/' . $bill_id);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2271b1; color: #fff; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .bill-info { background-color: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #2271b1; }
                .button { display: inline-block; padding: 12px 24px; background-color: #2271b1; color: #fff; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('New Bill Created', 'black-rock-billing'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(__('Hello %s,', 'black-rock-billing'), esc_html($customer->display_name)); ?></p>
                    <p><?php _e('A new bill has been created for you. Please find the details below:', 'black-rock-billing'); ?></p>
                    
                    <div class="bill-info">
                        <p><strong><?php _e('Bill Number:', 'black-rock-billing'); ?></strong> <?php echo esc_html($bill_number); ?></p>
                        <p><strong><?php _e('Bill Date:', 'black-rock-billing'); ?></strong> <?php echo $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '—'; ?></p>
                        <p><strong><?php _e('Total Amount:', 'black-rock-billing'); ?></strong> <?php echo brb_format_currency($total); ?></p>
                    </div>
                    
                    <p><?php _e('You can view and download your bill by clicking the button below:', 'black-rock-billing'); ?></p>
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($bill_url); ?>" class="button"><?php _e('View Bill', 'black-rock-billing'); ?></a>
                    </p>
                    
                    <p><?php _e('Thank you for your business!', 'black-rock-billing'); ?></p>
                </div>
                <div class="footer">
                    <p><?php echo esc_html(get_bloginfo('name')); ?></p>
                    <p><?php echo esc_html(get_bloginfo('url')); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get bill sent email template
     */
    private static function get_bill_sent_email($bill_id, $customer, $bill_number, $bill_date, $total) {
        return self::get_bill_created_email($bill_id, $customer, $bill_number, $bill_date, $total);
    }
    
    /**
     * Get bill paid email template
     */
    private static function get_bill_paid_email($bill_id, $customer, $bill_number, $bill_date, $total) {
        $bill_url = home_url('/billing-dashboard/bill/' . $bill_id);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #00a32a; color: #fff; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .bill-info { background-color: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #00a32a; }
                .button { display: inline-block; padding: 12px 24px; background-color: #00a32a; color: #fff; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Bill Paid - Thank You!', 'black-rock-billing'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(__('Hello %s,', 'black-rock-billing'), esc_html($customer->display_name)); ?></p>
                    <p><?php _e('We have received your payment. Thank you!', 'black-rock-billing'); ?></p>
                    
                    <div class="bill-info">
                        <p><strong><?php _e('Bill Number:', 'black-rock-billing'); ?></strong> <?php echo esc_html($bill_number); ?></p>
                        <p><strong><?php _e('Amount Paid:', 'black-rock-billing'); ?></strong> <?php echo brb_format_currency($total); ?></p>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($bill_url); ?>" class="button"><?php _e('View Receipt', 'black-rock-billing'); ?></a>
                    </p>
                </div>
                <div class="footer">
                    <p><?php echo esc_html(get_bloginfo('name')); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get bill overdue email template
     */
    private static function get_bill_overdue_email($bill_id, $customer, $bill_number, $bill_date, $total) {
        $bill_url = home_url('/billing-dashboard/bill/' . $bill_id);
        $pending = brb_get_pending_amount($bill_id);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #d63638; color: #fff; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .bill-info { background-color: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #d63638; }
                .button { display: inline-block; padding: 12px 24px; background-color: #d63638; color: #fff; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Bill Overdue Notice', 'black-rock-billing'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(__('Hello %s,', 'black-rock-billing'), esc_html($customer->display_name)); ?></p>
                    <p><?php _e('This is a reminder that your bill is now overdue. Please make payment as soon as possible.', 'black-rock-billing'); ?></p>
                    
                    <div class="bill-info">
                        <p><strong><?php _e('Bill Number:', 'black-rock-billing'); ?></strong> <?php echo esc_html($bill_number); ?></p>
                        <p><strong><?php _e('Amount Due:', 'black-rock-billing'); ?></strong> <?php echo brb_format_currency($pending); ?></p>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($bill_url); ?>" class="button"><?php _e('Pay Now', 'black-rock-billing'); ?></a>
                    </p>
                </div>
                <div class="footer">
                    <p><?php echo esc_html(get_bloginfo('name')); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get bill updated email template
     */
    private static function get_bill_updated_email($bill_id, $customer, $bill_number, $bill_date, $total) {
        $bill_url = home_url('/billing-dashboard/bill/' . $bill_id);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2271b1; color: #fff; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .bill-info { background-color: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #2271b1; }
                .button { display: inline-block; padding: 12px 24px; background-color: #2271b1; color: #fff; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Bill Updated', 'black-rock-billing'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(__('Hello %s,', 'black-rock-billing'), esc_html($customer->display_name)); ?></p>
                    <p><?php _e('Your bill has been updated. Please review the changes below:', 'black-rock-billing'); ?></p>
                    
                    <div class="bill-info">
                        <p><strong><?php _e('Bill Number:', 'black-rock-billing'); ?></strong> <?php echo esc_html($bill_number); ?></p>
                        <p><strong><?php _e('Total Amount:', 'black-rock-billing'); ?></strong> <?php echo brb_format_currency($total); ?></p>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($bill_url); ?>" class="button"><?php _e('View Updated Bill', 'black-rock-billing'); ?></a>
                    </p>
                </div>
                <div class="footer">
                    <p><?php echo esc_html(get_bloginfo('name')); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

