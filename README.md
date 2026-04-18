# 🤝 VolunteerHub — Nonprofit & Volunteer Management System

> A web-based platform for managing volunteers, events, donations, and attendance — built with PHP, MySQL, HTML, CSS, and JavaScript.

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## 📖 Table of Contents

- [About the Project](#about-the-project)
- [Features](#features)
- [System Architecture](#system-architecture)
- [Tech Stack](#tech-stack)
- [Database Structure](#database-structure)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
  - [Option A: Run Locally with XAMPP](#option-a-run-locally-with-xampp)
  - [Option B: Deploy to InfinityFree (Free Hosting)](#option-b-deploy-to-infinityfree-free-hosting)
- [Default Credentials](#default-credentials)
- [Screenshots](#screenshots)
- [Performance Metrics](#performance-metrics)
- [Known Limitations](#known-limitations)
- [Roadmap](#roadmap)
- [Author](#author)

---

## About the Project

**VolunteerHub** is a capstone project developed at PHINMA University of Iloilo (College of Information Technology Education). It was built to replace the inefficient manual methods — paper records and spreadsheets — that many nonprofit organizations still rely on for volunteer coordination.

The system provides a centralized, automated platform for both **administrators** and **volunteers**, enabling real-time tracking of events, tasks, donations, and attendance using QR code technology.

**Live Demo:** [https://volunteerhub.page.gd/VolunteerHub/index.php](https://volunteerhub.page.gd/VolunteerHub/index.php)

---

## Features

### 👤 User Management
- Volunteer self-registration with email and profile details
- Secure PHP-based login and session handling
- Role-based access control (Admin vs. Volunteer)
- Password hashing using PHP's built-in functions

### 📅 Event Management
- Admins can create, edit, or cancel events (date, time, location, description)
- Volunteers can browse available events and submit participation applications
- Admins can approve or reject volunteer applications per event

### ✅ Task Assignment
- Admins assign specific tasks/roles to volunteers per event
- Volunteers view their assigned responsibilities in their personal dashboard
- Task status tracking and completion monitoring

### 📷 QR Code Attendance
- Each event generates a unique QR code for check-in and check-out
- Volunteers scan the QR code on arrival and departure
- Timestamps are automatically recorded in the database
- Eliminates manual sign-in sheets and reduces duplicate entries

### 💰 Donation Management
- Supports three donation methods:
  - **GCash** — mobile payment with proof of transfer upload
  - **Bank Transfer** — proof of payment upload
  - **Drop-off** — physical item/cash donation logging
- Admins can approve, reject, or review donation records
- Full donation history with donor name, amount, and date

### 🔔 Notification System
- In-platform notifications for task assignments, event updates, and donation statuses
- Real-time alerts for volunteers and administrators

### 📊 Reports & Dashboard
- Admin dashboard with consolidated summary: total volunteers, events, donations, and attendance
- Report generation for volunteer activity, event participation, and donation records
- Search and filter functions across all modules

### 🔍 Search & Filter
- Search for specific events, volunteers, or donation records by keyword
- Filter results by date, status, or category

---

## System Architecture

VolunteerHub follows a **Three-Tier Architecture**:

```
┌─────────────────────────────────────────┐
│         PRESENTATION LAYER              │
│    HTML · CSS · JavaScript · Bootstrap  │
│  (Login, Dashboards, Forms, Tables)     │
└────────────────┬────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│          APPLICATION LAYER              │
│               PHP Scripts               │
│  (Auth, Session, Business Logic,        │
│   Role Access, QR Generation,           │
│   Donation Processing, Reporting)       │
└────────────────┬────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│            DATA LAYER                   │
│           MySQL Database                │
│  (Users, Volunteers, Events, Tasks,     │
│   Donations, Attendance, Notifications) │
└─────────────────────────────────────────┘
```

---

## Tech Stack

| Component              | Technology                              |
|------------------------|-----------------------------------------|
| Frontend               | HTML5, CSS3, JavaScript                 |
| UI Framework           | Bootstrap 5, Font Awesome               |
| Backend / Auth         | PHP (session handling, RBAC, login)     |
| Database               | MySQL                                   |
| QR Code                | PHP QR Code Generator Library           |
| Local Development      | XAMPP (Apache + MySQL)                  |
| Production Hosting     | InfinityFree (free PHP + MySQL hosting) |
| Browser Compatibility  | Google Chrome, Microsoft Edge           |

---

## Database Structure

The system uses a relational MySQL database with the following core tables:

| Table           | Description                                                     |
|-----------------|-----------------------------------------------------------------|
| `users`         | Login credentials for admins and volunteers (id, username, password_hash, role) |
| `volunteers`    | Volunteer profiles — name, email, contact, address             |
| `events`        | Event records — title, date, time, location, status            |
| `donations`     | Donation submissions — type, amount, donor, status, proof      |
| `tasks`         | Task assignments linked to volunteers and events               |
| `attendance`    | QR-based check-in/check-out logs with timestamps               |
| `notifications` | System alerts and in-platform notifications for users          |

All tables are linked via foreign keys (`volunteer_id`, `event_id`, `donation_id`) to maintain referential integrity.

---

## Project Structure

```
VolunteerHub/
├── admin/
│   ├── admin_dashboard.php          # Main admin dashboard
│   ├── admin_login.php              # Admin login page
│   ├── admin_profile.php            # Admin profile management
│   ├── assign_tasks.php             # Task assignment interface
│   ├── check_session.php            # Session validation guard
│   ├── complete_event.php           # Mark event as complete
│   ├── delete_item.php              # Generic record deletion
│   ├── fetch_applicants.php         # Fetch event applicants (AJAX)
│   ├── fetch_volunteers.php         # Fetch volunteer list (AJAX)
│   ├── generate_qr.php              # QR code generator for events
│   ├── handle_volunteer_action.php  # Approve/reject volunteers
│   ├── manage_donations.php         # Donation review interface
│   ├── manage_events.php            # Event CRUD interface
│   ├── manage_tasks.php             # Task management interface
│   ├── process_update_donation_info.php  # Update donation records
│   ├── register_organization.php    # Organization registration
│   ├── update_application.php       # Update volunteer applications
│   └── update_attendance.php        # Manually update attendance
│
├── assets/
│   └── pattern.png                  # Background/UI asset
│
├── Generator/
│   ├── Qrcode Checkin/              # QR code for check-in scanning
│   └── Qrcode Checkout/             # QR code for check-out scanning
│
├── includes/
│   ├── css/                         # Shared CSS includes
│   ├── scripts/                     # Shared JS scripts
│   ├── webfonts/                    # Font Awesome webfonts
│   ├── footer.php                   # Shared footer component
│   ├── header_admin.php             # Admin navigation header
│   └── header_volunteer.php         # Volunteer navigation header
│
├── styles/
│   ├── admin_layout.css
│   ├── admin_manage.css
│   ├── dashboard.css
│   ├── footer.css
│   ├── index_styles.css
│   ├── login_styles.css
│   ├── notifications.css
│   ├── organization_profile.css
│   ├── organization.css
│   ├── profile.css
│   ├── register_org.css
│   ├── volunteer_dashboard.css
│   └── volunteer_tables.css
│
├── uploads/
│   ├── logo/                        # Organization logos
│   ├── moneytransfer/               # Donation proof of payment images
│   └── profiles/                    # Volunteer profile photos
│
├── volunteer/
│   ├── check_session.php            # Volunteer session guard
│   ├── delete_item.php              # Record deletion (volunteer scope)
│   ├── generate_acknowledgement.php # Donation acknowledgement generator
│   ├── lists_events.php             # Browse available events
│   ├── my_tasks.php                 # View assigned tasks
│   ├── notifications.php            # Volunteer notifications
│   ├── organization_profile.php     # View organization details
│   ├── organization.php             # Organization listing
│   ├── process_donation.php         # Submit donation
│   ├── volunteer_dashboard.php      # Volunteer home dashboard
│   ├── volunteer_login.php          # Volunteer login page
│   └── volunteers_profile.php       # Volunteer profile page
│
├── composer.json                    # PHP dependency manager config
├── conn.php                         # Database connection file
├── index.php                        # Landing / home page
├── logout.php                       # Logout handler
└── register.php                     # New volunteer registration
```

---

## Getting Started

### Prerequisites

Before you start, make sure you have the following installed:

- [XAMPP](https://www.apachefriends.org/) (for local development) — includes Apache, PHP, and MySQL
- A web browser (Google Chrome or Microsoft Edge recommended)
- [Git](https://git-scm.com/) (to clone the repository)

---

### Option A: Run Locally with XAMPP

Follow these steps to run VolunteerHub on your local machine.

#### Step 1 — Install XAMPP

1. Download XAMPP from [https://www.apachefriends.org](https://www.apachefriends.org).
2. Run the installer and follow the on-screen instructions.
3. Make sure to include **Apache** and **MySQL** during installation.

#### Step 2 — Clone the Repository

Open a terminal (or Git Bash) and run:

```bash
git clone https://github.com/JLESC2002/VolunteerHub.git
```

Then move the cloned folder into XAMPP's web root:

- **Windows:** `C:\xampp\htdocs\`
- **Mac/Linux:** `/Applications/XAMPP/htdocs/` or `/opt/lampp/htdocs/`

Your folder should look like:
```
C:\xampp\htdocs\VolunteerHub\
```

#### Step 3 — Start XAMPP

1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

Both status indicators should turn green.

#### Step 4 — Create the Database

1. Open your browser and go to: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Click **New** in the left sidebar.
3. Enter the database name: `volunteerhub`
4. Click **Create**.
5. With the `volunteerhub` database selected, click the **Import** tab.
6. Click **Choose File** and select the SQL file from the project:
   ```
   VolunteerHub/volunteerhub.sql
   ```
   *(If no SQL file is provided, manually create the tables using the structure in the [Database Structure](#database-structure) section above.)*
7. Click **Import** at the bottom.

#### Step 5 — Configure the Database Connection

Open the file `conn.php` in the root of the project and verify the settings:

```php
<?php
$host     = "localhost";
$user     = "root";       // default XAMPP MySQL username
$password = "";           // default XAMPP MySQL password (leave blank)
$database = "volunteerhub";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
```

> **Note:** If you changed your MySQL username or password during XAMPP setup, update the values above to match.

#### Step 6 — Run the System

Open your browser and visit:

```
http://localhost/VolunteerHub/index.php
```

The VolunteerHub landing page should load. You can now log in as an admin or register as a volunteer.

---

### Option B: Deploy to InfinityFree (Free Hosting)

InfinityFree provides free PHP and MySQL web hosting — no credit card required. VolunteerHub was originally deployed and tested on this platform.

**Live Hosting Dashboard:** [https://dash.infinityfree.com](https://dash.infinityfree.com)

---

#### Step 1 — Create a Free InfinityFree Account

1. Go to [https://www.infinityfree.com](https://www.infinityfree.com).
2. Click **Sign Up** and register using your email address.
3. Verify your email via the confirmation link sent to your inbox.
4. Log in to your InfinityFree account.

#### Step 2 — Create a Hosting Account

1. From the InfinityFree dashboard, click **+ New Account**.
2. Choose a **subdomain** (e.g., `volunteerhub.infinityfreeapp.com`) or use your own custom domain.
3. Set a **password** for the hosting account and confirm.
4. Click **Create Account** and wait for it to be provisioned (usually takes 1–2 minutes).

#### Step 3 — Create the MySQL Database

1. From the InfinityFree dashboard, click **Manage** on your new hosting account.
2. Go to the **MySQL Databases** section (in the control panel / cPanel).
3. Create a new database:
   - **Database name:** e.g., `volunteerhub`
   - **Database user:** create a new user with a strong password
   - Assign the user to the database with **All Privileges**
4. Note down the following values (you will need them):
   - Database host (usually `sql.infinityfree.com` or similar — shown in the panel)
   - Database name (with prefix, e.g., `if0_12345678_volunteerhub`)
   - Database username (with prefix)
   - Database password

#### Step 4 — Import the Database

1. In the cPanel, go to **phpMyAdmin**.
2. Select your newly created database from the left panel.
3. Click the **Import** tab.
4. Click **Choose File** and upload the `volunteerhub.sql` file from the project.
5. Click **Import** to execute.

#### Step 5 — Update the Database Connection File

Open `conn.php` and update the connection details to match your InfinityFree database:

```php
<?php
$host     = "sql.infinityfree.com";      // Your InfinityFree MySQL host
$user     = "if0_XXXXXXXX";              // Your InfinityFree DB username
$password = "your_db_password";          // Your database password
$database = "if0_XXXXXXXX_volunteerhub"; // Your full database name

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
```

> Replace all placeholder values with your actual InfinityFree credentials from Step 3.

#### Step 6 — Upload Project Files via FileZilla (FTP)

1. Download and install [FileZilla](https://filezilla-project.org/) (free FTP client).
2. In the InfinityFree cPanel, go to **FTP Accounts** and note your FTP credentials:
   - **FTP Host**
   - **FTP Username**
   - **FTP Password**
   - **Port:** 21
3. Open FileZilla and enter:
   - **Host:** your FTP host
   - **Username:** your FTP username
   - **Password:** your FTP password
   - **Port:** 21
4. Click **Quickconnect**.
5. On the **Remote Site** panel, navigate to the `/htdocs` folder.
6. On the **Local Site** panel, find your `VolunteerHub` project folder.
7. Select all files inside `VolunteerHub/` and drag them into the `/htdocs` remote directory.
8. Wait for all files to finish uploading (this may take a few minutes depending on connection speed).

#### Step 7 — Access Your Live Site

Once the upload is complete, open your browser and visit your subdomain URL, for example:

```
https://volunteerhub.infinityfreeapp.com/index.php
```

Or if you're using a custom domain, visit:

```
https://yourdomain.com/index.php
```

> **Note:** InfinityFree may take up to 72 hours to fully propagate a new domain. Subdomains are usually active within minutes.

#### Step 8 — Verify Everything Works

- Try loading the login page.
- Register a new volunteer account.
- Log in as an admin and verify dashboard data loads.
- Create a test event and check QR code generation.
- Submit a test donation and confirm it appears in the admin panel.

---

## Default Credentials

> ⚠️ **Security Notice:** Change these immediately after first login, especially on a live/public deployment.

| Role      | Username  | Password   |
|-----------|-----------|------------|
| Admin     | `admin`   | `admin123` |
| Volunteer | Register using the registration form on the index page |

---

## Screenshots

| Screen                  | Description                                                        |
|-------------------------|--------------------------------------------------------------------|
| **Login Page**          | PHP-based login interface for admins and volunteers                |
| **Admin Dashboard**     | Overview of total events, volunteers, donations, and attendance    |
| **Volunteer Dashboard** | Volunteer's personal view of assigned tasks and upcoming events    |
| **Event Management**    | Admin interface for creating and managing events                   |
| **Donation Management** | Admin panel for reviewing, approving, or rejecting donations       |
| **QR Code Attendance**  | Volunteer check-in and check-out interface via QR code scanning    |
| **Task Assignment**     | Admin panel for assigning tasks to volunteers per event            |

---

## Performance Metrics

Results from User Acceptance Testing (UAT):

| Metric                         | Before System | After System  |
|-------------------------------|---------------|---------------|
| Volunteer Task Accuracy        | 82%           | **97%**       |
| Task Assignment Time           | 4 minutes     | **50 seconds**|
| Missed Volunteer Schedules     | 18 incidents  | **3 incidents**|
| User Satisfaction Rating       | —             | **4.8 / 5.0** |
| Average Page Response Time     | —             | **1.3 seconds**|
| System Error Rate              | —             | **< 1%**      |
| System Uptime During Testing   | —             | **99%**       |

**93%** of participants preferred VolunteerHub over existing manual methods.

---

## Known Limitations

- **Internet Required:** The system is web-based and requires a stable internet connection. It cannot run offline.
- **Camera Needed for QR:** The QR code attendance feature requires a functioning camera or barcode scanner. Biometric alternatives are not supported in this version.
- **Two User Roles Only:** The system currently supports Admins and Volunteers. Separate modules for external donors or higher management levels are not included.
- **Basic Security:** Fundamental security measures are implemented (input validation, password hashing, session checks), but additional hardening may be needed for high-traffic production environments.
- **Not Tested at Large Scale:** UAT was conducted in a small-to-medium organizational context. Scalability under heavy concurrent usage has not been fully evaluated.

---

## Roadmap

Planned improvements for future versions:

- [ ] Mobile application (Android/iOS) for volunteer access
- [ ] Email and SMS notifications for time-sensitive event reminders
- [ ] Volunteer performance rankings and leaderboard
- [ ] Certificate generation for event participation
- [ ] Automated cloud backup of database records
- [ ] Detailed attendance analytics and export to Excel/PDF
- [ ] Multi-organization support under one admin panel

---

## Author

**John Lester H. Chua**
Bachelor of Science in Information Technology
PHINMA University of Iloilo — College of Information Technology Education
Capstone Project — March 2026

**Adviser:** Krislyn Mae Sinoy
**Panel Chairman:** Dr. Arnold M. Fuentes, Ph.D, MPA, MMIT

---

## Repository & Links

| Resource       | Link                                                                 |
|----------------|----------------------------------------------------------------------|
| GitHub Repo    | [https://github.com/JLESC2002/VolunteerHub](https://github.com/JLESC2002/VolunteerHub) |
| Live Site      | [https://volunteerhub.page.gd/VolunteerHub/index.php](https://volunteerhub.page.gd/VolunteerHub/index.php) |
| Hosting Panel  | [https://dash.infinityfree.com](https://dash.infinityfree.com)       |

---

*Built with ❤️ for nonprofit organizations and the volunteers who make them run.*
