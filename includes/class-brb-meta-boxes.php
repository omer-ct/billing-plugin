<?php
/**
 * Meta Boxes
 *
 * @package Black_Rock_Billing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BRB_Meta_Boxes {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'), 10, 2);
        add_action('admin_footer', array($this, 'prefill_customer_from_url'));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'brb_bill_details',
            __('Bill Details', 'black-rock-billing'),
            array($this, 'render_bill_details_meta_box'),
            'brb_bill',
            'normal',
            'high'
        );
        
        add_meta_box(
            'brb_bill_items',
            __('Bill Items', 'black-rock-billing'),
            array($this, 'render_bill_items_meta_box'),
            'brb_bill',
            'normal',
            'high'
        );
        
        add_meta_box(
            'brb_bill_payments',
            __('Payment Information', 'black-rock-billing'),
            array($this, 'render_payment_meta_box'),
            'brb_bill',
            'side',
            'default'
        );
        
        add_meta_box(
            'brb_bill_returns',
            __('Return Items', 'black-rock-billing'),
            array($this, 'render_returns_meta_box'),
            'brb_bill',
            'normal',
            'default'
        );
        
        // Product meta boxes
        add_meta_box(
            'brb_product_details',
            __('Product Details', 'black-rock-billing'),
            array($this, 'render_product_details_meta_box'),
            'brb_product',
            'normal',
            'high'
        );
        
        add_meta_box(
            'brb_product_inventory_history',
            __('Inventory History', 'black-rock-billing'),
            array($this, 'render_product_inventory_history_meta_box'),
            'brb_product',
            'normal',
            'default'
        );
    }
    
    /**
     * Render bill details meta box
     */
    public function render_bill_details_meta_box($post) {
        wp_nonce_field('brb_save_bill_details', 'brb_bill_details_nonce');
        
        $customer_id = intval(get_post_meta($post->ID, '_brb_customer_id', true));
        $bill_date = get_post_meta($post->ID, '_brb_bill_date', true);
        $due_date = get_post_meta($post->ID, '_brb_due_date', true);
        $status = get_post_meta($post->ID, '_brb_status', true);
        $bill_number = get_post_meta($post->ID, '_brb_bill_number', true);
        
        if (!$bill_date) {
            $bill_date = date('Y-m-d');
        }
        
        if (empty($status)) {
            $status = 'draft';
        }
        
        // Get all customers for customer dropdown
        $customers = get_users(array('orderby' => 'display_name'));
        ?>
        <table class="form-table">
            <tr>
                <th><label for="brb_bill_number"><?php _e('Bill Number', 'black-rock-billing'); ?></label></th>
                <td>
                    <input type="text" id="brb_bill_number" name="brb_bill_number" value="<?php echo esc_attr($bill_number); ?>" class="regular-text" />
                    <p class="description"><?php _e('Leave empty to auto-generate', 'black-rock-billing'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="brb_customer_id"><?php _e('Customer', 'black-rock-billing'); ?> <span class="required">*</span></label></th>
                <td>
                    <select id="brb_customer_id" name="brb_customer_id" class="regular-text" required style="width: 100%; max-width: 400px; padding: 8px 12px; font-size: 14px;">
                        <option value=""><?php _e('Select Customer', 'black-rock-billing'); ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo esc_attr($customer->ID); ?>" <?php selected($customer_id, intval($customer->ID)); ?>>
                                <?php echo esc_html($customer->display_name . ' (' . $customer->user_email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="brb_bill_date"><?php _e('Bill Date', 'black-rock-billing'); ?></label></th>
                <td>
                    <input type="date" id="brb_bill_date" name="brb_bill_date" value="<?php echo esc_attr($bill_date); ?>" class="regular-text" style="width: 100%; max-width: 400px; padding: 8px 12px; font-size: 14px;" />
                </td>
            </tr>
            <tr>
                <th><label for="brb_due_date"><?php _e('Due Date', 'black-rock-billing'); ?></label></th>
                <td>
                    <input type="date" id="brb_due_date" name="brb_due_date" value="<?php echo esc_attr($due_date); ?>" class="regular-text" style="width: 100%; max-width: 400px; padding: 8px 12px; font-size: 14px;" />
                </td>
            </tr>
            <tr>
                <th><label for="brb_status"><?php _e('Status', 'black-rock-billing'); ?></label></th>
                <td>
                    <select id="brb_status" name="brb_status" class="regular-text" style="width: 100%; max-width: 400px; padding: 8px 12px; font-size: 14px;">
                        <option value="draft" <?php selected($status, 'draft'); ?>><?php _e('Draft', 'black-rock-billing'); ?></option>
                        <option value="sent" <?php selected($status, 'sent'); ?>><?php _e('Sent', 'black-rock-billing'); ?></option>
                        <option value="paid" <?php selected($status, 'paid'); ?>><?php _e('Paid', 'black-rock-billing'); ?></option>
                        <option value="overdue" <?php selected($status, 'overdue'); ?>><?php _e('Overdue', 'black-rock-billing'); ?></option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'black-rock-billing'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render bill items meta box
     */
    public function render_bill_items_meta_box($post) {
        $items = brb_get_bill_items($post->ID);
        $total = brb_get_bill_total($post->ID);
        ?>
        <div id="brb-bill-items-container">
            <table class="widefat" id="brb-items-table">
                <thead>
                    <tr>
                        <th style="width: 40%;"><?php _e('Item Description', 'black-rock-billing'); ?></th>
                        <th style="width: 15%;"><?php _e('Quantity', 'black-rock-billing'); ?></th>
                        <th style="width: 15%;"><?php _e('Rate', 'black-rock-billing'); ?></th>
                        <th style="width: 15%;"><?php _e('Total', 'black-rock-billing'); ?></th>
                        <th style="width: 15%;"><?php _e('Actions', 'black-rock-billing'); ?></th>
                    </tr>
                </thead>
                <tbody id="brb-items-tbody">
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $index => $item): ?>
                            <tr class="brb-item-row" data-index="<?php echo esc_attr($index); ?>">
                                <td>
                                    <input type="text" name="brb_items[<?php echo esc_attr($index); ?>][description]" 
                                           value="<?php echo esc_attr($item['description'] ?? ''); ?>" 
                                           class="regular-text brb-item-description" placeholder="<?php _e('Item description', 'black-rock-billing'); ?>" />
                                </td>
                                <td>
                                    <input type="number" name="brb_items[<?php echo esc_attr($index); ?>][quantity]" 
                                           value="<?php echo esc_attr($item['quantity'] ?? ''); ?>" 
                                           class="small-text brb-item-quantity" step="0.01" min="0" />
                                </td>
                                <td>
                                    <input type="number" name="brb_items[<?php echo esc_attr($index); ?>][rate]" 
                                           value="<?php echo esc_attr($item['rate'] ?? ''); ?>" 
                                           class="small-text brb-item-rate" step="0.01" min="0" />
                                </td>
                                <td>
                                    <span class="brb-item-total"><?php 
                                        $qty = floatval($item['quantity'] ?? 0);
                                        $rate = floatval($item['rate'] ?? 0);
                                        echo brb_format_currency($qty * $rate);
                                    ?></span>
                                </td>
                                <td>
                                    <button type="button" class="brb-icon-btn brb-icon-btn-remove brb-remove-item" title="<?php _e('Remove Item', 'black-rock-billing'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="brb-item-row" data-index="0">
                            <td>
                                <input type="text" name="brb_items[0][description]" 
                                       class="regular-text brb-item-description" placeholder="<?php _e('Item description', 'black-rock-billing'); ?>" />
                            </td>
                            <td>
                                <input type="number" name="brb_items[0][quantity]" 
                                       class="small-text brb-item-quantity" step="0.01" min="0" value="1" />
                            </td>
                            <td>
                                <input type="number" name="brb_items[0][rate]" 
                                       class="small-text brb-item-rate" step="0.01" min="0" />
                            </td>
                            <td>
                                <span class="brb-item-total"><?php echo brb_format_currency(0); ?></span>
                            </td>
                            <td>
                                <button type="button" class="button brb-remove-item"><?php _e('Remove', 'black-rock-billing'); ?></button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;"><strong><?php _e('Total Amount:', 'black-rock-billing'); ?></strong></td>
                        <td><strong id="brb-grand-total"><?php echo brb_format_currency($total); ?></strong></td>
                        <td>
                            <button type="button" class="brb-icon-btn brb-icon-btn-add brb-add-item" title="<?php _e('Add Item', 'black-rock-billing'); ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                            </button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <input type="hidden" id="brb-total-amount" name="brb_total_amount" value="<?php echo esc_attr($total); ?>" />
        <?php
    }
    
    /**
     * Render payment meta box
     */
    public function render_payment_meta_box($post) {
        $original_total = brb_get_bill_total($post->ID);
        $return_total = brb_get_return_total($post->ID);
        $adjusted_total = $original_total - $return_total;
        $paid = brb_get_paid_amount($post->ID);
        $pending = brb_get_pending_amount($post->ID);
        $refund_due = brb_get_refund_due($post->ID);
        ?>
        <div class="brb-payment-info">
            <p>
                <strong><?php _e('Original Amount:', 'black-rock-billing'); ?></strong><br>
                <span><?php echo brb_format_currency($original_total); ?></span>
            </p>
            <?php if ($return_total > 0): ?>
            <p>
                <strong><?php _e('Return Amount:', 'black-rock-billing'); ?></strong><br>
                <span style="color: #ef4444;">-<?php echo brb_format_currency($return_total); ?></span>
            </p>
            <p>
                <strong><?php _e('Adjusted Total:', 'black-rock-billing'); ?></strong><br>
                <span id="brb-payment-total" style="font-weight: 700;"><?php echo brb_format_currency($adjusted_total); ?></span>
            </p>
            <?php else: ?>
            <p>
                <strong><?php _e('Total Amount:', 'black-rock-billing'); ?></strong><br>
                <span id="brb-payment-total"><?php echo brb_format_currency($adjusted_total); ?></span>
            </p>
            <?php endif; ?>
            <p>
                <label for="brb_paid_amount"><strong><?php _e('Paid Amount:', 'black-rock-billing'); ?></strong></label><br>
                <input type="number" id="brb_paid_amount" name="brb_paid_amount" 
                       value="<?php echo esc_attr($paid); ?>" 
                       class="regular-text" step="0.01" min="0" />
                <p class="description"><?php _e('Can exceed adjusted total if customer overpaid (will show refund due)', 'black-rock-billing'); ?></p>
            </p>
            <?php if ($refund_due > 0): ?>
            <p>
                <strong><?php _e('Refund Due to Customer:', 'black-rock-billing'); ?></strong><br>
                <span id="brb-payment-refund" style="color: #dc2626; font-weight: 700; font-size: 1.2em;">
                    <?php echo brb_format_currency($refund_due); ?>
                </span>
            </p>
            <?php else: ?>
            <p>
                <strong><?php _e('Pending Amount:', 'black-rock-billing'); ?></strong><br>
                <span id="brb-payment-pending" class="<?php echo $pending > 0 ? 'brb-pending-amount' : 'brb-paid-full'; ?>">
                    <?php echo brb_format_currency($pending); ?>
                </span>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render returns meta box
     */
    public function render_returns_meta_box($post) {
        $return_items = brb_get_return_items($post->ID);
        $return_total = brb_get_return_total($post->ID);
        ?>
        <div id="brb-return-items-container">
            <p class="description"><?php _e('Add items that have been returned. The return amount will be deducted from the bill total.', 'black-rock-billing'); ?></p>
            <table class="widefat" id="brb-returns-table">
                <thead>
                    <tr>
                        <th style="width: 40%;"><?php _e('Item Description', 'black-rock-billing'); ?></th>
                        <th style="width: 15%;"><?php _e('Quantity', 'black-rock-billing'); ?></th>
                        <th style="width: 15%;"><?php _e('Rate', 'black-rock-billing'); ?></th>
                        <th style="width: 15%;"><?php _e('Total', 'black-rock-billing'); ?></th>
                        <th style="width: 15%;"><?php _e('Actions', 'black-rock-billing'); ?></th>
                    </tr>
                </thead>
                <tbody id="brb-returns-tbody">
                    <?php if (!empty($return_items)): ?>
                        <?php foreach ($return_items as $index => $item): ?>
                            <tr class="brb-return-row" data-index="<?php echo esc_attr($index); ?>">
                                <td>
                                    <input type="text" name="brb_return_items[<?php echo esc_attr($index); ?>][description]" 
                                           value="<?php echo esc_attr($item['description'] ?? ''); ?>" 
                                           class="regular-text brb-return-description" placeholder="<?php _e('Return item description', 'black-rock-billing'); ?>" />
                                </td>
                                <td>
                                    <input type="number" name="brb_return_items[<?php echo esc_attr($index); ?>][quantity]" 
                                           value="<?php echo esc_attr($item['quantity'] ?? ''); ?>" 
                                           class="small-text brb-return-quantity" step="0.01" min="0" />
                                </td>
                                <td>
                                    <input type="number" name="brb_return_items[<?php echo esc_attr($index); ?>][rate]" 
                                           value="<?php echo esc_attr($item['rate'] ?? ''); ?>" 
                                           class="small-text brb-return-rate" step="0.01" min="0" />
                                </td>
                                <td>
                                    <span class="brb-return-total"><?php 
                                        $qty = floatval($item['quantity'] ?? 0);
                                        $rate = floatval($item['rate'] ?? 0);
                                        echo brb_format_currency($qty * $rate);
                                    ?></span>
                                </td>
                                <td>
                                    <button type="button" class="brb-icon-btn brb-icon-btn-remove brb-remove-return" title="<?php _e('Remove Return Item', 'black-rock-billing'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;"><strong><?php _e('Return Total:', 'black-rock-billing'); ?></strong></td>
                        <td><strong id="brb-return-grand-total"><?php echo brb_format_currency($return_total); ?></strong></td>
                        <td>
                            <button type="button" class="brb-icon-btn brb-icon-btn-add brb-add-return" title="<?php _e('Add Return Item', 'black-rock-billing'); ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                            </button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id, $post) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check post type
        if ($post->post_type === 'brb_product') {
            $this->save_product_meta($post_id, $post);
            return;
        }
        
        if ($post->post_type !== 'brb_bill') {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['brb_bill_details_nonce']) || !wp_verify_nonce($_POST['brb_bill_details_nonce'], 'brb_save_bill_details')) {
            return;
        }
        
        // Save customer ID
        if (isset($_POST['brb_customer_id'])) {
            update_post_meta($post_id, '_brb_customer_id', intval($_POST['brb_customer_id']));
        }
        
        // Save bill date
        if (isset($_POST['brb_bill_date'])) {
            update_post_meta($post_id, '_brb_bill_date', sanitize_text_field($_POST['brb_bill_date']));
        }
        
        // Save due date
        if (isset($_POST['brb_due_date'])) {
            update_post_meta($post_id, '_brb_due_date', sanitize_text_field($_POST['brb_due_date']));
        }
        
        // Save status
        if (isset($_POST['brb_status'])) {
            update_post_meta($post_id, '_brb_status', sanitize_text_field($_POST['brb_status']));
        }
        
        // Save bill number
        if (isset($_POST['brb_bill_number'])) {
            $bill_number = sanitize_text_field($_POST['brb_bill_number']);
            if (empty($bill_number)) {
                // Auto-generate if empty
                brb_generate_bill_number($post_id);
            } else {
                update_post_meta($post_id, '_brb_bill_number', $bill_number);
            }
        } else {
            // Auto-generate if not set
            brb_generate_bill_number($post_id);
        }
        
        // Save bill items
        if (isset($_POST['brb_items']) && is_array($_POST['brb_items'])) {
            $old_items = brb_get_bill_items($post_id);
            
            // Restore inventory from old items
            foreach ($old_items as $old_item) {
                if (!empty($old_item['product_id']) && $old_item['product_id'] > 0 && $old_item['quantity'] > 0) {
                    brb_restore_product_quantity($old_item['product_id'], $old_item['quantity'], $post_id, 'invoice_edit');
                }
            }
            
            $items = array();
            foreach ($_POST['brb_items'] as $item) {
                if (!empty($item['description'])) {
                    $product_id = isset($item['product_id']) ? intval($item['product_id']) : 0;
                    $items[] = array(
                        'description' => sanitize_text_field($item['description']),
                        'quantity' => floatval($item['quantity']),
                        'rate' => floatval($item['rate']),
                        'product_id' => $product_id,
                    );
                }
            }
            update_post_meta($post_id, '_brb_bill_items', $items);
            
            // Deduct inventory for new items
            foreach ($items as $item) {
                if (!empty($item['product_id']) && $item['product_id'] > 0 && $item['quantity'] > 0) {
                    brb_deduct_product_quantity($item['product_id'], $item['quantity'], $post_id, 'sale');
                }
            }
        }
        
        // Calculate and save total
        $total = 0;
        if (isset($_POST['brb_items']) && is_array($_POST['brb_items'])) {
            foreach ($_POST['brb_items'] as $item) {
                $quantity = floatval($item['quantity'] ?? 0);
                $rate = floatval($item['rate'] ?? 0);
                $total += $quantity * $rate;
            }
        }
        
        // Use calculated total or provided total
        if (isset($_POST['brb_total_amount'])) {
            $total = floatval($_POST['brb_total_amount']);
        }
        
        update_post_meta($post_id, '_brb_total_amount', $total);
        
        // Save return items
        if (isset($_POST['brb_return_items']) && is_array($_POST['brb_return_items'])) {
            $return_items = array();
            foreach ($_POST['brb_return_items'] as $item) {
                if (!empty($item['description'])) {
                    $return_items[] = array(
                        'description' => sanitize_text_field($item['description']),
                        'quantity' => floatval($item['quantity']),
                        'rate' => floatval($item['rate']),
                    );
                }
            }
            update_post_meta($post_id, '_brb_return_items', $return_items);
        } else {
            // Clear return items if not set
            update_post_meta($post_id, '_brb_return_items', array());
        }
        
        // Save paid amount and calculate refund due
        $old_paid = brb_get_paid_amount($post_id);
        $return_total = brb_get_return_total($post_id);
        $adjusted_total = max(0, $total - $return_total);
        
        if (isset($_POST['brb_paid_amount'])) {
            $paid = floatval($_POST['brb_paid_amount']);
            update_post_meta($post_id, '_brb_paid_amount', $paid);
            
            // Calculate refund due if paid exceeds adjusted total
            $refund_due = 0;
            if ($paid > $adjusted_total) {
                $refund_due = $paid - $adjusted_total;
            }
            update_post_meta($post_id, '_brb_refund_due', $refund_due);
            
            // Send email if bill is fully paid
            if ($paid >= $adjusted_total && $old_paid < $adjusted_total) {
                BRB_Email::send_bill_notification($post_id, 'paid');
            }
        } else {
            // Even if paid amount isn't being updated, recalculate refund due based on current paid amount
            // This ensures refund_due is correct when returns are added/removed
            $paid = brb_get_paid_amount($post_id);
            $refund_due = 0;
            if ($paid > $adjusted_total) {
                $refund_due = $paid - $adjusted_total;
            }
            update_post_meta($post_id, '_brb_refund_due', $refund_due);
        }
        
        // Send email notifications based on status
        $old_status = get_post_meta($post_id, '_brb_status', true);
        if (isset($_POST['brb_status']) && $_POST['brb_status'] !== $old_status) {
            $new_status = sanitize_text_field($_POST['brb_status']);
            
            if ($new_status === 'sent' && $old_status !== 'sent') {
                BRB_Email::send_bill_notification($post_id, 'sent');
            } elseif ($new_status === 'overdue' && $old_status !== 'overdue') {
                BRB_Email::send_bill_notification($post_id, 'overdue');
            } elseif ($new_status === 'paid' && $old_status !== 'paid') {
                BRB_Email::send_bill_notification($post_id, 'paid');
            }
        }
        
        // Send email on first creation
        if (get_post_meta($post_id, '_brb_email_sent', true) !== 'yes' && $status !== 'draft') {
            BRB_Email::send_bill_notification($post_id, 'created');
            update_post_meta($post_id, '_brb_email_sent', 'yes');
        }
    }
    
    /**
     * Prefill customer from URL parameter
     */
    public function prefill_customer_from_url() {
        global $post_type;
        
        if ($post_type === 'brb_bill' && isset($_GET['brb_customer'])) {
            $customer_id = intval($_GET['brb_customer']);
            ?>
            <script>
            jQuery(document).ready(function($) {
                var customerId = <?php echo $customer_id; ?>;
                if (customerId && $('#brb_customer_id').length) {
                    $('#brb_customer_id').val(customerId).trigger('change');
                }
            });
            </script>
            <?php
        }
    }
    
    /**
     * Render product details meta box
     */
    public function render_product_details_meta_box($post) {
        wp_nonce_field('brb_save_product_details', 'brb_product_details_nonce');
        
        $purchased_from = get_post_meta($post->ID, '_brb_purchased_from', true);
        $purchased_rate = get_post_meta($post->ID, '_brb_purchased_rate', true);
        $sale_rate = get_post_meta($post->ID, '_brb_sale_rate', true);
        $quantity_available = get_post_meta($post->ID, '_brb_quantity_available', true);
        if ($quantity_available === '') {
            $quantity_available = 0;
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="brb_purchased_from"><?php _e('Purchased From', 'black-rock-billing'); ?></label></th>
                <td>
                    <input type="text" id="brb_purchased_from" name="brb_purchased_from" value="<?php echo esc_attr($purchased_from); ?>" class="regular-text" />
                    <p class="description"><?php _e('Supplier or vendor name', 'black-rock-billing'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="brb_purchased_rate"><?php _e('Purchased Rate', 'black-rock-billing'); ?></label></th>
                <td>
                    <input type="number" id="brb_purchased_rate" name="brb_purchased_rate" value="<?php echo esc_attr($purchased_rate); ?>" class="regular-text" step="0.01" min="0" />
                    <p class="description"><?php _e('Cost price per unit', 'black-rock-billing'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="brb_sale_rate"><?php _e('Sale Rate', 'black-rock-billing'); ?></label></th>
                <td>
                    <input type="number" id="brb_sale_rate" name="brb_sale_rate" value="<?php echo esc_attr($sale_rate); ?>" class="regular-text" step="0.01" min="0" />
                    <p class="description"><?php _e('Selling price per unit (will auto-fill when adding to invoice)', 'black-rock-billing'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php _e('Profit per Unit', 'black-rock-billing'); ?></th>
                <td>
                    <?php 
                    $profit = 0;
                    if ($purchased_rate > 0 && $sale_rate > 0) {
                        $profit = $sale_rate - $purchased_rate;
                    }
                    ?>
                    <p id="brb_profit_display" style="font-size: 1.2em; font-weight: bold; color: <?php echo $profit >= 0 ? '#10b981' : '#dc2626'; ?>; margin: 0;">
                        <?php echo brb_format_currency($profit); ?>
                    </p>
                    <p class="description"><?php _e('Calculated automatically: Sale Rate - Purchased Rate', 'black-rock-billing'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="brb_quantity_available"><?php _e('Quantity Available', 'black-rock-billing'); ?></label></th>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="number" id="brb_quantity_available" name="brb_quantity_available" value="<?php echo esc_attr($quantity_available); ?>" class="regular-text" step="0.01" min="0" style="flex: 1; max-width: 200px;" />
                        <span style="font-weight: bold; color: #2563eb; font-size: 1.1em;"><?php echo number_format($quantity_available, 2); ?></span>
                    </div>
                    <p class="description"><?php _e('Current stock quantity. This will be automatically deducted when items are sold.', 'black-rock-billing'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="brb_add_stock_quantity"><?php _e('Quick Add Stock', 'black-rock-billing'); ?></label></th>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="number" id="brb_add_stock_quantity_visible" value="" class="regular-text" step="0.01" min="0" placeholder="<?php _e('Enter quantity to add', 'black-rock-billing'); ?>" style="flex: 1; max-width: 200px;" />
                        <button type="button" id="brb-quick-add-stock-btn" class="button button-primary" style="white-space: nowrap;">
                            <?php _e('Add to Stock', 'black-rock-billing'); ?>
                        </button>
                    </div>
                    <p class="description"><?php _e('Enter the quantity you want to ADD to current stock (e.g., 2000 for a new container). This will update the total quantity and record it in history.', 'black-rock-billing'); ?></p>
                </td>
            </tr>
        </table>
        <input type="hidden" id="brb_add_stock_quantity" name="brb_add_stock_quantity" value="" />
        <script>
        jQuery(document).ready(function($) {
            // Calculate and update profit display
            function updateProfit() {
                var purchasedRate = parseFloat($('#brb_purchased_rate').val()) || 0;
                var saleRate = parseFloat($('#brb_sale_rate').val()) || 0;
                var profit = saleRate - purchasedRate;
                var profitColor = profit >= 0 ? '#10b981' : '#dc2626';
                
                // Format profit with currency (simple format)
                var currencySymbol = '<?php echo get_option('brb_currency_symbol', 'AED'); ?>';
                var profitText = currencySymbol + ' ' + profit.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                
                // Update profit display if it exists
                var $profitDisplay = $('#brb_profit_display');
                if ($profitDisplay.length) {
                    $profitDisplay.text(profitText).css('color', profitColor);
                }
            }
            
            // Update profit when rates change
            $('#brb_purchased_rate, #brb_sale_rate').on('input', updateProfit);
            
            // Initial calculation
            updateProfit();
            
            $('#brb-quick-add-stock-btn').on('click', function(e) {
                e.preventDefault();
                var addQuantity = parseFloat($('#brb_add_stock_quantity').val()) || 0;
                var currentQuantity = parseFloat($('#brb_quantity_available').val()) || 0;
                
                // Get value from the visible input
                var visibleInput = $('#brb_add_stock_quantity_visible');
                addQuantity = parseFloat(visibleInput.val()) || 0;
                
                if (addQuantity <= 0) {
                    alert('<?php _e('Please enter a valid quantity to add.', 'black-rock-billing'); ?>');
                    return;
                }
                
                var newQuantity = currentQuantity + addQuantity;
                $('#brb_quantity_available').val(newQuantity);
                
                // Set hidden field to track this was a stock purchase
                $('#brb_add_stock_quantity').val(addQuantity);
                
                // Clear visible input
                visibleInput.val('');
                
                // Show confirmation
                var message = '<?php _e('Stock will be updated to', 'black-rock-billing'); ?> ' + newQuantity.toFixed(2) + ' <?php _e('when you save the product. This will be recorded as a Stock Purchase in history.', 'black-rock-billing'); ?>';
                alert(message);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render product inventory history meta box
     */
    public function render_product_inventory_history_meta_box($post) {
        $history = brb_get_inventory_history($post->ID);
        ?>
        <div style="max-height: 500px; overflow-y: auto;">
            <?php if (!empty($history)): ?>
                <table class="widefat" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th style="padding: 10px;"><?php _e('Date/Time', 'black-rock-billing'); ?></th>
                            <th style="padding: 10px;"><?php _e('Action', 'black-rock-billing'); ?></th>
                            <th style="padding: 10px; text-align: right;"><?php _e('Old Qty', 'black-rock-billing'); ?></th>
                            <th style="padding: 10px; text-align: right;"><?php _e('Change', 'black-rock-billing'); ?></th>
                            <th style="padding: 10px; text-align: right;"><?php _e('New Qty', 'black-rock-billing'); ?></th>
                            <th style="padding: 10px;"><?php _e('Invoice', 'black-rock-billing'); ?></th>
                            <th style="padding: 10px;"><?php _e('User', 'black-rock-billing'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry): ?>
                            <?php
                            $change_class = $entry['change'] > 0 ? 'color: #10b981; font-weight: bold;' : 'color: #dc2626; font-weight: bold;';
                            $change_sign = $entry['change'] > 0 ? '+' : '';
                            
                            $action_text = '';
                            switch ($entry['action']) {
                                case 'sale':
                                    $action_text = __('Sale', 'black-rock-billing');
                                    break;
                                case 'return':
                                    $action_text = __('Return', 'black-rock-billing');
                                    break;
                                case 'manual_update':
                                    $action_text = __('Manual Update', 'black-rock-billing');
                                    break;
                                case 'stock_purchase':
                                    $action_text = __('Stock Purchase', 'black-rock-billing');
                                    break;
                                case 'invoice_edit':
                                    $action_text = __('Invoice Edit', 'black-rock-billing');
                                    break;
                                case 'return_removed':
                                    $action_text = __('Return Removed', 'black-rock-billing');
                                    break;
                                default:
                                    $action_text = ucfirst($entry['action']);
                            }
                            
                            $invoice_link = '—';
                            if (!empty($entry['invoice_id']) && $entry['invoice_id'] > 0) {
                                $invoice_url = admin_url('post.php?post=' . $entry['invoice_id'] . '&action=edit');
                                $invoice_link = '<a href="' . esc_url($invoice_url) . '">#' . $entry['invoice_id'] . '</a>';
                            }
                            
                            $user_name = '—';
                            if (!empty($entry['user_id']) && $entry['user_id'] > 0) {
                                $user = get_userdata($entry['user_id']);
                                if ($user) {
                                    $user_name = $user->display_name;
                                }
                            }
                            ?>
                            <tr>
                                <td style="padding: 10px;"><?php echo esc_html($entry['timestamp']); ?></td>
                                <td style="padding: 10px;"><?php echo esc_html($action_text); ?></td>
                                <td style="padding: 10px; text-align: right;"><?php echo number_format($entry['old_quantity'], 2); ?></td>
                                <td style="padding: 10px; text-align: right; <?php echo $change_class; ?>">
                                    <?php echo $change_sign . number_format($entry['change'], 2); ?>
                                </td>
                                <td style="padding: 10px; text-align: right; font-weight: bold;"><?php echo number_format($entry['new_quantity'], 2); ?></td>
                                <td style="padding: 10px;"><?php echo $invoice_link; ?></td>
                                <td style="padding: 10px;"><?php echo esc_html($user_name); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 20px; text-align: center; color: #64748b;">
                    <?php _e('No inventory history available yet.', 'black-rock-billing'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Save product meta
     */
    private function save_product_meta($post_id, $post) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['brb_product_details_nonce']) || !wp_verify_nonce($_POST['brb_product_details_nonce'], 'brb_save_product_details')) {
            return;
        }
        
        // Save purchased from
        if (isset($_POST['brb_purchased_from'])) {
            update_post_meta($post_id, '_brb_purchased_from', sanitize_text_field($_POST['brb_purchased_from']));
        }
        
        // Save purchased rate
        if (isset($_POST['brb_purchased_rate'])) {
            update_post_meta($post_id, '_brb_purchased_rate', floatval($_POST['brb_purchased_rate']));
        }
        
        // Save sale rate
        if (isset($_POST['brb_sale_rate'])) {
            update_post_meta($post_id, '_brb_sale_rate', floatval($_POST['brb_sale_rate']));
        }
        
        // Save quantity available (track history if changed)
        if (isset($_POST['brb_quantity_available'])) {
            $old_quantity = floatval(get_post_meta($post_id, '_brb_quantity_available', true));
            $new_quantity = floatval($_POST['brb_quantity_available']);
            
            // Check if this was a quick add stock (quantity increased)
            $add_quantity = isset($_POST['brb_add_stock_quantity']) ? floatval($_POST['brb_add_stock_quantity']) : 0;
            
            if ($old_quantity != $new_quantity) {
                // Determine action type
                $action = 'manual_update';
                if ($add_quantity > 0 && $new_quantity > $old_quantity && abs($new_quantity - ($old_quantity + $add_quantity)) < 0.01) {
                    // This was a stock purchase/add
                    $action = 'stock_purchase';
                }
                
                // Track history
                $this->add_inventory_history($post_id, $old_quantity, $new_quantity, $action, get_current_user_id());
            }
            
            update_post_meta($post_id, '_brb_quantity_available', $new_quantity);
        }
    }
    
    /**
     * Add inventory history entry
     */
    private function add_inventory_history($product_id, $old_quantity, $new_quantity, $action = 'update', $user_id = 0, $invoice_id = 0, $quantity_sold = 0) {
        $history = get_post_meta($product_id, '_brb_inventory_history', true);
        if (!is_array($history)) {
            $history = array();
        }
        
        $entry = array(
            'timestamp' => current_time('mysql'),
            'old_quantity' => $old_quantity,
            'new_quantity' => $new_quantity,
            'change' => $new_quantity - $old_quantity,
            'action' => $action,
            'user_id' => $user_id,
            'invoice_id' => $invoice_id,
            'quantity_sold' => $quantity_sold
        );
        
        array_unshift($history, $entry);
        
        // Keep only last 100 entries
        if (count($history) > 100) {
            $history = array_slice($history, 0, 100);
        }
        
        update_post_meta($product_id, '_brb_inventory_history', $history);
    }
}

// Initialize
new BRB_Meta_Boxes();

