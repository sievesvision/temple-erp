# 🛕 Temple ERP - Integrated Temple Administration & Devotee Services Portal

Temple ERP is a comprehensive web-based Enterprise Resource Planning (ERP) system developed to digitize and automate temple management. The application simplifies temple administration by providing dedicated portals for Devotees, Priests, Staff, Trustees, Accountants, and Administrators.

---

## 🚀 Features

### 👤 Authentication
- Multi-role Login System
- Email OTP Verification
- Forgot Password with OTP
- Secure Authentication
- Role-Based Access Control

### 🙏 Devotee Module
- Online Registration
- Profile Management
- Pooja Booking
- E-Hundi Donations
- Donation History
- Event Information
- Chat Support

### 🛕 Priest Module
- Assigned Poojas
- Daily Schedule
- Leave Management
- Profile Management

### 👨‍💼 Staff Module
- Attendance Management
- Shift Tracking
- Support Chat
- Dashboard

### 💰 Accountant Module
- Donation Records
- Payroll Management
- Financial Reports
- Expense Tracking

### 👨‍⚖️ Trustee Module
- Temple Reports
- Financial Overview
- Event Monitoring

### 👨‍💻 Admin Module
- Dashboard
- User Management
- Priest Management
- Staff Management
- Trustee Management
- Accountant Management
- Devotee Verification
- Pooja Management
- Event Management
- Donation Management
- Reports
- System Settings

---

# 🏗️ Technology Stack

| Technology | Version |
|------------|---------|
| Laravel | 11.x |
| PHP | 8.2+ |
| MySQL | 8.0 |
| SQLite | Testing |
| HTML5 | ✓ |
| CSS3 | ✓ |
| Bootstrap 5 | ✓ |
| JavaScript | ✓ |
| jQuery | ✓ |
| AJAX | ✓ |
| Blade Template Engine | ✓ |

---

# 📂 Project Structure

```
TempleERP/
│
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── vendor/
└── artisan
```

---

# ⚙️ Installation

## Clone Repository

```bash
git clone https://github.com/your-username/TempleERP.git
```

## Move into Project

```bash
cd TempleERP
```

## Install Dependencies

```bash
composer install
```

## Copy Environment File

```bash
cp .env.example .env
```

## Generate Application Key

```bash
php artisan key:generate
```

## Configure Database

Update the `.env` file:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=temple_erp
DB_USERNAME=root
DB_PASSWORD=
```

## Run Migrations

```bash
php artisan migrate
```

## Seed Database (Optional)

```bash
php artisan db:seed
```

## Start Server

```bash
php artisan serve
```

Application URL

```
http://127.0.0.1:8000
```

---

# 🚀 Production Deployment (Hostinger Shared Hosting)

This app runs on Laravel 12 / PHP 8.2+, uses `database` drivers for session/cache/queue (no Redis needed), and doesn't depend on a Vite build for any of its actual pages (Bootstrap/jQuery load from CDN) — so `npm run build` is not required to deploy.

## 0. Commit everything first

Before deploying, make sure **all** app code, migrations, and the `public/images/**` assets are committed and pushed — nothing in `.gitignore` should be load-bearing for the app to run.

```bash
git add -A
git status   # sanity check what's staged
git commit -m "Prepare for deployment"
git push origin main
```

## 1. Create the site & database in hPanel

1. **hPanel → Websites** — add your domain (or use a Hostinger subdomain).
2. **hPanel → Advanced → PHP Configuration** — set PHP to **8.2 or 8.3**, and confirm `pdo_mysql` and `bcmath` extensions are enabled.
3. **hPanel → Databases → MySQL Databases** — create a database + user with full privileges. Note the database name, username, password (host is almost always `localhost` on shared hosting).
4. **hPanel → Advanced → SSH Access** — enable it and note the SSH host/port/username (Business plan and above).

## 2. Get the code onto the server

```bash
ssh -p <port> u123456789@your-ssh-host
cd domains/yourdomain.com
git clone https://github.com/rohansserigar/Temple-Management.git ssvk
cd ssvk
```

## 3. Point the domain at Laravel's `public/` folder

Laravel's entry point is `public/index.php`, not the repo root, so the domain's document root must point there:

- **Preferred:** hPanel → Websites → your domain → Manage → Advanced → Document Root → set it to `domains/yourdomain.com/ssvk/public`.
- **Fallback**, if your plan won't let you change the document root: symlink it —
  ```bash
  rm -rf ~/domains/yourdomain.com/public_html
  ln -s ~/domains/yourdomain.com/ssvk/public ~/domains/yourdomain.com/public_html
  ```
  Note: once `public_html` is a symlink, some File Manager / FTP clients handle it oddly (can't "see" it as a normal folder, refuse uploads directly into it). If you need to drop files in directly, upload into `ssvk/public` (or the relevant subfolder, e.g. `ssvk/public/images/events`) instead of `public_html`.

## 4. Install dependencies

```bash
cd ~/domains/yourdomain.com/ssvk
composer install --no-dev --optimize-autoloader
```

## 5. Configure `.env` for production

```bash
cp .env.example .env
```

Set at minimum:

```env
APP_NAME="Sri Selva Vinayakar Koyil"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<your db name>
DB_USERNAME=<your db user>
DB_PASSWORD=<your db password>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

```bash
php artisan key:generate
```

**Real email for OTP:** the default `.env.example` has `MAIL_MAILER=log`, which only logs OTP codes instead of emailing them. Registration/forgot-password depend on real delivery, so create a mailbox in **hPanel → Emails** and set:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=<mailbox password>
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

## 6. Run migrations and cache config

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

## 7. Enable SSL

**hPanel → SSL** — issue the free Let's Encrypt certificate, then keep `APP_URL=https://...` in `.env`.

## 8. Smoke-test checklist

- Homepage loads, donation section and Event Donations cards render.
- An individual event page (e.g. `/events/vinyagar-chathurthi-2026-09-14-1`) loads its header image and flyer correctly.
- Admin → Manage Events / Manage Donations pages load and save correctly.
- A test donation through each payment tab (Bank / Cash / Online) lands in Manage Donations.
- New devotee registration actually receives the OTP email.

## 9. Deploying future updates (manual)

```bash
ssh -p <port> u123456789@your-ssh-host
cd ~/domains/yourdomain.com/ssvk
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

# 🔁 CI/CD via GitHub Actions

`.github/workflows/deploy.yml` automates the update step above. On every push to `main` (or a manual run from the Actions tab):

1. **`test` job** — installs dependencies and runs the test suite (`tests/Feature`, `tests/Unit`) against an in-memory SQLite DB.
2. **`deploy` job** — only runs if tests pass; SSHes into Hostinger and runs `git pull`, `composer install`, `migrate --force`, and re-caches config/routes/views.

This assumes the one-time manual setup in steps 1–7 above has already been done on the server (cloned repo, working `.env`) — the workflow only pulls updates, it doesn't bootstrap a fresh install.

## One-time setup

**1. Generate a dedicated SSH deploy key** (don't reuse your personal key):
```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f deploy_key -N ""
```

**2. Add the public key to Hostinger**: hPanel → Advanced → SSH Access → Manage SSH Keys → paste `deploy_key.pub`.

**3. Add these repository secrets** (GitHub repo → Settings → Secrets and variables → Actions):

| Secret | Value |
|---|---|
| `HOSTINGER_SSH_HOST` | SSH hostname from hPanel (e.g. `srv123.hostinger.com`) |
| `HOSTINGER_SSH_PORT` | SSH port from hPanel (often not 22) |
| `HOSTINGER_SSH_USERNAME` | SSH username (e.g. `u123456789`) |
| `HOSTINGER_SSH_KEY` | full contents of the **private** key file (`deploy_key`) |
| `HOSTINGER_APP_PATH` | absolute path to the app on the server, e.g. `/home/u123456789/domains/yourdomain.com/ssvk` |

**4. Delete the local key files** once the public half is uploaded and the private half is pasted into GitHub secrets.

---

# 🔒 Security Features

- Role-Based Authentication
- CSRF Protection
- SQL Injection Prevention
- XSS Protection
- Password Hashing
- Session Management
- OTP Verification
- Email Verification

---

Run this wherever you need the admin created (local: from this directory; Hostinger: after cd ~/domains/hasq.org/ssvk):

php artisan tinker --execute="
\App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@hasq.org',
    'mobile' => '0000000000',
    'password' => 'ChangeThisPassword123!',
    'role' => 'Admin',
    'status' => 'Active',
    'email_verified_at' => now(),
]);
echo 'Admin user created.';
"

# 📸 Screenshots
Home Page:
<img width="1919" height="907" alt="image" src="https://github.com/user-attachments/assets/52027a83-c232-460d-825e-9bc7c8585a30" />

Donation Page:
<img width="1919" height="921" alt="image" src="https://github.com/user-attachments/assets/c51c168e-4fe5-466c-b292-096b9ed3342b" />

E-Hundi:
<img width="1902" height="917" alt="image" src="https://github.com/user-attachments/assets/21691123-ee3a-4474-b508-c65a32c7d2ee" />
Registration page:
<img width="1911" height="915" alt="image" src="https://github.com/user-attachments/assets/df5d78d9-9d80-4d35-bd06-dd4d574abc35" />

Login Page:
<img width="1900" height="904" alt="image" src="https://github.com/user-attachments/assets/52c6c99a-3f47-4877-a547-8586c8537737" />
<img width="1899" height="916" alt="image" src="https://github.com/user-attachments/assets/79f1fa19-d229-461e-914e-dbd66d9a2b3f" />

Devotee Dashboard:
<img width="1909" height="924" alt="image" src="https://github.com/user-attachments/assets/ad09c42e-882b-41ed-a4b7-5e329464a35e" />
Book Pooja:
<img width="1902" height="924" alt="image" src="https://github.com/user-attachments/assets/29f015fb-0944-4811-9a49-96fc39604471" />

Select Priest:
<img width="1918" height="917" alt="image" src="https://github.com/user-attachments/assets/d7aaeea0-013b-4a14-8228-6a4db6ba60ef" />
Pooja Booking history:
<img width="1916" height="920" alt="image" src="https://github.com/user-attachments/assets/d74ca44f-8890-47c2-992a-d0260ff001cc" />
MemberShip page:
<img width="1917" height="916" alt="image" src="https://github.com/user-attachments/assets/49685252-7e1d-45cc-b68b-96134d9d7850" />
Live Chat Support:
<img width="1890" height="912" alt="image" src="https://github.com/user-attachments/assets/772406e4-b8da-4dd6-aa4a-d30abaecfcfe" />


Priest Dashboard:
<img width="1918" height="922" alt="image" src="https://github.com/user-attachments/assets/e9b41ec8-e0a5-4c55-b49e-0dc77d24c1bb" />
Attendence System:
<img width="1905" height="929" alt="image" src="https://github.com/user-attachments/assets/9ab7e98c-4868-48e4-a114-3f4180c3c54b" />
Leave Request:
<img width="1919" height="906" alt="image" src="https://github.com/user-attachments/assets/7432d46a-0b4f-487f-897a-85cb897fbee8" />
Priest Wallet:
<img width="1919" height="913" alt="image" src="https://github.com/user-attachments/assets/0a44eb03-3a8e-4f4a-a215-7213b967dcb7" />
Salary Details:
<img width="1907" height="925" alt="image" src="https://github.com/user-attachments/assets/a9970557-5180-4450-865c-782d1c6304db" />
Trustee Dashboard:
<img width="1919" height="915" alt="image" src="https://github.com/user-attachments/assets/32ce2841-8640-482e-a1b3-19982f71ca25" />
Staff Dashboard:
<img width="1919" height="919" alt="image" src="https://github.com/user-attachments/assets/07c1dc80-1fb0-465a-bca4-85f230474d75" />
Chat Section:
<img width="1919" height="919" alt="image" src="https://github.com/user-attachments/assets/f576ff76-d10e-4134-8407-5cd4e2af36db" />
Offline Counter:
<img width="1903" height="920" alt="image" src="https://github.com/user-attachments/assets/65fc2a8b-8463-4259-a5e7-318e1c401d11" />
Admin Dashboard:
<img width="1894" height="909" alt="image" src="https://github.com/user-attachments/assets/ddd7a28b-f7ec-40a8-b3d8-dbea5da111bc" />
Mange Priest:
<img width="1901" height="918" alt="image" src="https://github.com/user-attachments/assets/9b6f9417-a8cf-4813-9511-f053315093ad" />
Sancation Salary Page:
<img width="1898" height="917" alt="image" src="https://github.com/user-attachments/assets/f78b6801-cbf4-4377-8b33-63e521d1d530" />
Custom settings:
<img width="1907" height="916" alt="image" src="https://github.com/user-attachments/assets/17f436b2-6a33-41ca-b68d-24c3f4f2bede" />


---

# 👨‍💻 Developed By

**Rohan**

B.Tech Computer Science Engineering

MIT Manipal

---

# 📄 License

This project is developed for educational and academic purposes.

---

