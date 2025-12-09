const db = require('../config/database');

class Payment {
  static async create({ membershipId, amount, paymentMethod, transactionId, status = 'completed' }) {
    const result = await db.run(
      `INSERT INTO payment_history 
       (membership_id, amount, payment_method, transaction_id, status) 
       VALUES (?, ?, ?, ?, ?)`,
      [membershipId, amount, paymentMethod, transactionId, status]
    );

    // Update membership payment status
    await db.run(
      'UPDATE user_memberships SET payment_status = ? WHERE id = ?',
      [status === 'completed' ? 'paid' : 'pending', membershipId]
    );

    return await this.findById(result.id);
  }

  static async findById(id) {
    return await db.get('SELECT * FROM payment_history WHERE id = ?', [id]);
  }

  static async getByMembershipId(membershipId) {
    return await db.all(
      'SELECT * FROM payment_history WHERE membership_id = ? ORDER BY payment_date DESC',
      [membershipId]
    );
  }

  static async getByUserId(userId) {
    const payments = await db.all(`
      SELECT ph.*, um.plan_id, mp.name as plan_name
      FROM payment_history ph
      JOIN user_memberships um ON ph.membership_id = um.id
      JOIN membership_plans mp ON um.plan_id = mp.id
      WHERE um.user_id = ?
      ORDER BY ph.payment_date DESC
    `, [userId]);
    
    return payments;
  }

  static async getAll() {
    const payments = await db.all(`
      SELECT ph.*, 
             um.user_id,
             u.email,
             u.first_name,
             u.last_name,
             mp.name as plan_name
      FROM payment_history ph
      JOIN user_memberships um ON ph.membership_id = um.id
      JOIN users u ON um.user_id = u.id
      JOIN membership_plans mp ON um.plan_id = mp.id
      ORDER BY ph.payment_date DESC
    `);
    
    return payments;
  }

  static async updateStatus(id, status) {
    await db.run('UPDATE payment_history SET status = ? WHERE id = ?', [status, id]);
    return await this.findById(id);
  }
}

module.exports = Payment;
