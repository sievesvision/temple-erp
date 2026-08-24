# Task List - Pooja Booking System & Role Layouts Segregation

- [x] Create separate app, sidebar, topbar layouts for every role (Admin, Devotee, Priest, Trustee, Staff, Accountant)
- [x] Implement role-based route protection middleware `RoleMiddleware` and register alias in `bootstrap/app.php`
- [x] Group routes in `routes/web.php` with respective auth and role restrictions
- [x] Create dynamic controllers: `TrusteeController`, `StaffController`, `AccountantController`
- [x] Route devotee chat/agent clicks/inputs to live staff support chat mode
- [x] Update UPI Payee ID to `rohandevadigapithrodi-1@oksbi`
- [x] Correct syntax nesting bug in staff dashboard script preventing chat polling from loading/rendering
- [x] Segregate and build interactive tab dashboards for Trustee, Accountant, and Staff
- [x] Redesign Devotee Pooja Booking to a 6-step wizard (Poojas -> Date & Time -> Priest Select -> Mode -> Summary -> Payment)
- [x] Refactor ProfileController validation and persistence logic for all roles
- [x] Add instant inline validation and disabled progression state to booking wizard
- [x] Implement draft autosaving state to browser `localStorage` and recover alert prompt on login
- [x] Create `notifications` and `audit_logs` database tables via scratch updates
- [x] Create `NotificationService` and `AuditLogService` for system messaging and event auditing
- [x] Secure logout flow across all layouts to route to AuthController@logout instead of static files
- [x] Verify routing syntax compiles without warnings
