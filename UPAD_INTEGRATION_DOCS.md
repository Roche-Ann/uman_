# UMAN–Urban Planning API Integration Guide

This document defines the official production specification for integrating the **Urban Planning System (UPAD)** with the **UMAN Utilities & Infrastructure Management System**.

---

## 1. API Endpoint Overview

| Parameter | Specification |
| :--- | :--- |
| **Endpoint URL** | `https://uman.infragovservices.com/api/v1/inspection-requests.php` |
| **HTTP Method** | `POST` |
| **Format** | `application/json` |
| **Authentication** | Bearer Token |

---

## 2. Authentication

Requests must include a Bearer Token in the `Authorization` header.

### Request Headers
```http
Authorization: Bearer UPAD_UMAN_INTEGRATION_KEY_2026
Content-Type: application/json
```

> **Security Note:** The integration key must be stored securely in server environment configuration (`UPAD_INTEGRATION_KEY`). Do not hardcode or expose this token in public client repositories.

---

## 3. Request Format

Submit inspection requests using a JSON payload:

```json
{
  "inspection_id": "UP-2026-001",
  "coverage": "Fully Covered",
  "asset_health": 90,
  "capacity": "Normal",
  "incident_count": 0
}
```

### Data Fields & Validation Rules

| Field | Type | Required | Allowed / Expected Values | Scoring Mapping |
| :--- | :--- | :--- | :--- | :--- |
| `inspection_id` | String | Yes | Unique string identifier | Primary Idempotency Key |
| `coverage` | String | Yes | `Fully Covered`<br/>`Partially Covered`<br/>`Not Covered` | `Fully Covered` = 100<br/>`Partially Covered` = 50<br/>`Not Covered` = 0 |
| `asset_health` | Numeric | Yes | `0` to `100` | Direct percentage score ($0–100$) |
| `capacity` | String | Yes | `Normal`<br/>`Near Capacity`<br/>`Overloaded` | `Normal` = 100<br/>`Near Capacity` = 60<br/>`Overloaded` = 20 |
| `incident_count` | Integer | Yes | $\ge 0$ | `0` incidents = 100<br/>`1–2` incidents = 70<br/>`> 2` incidents = 30 |

---

## 4. Evaluation & Scoring Logic

The overall evaluation score is calculated using configured factor weights:

$$\text{Score} = \text{round}\left( (\text{coverage\_score} \times 0.30) + (\text{asset\_health\_score} \times 0.30) + (\text{capacity\_score} \times 0.20) + (\text{incident\_score} \times 0.20) \right)$$

### Decision Matrix

| Score Range | Decision | Description |
| :--- | :--- | :--- |
| $\ge 80$ | `Approved` | High grid feasibility and infrastructure readiness. |
| $50 \le \text{Score} < 80$ | `Conditional` | Moderate feasibility; grid monitoring or minor upgrade required. |
| $< 50$ | `Rejected` | High infrastructure risk or overloaded grid capacity. |

---

## 5. API Response Formats

### 5.1 Success Response (HTTP 200 OK)

```json
{
  "success": true,
  "message": "Inspection request processed successfully",
  "data": {
    "inspection_id": "UP-2026-001",
    "score": 97,
    "decision": "Approved",
    "factors": {
      "coverage": 100,
      "asset_health": 90,
      "capacity": 100,
      "incidents": 100
    },
    "processed_at": "2026-08-27T01:30:00+08:00"
  }
}
```

### 5.2 Idempotent / Duplicate Request Response (HTTP 200 OK)

If an `inspection_id` has already been processed, the system returns the existing evaluation result without duplicating records:

```json
{
  "success": true,
  "message": "Inspection request already processed",
  "data": {
    "inspection_id": "UP-2026-001",
    "score": 97,
    "decision": "Approved",
    "factors": {
      "coverage": 100,
      "asset_health": 90,
      "capacity": 100,
      "incidents": 100
    },
    "processed_at": "2026-08-27T01:30:00+08:00"
  }
}
```

### 5.3 Error Responses

#### Missing or Invalid Authentication (HTTP 401 Unauthorized)
```json
{
  "success": false,
  "error": "Unauthorized — invalid or missing Bearer token"
}
```

#### Invalid / Missing Required Field (HTTP 400 Bad Request)
```json
{
  "success": false,
  "error": "Missing required field: coverage"
}
```
or
```json
{
  "success": false,
  "error": "Invalid asset_health. Value must be between 0 and 100."
}
```

#### Server Error (HTTP 500 Internal Server Error)
```json
{
  "success": false,
  "error": "Unable to process inspection request"
}
```

---

## 6. HTTP Status Codes Summary

| Code | Status | Trigger Condition |
| :--- | :--- | :--- |
| `200` | OK | Request processed successfully or duplicate result returned. |
| `400` | Bad Request | Missing field or invalid input parameter. |
| `401` | Unauthorized | Missing or invalid Bearer authorization token. |
| `405` | Method Not Allowed | Called with HTTP method other than `POST` (or `GET` for admin). |
| `500` | Server Error | Internal processing or database exception. |

---

## 7. cURL Testing Instructions

### Test Scenario 1: Valid Approved Request

```bash
curl -X POST \
  "https://uman.infragovservices.com/api/v1/inspection-requests.php" \
  -H "Authorization: Bearer UPAD_UMAN_INTEGRATION_KEY_2026" \
  -H "Content-Type: application/json" \
  -d '{
    "inspection_id": "UP-2026-001",
    "coverage": "Fully Covered",
    "asset_health": 90,
    "capacity": "Normal",
    "incident_count": 0
  }'
```

### Test Scenario 2: Test Invalid Authentication (Missing/Wrong Token)

```bash
curl -X POST \
  "https://uman.infragovservices.com/api/v1/inspection-requests.php" \
  -H "Authorization: Bearer INVALID_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "inspection_id": "UP-2026-002",
    "coverage": "Fully Covered",
    "asset_health": 80,
    "capacity": "Normal",
    "incident_count": 0
  }'
```

### Test Scenario 3: Test Validation Error (Invalid asset_health)

```bash
curl -X POST \
  "https://uman.infragovservices.com/api/v1/inspection-requests.php" \
  -H "Authorization: Bearer UPAD_UMAN_INTEGRATION_KEY_2026" \
  -H "Content-Type: application/json" \
  -d '{
    "inspection_id": "UP-2026-003",
    "coverage": "Fully Covered",
    "asset_health": 150,
    "capacity": "Normal",
    "incident_count": 0
  }'
```
