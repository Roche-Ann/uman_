# UMAN to LGU1 Energy monthly-record integration

UMAN exposes its locally owned monthly electricity records through:

```text
GET /api/monthly-energy-records.php
X-API-Key: <UMAN_INTEGRATION_API_KEY>
```

The API also accepts the key as `?key=...` for compatibility with the other
UMAN endpoints. Supported query parameters are `page`, `per_page` (maximum
200), `year`, `month`, and `updated_since`.

Only rows with `source_system=CPRF` and a valid `cprf_facility_id` are
exported. UMAN retains the analytics view, while LGU1 Energy attaches the
monthly record to its matching CPRF-mirrored facility.

For local XAMPP installations, LGU1 Energy normally uses:

```dotenv
UMAN_MONTHLY_RECORDS_URL=https://uman.infragovservices.com/api/monthly-energy-records.php
UMAN_INTEGRATION_API_KEY=UMAN_SECURE_KEY_2025
```

Change the URL if the UMAN folder has a different Apache alias. Both systems
must use the same integration API key.
