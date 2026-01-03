<?php
/**
 * Custom Post Types
 *
 * @package Black_Rock_Billing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BRB_Post_Types {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_filter('manage_brb_bill_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_brb_bill_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
        add_filter('manage_edit-brb_bill_sortable_columns', array($this, 'sortable_columns'));
        add_action('restrict_manage_posts', array($this, 'add_customer_filter'));
        add_filter('parse_query', array($this, 'filter_by_customer'));
    }
    
    /**
     * Register custom post types
     */
    public function register_post_types() {
        $labels = array(
            'name'                  => _x('Bills', 'Post type general name', 'black-rock-billing'),
            'singular_name'         => _x('Bill', 'Post type singular name', 'black-rock-billing'),
            'menu_name'             => _x('Bills', 'Admin Menu text', 'black-rock-billing'),
            'name_admin_bar'        => _x('Bill', 'Add New on Toolbar', 'black-rock-billing'),
            'add_new'               => __('Add New', 'black-rock-billing'),
            'add_new_item'          => __('Add New Bill', 'black-rock-billing'),
            'new_item'              => __('New Bill', 'black-rock-billing'),
            'edit_item'             => __('Edit Bill', 'black-rock-billing'),
            'view_item'             => __('View Bill', 'black-rock-billing'),
            'all_items'             => __('All Bills', 'black-rock-billing'),
            'search_items'          => __('Search Bills', 'black-rock-billing'),
            'parent_item_colon'     => __('Parent Bills:', 'black-rock-billing'),
            'not_found'             => __('No bills found.', 'black-rock-billing'),
            'not_found_in_trash'    => __('No bills found in Trash.', 'black-rock-billing'),
            'featured_image'        => _x('Bill Featured Image', 'Overrides the "Featured Image" phrase', 'black-rock-billing'),
            'set_featured_image'    => _x('Set bill featured image', 'Overrides the "Set featured image" phrase', 'black-rock-billing'),
            'remove_featured_image' => _x('Remove bill featured image', 'Overrides the "Remove featured image" phrase', 'black-rock-billing'),
            'use_featured_image'    => _x('Use as bill featured image', 'Overrides the "Use as featured image" phrase', 'black-rock-billing'),
            'archives'              => _x('Bill archives', 'The post type archive label used in nav menus', 'black-rock-billing'),
            'insert_into_item'      => _x('Insert into bill', 'Overrides the "Insert into post"/"Insert into page" phrase', 'black-rock-billing'),
            'uploaded_to_this_item' => _x('Uploaded to this bill', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'black-rock-billing'),
            'filter_items_list'     => _x('Filter bills list', 'Screen reader text for the filter links', 'black-rock-billing'),
            'items_list_navigation' => _x('Bills list navigation', 'Screen reader text for the pagination', 'black-rock-billing'),
            'items_list'            => _x('Bills list', 'Screen reader text for the items list', 'black-rock-billing'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'bill'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 30,
            'menu_icon'          => 'dashicons-money-alt',
            'supports'           => array('title', 'editor'),
            'show_in_rest'       => false,
        );
        
        register_post_type('brb_bill', $args);
    }
    
    /**
     * Register taxonomies
     */
    public function register_taxonomies() {
        // Bill Status Taxonomy
        $status_labels = array(
            'name'              => _x('Bill Status', 'taxonomy general name', 'black-rock-billing'),
            'singular_name'     => _x('Bill Status', 'taxonomy singular name', 'black-rock-billing'),
            'search_items'      => __('Search Statuses', 'black-rock-billing'),
            'all_items'         => __('All Statuses', 'black-rock-billing'),
            'edit_item'         => __('Edit Status', 'black-rock-billing'),
            'update_item'       => __('Update Status', 'black-rock-billing'),
            'add_new_item'      => __('Add New Status', 'black-rock-billing'),
            'new_item_name'     => __('New Status Name', 'black-rock-billing'),
            'menu_name'         => __('Status', 'black-rock-billing'),
        );
        
        $status_args = array(
            'hierarchical'      => true,
            'labels'            => $status_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'bill-status'),
            'show_in_rest'      => false,
        );
        
        register_taxonomy('brb_bill_status', array('brb_bill'), $status_args);
        
        // Add default statuses
        $this->add_default_statuses();
    }
    
    /**
     * Add default bill statuses
     */
    private function add_default_statuses() {
        $default_statuses = array(
            'draft'     => 'Draft',
            'sent'      => 'Sent',
            'paid'      => 'Paid',
            'overdue'   => 'Overdue',
            'cancelled' => 'Cancelled',
        );
        
        foreach ($default_statuses as $slug => $name) {
            if (!term_exists($slug, 'brb_bill_status')) {
                wp_insert_term($name, 'brb_bill_status', array('slug' => $slug));
            }
        }
    }
    
    /**
     * Add custom columns to bills list
     */
    public function add_custom_columns($columns) {
        $new_columns = array();
        
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['brb_customer'] = __('Customer', 'black-rock-billing');
        $new_columns['brb_bill_date'] = __('Bill Date', 'black-rock-billing');
        $new_columns['brb_due_date'] = __('Due Date', 'black-rock-billing');
        $new_columns['brb_total'] = __('Total Amount', 'black-rock-billing');
        $new_columns['brb_paid'] = __('Paid', 'black-rock-billing');
        $new_columns['brb_pending'] = __('Pending', 'black-rock-billing');
        $new_columns['brb_status'] = __('Status', 'black-rock-billing');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Render custom column content
     */
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'brb_customer':
                $customer_id = get_post_meta($post_id, '_brb_customer_id', true);
                if ($customer_id) {
                    $customer = get_userdata($customer_id);
                    if ($customer) {
                        echo esc_html($customer->display_name . ' (' . $customer->user_email . ')');
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'brb_bill_date':
                $bill_date = get_post_meta($post_id, '_brb_bill_date', true);
                if ($bill_date) {
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($bill_date)));
                } else {
                    echo '—';
                }
                break;
                
            case 'brb_due_date':
                $due_date = get_post_meta($post_id, '_brb_due_date', true);
                if ($due_date) {
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($due_date)));
                } else {
                    echo '—';
                }
                break;
                
            case 'brb_total':
                $total = get_post_meta($post_id, '_brb_total_amount', true);
                echo $total ? brb_format_currency($total) : '—';
                break;
                
            case 'brb_paid':
                $paid = get_post_meta($post_id, '_brb_paid_amount', true);
                echo $paid ? brb_format_currency($paid) : brb_format_currency(0);
                break;
                
            case 'brb_pending':
                $total = floatval(get_post_meta($post_id, '_brb_total_amount', true));
                $paid = floatval(get_post_meta($post_id, '_brb_paid_amount', true));
                $pending = $total - $paid;
                $class = $pending > 0 ? 'brb-pending-amount' : 'brb-paid-full';
                echo '<span class="' . esc_attr($class) . '">' . brb_format_currency($pending) . '</span>';
                break;
                
            case 'brb_status':
                $status = get_post_meta($post_id, '_brb_status', true);
                if ($status) {
                    $status_class = sanitize_html_class($status);
                    echo '<span class="brb-status brb-status-' . esc_attr($status_class) . '">' . esc_html(ucfirst($status)) . '</span>';
                } else {
                    echo '—';
                }
                break;
        }
    }
    
    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['brb_bill_date'] = 'brb_bill_date';
        $columns['brb_due_date'] = 'brb_due_date';
        $columns['brb_total'] = 'brb_total';
        return $columns;
    }
    
    /**
     * Add customer filter dropdown
     */
    public function add_customer_filter() {
        global $typenow;
        
        if ($typenow === 'brb_bill') {
            $selected = isset($_GET['brb_customer']) ? intval($_GET['brb_customer']) : 0;
            
            // Get all customers who have bills
            global $wpdb;
            $customer_ids = $wpdb->get_col("
                SELECT DISTINCT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_brb_customer_id' 
                AND meta_value != ''
            ");
            
            if (!empty($customer_ids)) {
                $customers = get_users(array(
                    'include' => $customer_ids,
                    'orderby' => 'display_name',
                    'order' => 'ASC'
                ));
                
                echo '<select name="brb_customer">';
                echo '<option value="">' . __('All Customers', 'black-rock-billing') . '</option>';
                
                foreach ($customers as $customer) {
                    printf(
                        '<option value="%s"%s>%s</option>',
                        $customer->ID,
                        selected($selected, $customer->ID, false),
                        esc_html($customer->display_name . ' (' . $customer->user_email . ')')
                    );
                }
                
                echo '</select>';
            }
        }
    }
    
    /**
     * Filter bills by customer
     */
    public function filter_by_customer($query) {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'brb_bill' && isset($_GET['brb_customer']) && $_GET['brb_customer'] != '') {
            $customer_id = intval($_GET['brb_customer']);
            
            $query->query_vars['meta_key'] = '_brb_customer_id';
            $query->query_vars['meta_value'] = $customer_id;
        }
    }
}

// Initialize
new BRB_Post_Types();

