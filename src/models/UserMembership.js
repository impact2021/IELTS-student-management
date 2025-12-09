const db = require('../config/database');

class UserMembership {
  static async create({ userId, planId, paymentStatus = 'pending' }) {
    // Get plan details to calculate end date
    const plan = await db.get('SELECT duration_days FROM membership_plans WHERE id = ?', [planId]);
    
    if (!plan) {
      throw new Error('Plan not found');
    }

    const startDate = new Date();
    const endDate = new Date();
    endDate.setDate(endDate.getDate() + plan.duration_days);

    const result = await db.run(
      `INSERT INTO user_memberships 
       (user_id, plan_id, start_date, end_date, status, payment_status) 
       VALUES (?, ?, ?, ?, 'active', ?)`,
      [userId, planId, startDate.toISOString(), endDate.toISOString(), paymentStatus]
    );

    return await this.findById(result.id);
  }

  static async findById(id) {
    const membership = await db.get(`
      SELECT um.*, mp.name as plan_name, mp.description, mp.price
      FROM user_memberships um
      JOIN membership_plans mp ON um.plan_id = mp.id
      WHERE um.id = ?
    `, [id]);
    
    return membership;
  }

  static async getByUserId(userId) {
    const memberships = await db.all(`
      SELECT um.*, mp.name as plan_name, mp.description, mp.price
      FROM user_memberships um
      JOIN membership_plans mp ON um.plan_id = mp.id
      WHERE um.user_id = ?
      ORDER BY um.created_at DESC
    `, [userId]);
    
    return memberships;
  }

  static async getActiveMembership(userId) {
    const membership = await db.get(`
      SELECT um.*, mp.name as plan_name, mp.description, mp.price
      FROM user_memberships um
      JOIN membership_plans mp ON um.plan_id = mp.id
      WHERE um.user_id = ? 
        AND um.status = 'active'
        AND um.end_date > CURRENT_TIMESTAMP
      ORDER BY um.end_date DESC
      LIMIT 1
    `, [userId]);
    
    return membership;
  }

  static async updateStatus(id, status) {
    await db.run('UPDATE user_memberships SET status = ? WHERE id = ?', [status, id]);
    return await this.findById(id);
  }

  static async updatePaymentStatus(id, paymentStatus) {
    await db.run('UPDATE user_memberships SET payment_status = ? WHERE id = ?', [paymentStatus, id]);
    return await this.findById(id);
  }

  static async renewMembership(userId, planId) {
    // Cancel any active memberships
    await db.run(
      `UPDATE user_memberships 
       SET status = 'expired' 
       WHERE user_id = ? AND status = 'active'`,
      [userId]
    );

    // Create new membership
    return await this.create({ userId, planId });
  }

  static async cancelMembership(id) {
    await db.run('UPDATE user_memberships SET status = \'cancelled\' WHERE id = ?', [id]);
    return await this.findById(id);
  }

  static async checkAndExpireMemberships() {
    // This should be run periodically to expire memberships
    const result = await db.run(`
      UPDATE user_memberships 
      SET status = 'expired' 
      WHERE status = 'active' 
        AND end_date <= datetime('now')
    `);
    
    return result.changes;
  }

  static async getAll() {
    const memberships = await db.all(`
      SELECT um.*, 
             mp.name as plan_name, 
             mp.price,
             u.email,
             u.first_name,
             u.last_name
      FROM user_memberships um
      JOIN membership_plans mp ON um.plan_id = mp.id
      JOIN users u ON um.user_id = u.id
      ORDER BY um.created_at DESC
    `);
    
    return memberships;
  }
}

module.exports = UserMembership;
