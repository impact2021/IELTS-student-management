# API Testing Guide

This guide provides step-by-step instructions for testing the IELTS Membership System API.

## Prerequisites

- Server running on `http://localhost:3000`
- curl or Postman installed
- jq (optional, for pretty JSON output)

## Quick Start

### 1. Start the Server

```bash
npm install
npm run seed  # Load sample membership plans
npm start     # Start the server
```

The server will be available at `http://localhost:3000`

### 2. Register a New User

```bash
curl -X POST http://localhost:3000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "student@example.com",
    "password": "SecurePass123",
    "firstName": "John",
    "lastName": "Doe"
  }'
```

**Response:**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "email": "student@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "created_at": "2024-12-07 10:00:00"
  },
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Save the token** - you'll need it for authenticated requests!

### 3. Login

```bash
curl -X POST http://localhost:3000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "student@example.com",
    "password": "SecurePass123"
  }'
```

### 4. Get Your Profile

```bash
TOKEN="your-token-here"

curl http://localhost:3000/api/auth/profile \
  -H "Authorization: Bearer $TOKEN"
```

## Membership Plans

### Get All Available Plans

```bash
curl http://localhost:3000/api/membership/plans \
  -H "Authorization: Bearer $TOKEN"
```

### Get Active Plans Only

```bash
curl http://localhost:3000/api/membership/plans?active=true \
  -H "Authorization: Bearer $TOKEN"
```

### Get Specific Plan

```bash
curl http://localhost:3000/api/membership/plans/2 \
  -H "Authorization: Bearer $TOKEN"
```

## Subscriptions

### Subscribe to a Plan

```bash
curl -X POST http://localhost:3000/api/membership/subscribe \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "planId": 2,
    "paymentMethod": "credit_card",
    "transactionId": "txn_abc123"
  }'
```

**Response:**
```json
{
  "message": "Subscribed to plan successfully",
  "membership": {
    "id": 1,
    "user_id": 1,
    "plan_id": 2,
    "start_date": "2024-12-07T10:00:00.000Z",
    "end_date": "2025-01-06T10:00:00.000Z",
    "status": "active",
    "payment_status": "paid",
    "plan_name": "Basic",
    "price": 29.99
  }
}
```

### Get Your Active Membership

```bash
curl http://localhost:3000/api/membership/my-membership \
  -H "Authorization: Bearer $TOKEN"
```

### Get All Your Memberships (History)

```bash
curl http://localhost:3000/api/membership/my-memberships \
  -H "Authorization: Bearer $TOKEN"
```

### Renew Membership

```bash
curl -X POST http://localhost:3000/api/membership/renew \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "planId": 3,
    "paymentMethod": "credit_card",
    "transactionId": "txn_xyz789"
  }'
```

### Cancel Membership

```bash
curl -X POST http://localhost:3000/api/membership/cancel/1 \
  -H "Authorization: Bearer $TOKEN"
```

## Payment History

### Get Your Payment History

```bash
curl http://localhost:3000/api/membership/my-payments \
  -H "Authorization: Bearer $TOKEN"
```

## Admin Endpoints

### Get All Memberships (Admin)

```bash
curl http://localhost:3000/api/membership/admin/memberships \
  -H "Authorization: Bearer $TOKEN"
```

### Get All Payments (Admin)

```bash
curl http://localhost:3000/api/membership/admin/payments \
  -H "Authorization: Bearer $TOKEN"
```

## Complete Testing Workflow

Here's a complete bash script to test the full workflow:

```bash
#!/bin/bash

BASE_URL="http://localhost:3000"

echo "1. Registering new user..."
REGISTER_RESPONSE=$(curl -s -X POST $BASE_URL/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test'$RANDOM'@example.com",
    "password": "TestPass123",
    "firstName": "Test",
    "lastName": "User"
  }')

TOKEN=$(echo $REGISTER_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)
echo "Token: $TOKEN"

echo -e "\n2. Getting profile..."
curl -s $BASE_URL/api/auth/profile \
  -H "Authorization: Bearer $TOKEN" | jq

echo -e "\n3. Getting membership plans..."
curl -s $BASE_URL/api/membership/plans \
  -H "Authorization: Bearer $TOKEN" | jq

echo -e "\n4. Subscribing to Basic plan..."
curl -s -X POST $BASE_URL/api/membership/subscribe \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "planId": 2,
    "paymentMethod": "credit_card",
    "transactionId": "txn_test_'$RANDOM'"
  }' | jq

echo -e "\n5. Getting active membership..."
curl -s $BASE_URL/api/membership/my-membership \
  -H "Authorization: Bearer $TOKEN" | jq

echo -e "\n6. Getting payment history..."
curl -s $BASE_URL/api/membership/my-payments \
  -H "Authorization: Bearer $TOKEN" | jq

echo -e "\nAll tests completed!"
```

Save this as `test-api.sh`, make it executable with `chmod +x test-api.sh`, and run it with `./test-api.sh`

## Rate Limiting

The API has rate limiting enabled:

- **General API**: 100 requests per 15 minutes per IP
- **Authentication**: 5 requests per 15 minutes per IP
- **Static Files**: 30 requests per minute per IP

If you exceed these limits, you'll receive a 429 status code with the message:
```json
{
  "error": "Too many requests from this IP, please try again later."
}
```

## Error Responses

### 400 Bad Request
```json
{
  "error": "All fields are required"
}
```

### 401 Unauthorized
```json
{
  "error": "Invalid token"
}
```

### 404 Not Found
```json
{
  "error": "Plan not found"
}
```

### 429 Too Many Requests
```json
{
  "error": "Too many requests from this IP, please try again later."
}
```

### 500 Internal Server Error
```json
{
  "error": "Something went wrong!"
}
```

## Using Postman

1. Import the API endpoints into Postman
2. Create an environment variable `token` for authentication
3. Set Authorization header to `Bearer {{token}}`
4. Test each endpoint sequentially

## Additional Tips

- Always save your JWT token after registration/login
- Tokens expire after 7 days
- Use the `/api/auth/profile` endpoint to verify your authentication
- Check membership status before attempting operations
- Payment transactions are recorded for audit purposes

## Troubleshooting

**Issue**: "No token provided" error
- **Solution**: Make sure you're including the Authorization header with a valid token

**Issue**: "Invalid token" error
- **Solution**: Your token may have expired. Login again to get a new token

**Issue**: Rate limit errors
- **Solution**: Wait for the time window to reset, or use a different IP address for testing

**Issue**: Database locked error
- **Solution**: Make sure only one instance of the server is running

## Next Steps

- Integrate with a payment gateway (Stripe, PayPal)
- Add email notifications for membership expiration
- Implement role-based access control
- Create a frontend dashboard
- Add more sophisticated reporting
