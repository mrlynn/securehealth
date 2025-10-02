// Create secure_health database and app user
db = db.getSiblingDB('secure_health');

// Create a user with permissions for the secure_health database
db.createUser({
    user: 'secure_user',
    pwd: 'secure_password',
    roles: [
        { role: 'readWrite', db: 'secure_health' },
        { role: 'dbAdmin', db: 'secure_health' }
    ]
});

// Create collections
db.createCollection('patient');
db.createCollection('audit_log');
db.createCollection('user');

// Create indexes
db.patient.createIndex({ "lastName": 1 });
db.patient.createIndex({ "birthDate": 1 });
db.audit_log.createIndex({ "timestamp": 1 });
db.audit_log.createIndex({ "userId": 1 });
db.audit_log.createIndex({ "action": 1 });
db.user.createIndex({ "email": 1 }, { unique: true });

// Create test users
db.user.insertMany([
    {
        email: 'doctor@example.com',
        password: '$2y$13$EYwD6hRCDnX76nfq/c3z9uea8Mv5Bj0Vmxt4NrpKP3lFGWHPZ6ffW', // 'doctor'
        roles: ['ROLE_DOCTOR'],
        firstName: 'John',
        lastName: 'Doe',
        createdAt: new Date()
    },
    {
        email: 'nurse@example.com',
        password: '$2y$13$8biXPW/QYv6VN3xM0U9Q8uS89OKu.FMH82mBfUbZS/iMfSltlTPEe', // 'nurse'
        roles: ['ROLE_NURSE'],
        firstName: 'Jane',
        lastName: 'Smith',
        createdAt: new Date()
    },
    {
        email: 'receptionist@example.com',
        password: '$2y$13$uGI7nLFfmzU0Yw9mzUU5WejzIJDH.r8GcqDJP4yT4OKkDBiYKI9Tm', // 'receptionist'
        roles: ['ROLE_RECEPTIONIST'],
        firstName: 'Robert',
        lastName: 'Johnson',
        createdAt: new Date()
    }
]);