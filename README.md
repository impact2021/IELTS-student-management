# IELTS Student Management - Membership System

A comprehensive membership management system for IELTS student management, replacing Amember with a modern, self-hosted solution.

## Features

- **User Authentication**: Secure registration and login with JWT tokens
- **Membership Plans**: Multiple tiers (Free Trial, Basic, Premium, Professional)
- **Subscription Management**: Subscribe, renew, and cancel memberships
- **Payment Tracking**: Track payment history and status
- **Expiration Management**: Automatic membership expiration handling
- **REST API**: Complete API for integration with frontend applications

## Tech Stack

- **Backend**: Node.js + Express
- **Database**: SQLite (easily upgradeable to PostgreSQL/MySQL)
- **Authentication**: JWT (JSON Web Tokens)
- **Password Security**: bcrypt for password hashing

## Installation

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
