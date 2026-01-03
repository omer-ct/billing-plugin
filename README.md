# Black Rock Billing Plugin

A comprehensive billing system for WordPress that allows you to create and manage customer bills, track payments, and provide customers with a dashboard to view their billing history.

## Features

- **Custom Post Type for Bills**: Create and manage bills as custom post types
- **Customer Management**: Link bills to WordPress users (customers)
- **Item Management**: Add multiple items to each bill with quantity and rate
- **Payment Tracking**: Track paid and pending amounts for each bill
- **Bill Status**: Manage bill status (Draft, Sent, Paid, Overdue, Cancelled)
- **Customer Dashboard**: Frontend dashboard where customers can view their bills
- **Auto Bill Numbering**: Automatic bill number generation (e.g., BILL-2026-0001)
- **Currency Settings**: Configurable currency symbol and position

## Installation

1. Upload the `black-rock-billing` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Bills > Settings to configure currency and bill prefix settings

## Usage

### Creating a Bill

1. Go to **Bills > Add New** in WordPress admin
2. Enter bill title and description (optional)
3. Select a customer from the dropdown
4. Set bill date and due date
5. Add bill items:
   - Click "Add Item" to add more items
   - Enter item description, quantity, and rate
   - Total is calculated automatically
6. Set payment information:
   - Enter paid amount (if any)
   - Pending amount is calculated automatically
7. Set bill status
8. Publish the bill

### Customer Dashboard

Customers can access their billing dashboard at:
- `/billing-dashboard` - View all bills and summary
- `/billing-dashboard/bill/{bill_id}` - View individual bill details

### Settings

Configure the plugin settings at **Bills > Settings**:
- Currency Symbol (default: $)
- Currency Position (before or after amount)
- Bill Number Prefix (default: BILL)

## File Structure

```
black-rock-billing/
├── admin/
│   └── class-brb-admin.php          # Admin settings page
├── assets/
│   ├── css/
│   │   ├── admin.css                 # Admin styles
│   │   └── frontend.css              # Frontend styles
│   └── js/
│       ├── admin.js                  # Admin JavaScript
│       └── frontend.js               # Frontend JavaScript
├── frontend/
│   └── class-brb-frontend.php        # Frontend dashboard
├── includes/
│   ├── class-brb-helpers.php         # Helper functions
│   ├── class-brb-meta-boxes.php      # Meta boxes for bills
│   └── class-brb-post-types.php      # Custom post type registration
└── black-rock-billing.php            # Main plugin file
```

## Customization

The plugin is designed to work with the Blocksy theme and follows WordPress coding standards. You can customize:

- **Styling**: Modify CSS files in `assets/css/`
- **Templates**: The frontend templates are in `frontend/class-brb-frontend.php`
- **Functionality**: Extend helper functions in `includes/class-brb-helpers.php`

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Blocksy theme (recommended, but works with any theme)

## Support

For support and feature requests, please contact the plugin developer.

## License

GPL v2 or later

