<?php
/**
 * Admin Class
 *
 * @package Black_Rock_Billing
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BRB_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Dashboard link (first item)
        add_submenu_page(
            'edit.php?post_type=brb_bill',
            __('Dashboard', 'black-rock-billing'),
            __('Dashboard', 'black-rock-billing'),
            'read',
            'brb-dashboard',
            array($this, 'redirect_to_dashboard'),
            1
        );
        
        // Customers link is handled by BRB_Customers class
        
        // Settings submenu
        add_submenu_page(
            'edit.php?post_type=brb_bill',
            __('Settings', 'black-rock-billing'),
            __('Settings', 'black-rock-billing'),
            'manage_options',
            'brb-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Redirect to frontend dashboard
     */
    public function redirect_to_dashboard() {
        wp_redirect(home_url('/billing-dashboard'));
        exit;
    }
    
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('brb_settings', 'brb_currency_symbol');
        register_setting('brb_settings', 'brb_currency_position');
        register_setting('brb_settings', 'brb_bill_prefix');
        
        add_settings_section(
            'brb_general_settings',
            __('General Settings', 'black-rock-billing'),
            array($this, 'render_general_settings_section'),
            'brb-settings'
        );
        
        add_settings_field(
            'brb_currency_symbol',
            __('Currency Symbol', 'black-rock-billing'),
            array($this, 'render_currency_symbol_field'),
            'brb-settings',
            'brb_general_settings'
        );
        
        add_settings_field(
            'brb_currency_position',
            __('Currency Position', 'black-rock-billing'),
            array($this, 'render_currency_position_field'),
            'brb-settings',
            'brb_general_settings'
        );
        
        add_settings_field(
            'brb_bill_prefix',
            __('Bill Number Prefix', 'black-rock-billing'),
            array($this, 'render_bill_prefix_field'),
            'brb-settings',
            'brb_general_settings'
        );
    }
    
    /**
     * Render general settings section
     */
    public function render_general_settings_section() {
        echo '<p>' . __('Configure general billing system settings.', 'black-rock-billing') . '</p>';
    }
    
    /**
     * Render currency symbol field
     */
    public function render_currency_symbol_field() {
        $value = get_option('brb_currency_symbol', 'AED');
        echo '<input type="text" name="brb_currency_symbol" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Symbol to use for currency display (e.g., AED, $, €, £)', 'black-rock-billing') . '</p>';
    }
    
    /**
     * Render currency position field
     */
    public function render_currency_position_field() {
        $value = get_option('brb_currency_position', 'before');
        ?>
        <select name="brb_currency_position">
            <option value="before" <?php selected($value, 'before'); ?>><?php _e('Before amount ($100)', 'black-rock-billing'); ?></option>
            <option value="after" <?php selected($value, 'after'); ?>><?php _e('After amount (100 $)', 'black-rock-billing'); ?></option>
        </select>
        <?php
    }
    
    /**
     * Render bill prefix field
     */
    public function render_bill_prefix_field() {
        $value = get_option('brb_bill_prefix', 'BILL');
        echo '<input type="text" name="brb_bill_prefix" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Prefix for auto-generated bill numbers (e.g., BILL-2026-0001)', 'black-rock-billing') . '</p>';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error('brb_messages', 'brb_message', __('Settings Saved', 'black-rock-billing'), 'updated');
        }
        
        settings_errors('brb_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('brb_settings');
                do_settings_sections('brb-settings');
                submit_button(__('Save Settings', 'black-rock-billing'));
                ?>
            </form>
        </div>
        <?php
    }
}

// Initialize
new BRB_Admin();

