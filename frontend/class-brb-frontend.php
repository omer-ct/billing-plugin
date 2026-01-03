<?php
/**
 * Frontend Class
 *
 * @package Black_Rock_Billing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BRB_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_custom_routes'));
        add_action('wp', array($this, 'check_user_access'));
        // Removed automatic menu injection - users can add menu items manually
        // add_action('wp_nav_menu_items', array($this, 'add_menu_items'), 10, 2);
        add_action('wp_ajax_brb_update_payment', array($this, 'ajax_update_payment'));
        add_action('wp_ajax_brb_create_bill', array($this, 'ajax_create_bill'));
        add_action('wp_ajax_brb_delete_bill', array($this, 'ajax_delete_bill'));
        add_action('wp_ajax_brb_search_bills', array($this, 'ajax_search_bills'));
        add_action('wp_ajax_nopriv_brb_search_bills', array($this, 'ajax_search_bills'));
        add_action('wp_ajax_brb_save_returns', array($this, 'ajax_save_returns'));
        add_action('wp_ajax_brb_update_bill', array($this, 'ajax_update_bill'));
        add_action('wp_ajax_brb_save_customer', array($this, 'ajax_save_customer'));
        
        // PDF download
        add_action('init', array($this, 'handle_pdf_download'));
        
        // Settings save handler
        add_action('admin_post_brb_save_settings', array($this, 'save_settings'));
        add_action('admin_post_nopriv_brb_save_settings', array($this, 'save_settings'));
    }
    
    /**
     * Add rewrite rules
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^billing-dashboard/?$', 'index.php?brb_page=dashboard', 'top');
        add_rewrite_rule('^billing-dashboard/bill/([0-9]+)/?$', 'index.php?brb_page=bill&brb_bill_id=$matches[1]', 'top');
        add_rewrite_rule('^billing-dashboard/create/?$', 'index.php?brb_page=create', 'top');
        add_rewrite_rule('^billing-dashboard/edit/([0-9]+)/?$', 'index.php?brb_page=edit&brb_bill_id=$matches[1]', 'top');
        add_rewrite_rule('^billing-dashboard/bills/?$', 'index.php?brb_page=all-bills', 'top');
        add_rewrite_rule('^billing-dashboard/customers/?$', 'index.php?brb_page=customers', 'top');
        add_rewrite_rule('^billing-dashboard/customers/([0-9]+)/?$', 'index.php?brb_page=customer-detail&brb_customer_id=$matches[1]', 'top');
        add_rewrite_rule('^billing-dashboard/customers/add/?$', 'index.php?brb_page=customer-add', 'top');
        add_rewrite_rule('^billing-dashboard/customers/edit/([0-9]+)/?$', 'index.php?brb_page=customer-edit&brb_customer_id=$matches[1]', 'top');
        add_rewrite_rule('^billing-dashboard/settings/?$', 'index.php?brb_page=settings', 'top');
    }
    
    /**
     * Handle PDF download
     */
    public function handle_pdf_download() {
        if (isset($_GET['brb_download_pdf']) && isset($_GET['bill_id'])) {
            $bill_id = intval($_GET['bill_id']);
            
            // Check permissions
            if (!brb_can_user_view_bill($bill_id)) {
                wp_die(__('You do not have permission to download this bill.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
            }
            
            BRB_PDF::generate_pdf($bill_id);
        }
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'brb_page';
        $vars[] = 'brb_bill_id';
        $vars[] = 'brb_customer_id';
        return $vars;
    }
    
    /**
     * Handle custom routes
     */
    public function handle_custom_routes() {
        $page = get_query_var('brb_page');
        
        if (!$page) {
            return;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/billing-dashboard')));
            exit;
        }
        
        switch ($page) {
            case 'dashboard':
                $this->render_dashboard();
                exit;
                
            case 'bill':
                $bill_id = intval(get_query_var('brb_bill_id'));
                $this->render_bill_view($bill_id);
                exit;
                
            case 'create':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to create bills.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $this->render_create_bill();
                exit;
                
            case 'edit':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to edit bills.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $bill_id = intval(get_query_var('brb_bill_id'));
                $this->render_edit_bill($bill_id);
                exit;
                
            case 'all-bills':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to view all bills.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $this->render_all_bills();
                exit;
                
            case 'customers':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to view customers.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $this->render_customers_list();
                exit;
                
            case 'customer-detail':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to view customer details.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $customer_id = intval(get_query_var('brb_customer_id'));
                $this->render_customer_detail($customer_id);
                exit;
                
            case 'settings':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to view settings.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $this->render_settings();
                exit;
                
            case 'customer-add':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to add customers.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $this->render_add_customer();
                exit;
                
            case 'customer-edit':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to edit customers.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $customer_id = intval(get_query_var('brb_customer_id'));
                $this->render_edit_customer($customer_id);
                exit;
        }
    }
    
    /**
     * Check user access
     */
    public function check_user_access() {
        $page = get_query_var('brb_page');
        
        if ($page === 'bill') {
            $bill_id = intval(get_query_var('brb_bill_id'));
            
            if ($bill_id && !brb_can_user_view_bill($bill_id)) {
                wp_die(__('You do not have permission to view this bill.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
            }
        }
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        $user_id = get_current_user_id();
        
        // Get bills - if admin, show all bills, otherwise only customer's bills
        if (current_user_can('manage_options')) {
            // Admin can see all bills
            $args = array(
                'post_type' => 'brb_bill',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'orderby' => 'post_date',
                'order' => 'DESC'
            );
            $query = new WP_Query($args);
            $bills = $query->posts;
            
            // Calculate totals from all bills for admin
            $total_billed = 0;
            $total_paid = 0;
            $net_pending = 0;
            foreach ($bills as $bill) {
                $total_billed += brb_get_adjusted_bill_total($bill->ID);
                $total_paid += brb_get_paid_amount($bill->ID);
                $net_pending += brb_get_net_pending_amount($bill->ID);
            }
        } else {
            // Regular users see only their bills
            $bills = brb_get_customer_bills($user_id, array('orderby' => 'date', 'order' => 'DESC'));
            
            $total_billed = brb_get_customer_total_billed($user_id);
            $total_paid = brb_get_customer_total_paid($user_id);
            $net_pending = brb_get_customer_net_pending($user_id);
        }
        
        // Get header and footer
        get_header();
        ?>
        <div class="brb-dashboard-container">
            <div class="brb-dashboard-header">
                <h1><?php _e('Billing Dashboard', 'black-rock-billing'); ?></h1>
                <p class="brb-welcome-message">
                    <?php 
                    $current_user = wp_get_current_user();
                    printf(__('Welcome, %s', 'black-rock-billing'), esc_html($current_user->display_name)); 
                    ?>
                </p>
                
                <div class="brb-dashboard-nav">
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-nav-link active">
                        <?php _e('Dashboard', 'black-rock-billing'); ?>
                    </a>
                    <?php if (current_user_can('manage_options')): ?>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="brb-nav-link">
                            <?php _e('Customers', 'black-rock-billing'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/add')); ?>" class="brb-nav-link">
                            <?php _e('Add Customer', 'black-rock-billing'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="brb-nav-link">
                            <?php _e('Create Bill', 'black-rock-billing'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/settings')); ?>" class="brb-nav-link">
                            <?php _e('Settings', 'black-rock-billing'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="brb-summary-cards">
                <div class="brb-summary-card">
                    <h3><?php _e('Total Billed', 'black-rock-billing'); ?></h3>
                    <p class="brb-amount"><?php echo brb_format_currency($total_billed); ?></p>
                </div>
                <div class="brb-summary-card">
                    <h3><?php _e('Total Paid', 'black-rock-billing'); ?></h3>
                    <p class="brb-amount brb-paid"><?php echo brb_format_currency($total_paid); ?></p>
                </div>
                <div class="brb-summary-card" style="border-top-color: <?php echo $net_pending >= 0 ? '#ef4444' : '#dc2626'; ?>;">
                    <h3><?php _e('Pending', 'black-rock-billing'); ?></h3>
                    <p class="brb-amount" style="color: <?php echo $net_pending >= 0 ? '#00a32a' : '#dc2626'; ?>;">
                        <?php echo $net_pending >= 0 ? '' : '-'; ?><?php echo brb_format_currency(abs($net_pending)); ?>
                    </p>
                </div>
            </div>
            
            <div class="brb-bills-section">
                <div class="brb-bills-header">
                    <h2><?php _e('Your Bills', 'black-rock-billing'); ?></h2>
                    <?php if (current_user_can('manage_options')): ?>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="button button-primary brb-create-bill-btn">
                            <?php _e('Create New Bill', 'black-rock-billing'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="brb-search-filter">
                    <input type="text" id="brb-search-bills" placeholder="<?php _e('Search by bill number, customer name, email, phone, date, or amount...', 'black-rock-billing'); ?>" class="brb-search-input" />
                    <select id="brb-filter-status" class="brb-filter-select">
                        <option value=""><?php _e('All Statuses', 'black-rock-billing'); ?></option>
                        <option value="draft"><?php _e('Draft', 'black-rock-billing'); ?></option>
                        <option value="sent"><?php _e('Sent', 'black-rock-billing'); ?></option>
                        <option value="paid"><?php _e('Paid', 'black-rock-billing'); ?></option>
                        <option value="overdue"><?php _e('Overdue', 'black-rock-billing'); ?></option>
                        <option value="cancelled"><?php _e('Cancelled', 'black-rock-billing'); ?></option>
                    </select>
                    <button type="button" id="brb-reset-filters" class="button"><?php _e('Reset', 'black-rock-billing'); ?></button>
                </div>
                
                <?php if (empty($bills)): ?>
                    <p class="brb-no-bills"><?php _e('You don\'t have any bills yet.', 'black-rock-billing'); ?></p>
                <?php else: ?>
                    <table class="brb-bills-table" id="brb-bills-table">
                        <thead>
                            <tr>
                                <th><?php _e('Bill Number', 'black-rock-billing'); ?></th>
                                <th><?php _e('Customer', 'black-rock-billing'); ?></th>
                                <th><?php _e('Date', 'black-rock-billing'); ?></th>
                                <th><?php _e('Total', 'black-rock-billing'); ?></th>
                                <th><?php _e('Paid', 'black-rock-billing'); ?></th>
                                <th><?php _e('Pending', 'black-rock-billing'); ?></th>
                                <th><?php _e('Status', 'black-rock-billing'); ?></th>
                                <th><?php _e('Actions', 'black-rock-billing'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="brb-bills-tbody">
                            <?php foreach ($bills as $bill): ?>
                                <?php
                                $bill_number = get_post_meta($bill->ID, '_brb_bill_number', true);
                                $bill_date = get_post_meta($bill->ID, '_brb_bill_date', true);
                                $due_date = get_post_meta($bill->ID, '_brb_due_date', true);
                                $original_total = brb_get_bill_total($bill->ID);
                                $adjusted_total = brb_get_adjusted_bill_total($bill->ID);
                                $paid = brb_get_paid_amount($bill->ID);
                                $net_pending = brb_get_net_pending_amount($bill->ID);
                                $status = brb_get_bill_status($bill->ID);
                                $customer_id = get_post_meta($bill->ID, '_brb_customer_id', true);
                                
                                // Get customer data for search and display
                                $customer_name = '';
                                $customer_name_display = '—';
                                $customer_email = '';
                                $customer_phone = '';
                                if ($customer_id) {
                                    $customer = get_userdata($customer_id);
                                    if ($customer) {
                                        $customer_name = strtolower(brb_format_customer_name($customer->display_name));
                                        $customer_name_display = brb_format_customer_name($customer->display_name);
                                        $customer_email = strtolower($customer->user_email);
                                        $customer_phone = strtolower(brb_get_customer_phone($customer_id));
                                    }
                                }
                                ?>
                                <tr class="brb-bill-row" 
                                    data-bill-number="<?php echo esc_attr(strtolower($bill_number)); ?>" 
                                    data-status="<?php echo esc_attr($status); ?>" 
                                    data-total="<?php echo esc_attr($adjusted_total); ?>"
                                    data-customer-name="<?php echo esc_attr($customer_name); ?>"
                                    data-customer-email="<?php echo esc_attr($customer_email); ?>"
                                    data-customer-phone="<?php echo esc_attr($customer_phone); ?>">
                                    <td><strong><?php echo esc_html($bill_number ?: 'N/A'); ?></strong></td>
                                    <td><strong><?php echo esc_html($customer_name_display); ?></strong></td>
                                    <td><strong><?php echo $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '—'; ?></strong></td>
                                    <td><strong><?php echo brb_format_currency($adjusted_total); ?></strong></td>
                                    <td style="color: #00a32a;"><strong><?php echo brb_format_currency($paid); ?></strong></td>
                                    <td style="color: <?php echo $net_pending >= 0 ? '#00a32a' : '#dc2626'; ?>;">
                                        <strong><?php echo $net_pending >= 0 ? '' : '-'; ?><?php echo brb_format_currency(abs($net_pending)); ?></strong>
                                    </td>
                                    <td>
                                        <span class="brb-status brb-status-<?php echo esc_attr($status); ?>">
                                            <strong><?php echo esc_html(ucfirst($status)); ?></strong>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="<?php echo esc_url(home_url('/billing-dashboard/bill/' . $bill->ID)); ?>" class="brb-action-btn brb-action-view" title="<?php _e('View Bill', 'black-rock-billing'); ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * Render bill view
     */
    public function render_bill_view($bill_id) {
        if (!$bill_id || !brb_can_user_view_bill($bill_id)) {
            wp_die(__('Bill not found or access denied.', 'black-rock-billing'), __('Error', 'black-rock-billing'), array('response' => 404));
        }
        
        $bill = get_post($bill_id);
        $bill_number = get_post_meta($bill_id, '_brb_bill_number', true);
        $bill_date = get_post_meta($bill_id, '_brb_bill_date', true);
        $due_date = get_post_meta($bill_id, '_brb_due_date', true);
        $customer_id = get_post_meta($bill_id, '_brb_customer_id', true);
        $items = brb_get_bill_items($bill_id);
        $original_total = brb_get_bill_total($bill_id);
        $return_items = brb_get_return_items($bill_id);
        $return_total = brb_get_return_total($bill_id);
        $adjusted_total = brb_get_adjusted_bill_total($bill_id);
        $paid = brb_get_paid_amount($bill_id);
        $pending = brb_get_pending_amount($bill_id);
        $refund_due = brb_get_refund_due($bill_id);
        $status = brb_get_bill_status($bill_id);
        
        $customer = get_userdata($customer_id);
        
        get_header();
        ?>
        <div class="brb-bill-view-container">
            <div class="brb-bill-header">
                <div class="brb-bill-header-links">
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-back-link">
                        ← <?php _e('Back to Dashboard', 'black-rock-billing'); ?>
                    </a>
                    <?php if ($customer_id && current_user_can('manage_options')): ?>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/' . $customer_id)); ?>" class="brb-back-link">
                            ← <?php _e('Back to Customer', 'black-rock-billing'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <h1><?php _e('Bill Details', 'black-rock-billing'); ?></h1>
            </div>
            
            <div class="brb-bill-document">
                <div class="brb-bill-header-info">
                    <div class="brb-bill-company">
                        <h2><?php bloginfo('name'); ?></h2>
                        <p><?php bloginfo('description'); ?></p>
                    </div>
                    <div class="brb-bill-meta">
                        <p><strong><?php _e('Bill Number:', 'black-rock-billing'); ?></strong> <?php echo esc_html($bill_number ?: 'N/A'); ?></p>
                        <p><strong><?php _e('Bill Date:', 'black-rock-billing'); ?></strong> <?php echo $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '—'; ?></p>
                        <p><strong><?php _e('Due Date:', 'black-rock-billing'); ?></strong> <?php echo $due_date ? date_i18n(get_option('date_format'), strtotime($due_date)) : '—'; ?></p>
                        <p><strong><?php _e('Status:', 'black-rock-billing'); ?></strong> 
                            <span class="brb-status brb-status-<?php echo esc_attr($status); ?>">
                                <?php echo esc_html(ucfirst($status)); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="brb-bill-customer">
                    <h3><?php _e('Bill To:', 'black-rock-billing'); ?></h3>
                    <?php if ($customer): 
                        $phone = brb_get_customer_phone($customer_id);
                        $display_name = brb_format_customer_name($customer->display_name);
                    ?>
                        <p><strong><?php echo esc_html($display_name); ?></strong></p>
                        <p>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            <?php echo esc_html($customer->user_email); ?>
                        </p>
                        <?php if ($phone): ?>
                        <p>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: middle; margin-right: 6px;">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            <a href="tel:<?php echo esc_attr($phone); ?>"><?php echo esc_html($phone); ?></a>
                        </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="brb-bill-items">
                    <table class="brb-items-table">
                        <thead>
                            <tr>
                                <th><?php _e('Description', 'black-rock-billing'); ?></th>
                                <th><?php _e('Quantity', 'black-rock-billing'); ?></th>
                                <th><?php _e('Rate', 'black-rock-billing'); ?></th>
                                <th><?php _e('Total', 'black-rock-billing'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($items)): ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo esc_html($item['description']); ?></td>
                                        <td><?php echo esc_html($item['quantity']); ?></td>
                                        <td><?php echo brb_format_currency($item['rate']); ?></td>
                                        <td><?php echo brb_format_currency(floatval($item['quantity']) * floatval($item['rate'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4"><?php _e('No items found.', 'black-rock-billing'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="brb-total-row">
                                <td colspan="3"><strong><?php _e('Original Total:', 'black-rock-billing'); ?></strong></td>
                                <td><strong><?php echo brb_format_currency($original_total); ?></strong></td>
                            </tr>
                            <?php if ($return_total > 0): ?>
                            <tr style="color: #ef4444;">
                                <td colspan="3"><strong><?php _e('Return Amount:', 'black-rock-billing'); ?></strong></td>
                                <td><strong>-<?php echo brb_format_currency($return_total); ?></strong></td>
                            </tr>
                            <tr class="brb-total-row" style="border-top: 2px solid #e2e8f0;">
                                <td colspan="3"><strong><?php _e('Adjusted Total:', 'black-rock-billing'); ?></strong></td>
                                <td><strong><?php echo brb_format_currency($adjusted_total); ?></strong></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="3"><strong><?php _e('Paid Amount:', 'black-rock-billing'); ?></strong></td>
                                <td><?php echo brb_format_currency($paid); ?></td>
                            </tr>
                            <?php if ($refund_due > 0): ?>
                            <tr class="brb-refund-row" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);">
                                <td colspan="3"><strong style="color: #991b1b;"><?php _e('Refund Due to Customer:', 'black-rock-billing'); ?></strong></td>
                                <td><strong style="color: #dc2626; font-size: 1.1em;"><?php echo brb_format_currency($refund_due); ?></strong></td>
                            </tr>
                            <?php else: ?>
                            <tr class="brb-pending-row">
                                <td colspan="3"><strong><?php _e('Pending Amount:', 'black-rock-billing'); ?></strong></td>
                                <td><strong><?php echo brb_format_currency($pending); ?></strong></td>
                            </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>
                
                <?php if (!empty($return_items)): ?>
                <div class="brb-return-items-section">
                    <h3><?php _e('Return Items', 'black-rock-billing'); ?></h3>
                    <table class="brb-items-table">
                        <thead>
                            <tr>
                                <th><?php _e('Description', 'black-rock-billing'); ?></th>
                                <th><?php _e('Quantity', 'black-rock-billing'); ?></th>
                                <th><?php _e('Rate', 'black-rock-billing'); ?></th>
                                <th><?php _e('Total', 'black-rock-billing'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($return_items as $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['description']); ?></td>
                                    <td><?php echo esc_html($item['quantity']); ?></td>
                                    <td><?php echo brb_format_currency($item['rate']); ?></td>
                                    <td style="color: #ef4444;">-<?php echo brb_format_currency(floatval($item['quantity']) * floatval($item['rate'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="color: #ef4444;">
                                <td colspan="3"><strong><?php _e('Total Returns:', 'black-rock-billing'); ?></strong></td>
                                <td><strong>-<?php echo brb_format_currency($return_total); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($bill->post_content)): ?>
                    <div class="brb-bill-notes">
                        <h3><?php _e('Notes', 'black-rock-billing'); ?></h3>
                        <div class="brb-notes-content">
                            <?php echo wp_kses_post(wpautop($bill->post_content)); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="brb-bill-actions">
                    <?php if (current_user_can('manage_options')): ?>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/edit/' . $bill_id)); ?>" class="brb-edit-bill">
                            <?php _e('Edit Bill', 'black-rock-billing'); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(add_query_arg(array('brb_download_pdf' => '1', 'bill_id' => $bill_id), home_url())); ?>" class="brb-download-pdf">
                        <?php _e('Download PDF', 'black-rock-billing'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * Add menu items - DISABLED
     * 
     * This function is disabled. To add billing links to your menu:
     * 1. Go to Appearance > Menus in WordPress admin
     * 2. Click "Add Custom Links"
     * 3. Add these URLs:
     *    - Billing Dashboard: /billing-dashboard
     *    - Create Bill: /billing-dashboard/create (admin only)
     *    - Customers: /billing-dashboard/customers (admin only)
     *    - Settings: /billing-dashboard/settings (admin only)
     * 4. Save the menu
     */
    /*
    public function add_menu_items($items, $args) {
        if (!is_user_logged_in()) {
            return $items;
        }
        
        // Add billing dashboard link for all logged-in users
        $dashboard_link = '<li class="menu-item brb-dashboard-menu-item"><a href="' . esc_url(home_url('/billing-dashboard')) . '">' . __('Billing Dashboard', 'black-rock-billing') . '</a></li>';
        
        // Add create bill link for admins
        if (current_user_can('manage_options')) {
            $create_link = '<li class="menu-item brb-create-bill-menu-item"><a href="' . esc_url(home_url('/billing-dashboard/create')) . '">' . __('Create Bill', 'black-rock-billing') . '</a></li>';
            $items .= $dashboard_link . $create_link;
        } else {
            $items .= $dashboard_link;
        }
        
        return $items;
    }
    */
    
    /**
     * Render create bill page (frontend)
     */
    public function render_create_bill() {
        $customers = get_users(array('orderby' => 'display_name'));
        
        // Get customer ID from URL if provided
        $preselected_customer = isset($_GET['brb_customer']) ? intval($_GET['brb_customer']) : 0;
        
        // Prepare customers data for JavaScript
        $customers_data = array();
        foreach ($customers as $customer) {
            $phone = brb_get_customer_phone($customer->ID);
            $customers_data[] = array(
                'id' => $customer->ID,
                'name' => brb_format_customer_name($customer->display_name),
                'email' => $customer->user_email,
                'phone' => $phone,
                'display' => brb_format_customer_name($customer->display_name) . ' (' . $customer->user_email . ')'
            );
        }
        
        // Set preselected customer display
        $preselected_display = '';
        if ($preselected_customer) {
            $preselected_customer_obj = get_userdata($preselected_customer);
            if ($preselected_customer_obj) {
                $preselected_display = brb_format_customer_name($preselected_customer_obj->display_name) . ' (' . $preselected_customer_obj->user_email . ')';
            }
        }
        
        get_header();
        ?>
        <script type="text/javascript">
            var brbCustomersData = <?php echo json_encode($customers_data); ?>;
        </script>
        <div class="brb-create-bill-container">
            <div class="brb-page-header">
                <h1><?php _e('Create New Bill', 'black-rock-billing'); ?></h1>
                <div class="brb-dashboard-nav">
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-nav-link">
                        <?php _e('Dashboard', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="brb-nav-link">
                        <?php _e('Customers', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/add')); ?>" class="brb-nav-link">
                        <?php _e('Add Customer', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="brb-nav-link active">
                        <?php _e('Create Bill', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/settings')); ?>" class="brb-nav-link">
                        <?php _e('Settings', 'black-rock-billing'); ?>
                    </a>
                </div>
            </div>
            
            <form id="brb-create-bill-form" class="brb-bill-form">
                <?php wp_nonce_field('brb_create_bill', 'brb_create_bill_nonce'); ?>
                
                <div class="brb-form-section">
                    <h2><?php _e('Bill Information', 'black-rock-billing'); ?></h2>
                    
                    <div class="brb-form-grid">
                        <div class="brb-form-row">
                            <label for="brb_customer_search"><?php _e('Customer', 'black-rock-billing'); ?> <span class="required">*</span></label>
                            <div class="brb-customer-search-wrapper">
                                <input type="text" id="brb_customer_search" class="brb-form-input" placeholder="<?php _e('Type to search customer...', 'black-rock-billing'); ?>" value="<?php echo esc_attr($preselected_display); ?>" autocomplete="off" />
                                <input type="hidden" id="brb_customer_id" name="brb_customer_id" value="<?php echo esc_attr($preselected_customer); ?>" required />
                                <div id="brb-customer-dropdown" class="brb-customer-dropdown"></div>
                            </div>
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_bill_date"><?php _e('Bill Date', 'black-rock-billing'); ?></label>
                            <input type="date" id="brb_bill_date" name="brb_bill_date" value="<?php echo date('Y-m-d'); ?>" required class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_due_date"><?php _e('Due Date', 'black-rock-billing'); ?></label>
                            <input type="date" id="brb_due_date" name="brb_due_date" class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_status"><?php _e('Status', 'black-rock-billing'); ?></label>
                            <select id="brb_status" name="brb_status" class="brb-form-select">
                                <option value="draft" selected><?php _e('Draft', 'black-rock-billing'); ?></option>
                                <option value="sent"><?php _e('Sent', 'black-rock-billing'); ?></option>
                                <option value="paid"><?php _e('Paid', 'black-rock-billing'); ?></option>
                                <option value="overdue"><?php _e('Overdue', 'black-rock-billing'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="brb-form-row brb-form-row-full">
                        <label for="brb_bill_notes"><?php _e('Notes', 'black-rock-billing'); ?></label>
                        <textarea id="brb_bill_notes" name="brb_bill_notes" rows="4" class="brb-form-textarea brb-form-textarea-full"></textarea>
                    </div>
                </div>
                
                <div class="brb-form-section">
                    <h2><?php _e('Bill Items', 'black-rock-billing'); ?></h2>
                    <div id="brb-items-container-frontend">
                        <table class="brb-items-table-frontend">
                            <thead>
                                <tr>
                                    <th><?php _e('Description', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Quantity', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Rate', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Total', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Actions', 'black-rock-billing'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="brb-items-tbody-frontend">
                                <tr class="brb-item-row-frontend">
                                    <td><input type="text" name="brb_items[0][description]" class="brb-item-description" placeholder="<?php _e('Item description', 'black-rock-billing'); ?>" /></td>
                                    <td><input type="number" name="brb_items[0][quantity]" class="brb-item-quantity" step="0.01" min="0" value="1" /></td>
                                    <td><input type="number" name="brb_items[0][rate]" class="brb-item-rate" step="0.01" min="0" /></td>
                                    <td><span class="brb-item-total"><?php echo brb_format_currency(0); ?></span></td>
                                    <td><button type="button" class="brb-icon-btn brb-icon-btn-remove brb-remove-item-frontend" title="<?php _e('Remove Item', 'black-rock-billing'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="text-align: right;"><strong><?php _e('Total Amount:', 'black-rock-billing'); ?></strong></td>
                                    <td><strong id="brb-grand-total-frontend"><?php echo brb_format_currency(0); ?></strong></td>
                                    <td><button type="button" class="brb-icon-btn brb-icon-btn-add brb-add-item-frontend" title="<?php _e('Add Item', 'black-rock-billing'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                    </button></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <div class="brb-form-section">
                    <h2><?php _e('Payment Information', 'black-rock-billing'); ?></h2>
                    <div class="brb-form-row">
                        <label for="brb_paid_amount"><?php _e('Paid Amount', 'black-rock-billing'); ?></label>
                        <input type="number" id="brb_paid_amount" name="brb_paid_amount" step="0.01" min="0" value="0" />
                    </div>
                </div>
                
                <div class="brb-form-actions">
                    <button type="submit" class="button button-primary button-large"><?php _e('Create Bill', 'black-rock-billing'); ?></button>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="button button-large"><?php _e('Cancel', 'black-rock-billing'); ?></a>
                </div>
                
                <div id="brb-form-messages"></div>
            </form>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * Render edit bill page (frontend)
     */
    public function render_edit_bill($bill_id) {
        if (!$bill_id) {
            wp_redirect(home_url('/billing-dashboard'));
            exit;
        }
        
        $bill = get_post($bill_id);
        if (!$bill || $bill->post_type !== 'brb_bill') {
            wp_redirect(home_url('/billing-dashboard'));
            exit;
        }
        
        if (!current_user_can('edit_post', $bill_id)) {
            wp_die(__('You do not have permission to edit this bill.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
        }
        
        // Get bill data
        $bill_number = get_post_meta($bill_id, '_brb_bill_number', true);
        $bill_date = get_post_meta($bill_id, '_brb_bill_date', true);
        $due_date = get_post_meta($bill_id, '_brb_due_date', true);
        $customer_id = intval(get_post_meta($bill_id, '_brb_customer_id', true));
        $status = get_post_meta($bill_id, '_brb_status', true);
        if (empty($status)) {
            $status = 'draft';
        }
        $items = brb_get_bill_items($bill_id);
        $return_items = brb_get_return_items($bill_id);
        $total = brb_get_bill_total($bill_id);
        $paid = brb_get_paid_amount($bill_id);
        $notes = $bill->post_content;
        
        $customers = get_users(array('orderby' => 'display_name'));
        
        // Prepare customers data for JavaScript
        $customers_data = array();
        foreach ($customers as $customer) {
            $phone = brb_get_customer_phone($customer->ID);
            $customers_data[] = array(
                'id' => $customer->ID,
                'name' => brb_format_customer_name($customer->display_name),
                'email' => $customer->user_email,
                'phone' => $phone,
                'display' => brb_format_customer_name($customer->display_name) . ' (' . $customer->user_email . ')'
            );
        }
        
        get_header();
        ?>
        <script type="text/javascript">
            var brbCustomersData = <?php echo json_encode($customers_data); ?>;
        </script>
        <div class="brb-create-bill-container">
            <div class="brb-page-header">
                <h1><?php _e('Edit Bill', 'black-rock-billing'); ?> - <?php echo esc_html($bill_number ?: '#' . $bill_id); ?></h1>
                <div class="brb-dashboard-nav">
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-nav-link">
                        <?php _e('Dashboard', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/bill/' . $bill_id)); ?>" class="brb-nav-link">
                        <?php _e('View Bill', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="brb-nav-link">
                        <?php _e('Customers', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/add')); ?>" class="brb-nav-link">
                        <?php _e('Add Customer', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="brb-nav-link">
                        <?php _e('Create Bill', 'black-rock-billing'); ?>
                    </a>
                </div>
            </div>
            
            <form id="brb-edit-bill-form" class="brb-bill-form">
                <?php wp_nonce_field('brb_edit_bill', 'brb_edit_bill_nonce'); ?>
                <input type="hidden" name="brb_bill_id" value="<?php echo esc_attr($bill_id); ?>" />
                
                <div class="brb-form-section">
                    <h2><?php _e('Bill Information', 'black-rock-billing'); ?></h2>
                    
                    <div class="brb-form-row brb-form-row-full">
                        <label for="brb_bill_number"><?php _e('Bill Number', 'black-rock-billing'); ?></label>
                        <input type="text" id="brb_bill_number" name="brb_bill_number" value="<?php echo esc_attr($bill_number); ?>" readonly class="brb-form-input" />
                        <p class="description"><?php _e('Bill number cannot be changed', 'black-rock-billing'); ?></p>
                    </div>
                    
                    <div class="brb-form-grid">
                        <div class="brb-form-row">
                            <label for="brb_customer_search"><?php _e('Customer', 'black-rock-billing'); ?> <span class="required">*</span></label>
                            <div class="brb-customer-search-wrapper">
                                <?php
                                $selected_customer_display = '';
                                if ($customer_id) {
                                    $selected_customer = get_userdata($customer_id);
                                    if ($selected_customer) {
                                        $selected_customer_display = brb_format_customer_name($selected_customer->display_name) . ' (' . $selected_customer->user_email . ')';
                                    }
                                }
                                ?>
                                <input type="text" id="brb_customer_search" class="brb-form-input" placeholder="<?php _e('Type to search customer...', 'black-rock-billing'); ?>" value="<?php echo esc_attr($selected_customer_display); ?>" autocomplete="off" />
                                <input type="hidden" id="brb_customer_id" name="brb_customer_id" value="<?php echo esc_attr($customer_id); ?>" required />
                                <div id="brb-customer-dropdown" class="brb-customer-dropdown"></div>
                            </div>
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_bill_date"><?php _e('Bill Date', 'black-rock-billing'); ?></label>
                            <input type="date" id="brb_bill_date" name="brb_bill_date" value="<?php echo esc_attr($bill_date ?: date('Y-m-d')); ?>" required class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_due_date"><?php _e('Due Date', 'black-rock-billing'); ?></label>
                            <input type="date" id="brb_due_date" name="brb_due_date" value="<?php echo esc_attr($due_date); ?>" class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_status"><?php _e('Status', 'black-rock-billing'); ?></label>
                            <select id="brb_status" name="brb_status" class="brb-form-select">
                                <option value="draft" <?php selected($status, 'draft'); ?>><?php _e('Draft', 'black-rock-billing'); ?></option>
                                <option value="sent" <?php selected($status, 'sent'); ?>><?php _e('Sent', 'black-rock-billing'); ?></option>
                                <option value="paid" <?php selected($status, 'paid'); ?>><?php _e('Paid', 'black-rock-billing'); ?></option>
                                <option value="overdue" <?php selected($status, 'overdue'); ?>><?php _e('Overdue', 'black-rock-billing'); ?></option>
                                <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'black-rock-billing'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="brb-form-row brb-form-row-full">
                        <label for="brb_bill_notes"><?php _e('Notes', 'black-rock-billing'); ?></label>
                        <textarea id="brb_bill_notes" name="brb_bill_notes" rows="4" class="brb-form-textarea brb-form-textarea-full"><?php echo esc_textarea($notes); ?></textarea>
                    </div>
                </div>
                
                <div class="brb-form-section">
                    <h2><?php _e('Bill Items', 'black-rock-billing'); ?></h2>
                    <div id="brb-items-container-frontend">
                        <table class="brb-items-table-frontend">
                            <thead>
                                <tr>
                                    <th><?php _e('Description', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Quantity', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Rate', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Total', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Actions', 'black-rock-billing'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="brb-items-tbody-frontend">
                                <?php if (!empty($items)): ?>
                                    <?php foreach ($items as $index => $item): ?>
                                        <tr class="brb-item-row-frontend">
                                            <td><input type="text" name="brb_items[<?php echo esc_attr($index); ?>][description]" class="brb-item-description" value="<?php echo esc_attr($item['description']); ?>" placeholder="<?php _e('Item description', 'black-rock-billing'); ?>" /></td>
                                            <td><input type="number" name="brb_items[<?php echo esc_attr($index); ?>][quantity]" class="brb-item-quantity" step="0.01" min="0" value="<?php echo esc_attr($item['quantity']); ?>" /></td>
                                            <td><input type="number" name="brb_items[<?php echo esc_attr($index); ?>][rate]" class="brb-item-rate" step="0.01" min="0" value="<?php echo esc_attr($item['rate']); ?>" /></td>
                                            <td><span class="brb-item-total"><?php echo brb_format_currency(floatval($item['quantity']) * floatval($item['rate'])); ?></span></td>
                                            <td><button type="button" class="brb-icon-btn brb-icon-btn-remove brb-remove-item-frontend" title="<?php _e('Remove Item', 'black-rock-billing'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="brb-item-row-frontend">
                                        <td><input type="text" name="brb_items[0][description]" class="brb-item-description" placeholder="<?php _e('Item description', 'black-rock-billing'); ?>" /></td>
                                        <td><input type="number" name="brb_items[0][quantity]" class="brb-item-quantity" step="0.01" min="0" value="1" /></td>
                                        <td><input type="number" name="brb_items[0][rate]" class="brb-item-rate" step="0.01" min="0" /></td>
                                        <td><span class="brb-item-total"><?php echo brb_format_currency(0); ?></span></td>
                                        <td><button type="button" class="brb-icon-btn brb-icon-btn-remove brb-remove-item-frontend" title="<?php _e('Remove Item', 'black-rock-billing'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="text-align: right;"><strong><?php _e('Total Amount:', 'black-rock-billing'); ?></strong></td>
                                    <td><strong id="brb-grand-total-frontend"><?php echo brb_format_currency($total); ?></strong></td>
                                    <td><button type="button" class="brb-icon-btn brb-icon-btn-add brb-add-item-frontend" title="<?php _e('Add Item', 'black-rock-billing'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                    </button></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <div class="brb-form-section">
                    <h2><?php _e('Return Items', 'black-rock-billing'); ?></h2>
                    <p class="description"><?php _e('Add items that have been returned. The return amount will be deducted from the bill total.', 'black-rock-billing'); ?></p>
                    <div id="brb-return-items-frontend-container">
                        <table class="brb-items-table-frontend" id="brb-returns-table-frontend">
                            <thead>
                                <tr>
                                    <th><?php _e('Description', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Quantity', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Rate', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Total', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Actions', 'black-rock-billing'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="brb-returns-tbody-frontend">
                                <?php if (!empty($return_items)): ?>
                                    <?php foreach ($return_items as $index => $item): ?>
                                        <tr class="brb-return-row-frontend" data-index="<?php echo esc_attr($index); ?>">
                                            <td><input type="text" name="brb_return_items[<?php echo esc_attr($index); ?>][description]" class="brb-return-description-frontend" value="<?php echo esc_attr($item['description']); ?>" placeholder="<?php _e('Return item description', 'black-rock-billing'); ?>" /></td>
                                            <td><input type="number" name="brb_return_items[<?php echo esc_attr($index); ?>][quantity]" class="brb-return-quantity-frontend" step="0.01" min="0" value="<?php echo esc_attr($item['quantity']); ?>" /></td>
                                            <td><input type="number" name="brb_return_items[<?php echo esc_attr($index); ?>][rate]" class="brb-return-rate-frontend" step="0.01" min="0" value="<?php echo esc_attr($item['rate']); ?>" /></td>
                                            <td><span class="brb-return-total-frontend"><?php echo brb_format_currency(floatval($item['quantity']) * floatval($item['rate'])); ?></span></td>
                                            <td><button type="button" class="brb-icon-btn brb-icon-btn-remove brb-remove-return-frontend" title="<?php _e('Remove Return Item', 'black-rock-billing'); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="text-align: right;"><strong><?php _e('Return Total:', 'black-rock-billing'); ?></strong></td>
                                    <td><strong id="brb-return-grand-total-frontend"><?php echo brb_format_currency(brb_get_return_total($bill_id)); ?></strong></td>
                                    <td><button type="button" class="brb-icon-btn brb-icon-btn-add brb-add-return-frontend" title="<?php _e('Add Return Item', 'black-rock-billing'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                    </button></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <div class="brb-form-section">
                    <h2><?php _e('Payment Information', 'black-rock-billing'); ?></h2>
                    <div class="brb-form-row">
                        <label for="brb_paid_amount"><?php _e('Paid Amount', 'black-rock-billing'); ?></label>
                        <input type="number" id="brb_paid_amount" name="brb_paid_amount" step="0.01" min="0" value="<?php echo esc_attr($paid); ?>" />
                        <p class="description"><?php _e('Enter the amount that has been paid for this bill.', 'black-rock-billing'); ?></p>
                    </div>
                    <div class="brb-payment-summary">
                        <p><strong><?php _e('Original Total:', 'black-rock-billing'); ?></strong> <span id="brb-original-total-display"><?php echo brb_format_currency($total); ?></span></p>
                        <p><strong><?php _e('Return Total:', 'black-rock-billing'); ?></strong> <span id="brb-return-total-display" style="color: #dc2626;">-<?php echo brb_format_currency(brb_get_return_total($bill_id)); ?></span></p>
                        <p><strong><?php _e('Adjusted Total:', 'black-rock-billing'); ?></strong> <span id="brb-adjusted-total-display"><?php echo brb_format_currency(brb_get_adjusted_bill_total($bill_id)); ?></span></p>
                        <?php 
                        $refund_due_edit = brb_get_refund_due($bill_id);
                        if ($refund_due_edit > 0): ?>
                            <p id="brb-pending-row" style="display: none;"><strong><?php _e('Pending Amount:', 'black-rock-billing'); ?></strong> <span id="brb-pending-display"></span></p>
                            <p id="brb-refund-row"><strong><?php _e('Refund Due to Customer:', 'black-rock-billing'); ?></strong> <span id="brb-refund-display" style="color: #dc2626; font-weight: 700;"><?php echo brb_format_currency($refund_due_edit); ?></span></p>
                        <?php else: ?>
                            <p id="brb-pending-row"><strong><?php _e('Pending Amount:', 'black-rock-billing'); ?></strong> <span id="brb-pending-display"><?php echo brb_format_currency(brb_get_pending_amount($bill_id)); ?></span></p>
                            <p id="brb-refund-row" style="display: none;"><strong><?php _e('Refund Due to Customer:', 'black-rock-billing'); ?></strong> <span id="brb-refund-display" style="color: #dc2626; font-weight: 700;"></span></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="brb-form-actions">
                    <button type="submit" class="button button-primary button-large"><?php _e('Save Changes', 'black-rock-billing'); ?></button>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/bill/' . $bill_id)); ?>" class="button button-large"><?php _e('Cancel', 'black-rock-billing'); ?></a>
                </div>
                
                <div id="brb-form-messages"></div>
            </form>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * AJAX: Create bill
     */
    public function ajax_create_bill() {
        check_ajax_referer('brb_create_bill', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'black-rock-billing')));
        }
        
        $customer_id = intval($_POST['brb_customer_id'] ?? 0);
        $bill_date = sanitize_text_field($_POST['brb_bill_date'] ?? '');
        $due_date = sanitize_text_field($_POST['brb_due_date'] ?? '');
        $status = sanitize_text_field($_POST['brb_status'] ?? 'draft');
        $notes = wp_kses_post($_POST['brb_bill_notes'] ?? '');
        $items = isset($_POST['brb_items']) ? $_POST['brb_items'] : array();
        $paid_amount = floatval($_POST['brb_paid_amount'] ?? 0);
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => __('Please select a customer.', 'black-rock-billing')));
        }
        
        // Calculate total
        $total = 0;
        $clean_items = array();
        foreach ($items as $item) {
            if (!empty($item['description'])) {
                $quantity = floatval($item['quantity'] ?? 0);
                $rate = floatval($item['rate'] ?? 0);
                $total += $quantity * $rate;
                $clean_items[] = array(
                    'description' => sanitize_text_field($item['description']),
                    'quantity' => $quantity,
                    'rate' => $rate,
                );
            }
        }
        
        // Create bill post
        $bill_data = array(
            'post_title' => __('Bill', 'black-rock-billing') . ' - ' . date_i18n(get_option('date_format')),
            'post_content' => $notes,
            'post_status' => 'publish',
            'post_type' => 'brb_bill',
        );
        
        $bill_id = wp_insert_post($bill_data);
        
        if (is_wp_error($bill_id)) {
            wp_send_json_error(array('message' => $bill_id->get_error_message()));
        }
        
        // Save meta data
        update_post_meta($bill_id, '_brb_customer_id', $customer_id);
        update_post_meta($bill_id, '_brb_bill_date', $bill_date);
        update_post_meta($bill_id, '_brb_due_date', $due_date);
        update_post_meta($bill_id, '_brb_status', $status);
        update_post_meta($bill_id, '_brb_bill_items', $clean_items);
        update_post_meta($bill_id, '_brb_total_amount', $total);
        update_post_meta($bill_id, '_brb_paid_amount', $paid_amount);
        
        // Generate bill number
        brb_generate_bill_number($bill_id);
        
        // Send email notification if status is not draft
        if ($status !== 'draft') {
            BRB_Email::send_bill_notification($bill_id, 'created');
            update_post_meta($bill_id, '_brb_email_sent', 'yes');
        }
        
        wp_send_json_success(array(
            'message' => __('Bill created successfully!', 'black-rock-billing'),
            'bill_id' => $bill_id,
            'redirect_url' => home_url('/billing-dashboard/bill/' . $bill_id)
        ));
    }
    
    /**
     * AJAX: Update payment
     */
    public function ajax_update_payment() {
        check_ajax_referer('brb_nonce', 'nonce');
        
        $bill_id = intval($_POST['bill_id'] ?? 0);
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        
        if (!$bill_id) {
            wp_send_json_error(array('message' => __('Invalid bill ID.', 'black-rock-billing')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options') && !brb_can_user_view_bill($bill_id)) {
            wp_send_json_error(array('message' => __('Permission denied.', 'black-rock-billing')));
        }
        
        $adjusted_total = brb_get_adjusted_bill_total($bill_id);
        
        // Calculate refund due if paid exceeds adjusted total
        $refund_due = 0;
        if ($paid_amount > $adjusted_total) {
            $refund_due = $paid_amount - $adjusted_total;
        }
        
        update_post_meta($bill_id, '_brb_paid_amount', $paid_amount);
        update_post_meta($bill_id, '_brb_refund_due', $refund_due);
        
        // Update status if fully paid (or overpaid)
        if ($paid_amount >= $adjusted_total) {
            update_post_meta($bill_id, '_brb_status', 'paid');
        }
        
        wp_send_json_success(array(
            'message' => __('Payment updated successfully!', 'black-rock-billing'),
            'pending' => brb_get_pending_amount($bill_id)
        ));
    }
    
    /**
     * AJAX: Delete bill
     */
    public function ajax_delete_bill() {
        check_ajax_referer('brb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'black-rock-billing')));
        }
        
        $bill_id = intval($_POST['bill_id'] ?? 0);
        
        if (!$bill_id) {
            wp_send_json_error(array('message' => __('Invalid bill ID.', 'black-rock-billing')));
        }
        
        $result = wp_delete_post($bill_id, true);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Bill deleted successfully!', 'black-rock-billing')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete bill.', 'black-rock-billing')));
        }
    }
    
    /**
     * AJAX: Search bills
     */
    public function ajax_search_bills() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'black-rock-billing')));
        }
        
        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $status_filter = sanitize_text_field($_POST['status'] ?? '');
        $user_id = get_current_user_id();
        
        // If admin, can search all bills, otherwise only their own
        $args = array(
            'post_type' => 'brb_bill',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        if (!current_user_can('manage_options')) {
            $args['meta_query'] = array(
                array(
                    'key' => '_brb_customer_id',
                    'value' => $user_id,
                    'compare' => '='
                )
            );
        }
        
        if ($status_filter) {
            if (!isset($args['meta_query'])) {
                $args['meta_query'] = array();
            }
            $args['meta_query'][] = array(
                'key' => '_brb_status',
                'value' => $status_filter,
                'compare' => '='
            );
        }
        
        if ($search_term) {
            // First, try to find customers matching the search term (name, email, or phone)
            $customer_ids = array();
            
            // Search by email
            $users_by_email = get_users(array(
                'search' => '*' . $search_term . '*',
                'search_columns' => array('user_email'),
                'fields' => 'ID'
            ));
            
            // Search by display name
            $users_by_name = get_users(array(
                'search' => '*' . $search_term . '*',
                'search_columns' => array('display_name'),
                'fields' => 'ID'
            ));
            
            // Search by phone (user meta)
            global $wpdb;
            $phone_users = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} 
                WHERE (meta_key = 'billing_phone' OR meta_key = 'phone') 
                AND meta_value LIKE %s",
                '%' . $wpdb->esc_like($search_term) . '%'
            ));
            
            // Combine all customer IDs
            $customer_ids = array_unique(array_merge(
                $users_by_email,
                $users_by_name,
                $phone_users
            ));
            
            // If we found matching customers, search bills by customer ID
            if (!empty($customer_ids)) {
                if (!isset($args['meta_query'])) {
                    $args['meta_query'] = array();
                }
                
                // Add customer ID search to meta_query
                $args['meta_query'][] = array(
                    'key' => '_brb_customer_id',
                    'value' => $customer_ids,
                    'compare' => 'IN'
                );
            }
            
            // Also search by bill number, date, and other bill fields
            // Search in post meta for bill number
            $bill_number_posts = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = '_brb_bill_number' 
                AND meta_value LIKE %s",
                '%' . $wpdb->esc_like($search_term) . '%'
            ));
            
            // Search in post meta for dates
            $date_posts = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE (meta_key = '_brb_bill_date' OR meta_key = '_brb_due_date') 
                AND meta_value LIKE %s",
                '%' . $wpdb->esc_like($search_term) . '%'
            ));
            
            // Combine all post IDs
            $post_ids = array_unique(array_merge($bill_number_posts, $date_posts));
            
            // If we found matching posts by bill number or date, include them
            if (!empty($post_ids)) {
                if (isset($args['post__in'])) {
                    $args['post__in'] = array_merge($args['post__in'], $post_ids);
                } else {
                    $args['post__in'] = $post_ids;
                }
            }
            
            // If we have customer IDs or post IDs, don't use default search
            // Otherwise, use default WordPress search
            if (empty($customer_ids) && empty($post_ids)) {
                $args['s'] = $search_term;
            } else {
                // Make sure we include posts that match the search term in title/content
                $title_posts = get_posts(array(
                    'post_type' => 'brb_bill',
                    's' => $search_term,
                    'posts_per_page' => -1,
                    'fields' => 'ids'
                ));
                
                if (!empty($title_posts)) {
                    if (isset($args['post__in'])) {
                        $args['post__in'] = array_merge($args['post__in'], $title_posts);
                    } else {
                        $args['post__in'] = $title_posts;
                    }
                }
            }
        }
        
        // If we have post__in with empty array, return no results
        if (isset($args['post__in']) && empty($args['post__in'])) {
            $bills = array();
        } else {
            $bills = get_posts($args);
        }
        
        $results = array();
        foreach ($bills as $bill) {
            $bill_number = get_post_meta($bill->ID, '_brb_bill_number', true);
            $bill_date = get_post_meta($bill->ID, '_brb_bill_date', true);
            $due_date = get_post_meta($bill->ID, '_brb_due_date', true);
            $total = brb_get_bill_total($bill->ID);
            $paid = brb_get_paid_amount($bill->ID);
            $pending = brb_get_pending_amount($bill->ID);
            $status = brb_get_bill_status($bill->ID);
            
            $results[] = array(
                'id' => $bill->ID,
                'bill_number' => $bill_number ?: 'N/A',
                'date' => $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '—',
                'due_date' => $due_date ? date_i18n(get_option('date_format'), strtotime($due_date)) : '—',
                'total' => brb_format_currency($total),
                'paid' => brb_format_currency($paid),
                'pending' => brb_format_currency($pending),
                'status' => $status,
                'view_url' => home_url('/billing-dashboard/bill/' . $bill->ID)
            );
        }
        
        wp_send_json_success(array('bills' => $results));
    }
    
    /**
     * Save settings
     */
    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to save settings.', 'black-rock-billing'));
        }
        
        check_admin_referer('brb_save_settings', 'brb_settings_nonce');
        
        if (isset($_POST['brb_currency_symbol'])) {
            update_option('brb_currency_symbol', sanitize_text_field($_POST['brb_currency_symbol']));
        }
        
        if (isset($_POST['brb_currency_position'])) {
            update_option('brb_currency_position', sanitize_text_field($_POST['brb_currency_position']));
        }
        
        if (isset($_POST['brb_bill_prefix'])) {
            update_option('brb_bill_prefix', sanitize_text_field($_POST['brb_bill_prefix']));
        }
        
        wp_redirect(add_query_arg('settings-updated', 'true', home_url('/billing-dashboard/settings')));
        exit;
    }
    
    /**
     * Render all bills page (frontend)
     */
    public function render_all_bills() {
        $args = array(
            'post_type' => 'brb_bill',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'orderby' => 'post_date',
            'order' => 'DESC'
        );
        
        $query = new WP_Query($args);
        $bills = $query->posts;
        
        get_header();
        ?>
        <div class="brb-dashboard-container">
            <div class="brb-dashboard-header">
                <h1><?php _e('All Bills', 'black-rock-billing'); ?></h1>
                <div class="brb-dashboard-nav">
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-nav-link">
                        <?php _e('Dashboard', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="brb-nav-link">
                        <?php _e('Customers', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/add')); ?>" class="brb-nav-link">
                        <?php _e('Add Customer', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="brb-nav-link">
                        <?php _e('Create Bill', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/settings')); ?>" class="brb-nav-link">
                        <?php _e('Settings', 'black-rock-billing'); ?>
                    </a>
                </div>
            </div>
            
            <div class="brb-bills-section">
                <div class="brb-bills-header">
                    <h2><?php _e('All Bills', 'black-rock-billing'); ?></h2>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="button button-primary brb-create-bill-btn">
                        <?php _e('Create New Bill', 'black-rock-billing'); ?>
                    </a>
                </div>
                
                <?php if (empty($bills)): ?>
                    <p class="brb-no-bills"><?php _e('No bills found.', 'black-rock-billing'); ?></p>
                <?php else: ?>
                    <table class="brb-bills-table">
                        <thead>
                            <tr>
                                <th><?php _e('Bill Number', 'black-rock-billing'); ?></th>
                                <th><?php _e('Customer', 'black-rock-billing'); ?></th>
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
                                $customer_id = get_post_meta($bill->ID, '_brb_customer_id', true);
                                $customer = $customer_id ? get_userdata($customer_id) : null;
                                $bill_date = get_post_meta($bill->ID, '_brb_bill_date', true);
                                $due_date = get_post_meta($bill->ID, '_brb_due_date', true);
                                $adjusted_total = brb_get_adjusted_bill_total($bill->ID);
                                $paid = brb_get_paid_amount($bill->ID);
                                $net_pending = brb_get_net_pending_amount($bill->ID);
                                $status = brb_get_bill_status($bill->ID);
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($bill_number ?: 'N/A'); ?></strong></td>
                                    <td><?php echo $customer ? esc_html(brb_format_customer_name($customer->display_name)) : '—'; ?></td>
                                        <td><strong><?php echo $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '—'; ?></strong></td>
                                        <td><strong><?php echo $due_date ? date_i18n(get_option('date_format'), strtotime($due_date)) : '—'; ?></strong></td>
                                        <td><strong><?php echo brb_format_currency($adjusted_total); ?></strong></td>
                                        <td style="color: #00a32a;"><strong><?php echo brb_format_currency($paid); ?></strong></td>
                                    <td style="color: <?php echo $net_pending >= 0 ? '#00a32a' : '#dc2626'; ?>;">
                                        <strong><?php echo $net_pending >= 0 ? '' : '-'; ?><?php echo brb_format_currency(abs($net_pending)); ?></strong>
                                    </td>
                                    <td>
                                        <span class="brb-status brb-status-<?php echo esc_attr($status); ?>">
                                            <?php echo esc_html(ucfirst($status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url(home_url('/billing-dashboard/bill/' . $bill->ID)); ?>" class="button brb-view-bill">
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
        <?php
        get_footer();
    }
    
    /**
     * Render customers list (frontend)
     */
    public function render_customers_list() {
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
        
        get_header();
        ?>
        <div class="brb-dashboard-container">
            <div class="brb-dashboard-header">
                <h1><?php _e('Customers', 'black-rock-billing'); ?></h1>
                <div class="brb-dashboard-nav">
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-nav-link">
                        <?php _e('Dashboard', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="brb-nav-link active">
                        <?php _e('Customers', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="brb-nav-link">
                        <?php _e('Create Bill', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/settings')); ?>" class="brb-nav-link">
                        <?php _e('Settings', 'black-rock-billing'); ?>
                    </a>
                </div>
            </div>
            
            <div class="brb-bills-section">
                <div class="brb-bills-header">
                    <h2><?php _e('All Customers', 'black-rock-billing'); ?></h2>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/add')); ?>" class="button button-primary">
                        <?php _e('Add New Customer', 'black-rock-billing'); ?>
                    </a>
                </div>
                
                <?php if (empty($customers)): ?>
                    <p class="brb-no-bills"><?php _e('No customers found.', 'black-rock-billing'); ?></p>
                <?php else: ?>
                    <div class="brb-customers-table-wrapper">
                        <table class="brb-customers-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Customer', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Contact', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Bills', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Total Billed', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Total Paid', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Pending', 'black-rock-billing'); ?></th>
                                    <th><?php _e('Actions', 'black-rock-billing'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                    <?php
                                    $total_bills = count(brb_get_customer_bills($customer->ID));
                                    $total_billed = brb_get_customer_total_billed($customer->ID);
                                    $total_paid = brb_get_customer_total_paid($customer->ID);
                                    $net_pending = brb_get_customer_net_pending($customer->ID);
                                    
                                    $phone = brb_get_customer_phone($customer->ID);
                                    $display_name = brb_format_customer_name($customer->display_name);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($display_name); ?></strong>
                                        </td>
                                        <td>
                                            <div class="brb-customer-contact">
                                                <a href="mailto:<?php echo esc_attr($customer->user_email); ?>" class="brb-customer-email-badge">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                        <polyline points="22,6 12,13 2,6"></polyline>
                                                    </svg>
                                                    <span><?php echo esc_html($customer->user_email); ?></span>
                                                </a>
                                                <?php if ($phone): ?>
                                                    <a href="tel:<?php echo esc_attr($phone); ?>" class="brb-customer-phone-badge">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 2 0 0 1 22 16.92z"></path>
                                                        </svg>
                                                        <span><?php echo esc_html($phone); ?></span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo $total_bills; ?></strong>
                                        </td>
                                        <td>
                                            <?php echo brb_format_currency($total_billed); ?>
                                        </td>
                                        <td style="color: #00a32a;">
                                            <?php echo brb_format_currency($total_paid); ?>
                                        </td>
                                        <td style="color: <?php echo $net_pending >= 0 ? '#00a32a' : '#dc2626'; ?>;">
                                            <strong><?php echo $net_pending >= 0 ? '' : '-'; ?><?php echo brb_format_currency(abs($net_pending)); ?></strong>
                                        </td>
                                        <td>
                                            <div class="brb-customer-actions-inline">
                                                <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/' . $customer->ID)); ?>" class="brb-action-btn brb-action-view" title="<?php _e('View Details', 'black-rock-billing'); ?>">
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
                                                <a href="<?php echo esc_url(home_url('/billing-dashboard/create?brb_customer=' . $customer->ID)); ?>" class="brb-action-btn brb-action-bill" title="<?php _e('Create Bill', 'black-rock-billing'); ?>">
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
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * Render customer detail (frontend)
     */
    public function render_customer_detail($customer_id) {
        $customer = get_userdata($customer_id);
        
        if (!$customer) {
            wp_die(__('Customer not found.', 'black-rock-billing'), __('Error', 'black-rock-billing'), array('response' => 404));
        }
        
        $bills = brb_get_customer_bills($customer_id, array('orderby' => 'date', 'order' => 'DESC'));
        $total_billed = brb_get_customer_total_billed($customer_id);
        $total_paid = brb_get_customer_total_paid($customer_id);
        $net_pending = brb_get_customer_net_pending($customer_id);
        
        get_header();
        ?>
        <div class="brb-dashboard-container">
            <div class="brb-dashboard-header">
                <h1><?php printf(__('Customer: %s', 'black-rock-billing'), esc_html(brb_format_customer_name($customer->display_name))); ?></h1>
                <div class="brb-dashboard-nav">
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-nav-link">
                        <?php _e('Dashboard', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="brb-nav-link">
                        <?php _e('Customers', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/add')); ?>" class="brb-nav-link">
                        <?php _e('Add Customer', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="brb-nav-link">
                        <?php _e('Create Bill', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/settings')); ?>" class="brb-nav-link">
                        <?php _e('Settings', 'black-rock-billing'); ?>
                    </a>
                </div>
            </div>
            
            <?php
            $display_name = brb_format_customer_name($customer->display_name);
            $phone = brb_get_customer_phone($customer_id);
            ?>
            <div class="brb-customer-detail-frontend">
                <div class="brb-customer-info-box">
                    <h2><?php _e('Customer Information', 'black-rock-billing'); ?></h2>
                    <div class="brb-customer-info-grid">
                        <div class="brb-info-item">
                            <span class="brb-info-label"><?php _e('Name:', 'black-rock-billing'); ?></span>
                            <span class="brb-info-value"><?php echo esc_html($display_name); ?></span>
                        </div>
                        <div class="brb-info-item">
                            <span class="brb-info-label"><?php _e('Email:', 'black-rock-billing'); ?></span>
                            <span class="brb-info-value">
                                <a href="mailto:<?php echo esc_attr($customer->user_email); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                    <?php echo esc_html($customer->user_email); ?>
                                </a>
                            </span>
                        </div>
                        <?php if ($phone): ?>
                        <div class="brb-info-item">
                            <span class="brb-info-label"><?php _e('Phone:', 'black-rock-billing'); ?></span>
                            <span class="brb-info-value">
                                <a href="tel:<?php echo esc_attr($phone); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                    <?php echo esc_html($phone); ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="brb-summary-cards">
                    <div class="brb-summary-card">
                        <h3><?php _e('Total Bills', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount"><?php echo count($bills); ?></p>
                    </div>
                    <div class="brb-summary-card">
                        <h3><?php _e('Total Billed', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount"><?php echo brb_format_currency($total_billed); ?></p>
                    </div>
                    <div class="brb-summary-card">
                        <h3><?php _e('Total Paid', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount brb-paid"><?php echo brb_format_currency($total_paid); ?></p>
                    </div>
                    <div class="brb-summary-card" style="border-top-color: <?php echo $net_pending >= 0 ? '#ef4444' : '#dc2626'; ?>;">
                        <h3><?php _e('Pending', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount" style="color: <?php echo $net_pending >= 0 ? '#00a32a' : '#dc2626'; ?>;">
                            <?php echo $net_pending >= 0 ? '' : '-'; ?><?php echo brb_format_currency(abs($net_pending)); ?>
                        </p>
                    </div>
                </div>
                
                <div class="brb-bills-section">
                    <div class="brb-bills-header">
                        <h2><?php _e('Customer Bills', 'black-rock-billing'); ?></h2>
                        <div style="display: flex; gap: 12px;">
                            <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/edit/' . $customer_id)); ?>" class="button">
                                <?php _e('Edit Customer', 'black-rock-billing'); ?>
                            </a>
                            <a href="<?php echo esc_url(home_url('/billing-dashboard/create?brb_customer=' . $customer_id)); ?>" class="button button-primary">
                                <?php _e('Create New Bill', 'black-rock-billing'); ?>
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($bills)): ?>
                        <p class="brb-no-bills"><?php _e('This customer has no bills yet.', 'black-rock-billing'); ?></p>
                    <?php else: ?>
                        <table class="brb-bills-table">
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
                                        <td><strong><?php echo $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '—'; ?></strong></td>
                                        <td><strong><?php echo $due_date ? date_i18n(get_option('date_format'), strtotime($due_date)) : '—'; ?></strong></td>
                                        <td><strong><?php echo brb_format_currency($adjusted_total); ?></strong></td>
                                        <td style="color: #00a32a;"><strong><?php echo brb_format_currency($paid); ?></strong></td>
                                        <td style="color: <?php echo $net_pending >= 0 ? '#00a32a' : '#dc2626'; ?>;">
                                            <strong><?php echo $net_pending >= 0 ? '' : '-'; ?><?php echo brb_format_currency(abs($net_pending)); ?></strong>
                                            <?php if ($net_pending < 0): ?>
                                                <small style="display: block; font-size: 0.85em; opacity: 0.8;"><?php _e('(Refund Due)', 'black-rock-billing'); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="brb-status brb-status-<?php echo esc_attr($status); ?>">
                                                <strong><?php echo esc_html(ucfirst($status)); ?></strong>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <a href="<?php echo esc_url(home_url('/billing-dashboard/bill/' . $bill->ID)); ?>" class="brb-action-btn brb-action-view" title="<?php _e('View Bill', 'black-rock-billing'); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
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
        <?php
        get_footer();
    }
    
    /**
     * Render settings page (frontend)
     */
    public function render_settings() {
        get_header();
        ?>
        <div class="brb-dashboard-container">
            <div class="brb-dashboard-header">
                <h1><?php _e('Settings', 'black-rock-billing'); ?></h1>
                <div class="brb-dashboard-nav">
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-nav-link">
                        <?php _e('Dashboard', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="brb-nav-link">
                        <?php _e('Customers', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="brb-nav-link">
                        <?php _e('Create Bill', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/settings')); ?>" class="brb-nav-link active">
                        <?php _e('Settings', 'black-rock-billing'); ?>
                    </a>
                </div>
            </div>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success">
                    <p><?php _e('Settings saved successfully!', 'black-rock-billing'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="brb-settings-section">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="brb-settings-form">
                    <?php wp_nonce_field('brb_save_settings', 'brb_settings_nonce'); ?>
                    <input type="hidden" name="action" value="brb_save_settings" />
                    
                    <div class="brb-form-section">
                        <h2><?php _e('Currency Settings', 'black-rock-billing'); ?></h2>
                        
                        <div class="brb-form-row">
                            <label for="brb_currency_symbol"><?php _e('Currency Symbol', 'black-rock-billing'); ?></label>
                            <input type="text" id="brb_currency_symbol" name="brb_currency_symbol" 
                                   value="<?php echo esc_attr(get_option('brb_currency_symbol', 'AED')); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Symbol to use for currency display (e.g., AED, $, €, £)', 'black-rock-billing'); ?></p>
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_currency_position"><?php _e('Currency Position', 'black-rock-billing'); ?></label>
                            <select id="brb_currency_position" name="brb_currency_position">
                                <option value="before" <?php selected(get_option('brb_currency_position', 'before'), 'before'); ?>>
                                    <?php _e('Before amount (AED 100)', 'black-rock-billing'); ?>
                                </option>
                                <option value="after" <?php selected(get_option('brb_currency_position', 'before'), 'after'); ?>>
                                    <?php _e('After amount (100 AED)', 'black-rock-billing'); ?>
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="brb-form-section">
                        <h2><?php _e('Bill Settings', 'black-rock-billing'); ?></h2>
                        
                        <div class="brb-form-row">
                            <label for="brb_bill_prefix"><?php _e('Bill Number Prefix', 'black-rock-billing'); ?></label>
                            <input type="text" id="brb_bill_prefix" name="brb_bill_prefix" 
                                   value="<?php echo esc_attr(get_option('brb_bill_prefix', 'BILL')); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Prefix for auto-generated bill numbers (e.g., BILL-2026-0001)', 'black-rock-billing'); ?></p>
                        </div>
                    </div>
                    
                    <div class="brb-form-actions">
                        <button type="submit" class="button button-primary button-large"><?php _e('Save Settings', 'black-rock-billing'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * AJAX handler for saving return items
     */
    public function ajax_save_returns() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'black-rock-billing')));
        }
        
        // Verify nonce
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'brb_save_returns') && !wp_verify_nonce($nonce, 'brb_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'black-rock-billing')));
        }
        
        $bill_id = intval($_POST['bill_id'] ?? 0);
        
        if (!$bill_id) {
            wp_send_json_error(array('message' => __('Invalid bill ID.', 'black-rock-billing')));
        }
        
        // Check if user can edit this bill
        if (!current_user_can('edit_post', $bill_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to edit this bill.', 'black-rock-billing')));
        }
        
        // Save return items
        $return_items = array();
        if (isset($_POST['return_items']) && is_array($_POST['return_items'])) {
            foreach ($_POST['return_items'] as $item) {
                if (!empty($item['description'])) {
                    $return_items[] = array(
                        'description' => sanitize_text_field($item['description']),
                        'quantity' => floatval($item['quantity'] ?? 0),
                        'rate' => floatval($item['rate'] ?? 0),
                    );
                }
            }
        }
        
        update_post_meta($bill_id, '_brb_return_items', $return_items);
        
        // Calculate return total
        $return_total = 0;
        foreach ($return_items as $item) {
            $return_total += floatval($item['quantity']) * floatval($item['rate']);
        }
        
        // Get adjusted total
        $original_total = brb_get_bill_total($bill_id);
        $adjusted_total = max(0, $original_total - $return_total);
        
        wp_send_json_success(array(
            'message' => __('Return items saved successfully.', 'black-rock-billing'),
            'return_total' => $return_total,
            'adjusted_total' => $adjusted_total,
            'formatted_return_total' => brb_format_currency($return_total),
            'formatted_adjusted_total' => brb_format_currency($adjusted_total),
        ));
    }
    
    /**
     * AJAX handler for updating bill
     */
    public function ajax_update_bill() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'black-rock-billing')));
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'brb_edit_bill')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'black-rock-billing')));
        }
        
        $bill_id = intval($_POST['brb_bill_id'] ?? 0);
        
        if (!$bill_id) {
            wp_send_json_error(array('message' => __('Invalid bill ID.', 'black-rock-billing')));
        }
        
        // Check if user can edit this bill
        if (!current_user_can('edit_post', $bill_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to edit this bill.', 'black-rock-billing')));
        }
        
        // Get form data
        $customer_id = intval($_POST['brb_customer_id'] ?? 0);
        $bill_date = sanitize_text_field($_POST['brb_bill_date'] ?? '');
        $due_date = sanitize_text_field($_POST['brb_due_date'] ?? '');
        $status = sanitize_text_field($_POST['brb_status'] ?? 'draft');
        $notes = wp_kses_post($_POST['brb_bill_notes'] ?? '');
        $items = isset($_POST['brb_items']) ? $_POST['brb_items'] : array();
        $return_items = isset($_POST['brb_return_items']) ? $_POST['brb_return_items'] : array();
        $paid_amount = floatval($_POST['brb_paid_amount'] ?? 0);
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => __('Please select a customer.', 'black-rock-billing')));
        }
        
        // Calculate total from items
        $total = 0;
        $clean_items = array();
        foreach ($items as $item) {
            if (!empty($item['description'])) {
                $quantity = floatval($item['quantity'] ?? 0);
                $rate = floatval($item['rate'] ?? 0);
                $total += $quantity * $rate;
                $clean_items[] = array(
                    'description' => sanitize_text_field($item['description']),
                    'quantity' => $quantity,
                    'rate' => $rate,
                );
            }
        }
        
        // Save return items
        $clean_return_items = array();
        foreach ($return_items as $item) {
            if (!empty($item['description'])) {
                $clean_return_items[] = array(
                    'description' => sanitize_text_field($item['description']),
                    'quantity' => floatval($item['quantity'] ?? 0),
                    'rate' => floatval($item['rate'] ?? 0),
                );
            }
        }
        
        // Update bill post
        $bill_data = array(
            'ID' => $bill_id,
            'post_content' => $notes,
        );
        wp_update_post($bill_data);
        
        // Save meta data
        update_post_meta($bill_id, '_brb_customer_id', $customer_id);
        update_post_meta($bill_id, '_brb_bill_date', $bill_date);
        update_post_meta($bill_id, '_brb_due_date', $due_date);
        update_post_meta($bill_id, '_brb_status', $status);
        update_post_meta($bill_id, '_brb_bill_items', $clean_items);
        update_post_meta($bill_id, '_brb_total_amount', $total);
        update_post_meta($bill_id, '_brb_return_items', $clean_return_items);
        update_post_meta($bill_id, '_brb_paid_amount', $paid_amount);
        
        // Calculate adjusted total and refund due
        $return_total = 0;
        foreach ($clean_return_items as $item) {
            $return_total += floatval($item['quantity']) * floatval($item['rate']);
        }
        $adjusted_total = max(0, $total - $return_total);
        
        // Calculate refund due if paid amount exceeds adjusted total
        $refund_due = 0;
        if ($paid_amount > $adjusted_total) {
            $refund_due = $paid_amount - $adjusted_total;
        }
        update_post_meta($bill_id, '_brb_refund_due', $refund_due);
        
        // Get old status before updating (for email notification)
        $old_status = get_post_meta($bill_id, '_brb_status', true);
        
        // Send email notification if status changed
        if ($status !== $old_status && $status !== 'draft') {
            BRB_Email::send_bill_notification($bill_id, 'updated');
        }
        
        wp_send_json_success(array(
            'message' => __('Bill updated successfully!', 'black-rock-billing'),
            'bill_id' => $bill_id,
            'redirect_url' => home_url('/billing-dashboard/bill/' . $bill_id)
        ));
    }
    
    /**
     * Render add customer page
     */
    public function render_add_customer() {
        get_header();
        ?>
        <div class="brb-create-bill-container">
            <div class="brb-page-header">
                <h1><?php _e('Add New Customer', 'black-rock-billing'); ?></h1>
                <div class="brb-dashboard-nav">
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-nav-link">
                        <?php _e('Dashboard', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="brb-nav-link">
                        <?php _e('Customers', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/add')); ?>" class="brb-nav-link active">
                        <?php _e('Add Customer', 'black-rock-billing'); ?>
                    </a>
                </div>
            </div>
            
            <form id="brb-add-customer-form" class="brb-bill-form">
                <?php wp_nonce_field('brb_save_customer', 'brb_customer_nonce'); ?>
                
                <div class="brb-form-section">
                    <h2><?php _e('Customer Information', 'black-rock-billing'); ?></h2>
                    
                    <div class="brb-form-grid">
                        <div class="brb-form-row">
                            <label for="brb_customer_first_name"><?php _e('First Name', 'black-rock-billing'); ?> <span class="required">*</span></label>
                            <input type="text" id="brb_customer_first_name" name="first_name" required class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_last_name"><?php _e('Last Name', 'black-rock-billing'); ?> <span class="required">*</span></label>
                            <input type="text" id="brb_customer_last_name" name="last_name" required class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_email"><?php _e('Email', 'black-rock-billing'); ?> <span class="required">*</span></label>
                            <input type="email" id="brb_customer_email" name="user_email" required class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_phone"><?php _e('Phone Number', 'black-rock-billing'); ?></label>
                            <input type="tel" id="brb_customer_phone" name="billing_phone" class="brb-form-input" />
                        </div>
                    </div>
                </div>
                
                <div class="brb-form-actions">
                    <button type="submit" class="button"><?php _e('Create Customer', 'black-rock-billing'); ?></button>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="button"><?php _e('Cancel', 'black-rock-billing'); ?></a>
                </div>
            </form>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * Render edit customer page
     */
    public function render_edit_customer($customer_id) {
        $customer = get_userdata($customer_id);
        
        if (!$customer) {
            wp_redirect(home_url('/billing-dashboard/customers'));
            exit;
        }
        
        $phone = brb_get_customer_phone($customer_id);
        $first_name = get_user_meta($customer_id, 'first_name', true);
        $last_name = get_user_meta($customer_id, 'last_name', true);
        
        get_header();
        ?>
        <div class="brb-create-bill-container">
            <div class="brb-page-header">
                <h1><?php _e('Edit Customer', 'black-rock-billing'); ?> - <?php echo esc_html(brb_format_customer_name($customer->display_name)); ?></h1>
                <div class="brb-dashboard-nav">
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-nav-link">
                        <?php _e('Dashboard', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="brb-nav-link">
                        <?php _e('Customers', 'black-rock-billing'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/' . $customer_id)); ?>" class="brb-nav-link">
                        <?php _e('View Customer', 'black-rock-billing'); ?>
                    </a>
                </div>
            </div>
            
            <form id="brb-edit-customer-form" class="brb-bill-form">
                <?php wp_nonce_field('brb_save_customer', 'brb_customer_nonce'); ?>
                <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>" />
                
                <div class="brb-form-section">
                    <h2><?php _e('Customer Information', 'black-rock-billing'); ?></h2>
                    
                    <div class="brb-form-grid">
                        <div class="brb-form-row">
                            <label for="brb_customer_first_name"><?php _e('First Name', 'black-rock-billing'); ?> <span class="required">*</span></label>
                            <input type="text" id="brb_customer_first_name" name="first_name" value="<?php echo esc_attr($first_name); ?>" required class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_last_name"><?php _e('Last Name', 'black-rock-billing'); ?> <span class="required">*</span></label>
                            <input type="text" id="brb_customer_last_name" name="last_name" value="<?php echo esc_attr($last_name); ?>" required class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_email"><?php _e('Email', 'black-rock-billing'); ?> <span class="required">*</span></label>
                            <input type="email" id="brb_customer_email" name="user_email" value="<?php echo esc_attr($customer->user_email); ?>" required class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_phone"><?php _e('Phone Number', 'black-rock-billing'); ?></label>
                            <input type="tel" id="brb_customer_phone" name="billing_phone" value="<?php echo esc_attr($phone); ?>" class="brb-form-input" />
                        </div>
                    </div>
                </div>
                
                <div class="brb-form-actions">
                    <button type="submit" class="button"><?php _e('Update Customer', 'black-rock-billing'); ?></button>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/' . $customer_id)); ?>" class="button"><?php _e('Cancel', 'black-rock-billing'); ?></a>
                </div>
            </form>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * AJAX handler for saving customer
     */
    public function ajax_save_customer() {
        check_ajax_referer('brb_save_customer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'black-rock-billing')));
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['user_email'] ?? '');
        $phone = sanitize_text_field($_POST['billing_phone'] ?? '');
        
        if (empty($first_name) || empty($last_name) || empty($email)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', 'black-rock-billing')));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'black-rock-billing')));
        }
        
        if ($customer_id) {
            // Update existing customer
            $user_data = array(
                'ID' => $customer_id,
                'user_email' => $email,
                'display_name' => trim($first_name . ' ' . $last_name)
            );
            
            // Check if email is already taken by another user
            $existing_user = get_user_by('email', $email);
            if ($existing_user && $existing_user->ID != $customer_id) {
                wp_send_json_error(array('message' => __('This email is already registered to another user.', 'black-rock-billing')));
            }
            
            $user_id = wp_update_user($user_data);
            
            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => $user_id->get_error_message()));
            }
        } else {
            // Create new customer
            $username = sanitize_user($email);
            $counter = 1;
            while (username_exists($username)) {
                $username = sanitize_user($email) . $counter;
                $counter++;
            }
            
            $user_data = array(
                'user_login' => $username,
                'user_email' => $email,
                'user_pass' => wp_generate_password(12, false),
                'display_name' => trim($first_name . ' ' . $last_name),
                'role' => 'subscriber'
            );
            
            $user_id = wp_insert_user($user_data);
            
            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => $user_id->get_error_message()));
            }
        }
        
        // Update user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'billing_phone', $phone);
        
        wp_send_json_success(array(
            'message' => $customer_id ? __('Customer updated successfully.', 'black-rock-billing') : __('Customer created successfully.', 'black-rock-billing'),
            'redirect' => home_url('/billing-dashboard/customers/' . $user_id)
        ));
    }
}

// Initialize
new BRB_Frontend();