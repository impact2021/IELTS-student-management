# IELTS Student Management - Impact Websites Student Management Plugin

A comprehensive partner-admin invite system for LearnDash with shared partner dashboard functionality.

## Overview

This WordPress plugin provides a complete student management system where partner administrators can create and manage invite codes for students. It features a global shared dashboard where all partner admins see the same codes and users (shared company pool).

## Key Features

- **Partner Admin Role**: Dedicated role with capability to manage invites
- **Single-Use Invite Codes**: Generate up to 10 invite codes at once
- **Global Shared Dashboard**: All partner admins see the same invites and managed users
- **LearnDash Integration**: Auto-enrollment in ALL LearnDash courses upon registration
- **Site-Wide Login Enforcement**: Public access only to configured login and registration pages
- **Expiration Management**: Automatic user expiration with email notifications
- **WordPress Integration**: Ready-to-use shortcodes for dashboard, login, registration, and expiry display

## Components

This system consists of two main components:

1. **WordPress Plugin** - Main student management functionality with partner dashboard
2. **Node.js REST API Backend** (Optional) - Handles authentication, membership logic, and data storage for extended features

## Tech Stack

- **Backend**: Node.js + Express
- **Database**: SQLite (easily upgradeable to PostgreSQL/MySQL)
- **Authentication**: JWT (JSON Web Tokens)
- **Password Security**: bcrypt for password hashing
- **Frontend**: WordPress Plugin with AJAX-powered forms

## Installation

### Part 1: Node.js API Backend

1. Clone the repository:
```bash
git clone <repository-url>
cd IELTS-student-management
```

2. Install dependencies:
```bash
npm install
```

3. Create environment file:
```bash
cp .env.example .env
```

4. Edit `.env` and set your configuration:
```
PORT=3000
JWT_SECRET=your-secret-key-here
```

5. Seed the database with initial membership plans:
```bash
npm run seed
```

6. Start the server:
```bash
npm start
```

The API will be available at `http://localhost:3000`

### WordPress Plugin Installation

#### Option A: Upload via WordPress Admin (Recommended)

1. Download or create a zip file of the entire repository
2. In WordPress admin, go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Activate the plugin
5. Go to **Partnership area > Settings** in the WordPress admin sidebar
6. Configure the settings:
   - **Login page URL**: Full URL of the page with `[iw_login]` shortcode
   - **Registration page URL**: Full URL of the page with `[iw_register_with_code]` shortcode (must be publicly accessible)
   - **Default invite length (days)**: How long invite codes are valid (e.g., 30, 60, 90 days)
   - **Max students per partner**: Maximum active students (0 = unlimited)
   - **Action on user expiry**: Choose to delete user or just remove LearnDash enrollment
   - **Post-registration redirect URLs**: Where to send users after successful registration

#### Option B: Manual Installation

1. Copy the entire repository folder to your WordPress `wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin panel
3. A new **Partnership area** menu item will appear in the WordPress admin sidebar
4. Go to **Partnership area > Settings** to configure the plugin

#### Required Pages

Create these pages with the specified shortcodes:

1. **Partner Dashboard** - Add shortcode `[iw_partner_dashboard]`
   - Only accessible to users with partner admin capability
   
2. **Login Page** - Add shortcode `[iw_login]`
   - Configure the full URL in plugin settings
   
3. **Registration Page** - Add shortcode `[iw_register_with_code]`
   - Configure the full URL in plugin settings
   - Must be publicly accessible (exempted from site-wide login enforcement)
   
4. **My Account / Expiry Page** - Add shortcode `[iw_my_expiry]`
   - Shows logged-in user's membership expiration date

#### Admin Menu

After activation, you'll see a **Partnership area** menu in the WordPress admin sidebar with:
- Settings page for configuring plugin options
- Dashboard icon (network/connections icon)

## Usage Workflow

### For Administrators

1. **Initial Setup**:
   - Activate the plugin
   - Create the required pages with shortcodes
   - Configure settings in **Partnership area > Settings**
   - Create partner admin users or assign the `partner_admin` role to existing users

2. **Managing Partner Admins**:
   - Users with administrator, partner_admin, or impact_manager roles can manage invites
   - All partner admins share the same pool of invites and students (global dashboard)

### For Partner Admins

1. **Access Partner Dashboard**:
   - Log in to WordPress
   - Navigate to the page containing `[iw_partner_dashboard]` shortcode

2. **Create Invite Codes**:
   - Select quantity (1-10 codes)
   - Select days valid (informational only - actual expiry is set in plugin settings)
   - Click "Create codes"
   - Copy the generated codes to share with students

3. **Create Users Manually** (New Feature):
   - Enter user's email address, first name, and last name
   - Select days of access (30, 60, 90, 180, or 365 days)
   - Click "Create User"
   - System automatically:
     - Generates a username from the email address
     - Creates a secure random password
     - Enrolls user in ALL LearnDash courses (if slots available)
     - Sends welcome email to user with login credentials
     - Sends confirmation email to partner admin
   - Use this feature to quickly add users without requiring invite codes

4. **Monitor Students**:
   - View all active students in the global pool
   - See username, email, and expiration date
   - Revoke access for any student if needed (removes LearnDash enrollments immediately)

5. **Track Invite Usage**:
   - See all invite codes (used and available)
   - View who used each code and when
   - All partner admins see the same invites (shared pool)

### For Students

1. **Registration**:
   - Receive an invite code from a partner admin
   - Visit the registration page
   - Enter invite code, username, email, and password
   - Submit form to create account

2. **What Happens Automatically**:
   - Account is created with subscriber role
   - User is enrolled in ALL LearnDash courses
   - Invite code is marked as used (single-use)
   - User is automatically logged in and redirected
   - Partner admin receives email notification
   - Expiration date is set based on plugin settings

3. **Access Content**:
   - User can now access all LearnDash courses
   - Can view expiration date on the page with `[iw_my_expiry]` shortcode

4. **Expiration**:
   - Partner admin receives advance notice (configurable days before expiry)
   - On expiration, user is either deleted or LearnDash enrollment is removed (configurable)
   - Partner admin receives expiration notification

## Available Shortcodes

### `[iw_partner_dashboard]`
Displays the partner dashboard with:
- Form to create invite codes (1-10 codes at once)
- Form to manually create new users (without invite codes)
- Table of all invite codes (used/available)
- List of active students with ability to revoke
- Search functionality for students
- Student management options (re-enroll, update expiry)

**Access**: Requires `manage_partner_invites` capability (administrator, partner_admin, or impact_manager role)

### `[iw_register_with_code]`
Displays registration form with fields:
- Invite Code (single-use)
- Username
- Email
- Password

**Access**: Public (configure URL in settings to exempt from login enforcement)

### `[iw_login]`
Displays custom login form with:
- Username or Email field
- Password field
- Remember me checkbox
- Lost password link

**Access**: Public

### `[iw_my_expiry]`
Displays membership expiration information:
- Expiration date
- Days remaining
- Expired status if applicable

**Access**: Logged-in users only

## Settings Reference

### Partnership area > Settings

**Default invite length (days)**
- How many days students get access after using an invite code
- Range: 1-365 days
- Default: 30 days

**Max students per partner (0 = unlimited)**
- Global limit for the shared partner pool
- Set to 0 for unlimited
- Default: 10
- Note: This is for ALL partners combined (shared pool)

**Action on user expiry**
- **Delete user**: Completely removes the WordPress user account
- **Remove LearnDash enrollments (keep WP user)**: Removes course access but keeps the account with "expired" role

**Notify partners this many days before expiry**
- Days before expiration to send advance notification email to partner admin
- Set to 0 to disable advance notifications
- Default: 7 days

**Post-registration redirect URL (site)**
- Full URL where users are redirected after registration
- Leave blank to redirect to homepage
- Applied to all registrations unless partner-specific URL is set

**Post-registration redirect URL (partners)**
- Full URL for users registered via partner invites
- If empty, uses the site-wide post-registration redirect
- Allows custom flow for partner-invited students

**Login page URL (required for site-wide access control)**
- Full URL of the page containing `[iw_login]` shortcode
- Example: `https://example.com/login`
- Required for site-wide login enforcement

**Registration page URL (public)**
- Full URL of the page containing `[iw_register_with_code]` shortcode
- Example: `https://example.com/register`
- This page is exempted from login enforcement so students can register
- Must be publicly accessible

## Node.js API Backend (Optional)

The repository also includes an optional Node.js REST API backend for extended features. See below for API documentation.

## API Documentation

### Authentication Endpoints

#### Register a new user
```http
POST /api/auth/register
Content-Type: application/json

{
  "email": "student@example.com",
  "password": "securepassword",
  "firstName": "John",
  "lastName": "Doe"
}
```

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "student@example.com",
  "password": "securepassword"
}
```

Response includes a JWT token to use for authenticated requests.

#### Get user profile
```http
GET /api/auth/profile
Authorization: Bearer <token>
```

### Membership Endpoints

All membership endpoints require authentication (Bearer token in Authorization header).

#### Get all membership plans
```http
GET /api/membership/plans?active=true
Authorization: Bearer <token>
```

#### Get specific plan
```http
GET /api/membership/plans/:id
Authorization: Bearer <token>
```

#### Subscribe to a plan
```http
POST /api/membership/subscribe
Authorization: Bearer <token>
Content-Type: application/json

{
  "planId": 1,
  "paymentMethod": "credit_card",
  "transactionId": "txn_123456"
}
```

#### Get user's memberships
```http
GET /api/membership/my-memberships
Authorization: Bearer <token>
```

#### Get active membership
```http
GET /api/membership/my-membership
Authorization: Bearer <token>
```

#### Renew membership
```http
POST /api/membership/renew
Authorization: Bearer <token>
Content-Type: application/json

{
  "planId": 2,
  "paymentMethod": "credit_card",
  "transactionId": "txn_789012"
}
```

#### Cancel membership
```http
POST /api/membership/cancel/:membershipId
Authorization: Bearer <token>
```

#### Get payment history
```http
GET /api/membership/my-payments
Authorization: Bearer <token>
```

### Admin Endpoints

#### Get all memberships (admin)
```http
GET /api/membership/admin/memberships
Authorization: Bearer <token>
```

#### Get all payments (admin)
```http
GET /api/membership/admin/payments
Authorization: Bearer <token>
```

## Membership Plans

The system comes with 4 pre-configured membership plans:

1. **Free Trial** ($0 - 7 days)
   - Access to basic IELTS materials
   - Limited practice tests

2. **Basic** ($29.99 - 30 days)
   - All IELTS materials
   - Unlimited practice tests
   - Score tracking
   - Email support

3. **Premium** ($79.99 - 90 days)
   - All Basic features
   - Live online classes
   - One-on-one tutoring
   - Writing evaluation
   - Speaking practice with AI
   - Priority support

4. **Professional** ($199.99 - 365 days)
   - All Premium features
   - Unlimited live classes
   - Weekly one-on-one sessions
   - Personalized study plan
   - Mock exams with feedback
   - Guaranteed score improvement
   - 24/7 priority support

## Database Schema

### Users
- id, email, password_hash, first_name, last_name, created_at, updated_at

### Membership Plans
- id, name, description, price, duration_days, features, is_active, created_at

### User Memberships
- id, user_id, plan_id, start_date, end_date, status, payment_status, created_at

### Payment History
- id, membership_id, amount, payment_date, payment_method, transaction_id, status

## Development

### Project Structure
```
.
├── src/
│   ├── config/          # Database configuration
│   ├── controllers/     # Request handlers
│   ├── middleware/      # Authentication middleware
│   ├── models/          # Database models
│   ├── routes/          # API routes
│   ├── utils/           # Utility functions and seed data
│   ├── app.js           # Express app configuration
│   └── server.js        # Server entry point
├── tests/               # Test files
├── public/              # Static files
├── .env.example         # Environment variables template
├── .gitignore          # Git ignore rules
└── package.json        # Project dependencies

```

## Security Features

- Password hashing with bcrypt
- JWT token-based authentication
- SQL injection prevention through parameterized queries
- CORS protection
- Environment variable configuration

## Future Enhancements

- Role-based access control (admin, instructor, student)
- Email notifications for membership expiration
- Payment gateway integration (Stripe, PayPal)
- Reporting and analytics
- Frontend dashboard
- Mobile app API support

## License

ISC

## Support

For issues and questions, please open an issue in the repository.
