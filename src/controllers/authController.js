const jwt = require('jsonwebtoken');
const User = require('../models/User');
const UserMembership = require('../models/UserMembership');
const MembershipPlan = require('../models/MembershipPlan');
const Payment = require('../models/Payment');

const JWT_SECRET = process.env.JWT_SECRET || 'your-secret-key-change-this-in-production';

const authController = {
  async register(req, res) {
    try {
      const { email, password, firstName, lastName, planId, paymentMethod, transactionId } = req.body;

      if (!email || !password || !firstName || !lastName) {
        return res.status(400).json({ error: 'All fields are required' });
      }

      const user = await User.create({ email, password, firstName, lastName });
      
      const token = jwt.sign({ id: user.id, email: user.email }, JWT_SECRET, {
        expiresIn: '7d'
      });

      let membership = null;
      let payment = null;

      // If planId is provided, create membership and payment during registration
      if (planId) {
        // Validate planId
        const planIdNum = parseInt(planId);
        if (!planIdNum || planIdNum <= 0) {
          return res.status(400).json({ error: 'Invalid plan ID' });
        }

        // Verify plan exists
        const plan = await MembershipPlan.findById(planIdNum);
        if (!plan) {
          return res.status(404).json({ error: 'Plan not found' });
        }

        // Create membership
        membership = await UserMembership.create({
          userId: user.id,
          planId: planIdNum,
          paymentStatus: 'pending'
        });

        // If payment info provided, create payment record
        if (paymentMethod && transactionId) {
          payment = await Payment.create({
            membershipId: membership.id,
            amount: plan.price,
            paymentMethod,
            transactionId,
            status: 'completed'
          });
          
          // Update membership object with the new payment status (paid)
          membership.payment_status = 'paid';
        }
      }

      res.status(201).json({
        message: 'User registered successfully',
        user,
        token,
        membership,
        payment
      });
    } catch (error) {
      res.status(400).json({ error: error.message });
    }
  },

  async login(req, res) {
    try {
      const { email, password } = req.body;

      if (!email || !password) {
        return res.status(400).json({ error: 'Email and password are required' });
      }

      const user = await User.verifyPassword(email, password);

      if (!user) {
        return res.status(401).json({ error: 'Invalid email or password' });
      }

      const token = jwt.sign({ id: user.id, email: user.email }, JWT_SECRET, {
        expiresIn: '7d'
      });

      res.json({
        message: 'Login successful',
        user,
        token
      });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  async getProfile(req, res) {
    try {
      const user = await User.findById(req.userId);
      
      if (!user) {
        return res.status(404).json({ error: 'User not found' });
      }

      const membership = await User.getActiveMembership(req.userId);

      res.json({
        user,
        membership
      });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  async changePassword(req, res) {
    try {
      const { currentPassword, newPassword } = req.body;

      if (!currentPassword || !newPassword) {
        return res.status(400).json({ error: 'Current password and new password are required' });
      }

      if (newPassword.length < 6) {
        return res.status(400).json({ error: 'New password must be at least 6 characters' });
      }

      const user = await User.findById(req.userId);
      
      if (!user) {
        return res.status(404).json({ error: 'User not found' });
      }

      // Verify current password - verifyPassword returns user object or null
      const isValid = await User.verifyPassword(user.email, currentPassword);
      
      if (!isValid) {
        return res.status(401).json({ error: 'Current password is incorrect' });
      }

      // Update password
      await User.updatePassword(req.userId, newPassword);

      res.json({
        message: 'Password changed successfully'
      });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  }
};

module.exports = authController;
