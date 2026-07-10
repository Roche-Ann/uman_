# Fix for Service Requests Error: Invalid Request ID 0

## Problem
When clicking "View" on service requests, you get an error: "Invalid request ID: 0"

This happens because the `service_requests` table is missing the `AUTO_INCREMENT` definition, causing new records to be inserted with `ID = 0` instead of auto-incremented IDs.

## Solution

Run one of these SQL commands in your database:

### Option 1: Using fix_requests_table.php (Easiest - Web Interface)
1. Open your browser and go to: `http://localhost/system/api/fix_requests_table.php`
2. This will automatically:
   - Delete any invalid records with ID = 0
   - Fix the AUTO_INCREMENT setting
   - Verify all records are now valid

### Option 2: Manual SQL Update (Direct Database)
Run these commands in phpMyAdmin or MySQL CLI:

```sql
-- Step 1: Delete any invalid records with ID = 0
DELETE FROM service_requests WHERE id = 0;

-- Step 2: Drop and recreate PRIMARY KEY with AUTO_INCREMENT
ALTER TABLE service_requests DROP PRIMARY KEY;
ALTER TABLE service_requests MODIFY id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY;

-- Step 3: Set AUTO_INCREMENT to next available value
ALTER TABLE service_requests AUTO_INCREMENT = 7;
```

### Option 3: Complete Schema Fix (Most Thorough)
Run this comprehensive fix:

```sql
-- First, backup your existing data
-- Then execute:

-- Remove old indexes and primary key
ALTER TABLE service_requests 
  DROP KEY idx_user_id,
  DROP KEY idx_status,
  DROP KEY idx_request_type,
  DROP KEY idx_service_requests_created,
  DROP KEY idx_service_requests_user_status,
  DROP PRIMARY KEY;

-- Delete invalid records
DELETE FROM service_requests WHERE id <= 0;

-- Fix the id column to be AUTO_INCREMENT
ALTER TABLE service_requests 
  MODIFY id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ADD KEY idx_user_id (user_id),
  ADD KEY idx_status (status),
  ADD KEY idx_request_type (request_type),
  ADD KEY idx_service_requests_created (created_at),
  ADD KEY idx_service_requests_user_status (user_id, status);

-- Set AUTO_INCREMENT to start after the highest existing ID
ALTER TABLE service_requests AUTO_INCREMENT = 7;
```

## After Fixing

You should be able to:
1. ✓ Create new service requests without errors
2. ✓ View existing service requests without getting "Invalid request ID: 0"
3. ✓ All new requests will get proper auto-incremented IDs starting from 7

## Verification

Run this query to verify the fix worked:

```sql
SELECT * FROM service_requests WHERE id <= 0;
-- Should return: (no rows)

SHOW TABLE STATUS WHERE name='service_requests'\G
-- Should show: Auto_increment = 7 (or higher)
```

---

**Recommended:** Use Option 1 (fix_requests_table.php) as it's automated and safe!
