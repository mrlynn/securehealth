# SecureHealth Command-Line Tool

The SecureHealth command-line tool provides comprehensive management of your HIPAA-compliant medical records system through a unified interface.

## Quick Start

```bash
# Show main menu
php bin/console securehealth

# Run in interactive mode
php bin/console securehealth --interactive

# Run specific command
php bin/console securehealth -c setup
```

## Available Commands

### 🚀 Setup Command
Complete system setup and initialization

```bash
php bin/console securehealth -c setup
```

**What it does:**
- Creates MongoDB schema
- Creates default users (Doctor, Nurse, Receptionist, Admin)
- Generates sample patient data (10 patients)
- Seeds medical knowledge base (20 entries)
- Runs system validation

### 👥 User Management
User management operations

```bash
php bin/console securehealth -c users
```

**Available operations:**
- Create default users
- List all users
- Create admin user
- Manage user roles

### 📋 Patient Management
Patient data management operations

```bash
php bin/console securehealth -c patients
```

**Available operations:**
- Generate sample patient data
- Show patient count
- Export patient data
- Import patient data

### 🧠 Medical Knowledge Management
Medical knowledge base operations

```bash
php bin/console securehealth -c medical-knowledge
```

**Available operations:**
- Seed medical knowledge base
- Show knowledge base statistics
- Search medical knowledge

### 🔐 Encryption Utilities
Encryption utilities and debugging

```bash
php bin/console securehealth -c encryption
```

**Available operations:**
- Initialize encryption
- Debug encryption configuration
- Test encryption/decryption
- Check encryption status

### 🗄️ Database Operations
Database management operations

```bash
php bin/console securehealth -c database
```

**Available operations:**
- Reset database (⚠️ WARNING: Deletes all data)
- Create database backup
- Restore from backup
- Show database statistics

### ✅ System Validation
System validation and health checks

```bash
php bin/console securehealth -c validation
```

**Validates:**
- Database connection
- User authentication system
- API endpoints
- Encryption service
- Medical knowledge base
- File permissions

### 📊 System Status
Display current system status and statistics

```bash
php bin/console securehealth -c status
```

**Shows:**
- Application version
- PHP and Symfony versions
- Environment information
- Database status
- Encryption status
- User count
- Patient count
- Medical knowledge entries count

### 📚 Help
Show detailed help for any command

```bash
php bin/console securehealth -c help
```

## Usage Examples

### Complete System Setup
```bash
php bin/console securehealth -c setup
```

### Generate Sample Data
```bash
# Generate 50 patients
php bin/console securehealth -c patients

# Seed 100 medical knowledge entries
php bin/console securehealth -c medical-knowledge
```

### System Maintenance
```bash
# Run health checks
php bin/console securehealth -c validation

# Check system status
php bin/console securehealth -c status

# Debug encryption
php bin/console securehealth -c encryption
```

### Interactive Mode
```bash
php bin/console securehealth --interactive
```

Interactive mode provides a menu-driven interface where you can select operations from a list.

## Command Options

| Option | Short | Description |
|--------|-------|-------------|
| `--command` | `-c` | Run specific command |
| `--interactive` | `-i` | Run in interactive mode |

## Integration with Existing Commands

The SecureHealth command-line tool integrates with existing Symfony console commands:

- `app:create-users` - User creation
- `app:generate-patient-data` - Patient data generation
- `app:seed-medical-knowledge` - Medical knowledge seeding
- `app:initialize-encryption` - Encryption initialization
- `app:debug-encryption` - Encryption debugging
- `doctrine:mongodb:schema:create` - Database schema creation
- `doctrine:mongodb:schema:drop` - Database schema deletion

## Error Handling

The tool provides comprehensive error handling and validation:

- **Database Connection Errors**: Graceful handling of MongoDB connection issues
- **Permission Errors**: Clear messages for file permission problems
- **Validation Failures**: Detailed reporting of system validation issues
- **Command Failures**: Proper error codes and messages for failed operations

## Security Considerations

- **Database Reset**: Requires explicit confirmation before deleting data
- **User Management**: Proper role-based access control
- **Encryption**: Secure handling of encryption keys and configuration
- **Audit Logging**: All operations are logged for compliance

## Troubleshooting

### Common Issues

1. **Command Not Found**
   ```bash
   # Ensure you're in the project root directory
   cd /path/to/securehealth
   php bin/console securehealth
   ```

2. **Database Connection Issues**
   ```bash
   # Check database status
   php bin/console securehealth -c status
   
   # Run validation
   php bin/console securehealth -c validation
   ```

3. **Permission Issues**
   ```bash
   # Check file permissions
   php bin/console securehealth -c validation
   ```

### Getting Help

```bash
# Show main help
php bin/console securehealth -c help

# Show command-specific help
php bin/console securehealth -c [command-name]

# Show Symfony help
php bin/console help securehealth
```

## Development

To extend the command-line tool:

1. Add new commands to the `$availableCommands` array
2. Implement the command logic in `runSpecificCommand()`
3. Add validation methods for new features
4. Update documentation

## Best Practices

1. **Always run validation** after system changes
2. **Use interactive mode** for complex operations
3. **Check system status** before performing maintenance
4. **Backup data** before major operations
5. **Review logs** for troubleshooting

## Compliance

The command-line tool maintains HIPAA compliance by:

- **Audit Logging**: All operations are logged
- **Access Control**: Role-based permissions
- **Data Encryption**: Secure data handling
- **Validation**: Comprehensive system checks
- **Documentation**: Complete operation tracking
