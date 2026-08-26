# LGU Urban Planning and Development System

A comprehensive PHP-based system for Local Government Unit (LGU) Urban Planning and Development management.

## Features

### 1. User & Access Management Module
- User accounts with roles: Admin, Zoning Officer, Building Official, Assessor, Applicant
- Authentication (login/logout)
- Registration for applicants
- Role-based permission control
- Account management (create/update/deactivate users)
- Activity logs (approve, upload, edit, delete)
- Audit trail of actions
- Admin view of logs

### 2. Applicant Self-Service Module
- Submit development permit application
- Application form (project details)
- Upload required documents (site plan, lot plan, ownership proof)
- Geo-tagging / lot location selection on map
- Track application status (real-time)
- Timeline history of application
- Applicant notifications (Email, SMS optional, In-app alerts)
- Messaging panel between applicant & LGU officer
- View zoning compliance reports
- View permit results and downloadable files

### 3. GIS Mapping & Zoning Analysis Module
- Interactive map viewer (Leaflet)
- Multi-layer support:
  - Zoning classifications (R1, R2, C1, Industrial, etc.)
  - Land use map
  - Hazard maps (flood, landslide)
  - Parcel/tax mapping layers
- Search tools:
  - Lot number
  - Barangay
  - Coordinates
  - Assessor's parcel ID
- Information popup per parcel
- Measurement tools (area, buffer, distance)
- Automated zoning compliance checking:
  - Determine zoning classification of parcel
  - Check allowed land uses
  - Validate height limits
  - Setbacks
  - Density limits
  - Floor Area Ratio (FAR)
- Auto-generation of:
  - Compliance result
  - Allowed uses
  - Restrictions
  - Zoning Compliance Report (ZCR)

### 4. Permit Processing & Workflow Module
- Application intake dashboard (for LGU staff)
- View applicant-submitted documents
- Cross-check GIS zoning compliance results
- Add remarks, comments, and request revisions
- Approve / Return / Reject application
- Assign/reassign application to officers
- Track permit processing stages
- Inspection scheduling (optional)
- Fee computation (optional)
- Internal staff notifications
- Generate Development Permit (PDF)
- Monitoring tools for LGU:
  - Application tracker
  - Status history
  - Processing timelines

### 5. Document & Report Management Module
- Upload / preview / download documents
- Automatic file categorization per application
- Version control (when applicant resubmits)
- Secure file storage with role-based access
- Generate:
  - Zoning Compliance Report
  - Development Permit (PDF)
  - Inspection report (optional)
- System analytics:
  - Number of permits issued
  - Application summary
  - Zoning compliance statistics
  - Monthly & annual analytics
- Export reports to:
  - PDF
  - CSV
  - Excel

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP/LAMP (for local development)

### Setup Steps

1. **Database Setup**
   ```bash
   # Import the database schema
   mysql -u root -p < database/schema.sql
   ```
   Or use phpMyAdmin to import `database/schema.sql`

2. **Configuration**
   - Edit `config/database.php` with your database credentials
   - Edit `config/app.php` with your application settings
   - Update `base_url` in `config/app.php` to match your installation path

3. **Directory Permissions**
   ```bash
   # Create upload directories
   mkdir -p uploads/documents
   mkdir -p uploads/permits
   mkdir -p uploads/reports
   
   # Set permissions (Linux/Mac)
   chmod -R 755 uploads/
   ```

4. **Web Server Configuration**
   - Point your web server document root to the project directory
   - Ensure mod_rewrite is enabled (if using Apache)
   - For XAMPP, place the project in `htdocs/lgu-urban-planning/`

5. **Default Login Credentials**
   - Username: `admin`
   - Password: `admin123`
   - **Change this immediately after first login!**

## File Structure

```
lgu-urban-planning/
├── assets/
│   └── js/
│       └── main.js
├── config/
│   ├── app.php
│   └── database.php
├── core/
│   ├── Auth.php
│   ├── Database.php
│   └── Helper.php
├── database/
│   └── schema.sql
├── modules/
│   ├── ApplicantSelfService/
│   │   └── ApplicantController.php
│   ├── DocumentReportManagement/
│   │   └── DocumentController.php
│   ├── GISMapping/
│   │   └── GISController.php
│   ├── PermitProcessing/
│   │   └── PermitController.php
│   └── UserAccessManagement/
│       └── UserController.php
├── views/
│   ├── dashboard.php
│   ├── footer.php
│   └── header.php
├── admin/
│   ├── audit-logs.php
│   └── users.php
├── applicant/
│   ├── apply.php
│   ├── applications.php
│   ├── messages.php
│   └── view.php
├── documents/
│   └── download.php
├── gis/
│   └── map.php
├── permit/
│   ├── applications.php
│   └── view.php
├── reports/
│   ├── export.php
│   └── index.php
├── access-denied.php
├── index.php
├── login.php
├── logout.php
├── register.php
└── README.md
```

## Usage

### For Applicants
1. Register an account at `/register.php`
2. Login at `/login.php`
3. Submit a development permit application
4. Upload required documents
5. Track application status
6. Receive notifications and messages

### For LGU Staff
1. Login with your assigned credentials
2. View applications in the dashboard
3. Check zoning compliance using GIS map
4. Process applications and update status
5. Generate permits for approved applications
6. Generate reports and analytics

### For Administrators
1. Manage users and roles
2. View audit logs
3. Generate system reports
4. Configure system settings

## Security Notes

- All passwords are hashed using PHP's `password_hash()` function
- SQL injection protection using prepared statements
- XSS protection using `htmlspecialchars()`
- Role-based access control implemented
- Session management for authentication
- File upload validation

## Development Notes

- This is a basic implementation. For production use, consider:
  - Adding email notifications (SMTP configuration in `config/app.php`)
  - Implementing SMS notifications
  - Adding PDF generation library (TCPDF/FPDF) for permits
  - Enhancing GIS mapping with more layers
  - Adding file validation and virus scanning
  - Implementing rate limiting
  - Adding CSRF protection
  - Using environment variables for sensitive configuration

## License

This project is provided as-is for educational and development purposes.

## Support

For issues or questions, please refer to the documentation or contact your system administrator.

