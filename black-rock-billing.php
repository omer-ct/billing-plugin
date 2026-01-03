<?php
/**
 * Plugin Name: Billing
 * Plugin URI: https://github.com/omer-ct/billing-plugin
 * Description: A comprehensive billing system for managing customer bills, payments, and invoices.
 * Version: 1.0.0
 * Author: Omer Muhammad
 * Author URI: https://www.linkedin.com/in/omer-muhammad-14b64929b/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: black-rock-billing
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BRB_VERSION', '1.0.0');
define('BRB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BRB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BRB_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class Black_Rock_Billing {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load plugin files
        $this->load_dependencies();
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once BRB_PLUGIN_DIR . 'includes/class-brb-post-types.php';
        require_once BRB_PLUGIN_DIR . 'includes/class-brb-helpers.php';
        require_once BRB_PLUGIN_DIR . 'includes/class-brb-meta-boxes.php';
        require_once BRB_PLUGIN_DIR . 'includes/class-brb-pdf.php';
        require_once BRB_PLUGIN_DIR . 'includes/class-brb-email.php';
        require_once BRB_PLUGIN_DIR . 'includes/class-brb-user-profile.php';
        
        if (is_admin()) {
            require_once BRB_PLUGIN_DIR . 'admin/class-brb-admin.php';
            require_once BRB_PLUGIN_DIR . 'admin/class-brb-customers.php';
        }
        
        require_once BRB_PLUGIN_DIR . 'frontend/class-brb-frontend.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(BRB_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(BRB_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Register post types first
        require_once BRB_PLUGIN_DIR . 'includes/class-brb-post-types.php';
        $post_types = new BRB_Post_Types();
        $post_types->register_post_types();
        $post_types->register_taxonomies();
        
        // Set default currency to AED if not already set
        if (get_option('brb_currency_symbol') === false) {
            update_option('brb_currency_symbol', 'AED');
        }
        if (get_option('brb_currency_position') === false) {
            update_option('brb_currency_position', 'before');
        }
        if (get_option('brb_bill_prefix') === false) {
            update_option('brb_bill_prefix', 'BILL');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('black-rock-billing', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'brb-frontend',
            BRB_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            BRB_VERSION
        );
        
        wp_enqueue_script(
            'brb-frontend',
            BRB_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            BRB_VERSION,
            true
        );
        
        wp_localize_script('brb-frontend', 'brbData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('brb_nonce'),
            'saveReturnsNonce' => wp_create_nonce('brb_save_returns'),
            'currencySymbol' => get_option('brb_currency_symbol', 'AED'),
            'currencyPosition' => get_option('brb_currency_position', 'before')
        ));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        // Only load on our custom post type pages
        if ($post_type === 'brb_bill' || $hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_style(
                'brb-admin',
                BRB_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                BRB_VERSION
            );
            
            wp_enqueue_script(
                'brb-admin',
                BRB_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'jquery-ui-sortable'),
                BRB_VERSION,
                true
            );
            
            wp_localize_script('brb-admin', 'brbAdminData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('brb_admin_nonce'),
                'currencySymbol' => get_option('brb_currency_symbol', 'AED'),
                'currencyPosition' => get_option('brb_currency_position', 'before')
            ));
        }
    }
}

/**
 * Initialize the plugin
 */
function brb_init() {
    return Black_Rock_Billing::get_instance();
}

// Start the plugin
brb_init();

