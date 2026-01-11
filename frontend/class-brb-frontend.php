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
        add_action('wp_ajax_brb_import_invoices_csv', array($this, 'ajax_import_invoices_csv'));
        add_action('wp_ajax_brb_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_brb_get_inventory_history', array($this, 'ajax_get_inventory_history'));
        add_action('wp_ajax_brb_save_product', array($this, 'ajax_save_product'));
        
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
        add_rewrite_rule('^billing-dashboard/import/?$', 'index.php?brb_page=import', 'top');
        add_rewrite_rule('^billing-dashboard/inventory/?$', 'index.php?brb_page=inventory', 'top');
        add_rewrite_rule('^billing-dashboard/inventory/add/?$', 'index.php?brb_page=product-add', 'top');
        add_rewrite_rule('^billing-dashboard/inventory/edit/([0-9]+)/?$', 'index.php?brb_page=product-edit&brb_product_id=$matches[1]', 'top');
        add_rewrite_rule('^billing-dashboard/reports/?$', 'index.php?brb_page=reports', 'top');
        
        // Flush rewrite rules if reports rule version is outdated
        $current_version = get_option('brb_rewrite_rules_version', '0');
        if ($current_version !== '3') {
            flush_rewrite_rules(false);
            update_option('brb_rewrite_rules_version', '3');
        }
    }
    
    /**
     * Handle PDF download
     */
    public function handle_pdf_download() {
        if (isset($_GET['brb_download_pdf']) && isset($_GET['bill_id'])) {
            $bill_id = intval($_GET['bill_id']);
            
            // Check permissions
            if (!brb_can_user_view_bill($bill_id)) {
                    wp_die(__('You do not have permission to download this invoice.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
            }
            
            BRB_PDF::generate_pdf($bill_id);
        }
        
        // Handle CSV export
        if (isset($_GET['brb_export_csv']) && $_GET['brb_export_csv'] === '1') {
            $this->export_invoices_csv();
        }
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'brb_page';
        $vars[] = 'brb_bill_id';
        $vars[] = 'brb_customer_id';
        $vars[] = 'brb_product_id';
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
                    wp_die(__('You do not have permission to create invoices.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $this->render_create_bill();
                exit;
                
            case 'edit':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to edit invoices.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $bill_id = intval(get_query_var('brb_bill_id'));
                $this->render_edit_bill($bill_id);
                exit;
                
            case 'all-bills':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to view all invoices.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
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
                
            case 'import':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to import invoices.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $this->render_import();
                exit;
                
            case 'inventory':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to view inventory.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $this->render_inventory();
                exit;
                
            case 'reports':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to view reports.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $this->render_reports();
                exit;
                
            case 'product-add':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to add products.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $this->render_add_product();
                exit;
                
            case 'product-edit':
                if (!current_user_can('manage_options')) {
                    wp_die(__('You do not have permission to edit products.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
                }
                $product_id = intval(get_query_var('brb_product_id'));
                $this->render_edit_product($product_id);
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
                wp_die(__('You do not have permission to view this invoice.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
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
                
                <?php $this->render_navigation_menu('dashboard'); ?>
            </div>
            
            <div class="brb-summary-cards">
                <div class="brb-summary-card" style="border-top: 7px solid #3b82f6;">
                    <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: #3b82f6;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <h3><?php _e('Total Billed', 'black-rock-billing'); ?></h3>
                    <p class="brb-amount"><?php echo brb_format_currency($total_billed); ?></p>
                </div>
                <div class="brb-summary-card" style="border-top: 7px solid #10b981;">
                    <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: #10b981;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <h3><?php _e('Total Paid', 'black-rock-billing'); ?></h3>
                    <p class="brb-amount brb-paid"><?php echo brb_format_currency($total_paid); ?></p>
                </div>
                <div class="brb-summary-card" style="border-top: 7px solid <?php echo $net_pending >= 0 ? '#ef4444' : '#dc2626'; ?>;">
                    <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: <?php echo $net_pending >= 0 ? '#ef4444' : '#dc2626'; ?>;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <h3><?php _e('Pending', 'black-rock-billing'); ?></h3>
                    <p class="brb-amount" style="color: <?php echo $net_pending >= 0 ? '#00a32a' : '#dc2626'; ?>;">
                        <?php echo $net_pending >= 0 ? '' : '-'; ?><?php echo brb_format_currency(abs($net_pending)); ?>
                    </p>
                </div>
            </div>
            
            <div class="brb-bills-section">
                <div class="brb-bills-header">
                    <h2><?php _e('Your Invoices', 'black-rock-billing'); ?></h2>
                    <div class="brb-header-actions">
                        <?php if (!empty($bills)): ?>
                            <a href="<?php echo esc_url(add_query_arg(array('brb_export_csv' => '1'), home_url('/billing-dashboard'))); ?>" class="button" style="display: inline-flex; align-items: center; gap: 6px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                <?php _e('Export CSV', 'black-rock-billing'); ?>
                            </a>
                        <?php endif; ?>
                        <?php if (current_user_can('manage_options')): ?>
                            <a href="<?php echo esc_url(home_url('/billing-dashboard/import')); ?>" class="button" style="display: inline-flex; align-items: center; gap: 6px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <?php _e('Import CSV', 'black-rock-billing'); ?>
                            </a>
                            <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="button button-primary brb-create-bill-btn">
                                <?php _e('Create New Invoice', 'black-rock-billing'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="brb-search-filter">
                    <input type="text" id="brb-search-bills" placeholder="<?php _e('Search by invoice number, customer name, email, phone, date, or amount...', 'black-rock-billing'); ?>" class="brb-search-input" />
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
                    <p class="brb-no-bills"><?php _e('You don\'t have any invoices yet.', 'black-rock-billing'); ?></p>
                <?php else: ?>
                    <table class="brb-bills-table" id="brb-bills-table">
                        <thead>
                            <tr>
                                <th><?php _e('Invoice Number', 'black-rock-billing'); ?></th>
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
                                        <a href="<?php echo esc_url(home_url('/billing-dashboard/bill/' . $bill->ID)); ?>" class="brb-action-btn brb-action-view" title="<?php _e('View Invoice', 'black-rock-billing'); ?>">
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
            wp_die(__('Invoice not found or access denied.', 'black-rock-billing'), __('Error', 'black-rock-billing'), array('response' => 404));
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
        <div class="brb-create-bill-container">
            <div class="brb-page-header">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; flex-wrap: wrap; gap: 15px;">
                    <h1><?php _e('Invoice Details', 'black-rock-billing'); ?> - <?php echo esc_html($bill_number ?: '#' . $bill_id); ?></h1>
                    <?php if ($customer_id && current_user_can('manage_options')): ?>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/' . $customer_id)); ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none; border-radius: 10px; color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.3s; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);" onmouseover="this.style.background='linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)'; this.style.boxShadow='0 4px 12px rgba(59, 130, 246, 0.4)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)'; this.style.boxShadow='0 2px 8px rgba(59, 130, 246, 0.3)'; this.style.transform='translateY(0)'">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M19 12H5"></path>
                                <path d="M12 19l-7-7 7-7"></path>
                            </svg>
                            <?php _e('Back to Customer', 'black-rock-billing'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <?php $this->render_navigation_menu('dashboard'); ?>
            </div>
            
            <div style="background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 25px;">
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h2 style="margin: 0; color: #fff; font-size: 1.5em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        <?php _e('Invoice Information', 'black-rock-billing'); ?>
                    </h2>
                </div>
                
                <div style="padding: 30px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; margin-bottom: 30px;">
                    <div style="background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; transition: all 0.3s;">
                        <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <h3 style="margin: 0; font-size: 1.1em; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                </svg>
                                <?php _e('Invoice To', 'black-rock-billing'); ?>
                            </h3>
                        </div>
                        <?php if ($customer): 
                            $phone = brb_get_customer_phone($customer_id);
                            $display_name = brb_format_customer_name($customer->display_name);
                        ?>
                            <div style="padding: 24px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9;">
                                    <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #fff;">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="8.5" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                    <div>
                                        <p style="margin: 0; font-size: 1.2em; font-weight: 700; color: #1e293b; line-height: 1.3;"><?php echo esc_html($display_name); ?></p>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 16px;">
                                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; transition: all 0.2s;">
                                        <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #0284c7;">
                                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                <polyline points="22,6 12,13 2,6"></polyline>
                                            </svg>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <p style="margin: 0; font-size: 0.85em; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;"><?php _e('Email', 'black-rock-billing'); ?></p>
                                            <p style="margin: 0; font-size: 0.95em; color: #1e293b; font-weight: 500; word-break: break-word;"><?php echo esc_html($customer->user_email); ?></p>
                                        </div>
                                    </div>
                                    <?php if ($phone): ?>
                                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; transition: all 0.2s;">
                                        <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #16a34a;">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 2 0 0 1 22 16.92z"></path>
                                            </svg>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <p style="margin: 0; font-size: 0.85em; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;"><?php _e('Phone', 'black-rock-billing'); ?></p>
                                            <a href="tel:<?php echo esc_attr($phone); ?>" style="margin: 0; font-size: 0.95em; color: #1e293b; font-weight: 500; text-decoration: none; display: block;"><?php echo esc_html($phone); ?></a>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; transition: all 0.3s;">
                        <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <h3 style="margin: 0; font-size: 1.1em; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                <?php _e('Invoice Details', 'black-rock-billing'); ?>
                            </h3>
                        </div>
                        <div style="padding: 24px;">
                            <div style="display: flex; flex-direction: column; gap: 20px;">
                                <div style="padding-bottom: 20px; border-bottom: 1px solid #f1f5f9;">
                                    <p style="margin: 0 0 8px 0; font-size: 0.85em; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Invoice Number', 'black-rock-billing'); ?></p>
                                    <p style="margin: 0; font-size: 1.3em; font-weight: 700; color: #1e293b; letter-spacing: -0.3px;"><?php echo esc_html($bill_number ?: 'N/A'); ?></p>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div>
                                        <p style="margin: 0 0 8px 0; font-size: 0.85em; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Invoice Date', 'black-rock-billing'); ?></p>
                                        <p style="margin: 0; font-size: 1em; font-weight: 600; color: #1e293b;"><?php echo $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '—'; ?></p>
                                    </div>
                                    
                                    <div>
                                        <p style="margin: 0 0 8px 0; font-size: 0.85em; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Due Date', 'black-rock-billing'); ?></p>
                                        <p style="margin: 0; font-size: 1em; font-weight: 600; color: #1e293b;"><?php echo $due_date ? date_i18n(get_option('date_format'), strtotime($due_date)) : '—'; ?></p>
                                    </div>
                                </div>
                                
                                <div style="padding-top: 20px; border-top: 1px solid #f1f5f9;">
                                    <p style="margin: 0 0 12px 0; font-size: 0.85em; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Status', 'black-rock-billing'); ?></p>
                                    <span class="brb-status brb-status-<?php echo esc_attr($status); ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 0.9em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <?php echo esc_html(ucfirst($status)); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 30px;">
                    <h3 style="margin: 0 0 20px 0; font-size: 1.3em; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        <?php _e('Invoice Items', 'black-rock-billing'); ?>
                    </h3>
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
                <div style="margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 30px;">
                    <h3 style="margin: 0 0 20px 0; font-size: 1.3em; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #dc2626;">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        <?php _e('Return Items', 'black-rock-billing'); ?>
                    </h3>
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
                    <div style="margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 30px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 1.3em; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                            <?php _e('Notes', 'black-rock-billing'); ?>
                        </h3>
                        <div style="background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; color: #475569; line-height: 1.6;">
                            <?php echo wp_kses_post(wpautop($bill->post_content)); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 30px; padding-top: 25px; border-top: 2px solid #f1f5f9; display: flex; gap: 15px; justify-content: flex-start;">
                    <?php if (current_user_can('manage_options')): ?>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/edit/' . $bill_id)); ?>" class="button button-primary" style="padding: 14px 32px; font-weight: 600; font-size: 15px; text-decoration: none;">
                            <?php _e('Edit Invoice', 'black-rock-billing'); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(add_query_arg(array('brb_download_pdf' => '1', 'bill_id' => $bill_id), home_url())); ?>" class="button" style="padding: 14px 32px; font-weight: 600; font-size: 15px; text-decoration: none;">
                        <?php _e('Download PDF', 'black-rock-billing'); ?>
                    </a>
                </div>
                </div>
            </div>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * Render consistent navigation menu
     */
    private function render_navigation_menu($active_page = '') {
        ?>
        <div class="brb-dashboard-nav">
            <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="brb-nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                </svg>
                <?php _e('Dashboard', 'black-rock-billing'); ?>
            </a>
            <?php if (current_user_can('manage_options')): ?>
                <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="brb-nav-link <?php echo $active_page === 'customers' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <?php _e('Customers', 'black-rock-billing'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/add')); ?>" class="brb-nav-link <?php echo $active_page === 'customer-add' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                    <?php _e('Add Customer', 'black-rock-billing'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="brb-nav-link <?php echo $active_page === 'create' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                    <?php _e('Create Invoice', 'black-rock-billing'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/billing-dashboard/inventory')); ?>" class="brb-nav-link <?php echo $active_page === 'inventory' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                    </svg>
                    <?php _e('Inventory', 'black-rock-billing'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/billing-dashboard/reports')); ?>" class="brb-nav-link <?php echo $active_page === 'reports' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <?php _e('Reports', 'black-rock-billing'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/billing-dashboard/settings')); ?>" class="brb-nav-link <?php echo $active_page === 'settings' ? 'active' : ''; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <?php _e('Settings', 'black-rock-billing'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
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
            $create_link = '<li class="menu-item brb-create-bill-menu-item"><a href="' . esc_url(home_url('/billing-dashboard/create')) . '">' . __('Create Invoice', 'black-rock-billing') . '</a></li>';
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
                <h1><?php _e('Create New Invoice', 'black-rock-billing'); ?></h1>
                <?php $this->render_navigation_menu('create'); ?>
            </div>
            
            <form id="brb-create-bill-form" class="brb-bill-form" style="background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column; gap: 30px;">
                <?php wp_nonce_field('brb_create_bill', 'brb_create_bill_nonce'); ?>
                
                <div class="brb-form-section" style="background: transparent; border-radius: 0; padding: 0; box-shadow: none; border: none; overflow: hidden; margin-bottom: 0;">
                    <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <h2 style="margin: 0; color: #fff; font-size: 1.5em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 12px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                            <?php _e('Invoice Information', 'black-rock-billing'); ?>
                        </h2>
                    </div>
                    <div class="brb-form-content">
                    <div class="brb-form-grid brb-form-grid-top">
                        <div class="brb-form-row">
                            <label for="brb_customer_search" class="brb-form-label"><?php _e('Customer', 'black-rock-billing'); ?> <span class="required">*</span></label>
                            <div class="brb-customer-search-wrapper brb-customer-search-wrapper-base">
                                <input type="text" id="brb_customer_search" class="brb-form-input" placeholder="<?php _e('Type to search customer...', 'black-rock-billing'); ?>" value="<?php echo esc_attr($preselected_display); ?>" autocomplete="off" />
                                <input type="hidden" id="brb_customer_id" name="brb_customer_id" value="<?php echo esc_attr($preselected_customer); ?>" required />
                                <div id="brb-customer-dropdown" class="brb-customer-dropdown"></div>
                            </div>
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_bill_date" class="brb-form-label"><?php _e('Invoice Date', 'black-rock-billing'); ?></label>
                            <input type="date" id="brb_bill_date" name="brb_bill_date" value="<?php echo date('Y-m-d'); ?>" required class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_due_date" class="brb-form-label"><?php _e('Due Date', 'black-rock-billing'); ?></label>
                            <input type="date" id="brb_due_date" name="brb_due_date" class="brb-form-input" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_status" class="brb-form-label"><?php _e('Status', 'black-rock-billing'); ?></label>
                            <select id="brb_status" name="brb_status" class="brb-form-select">
                                <option value="draft" selected><?php _e('Draft', 'black-rock-billing'); ?></option>
                                <option value="sent"><?php _e('Sent', 'black-rock-billing'); ?></option>
                                <option value="paid"><?php _e('Paid', 'black-rock-billing'); ?></option>
                                <option value="overdue"><?php _e('Overdue', 'black-rock-billing'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="brb-form-row brb-form-row-full brb-form-row-full-base">
                        <label for="brb_bill_notes" class="brb-form-label"><?php _e('Notes', 'black-rock-billing'); ?></label>
                        <textarea id="brb_bill_notes" name="brb_bill_notes" rows="4" class="brb-form-textarea brb-form-textarea-full"></textarea>
                    </div>
                    </div>
                </div>
                
                <div class="brb-form-section brb-form-section-base">
                    <h2 class="brb-section-header-base">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <?php _e('Invoice Items', 'black-rock-billing'); ?>
                    </h2>
                    <div>
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
                                        <td>
                                            <div class="brb-product-search-wrapper" style="position: relative;">
                                                <input type="text" name="brb_items[0][description]" class="brb-item-description" placeholder="<?php _e('Item description or search product...', 'black-rock-billing'); ?>" autocomplete="off" />
                                                <input type="hidden" name="brb_items[0][product_id]" class="brb-item-product-id" value="" />
                                                <div class="brb-product-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></div>
                                            </div>
                                        </td>
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
                </div>
                
                <div class="brb-form-section brb-form-section-base">
                    <h2 class="brb-section-header-base">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <?php _e('Payment Information', 'black-rock-billing'); ?>
                    </h2>
                    <div>
                        <div class="brb-form-row" style="max-width: 400px;">
                        <label for="brb_paid_amount" class="brb-form-label"><?php _e('Paid Amount', 'black-rock-billing'); ?></label>
                        <input type="number" id="brb_paid_amount" name="brb_paid_amount" step="0.01" min="0" value="0" class="brb-form-input" />
                    </div>
                    </div>
                </div>
                
                <div class="brb-form-actions brb-form-actions-base" style="padding: 25px 30px 0;">
                    <button type="submit" class="button button-primary button-large brb-form-button-base"><?php _e('Create Invoice', 'black-rock-billing'); ?></button>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard')); ?>" class="button button-large brb-form-button-base"><?php _e('Cancel', 'black-rock-billing'); ?></a>
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
            wp_die(__('You do not have permission to edit this invoice.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
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
                <h1><?php _e('Edit Invoice', 'black-rock-billing'); ?> - <?php echo esc_html($bill_number ?: '#' . $bill_id); ?></h1>
                <?php $this->render_navigation_menu('create'); ?>
            </div>
            
            <form id="brb-edit-bill-form" class="brb-bill-form" style="background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column; gap: 30px;">
                <?php wp_nonce_field('brb_edit_bill', 'brb_edit_bill_nonce'); ?>
                <input type="hidden" name="brb_bill_id" value="<?php echo esc_attr($bill_id); ?>" />
                
                <div class="brb-form-section" style="background: transparent; border-radius: 0; padding: 0; box-shadow: none; border: none; overflow: hidden; margin-bottom: 0;">
                    <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <h2 style="margin: 0; color: #fff; font-size: 1.5em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 12px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                            <?php _e('Invoice Information', 'black-rock-billing'); ?>
                        </h2>
                    </div>
                    <div style="padding: 0 30px;">
                    <div class="brb-form-row brb-form-row-full">
                        <label for="brb_bill_number" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Invoice Number', 'black-rock-billing'); ?></label>
                        <input type="text" id="brb_bill_number" name="brb_bill_number" value="<?php echo esc_attr($bill_number); ?>" readonly class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #f8fafc; transition: all 0.3s;" />
                        <p class="description" style="margin-top: 8px; font-size: 13px; color: #64748b;"><?php _e('Invoice number cannot be changed', 'black-rock-billing'); ?></p>
                    </div>
                    
                    <div class="brb-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">
                        <div class="brb-form-row">
                            <label for="brb_customer_search" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Customer', 'black-rock-billing'); ?> <span class="required" style="color: #ef4444;">*</span></label>
                            <div class="brb-customer-search-wrapper" style="position: relative;">
                                <?php
                                $selected_customer_display = '';
                                if ($customer_id) {
                                    $selected_customer = get_userdata($customer_id);
                                    if ($selected_customer) {
                                        $selected_customer_display = brb_format_customer_name($selected_customer->display_name) . ' (' . $selected_customer->user_email . ')';
                                    }
                                }
                                ?>
                                <input type="text" id="brb_customer_search" class="brb-form-input" placeholder="<?php _e('Type to search customer...', 'black-rock-billing'); ?>" value="<?php echo esc_attr($selected_customer_display); ?>" autocomplete="off" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                                <input type="hidden" id="brb_customer_id" name="brb_customer_id" value="<?php echo esc_attr($customer_id); ?>" required />
                                <div id="brb-customer-dropdown" class="brb-customer-dropdown"></div>
                            </div>
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_bill_date" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Invoice Date', 'black-rock-billing'); ?></label>
                            <input type="date" id="brb_bill_date" name="brb_bill_date" value="<?php echo esc_attr($bill_date ?: date('Y-m-d')); ?>" required class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_due_date" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Due Date', 'black-rock-billing'); ?></label>
                            <input type="date" id="brb_due_date" name="brb_due_date" value="<?php echo esc_attr($due_date); ?>" class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_status" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Status', 'black-rock-billing'); ?></label>
                            <select id="brb_status" name="brb_status" class="brb-form-select" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #fff; cursor: pointer; transition: all 0.3s;">
                                <option value="draft" <?php selected($status, 'draft'); ?>><?php _e('Draft', 'black-rock-billing'); ?></option>
                                <option value="sent" <?php selected($status, 'sent'); ?>><?php _e('Sent', 'black-rock-billing'); ?></option>
                                <option value="paid" <?php selected($status, 'paid'); ?>><?php _e('Paid', 'black-rock-billing'); ?></option>
                                <option value="overdue" <?php selected($status, 'overdue'); ?>><?php _e('Overdue', 'black-rock-billing'); ?></option>
                                <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'black-rock-billing'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="brb-form-row brb-form-row-full" style="margin-top: 20px;">
                        <label for="brb_bill_notes" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Notes', 'black-rock-billing'); ?></label>
                        <textarea id="brb_bill_notes" name="brb_bill_notes" rows="4" class="brb-form-textarea brb-form-textarea-full" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;"><?php echo esc_textarea($notes); ?></textarea>
                    </div>
                    </div>
                </div>
                
                <div class="brb-form-section" style="background: transparent; border-radius: 0; padding: 0 30px; box-shadow: none; border: none; margin-bottom: 0;">
                    <h2 style="margin: 0; padding: 0 0 15px 0; color: #1e293b; font-size: 1.3em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f5f9;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <?php _e('Invoice Items', 'black-rock-billing'); ?>
                    </h2>
                    <div>
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
                                                <td>
                                                    <div class="brb-product-search-wrapper" style="position: relative;">
                                                        <input type="text" name="brb_items[<?php echo esc_attr($index); ?>][description]" class="brb-item-description" value="<?php echo esc_attr($item['description']); ?>" placeholder="<?php _e('Item description or search product...', 'black-rock-billing'); ?>" autocomplete="off" />
                                                        <input type="hidden" name="brb_items[<?php echo esc_attr($index); ?>][product_id]" class="brb-item-product-id" value="" />
                                                        <div class="brb-product-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></div>
                                                    </div>
                                                </td>
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
                                            <td>
                                                <div class="brb-product-search-wrapper" style="position: relative;">
                                                    <input type="text" name="brb_items[0][description]" class="brb-item-description" placeholder="<?php _e('Item description or search product...', 'black-rock-billing'); ?>" autocomplete="off" />
                                                    <input type="hidden" name="brb_items[0][product_id]" class="brb-item-product-id" value="" />
                                                    <div class="brb-product-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></div>
                                                </div>
                                            </td>
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
                </div>
                
                <div class="brb-form-section" style="background: transparent; border-radius: 0; padding: 0 30px; box-shadow: none; border: none; margin-bottom: 0;">
                    <h2 style="margin: 0; padding: 0 0 15px 0; color: #1e293b; font-size: 1.3em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f5f9;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        <?php _e('Return Items', 'black-rock-billing'); ?>
                    </h2>
                    <p class="description" style="margin: 0; font-size: 13px; color: #64748b;"><?php _e('Add items that have been returned. The return amount will be deducted from the invoice total.', 'black-rock-billing'); ?></p>
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
                
                <div class="brb-form-section" style="background: transparent; border-radius: 0; padding: 0 30px; box-shadow: none; border: none; margin-bottom: 0;">
                    <h2 style="margin: 0; padding: 0 0 15px 0; color: #1e293b; font-size: 1.3em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f5f9;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <?php _e('Payment Information', 'black-rock-billing'); ?>
                    </h2>
                    <div style="padding-bottom: 30px;">
                        <div class="brb-form-row" style="max-width: 400px; margin-bottom: 20px;">
                            <label for="brb_paid_amount" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Paid Amount', 'black-rock-billing'); ?></label>
                            <input type="number" id="brb_paid_amount" name="brb_paid_amount" step="0.01" min="0" value="<?php echo esc_attr($paid); ?>" class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                            <p class="description" style="margin-top: 8px; font-size: 13px; color: #64748b;"><?php _e('Enter the amount that has been paid for this invoice.', 'black-rock-billing'); ?></p>
                        </div>
                        <div class="brb-payment-summary" style="background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-top: 20px;">
                            <h4 style="margin: 0 0 20px 0; font-size: 1.1em; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                                <?php _e('Payment Summary', 'black-rock-billing'); ?>
                            </h4>
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                    <span style="font-size: 0.9em; color: #64748b; font-weight: 600;"><?php _e('Original Total', 'black-rock-billing'); ?></span>
                                    <span id="brb-original-total-display" style="font-size: 1.1em; font-weight: 700; color: #1e293b;"><?php echo brb_format_currency($total); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                    <span style="font-size: 0.9em; color: #64748b; font-weight: 600;"><?php _e('Return Total', 'black-rock-billing'); ?></span>
                                    <span id="brb-return-total-display" style="font-size: 1.1em; font-weight: 700; color: #dc2626;">-<?php echo brb_format_currency(brb_get_return_total($bill_id)); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 2px solid #e2e8f0;">
                                    <span style="font-size: 0.9em; color: #64748b; font-weight: 600;"><?php _e('Adjusted Total', 'black-rock-billing'); ?></span>
                                    <span id="brb-adjusted-total-display" style="font-size: 1.1em; font-weight: 700; color: #1e293b;"><?php echo brb_format_currency(brb_get_adjusted_bill_total($bill_id)); ?></span>
                                </div>
                                <?php 
                                $refund_due_edit = brb_get_refund_due($bill_id);
                                if ($refund_due_edit > 0): ?>
                                    <div id="brb-pending-row" style="display: none; justify-content: space-between; align-items: center; padding: 12px 0;">
                                        <span style="font-size: 0.9em; color: #64748b; font-weight: 600;"><?php _e('Pending Amount', 'black-rock-billing'); ?></span>
                                        <span id="brb-pending-display" style="font-size: 1.1em; font-weight: 700; color: #1e293b;"></span>
                                    </div>
                                    <div id="brb-refund-row" style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border-radius: 8px; border: 2px solid #fecaca; margin-top: 8px;">
                                        <span style="font-size: 0.95em; color: #991b1b; font-weight: 700;"><?php _e('Refund Due to Customer', 'black-rock-billing'); ?></span>
                                        <span id="brb-refund-display" style="font-size: 1.3em; font-weight: 800; color: #dc2626;"><?php echo brb_format_currency($refund_due_edit); ?></span>
                                    </div>
                                <?php else: ?>
                                    <div id="brb-pending-row" style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 8px; border: 2px solid #fcd34d; margin-top: 8px;">
                                        <span style="font-size: 0.95em; color: #92400e; font-weight: 700;"><?php _e('Pending Amount', 'black-rock-billing'); ?></span>
                                        <span id="brb-pending-display" style="font-size: 1.3em; font-weight: 800; color: #d97706;"><?php echo brb_format_currency(brb_get_pending_amount($bill_id)); ?></span>
                                    </div>
                                    <div id="brb-refund-row" style="display: none; justify-content: space-between; align-items: center; padding: 16px; background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border-radius: 8px; border: 2px solid #fecaca; margin-top: 8px;">
                                        <span style="font-size: 0.95em; color: #991b1b; font-weight: 700;"><?php _e('Refund Due to Customer', 'black-rock-billing'); ?></span>
                                        <span id="brb-refund-display" style="font-size: 1.3em; font-weight: 800; color: #dc2626;"></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="brb-form-actions" style="padding: 25px 30px 0; border-top: 2px solid #f1f5f9; display: flex; gap: 15px; justify-content: flex-start;">
                    <button type="submit" class="button button-primary button-large" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Save Changes', 'black-rock-billing'); ?></button>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/bill/' . $bill_id)); ?>" class="button button-large" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Cancel', 'black-rock-billing'); ?></a>
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
        
        // Calculate total and process inventory
        $total = 0;
        $clean_items = array();
        foreach ($items as $item) {
            if (!empty($item['description'])) {
                $quantity = floatval($item['quantity'] ?? 0);
                $rate = floatval($item['rate'] ?? 0);
                $product_id = isset($item['product_id']) ? intval($item['product_id']) : 0;
                $total += $quantity * $rate;
                $clean_items[] = array(
                    'description' => sanitize_text_field($item['description']),
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'product_id' => $product_id,
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
        
        // Deduct inventory for products
        foreach ($clean_items as $item) {
            if (!empty($item['product_id']) && $item['product_id'] > 0 && $item['quantity'] > 0) {
                brb_deduct_product_quantity($item['product_id'], $item['quantity'], $bill_id, 'sale');
            }
        }
        
        // Send email notification if status is not draft
        if ($status !== 'draft') {
            BRB_Email::send_bill_notification($bill_id, 'created');
            update_post_meta($bill_id, '_brb_email_sent', 'yes');
        }
        
        wp_send_json_success(array(
            'message' => __('Invoice created successfully!', 'black-rock-billing'),
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
            wp_send_json_success(array('message' => __('Invoice deleted successfully!', 'black-rock-billing')));
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
        
        if (isset($_POST['brb_company_name'])) {
            update_option('brb_company_name', sanitize_text_field($_POST['brb_company_name']));
        }
        
        if (isset($_POST['brb_company_email'])) {
            update_option('brb_company_email', sanitize_email($_POST['brb_company_email']));
        }
        
        if (isset($_POST['brb_company_phone'])) {
            update_option('brb_company_phone', sanitize_text_field($_POST['brb_company_phone']));
        }
        
        if (isset($_POST['brb_company_address'])) {
            update_option('brb_company_address', sanitize_textarea_field($_POST['brb_company_address']));
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
                <h1><?php _e('All Invoices', 'black-rock-billing'); ?></h1>
                <?php $this->render_navigation_menu('dashboard'); ?>
            </div>
            
            <div class="brb-bills-section">
                <div class="brb-bills-header">
                    <h2><?php _e('All Invoices', 'black-rock-billing'); ?></h2>
                    <div class="brb-header-actions">
                        <?php if (!empty($bills)): ?>
                            <a href="<?php echo esc_url(add_query_arg(array('brb_export_csv' => '1'), home_url('/billing-dashboard/bills'))); ?>" class="button" style="display: inline-flex; align-items: center; gap: 6px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                <?php _e('Export CSV', 'black-rock-billing'); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/import')); ?>" class="button" style="display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            <?php _e('Import CSV', 'black-rock-billing'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" class="button button-primary brb-create-bill-btn">
                            <?php _e('Create New Invoice', 'black-rock-billing'); ?>
                        </a>
                    </div>
                </div>
                
                <?php if (empty($bills)): ?>
                    <div class="brb-no-bills">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        <p><?php _e('No invoices found.', 'black-rock-billing'); ?></p>
                        <?php if (current_user_can('manage_options')): ?>
                            <p style="margin-top: 15px; font-size: 0.9em; opacity: 0.8;">
                                <a href="<?php echo esc_url(home_url('/billing-dashboard/create')); ?>" style="color: #3b82f6; text-decoration: none; font-weight: 600;">
                                    <?php _e('Create your first invoice →', 'black-rock-billing'); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="brb-bills-table">
                        <thead>
                            <tr>
                                <th><?php _e('Invoice Number', 'black-rock-billing'); ?></th>
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
                <?php $this->render_navigation_menu('customers'); ?>
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
                                                <a href="<?php echo esc_url(home_url('/billing-dashboard/create?brb_customer=' . $customer->ID)); ?>" class="brb-action-btn brb-action-bill" title="<?php _e('Create Invoice', 'black-rock-billing'); ?>">
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
                <?php $this->render_navigation_menu('customers'); ?>
            </div>
            
            <?php
            $display_name = brb_format_customer_name($customer->display_name);
            $phone = brb_get_customer_phone($customer_id);
            ?>
            <div class="brb-customer-detail-frontend">
                <div class="brb-customer-info-box" style="background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                    <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <h2 style="margin: 0; color: #fff; font-size: 1.5em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 12px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <?php _e('Customer Information', 'black-rock-billing'); ?>
                        </h2>
                    </div>
                    <div style="padding: 30px;">
                        <div class="brb-customer-info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                            <div class="brb-info-item" style="background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; transition: all 0.3s; position: relative; overflow: hidden;">
                                <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);"></div>
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); display: flex; align-items: center; justify-content: center; color: #3b82f6; flex-shrink: 0;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                    <span class="brb-info-label" style="font-size: 0.75em; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Name', 'black-rock-billing'); ?></span>
                                </div>
                                <span class="brb-info-value" style="font-size: 1.1em; color: #0f172a; font-weight: 600; display: block; margin-top: 4px;"><?php echo esc_html($display_name); ?></span>
                            </div>
                            
                            <div class="brb-info-item" style="background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; transition: all 0.3s; position: relative; overflow: hidden;">
                                <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(135deg, #10b981 0%, #059669 100%);"></div>
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); display: flex; align-items: center; justify-content: center; color: #10b981; flex-shrink: 0;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                            <polyline points="22,6 12,13 2,6"></polyline>
                                        </svg>
                                    </div>
                                    <span class="brb-info-label" style="font-size: 0.75em; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Email', 'black-rock-billing'); ?></span>
                                </div>
                                <span class="brb-info-value" style="font-size: 1.1em; color: #0f172a; font-weight: 600; display: block; margin-top: 4px;">
                                    <a href="mailto:<?php echo esc_attr($customer->user_email); ?>" style="color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; padding: 4px 0;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.7;">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                            <polyline points="22,6 12,13 2,6"></polyline>
                                        </svg>
                                        <span><?php echo esc_html($customer->user_email); ?></span>
                                    </a>
                                </span>
                            </div>
                            
                            <?php if ($phone): ?>
                            <div class="brb-info-item" style="background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; transition: all 0.3s; position: relative; overflow: hidden;">
                                <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);"></div>
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); display: flex; align-items: center; justify-content: center; color: #f59e0b; flex-shrink: 0;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 2 0 0 1 22 16.92z"></path>
                                        </svg>
                                    </div>
                                    <span class="brb-info-label" style="font-size: 0.75em; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Phone', 'black-rock-billing'); ?></span>
                                </div>
                                <span class="brb-info-value" style="font-size: 1.1em; color: #0f172a; font-weight: 600; display: block; margin-top: 4px;">
                                    <a href="tel:<?php echo esc_attr($phone); ?>" style="color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; padding: 4px 0;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.7;">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 2 0 0 1 22 16.92z"></path>
                                        </svg>
                                        <span><?php echo esc_html($phone); ?></span>
                                    </a>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="brb-summary-cards">
                    <div class="brb-summary-card" style="border-top: 7px solid #8b5cf6;">
                        <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: #8b5cf6;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                        </div>
                        <h3><?php _e('Total Bills', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount"><?php echo count($bills); ?></p>
                    </div>
                    <div class="brb-summary-card" style="border-top: 7px solid #3b82f6;">
                        <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: #3b82f6;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <h3><?php _e('Total Billed', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount"><?php echo brb_format_currency($total_billed); ?></p>
                    </div>
                    <div class="brb-summary-card" style="border-top: 7px solid #10b981;">
                        <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: #10b981;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <h3><?php _e('Total Paid', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount brb-paid"><?php echo brb_format_currency($total_paid); ?></p>
                    </div>
                    <div class="brb-summary-card" style="border-top: 7px solid <?php echo $net_pending >= 0 ? '#ef4444' : '#dc2626'; ?>;">
                        <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: <?php echo $net_pending >= 0 ? '#ef4444' : '#dc2626'; ?>;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
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
                                <?php _e('Create New Invoice', 'black-rock-billing'); ?>
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($bills)): ?>
                        <p class="brb-no-bills"><?php _e('This customer has no invoices yet.', 'black-rock-billing'); ?></p>
                    <?php else: ?>
                        <table class="brb-bills-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Invoice Number', 'black-rock-billing'); ?></th>
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
                                            <a href="<?php echo esc_url(home_url('/billing-dashboard/bill/' . $bill->ID)); ?>" class="brb-action-btn brb-action-view" title="<?php _e('View Invoice', 'black-rock-billing'); ?>">
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
        <div class="brb-create-bill-container">
            <div class="brb-page-header">
                <h1><?php _e('Settings', 'black-rock-billing'); ?></h1>
                <?php $this->render_navigation_menu('settings'); ?>
            </div>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border: 2px solid #86efac; border-radius: 12px; padding: 16px 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #16a34a; flex-shrink: 0;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <p style="margin: 0; color: #166534; font-weight: 600; font-size: 14px;"><?php _e('Settings saved successfully!', 'black-rock-billing'); ?></p>
                </div>
            <?php endif; ?>
            
            <div style="background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 25px;">
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h2 style="margin: 0; color: #fff; font-size: 1.5em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <?php _e('General Settings', 'black-rock-billing'); ?>
                    </h2>
                </div>
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="padding: 30px; display: flex; flex-direction: column; gap: 30px;">
                    <?php wp_nonce_field('brb_save_settings', 'brb_settings_nonce'); ?>
                    <input type="hidden" name="action" value="brb_save_settings" />
                    
                    <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 30px;">
                        <h3 style="margin: 0 0 20px 0; font-size: 1.2em; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                            <?php _e('Currency Settings', 'black-rock-billing'); ?>
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                            <div>
                                <label for="brb_currency_symbol" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Currency Symbol', 'black-rock-billing'); ?></label>
                                <input type="text" id="brb_currency_symbol" name="brb_currency_symbol" 
                                       value="<?php echo esc_attr(get_option('brb_currency_symbol', 'AED')); ?>" 
                                       style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s; height: auto; min-height: 44px; box-sizing: border-box;" />
                                <p class="description" style="margin-top: 8px; font-size: 13px; color: #64748b;"><?php _e('Symbol to use for currency display (e.g., AED, $, €, £)', 'black-rock-billing'); ?></p>
                            </div>
                            
                            <div>
                                <label for="brb_currency_position" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Currency Position', 'black-rock-billing'); ?></label>
                                <select id="brb_currency_position" name="brb_currency_position" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #fff; cursor: pointer; transition: all 0.3s; height: auto; min-height: 44px; box-sizing: border-box;">
                                    <option value="before" <?php selected(get_option('brb_currency_position', 'before'), 'before'); ?>>
                                        <?php _e('Before amount (AED 100)', 'black-rock-billing'); ?>
                                    </option>
                                    <option value="after" <?php selected(get_option('brb_currency_position', 'before'), 'after'); ?>>
                                        <?php _e('After amount (100 AED)', 'black-rock-billing'); ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 30px;">
                        <h3 style="margin: 0 0 20px 0; font-size: 1.2em; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                            <?php _e('Invoice Settings', 'black-rock-billing'); ?>
                        </h3>
                        
                        <div>
                            <label for="brb_bill_prefix" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Invoice Number Prefix', 'black-rock-billing'); ?></label>
                            <input type="text" id="brb_bill_prefix" name="brb_bill_prefix" 
                                   value="<?php echo esc_attr(get_option('brb_bill_prefix', 'BILL')); ?>" 
                                   style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s; height: auto; min-height: 44px; box-sizing: border-box;" />
                            <p class="description" style="margin-top: 8px; font-size: 13px; color: #64748b;"><?php _e('Prefix for auto-generated invoice numbers (e.g., INV-2026-0001)', 'black-rock-billing'); ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <h3 style="margin: 0 0 20px 0; font-size: 1.2em; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #64748b;">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <?php _e('Company Information', 'black-rock-billing'); ?>
                        </h3>
                        <p style="margin: 0 0 20px 0; font-size: 14px; color: #64748b;"><?php _e('This information will be displayed on invoices and PDFs.', 'black-rock-billing'); ?></p>
                        
                        <div class="brb-company-info-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label for="brb_company_name" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Company Name', 'black-rock-billing'); ?></label>
                                <input type="text" id="brb_company_name" name="brb_company_name" 
                                       value="<?php echo esc_attr(get_option('brb_company_name', get_bloginfo('name'))); ?>" 
                                       style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s; height: auto; min-height: 44px; box-sizing: border-box;" />
                            </div>
                            
                            <div>
                                <label for="brb_company_email" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Email', 'black-rock-billing'); ?></label>
                                <input type="email" id="brb_company_email" name="brb_company_email" 
                                       value="<?php echo esc_attr(get_option('brb_company_email', get_bloginfo('admin_email'))); ?>" 
                                       style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s; height: auto; min-height: 44px; box-sizing: border-box;" />
                            </div>
                            
                            <div>
                                <label for="brb_company_phone" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Phone', 'black-rock-billing'); ?></label>
                                <input type="tel" id="brb_company_phone" name="brb_company_phone" 
                                       value="<?php echo esc_attr(get_option('brb_company_phone', '')); ?>" 
                                       style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s; height: auto; min-height: 44px; box-sizing: border-box;" />
                            </div>
                        </div>
                        
                        <div>
                            <label for="brb_company_address" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Address', 'black-rock-billing'); ?></label>
                            <input type="text" id="brb_company_address" name="brb_company_address" 
                                   value="<?php echo esc_attr(get_option('brb_company_address', '')); ?>" 
                                   style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s; height: auto; min-height: 44px; box-sizing: border-box;" />
                        </div>
                    </div>
                    
                    <div style="padding-top: 25px; border-top: 2px solid #f1f5f9; display: flex; gap: 15px; justify-content: flex-start;">
                        <button type="submit" class="button button-primary" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Save Settings', 'black-rock-billing'); ?></button>
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
            wp_send_json_error(array('message' => __('You do not have permission to edit this invoice.', 'black-rock-billing')));
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
        
        // Recalculate refund due based on current paid amount and adjusted total
        // This is important for bills with negative payments (overpaid)
        $paid_amount = brb_get_paid_amount($bill_id);
        $refund_due = 0;
        if ($paid_amount > $adjusted_total) {
            $refund_due = $paid_amount - $adjusted_total;
        }
        update_post_meta($bill_id, '_brb_refund_due', $refund_due);
        
        wp_send_json_success(array(
            'message' => __('Return items saved successfully.', 'black-rock-billing'),
            'return_total' => $return_total,
            'adjusted_total' => $adjusted_total,
            'refund_due' => $refund_due,
            'formatted_return_total' => brb_format_currency($return_total),
            'formatted_adjusted_total' => brb_format_currency($adjusted_total),
            'formatted_refund_due' => brb_format_currency($refund_due),
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
            wp_send_json_error(array('message' => __('You do not have permission to edit this invoice.', 'black-rock-billing')));
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
        
        // Get old items to restore inventory
        $old_items = brb_get_bill_items($bill_id);
        $old_return_items = brb_get_return_items($bill_id);
        
        // Restore inventory from old items
        foreach ($old_items as $old_item) {
            if (!empty($old_item['product_id']) && $old_item['product_id'] > 0 && $old_item['quantity'] > 0) {
                brb_restore_product_quantity($old_item['product_id'], $old_item['quantity'], $bill_id, 'invoice_edit');
            }
        }
        
        // Restore inventory from old return items (returns reduce inventory, so restoring means deducting again)
        foreach ($old_return_items as $old_return_item) {
            if (!empty($old_return_item['product_id']) && $old_return_item['product_id'] > 0 && $old_return_item['quantity'] > 0) {
                brb_deduct_product_quantity($old_return_item['product_id'], $old_return_item['quantity'], $bill_id, 'return_removed');
            }
        }
        
        // Calculate total from items
        $total = 0;
        $clean_items = array();
        foreach ($items as $item) {
            if (!empty($item['description'])) {
                $quantity = floatval($item['quantity'] ?? 0);
                $rate = floatval($item['rate'] ?? 0);
                $product_id = isset($item['product_id']) ? intval($item['product_id']) : 0;
                $total += $quantity * $rate;
                $clean_items[] = array(
                    'description' => sanitize_text_field($item['description']),
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'product_id' => $product_id,
                );
            }
        }
        
        // Save return items
        $clean_return_items = array();
        foreach ($return_items as $item) {
            if (!empty($item['description'])) {
                $product_id = isset($item['product_id']) ? intval($item['product_id']) : 0;
                $clean_return_items[] = array(
                    'description' => sanitize_text_field($item['description']),
                    'quantity' => floatval($item['quantity'] ?? 0),
                    'rate' => floatval($item['rate'] ?? 0),
                    'product_id' => $product_id,
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
        
        // Deduct inventory for new items
        foreach ($clean_items as $item) {
            if (!empty($item['product_id']) && $item['product_id'] > 0 && $item['quantity'] > 0) {
                brb_deduct_product_quantity($item['product_id'], $item['quantity'], $bill_id, 'sale');
            }
        }
        
        // Restore inventory for return items (returns add back to stock)
        foreach ($clean_return_items as $return_item) {
            if (!empty($return_item['product_id']) && $return_item['product_id'] > 0 && $return_item['quantity'] > 0) {
                brb_restore_product_quantity($return_item['product_id'], $return_item['quantity'], $bill_id, 'return');
            }
        }
        
        // Get old status before updating (for email notification)
        $old_status = get_post_meta($bill_id, '_brb_status', true);
        
        // Send email notification if status changed
        if ($status !== $old_status && $status !== 'draft') {
            BRB_Email::send_bill_notification($bill_id, 'updated');
        }
        
        wp_send_json_success(array(
            'message' => __('Invoice updated successfully!', 'black-rock-billing'),
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
                <?php $this->render_navigation_menu('customer-add'); ?>
            </div>
            
            <div style="background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h2 style="margin: 0; color: #fff; font-size: 1.5em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        <?php _e('Customer Information', 'black-rock-billing'); ?>
                    </h2>
                </div>
                
                <form id="brb-add-customer-form" class="brb-bill-form" style="padding: 30px; border: 0; box-shadow: none;">
                    <?php wp_nonce_field('brb_save_customer', 'brb_customer_nonce'); ?>
                    
                    <div class="brb-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                        <div class="brb-form-row">
                            <label for="brb_customer_first_name" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('First Name', 'black-rock-billing'); ?> <span class="required" style="color: #ef4444;">*</span></label>
                            <input type="text" id="brb_customer_first_name" name="first_name" required class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_last_name" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Last Name', 'black-rock-billing'); ?> <span class="required" style="color: #ef4444;">*</span></label>
                            <input type="text" id="brb_customer_last_name" name="last_name" required class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_email" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Email', 'black-rock-billing'); ?> <span class="required" style="color: #ef4444;">*</span></label>
                            <input type="email" id="brb_customer_email" name="user_email" required class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_phone" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Phone Number', 'black-rock-billing'); ?></label>
                            <input type="tel" id="brb_customer_phone" name="billing_phone" class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                    </div>
                    
                    <div class="brb-form-actions" style="padding-top: 25px; border-top: 2px solid #f1f5f9; display: flex; gap: 15px; justify-content: flex-start;">
                        <button type="submit" class="button button-primary" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Create Customer', 'black-rock-billing'); ?></button>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/customers')); ?>" class="button" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Cancel', 'black-rock-billing'); ?></a>
                    </div>
                </form>
            </div>
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
                <?php $this->render_navigation_menu('customers'); ?>
            </div>
            
            <div style="background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h2 style="margin: 0; color: #fff; font-size: 1.5em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        <?php _e('Customer Information', 'black-rock-billing'); ?>
                    </h2>
                </div>
                
                <form id="brb-edit-customer-form" class="brb-bill-form" style="padding: 30px; border: 0; box-shadow: none;">
                    <?php wp_nonce_field('brb_save_customer', 'brb_customer_nonce'); ?>
                    <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>" />
                    
                    <div class="brb-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                        <div class="brb-form-row">
                            <label for="brb_customer_first_name" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('First Name', 'black-rock-billing'); ?> <span class="required" style="color: #ef4444;">*</span></label>
                            <input type="text" id="brb_customer_first_name" name="first_name" value="<?php echo esc_attr($first_name); ?>" required class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_last_name" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Last Name', 'black-rock-billing'); ?> <span class="required" style="color: #ef4444;">*</span></label>
                            <input type="text" id="brb_customer_last_name" name="last_name" value="<?php echo esc_attr($last_name); ?>" required class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_email" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Email', 'black-rock-billing'); ?> <span class="required" style="color: #ef4444;">*</span></label>
                            <input type="email" id="brb_customer_email" name="user_email" value="<?php echo esc_attr($customer->user_email); ?>" required class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_customer_phone" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Phone Number', 'black-rock-billing'); ?></label>
                            <input type="tel" id="brb_customer_phone" name="billing_phone" value="<?php echo esc_attr($phone); ?>" class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                    </div>
                    
                    <div class="brb-form-actions" style="padding-top: 25px; border-top: 2px solid #f1f5f9; display: flex; gap: 15px; justify-content: flex-start;">
                        <button type="submit" class="button button-primary" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Update Customer', 'black-rock-billing'); ?></button>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/customers/' . $customer_id)); ?>" class="button" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Cancel', 'black-rock-billing'); ?></a>
                    </div>
                </form>
            </div>
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
    
    /**
     * Render import invoices page
     */
    public function render_import() {
        get_header();
        ?>
        <div class="brb-create-bill-container">
            <div class="brb-page-header">
                <h1><?php _e('Import Invoices', 'black-rock-billing'); ?></h1>
                <?php $this->render_navigation_menu('inventory'); ?>
            </div>
            
            <div style="background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h2 style="margin: 0; color: #fff; font-size: 1.5em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <?php _e('Import Invoices from CSV', 'black-rock-billing'); ?>
                    </h2>
                </div>
                
                <div style="padding: 30px;">
                    <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 2px solid #bae6fd; border-radius: 12px; padding: 20px; margin-bottom: 25px;">
                        <div style="display: flex; align-items: start; gap: 12px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #0284c7; flex-shrink: 0; margin-top: 2px;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            <div>
                                <p style="margin: 0 0 8px 0; font-weight: 600; color: #0c4a6e; font-size: 14px;"><?php _e('CSV Format Requirements', 'black-rock-billing'); ?></p>
                                <p style="margin: 0; color: #075985; font-size: 13px; line-height: 1.6;"><?php _e('The CSV should match the export format with the following columns: Invoice Number, Date, Due Date, Customer Name, Customer Email, Customer Phone, Status, Original Total, Return Total, Adjusted Total, Paid Amount, Pending Amount, Refund Due, Items Count, Return Items Count, Notes.', 'black-rock-billing'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <form id="brb-import-csv-form" enctype="multipart/form-data">
                        <?php wp_nonce_field('brb_import_csv', 'brb_import_nonce'); ?>
                        
                        <div class="brb-form-row" style="margin-bottom: 25px;">
                            <label for="brb_csv_file" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('CSV File', 'black-rock-billing'); ?> <span class="required" style="color: #ef4444;">*</span></label>
                            <div style="position: relative;">
                                <input type="file" id="brb_csv_file" name="csv_file" accept=".csv" required class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s; background: #fff; cursor: pointer;" />
                            </div>
                            <p class="description" style="margin: 8px 0 0 0; font-size: 13px; color: #64748b;"><?php _e('Select a CSV file to import. Maximum file size: 10MB', 'black-rock-billing'); ?></p>
                        </div>
                        
                        <div class="brb-form-row" style="margin-bottom: 25px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px 16px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; transition: all 0.3s; user-select: none;">
                                <input type="checkbox" id="brb_skip_duplicates" name="skip_duplicates" value="1" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: #3b82f6;" />
                                <span style="font-weight: 500; font-size: 14px; color: #475569;"><?php _e('Skip duplicate invoices (by invoice number)', 'black-rock-billing'); ?></span>
                            </label>
                        </div>
                        
                        <div class="brb-form-actions" style="margin-top: 30px; padding-top: 25px; border-top: 2px solid #f1f5f9; display: flex; gap: 15px; justify-content: flex-start;">
                            <button type="submit" class="button button-primary" style="padding: 14px 32px; font-weight: 600; font-size: 15px; display: inline-flex; align-items: center; gap: 8px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <?php _e('Import Invoices', 'black-rock-billing'); ?>
                            </button>
                            <a href="<?php echo esc_url(home_url('/billing-dashboard/bills')); ?>" class="button" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Cancel', 'black-rock-billing'); ?></a>
                        </div>
                    </form>
                    
                    <div id="brb-import-progress" style="display: none; margin-top: 25px;">
                        <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 2px solid #3b82f6; border-radius: 12px; padding: 20px;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #3b82f6; animation: spin 1s linear infinite;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <p style="margin: 0; font-weight: 600; color: #1e40af; font-size: 15px;"><?php _e('Importing...', 'black-rock-billing'); ?></p>
                            </div>
                            <p id="brb-import-status" style="margin: 0; color: #1e40af; font-size: 14px; line-height: 1.6;"></p>
                        </div>
                    </div>
                    
                    <div id="brb-import-results" style="display: none; margin-top: 25px;"></div>
                </div>
            </div>
            
            <style>
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            </style>
        </div>
        <?php
        get_footer();
    }
    
    /**
     * AJAX: Import invoices from CSV
     */
    public function ajax_import_invoices_csv() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to import invoices.', 'black-rock-billing')));
        }
        
        // Verify nonce
        if (!isset($_POST['brb_import_nonce']) || !wp_verify_nonce($_POST['brb_import_nonce'], 'brb_import_csv')) {
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'black-rock-billing')));
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('Please select a valid CSV file.', 'black-rock-billing')));
        }
        
        $file = $_FILES['csv_file'];
        $skip_duplicates = isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] === '1';
        
        // Validate file type
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'csv') {
            wp_send_json_error(array('message' => __('Invalid file type. Please upload a CSV file.', 'black-rock-billing')));
        }
        
        // Validate file size (10MB max)
        if ($file['size'] > 10 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('File size exceeds 10MB limit.', 'black-rock-billing')));
        }
        
        // Read CSV file
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            wp_send_json_error(array('message' => __('Unable to read CSV file.', 'black-rock-billing')));
        }
        
        // Skip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            wp_send_json_error(array('message' => __('CSV file is empty or invalid.', 'black-rock-billing')));
        }
        
        // Expected headers (flexible matching)
        $expected_headers = array(
            'invoice_number' => array('invoice number', 'bill number'),
            'date' => array('date', 'invoice date', 'bill date'),
            'due_date' => array('due date'),
            'customer_name' => array('customer name', 'name'),
            'customer_email' => array('customer email', 'email'),
            'customer_phone' => array('customer phone', 'phone'),
            'status' => array('status'),
            'original_total' => array('original total', 'total'),
            'return_total' => array('return total'),
            'adjusted_total' => array('adjusted total'),
            'paid_amount' => array('paid amount', 'paid'),
            'pending_amount' => array('pending amount', 'pending'),
            'refund_due' => array('refund due'),
            'items_count' => array('items count'),
            'return_items_count' => array('return items count'),
            'notes' => array('notes')
        );
        
        // Map headers to indices
        $header_map = array();
        foreach ($expected_headers as $key => $variations) {
            foreach ($headers as $index => $header) {
                $header_lower = strtolower(trim($header));
                foreach ($variations as $variation) {
                    if ($header_lower === strtolower($variation)) {
                        $header_map[$key] = $index;
                        break 2;
                    }
                }
            }
        }
        
        // Check required fields
        $required_fields = array('invoice_number', 'date', 'customer_email', 'original_total');
        $missing_fields = array();
        foreach ($required_fields as $field) {
            if (!isset($header_map[$field])) {
                $missing_fields[] = $expected_headers[$field][0];
            }
        }
        
        if (!empty($missing_fields)) {
            fclose($handle);
            wp_send_json_error(array('message' => sprintf(__('Missing required columns: %s', 'black-rock-billing'), implode(', ', $missing_fields))));
        }
        
        // Import invoices
        $imported = 0;
        $skipped = 0;
        $errors = array();
        $row_num = 1; // Header is row 1
        
        while (($row = fgetcsv($handle)) !== false) {
            $row_num++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            // Skip totals row
            if (isset($row[$header_map['invoice_number']]) && strtoupper(trim($row[$header_map['invoice_number']])) === 'TOTAL') {
                continue;
            }
            
            try {
                // Get values from CSV row
                $invoice_number = isset($header_map['invoice_number']) ? trim($row[$header_map['invoice_number']]) : '';
                $date = isset($header_map['date']) ? trim($row[$header_map['date']]) : '';
                $due_date = isset($header_map['due_date']) ? trim($row[$header_map['due_date']]) : '';
                $customer_name = isset($header_map['customer_name']) ? trim($row[$header_map['customer_name']]) : '';
                $customer_email = isset($header_map['customer_email']) ? trim($row[$header_map['customer_email']]) : '';
                $customer_phone = isset($header_map['customer_phone']) ? trim($row[$header_map['customer_phone']]) : '';
                $status = isset($header_map['status']) ? strtolower(trim($row[$header_map['status']])) : 'draft';
                $original_total = isset($header_map['original_total']) ? floatval($row[$header_map['original_total']]) : 0;
                $return_total = isset($header_map['return_total']) ? abs(floatval($row[$header_map['return_total']])) : 0;
                $adjusted_total = isset($header_map['adjusted_total']) ? floatval($row[$header_map['adjusted_total']]) : $original_total;
                $paid_amount = isset($header_map['paid_amount']) ? floatval($row[$header_map['paid_amount']]) : 0;
                $pending_amount = isset($header_map['pending_amount']) ? floatval($row[$header_map['pending_amount']]) : 0;
                $refund_due = isset($header_map['refund_due']) ? floatval($row[$header_map['refund_due']]) : 0;
                $items_count = isset($header_map['items_count']) ? intval($row[$header_map['items_count']]) : 0;
                $return_items_count = isset($header_map['return_items_count']) ? intval($row[$header_map['return_items_count']]) : 0;
                $notes = isset($header_map['notes']) ? trim($row[$header_map['notes']]) : '';
                
                // Validate required fields
                if (empty($invoice_number) || empty($customer_email) || empty($date)) {
                    $errors[] = sprintf(__('Row %d: Missing required fields', 'black-rock-billing'), $row_num);
                    continue;
                }
                
                // Check for duplicate invoice number
                if ($skip_duplicates) {
                    $existing = get_posts(array(
                        'post_type' => 'brb_bill',
                        'posts_per_page' => 1,
                        'post_status' => 'any',
                        'meta_query' => array(
                            array(
                                'key' => '_brb_bill_number',
                                'value' => $invoice_number,
                                'compare' => '='
                            )
                        )
                    ));
                    
                    if (!empty($existing)) {
                        $skipped++;
                        continue;
                    }
                }
                
                // Find or create customer
                $customer = get_user_by('email', $customer_email);
                if (!$customer) {
                    // Create new customer
                    $username = sanitize_user($customer_email);
                    $counter = 1;
                    while (username_exists($username)) {
                        $username = sanitize_user($customer_email) . $counter;
                        $counter++;
                    }
                    
                    $name_parts = explode(' ', $customer_name, 2);
                    $first_name = isset($name_parts[0]) ? $name_parts[0] : '';
                    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                    
                    $user_data = array(
                        'user_login' => $username,
                        'user_email' => $customer_email,
                        'user_pass' => wp_generate_password(12, false),
                        'display_name' => $customer_name,
                        'role' => 'subscriber'
                    );
                    
                    $customer_id = wp_insert_user($user_data);
                    
                    if (is_wp_error($customer_id)) {
                        $errors[] = sprintf(__('Row %d: Failed to create customer: %s', 'black-rock-billing'), $row_num, $customer_id->get_error_message());
                        continue;
                    }
                    
                    if ($first_name) {
                        update_user_meta($customer_id, 'first_name', $first_name);
                    }
                    if ($last_name) {
                        update_user_meta($customer_id, 'last_name', $last_name);
                    }
                    if ($customer_phone) {
                        update_user_meta($customer_id, 'billing_phone', $customer_phone);
                    }
                } else {
                    $customer_id = $customer->ID;
                }
                
                // Parse date
                $bill_date = '';
                if ($date) {
                    $parsed_date = strtotime($date);
                    if ($parsed_date !== false) {
                        $bill_date = date('Y-m-d', $parsed_date);
                    } else {
                        // Try different date formats
                        $formats = array('Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d');
                        foreach ($formats as $format) {
                            $parsed = date_create_from_format($format, $date);
                            if ($parsed !== false) {
                                $bill_date = $parsed->format('Y-m-d');
                                break;
                            }
                        }
                    }
                }
                
                if (empty($bill_date)) {
                    $bill_date = date('Y-m-d');
                }
                
                // Parse due date
                $parsed_due_date = '';
                if ($due_date) {
                    $parsed = strtotime($due_date);
                    if ($parsed !== false) {
                        $parsed_due_date = date('Y-m-d', $parsed);
                    } else {
                        $formats = array('Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d');
                        foreach ($formats as $format) {
                            $parsed = date_create_from_format($format, $due_date);
                            if ($parsed !== false) {
                                $parsed_due_date = $parsed->format('Y-m-d');
                                break;
                            }
                        }
                    }
                }
                
                // Validate status
                $valid_statuses = array('draft', 'sent', 'paid', 'overdue', 'cancelled');
                if (!in_array($status, $valid_statuses)) {
                    $status = 'draft';
                }
                
                // Create invoice post
                $post_data = array(
                    'post_type' => 'brb_bill',
                    'post_status' => 'publish',
                    'post_title' => sprintf(__('Invoice %s', 'black-rock-billing'), $invoice_number),
                    'post_content' => $notes
                );
                
                $post_id = wp_insert_post($post_data);
                
                if (is_wp_error($post_id)) {
                    $errors[] = sprintf(__('Row %d: Failed to create invoice: %s', 'black-rock-billing'), $row_num, $post_id->get_error_message());
                    continue;
                }
                
                // Save invoice meta
                update_post_meta($post_id, '_brb_bill_number', $invoice_number);
                update_post_meta($post_id, '_brb_bill_date', $bill_date);
                if ($parsed_due_date) {
                    update_post_meta($post_id, '_brb_due_date', $parsed_due_date);
                }
                update_post_meta($post_id, '_brb_customer_id', $customer_id);
                update_post_meta($post_id, '_brb_status', $status);
                update_post_meta($post_id, '_brb_total_amount', $original_total);
                update_post_meta($post_id, '_brb_paid_amount', $paid_amount);
                
                // Create placeholder items if items_count > 0
                if ($items_count > 0) {
                    $items = array();
                    for ($i = 0; $i < $items_count; $i++) {
                        $items[] = array(
                            'description' => sprintf(__('Imported Item %d', 'black-rock-billing'), $i + 1),
                            'quantity' => 1,
                            'rate' => $original_total / max($items_count, 1)
                        );
                    }
                    update_post_meta($post_id, '_brb_bill_items', $items);
                }
                
                // Create placeholder return items if return_items_count > 0
                if ($return_items_count > 0 && $return_total > 0) {
                    $return_items = array();
                    for ($i = 0; $i < $return_items_count; $i++) {
                        $return_items[] = array(
                            'description' => sprintf(__('Imported Return Item %d', 'black-rock-billing'), $i + 1),
                            'quantity' => 1,
                            'rate' => $return_total / max($return_items_count, 1)
                        );
                    }
                    update_post_meta($post_id, '_brb_return_items', $return_items);
                }
                
                // Calculate and save refund due
                $calculated_refund = 0;
                if ($paid_amount > $adjusted_total) {
                    $calculated_refund = $paid_amount - $adjusted_total;
                }
                update_post_meta($post_id, '_brb_refund_due', $calculated_refund);
                
                $imported++;
                
            } catch (Exception $e) {
                $errors[] = sprintf(__('Row %d: %s', 'black-rock-billing'), $row_num, $e->getMessage());
            }
        }
        
        fclose($handle);
        
        // Build response message
        $message = sprintf(
            __('Import completed: %d imported, %d skipped', 'black-rock-billing'),
            $imported,
            $skipped
        );
        
        if (!empty($errors)) {
            $message .= '. ' . sprintf(__('%d errors occurred.', 'black-rock-billing'), count($errors));
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'redirect' => home_url('/billing-dashboard/bills')
        ));
    }
    
    /**
     * Export invoices to CSV
     */
    private function export_invoices_csv() {
        // Check permissions
        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in to export invoices.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
        }
        
        // Get all invoices user can view
        if (current_user_can('manage_options')) {
            $args = array(
                'post_type' => 'brb_bill',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC'
            );
        } else {
            $args = array(
                'post_type' => 'brb_bill',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => '_brb_customer_id',
                        'value' => get_current_user_id(),
                        'compare' => '='
                    )
                )
            );
        }
        
        $bills = get_posts($args);
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=invoices-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output UTF-8 BOM for Excel compatibility
        echo "\xEF\xBB\xBF";
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        $headers = array(
            __('Invoice Number', 'black-rock-billing'),
            __('Date', 'black-rock-billing'),
            __('Due Date', 'black-rock-billing'),
            __('Customer Name', 'black-rock-billing'),
            __('Customer Email', 'black-rock-billing'),
            __('Customer Phone', 'black-rock-billing'),
            __('Status', 'black-rock-billing'),
            __('Original Total', 'black-rock-billing'),
            __('Return Total', 'black-rock-billing'),
            __('Adjusted Total', 'black-rock-billing'),
            __('Paid Amount', 'black-rock-billing'),
            __('Pending Amount', 'black-rock-billing'),
            __('Refund Due', 'black-rock-billing'),
            __('Items Count', 'black-rock-billing'),
            __('Return Items Count', 'black-rock-billing'),
            __('Notes', 'black-rock-billing')
        );
        
        fputcsv($output, $headers);
        
        // Initialize totals
        $totals = array(
            'original_total' => 0,
            'return_total' => 0,
            'adjusted_total' => 0,
            'paid_amount' => 0,
            'pending_amount' => 0,
            'refund_due' => 0,
            'items_count' => 0,
            'return_items_count' => 0
        );
        
        // Export each invoice
        foreach ($bills as $bill) {
            $bill_id = $bill->ID;
            $bill_number = get_post_meta($bill_id, '_brb_bill_number', true);
            $bill_date = get_post_meta($bill_id, '_brb_bill_date', true);
            $due_date = get_post_meta($bill_id, '_brb_due_date', true);
            $customer_id = get_post_meta($bill_id, '_brb_customer_id', true);
            $status = brb_get_bill_status($bill_id);
            $total = brb_get_bill_total($bill_id);
            $return_total = brb_get_return_total($bill_id);
            $adjusted_total = brb_get_adjusted_bill_total($bill_id);
            $paid = brb_get_paid_amount($bill_id);
            $pending = brb_get_pending_amount($bill_id);
            $refund_due = brb_get_refund_due($bill_id);
            $items = brb_get_bill_items($bill_id);
            $return_items = brb_get_return_items($bill_id);
            $notes = $bill->post_content;
            
            $customer = get_userdata($customer_id);
            $customer_name = $customer ? $customer->display_name : '';
            $customer_email = $customer ? $customer->user_email : '';
            $customer_phone = $customer_id ? brb_get_customer_phone($customer_id) : '';
            
            // Format dates
            $formatted_date = $bill_date ? date_i18n(get_option('date_format'), strtotime($bill_date)) : '';
            $formatted_due_date = $due_date ? date_i18n(get_option('date_format'), strtotime($due_date)) : '';
            
            // Format currency values (remove currency symbol for CSV)
            $currency_symbol = get_option('brb_currency_symbol', 'AED');
            $total_clean = str_replace($currency_symbol . ' ', '', brb_format_currency($total));
            $return_total_clean = str_replace($currency_symbol . ' ', '', brb_format_currency($return_total));
            $adjusted_total_clean = str_replace($currency_symbol . ' ', '', brb_format_currency($adjusted_total));
            $paid_clean = str_replace($currency_symbol . ' ', '', brb_format_currency($paid));
            $pending_clean = str_replace($currency_symbol . ' ', '', brb_format_currency($pending));
            $refund_due_clean = str_replace($currency_symbol . ' ', '', brb_format_currency($refund_due));
            
            // Accumulate totals
            $totals['original_total'] += floatval($total);
            $totals['return_total'] += floatval($return_total);
            $totals['adjusted_total'] += floatval($adjusted_total);
            $totals['paid_amount'] += floatval($paid);
            $totals['pending_amount'] += floatval($pending);
            $totals['refund_due'] += floatval($refund_due);
            $totals['items_count'] += count($items);
            $totals['return_items_count'] += count($return_items);
            
            $row = array(
                $bill_number ?: 'N/A',
                $formatted_date,
                $formatted_due_date,
                $customer_name,
                $customer_email,
                $customer_phone,
                ucfirst($status),
                $total_clean,
                $return_total > 0 ? '-' . $return_total_clean : '0',
                $adjusted_total_clean,
                $paid_clean,
                $pending_clean,
                $refund_due > 0 ? $refund_due_clean : '0',
                count($items),
                count($return_items),
                strip_tags($notes)
            );
            
            fputcsv($output, $row);
        }
        
        // Add totals row
        if (count($bills) > 0) {
            // Format totals
            $currency_symbol = get_option('brb_currency_symbol', 'AED');
            $total_original = number_format($totals['original_total'], 2, '.', '');
            $total_return = number_format($totals['return_total'], 2, '.', '');
            $total_adjusted = number_format($totals['adjusted_total'], 2, '.', '');
            $total_paid = number_format($totals['paid_amount'], 2, '.', '');
            $total_pending = number_format($totals['pending_amount'], 2, '.', '');
            $total_refund = number_format($totals['refund_due'], 2, '.', '');
            
            $totals_row = array(
                __('TOTAL', 'black-rock-billing'),
                '', // Date
                '', // Due Date
                '', // Customer Name
                '', // Customer Email
                '', // Customer Phone
                '', // Status
                $total_original,
                $totals['return_total'] > 0 ? '-' . $total_return : '0',
                $total_adjusted,
                $total_paid,
                $total_pending,
                $totals['refund_due'] > 0 ? $total_refund : '0',
                $totals['items_count'],
                $totals['return_items_count'],
                '' // Notes
            );
            
            fputcsv($output, $totals_row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX: Search products
     */
    public function ajax_search_products() {
        check_ajax_referer('brb_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to search products.', 'black-rock-billing')));
        }
        
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search_term)) {
            wp_send_json_success(array('products' => array()));
        }
        
        $args = array(
            'post_type' => 'brb_product',
            'posts_per_page' => 20,
            'post_status' => 'publish',
            's' => $search_term,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $products = get_posts($args);
        
        $results = array();
        foreach ($products as $product) {
            $purchased_from = get_post_meta($product->ID, '_brb_purchased_from', true);
            $purchased_rate = get_post_meta($product->ID, '_brb_purchased_rate', true);
            $sale_rate = get_post_meta($product->ID, '_brb_sale_rate', true);
            
            $quantity_available = brb_get_product_quantity($product->ID);
            
            $results[] = array(
                'id' => $product->ID,
                'name' => $product->post_title,
                'purchased_from' => $purchased_from,
                'purchased_rate' => floatval($purchased_rate),
                'sale_rate' => floatval($sale_rate),
                'quantity_available' => $quantity_available
            );
        }
        
        wp_send_json_success(array('products' => $results));
    }
    
    /**
     * AJAX: Get inventory history
     */
    public function ajax_get_inventory_history() {
        check_ajax_referer('brb_nonce', 'nonce');
        
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'black-rock-billing')));
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'black-rock-billing')));
        }
        
        $history = brb_get_inventory_history($product_id);
        
        wp_send_json_success(array('history' => $history));
    }
    
    /**
     * Render inventory page
     */
    public function render_inventory() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to view inventory.', 'black-rock-billing'), __('Access Denied', 'black-rock-billing'), array('response' => 403));
        }
        
        // Get all products
        $args = array(
            'post_type' => 'brb_product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $products = get_posts($args);
        
        get_header();
        ?>
        <div class="brb-dashboard-container">
            <div class="brb-dashboard-header">
                <h1><?php _e('Inventory Management', 'black-rock-billing'); ?></h1>
                <?php $this->render_navigation_menu('inventory'); ?>
            </div>
            
            <div class="brb-inventory-container" style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                <div class="brb-inventory-header" style="display: flex; gap: 15px; align-items: flex-end; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f1f5f9; flex-wrap: wrap;">
                    <input type="text" id="brb-search-inventory" placeholder="<?php _e('Search products...', 'black-rock-billing'); ?>" class="brb-form-input" style="flex: 1; min-width: 250px; max-width: 400px; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s; height: auto; min-height: 44px; box-sizing: border-box;" />
                    <select id="brb-filter-stock" class="brb-form-select" style="min-width: 200px; max-width: 250px; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #fff; cursor: pointer; transition: all 0.3s; height: auto; min-height: 44px; box-sizing: border-box;">
                        <option value=""><?php _e('All Stock Levels', 'black-rock-billing'); ?></option>
                        <option value="in_stock"><?php _e('In Stock', 'black-rock-billing'); ?></option>
                        <option value="low_stock"><?php _e('Low Stock (< 10)', 'black-rock-billing'); ?></option>
                        <option value="out_of_stock"><?php _e('Out of Stock', 'black-rock-billing'); ?></option>
                    </select>
                    <a href="<?php echo esc_url(home_url('/billing-dashboard/inventory/add')); ?>" class="button button-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; font-weight: 600; text-decoration: none; white-space: nowrap; height: auto; min-height: 44px; box-sizing: border-box;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <?php _e('Add Product', 'black-rock-billing'); ?>
                    </a>
                </div>
                
                <div style="overflow-x: auto;">
                <table class="brb-items-table-frontend" id="brb-inventory-table" style="width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1000px;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                            <th style="padding: 16px 20px; text-align: left; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff; border-bottom: 2px solid #1d4ed8; white-space: nowrap;"><?php _e('Product Name', 'black-rock-billing'); ?></th>
                            <th style="padding: 16px 20px; text-align: left; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff; border-bottom: 2px solid #1d4ed8; white-space: nowrap;"><?php _e('Purchased From', 'black-rock-billing'); ?></th>
                            <th style="padding: 16px 20px; text-align: right; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff; border-bottom: 2px solid #1d4ed8; white-space: nowrap;"><?php _e('Purchased Rate', 'black-rock-billing'); ?></th>
                            <th style="padding: 16px 20px; text-align: right; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff; border-bottom: 2px solid #1d4ed8; white-space: nowrap;"><?php _e('Sale Rate', 'black-rock-billing'); ?></th>
                            <th style="padding: 16px 20px; text-align: right; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff; border-bottom: 2px solid #1d4ed8; white-space: nowrap;"><?php _e('Stock Quantity', 'black-rock-billing'); ?></th>
                            <th style="padding: 16px 20px; text-align: right; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff; border-bottom: 2px solid #1d4ed8; white-space: nowrap;"><?php _e('Total Profit Potential', 'black-rock-billing'); ?></th>
                            <th style="padding: 16px 20px; text-align: center; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff; border-bottom: 2px solid #1d4ed8; white-space: nowrap;"><?php _e('Status', 'black-rock-billing'); ?></th>
                            <th style="padding: 16px 20px; text-align: center; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff; border-bottom: 2px solid #1d4ed8; white-space: nowrap;"><?php _e('Actions', 'black-rock-billing'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="brb-inventory-tbody">
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $product_id = $product->ID;
                                $purchased_from = get_post_meta($product_id, '_brb_purchased_from', true);
                                $purchased_rate = floatval(get_post_meta($product_id, '_brb_purchased_rate', true));
                                $sale_rate = floatval(get_post_meta($product_id, '_brb_sale_rate', true));
                                $quantity = brb_get_product_quantity($product_id);
                                
                                // Calculate profit
                                $profit_per_unit = $sale_rate - $purchased_rate;
                                $total_profit_potential = $profit_per_unit * $quantity;
                                
                                // Determine status
                                $status_class = 'in-stock';
                                $status_text = __('In Stock', 'black-rock-billing');
                                if ($quantity <= 0) {
                                    $status_class = 'out-of-stock';
                                    $status_text = __('Out of Stock', 'black-rock-billing');
                                } elseif ($quantity < 10) {
                                    $status_class = 'low-stock';
                                    $status_text = __('Low Stock', 'black-rock-billing');
                                }
                                ?>
                                <tr class="brb-inventory-row" data-product-name="<?php echo esc_attr(strtolower($product->post_title)); ?>" data-stock-status="<?php echo esc_attr($status_class); ?>" style="border-bottom: 1px solid #f1f5f9; transition: all 0.2s;">
                                    <td style="padding: 18px 20px; font-size: 15px; color: #1e293b;">
                                        <strong style="font-weight: 600; color: #0f172a;"><?php echo esc_html($product->post_title); ?></strong>
                                    </td>
                                    <td style="padding: 18px 20px; font-size: 14px; color: #64748b;">
                                        <?php echo $purchased_from ? esc_html($purchased_from) : '<span style="color: #cbd5e1;">—</span>'; ?>
                                    </td>
                                    <td style="padding: 18px 20px; text-align: right; font-size: 14px; font-weight: 600; color: #475569;">
                                        <?php echo $purchased_rate > 0 ? brb_format_currency($purchased_rate) : '<span style="color: #cbd5e1;">—</span>'; ?>
                                    </td>
                                    <td style="padding: 18px 20px; text-align: right; font-size: 14px; font-weight: 600; color: #475569;">
                                        <?php echo $sale_rate > 0 ? brb_format_currency($sale_rate) : '<span style="color: #cbd5e1;">—</span>'; ?>
                                    </td>
                                    <td style="padding: 18px 20px; text-align: right; font-size: 15px;">
                                        <span class="brb-stock-quantity brb-stock-<?php echo esc_attr($status_class); ?>" style="font-weight: 700; font-size: 15px; padding: 6px 12px; border-radius: 6px; display: inline-block; <?php echo $quantity <= 0 ? 'background: #fee2e2; color: #dc2626;' : ($quantity < 10 ? 'background: #fef3c7; color: #f59e0b;' : 'background: #d1fae5; color: #10b981;'); ?>">
                                            <?php echo number_format($quantity, 2); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 18px 20px; text-align: right; font-size: 15px;">
                                        <?php if ($purchased_rate > 0 && $sale_rate > 0 && $quantity > 0): ?>
                                            <span style="font-weight: 700; font-size: 15px; color: <?php echo $total_profit_potential >= 0 ? '#10b981' : '#dc2626'; ?>;">
                                                <?php echo brb_format_currency($total_profit_potential); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 18px 20px; text-align: center;">
                                        <span class="brb-status brb-status-<?php echo esc_attr($status_class); ?>" style="display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; <?php echo $status_class === 'out-of-stock' ? 'background: #fee2e2; color: #dc2626;' : ($status_class === 'low-stock' ? 'background: #fef3c7; color: #f59e0b;' : 'background: #d1fae5; color: #10b981;'); ?>">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 18px 20px; text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                            <a href="<?php echo esc_url(home_url('/billing-dashboard/inventory/edit/' . $product_id)); ?>" class="brb-action-btn brb-action-edit" title="<?php _e('Edit Product', 'black-rock-billing'); ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 6px; background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; transition: all 0.2s; text-decoration: none;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </a>
                                            <button type="button" class="brb-action-btn brb-action-view brb-view-history-btn" data-product-id="<?php echo esc_attr($product_id); ?>" data-product-name="<?php echo esc_attr($product->post_title); ?>" title="<?php _e('View History', 'black-rock-billing'); ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 6px; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; transition: all 0.2s; cursor: pointer;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 60px 40px; background: #f8fafc;">
                                    <div style="max-width: 400px; margin: 0 auto;">
                                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #cbd5e1; margin: 0 auto 20px; display: block;">
                                            <rect x="3" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="3" width="7" height="7"></rect>
                                            <rect x="3" y="14" width="7" height="7"></rect>
                                            <rect x="14" y="14" width="7" height="7"></rect>
                                        </svg>
                                        <p style="font-size: 16px; color: #64748b; margin-bottom: 20px; font-weight: 500;"><?php _e('No products found. Add products from WordPress Admin.', 'black-rock-billing'); ?></p>
                                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=brb_product')); ?>" class="button button-primary" style="display: inline-block;"><?php _e('Add Product', 'black-rock-billing'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        
        <!-- History Modal -->
        <div id="brb-history-modal" class="brb-modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 10000; overflow-y: auto; align-items: center; justify-content: center; padding: 20px; opacity: 0; transition: opacity 0.3s ease-in-out;">
            <div class="brb-modal-content" style="max-width: 900px; width: 100%; margin: auto; background: white; border-radius: 16px; padding: 0; position: relative; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(0, 0, 0, 0.05); max-height: 90vh; overflow: hidden; transform: scale(0.95); transition: transform 0.3s ease-out, opacity 0.3s ease-out; opacity: 0;">
                <!-- Modal Header -->
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <h2 id="brb-history-product-name" style="margin: 0; font-size: 22px; font-weight: 700; color: #ffffff; padding-right: 40px; line-height: 1.4;"></h2>
                        <button type="button" id="brb-close-history-modal" style="position: absolute; top: 20px; right: 20px; background: rgba(255, 255, 255, 0.2); border: none; font-size: 24px; cursor: pointer; color: #ffffff; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s; flex-shrink: 0;" onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'; this.style.transform='rotate(90deg)';" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'; this.style.transform='rotate(0deg)';">
                            &times;
                        </button>
                    </div>
                </div>
                <!-- Modal Body -->
                <div style="padding: 30px; max-height: calc(90vh - 100px); overflow-y: auto;">
                    <div id="brb-history-content"></div>
                </div>
            </div>
        </div>
        
        <style>
        .brb-inventory-row:hover {
            background: #f8fafc !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        #brb-search-inventory:focus, #brb-filter-stock:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        .brb-modal-backdrop {
            animation: fadeIn 0.3s ease-out forwards;
        }
        .brb-modal-backdrop.show {
            opacity: 1 !important;
        }
        .brb-modal-backdrop.show .brb-modal-content {
            transform: scale(1) translateY(0) !important;
            opacity: 1 !important;
        }
        .brb-modal-content {
            animation: slideUp 0.3s ease-out forwards;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        @keyframes slideUp {
            from {
                transform: scale(0.95) translateY(20px);
                opacity: 0;
            }
            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }
        </style>
        <script>
        jQuery(document).ready(function($) {
            // Filter inventory
            function filterInventory() {
                var searchTerm = $('#brb-search-inventory').val().toLowerCase();
                var stockFilter = $('#brb-filter-stock').val();
                var $rows = $('.brb-inventory-row');
                var visibleCount = 0;
                
                $rows.each(function() {
                    var $row = $(this);
                    var productName = $row.data('product-name') || '';
                    var stockStatus = $row.data('stock-status') || '';
                    
                    var matchesSearch = !searchTerm || productName.indexOf(searchTerm) !== -1;
                    var matchesStock = !stockFilter || 
                        (stockFilter === 'in_stock' && stockStatus === 'in-stock') ||
                        (stockFilter === 'low_stock' && stockStatus === 'low-stock') ||
                        (stockFilter === 'out_of_stock' && stockStatus === 'out-of-stock');
                    
                    if (matchesSearch && matchesStock) {
                        $row.show();
                        visibleCount++;
                    } else {
                        $row.hide();
                    }
                });
            }
            
            $('#brb-search-inventory, #brb-filter-stock').on('input change', filterInventory);
            
            // View history
            $('.brb-view-history-btn').on('click', function() {
                var productId = $(this).data('product-id');
                var productName = $(this).data('product-name');
                
                $('#brb-history-product-name').text(productName + ' - <?php _e('Inventory History', 'black-rock-billing'); ?>');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'brb_get_inventory_history',
                        nonce: '<?php echo wp_create_nonce('brb_nonce'); ?>',
                        product_id: productId
                    },
                    success: function(response) {
                        if (response.success) {
                            var history = response.data.history;
                            var html = '<table style="width: 100%; border-collapse: collapse;">';
                            html += '<thead><tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">';
                            html += '<th style="padding: 10px; text-align: left;"><?php _e('Date/Time', 'black-rock-billing'); ?></th>';
                            html += '<th style="padding: 10px; text-align: left;"><?php _e('Action', 'black-rock-billing'); ?></th>';
                            html += '<th style="padding: 10px; text-align: right;"><?php _e('Old Qty', 'black-rock-billing'); ?></th>';
                            html += '<th style="padding: 10px; text-align: right;"><?php _e('Change', 'black-rock-billing'); ?></th>';
                            html += '<th style="padding: 10px; text-align: right;"><?php _e('New Qty', 'black-rock-billing'); ?></th>';
                            html += '<th style="padding: 10px; text-align: left;"><?php _e('Invoice', 'black-rock-billing'); ?></th>';
                            html += '</tr></thead><tbody>';
                            
                            if (history.length > 0) {
                                history.forEach(function(entry) {
                                    var changeClass = entry.change > 0 ? 'color: #10b981;' : 'color: #dc2626;';
                                    var changeSign = entry.change > 0 ? '+' : '';
                                    var actionText = entry.action === 'sale' ? '<?php _e('Sale', 'black-rock-billing'); ?>' :
                                                    entry.action === 'return' ? '<?php _e('Return', 'black-rock-billing'); ?>' :
                                                    entry.action === 'manual_update' ? '<?php _e('Manual Update', 'black-rock-billing'); ?>' :
                                                    entry.action === 'stock_purchase' ? '<?php _e('Stock Purchase', 'black-rock-billing'); ?>' :
                                                    entry.action === 'invoice_edit' ? '<?php _e('Invoice Edit', 'black-rock-billing'); ?>' :
                                                    entry.action === 'return_removed' ? '<?php _e('Return Removed', 'black-rock-billing'); ?>' :
                                                    entry.action;
                                    
                                    var invoiceLink = entry.invoice_id > 0 ? 
                                        '<a href="<?php echo home_url('/billing-dashboard/bill/'); ?>' + entry.invoice_id + '">#' + entry.invoice_id + '</a>' : '—';
                                    
                                    html += '<tr style="border-bottom: 1px solid #e2e8f0;">';
                                    html += '<td style="padding: 10px;">' + entry.timestamp + '</td>';
                                    html += '<td style="padding: 10px;">' + actionText + '</td>';
                                    html += '<td style="padding: 10px; text-align: right;">' + parseFloat(entry.old_quantity).toFixed(2) + '</td>';
                                    html += '<td style="padding: 10px; text-align: right; ' + changeClass + '">' + changeSign + parseFloat(entry.change).toFixed(2) + '</td>';
                                    html += '<td style="padding: 10px; text-align: right; font-weight: bold;">' + parseFloat(entry.new_quantity).toFixed(2) + '</td>';
                                    html += '<td style="padding: 10px;">' + invoiceLink + '</td>';
                                    html += '</tr>';
                                });
                            } else {
                                html += '<tr><td colspan="6" style="padding: 20px; text-align: center;"><?php _e('No history available.', 'black-rock-billing'); ?></td></tr>';
                            }
                            
                            html += '</tbody></table>';
                            $('#brb-history-content').html(html);
                            $('#brb-history-modal').css('display', 'flex');
                            setTimeout(function() {
                                $('#brb-history-modal').addClass('show');
                            }, 10);
                        }
                    }
                });
            });
            
            $('#brb-close-history-modal, #brb-history-modal').on('click', function(e) {
                if (e.target === this || $(e.target).attr('id') === 'brb-close-history-modal') {
                    $('#brb-history-modal').removeClass('show');
                    setTimeout(function() {
                        $('#brb-history-modal').hide();
                    }, 300);
                }
            });
            
            // Prevent modal from closing when clicking inside the content
            $('.brb-modal-content').on('click', function(e) {
                e.stopPropagation();
            });
        });
        </script>
        <?php
        get_footer();
    }
    
    /**
     * Render add product page
     */
    public function render_add_product() {
        get_header();
        ?>
        <div class="brb-create-bill-container">
            <div class="brb-page-header">
                <h1><?php _e('Add New Product', 'black-rock-billing'); ?></h1>
                <?php $this->render_navigation_menu('inventory'); ?>
            </div>
            
            <div style="background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h2 style="margin: 0; color: #fff; font-size: 1.5em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <?php _e('Product Information', 'black-rock-billing'); ?>
                    </h2>
                </div>
                
                <form id="brb-add-product-form" class="brb-bill-form" style="padding: 30px; border: 0; box-shadow: none;">
                    <?php wp_nonce_field('brb_save_product', 'brb_product_nonce'); ?>
                    
                    <div class="brb-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                        <div class="brb-form-row" style="grid-column: 1 / -1;">
                            <label for="brb_product_name" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Product Name', 'black-rock-billing'); ?> <span class="required" style="color: #ef4444;">*</span></label>
                            <input type="text" id="brb_product_name" name="product_name" required class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_purchased_from" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Purchased From', 'black-rock-billing'); ?></label>
                            <input type="text" id="brb_purchased_from" name="purchased_from" class="brb-form-input" placeholder="<?php _e('Supplier or vendor name', 'black-rock-billing'); ?>" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_purchased_rate" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Purchased Rate', 'black-rock-billing'); ?></label>
                            <input type="number" id="brb_purchased_rate" name="purchased_rate" step="0.01" min="0" class="brb-form-input" placeholder="0.00" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                            <p class="description" style="margin-top: 8px; font-size: 13px; color: #64748b;"><?php _e('Cost price per unit', 'black-rock-billing'); ?></p>
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_sale_rate" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Sale Rate', 'black-rock-billing'); ?></label>
                            <input type="number" id="brb_sale_rate" name="sale_rate" step="0.01" min="0" class="brb-form-input" placeholder="0.00" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                            <p class="description" style="margin-top: 8px; font-size: 13px; color: #64748b;"><?php _e('Selling price per unit', 'black-rock-billing'); ?></p>
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_quantity_available" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Initial Stock Quantity', 'black-rock-billing'); ?></label>
                            <input type="number" id="brb_quantity_available" name="quantity_available" step="0.01" min="0" value="0" class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                            <p class="description" style="margin-top: 8px; font-size: 13px; color: #64748b;"><?php _e('Starting inventory quantity', 'black-rock-billing'); ?></p>
                        </div>
                    </div>
                    
                    <div class="brb-form-actions" style="padding-top: 25px; border-top: 2px solid #f1f5f9; display: flex; gap: 15px; justify-content: flex-start;">
                        <button type="submit" class="button button-primary" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Create Product', 'black-rock-billing'); ?></button>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/inventory')); ?>" class="button" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Cancel', 'black-rock-billing'); ?></a>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#brb-add-product-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'brb_save_product',
                    nonce: $('#brb_product_nonce').val(),
                    product_name: $('#brb_product_name').val(),
                    purchased_from: $('#brb_purchased_from').val(),
                    purchased_rate: $('#brb_purchased_rate').val(),
                    sale_rate: $('#brb_sale_rate').val(),
                    quantity_available: $('#brb_quantity_available').val()
                };
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect;
                        } else {
                            alert(response.data.message || '<?php _e('Error saving product.', 'black-rock-billing'); ?>');
                        }
                    }
                });
            });
        });
        </script>
        <?php
        get_footer();
    }
    
    /**
     * Render edit product page
     */
    public function render_edit_product($product_id) {
        $product = get_post($product_id);
        
        if (!$product || $product->post_type !== 'brb_product') {
            wp_redirect(home_url('/billing-dashboard/inventory'));
            exit;
        }
        
        $purchased_from = get_post_meta($product_id, '_brb_purchased_from', true);
        $purchased_rate = get_post_meta($product_id, '_brb_purchased_rate', true);
        $sale_rate = get_post_meta($product_id, '_brb_sale_rate', true);
        $quantity_available = get_post_meta($product_id, '_brb_quantity_available', true);
        if ($quantity_available === '') {
            $quantity_available = 0;
        }
        
        get_header();
        ?>
        <div class="brb-create-bill-container">
            <div class="brb-page-header">
                <h1><?php _e('Edit Product', 'black-rock-billing'); ?> - <?php echo esc_html($product->post_title); ?></h1>
                <?php $this->render_navigation_menu('inventory'); ?>
            </div>
            
            <div style="background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h2 style="margin: 0; color: #fff; font-size: 1.5em; font-weight: 700; letter-spacing: -0.3px; display: flex; align-items: center; gap: 12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.9;">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <?php _e('Product Information', 'black-rock-billing'); ?>
                    </h2>
                </div>
                
                <form id="brb-edit-product-form" class="brb-bill-form" style="padding: 30px; border: 0; box-shadow: none;">
                    <?php wp_nonce_field('brb_save_product', 'brb_product_nonce'); ?>
                    <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>" />
                    
                    <div class="brb-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                        <div class="brb-form-row" style="grid-column: 1 / -1;">
                            <label for="brb_product_name" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Product Name', 'black-rock-billing'); ?> <span class="required" style="color: #ef4444;">*</span></label>
                            <input type="text" id="brb_product_name" name="product_name" value="<?php echo esc_attr($product->post_title); ?>" required class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_purchased_from" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Purchased From', 'black-rock-billing'); ?></label>
                            <input type="text" id="brb_purchased_from" name="purchased_from" value="<?php echo esc_attr($purchased_from); ?>" class="brb-form-input" placeholder="<?php _e('Supplier or vendor name', 'black-rock-billing'); ?>" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_purchased_rate" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Purchased Rate', 'black-rock-billing'); ?></label>
                            <input type="number" id="brb_purchased_rate" name="purchased_rate" value="<?php echo esc_attr($purchased_rate); ?>" step="0.01" min="0" class="brb-form-input" placeholder="0.00" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                            <p class="description" style="margin-top: 8px; font-size: 13px; color: #64748b;"><?php _e('Cost price per unit', 'black-rock-billing'); ?></p>
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_sale_rate" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Sale Rate', 'black-rock-billing'); ?></label>
                            <input type="number" id="brb_sale_rate" name="sale_rate" value="<?php echo esc_attr($sale_rate); ?>" step="0.01" min="0" class="brb-form-input" placeholder="0.00" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                            <p class="description" style="margin-top: 8px; font-size: 13px; color: #64748b;"><?php _e('Selling price per unit', 'black-rock-billing'); ?></p>
                        </div>
                        
                        <div class="brb-form-row">
                            <label for="brb_quantity_available" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569;"><?php _e('Stock Quantity', 'black-rock-billing'); ?></label>
                            <input type="number" id="brb_quantity_available" name="quantity_available" value="<?php echo esc_attr($quantity_available); ?>" step="0.01" min="0" class="brb-form-input" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; transition: all 0.3s;" />
                            <p class="description" style="margin-top: 8px; font-size: 13px; color: #64748b;"><?php _e('Current inventory quantity', 'black-rock-billing'); ?></p>
                        </div>
                    </div>
                    
                    <div class="brb-form-actions" style="padding-top: 25px; border-top: 2px solid #f1f5f9; display: flex; gap: 15px; justify-content: flex-start;">
                        <button type="submit" class="button button-primary" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Update Product', 'black-rock-billing'); ?></button>
                        <a href="<?php echo esc_url(home_url('/billing-dashboard/inventory')); ?>" class="button" style="padding: 14px 32px; font-weight: 600; font-size: 15px;"><?php _e('Cancel', 'black-rock-billing'); ?></a>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#brb-edit-product-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'brb_save_product',
                    nonce: $('#brb_product_nonce').val(),
                    product_id: $('input[name="product_id"]').val(),
                    product_name: $('#brb_product_name').val(),
                    purchased_from: $('#brb_purchased_from').val(),
                    purchased_rate: $('#brb_purchased_rate').val(),
                    sale_rate: $('#brb_sale_rate').val(),
                    quantity_available: $('#brb_quantity_available').val()
                };
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect;
                        } else {
                            alert(response.data.message || '<?php _e('Error saving product.', 'black-rock-billing'); ?>');
                        }
                    }
                });
            });
        });
        </script>
        <?php
        get_footer();
    }
    
    /**
     * AJAX: Save product (create or update)
     */
    public function ajax_save_product() {
        check_ajax_referer('brb_save_product', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'black-rock-billing')));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $product_name = sanitize_text_field($_POST['product_name'] ?? '');
        $purchased_from = sanitize_text_field($_POST['purchased_from'] ?? '');
        $purchased_rate = isset($_POST['purchased_rate']) ? floatval($_POST['purchased_rate']) : 0;
        $sale_rate = isset($_POST['sale_rate']) ? floatval($_POST['sale_rate']) : 0;
        $quantity_available = isset($_POST['quantity_available']) ? floatval($_POST['quantity_available']) : 0;
        
        if (empty($product_name)) {
            wp_send_json_error(array('message' => __('Product name is required.', 'black-rock-billing')));
        }
        
        if ($product_id) {
            // Update existing product
            $product = get_post($product_id);
            if (!$product || $product->post_type !== 'brb_product') {
                wp_send_json_error(array('message' => __('Invalid product ID.', 'black-rock-billing')));
            }
            
            // Get old quantity for history tracking
            $old_quantity = brb_get_product_quantity($product_id);
            
            wp_update_post(array(
                'ID' => $product_id,
                'post_title' => $product_name
            ));
            
            update_post_meta($product_id, '_brb_purchased_from', $purchased_from);
            update_post_meta($product_id, '_brb_purchased_rate', $purchased_rate);
            update_post_meta($product_id, '_brb_sale_rate', $sale_rate);
            
            // Update quantity and track history if changed
            if ($quantity_available != $old_quantity) {
                update_post_meta($product_id, '_brb_quantity_available', $quantity_available);
                brb_log_inventory_change($product_id, $old_quantity, $quantity_available, 'manual_update', 0);
            }
            
            wp_send_json_success(array(
                'message' => __('Product updated successfully.', 'black-rock-billing'),
                'redirect' => home_url('/billing-dashboard/inventory')
            ));
        } else {
            // Create new product
            $new_product_id = wp_insert_post(array(
                'post_title' => $product_name,
                'post_type' => 'brb_product',
                'post_status' => 'publish'
            ));
            
            if (is_wp_error($new_product_id)) {
                wp_send_json_error(array('message' => $new_product_id->get_error_message()));
            }
            
            update_post_meta($new_product_id, '_brb_purchased_from', $purchased_from);
            update_post_meta($new_product_id, '_brb_purchased_rate', $purchased_rate);
            update_post_meta($new_product_id, '_brb_sale_rate', $sale_rate);
            update_post_meta($new_product_id, '_brb_quantity_available', $quantity_available);
            
            // Log initial quantity
            if ($quantity_available > 0) {
                brb_log_inventory_change($new_product_id, 0, $quantity_available, 'stock_purchase', 0);
            }
            
            wp_send_json_success(array(
                'message' => __('Product created successfully.', 'black-rock-billing'),
                'redirect' => home_url('/billing-dashboard/inventory')
            ));
        }
    }
    
    /**
     * Render reports page
     */
    public function render_reports() {
        // Get date range from request
        $date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : 'month';
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        
        // Calculate dates based on range
        $today = date('Y-m-d');
        switch ($date_range) {
            case 'week':
                $start_date = date('Y-m-d', strtotime('monday this week'));
                $end_date = date('Y-m-d', strtotime('sunday this week'));
                break;
            case 'month':
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-t');
                break;
            case 'year':
                $start_date = date('Y-01-01');
                $end_date = date('Y-12-31');
                break;
            case 'all':
                // Get the earliest invoice date or use a very early date
                $first_bill = get_posts(array(
                    'post_type' => 'brb_bill',
                    'posts_per_page' => 1,
                    'post_status' => 'publish',
                    'orderby' => 'meta_value',
                    'order' => 'ASC',
                    'meta_key' => '_brb_bill_date'
                ));
                
                if (!empty($first_bill)) {
                    $first_date = get_post_meta($first_bill[0]->ID, '_brb_bill_date', true);
                    $start_date = $first_date ? $first_date : '2000-01-01';
                } else {
                    $start_date = '2000-01-01';
                }
                $end_date = $today;
                break;
            case 'custom':
                if (empty($start_date)) {
                    $start_date = date('Y-m-01');
                }
                if (empty($end_date)) {
                    $end_date = $today;
                }
                break;
            default:
                $start_date = date('Y-m-01');
                $end_date = date('Y-m-t');
        }
        
        // Calculate totals
        $total_sales = brb_calculate_total_sales($start_date, $end_date);
        $total_profit = brb_calculate_total_profit($start_date, $end_date);
        $total_credits = brb_calculate_total_credits($start_date, $end_date);
        $invoice_count = brb_get_invoice_count($start_date, $end_date);
        $pending_amount = $total_sales - $total_credits;
        
        get_header();
        ?>
        <div class="brb-dashboard-container">
            <div class="brb-dashboard-header">
                <h1><?php _e('Reports & Analytics', 'black-rock-billing'); ?></h1>
                <?php $this->render_navigation_menu('reports'); ?>
            </div>
            
            <div class="brb-reports-container">
                <!-- Date Range Filter -->
                <div class="brb-reports-filters">
                    <form method="get" action="<?php echo esc_url(home_url('/billing-dashboard/reports')); ?>" class="brb-reports-form">
                        <div class="brb-reports-field">
                            <label class="brb-reports-label"><?php _e('Date Range', 'black-rock-billing'); ?></label>
                            <select name="date_range" id="brb-date-range" class="brb-reports-select">
                                <option value="week" <?php selected($date_range, 'week'); ?>><?php _e('This Week', 'black-rock-billing'); ?></option>
                                <option value="month" <?php selected($date_range, 'month'); ?>><?php _e('This Month', 'black-rock-billing'); ?></option>
                                <option value="year" <?php selected($date_range, 'year'); ?>><?php _e('This Year', 'black-rock-billing'); ?></option>
                                <option value="all" <?php selected($date_range, 'all'); ?>><?php _e('All Time', 'black-rock-billing'); ?></option>
                                <option value="custom" <?php selected($date_range, 'custom'); ?>><?php _e('Custom Range', 'black-rock-billing'); ?></option>
                            </select>
                        </div>
                        <div id="brb-custom-dates" class="brb-custom-dates" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>;">
                            <div class="brb-custom-date-field">
                                <label class="brb-reports-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="brb-label-icon">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    <?php _e('Start Date', 'black-rock-billing'); ?>
                                </label>
                                <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="brb-reports-input" />
                            </div>
                            <div class="brb-date-separator">→</div>
                            <div class="brb-custom-date-field">
                                <label class="brb-reports-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="brb-label-icon">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    <?php _e('End Date', 'black-rock-billing'); ?>
                                </label>
                                <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="brb-reports-input" />
                            </div>
                        </div>
                        <div class="brb-reports-submit">
                            <button type="submit" class="button button-primary brb-reports-button">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="brb-button-icon">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                </svg>
                                <?php _e('Apply Filter', 'black-rock-billing'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <style>
                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                #brb-custom-dates {
                    transition: all 0.3s ease-out;
                }
                </style>
                
                <!-- Summary Cards -->
                <div class="brb-reports-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="brb-summary-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-top: 7px solid #3b82f6; position: relative; overflow: hidden;">
                        <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: #3b82f6;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <h3 style="font-size: 14px; color: #6b7280; margin: 0 0 8px 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Total Sales', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount" style="font-size: 28px; font-weight: 700; color: #1f2937; margin: 0 0 8px 0; line-height: 1.2;"><?php echo brb_format_currency($total_sales); ?></p>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px;"><?php printf(__('%d invoices', 'black-rock-billing'), $invoice_count); ?></div>
                    </div>
                    
                    <div class="brb-summary-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-top: 7px solid #10b981; position: relative; overflow: hidden;">
                        <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: #10b981;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="2" x2="12" y2="22"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </div>
                        <h3 style="font-size: 14px; color: #6b7280; margin: 0 0 8px 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Total Profit', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount" style="font-size: 28px; font-weight: 700; color: <?php echo $total_profit >= 0 ? '#10b981' : '#dc2626'; ?>; margin: 0 0 8px 0; line-height: 1.2;"><?php echo brb_format_currency($total_profit); ?></p>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px;"><?php _e('From inventory products', 'black-rock-billing'); ?></div>
                    </div>
                    
                    <div class="brb-summary-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-top: 7px solid #f59e0b; position: relative; overflow: hidden;">
                        <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: #f59e0b;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <h3 style="font-size: 14px; color: #6b7280; margin: 0 0 8px 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Total Credits (Paid)', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount" style="font-size: 28px; font-weight: 700; color: #1f2937; margin: 0 0 8px 0; line-height: 1.2;"><?php echo brb_format_currency($total_credits); ?></p>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px;"><?php _e('Amount received', 'black-rock-billing'); ?></div>
                    </div>
                    
                    <div class="brb-summary-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-top: 7px solid #ef4444; position: relative; overflow: hidden;">
                        <div class="brb-summary-card-icon" style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: #ef4444;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <h3 style="font-size: 14px; color: #6b7280; margin: 0 0 8px 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;"><?php _e('Pending Amount', 'black-rock-billing'); ?></h3>
                        <p class="brb-amount" style="font-size: 28px; font-weight: 700; color: #1f2937; margin: 0 0 8px 0; line-height: 1.2;"><?php echo brb_format_currency($pending_amount); ?></p>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px;"><?php _e('Outstanding', 'black-rock-billing'); ?></div>
                    </div>
                </div>
                
                <!-- Date Range Display -->
                <div style="background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <p style="margin: 0; color: #6b7280;">
                        <strong><?php _e('Report Period:', 'black-rock-billing'); ?></strong> 
                        <?php if ($date_range === 'all'): ?>
                            <?php _e('All Time', 'black-rock-billing'); ?>
                            <?php if ($start_date !== '2000-01-01'): ?>
                                (<?php echo date_i18n(get_option('date_format'), strtotime($start_date)); ?> 
                                <?php _e('to', 'black-rock-billing'); ?> 
                                <?php echo date_i18n(get_option('date_format'), strtotime($end_date)); ?>)
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo date_i18n(get_option('date_format'), strtotime($start_date)); ?> 
                            <?php _e('to', 'black-rock-billing'); ?> 
                            <?php echo date_i18n(get_option('date_format'), strtotime($end_date)); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#brb-date-range').on('change', function() {
                var $customDates = $('#brb-custom-dates');
                if ($(this).val() === 'custom') {
                    $customDates.css('display', 'flex').hide().fadeIn(300);
                } else {
                    $customDates.fadeOut(200, function() {
                        $(this).css('display', 'none');
                    });
                }
            });
        });
        </script>
        <?php
        get_footer();
    }
}

// Initialize
new BRB_Frontend();