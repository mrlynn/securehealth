# SecureHealth Command Reference

## Quick Reference

### Main Command
```bash
php bin/console securehealth [OPTIONS]
```

### Options
- `-c, --command COMMAND` - Run specific command
- `-i, --interactive` - Interactive mode
- `--help` - Show help

### Available Commands

| Command | Description | Example |
|---------|-------------|---------|
| `setup` | Complete system setup | `securehealth -c setup` |
| `users` | User management | `securehealth -c users` |
| `patients` | Patient data management | `securehealth -c patients` |
| `medical-knowledge` | Medical knowledge base | `securehealth -c medical-knowledge` |
| `encryption` | Encryption utilities | `securehealth -c encryption` |
| `database` | Database operations | `securehealth -c database` |
| `validation` | System validation | `securehealth -c validation` |
| `status` | System status | `securehealth -c status` |
| `help` | Detailed help | `securehealth -c help` |

## Quick Examples

### System Setup
```bash
# Complete system setup
php bin/console securehealth -c setup

# Interactive mode
php bin/console securehealth --interactive
```

### Data Management
```bash
# Generate sample patients
php bin/console securehealth -c patients

# Seed medical knowledge
php bin/console securehealth -c medical-knowledge
```

### System Maintenance
```bash
# Check system health
php bin/console securehealth -c validation

# View system status
php bin/console securehealth -c status

# Debug encryption
php bin/console securehealth -c encryption
```

### Database Operations
```bash
# Reset database (⚠️ WARNING: Deletes all data)
php bin/console securehealth -c database

# Show database stats
php bin/console securehealth -c database
```

## Integration with Existing Commands

The SecureHealth tool integrates with existing commands:

```bash
# Direct command usage (still available)
php bin/console app:create-users
php bin/console app:generate-patient-data --count=50
php bin/console app:seed-medical-knowledge --count=100
php bin/console app:initialize-encryption
php bin/console app:debug-encryption
```

## Troubleshooting

### Common Commands
```bash
# Show all available commands
php bin/console list

# Show help for specific command
php bin/console help securehealth

# Check Symfony version
php bin/console --version
```

### System Checks
```bash
# Validate system
php bin/console securehealth -c validation

# Check status
php bin/console securehealth -c status

# Show detailed help
php bin/console securehealth -c help
```
