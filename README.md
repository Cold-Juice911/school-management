# School Management System

A beginner-friendly PHP 8.1+ and MySQL school portal with administrator, teacher, and student accounts. It uses native PHP, PDO, HTML, custom CSS, and small vanilla JavaScript helpers. No frontend framework or external asset service is required.

## Requirements

- PHP 8.1 or later with `pdo_mysql` enabled
- MySQL 8 or a compatible MariaDB server
- Apache through XAMPP, or PHP's built-in server for local evaluation
- A database imported from `database.sql`

## Windows and XAMPP setup

1. Install XAMPP and start Apache and MySQL from the XAMPP Control Panel.
2. Copy this folder to `C:\xampp\htdocs\school_management`.
3. Open phpMyAdmin at `http://localhost/phpmyadmin/` and create a database named `school_management` using `utf8mb4`.
4. Select that database, choose **Import**, and import `database.sql`.
5. Copy `.env.example` to `.env`. For the default local database, set `DATABASE_URL=mysql://root:your-password@127.0.0.1:3306/school_management?ssl-mode=DISABLED` and enter the password configured for your MySQL root account. Do not assume that the password is blank.
6. In PowerShell or Command Prompt, run `C:\xampp\php\php.exe C:\xampp\htdocs\school_management\scripts\create_admin.php`. Enter the administrator name, email, and a private password when prompted. The script is CLI-only and refuses to create another administrator if one already exists.
7. Open `http://localhost/school_management/` and sign in with the account you created.

Apache's `.htaccess` files block direct requests to configuration, runtime storage, and setup-script folders. If your Apache configuration disables `.htaccess` overrides, enable `AllowOverride All` for the project directory.

## Hosted Aiven connection

The workspace `.env` is preconfigured with the MySQL service URI supplied for this project. It is excluded from source control. Import `database.sql` into the service's existing `defaultdb` database before using the app; the SQL file intentionally does not create or switch databases.

The supplied URI requests `ssl-mode=REQUIRED`, which encrypts the connection. Aiven's project CA is needed when using certificate-verifying modes such as `VERIFY_CA` or `VERIFY_IDENTITY`. To enable CA verification, download the CA certificate from the service's Aiven Console **Overview → Connection information → CA Certificate**, store it privately in this project (the `.pem` extension is ignored), change the URI query to `ssl-mode=VERIFY_CA`, and set `DATABASE_SSL_CA` in `.env` to the certificate's absolute path. Keep the CA path and URI out of source control. Aiven periodically rotates project CA certificates, so refresh the file when Aiven announces a rotation.

## Create and manage accounts

- Administrators manage students, teachers, classes, attendance, timetables, and fee records from the dashboard.
- Administrators can edit class names and sections, review attendance history, inspect teacher/student details, and see fee balances and payment history.
- New student and teacher accounts require an initial password. Passwords are stored with `password_hash()`.
- Teachers can mark attendance only for classes assigned on their timetable.
- Students see their own profile, attendance, class timetable, fees, and payment history.
- Students can click **Pay balance (demo)** to record the full remaining balance as a simulated payment. It does not contact a bank or payment gateway; the student account and fee ledger update in one database transaction.
- Fee balances are computed from payment rows. A student demo payment records the remaining balance under a transaction lock.
- Each payment has a unique reference and an ownership-scoped printable receipt. Students can only open receipts attached to their own fees.
- Classes with linked timetable periods cannot be removed. Student records, attendance, fee records, and payments are retained; deactivate accounts instead of deleting them.

## Local development without XAMPP

After configuring `.env` and importing the schema, run the built-in PHP server from the project folder:

```powershell
php -S 127.0.0.1:8000
```

Then visit `http://127.0.0.1:8000/`. Apache protections in `.htaccess` are not applied by PHP's built-in server, so use this only on a trusted local machine.

## Optional demo records

The CLI-only demo script adds fictional classes, accounts, schedules, recent attendance, and fee records. It refuses to run without an explicit flag, a temporary password, and an empty set of reserved demo accounts. It never updates or deletes existing school records. It also refuses to use a remote database unless you add `--allow-remote-demo-data`.

In PowerShell, set a temporary password in the current session, run the script, then clear the variable:

```powershell
$env:SCHOOL_DEMO_PASSWORD = "choose-a-temporary-password"
php scripts/demo_data.php --confirm-demo-data
Remove-Item Env:SCHOOL_DEMO_PASSWORD
```

Demo accounts use the `demo.teacherNN@example.test` and `demo.studentNN@example.test` email pattern and share the temporary password you supplied. The script stores only password hashes. Do not run it against a production school database. Because this project is configured with a hosted database URI, the script will refuse to run until you explicitly add `--allow-remote-demo-data`; use that flag only for a disposable demo database.

## Security before deployment

- Serve the app only over HTTPS and set secure session cookies through HTTPS.
- Keep `.env`, logs, and any downloaded CA certificate outside public source control and restrict filesystem access.
- Use a least-privilege database account, not an administrative service user, where possible.
- Configure the web server to deny access to `.env`, `config/`, `scripts/`, and `storage/`.
- Review backups, password reset procedures, account lifecycle, rate limiting, and monitoring before a real school deployment.
- The login throttle is session-based and intended for a college project; use a shared server-side rate limiter for multi-server deployment.
- The student payment action is a demonstration only and must not be used to collect real money.

## Project map

- `config/`: environment loading and PDO connection
- `includes/`: authorization, CSRF, validation, shared navigation, and helpers
- `admin/`, `teacher/`, `student/`: role-scoped application pages
- `assets/css/styles.css`: the native CSS variable system and reusable `.card`, `.btn`, and status classes
- `database.sql`: schema, indexes, constraints, and relationships
- `scripts/create_admin.php`: restricted command-line administrator initialization

The visual system is centralized in `assets/css/styles.css`. Component colors, borders, backgrounds, and shadows use the `:root` tokens. Surface panels and tables use `.card` styling, primary actions use `.btn-primary`, and status badges use the approved semantic accent tokens.

## Verification status

PHP syntax checks pass for all project PHP files, and the JavaScript passes `node --check`. The CSS audit found no component color literals outside `:root`. A read-only connection to the configured Aiven database succeeded and confirmed all eight expected tables; no database writes were performed. Admin pages render against the live schema. Teacher and student live-page checks require active accounts, which are not currently present.

After installing XAMPP, check every PHP file with `php -l`, import the schema, create the first administrator, and exercise each role's permissions and modules. Confirm the student demo-payment updates only that student's balance and each receipt enforces student ownership.

## Request flow

The browser requests a PHP page. PHP checks the session and role, validates any submitted form and its CSRF token, and runs prepared PDO queries. MySQL returns the authorized records, then PHP escapes user-controlled values into HTML styled by the shared CSS system.

## College viva overview

- **Frontend:** HTML templates share a responsive sidebar/header, reusable cards, tables, forms, and status badges. CSS custom properties keep color and surface choices consistent.
- **Authentication:** PHP checks the session and role on each protected page. Passwords are hashed, and forms use CSRF tokens.
- **Data layer:** PDO prepared statements send validated values to MySQL. Transactions group attendance updates and payment records so partial writes can be rolled back.
- **Privacy:** Student pages derive the student ID from the signed-in account. Receipt queries check ownership on the server.
- **Payment demo:** The student button is simulated and never contacts a payment provider. It creates a ledger entry so fee status and balance update like a completed payment.
