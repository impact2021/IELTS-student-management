const bcrypt = require('bcryptjs');
const db = require('../config/database');

class User {
  static async create({ email, password, firstName, lastName }) {
    const passwordHash = await bcrypt.hash(password, 10);
    
    try {
      const result = await db.run(
        'INSERT INTO users (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)',
        [email, passwordHash, firstName, lastName]
      );
      
      return await this.findById(result.id);
    } catch (error) {
      if (error.message.includes('UNIQUE constraint failed')) {
        throw new Error('Email already exists');
      }
      throw error;
    }
  }

  static async findById(id) {
    const user = await db.get('SELECT * FROM users WHERE id = ?', [id]);
    if (user) {
      delete user.password_hash;
    }
    return user;
  }

  static async findByEmail(email) {
    return await db.get('SELECT * FROM users WHERE email = ?', [email]);
  }

  static async verifyPassword(email, password) {
    const user = await this.findByEmail(email);
    if (!user) {
      return null;
    }

    const isValid = await bcrypt.compare(password, user.password_hash);
    if (!isValid) {
      return null;
    }

    delete user.password_hash;
    return user;
  }

  static async getAll() {
    const users = await db.all('SELECT id, email, first_name, last_name, created_at FROM users');
    return users;
  }

  static async update(id, { firstName, lastName }) {
    await db.run(
      'UPDATE users SET first_name = ?, last_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
      [firstName, lastName, id]
    );
    return await this.findById(id);
  }

  static async delete(id) {
    await db.run('DELETE FROM users WHERE id = ?', [id]);
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
}

module.exports = User;
