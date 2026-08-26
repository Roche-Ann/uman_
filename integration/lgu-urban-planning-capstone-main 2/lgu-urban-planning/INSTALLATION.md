# Installation Guide

## Quick Start

### Step 1: Database Setup

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database or use existing MySQL
3. Import the file: `database/schema.sql`
   - Click on "Import" tab
   - Choose file: `database/schema.sql`
   - Click "Go"

### Step 2: Configure Database

Edit `config/database.php`:
```php
'host' => 'localhost',
'dbname' => 'lgu_urban_planning',
'username' => 'root',        // Change if needed
'password' => '',            // Add your MySQL password
```

### Step 3: Configure Application

Edit `config/app.php`:
```php
'base_url' => 'http://localhost/lgu-urban-planning',  // Update if different
```

### Step 4: Set Permissions

The upload directories are already created. Ensure they are writable:
- `uploads/documents/`
- `uploads/permits/`
- `uploads/reports/`

### Step 5: Access the System

1. Open browser: http://localhost/lgu-urban-planning
2. Default admin login:
   - Username: `admin`
   - Password: `admin123`
3. **IMPORTANT**: Change the admin password immediately!

## Testing the System

### As Applicant:
1. Go to Register page
2. Create an applicant account
3. Login and submit an application
4. Upload documents
5. Track application status

### As Staff:
1. Login as admin
2. Create staff users (Zoning Officer, Building Official)
3. View applications
4. Check zoning compliance
5. Process applications

## Troubleshooting

### Database Connection Error
- Check MySQL is running
- Verify credentials in `config/database.php`
- Ensure database `lgu_urban_planning` exists

### File Upload Error
- Check `uploads/` directory permissions
- Verify PHP upload settings in `.htaccess`
- Check `config/app.php` for upload path

### Page Not Found
- Verify `base_url` in `config/app.php`
- Check Apache mod_rewrite is enabled
- Ensure `.htaccess` file exists

### Session Issues
- Check PHP session directory is writable
- Verify session settings in PHP configuration

## Next Steps

1. Configure email settings in `config/app.php` for notifications
2. Add real GIS layer data to `gis_layers` table
3. Import actual parcel data to `parcels` table
4. Customize zoning classifications in `zoning_classifications` table
5. Set up SSL certificate for production

## Support

For issues, check:
- PHP error logs
- Apache error logs
- Database error logs
- Browser console for JavaScript errors

