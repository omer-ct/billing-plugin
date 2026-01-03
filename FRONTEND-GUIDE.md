# Frontend Usage Guide

## Overview

The Black Rock Billing plugin provides comprehensive frontend functionality for both customers and administrators to manage bills directly from the frontend of your website.

## Frontend Routes

### Customer Dashboard
- **URL**: `/billing-dashboard`
- **Access**: All logged-in users
- **Features**:
  - View summary cards (Total Billed, Total Paid, Total Pending)
  - View all bills in a table format
  - Click "View" to see individual bill details
  - Print bills

### Individual Bill View
- **URL**: `/billing-dashboard/bill/{bill_id}`
- **Access**: Bill owner or administrators
- **Features**:
  - View complete bill details
  - See all items with quantities and rates
  - View payment information
  - Print bill

### Create Bill (Admin Only)
- **URL**: `/billing-dashboard/create`
- **Access**: Administrators only
- **Features**:
  - Create new bills from frontend
  - Select customer
  - Add multiple items dynamically
  - Set bill dates and status
  - Real-time total calculation

## Menu Integration

The plugin automatically adds menu items to your WordPress navigation menu:

- **Billing Dashboard** - Visible to all logged-in users
- **Create Bill** - Visible only to administrators

To customize menu placement, you can manually add these links to your menu in **Appearance > Menus**.

## Frontend Features

### 1. Customer Dashboard

Customers can:
- View their billing summary
- See all their bills in one place
- Filter and view bill details
- Track payment status

### 2. Bill Creation (Admin)

Administrators can create bills from the frontend:
1. Navigate to `/billing-dashboard/create`
2. Select a customer
3. Add bill items (description, quantity, rate)
4. Set bill date, due date, and status
5. Add notes if needed
6. Set initial paid amount (optional)
7. Click "Create Bill"

The form includes:
- Real-time total calculation
- Add/remove items dynamically
- Validation before submission
- Success/error messages

### 3. Bill Viewing

Both customers and admins can:
- View complete bill details
- See itemized list
- Check payment status
- Print bills (browser print dialog)

### 4. AJAX Functionality

The frontend uses AJAX for:
- Creating bills without page reload
- Updating payments (if implemented)
- Real-time calculations

## Styling

The frontend styles are designed to work with the Blocksy theme and include:
- Responsive design
- Modern card-based layout
- Print-friendly styles
- Mobile-optimized tables

## Customization

### Adding Custom Actions

You can add custom buttons or actions to bills by modifying:
- `frontend/class-brb-frontend.php` - Add new routes and handlers
- `assets/js/frontend.js` - Add JavaScript functionality
- `assets/css/frontend.css` - Customize styling

### Example: Add Payment Button

```php
// In render_bill_view() method
if ($pending > 0) {
    echo '<button class="brb-pay-now-btn" data-bill-id="' . $bill_id . '">Pay Now</button>';
}
```

### Example: Custom Menu Items

```php
// Add to functions.php or your theme
add_filter('wp_nav_menu_items', function($items, $args) {
    if (is_user_logged_in()) {
        $items .= '<li><a href="' . home_url('/billing-dashboard') . '">My Bills</a></li>';
    }
    return $items;
}, 10, 2);
```

## Security

- All routes check user authentication
- Bill access is restricted to owners and admins
- AJAX requests include nonce verification
- Admin functions require `manage_options` capability

## Troubleshooting

### Permalinks Not Working

If routes don't work, flush rewrite rules:
1. Go to **Settings > Permalinks**
2. Click "Save Changes" (no need to change anything)

### Menu Items Not Showing

Menu items are added automatically. If they don't appear:
1. Check if user is logged in
2. Verify menu location in theme settings
3. Manually add links in **Appearance > Menus**

### AJAX Errors

If AJAX requests fail:
1. Check browser console for errors
2. Verify nonce is being sent correctly
3. Ensure user has proper permissions

## Next Steps

Consider adding:
- Payment gateway integration
- PDF generation
- Email notifications
- Bill filtering and search
- Export functionality
- Recurring bills

