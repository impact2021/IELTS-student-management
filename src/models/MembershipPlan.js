const db = require('../config/database');

class MembershipPlan {
  static async create({ name, description, price, durationDays, features }) {
    const featuresJson = JSON.stringify(features || []);
    
    try {
      const result = await db.run(
        'INSERT INTO membership_plans (name, description, price, duration_days, features) VALUES (?, ?, ?, ?, ?)',
        [name, description, price, durationDays, featuresJson]
      );
      
      return await this.findById(result.id);
    } catch (error) {
      if (error.message.includes('UNIQUE constraint failed')) {
        throw new Error('Plan name already exists');
      }
      throw error;
    }
  }

  static async findById(id) {
    const plan = await db.get('SELECT * FROM membership_plans WHERE id = ?', [id]);
    if (plan && plan.features) {
      plan.features = JSON.parse(plan.features);
    }
    return plan;
  }

  static async getAll(activeOnly = false) {
    const query = activeOnly 
      ? 'SELECT * FROM membership_plans WHERE is_active = 1'
      : 'SELECT * FROM membership_plans';
    
    const plans = await db.all(query);
    return plans.map(plan => {
      if (plan.features) {
        plan.features = JSON.parse(plan.features);
      }
      return plan;
    });
  }

  static async update(id, { name, description, price, durationDays, features, isActive }) {
    const updates = [];
    const params = [];

    if (name !== undefined) {
      updates.push('name = ?');
      params.push(name);
    }
    if (description !== undefined) {
      updates.push('description = ?');
      params.push(description);
    }
    if (price !== undefined) {
      updates.push('price = ?');
      params.push(price);
    }
    if (durationDays !== undefined) {
      updates.push('duration_days = ?');
      params.push(durationDays);
    }
    if (features !== undefined) {
      updates.push('features = ?');
      params.push(JSON.stringify(features));
    }
    if (isActive !== undefined) {
      updates.push('is_active = ?');
      params.push(isActive ? 1 : 0);
    }

    if (updates.length === 0) {
      return await this.findById(id);
    }

    params.push(id);
    await db.run(
      `UPDATE membership_plans SET ${updates.join(', ')} WHERE id = ?`,
      params
    );

    return await this.findById(id);
  }

  static async delete(id) {
    await db.run('DELETE FROM membership_plans WHERE id = ?', [id]);
  }

  static async toggleActive(id, isActive) {
    await db.run('UPDATE membership_plans SET is_active = ? WHERE id = ?', [isActive ? 1 : 0, id]);
    return await this.findById(id);
  }
}

module.exports = MembershipPlan;
