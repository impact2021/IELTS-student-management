const db = require('../config/database');
const MembershipPlan = require('../models/MembershipPlan');

async function seedMembershipPlans() {
  console.log('Seeding membership plans...');

  const plans = [
    {
      name: 'Free Trial',
      description: 'Try our platform with limited features',
      price: 0,
      durationDays: 7,
      features: [
        'Access to basic IELTS materials',
        'Practice tests (limited)',
        '7 days access'
      ]
    },
    {
      name: 'Basic',
      description: 'Perfect for beginners starting their IELTS journey',
      price: 29.99,
      durationDays: 30,
      features: [
        'Access to all IELTS materials',
        'Unlimited practice tests',
        'Score tracking',
        '30 days access',
        'Email support'
      ]
    },
    {
      name: 'Premium',
      description: 'Best value for serious IELTS students',
      price: 79.99,
      durationDays: 90,
      features: [
        'All Basic features',
        'Live online classes',
        'One-on-one tutoring sessions',
        'Writing evaluation',
        'Speaking practice with AI',
        '90 days access',
        'Priority support'
      ]
    },
    {
      name: 'Professional',
      description: 'Complete IELTS preparation package',
      price: 199.99,
      durationDays: 365,
      features: [
        'All Premium features',
        'Unlimited live classes',
        'Weekly one-on-one sessions',
        'Personalized study plan',
        'Mock exams with detailed feedback',
        'Guaranteed score improvement',
        '365 days access',
        '24/7 priority support'
      ]
    }
  ];

  for (const planData of plans) {
    try {
      // Check if plan already exists
      const existingPlans = await MembershipPlan.getAll();
      const exists = existingPlans.find(p => p.name === planData.name);
      
      if (!exists) {
        await MembershipPlan.create(planData);
        console.log(`✓ Created plan: ${planData.name}`);
      } else {
        console.log(`- Plan already exists: ${planData.name}`);
      }
    } catch (error) {
      console.error(`Error creating plan ${planData.name}:`, error.message);
    }
  }

  console.log('Seeding completed!');
}

async function seed() {
  try {
    await db.connect();
    await seedMembershipPlans();
    await db.close();
  } catch (error) {
    console.error('Seeding failed:', error);
    process.exit(1);
  }
}

// Run if called directly
if (require.main === module) {
  seed();
}

module.exports = { seedMembershipPlans };
