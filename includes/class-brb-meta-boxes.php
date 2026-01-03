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
            $items = array();
            foreach ($_POST['brb_items'] as $item) {
                if (!empty($item['description'])) {
                    $items[] = array(
                        'description' => sanitize_text_field($item['description']),
                        'quantity' => floatval($item['quantity']),
                        'rate' => floatval($item['rate']),
                    );
                }
            }
            update_post_meta($post_id, '_brb_bill_items', $items);
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
}

// Initialize
new BRB_Meta_Boxes();

