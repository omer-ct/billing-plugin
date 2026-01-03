<?php
/**
 * Customer Management Class
 *
 * @package Black_Rock_Billing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BRB_Customers {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_customer_actions'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=brb_bill',
            __('Customers', 'black-rock-billing'),
            __('Customers', 'black-rock-billing'),
            'manage_options',
            'brb-customers',
            array($this, 'render_customers_page')
        );
    }
    
    /**
     * Handle customer actions
     */
    public function handle_customer_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle view customer
        if (isset($_GET['page']) && $_GET['page'] === 'brb-customers' && isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['customer_id'])) {
            // Will be handled in render method
        }
    }
    
    /**
     * Render customers page
     */
    public function render_customers_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if viewing single customer
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['customer_id'])) {
            $this->render_customer_detail(intval($_GET['customer_id']));
            return;
        }
        
        $this->render_customers_list();
    }
    
    /**
     * Render customers list
     */
    private function render_customers_list() {
        $customers = $this->get_all_customers();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Customers', 'black-rock-billing'); ?></h1>
            <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/add')); ?>" class="page-title-action"><?php _e('Add New Customer', 'black-rock-billing'); ?></a>
            <hr class="wp-header-end">
            
            <div class="brb-customers-container">
                <?php if (empty($customers)): ?>
                    <div class="brb-no-customers">
                        <p><?php _e('No customers found.', 'black-rock-billing'); ?></p>
                        <p><a href="<?php echo esc_url(home_url('/billing-dashboard/customers/add')); ?>" class="button button-primary"><?php _e('Add New Customer', 'black-rock-billing'); ?></a></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="column-name"><?php _e('Customer', 'black-rock-billing'); ?></th>
                                <th class="column-email"><?php _e('Email', 'black-rock-billing'); ?></th>
                                <th class="column-bills"><?php _e('Total Bills', 'black-rock-billing'); ?></th>
                                <th class="column-billed"><?php _e('Total Billed', 'black-rock-billing'); ?></th>
                                <th class="column-paid"><?php _e('Total Paid', 'black-rock-billing'); ?></th>
                                <th class="column-pending"><?php _e('Total Pending', 'black-rock-billing'); ?></th>
                                <th class="column-actions"><?php _e('Actions', 'black-rock-billing'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): 
                                $total_bills = count(brb_get_customer_bills($customer->ID));
                                $total_billed = brb_get_customer_total_billed($customer->ID);
                                $total_paid = brb_get_customer_total_paid($customer->ID);
                                $net_pending = brb_get_customer_net_pending($customer->ID);
                            ?>
                                <tr>
                                    <td class="column-name">
                                        <strong><?php echo esc_html(brb_format_customer_name($customer->display_name)); ?></strong>
                                    </td>
                                    <td class="column-email">
                                        <a href="mailto:<?php echo esc_attr($customer->user_email); ?>"><?php echo esc_html($customer->user_email); ?></a>
                                    </td>
                                    <td class="column-bills">
                                        <strong><?php echo $total_bills; ?></strong>
                                    </td>
                                    <td class="column-billed">
                                        <?php echo brb_format_currency($total_billed); ?>
                                    </td>
                                    <td class="column-paid" style="color: #00a32a;">
                                        <?php echo brb_format_currency($total_paid); ?>
                                    </td>
                                    <td class="column-pending" style="color: <?php echo $net_pending >= 0 ? '#00a32a' : '#dc2626'; ?>;">
                                        <strong><?php echo $net_pending >= 0 ? '' : '-'; ?><?php echo brb_format_currency(abs($net_pending)); ?></strong>
                                    </td>
                                    <td class="column-actions">
                                        <div class="brb-customer-actions-inline">
                                            <a href="<?php echo esc_url(add_query_arg(array('page' => 'brb-customers', 'action' => 'view', 'customer_id' => $customer->ID), admin_url('admin.php'))); ?>" class="brb-action-btn brb-action-view" title="<?php _e('View Details', 'black-rock-billing'); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </a>
                                            <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/edit/' . $customer->ID)); ?>" class="brb-action-btn brb-action-edit" title="<?php _e('Edit Customer', 'black-rock-billing'); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </a>
                                            <a href="<?php echo esc_url(add_query_arg(array('post_type' => 'brb_bill', 'brb_customer' => $customer->ID), admin_url('post-new.php'))); ?>" class="brb-action-btn brb-action-bill" title="<?php _e('Create Bill', 'black-rock-billing'); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                    <polyline points="14 2 14 8 20 8"></polyline>
                                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                                    <polyline points="10 9 9 9 8 9"></polyline>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .brb-customers-container {
                margin-top: 20px;
            }
            .brb-no-customers {
                padding: 40px;
                text-align: center;
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .column-name { width: 15%; }
            .column-email { width: 20%; }
            .column-bills { width: 10%; text-align: center; }
            .column-billed { width: 12%; text-align: right; }
            .column-paid { width: 12%; text-align: right; }
            .column-pending { width: 12%; text-align: right; }
            .column-actions { width: 19%; }
            .column-actions .button {
                margin: 2px;
            }
        </style>
        <?php
    }
    
    /**
     * Render customer detail page
     */
    private function render_customer_detail($customer_id) {
        $customer = get_userdata($customer_id);
        
        if (!$customer) {
            wp_die(__('Customer not found.', 'black-rock-billing'));
        }
        
        $bills = brb_get_customer_bills($customer_id, array('orderby' => 'date', 'order' => 'DESC'));
        $total_billed = brb_get_customer_total_billed($customer_id);
        $total_paid = brb_get_customer_total_paid($customer_id);
        $net_pending = brb_get_customer_net_pending($customer_id);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php printf(__('Customer: %s', 'black-rock-billing'), esc_html(brb_format_customer_name($customer->display_name))); ?>
            </h1>
            <a href="<?php echo esc_url(remove_query_arg(array('action', 'customer_id'))); ?>" class="page-title-action"><?php _e('← Back to Customers', 'black-rock-billing'); ?></a>
            <a href="<?php echo esc_url(get_edit_user_link($customer_id)); ?>" class="page-title-action"><?php _e('Edit Customer', 'black-rock-billing'); ?></a>
            <hr class="wp-header-end">
            
            <div class="brb-customer-detail">
                <div class="brb-customer-info">
                    <div class="brb-info-box">
                        <h2><?php _e('Customer Information', 'black-rock-billing'); ?></h2>
                        <?php
                        $phone = brb_get_customer_phone($customer_id);
                        $display_name = brb_format_customer_name($customer->display_name);
                        ?>
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Name', 'black-rock-billing'); ?></th>
                                <td><?php echo esc_html($display_name); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Email', 'black-rock-billing'); ?></th>
                                <td><a href="mailto:<?php echo esc_attr($customer->user_email); ?>"><?php echo esc_html($customer->user_email); ?></a></td>
                            </tr>
                            <?php if ($phone): ?>
                            <tr>
                                <th><?php _e('Phone', 'black-rock-billing'); ?></th>
                                <td><a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th><?php _e('User ID', 'black-rock-billing'); ?></th>
                                <td><?php echo $customer->ID; ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Registration Date', 'black-rock-billing'); ?></th>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($customer->user_registered)); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="brb-summary-boxes">
                        <div class="brb-summary-box">
                            <h3><?php _e('Total Bills', 'black-rock-billing'); ?></h3>
                            <p class="brb-big-number"><?php echo count($bills); ?></p>
                        </div>
                        <div class="brb-summary-box">
                            <h3><?php _e('Total Billed', 'black-rock-billing'); ?></h3>
                            <p class="brb-big-number"><?php echo brb_format_currency($total_billed); ?></p>
                        </div>
                        <div class="brb-summary-box brb-paid">
                            <h3><?php _e('Total Paid', 'black-rock-billing'); ?></h3>
                            <p class="brb-big-number"><?php echo brb_format_currency($total_paid); ?></p>
                        </div>
                        <div class="brb-summary-box brb-pending" style="border-left-color: <?php echo $net_pending >= 0 ? '#ef4444' : '#dc2626'; ?>;">
                            <h3><?php _e('Pending', 'black-rock-billing'); ?></h3>
                            <p class="brb-big-number" style="color: <?php echo $net_pending >= 0 ? '#00a32a' : '#dc2626'; ?>; font-weight: 700;">
                                <?php echo $net_pending >= 0 ? '' : '-'; ?><?php echo brb_format_currency(abs($net_pending)); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="brb-customer-bills">
                    <h2><?php _e('Customer Bills', 'black-rock-billing'); ?></h2>
                    
                    <div class="brb-bills-actions">
                        <a href="<?php echo esc_url(add_query_arg(array('post_type' => 'brb_bill', 'brb_customer' => $customer_id), admin_url('post-new.php'))); ?>" class="button button-primary">
                            <?php _e('Create New Bill', 'black-rock-billing'); ?>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg(array('post_type' => 'brb_bill', 'brb_customer' => $customer_id), admin_url('edit.php'))); ?>" class="button">
                            <?php _e('View All Bills', 'black-rock-billing'); ?>
                        </a>
                    </div>
                    
                    <?php if (empty($bills)): ?>
                        <p><?php _e('This customer has no bills yet.', 'black-rock-billing'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Bill Number', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Date', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Due Date', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Total', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Paid', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Pending', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Status', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Actions', 'black-rock-billing'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bills as $bill): ?>
                                    <?php
                                    $bill_number = get_post_meta($bill->ID, '_brb_bill_number', true);
                                    $bill_date = get_post_meta($bill->ID, '_brb_bill_date', true);
                                    $due_date = get_post_meta($bill->ID, '_brb_due_date', true);
                                    $adjusted_total = brb_get_adjusted_bill_total($bill->ID);
                                    $paid = brb_get_paid_amount($bill->ID);
                                    $net_pending = brb_get_net_pending_amount($bill->ID);
                                    $status = brb_get_bill_status($bill->ID);
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($bill_number ?: 'N/A'); ?></strong></td>
                                        <td><?php echo $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '—'; ?></td>
                                        <td><?php echo $due_date ? date_i18n(get_option('date_format'), strtotime($due_date)) : '—'; ?></td>
                                        <td><?php echo brb_format_currency($adjusted_total); ?></td>
                                        <td style="color: #00a32a;"><?php echo brb_format_currency($paid); ?></td>
                                        <td style="color: <?php echo $net_pending >= 0 ? '#00a32a' : '#dc2626'; ?>;">
                                            <strong><?php echo $net_pending >= 0 ? '' : '-'; ?><?php echo brb_format_currency(abs($net_pending)); ?></strong>
                                        </td>
                                        <td>
                                            <span class="brb-status brb-status-<?php echo esc_attr($status); ?>">
                                                <?php echo esc_html(ucfirst($status)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($bill->ID)); ?>" class="button button-small">
                                                <?php _e('Edit', 'black-rock-billing'); ?>
                                            </a>
                                            <a href="<?php echo esc_url(home_url('/billing-dashboard/bill/' . $bill->ID)); ?>" target="_blank" class="button button-small">
                                                <?php _e('View', 'black-rock-billing'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
            .brb-customer-detail {
                margin-top: 20px;
            }
            .brb-customer-info {
                display: grid;
                grid-template-columns: 1fr 2fr;
                gap: 20px;
                margin-bottom: 30px;
            }
            .brb-info-box,
            .brb-summary-boxes {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 20px;
            }
            .brb-info-box h2,
            .brb-customer-bills h2 {
                margin-top: 0;
            }
            .brb-summary-boxes {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            .brb-summary-box {
                text-align: center;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .brb-summary-box h3 {
                margin: 0 0 10px 0;
                font-size: 0.9em;
                color: #666;
                text-transform: uppercase;
            }
            .brb-big-number {
                font-size: 1.8em;
                font-weight: bold;
                margin: 0;
                color: #1a1a1a;
            }
            .brb-summary-box.brb-paid .brb-big-number {
                color: #00a32a;
            }
            .brb-bills-actions {
                margin-bottom: 20px;
            }
            .brb-bills-actions .button {
                margin-right: 10px;
            }
            .brb-status {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .brb-status-draft { background-color: #f0f0f1; color: #50575e; }
            .brb-status-sent { background-color: #2271b1; color: #fff; }
            .brb-status-paid { background-color: #00a32a; color: #fff; }
            .brb-status-overdue { background-color: #d63638; color: #fff; }
            .brb-status-cancelled { background-color: #50575e; color: #fff; }
            @media (max-width: 1200px) {
                .brb-customer-info {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }
    
    /**
     * Get all customers (users who have bills)
     */
    private function get_all_customers() {
        global $wpdb;
        
        $customer_ids = $wpdb->get_col("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_brb_customer_id' 
            AND meta_value != ''
        ");
        
        $customers = array();
        if (!empty($customer_ids)) {
            $customers = get_users(array(
                'include' => $customer_ids,
                'orderby' => 'display_name',
                'order' => 'ASC'
            ));
        }
        
        return $customers;
    }
}

// Initialize
new BRB_Customers();

