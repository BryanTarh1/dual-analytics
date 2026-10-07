# Dual Analytics

A self-hosted customer behaviour analytics tool for **any two establishments** run together (restaurant + gym, café + salon, bar + cinema...). It merges sales and visit data per customer so you can see who uses both, who is drifting away, and where revenue comes from.

Stack: PHP 8 (PDO), MariaDB/MySQL, vanilla JS, Chart.js. Runs on XAMPP/WAMP/LAMP.

## Install
1. Copy this folder to `htdocs` and start Apache and MySQL.
2. Copy `config.example.php` to `config.php` and edit it: database user, password, port (XAMPP default is 3306), currency, and your two establishments.
3. Run `php setup.php --demo` (or open `setup.php?demo=1`). Drop `--demo` for an empty install.
4. Open `index.html`, sign in with `admin` / `admin123`, change the password, and delete `setup.php`.

## Make it yours
In `config.php`, the `venues` list defines both establishments: their name, check-in activities and POS items with prices. Nothing else in the code mentions restaurants or gyms.

## Features
Customer registry with search, status (Active/Inactive/Trial) and duplicate email protection; POS with multi-item orders, discount, tax and payment method (saved in a DB transaction); check-in with activity and duration; manager dashboard with revenue trend, revenue split, top customers, activity mix, peak hours, at-risk members, high spenders who rarely visit, and customers using both establishments.

## Layout
`api.php` (JSON API) · `includes/bootstrap.php` (DB, session, helpers) · `database.sql` · `setup.php` · `index.html` + `assets/`

## Notes
Passwords use bcrypt, queries use prepared statements, and the dashboard is limited to manager/admin roles. Chart.js loads from a CDN; for offline use, download it into `assets/` and change the script tag. Add more users by inserting rows into `users` with a `password_hash()` value.

