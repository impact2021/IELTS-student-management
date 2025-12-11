const MembershipPlan = require('../models/MembershipPlan');
const UserMembership = require('../models/UserMembership');
const Payment = require('../models/Payment');

const membershipController = {
  // Membership Plans
  async createPlan(req, res) {
    try {
      const { name, description, price, durationDays, features } = req.body;

      if (!name || price === undefined || !durationDays) {
        return res.status(400).json({ error: 'Name, price, and duration are required' });
      }

      const plan = await MembershipPlan.create({
        name,
        description,
        price,
        durationDays,
        features
      });

      res.status(201).json({
        message: 'Membership plan created successfully',
        plan
      });
    } catch (error) {
      res.status(400).json({ error: error.message });
    }
  },

  async getPlans(req, res) {
    try {
      const activeOnly = req.query.active === 'true';
      const plans = await MembershipPlan.getAll(activeOnly);
      res.json({ plans });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  async getPlan(req, res) {
    try {
      const plan = await MembershipPlan.findById(req.params.id);
      
      if (!plan) {
        return res.status(404).json({ error: 'Plan not found' });
      }

      res.json({ plan });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  async updatePlan(req, res) {
    try {
      const { name, description, price, durationDays, features, isActive } = req.body;
      
      const plan = await MembershipPlan.update(req.params.id, {
        name,
        description,
        price,
        durationDays,
        features,
        isActive
      });

      res.json({
        message: 'Plan updated successfully',
        plan
      });
    } catch (error) {
      res.status(400).json({ error: error.message });
    }
  },

  async deletePlan(req, res) {
    try {
      await MembershipPlan.delete(req.params.id);
      res.json({ message: 'Plan deleted successfully' });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  // User Memberships
  async subscribeToPlan(req, res) {
    try {
      const { planId, paymentMethod, transactionId } = req.body;

      if (!planId) {
        return res.status(400).json({ error: 'Plan ID is required' });
      }

      // Create membership
      const membership = await UserMembership.create({
        userId: req.userId,
        planId,
        paymentStatus: 'pending'
      });

      // If payment info provided, create payment record
      if (paymentMethod && transactionId) {
        const plan = await MembershipPlan.findById(planId);
        await Payment.create({
          membershipId: membership.id,
          amount: plan.price,
          paymentMethod,
          transactionId,
          status: 'completed'
        });
      }

      res.status(201).json({
        message: 'Subscribed to plan successfully',
        membership
      });
    } catch (error) {
      res.status(400).json({ error: error.message });
    }
  },

  async getUserMemberships(req, res) {
    try {
      const memberships = await UserMembership.getByUserId(req.userId);
      res.json({ memberships });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  async getActiveMembership(req, res) {
    try {
      const membership = await UserMembership.getActiveMembership(req.userId);
      
      if (!membership) {
        return res.status(404).json({ error: 'No active membership found' });
      }

      res.json({ membership });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  async renewMembership(req, res) {
    try {
      const { planId, paymentMethod, transactionId } = req.body;

      if (!planId) {
        return res.status(400).json({ error: 'Plan ID is required' });
      }

      const membership = await UserMembership.renewMembership(req.userId, planId);

      // If payment info provided, create payment record
      if (paymentMethod && transactionId) {
        const plan = await MembershipPlan.findById(planId);
        await Payment.create({
          membershipId: membership.id,
          amount: plan.price,
          paymentMethod,
          transactionId,
          status: 'completed'
        });
      }

      res.json({
        message: 'Membership renewed successfully',
        membership
      });
    } catch (error) {
      res.status(400).json({ error: error.message });
    }
  },

  async cancelMembership(req, res) {
    try {
      const { id } = req.params;
      const membership = await UserMembership.cancelMembership(id);

      res.json({
        message: 'Membership cancelled successfully',
        membership
      });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  // Admin endpoints
  async getAllMemberships(req, res) {
    try {
      const memberships = await UserMembership.getAll();
      res.json({ memberships });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  async getUserPayments(req, res) {
    try {
      const payments = await Payment.getByUserId(req.userId);
      res.json({ payments });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  async getAllPayments(req, res) {
    try {
      const payments = await Payment.getAll();
      res.json({ payments });
    } catch (error) {
      res.status(500).json({ error: error.message });
    }
  },

  async extendMembership(req, res) {
    try {
      const { code } = req.body;

      if (!code) {
        return res.status(400).json({ error: 'Extension code is required' });
      }

      // For now, we'll implement a simple code-based extension
      // In a real implementation, you would validate the code against a database
      // and determine which plan to extend with
      
      // Mock validation - accept any non-empty code for now
      // In production, you would:
      // 1. Validate the code exists in an extension_codes table
      // 2. Check if the code is not already used
      // 3. Get the plan_id associated with the code
      // 4. Create a new membership with that plan
      
      // TODO: Make this configurable or retrieve from extension code validation
      // For this implementation, we'll extend with a default plan (assuming plan ID 2 - Basic)
      const DEFAULT_EXTENSION_PLAN_ID = 2; // Basic plan - should be configurable
      const planId = DEFAULT_EXTENSION_PLAN_ID;
      
      // Create new membership
      const membership = await UserMembership.create({
        userId: req.userId,
        planId,
        paymentStatus: 'completed'
      });

      res.json({
        message: 'Membership extended successfully',
        membership
      });
    } catch (error) {
      res.status(400).json({ error: error.message });
    }
  }
};

module.exports = membershipController;
