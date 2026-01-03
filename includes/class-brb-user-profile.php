<?php
/**
 * User Profile Fields
 *
 * @package Black_Rock_Billing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BRB_User_Profile {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add phone number field to user profile
        add_action('show_user_profile', array($this, 'add_phone_field'));
        add_action('edit_user_profile', array($this, 'add_phone_field'));
        
        // Save phone number field
        add_action('personal_options_update', array($this, 'save_phone_field'));
        add_action('edit_user_profile_update', array($this, 'save_phone_field'));
    }
    
    /**
     * Add phone number field to user profile
     */
    public function add_phone_field($user) {
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if (empty($phone)) {
            $phone = get_user_meta($user->ID, 'phone', true);
        }
        ?>
        <h3><?php _e('Contact Information', 'black-rock-billing'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="billing_phone"><?php _e('Phone Number', 'black-rock-billing'); ?></label>
                </th>
                <td>
                    <input type="tel" 
                           name="billing_phone" 
                           id="billing_phone" 
                           value="<?php echo esc_attr($phone); ?>" 
                           class="regular-text" 
                           placeholder="<?php _e('Enter phone number', 'black-rock-billing'); ?>" />
                    <p class="description"><?php _e('Customer phone number for billing purposes.', 'black-rock-billing'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save phone number field
     */
    public function save_phone_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (isset($_POST['billing_phone'])) {
            update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['billing_phone']));
        }
        
        return true;
    }
}

// Initialize
new BRB_User_Profile();

