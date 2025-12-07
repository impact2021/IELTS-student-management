const express = require('express');
const router = express.Router();
const membershipController = require('../controllers/membershipController');
const authMiddleware = require('../middleware/auth');

// All membership routes require authentication
router.use(authMiddleware);

// Membership Plans
router.get('/plans', membershipController.getPlans);
router.get('/plans/:id', membershipController.getPlan);
router.post('/plans', membershipController.createPlan);
router.put('/plans/:id', membershipController.updatePlan);
router.delete('/plans/:id', membershipController.deletePlan);

// User Memberships
router.post('/subscribe', membershipController.subscribeToPlan);
router.get('/my-memberships', membershipController.getUserMemberships);
router.get('/my-membership', membershipController.getActiveMembership);
router.post('/renew', membershipController.renewMembership);
router.post('/cancel/:id', membershipController.cancelMembership);

// Payments
router.get('/my-payments', membershipController.getUserPayments);

// Admin routes (in production, add admin role check)
router.get('/admin/memberships', membershipController.getAllMemberships);
router.get('/admin/payments', membershipController.getAllPayments);

module.exports = router;
