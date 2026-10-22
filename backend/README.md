# Sun Son Solar backend API

This is a standalone PHP 8.2+ / MySQLi backend. All API responses are JSON. Endpoints use `backend/api/index.php?action=ACTION`; send JSON request bodies (form fields are also accepted). Authentication uses a PHP session cookie. The API refreshes account status and employee role from the database on each authenticated request. Set HTTPS in production so the session cookie is Secure; cookies are HttpOnly and SameSite=Strict.

## Setup and database safety

1. Use a disposable local MariaDB/MySQL database first. For a new database, apply the base schema in step 2 first. Select the intended database, then run `database/000_preflight.sql`. Review every result and resolve duplicate usernames/emails and schema drift manually before proceeding. This workspace has no database connection, so current production/local row counts and duplicate results are unknown.
2. For a new database only, apply the existing base schema `database/user_db (1).sql`. For an existing database, inspect its actual schema and data; do not re-import the dump over it.
3. Review and apply `database/002_backend_requirements.sql` once, followed by `database/003_operations_and_payments.sql` once. These are incremental and do not drop tables, but contain non-idempotent ALTER statements. Do not rerun after partial application; inspect and repair the affected schema first. The unique constraints in 002 are per legacy account table; API customer/employee creation checks all account tables, but legacy tables cannot provide a single global unique constraint.
4. Configure PHP process environment variables `SUNSOLAR_DB_HOST`, `SUNSOLAR_DB_PORT`, `SUNSOLAR_DB_NAME`, `SUNSOLAR_DB_USER`, and `SUNSOLAR_DB_PASSWORD`. No database secrets are stored in source. PHP needs MySQLi with mysqlnd (`get_result`).
5. Serve `api/index.php` with PHP. Apply migrations manually in a local disposable environment after review. No migration has been executed by this implementation.

`000_preflight.sql` reports columns and duplicate identities. A unique-index migration will fail if existing duplicates remain. The legacy SQL dump contains plaintext sample admin credentials; the API deliberately requires password hashes. An authorized operator must rotate those accounts to `password_hash()` values before admin login will work. The API creates hashed employee/customer passwords and never returns hashes.

## Authentication and endpoint contract

Authenticated endpoints require the session cookie returned by `login`. The user ID comes only from the session; request-supplied account IDs are used only where explicitly documented as an admin/dispatcher target. Status codes are 200 for success, 201 for creation, 401 for unauthenticated/invalid credentials, 403 for insufficient role, 404 for missing resources, 409 for conflicts/duplicates, 422 for invalid input, 405 for wrong method, and 503 for unavailable/busy database operations. Error bodies use `{"error":"..."}`; successful bodies are JSON objects described below.

| Method and path | Authentication and permission | Request fields | Response |
| --- | --- | --- | --- |
| `POST ?action=register` | Public; customer only | `First_Name`, `Middle_Name`, `Last_Name`, `Birth_date` (`YYYY-MM-DD`), `Gender`, `Email`, `Phone_Number`, `Address`, `Username`, `Password` | 201 `{message}`. Validates email, date, phone, username and 10-character minimum password; stores a hash. |
| `POST ?action=login` | Public | `Username`, `Password` | 200 `{user:{id,username,role}}`; rejects inactive accounts. |
| `POST ?action=logout` | Session optional | None | 200 `{message}`; destroys the session. |
| `GET ?action=me` | Any authenticated role | None | 200 `{user:{id,username,role}}`. |
| `GET ?action=services` | Public | None | 200 `{services:[...]}` with active service records. |
| `POST ?action=service-manage` | Admin | `name`, `category`, `description`, optional `price` | 201 `{service_id}`. Creates an active service. |
| `PUT ?action=service-manage` | Admin | `service_id`, `name`, `category`, `description`, `is_active`, optional `price` | 200 `{service_id,is_active}`. Deactivates by flag; preserves history. |
| `GET ?action=service-availability&service_id=N` | Public | Query `service_id` | 200 `{availability:[...]}`. |
| `PUT ?action=service-availability` | Admin | `service_id`, `day_of_week` (0 Sunday�6 Saturday), `opens_at`, `closes_at` (`HH:MM:SS`), `is_available` | 200 `{message}`; upserts the weekly window. |
| `GET ?action=profile` | Customer | None | 200 `{profile:{...}}`; returns only the authenticated customer's fields. |
| `PUT ?action=profile` | Customer | Any permitted subset of `First_Name`, `Middle_Name`, `Last_Name`, `Birth_date`, `Gender`, `Email`, `Phone_Number`, `Address`, `Username` | 200 `{message}`. Role and status cannot be changed. |
| `POST ?action=password` | Customer | `current_password`, `new_password` | 200 `{message}`; verifies current password, hashes the new password, and rotates session ID. |
| `GET ?action=employees` | Admin or HR | None | 200 `{employees:[...]}` without password hashes. |
| `POST ?action=employees` | Admin or HR | Customer fields plus `Department`; optional `role` only for admin | 201 `{employee_id,role,department}`. Allowed departments: Administration, IT, Dispatch, Accounting, HR, Marketing, Sales, Customer Service. HR-created employees receive ordinary `employee` role. Only admins may assign `technician`, `dispatcher`, `support`, `accounting`, `hr`, or `employee`; no API creates an administrator. |
| `GET ?action=bookings` | Customer, admin, dispatcher | None | 200 `{bookings:[...]}`; customers see only their rows; admin/dispatch see all. |
| `POST ?action=bookings` | Customer | `service_id`, `requested_at` (`YYYY-MM-DD HH:MM:SS`), `address`, `details` | 201 `{booking_id,status:"Pending"}`. |
| `POST ?action=booking-status` | Admin or dispatcher | `booking_id`, `status` (`Approved`, `Declined`, `Rescheduled`); `requested_at` required for rescheduling | 200 `{booking_id,status}`. Approval/rescheduling require configured service availability, a future time, and no overlapping active booking. Updates create a customer notification and audit entry. |
| `GET ?action=assignments` | Technician, admin, dispatcher | None | 200 `{assignments:[...]}`. Technician rows include only their assigned jobs and limited customer contact details. |
| `POST ?action=assignments` | Admin or dispatcher | `booking_id`, `employee_id` (active technician's employee ID) | 201 `{assignment_id,booking_id,employee_id}`. Requires approved booking and checks technician overlap under a per-technician database lock. Creates an employee notification and audit entry. |
| `PATCH ?action=assignment-update` | Assigned technician for own job; admin or dispatcher | `assignment_id`, `status` (`In Progress`, `Completed`, `Cancelled`) | 200 `{assignment_id,status}`. Technician updates are scoped to their own assignment; booking status follows assignment progress. |
| `POST ?action=checkin` | Technician assigned to the booking | `booking_id`, `latitude`, `longitude`, optional `accuracy` | 201 `{message,checked_in_at}`. Timestamp is server generated; coordinate validity is checked; one check-in per assignment. No geofence/physical-presence verification. |
| `POST ?action=report` | Technician assigned to the booking | `booking_id`, `category`, `description` | 201 `{report_id,status}`. Categories: Equipment issue, Installation problem, Maintenance concern, Customer concern, Safety issue, Other. |
| `GET/PATCH ?action=technician-reports` | Admin | GET has no fields; PATCH needs `report_id`, `status` (`Reviewing` or `Resolved`) | GET 200 `{reports:[...]}`; PATCH 200 `{report_id,status}`. Administrative review is audited. |
| `GET ?action=notifications` | Customer, employee, or admin (admin sees employee-wide announcements) | None | 200 `{notifications:[...]}` for own, department, or all-employee audience. |
| `PATCH ?action=notifications` | Customer, employee, or admin for employee announcements | `notification_id`, `is_read` | 200 `{message}`; update is constrained to the recipient/audience. |
| `POST ?action=notifications` | Admin or HR | `audience` (`employees`, `department`, or `customer`), `title`, `body`; `department` needs `department`; `customer` needs `recipient_id` | 201 `{notification_id}`. HR may create employee announcements; only admin may target a customer. |
| `GET ?action=tickets` | Customer, support, admin | None | 200 `{tickets:[...]}`; customer sees only own. |
| `POST ?action=tickets` | Customer | `subject`, `description`, optional `category` | 201 `{ticket_id,status}`. |
| `PATCH ?action=ticket-update` | Support or admin | `ticket_id`, `status` (`Open`, `In Progress`, `Resolved`, `Closed`), optional `response` | 200 `{ticket_id,status}`; administrative change is audited. |
| `GET ?action=payments` | Customer, accounting, admin | None | 200 `{payments:[...]}`; customer rows are scoped by session account ID and include receipt number when issued. |
| `POST ?action=payments` | Accounting or admin | `booking_id`, `amount`, `currency` (ISO-like 3 letters), `method`, `status` (`paid`, `pending`, `void`, `refunded`), `transaction_at`; optional `reference` | 201 `{payment_id,receipt_number,status}`. Customer ID is derived from the booking; a receipt is issued only for a `paid` record. This records a staff-entered transaction only; there is no gateway. |
| `GET ?action=reports&date_from=YYYY-MM-DD&date_to=YYYY-MM-DD` | Admin | Query date range | 200 `{range,customers,bookings,service_usage,technician_assignments,technician_checkins,technician_reports,support_tickets,recorded_payments}`. Payments separate paid and refunded records and group by currency. |

All notifications are database-backed. No email/SMS provider or payment gateway is configured. Do not interpret manually entered payment rows as gateway-verified transactions.

## Known integration limits

- Frontend code is intentionally untouched. The frontend must call these `api/index.php?action=...` operations using JSON and preserve the session cookie. The older `customer/registration.php` path still exists for compatibility and is not a router for the new API.
- The app has three separate legacy identity tables. Migration 002's indexes enforce uniqueness only within each table. New API employee creation checks all tables; customer registration retains its existing customer-table check. Cross-role uniqueness for concurrent registration across old clients is not fully guaranteed without consolidating identity storage.
- Employee birthdate is nullable for existing employee rows; newly created accounts require it.
- Service approval requires an administrator to configure a weekly availability window first. No availability data or service catalog records are fabricated.
- No online payment, external notifications, geofence, admin account provisioning, full employee deactivation endpoint, schedule calendar UI, or shared test harness is present.
- This backend branch does not contain the framework test suite from the other branch. Runtime PHP and MariaDB/MySQL were unavailable in the inspected environment, so syntax, migration execution, and endpoint behavior remain untested.
