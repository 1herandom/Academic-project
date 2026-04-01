# SMART EDU

Role-based academic management website built with PHP, MySQL, HTML, CSS, and JavaScript.

## Features included
- Role-based login and redirect
- Server-side 403 access control
- Academic Admin user management
- Admin course management
- CSV batch enrollment with dry run
- Teacher attendance with Lecture / Tutorial / Workshop
- Bulk attendance marking
- Assignment publishing with brief upload
- Student submission uploads with deadline enforcement
- Student attendance progress dashboard
- Granular L/T/W attendance analytics
- Responsive layout with sidebar and mobile hamburger menu
- Remember-me secure cookie login
- Temporary password generation and password reset

## Setup
1. Create a MySQL database named `smart_edu`.
2. Import `db.sql`.
3. Edit `config.php` with your database username/password.
4. Run `seed.php` once to create the first admin account.
5. Open `index.php` in your server.

## Default admin
- Institutional ID: `ADMIN001`
- Password: `Admin@1234`

## Notes
- Teacher email format: `full.name + teacher-id + @smart.edu.np`
- Student email format: `NP + student-id + @smart.edu.np`
- Teacher ID: 4 digits
- Student ID: 8 digits
