db = db.getSiblingDB('securehealth');

// Create collection for patients
db.createCollection('patients');

// Create users for different roles
db.createUser({
  user: 'doctor',
  pwd: 'doctor_password',
  roles: [
    { role: 'readWrite', db: 'securehealth' }
  ]
});

db.createUser({
  user: 'nurse',
  pwd: 'nurse_password',
  roles: [
    { role: 'read', db: 'securehealth' }
  ]
});

db.createUser({
  user: 'receptionist',
  pwd: 'receptionist_password',
  roles: [
    { role: 'read', db: 'securehealth' }
  ]
});

// Create user for application access
db.createUser({
  user: 'app_user',
  pwd: 'app_password',
  roles: [
    { role: 'readWrite', db: 'securehealth' }
  ]
});

// Initialize Client-Side Field Level Encryption (CSFLE) collections
// This is a placeholder - in a real application, you would set up the key vault and encryption configurations here
print("MongoDB initialized with users and encryption setup");