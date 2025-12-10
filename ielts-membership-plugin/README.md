# IELTS Membership WordPress Plugin

A WordPress plugin that integrates with the IELTS Membership System REST API to provide membership management functionality through shortcodes.

## Features

- **Automatic Page Creation**: Creates required pages with shortcodes when activated
- **User Registration**: Frontend registration form with validation
- **User Login**: Secure login form
- **Partner Dashboard**: Member dashboard showing membership status
- **My Account**: Display membership details and expiration dates
- **AJAX-Powered**: Smooth user experience with AJAX form submissions
- **Responsive Design**: Mobile-friendly interface

## Installation

### Option A: Upload via WordPress Admin (Recommended)

1. Download or create a zip file of the repository
2. In WordPress admin, go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Activate the plugin
5. Configure the API URL in Settings > IELTS Membership

**Note:** The entire repository can be uploaded as a plugin. WordPress will recognize it automatically.

### Option B: Manual Installation

1. Copy the entire repository folder (or just the `ielts-membership-plugin` subfolder) to your WordPress `wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin panel
3. Configure the API URL in Settings > IELTS Membership

## Configuration

After activation, go to **Settings > IELTS Membership** and configure:

- **API URL**: The base URL of your IELTS Membership API (default: `http://localhost:3000/api`)

Make sure your Node.js API server is running before using the plugin.

## Created Pages

The plugin automatically creates the following pages when activated:

1. **/partner-dashboard/** - Partner Dashboard with shortcode `[iw_partner_dashboard]`
2. **/login/** - Login Form with shortcode `[iw_login]`
3. **/my-account/** - Account Details with shortcode `[iw_my_expiry]`
4. **/register/** - Registration Form with shortcode `[iw_register_with_code]`

**Page Creation Behavior:**
- If a published page with the same slug exists, the plugin will update it to include the shortcode
- If a page exists but is in trash or draft status, the plugin will create a new published page
- When you deactivate and reactivate the plugin, it will ensure all required published pages exist
- This ensures that you always have working published pages after reactivation

## Shortcodes

### [iw_partner_dashboard]

Displays the partner dashboard with membership overview and quick actions.

**Usage:**
```
[iw_partner_dashboard]
```

### [iw_login]

Displays the login form.

**Usage:**
```
[iw_login]
```

### [iw_my_expiry]

Displays the user's account information and membership expiration details.

**Usage:**
```
[iw_my_expiry]
```

### [iw_register_with_code]

Displays the registration form with optional registration code.

**Usage:**
```
[iw_register_with_code]
[iw_register_with_code code="SPECIAL123"]
[iw_register_with_code code="SPECIAL123" plan_id="2"]
```

**Attributes:**
- `code` (optional): Registration/referral code
- `plan_id` (optional): Pre-select a membership plan

## Template Files

Templates can be found in the `templates/` directory:

- `login-form.php` - Login form template
- `register-form.php` - Registration form template
- `my-account.php` - My Account template
- `partner-dashboard.php` - Dashboard template

You can override these templates by copying them to your theme's `ielts-membership/` directory.

## API Integration

The plugin communicates with the Node.js REST API for all membership operations:

- User registration
- User login
- Profile retrieval
- Membership management
- Payment tracking

All API requests are handled through the `IW_API_Client` class.

## Security

- AJAX requests use WordPress nonces for CSRF protection
- Passwords are hashed on the API server
- JWT tokens stored in secure cookies
- Input sanitization and validation

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Node.js API server running (included in this repository)
- jQuery (included with WordPress)

## File Structure

```
ielts-membership-plugin/
├── assets/
│   ├── css/
│   │   └── membership-styles.css
│   └── js/
│       └── membership-script.js
├── includes/
│   ├── class-iw-activator.php
│   ├── class-iw-ajax.php
│   ├── class-iw-api-client.php
│   └── class-iw-shortcodes.php
├── templates/
│   ├── login-form.php
│   ├── register-form.php
│   ├── my-account.php
│   └── partner-dashboard.php
├── ielts-membership.php
└── README.md
```

## Support

For issues and questions:
- Check the main repository README
- Review the API documentation
- Ensure the Node.js API server is running

## Changelog

### 1.0.0
- Initial release
- Automatic page creation on activation
- Four shortcodes for membership management
- AJAX-powered forms
- API integration
- Responsive design

## License

GPL v2 or later
