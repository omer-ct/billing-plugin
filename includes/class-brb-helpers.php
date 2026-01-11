<?php
/**
 * Helper Functions
 *
 * @package Black_Rock_Billing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format currency
 */
function brb_format_currency($amount) {
    $currency_symbol = get_option('brb_currency_symbol', 'AED');
    $currency_position = get_option('brb_currency_position', 'before');
    
    $formatted = number_format(floatval($amount), 2, '.', ',');
    
    if ($currency_position === 'before') {
        return $currency_symbol . ' ' . $formatted;
    } else {
        return $formatted . ' ' . $currency_symbol;
    }
}

/**
 * Get bill items
 */
function brb_get_bill_items($bill_id) {
    $items = get_post_meta($bill_id, '_brb_bill_items', true);
    return is_array($items) ? $items : array();
}

/**
 * Calculate bill total from items
 */
function brb_calculate_bill_total($bill_id) {
    $items = brb_get_bill_items($bill_id);
    $total = 0;
    
    foreach ($items as $item) {
        $quantity = floatval($item['quantity'] ?? 0);
        $rate = floatval($item['rate'] ?? 0);
        $total += $quantity * $rate;
    }
    
    return $total;
}

/**
 * Get bill total amount
 */
function brb_get_bill_total($bill_id) {
    $total = get_post_meta($bill_id, '_brb_total_amount', true);
    return $total ? floatval($total) : 0;
}

/**
 * Get paid amount
 */
function brb_get_paid_amount($bill_id) {
    $paid = get_post_meta($bill_id, '_brb_paid_amount', true);
    return $paid ? floatval($paid) : 0;
}

/**
 * Get return items
 */
function brb_get_return_items($bill_id) {
    $items = get_post_meta($bill_id, '_brb_return_items', true);
    return is_array($items) ? $items : array();
}

/**
 * Get return total
 */
function brb_get_return_total($bill_id) {
    $items = brb_get_return_items($bill_id);
    $total = 0;
    
    foreach ($items as $item) {
        $quantity = floatval($item['quantity'] ?? 0);
        $rate = floatval($item['rate'] ?? 0);
        $total += $quantity * $rate;
    }
    
    return $total;
}

/**
 * Get adjusted bill total (original - returns)
 */
function brb_get_adjusted_bill_total($bill_id) {
    $original_total = brb_get_bill_total($bill_id);
    $return_total = brb_get_return_total($bill_id);
    return max(0, $original_total - $return_total);
}

/**
 * Get refund due amount
 */
function brb_get_refund_due($bill_id) {
    $refund = get_post_meta($bill_id, '_brb_refund_due', true);
    return $refund ? floatval($refund) : 0;
}

/**
 * Get pending amount (always positive, returns 0 if refund due)
 */
function brb_get_pending_amount($bill_id) {
    $adjusted_total = brb_get_adjusted_bill_total($bill_id);
    $paid = brb_get_paid_amount($bill_id);
    $refund_due = brb_get_refund_due($bill_id);
    
    // If there's a refund due, pending is 0 (customer overpaid)
    if ($refund_due > 0) {
        return 0;
    }
    
    return max(0, $adjusted_total - $paid);
}

/**
 * Get net pending amount (can be negative if refund due)
 * Positive = customer owes money (green)
 * Negative = refund due to customer (red)
 */
function brb_get_net_pending_amount($bill_id) {
    $adjusted_total = brb_get_adjusted_bill_total($bill_id);
    $paid = brb_get_paid_amount($bill_id);
    
    // Return the difference (can be negative)
    return $adjusted_total - $paid;
}

/**
 * Get bill status
 */
function brb_get_bill_status($bill_id) {
    $status = get_post_meta($bill_id, '_brb_status', true);
    return $status ? $status : 'draft';
}

/**
 * Get customer bills
 */
function brb_get_customer_bills($customer_id, $args = array()) {
    $customer_id = intval($customer_id); // Ensure integer
    
    if (!$customer_id) {
        return array();
    }
    
    // Use direct database query as fallback to ensure we get all bills
    global $wpdb;
    
    // First, get all bill IDs for this customer using direct query
    $bill_ids = $wpdb->get_col($wpdb->prepare("
        SELECT post_id 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = '_brb_customer_id' 
        AND (meta_value = %d OR meta_value = %s)
    ", $customer_id, (string)$customer_id));
    
    if (empty($bill_ids)) {
        return array();
    }
    
    // Now get the posts with proper ordering
    $defaults = array(
        'post_type' => 'brb_bill',
        'post__in' => $bill_ids,
        'posts_per_page' => -1,
        'post_status' => 'any',
        'orderby' => 'post_date',
        'order' => 'DESC'
    );
    
    $args = wp_parse_args($args, $defaults);
    
    // Handle orderby for date properly
    if (isset($args['orderby']) && $args['orderby'] === 'date') {
        $args['orderby'] = 'post_date';
    }
    
    // Use WP_Query to get posts with proper ordering
    $query = new WP_Query($args);
    
    return $query->posts;
}

/**
 * Get customer total paid
 */
function brb_get_customer_total_paid($customer_id) {
    $bills = brb_get_customer_bills($customer_id);
    $total = 0;
    
    foreach ($bills as $bill) {
        $total += brb_get_paid_amount($bill->ID);
    }
    
    return $total;
}

/**
 * Get customer total pending (always positive, returns 0 if refund due)
 */
function brb_get_customer_total_pending($customer_id) {
    $bills = brb_get_customer_bills($customer_id);
    $total = 0;
    
    foreach ($bills as $bill) {
        $total += brb_get_pending_amount($bill->ID);
    }
    
    return $total;
}

/**
 * Get customer net pending (can be negative if refund due)
 * This is the sum of all net pending amounts from bills
 */
function brb_get_customer_net_pending($customer_id) {
    $bills = brb_get_customer_bills($customer_id);
    $total = 0;
    
    foreach ($bills as $bill) {
        $total += brb_get_net_pending_amount($bill->ID);
    }
    
    return $total;
}

/**
 * Get customer total billed (adjusted total after returns)
 */
function brb_get_customer_total_billed($customer_id) {
    $bills = brb_get_customer_bills($customer_id);
    $total = 0;
    
    foreach ($bills as $bill) {
        // Use adjusted total (after returns) instead of original total
        $total += brb_get_adjusted_bill_total($bill->ID);
    }
    
    return $total;
}

/**
 * Get customer total refund due
 */
function brb_get_customer_total_refund_due($customer_id) {
    $bills = brb_get_customer_bills($customer_id);
    $total = 0;
    
    foreach ($bills as $bill) {
        $total += brb_get_refund_due($bill->ID);
    }
    
    return $total;
}

/**
 * Generate bill number
 */
function brb_generate_bill_number($bill_id) {
    $bill_number = get_post_meta($bill_id, '_brb_bill_number', true);
    
    if (!$bill_number) {
        $year = date('Y');
        $prefix = get_option('brb_bill_prefix', 'BILL');
        $last_number = get_option('brb_last_bill_number_' . $year, 0);
        $last_number++;
        
        $bill_number = $prefix . '-' . $year . '-' . str_pad($last_number, 4, '0', STR_PAD_LEFT);
        
        update_post_meta($bill_id, '_brb_bill_number', $bill_number);
        update_option('brb_last_bill_number_' . $year, $last_number);
    }
    
    return $bill_number;
}

/**
 * Check if user can view bill
 */
function brb_can_user_view_bill($bill_id, $user_id = null) {
    // Admin can view all bills
    if (current_user_can('manage_options')) {
        return true;
    }
    
    // For regular users, they can view bills assigned to them (if we implement user-customer linking later)
    // For now, only admins can view bills
    return false;
}

/**
 * Get customer phone number
 */
function brb_get_customer_phone($customer_id) {
    $phone = get_user_meta($customer_id, 'billing_phone', true);
    if (empty($phone)) {
        $phone = get_user_meta($customer_id, 'phone', true);
    }
    return $phone;
}

/**
 * Format customer display name
 */
function brb_format_customer_name($name) {
    return ucwords(strtolower($name));
}

/**
 * Get customer data (WordPress user)
 */
function brb_get_customer($customer_id) {
    if (!$customer_id) {
        return null;
    }
    
    $user = get_userdata($customer_id);
    
    if (!$user) {
        return null;
    }
    
    return array(
        'ID' => $user->ID,
        'name' => $user->display_name,
        'email' => $user->user_email,
        'phone' => brb_get_customer_phone($customer_id),
        'display_name' => $user->display_name
    );
}

/**
 * Get all customers (WordPress users)
 */
function brb_get_all_customers($args = array()) {
    $defaults = array(
        'orderby' => 'display_name',
        'order' => 'ASC',
        'number' => -1
    );
    
    $args = wp_parse_args($args, $defaults);
    
    return get_users($args);
}

/**
 * Create or update customer (WordPress user)
 */
function brb_save_customer($customer_data) {
    $customer_id = isset($customer_data['ID']) ? intval($customer_data['ID']) : 0;
    $name = sanitize_text_field($customer_data['name'] ?? '');
    $email = sanitize_email($customer_data['email'] ?? '');
    $phone = sanitize_text_field($customer_data['phone'] ?? '');
    
    if (empty($name) || empty($email)) {
        return new WP_Error('missing_fields', __('Name and email are required.', 'black-rock-billing'));
    }
    
    if (!is_email($email)) {
        return new WP_Error('invalid_email', __('Invalid email address.', 'black-rock-billing'));
    }
    
    if ($customer_id) {
        // Update existing user
        $user_data = array(
            'ID' => $customer_id,
            'user_email' => $email,
            'display_name' => $name
        );
        
        // Check if email is already taken by another user
        $existing_user = get_user_by('email', $email);
        if ($existing_user && $existing_user->ID != $customer_id) {
            return new WP_Error('duplicate_email', __('A customer with this email already exists.', 'black-rock-billing'));
        }
        
        $user_id = wp_update_user($user_data);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
    } else {
        // Create new user
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
            'display_name' => $name,
            'role' => 'subscriber'
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
    }
    
    // Save phone number
    update_user_meta($user_id, 'billing_phone', $phone);
    
    return $user_id;
}

/**
 * Get product by name (for inventory lookup)
 */
function brb_get_product_by_name($product_name) {
    $args = array(
        'post_type' => 'brb_product',
        'post_status' => 'publish',
        'title' => $product_name,
        'posts_per_page' => 1
    );
    
    $products = get_posts($args);
    return !empty($products) ? $products[0] : null;
}

/**
 * Get product quantity available
 */
function brb_get_product_quantity($product_id) {
    $quantity = get_post_meta($product_id, '_brb_quantity_available', true);
    return $quantity !== '' ? floatval($quantity) : 0;
}

/**
 * Deduct product quantity
 */
function brb_deduct_product_quantity($product_id, $quantity_to_deduct, $invoice_id = 0, $action = 'sale') {
    $current_quantity = brb_get_product_quantity($product_id);
    $new_quantity = max(0, $current_quantity - $quantity_to_deduct);
    
    update_post_meta($product_id, '_brb_quantity_available', $new_quantity);
    
    // Track history
    $history = get_post_meta($product_id, '_brb_inventory_history', true);
    if (!is_array($history)) {
        $history = array();
    }
    
    $entry = array(
        'timestamp' => current_time('mysql'),
        'old_quantity' => $current_quantity,
        'new_quantity' => $new_quantity,
        'change' => -$quantity_to_deduct,
        'action' => $action,
        'user_id' => get_current_user_id(),
        'invoice_id' => $invoice_id,
        'quantity_sold' => $quantity_to_deduct
    );
    
    array_unshift($history, $entry);
    
    // Keep only last 100 entries
    if (count($history) > 100) {
        $history = array_slice($history, 0, 100);
    }
    
    update_post_meta($product_id, '_brb_inventory_history', $history);
    
    return $new_quantity;
}

/**
 * Restore product quantity (for returns/cancellations)
 */
function brb_restore_product_quantity($product_id, $quantity_to_restore, $invoice_id = 0, $action = 'return') {
    $current_quantity = brb_get_product_quantity($product_id);
    $new_quantity = $current_quantity + $quantity_to_restore;
    
    update_post_meta($product_id, '_brb_quantity_available', $new_quantity);
    
    // Track history
    $history = get_post_meta($product_id, '_brb_inventory_history', true);
    if (!is_array($history)) {
        $history = array();
    }
    
    $entry = array(
        'timestamp' => current_time('mysql'),
        'old_quantity' => $current_quantity,
        'new_quantity' => $new_quantity,
        'change' => $quantity_to_restore,
        'action' => $action,
        'user_id' => get_current_user_id(),
        'invoice_id' => $invoice_id,
        'quantity_sold' => 0
    );
    
    array_unshift($history, $entry);
    
    // Keep only last 100 entries
    if (count($history) > 100) {
        $history = array_slice($history, 0, 100);
    }
    
    update_post_meta($product_id, '_brb_inventory_history', $history);
    
    return $new_quantity;
}

/**
 * Get inventory history for a product
 */
function brb_get_inventory_history($product_id) {
    $history = get_post_meta($product_id, '_brb_inventory_history', true);
    return is_array($history) ? $history : array();
}

/**
 * Log inventory change
 */
function brb_log_inventory_change($product_id, $old_quantity, $new_quantity, $action = 'manual_update', $invoice_id = 0) {
    $history = get_post_meta($product_id, '_brb_inventory_history', true);
    if (!is_array($history)) {
        $history = array();
    }
    
    $entry = array(
        'timestamp' => current_time('mysql'),
        'old_quantity' => floatval($old_quantity),
        'new_quantity' => floatval($new_quantity),
        'change' => floatval($new_quantity) - floatval($old_quantity),
        'action' => $action,
        'user_id' => get_current_user_id(),
        'invoice_id' => $invoice_id,
        'quantity_sold' => 0
    );
    
    array_unshift($history, $entry);
    
    // Keep only last 100 entries
    if (count($history) > 100) {
        $history = array_slice($history, 0, 100);
    }
    
    update_post_meta($product_id, '_brb_inventory_history', $history);
}

/**
 * Get bills within a date range
 */
function brb_get_bills_by_date_range($start_date, $end_date) {
    $args = array(
        'post_type' => 'brb_bill',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_brb_bill_date',
                'value' => $start_date,
                'compare' => '>=',
                'type' => 'DATE'
            ),
            array(
                'key' => '_brb_bill_date',
                'value' => $end_date,
                'compare' => '<=',
                'type' => 'DATE'
            )
        ),
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_key' => '_brb_bill_date'
    );
    
    return get_posts($args);
}

/**
 * Calculate total sales for a date range
 */
function brb_calculate_total_sales($start_date, $end_date) {
    $bills = brb_get_bills_by_date_range($start_date, $end_date);
    $total_sales = 0;
    
    foreach ($bills as $bill) {
        $adjusted_total = brb_get_adjusted_bill_total($bill->ID);
        $total_sales += $adjusted_total;
    }
    
    return $total_sales;
}

/**
 * Calculate total profit for a date range (only for inventory products)
 */
function brb_calculate_total_profit($start_date, $end_date) {
    $bills = brb_get_bills_by_date_range($start_date, $end_date);
    $total_profit = 0;
    
    foreach ($bills as $bill) {
        $items = brb_get_bill_items($bill->ID);
        
        foreach ($items as $item) {
            if (isset($item['product_id']) && $item['product_id'] > 0) {
                $product_id = intval($item['product_id']);
                $quantity = floatval($item['quantity'] ?? 0);
                
                $purchased_rate = floatval(get_post_meta($product_id, '_brb_purchased_rate', true));
                $sale_rate = floatval($item['rate'] ?? 0);
                
                if ($purchased_rate > 0 && $sale_rate > 0) {
                    $profit_per_unit = $sale_rate - $purchased_rate;
                    $total_profit += $profit_per_unit * $quantity;
                }
            }
        }
        
        // Subtract returns
        $return_items = brb_get_return_items($bill->ID);
        foreach ($return_items as $return_item) {
            if (isset($return_item['product_id']) && $return_item['product_id'] > 0) {
                $product_id = intval($return_item['product_id']);
                $quantity = floatval($return_item['quantity'] ?? 0);
                
                $purchased_rate = floatval(get_post_meta($product_id, '_brb_purchased_rate', true));
                $sale_rate = floatval($return_item['rate'] ?? 0);
                
                if ($purchased_rate > 0 && $sale_rate > 0) {
                    $profit_per_unit = $sale_rate - $purchased_rate;
                    $total_profit -= $profit_per_unit * $quantity; // Subtract profit from returns
                }
            }
        }
    }
    
    return $total_profit;
}

/**
 * Calculate total credits (paid amounts) for a date range
 */
function brb_calculate_total_credits($start_date, $end_date) {
    $bills = brb_get_bills_by_date_range($start_date, $end_date);
    $total_credits = 0;
    
    foreach ($bills as $bill) {
        $paid_amount = brb_get_paid_amount($bill->ID);
        $total_credits += $paid_amount;
    }
    
    return $total_credits;
}

/**
 * Get number of invoices in a date range
 */
function brb_get_invoice_count($start_date, $end_date) {
    $bills = brb_get_bills_by_date_range($start_date, $end_date);
    return count($bills);
}
