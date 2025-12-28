#!/bin/bash
# Test script for registration with payment functionality

BASE_URL="http://localhost:3000"

echo "==============================================="
echo "Testing Registration with Payment Functionality"
echo "==============================================="
echo ""

# Test 1: Basic registration without plan (backward compatibility)
echo "Test 1: Registration without plan (backward compatibility)"
echo "-----------------------------------------------------------"
curl -s -X POST $BASE_URL/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user1@example.com",
    "password": "SecurePass123",
    "firstName": "John",
    "lastName": "Doe"
  }' | python3 -m json.tool 2>/dev/null
echo -e "\n✓ Should create user without membership\n"

# Test 2: Registration with plan and payment
echo "Test 2: Registration with plan and payment"
echo "-------------------------------------------"
curl -s -X POST $BASE_URL/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user2@example.com",
    "password": "SecurePass123",
    "firstName": "Jane",
    "lastName": "Smith",
    "planId": 2,
    "paymentMethod": "credit_card",
    "transactionId": "txn_test_12345"
  }' | python3 -m json.tool 2>/dev/null
echo -e "\n✓ Should create user with paid membership\n"

# Test 3: Registration with plan but no payment
echo "Test 3: Registration with plan but without payment details"
echo "-----------------------------------------------------------"
curl -s -X POST $BASE_URL/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user3@example.com",
    "password": "SecurePass123",
    "firstName": "Bob",
    "lastName": "Johnson",
    "planId": 3
  }' | python3 -m json.tool 2>/dev/null
echo -e "\n✓ Should create user with pending membership\n"

# Test 4: Registration with invalid planId
echo "Test 4: Registration with invalid planId"
echo "-----------------------------------------"
curl -s -X POST $BASE_URL/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user4@example.com",
    "password": "SecurePass123",
    "firstName": "Alice",
    "lastName": "Williams",
    "planId": "invalid"
  }' | python3 -m json.tool 2>/dev/null
echo -e "\n✓ Should return error: Invalid plan ID\n"

# Test 5: Registration with non-existent planId
echo "Test 5: Registration with non-existent planId"
echo "----------------------------------------------"
curl -s -X POST $BASE_URL/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user5@example.com",
    "password": "SecurePass123",
    "firstName": "Charlie",
    "lastName": "Brown",
    "planId": 999
  }' | python3 -m json.tool 2>/dev/null
echo -e "\n✓ Should return error: Plan not found\n"

echo "==============================================="
echo "All tests completed!"
echo "==============================================="
