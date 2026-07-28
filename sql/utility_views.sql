-- sql/utility_views.sql

-- 1. Create aggregated assets view
CREATE OR REPLACE VIEW `aggregated_assets_view` AS
SELECT 
  COUNT(id) as total_assets,
  SUM(CASE WHEN condition_status = 'Operational' THEN 1 ELSE 0 END) as operational_assets,
  SUM(CASE WHEN condition_status = 'Damaged' THEN 1 ELSE 0 END) as damaged_assets,
  SUM(CASE WHEN condition_status = 'Needs Inspection' THEN 1 ELSE 0 END) as inspection_assets
FROM utility_assets;

-- 2. Create aggregated incidents view
CREATE OR REPLACE VIEW `aggregated_incidents_view` AS
SELECT 
  COUNT(id) as total_incidents,
  SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted_incidents,
  SUM(CASE WHEN status = 'Under Review' THEN 1 ELSE 0 END) as review_incidents,
  SUM(CASE WHEN status = 'Forwarded to Maintenance System' THEN 1 ELSE 0 END) as forwarded_incidents,
  SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as resolved_incidents
FROM utility_incidents;

-- 3. Create aggregated maintenance view
CREATE OR REPLACE VIEW `aggregated_maintenance_view` AS
SELECT 
  COUNT(id) as total_requests,
  SUM(CASE WHEN status = 'Created' THEN 1 ELSE 0 END) as pending_requests,
  SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as progress_requests,
  SUM(CASE WHEN status IN ('Completed', 'Closed') THEN 1 ELSE 0 END) as completed_requests,
  SUM(CASE WHEN COALESCE(priority, '') = 'Emergency' THEN 1 ELSE 0 END) as emergency_requests
FROM maintenance_requests;

-- 4. Create aggregated energy view
CREATE OR REPLACE VIEW `aggregated_energy_view` AS
SELECT 
  COALESCE(SUM(consumption_kwh), 0) as total_consumption,
  COALESCE(SUM(cost), 0) as total_cost,
  COUNT(id) as total_records
FROM energy_consumption_records;


