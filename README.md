# Easy Order Management for WooCommerce

A simple yet powerful order management system for WooCommerce with custom field support and role-based access control.

## Features

- 🔒 Secure order management with role-based access control
- 📊 Customizable order columns with drag-and-drop interface
- 🔄 Advanced Custom Fields (ACF) integration
- 🏷️ Custom status labels
- 🔍 Powerful search and filtering
- 📱 Responsive design
- 🛡️ Built-in security features
- 🔌 Extensible with hooks and filters

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WooCommerce 6.0 or higher
- Advanced Custom Fields (optional, for custom fields)

## Installation

1. Download the latest release
2. Upload to your WordPress site
3. Activate the plugin
4. Go to WooCommerce > Order Management to configure

## Usage

### Basic Setup

1. Configure General Settings:
   - Set orders per page
   - Configure role access permissions

2. Manage Columns:
   - Go to Field Configuration > Column Management
   - Drag and drop to reorder columns
   - Add/remove columns as needed

3. Custom Status Labels:
   - Customize the display text for order statuses
   - Changes apply throughout the interface

### Adding Custom Fields

1. Create ACF Fields:
   - Create a field group for orders
   - Add your desired fields
   - Set location rule to "Post Type is equal to Shop order"

2. Add to Display:
   - Go to Column Management
   - Your ACF fields will appear in the available columns
   - Add them to your display

## Security Features

- Role-based access control
- Input validation and sanitization
- CSRF protection with nonces
- Rate limiting for AJAX requests
- Security headers
- Error logging and monitoring

## Development

### Action Hooks

```php
// Before orders table
do_action('wb_before_order_list');

// After orders table
do_action('wb_after_order_list');

// During plugin installation
do_action('wb_install');

// During plugin uninstallation
do_action('wb_uninstall');
```

### Filter Hooks

```php
// Filter order columns
add_filter('wb_order_columns', function($columns) {
    // Modify columns
    return $columns;
});

// Filter order data
add_filter('wb_order_data', function($data, $order) {
    // Modify data
    return $data;
}, 10, 2);

// Filter status labels
add_filter('wb_status_labels', function($labels) {
    // Modify labels
    return $labels;
});

// Filter role access
add_filter('wb_role_access', function($roles) {
    // Modify roles
    return $roles;
});
```

### Template Overrides

1. Create a directory in your theme: `your-theme/easy-order-management/`
2. Copy template files from `plugin/templates/` to your theme directory
3. Modify as needed - your theme templates will be used instead

### Error Handling

The plugin includes comprehensive error handling and logging:

```php
// Log a security event
WB_Security::log_security_event('custom_event', [
    'key' => 'value'
]);

// Check rate limiting
$result = WB_Security::check_rate_limit('custom_action');
if (is_wp_error($result)) {
    // Handle error
}
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

MIT License - see LICENSE.md for details

## Support

- [Documentation](https://wunderlandmedia.com/docs)
- [GitHub Issues](https://github.com/wunderlandmedia/easy-order-management/issues)
- [Support Forum](https://wordpress.org/support/plugin/easy-order-management/) 