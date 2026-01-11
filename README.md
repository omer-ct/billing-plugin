# Black Rock Billing Plugin

A comprehensive billing and invoicing system for WordPress that allows you to create and manage invoices, track payments, manage inventory, and provide customers with a modern dashboard to view their billing history.

**Repository**: [https://github.com/omer-ct/billing-plugin](https://github.com/omer-ct/billing-plugin)

## Features

### Core Features

- **Custom Post Type for Invoices**: Create and manage invoices as custom post types
- **Customer Management**: Full customer management system with frontend interface
- **Item Management**: Add multiple items to each invoice with quantity and rate
- **Payment Tracking**: Track paid and pending amounts for each invoice
- **Return Items**: Handle product returns and refunds
- **Invoice Status**: Manage invoice status (Draft, Sent, Paid, Overdue, Cancelled)
- **Auto Invoice Numbering**: Automatic invoice number generation (e.g., BILL-2026-0001)

### Frontend Dashboard

- **Customer Dashboard**: Modern frontend dashboard where customers can view their invoices
- **Invoice Management**: Create and edit invoices from the frontend
- **Customer Management**: Add and edit customers from the frontend
- **Inventory Management**: Complete inventory system with product management
- **Reports & Analytics**: Comprehensive reporting with date range filters (Weekly, Monthly, Yearly, All Time, Custom)
- **Import Invoices**: Bulk import invoices from CSV files

### Inventory System

- **Product Management**: Add, edit, and manage products
- **Stock Tracking**: Track inventory quantities
- **Purchase & Sale Rates**: Set purchase and sale prices for products
- **Inventory History**: View complete history of inventory changes
- **Automatic Deduction**: Inventory automatically updates when items are sold

### Reports & Analytics

- **Sales Reports**: Track total sales by date range
- **Profit Reports**: Calculate and display profits
- **Credit Reports**: Track credits and refunds
- **Date Range Filters**: 
  - Weekly
  - Monthly
  - Yearly
  - All Time
  - Custom date range
- **Visual Summary Cards**: Beautiful summary cards with icons and statistics

### Settings

- **Currency Settings**: 
  - Currency Symbol (e.g., AED, $, €, £)
  - Currency Position (before or after amount)
- **Invoice Settings**:
  - Invoice Number Prefix (customizable)
- **Company Information**:
  - Company Name
  - Email
  - Phone
  - Address
  (Displayed on invoices and PDFs)

## Installation

1. Upload the `black-rock-billing` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Billing Dashboard > Settings** to configure currency, invoice prefix, and company information

## Usage

### Frontend Dashboard

Access the frontend dashboard at `/billing-dashboard` (requires login).

**Available Pages:**
- **Dashboard** (`/billing-dashboard`) - Overview with summary statistics
- **Customers** (`/billing-dashboard/customers`) - View and manage customers
- **Add Customer** (`/billing-dashboard/customers/add`) - Create new customer
- **Edit Customer** (`/billing-dashboard/customers/edit/{id}`) - Edit customer information
- **Customer Detail** (`/billing-dashboard/customers/{id}`) - View customer details and invoices
- **Create Invoice** (`/billing-dashboard/create`) - Create new invoice
- **Edit Invoice** (`/billing-dashboard/edit/{id}`) - Edit existing invoice
- **View Invoice** (`/billing-dashboard/bill/{id}`) - View invoice details
- **Inventory** (`/billing-dashboard/inventory`) - View and manage inventory
- **Add Product** (`/billing-dashboard/inventory/add`) - Add new product
- **Edit Product** (`/billing-dashboard/inventory/edit/{id}`) - Edit product
- **Reports** (`/billing-dashboard/reports`) - View sales, profit, and credit reports
- **Import Invoices** (`/billing-dashboard/import`) - Import invoices from CSV
- **Settings** (`/billing-dashboard/settings`) - Configure plugin settings

### Creating an Invoice

**From Frontend:**
1. Go to **Billing Dashboard > Create Invoice**
2. Select a customer (or create new)
3. Set invoice date and due date
4. Add invoice items (search products or enter manually)
5. Set payment information
6. Add notes (optional)
7. Click "Create Invoice"

**From Admin:**
1. Go to **Bills > Add New** in WordPress admin
2. Follow the same process as frontend

### Managing Inventory

1. Go to **Billing Dashboard > Inventory**
2. View all products with stock quantities
3. Click "Add Product" to create new product
4. Click "Edit" icon to modify existing products
5. Click "View History" icon to see inventory change history

### Importing Invoices

1. Go to **Billing Dashboard > Import Invoices**
2. Prepare a CSV file with invoice data
3. Upload the CSV file
4. Review and confirm import
5. Invoices will be created automatically

### Reports

1. Go to **Billing Dashboard > Reports**
2. Select date range (Week, Month, Year, All Time, or Custom)
3. View sales, profits, and credits summary
4. Export data if needed

### Settings

Configure the plugin settings at **Billing Dashboard > Settings**:

**Currency Settings:**
- Currency Symbol (default: AED)
- Currency Position (before or after amount)

**Invoice Settings:**
- Invoice Number Prefix (default: BILL)

**Company Information:**
- Company Name
- Email
- Phone
- Address

## File Structure

```
black-rock-billing/
├── admin/
│   ├── class-brb-admin.php          # Admin settings and menu
│   └── class-brb-customers.php      # Admin customer management
├── assets/
│   ├── css/
│   │   ├── admin.css                 # Admin styles
│   │   └── frontend.css              # Frontend styles
│   └── js/
│       ├── admin.js                  # Admin JavaScript
│       └── frontend.js               # Frontend JavaScript
├── frontend/
│   └── class-brb-frontend.php        # Frontend dashboard and pages
├── includes/
│   ├── class-brb-helpers.php         # Helper functions
│   ├── class-brb-meta-boxes.php      # Meta boxes for invoices
│   ├── class-brb-post-types.php      # Custom post type registration
│   ├── class-brb-email.php          # Email notifications
│   ├── class-brb-pdf.php             # PDF generation
│   └── class-brb-user-profile.php    # User profile extensions
└── black-rock-billing.php            # Main plugin file
```

## Design System

The plugin features a modern, consistent design system:

- **Color Scheme**: Blue gradient headers (#3b82f6 to #2563eb)
- **Card-Based Layout**: Clean white cards with subtle shadows
- **Typography**: Clear hierarchy with proper font weights and sizes
- **Icons**: SVG icons throughout for visual consistency
- **Responsive**: Fully responsive design for all screen sizes
- **Animations**: Smooth transitions and hover effects

## Customization

The plugin is designed to work with any WordPress theme and follows WordPress coding standards. You can customize:

- **Styling**: Modify CSS files in `assets/css/`
- **Templates**: The frontend templates are in `frontend/class-brb-frontend.php`
- **Functionality**: Extend helper functions in `includes/class-brb-helpers.php`
- **PDF Templates**: Customize PDF generation in `includes/class-brb-pdf.php`

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Any WordPress theme (works with all themes)

## Support

For support and feature requests, please visit the [GitHub repository](https://github.com/omer-ct/billing-plugin) or contact the plugin developer.

**Developer**: Omer Muhammad  
**LinkedIn**: [https://www.linkedin.com/in/omer-muhammad-14b64929b/](https://www.linkedin.com/in/omer-muhammad-14b64929b/)

## License

GPL v2 or later

## Changelog

### Version 1.0.0
- Initial release
- Invoice management system
- Customer management
- Inventory management
- Reports and analytics
- CSV import functionality
- Modern frontend dashboard
- PDF generation
- Email notifications