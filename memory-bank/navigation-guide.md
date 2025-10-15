# Navigation System Guide

## Overview
SecureHealth uses a dual navigation system to support both static HTML pages and Symfony-rendered pages, with comprehensive role-based access control ensuring users see only the features they're authorized to use.

## Navigation Files

### 1. JavaScript Navbar (`public/assets/js/navbar.js`)
**Purpose**: Dynamic navigation for static HTML pages

**Features**:
- Client-side role detection from localStorage
- Dynamic menu rendering based on user role
- Bootstrap 5 compatible
- Real-time message badge updates
- Auto-initialization on page load

**Usage**: Include in any HTML page:
```html
<link href="/assets/css/navbar.css" rel="stylesheet">
<script src="/assets/js/navbar.js"></script>
<div id="navbar-container"></div>
```

### 2. Twig Navbar (`templates/includes/navbar.html.twig`)
**Purpose**: Server-side navigation for Symfony pages

**Features**:
- Server-side role detection via Symfony Security
- Twig template syntax with `is_granted()` checks
- Bootstrap 4 compatible
- Session-based authentication state
- Integrated with Symfony routing

**Usage**: Include in any Twig template:
```twig
{% include 'includes/navbar.html.twig' %}
```

## Role-Based Navigation Structure

### Public (Unauthenticated)
```
┌─────────────────────────────────────────┐
│ SecureHealth                             │
├─────────────────────────────────────────┤
│ Home                                     │
│ Resources ▼                              │
│   ├─ Documentation                       │
│   ├─ Features                            │
│   ├─ Security                            │
│   └─ Encryption Demo                     │
│ [Login]                                  │
└─────────────────────────────────────────┘
```

### ROLE_ADMIN
```
┌─────────────────────────────────────────┐
│ SecureHealth        [@admin] [Logout]    │
├─────────────────────────────────────────┤
│ Home                                     │
│ Calendar                                 │
│ Patients ▼                               │
│   ├─ View All Patients                   │
│   └─ Add New Patient                     │
│ Admin ▼                                  │
│   ├─ Dashboard                           │
│   ├─ Demo Data                           │
│   ├─────────────                         │
│   ├─ Medical Knowledge                   │
│   ├─ Encryption Search                   │
│   ├─────────────                         │
│   └─ User Management                     │
│ Documentation                            │
│ Help                                     │
└─────────────────────────────────────────┘
```

**Admin Capabilities**:
- View audit logs (dashboard)
- Manage demo data
- Create/edit/delete medical knowledge
- Test encryption features
- Manage users (future)
- View basic patient info
- View/edit insurance info
- NO access to medical data (PHI compliance)

### ROLE_DOCTOR
```
┌─────────────────────────────────────────┐
│ SecureHealth      [@doctor] [Logout]     │
├─────────────────────────────────────────┤
│ Home                                     │
│ Calendar                                 │
│ Patients ▼                               │
│   ├─ View All Patients                   │
│   ├─ Add New Patient                     │
│   ├─────────────                         │
│   └─ Manage Patient Notes                │
│ Clinical Tools ▼                         │
│   ├─ Medical Knowledge                   │
│   ├─ Clinical Decision Support           │
│   ├─ Drug Interactions                   │
│   ├─ Treatment Guidelines                │
│   ├─ Diagnostic Criteria                 │
│   ├─────────────                         │
│   └─ Audit Logs                          │
│ Messages [3]                             │
│ Documentation                            │
│ Help                                     │
└─────────────────────────────────────────┘
```

**Doctor Capabilities**:
- Full patient access (including SSN, diagnoses, medications)
- Create/edit/delete patient records
- Add/edit/delete patient notes
- Access all medical knowledge tools
- Clinical decision support
- Drug interaction checking
- Treatment guidelines
- Diagnostic criteria
- View audit logs
- Send/receive messages with nurses and patients
- Schedule appointments

### ROLE_NURSE
```
┌─────────────────────────────────────────┐
│ SecureHealth       [@nurse] [Logout]     │
├─────────────────────────────────────────┤
│ Home                                     │
│ Calendar                                 │
│ Patients ▼                               │
│   ├─ View All Patients                   │
│   ├─ Add New Patient                     │
│   ├─────────────                         │
│   └─ View Patient Notes                  │
│ Medical Tools ▼                          │
│   ├─ Drug Interactions                   │
│   └─ Medical Knowledge (View)            │
│ Messages [1]                             │
│ Documentation                            │
│ Help                                     │
└─────────────────────────────────────────┘
```

**Nurse Capabilities**:
- View patient info (except SSN)
- Create patient records
- Edit basic patient info
- View patient notes (read-only)
- View diagnoses and medications (read-only)
- Drug interaction checking
- View medical knowledge (read-only)
- Send/receive messages with doctors and patients
- Schedule appointments
- View insurance info

### ROLE_RECEPTIONIST
```
┌─────────────────────────────────────────┐
│ SecureHealth [@receptionist] [Logout]    │
├─────────────────────────────────────────┤
│ Home                                     │
│ Calendar                                 │
│ Patients ▼                               │
│   ├─ View All Patients                   │
│   ├─ Add New Patient                     │
│   ├─────────────                         │
│   └─ Scheduling                          │
│ Scheduling                               │
│ Documentation                            │
│ Help                                     │
└─────────────────────────────────────────┘
```

**Receptionist Capabilities**:
- View basic patient demographics
- Create patient records
- Edit contact information
- View/edit insurance information
- Appointment scheduling
- NO access to medical data (diagnoses, medications, notes)

### ROLE_PATIENT (Patient Portal)
```
┌─────────────────────────────────────────┐
│ SecureHealth Portal  [@patient] [Logout] │
├─────────────────────────────────────────┤
│ Dashboard                                │
│ My Health Records                        │
│ Messages                                 │
│ Appointments                             │
│ Contact Information                      │
└─────────────────────────────────────────┘
```

**Patient Capabilities**:
- View own medical records
- Update own contact information
- Send messages to healthcare providers
- View appointment history
- Request appointments (future)
- View test results (future)

## Navigation Implementation Details

### Dropdown Menu Structure
All dropdowns use Bootstrap's dropdown component with:
- Clear visual hierarchy
- Font Awesome icons for recognition
- Dividers to group related items
- Active state highlighting
- Hover effects for usability

### Icon Mapping
Consistent icons across all navigation items:
- 🏠 `fa-home` - Home
- 📅 `fa-calendar-alt` - Calendar
- 👥 `fa-users` - Patients
- 🩺 `fa-stethoscope` - Clinical Tools (Doctors)
- 💼 `fa-medkit` - Medical Tools (Nurses)
- ⚙️ `fa-cog` - Admin
- 📧 `fa-envelope` - Messages
- 📖 `fa-book` - Documentation
- ❓ `fa-question-circle` - Help
- 📊 `fa-tachoscope-alt` - Dashboard
- 🗄️ `fa-database` - Demo Data
- 🔒 `fa-lock` - Encryption
- 📝 `fa-notes-medical` - Patient Notes
- 💊 `fa-pills` - Drug Interactions
- 🧠 `fa-brain` - Clinical Decision Support
- 🔬 `fa-microscope` - Diagnostics
- 📋 `fa-clipboard-list` - Treatment Guidelines
- 📚 `fa-book-medical` - Medical Knowledge
- 📄 `fa-file-alt` - Audit Logs

### Role Detection Logic

**JavaScript (Static Pages)**:
```javascript
// User data stored in localStorage after login
const user = JSON.parse(localStorage.getItem('securehealth_user'));

// Role detection
this.isAdmin = user.roles.includes('ROLE_ADMIN');
this.isDoctor = user.roles.includes('ROLE_DOCTOR');
this.isNurse = user.roles.includes('ROLE_NURSE');
this.isReceptionist = user.roles.includes('ROLE_RECEPTIONIST');
```

**Twig (Symfony Pages)**:
```twig
{% if is_granted('ROLE_DOCTOR') %}
    {# Doctor-specific menu items #}
{% endif %}
```

### Message Badge Updates
Real-time unread message count for doctors and nurses:

**JavaScript**:
```javascript
// Polls every 15 seconds
fetch('/api/conversations/inbox/unread-count')
    .then(r => r.json())
    .then(j => {
        const count = j.count || 0;
        badge.textContent = count > 0 ? String(count) : '';
    });
```

**Twig**:
```twig
<script>
function refreshNavUnread(){
    fetch('/api/conversations/inbox/unread-count')
        .then(r=>r.json()).then(j=>{
            const n = j.count || 0;
            document.getElementById('navMessagesBadge').textContent = 
                n > 0 ? String(n) : '';
        });
}
refreshNavUnread();
setInterval(refreshNavUnread, 15000);
</script>
```

## Navigation Maintenance

### Adding New Menu Items

**For JavaScript Navbar**:
1. Edit `public/assets/js/navbar.js`
2. Find `getRoleBasedNavItems()` method
3. Add menu item in appropriate role section:
```javascript
if (this.isDoctor) {
    items += `
        <li class="nav-item">
            <a class="nav-link" href="/new-feature.html">
                <i class="fas fa-icon me-1"></i>New Feature
            </a>
        </li>
    `;
}
```

**For Twig Navbar**:
1. Edit `templates/includes/navbar.html.twig`
2. Add menu item with appropriate role check:
```twig
{% if is_granted('ROLE_DOCTOR') %}
    <li class="nav-item">
        <a class="nav-link" href="{{ path('new_feature') }}">
            <i class="fas fa-icon mr-1"></i>New Feature
        </a>
    </li>
{% endif %}
```

### Testing Navigation
1. Create test user for each role
2. Login as each user
3. Verify visible menu items match role permissions
4. Click each menu item to verify it works
5. Check that unauthorized items are hidden
6. Test dropdown functionality
7. Verify active states highlight correctly

### Common Issues

**Issue**: Menu item visible to wrong role
**Solution**: Check role detection logic and `is_granted()` conditions

**Issue**: Dropdown not working
**Solution**: Verify Bootstrap JS is loaded and `data-bs-toggle="dropdown"` is set

**Issue**: Message badge not updating
**Solution**: Check API endpoint is accessible and interval is running

**Issue**: Icons not showing
**Solution**: Verify Font Awesome CSS is loaded

## Accessibility Considerations

1. **Keyboard Navigation**: All menu items accessible via keyboard
2. **Screen Readers**: Proper ARIA labels on dropdowns
3. **Color Contrast**: Sufficient contrast for readability
4. **Focus States**: Visible focus indicators
5. **Semantic HTML**: Proper use of `<nav>`, `<ul>`, `<li>` elements

## Performance Notes

- JavaScript navbar renders client-side (fast, no server delay)
- Twig navbar renders server-side (secure, guaranteed correct permissions)
- Dropdown menus lazy-load (no performance impact)
- Message badge polling is throttled (15-second intervals)
- Role detection cached in memory (no repeated checks)

## Future Enhancements

1. **Breadcrumb Navigation**: Show current location hierarchy
2. **Search in Nav**: Quick search from navbar
3. **Favorites**: Pin frequently used items
4. **Recent Items**: Show recently accessed pages
5. **Mobile Menu**: Improved mobile navigation experience
6. **Keyboard Shortcuts**: Quick access via keyboard
7. **Customization**: Allow users to customize their menu
8. **Notifications Hub**: Centralized notification center

