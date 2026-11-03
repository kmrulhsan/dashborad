AUTO & TRUCK TRACKER  -  SETUP GUIDE
====================================
Cars (Japanese reconditioned) + Truck business, with login, VAT / Mushak files and backups.
Needs: PHP 8.0 or newer, MySQL or MariaDB, https.

IMPORTANT: this was written without being able to run PHP in the build environment, so test it
on your host before you rely on it (see "FIRST TEST" below). If any page shows an error,
send the exact message to your developer or back to Claude and it can be fixed.

-----------------------------------------------------------------------------------
PART 1 - BUY HOSTING AND A DOMAIN
-----------------------------------------------------------------------------------
1. Buy a domain (for example yourbusiness.com or .com.bd) and web hosting. Before you pay, ask the
   host to confirm ALL of these:
     - cPanel control panel
     - PHP 8.0 or newer (8.1 / 8.2 is better)
     - MySQL or MariaDB database
     - Free SSL certificate (https)
     - Cron Jobs available
     - Automatic daily backups (switch this on)
2. Point the domain to the hosting (the host tells you how; usually "change nameservers").

-----------------------------------------------------------------------------------
PART 2 - SET UP THE SERVER (about 20 minutes)
-----------------------------------------------------------------------------------
STEP 1  Create the database (cPanel > "MySQL Databases")
   a) Create a database, e.g. tracker
   b) Create a database user with a LONG random password (use the password generator)
   c) "Add user to database" > tick ALL PRIVILEGES
   d) Write down: database name, user name, password. (cPanel adds a prefix, e.g. abc123_tracker.
      Use the full names exactly as shown.)

STEP 2  Edit config.php (on your computer, with Notepad)
   - DB_NAME, DB_USER, DB_PASS = the three values from step 1. DB_HOST stays 'localhost'.
   - DATA_DIR = '/home/YOUR_CPANEL_USERNAME/bt_data'
       Your cPanel username is shown on the right side of the cPanel home page, and the full
       path is shown in File Manager (the folder above public_html).
       This folder must be OUTSIDE public_html. The app creates it. Uploaded VAT / NID / TIN
       documents and backups are kept here, so nobody can open them with a web address.

STEP 3  Upload the files (cPanel > File Manager > public_html)
   - Upload: index.php, setup.php, config.php, backup.php, .htaccess
   - If you cannot see .htaccess, in File Manager > Settings tick "Show Hidden Files (dotfiles)".
   - Do NOT upload this README.

STEP 4  Turn on https (cPanel > "SSL/TLS Status" > Run AutoSSL)
   - Open https://yourdomain/ and check the padlock appears. The .htaccess forces https.

STEP 5  Run setup ONCE
   - Open https://yourdomain/setup.php
   - It creates the tables and three logins: chairman, manager, accounts, with random passwords
     shown ONE time. Copy them to a safe place now.
   - Immediately DELETE setup.php (File Manager > select > Delete).

STEP 6  First login and passwords
   - Open https://yourdomain/ and sign in as chairman.
   - Menu "Password": change your password (minimum 10 characters).
   - As chairman you can also set new passwords for manager and accounts (Password page >
     "Change password for"). Give each person only their own login. Never share one login.

STEP 7  Daily backup (cPanel > "Cron Jobs")
   - Add a cron job, once a day (for example minute 30, hour 2), with this command
     (replace YOURCPANELUSER; if public_html is not where you uploaded, adjust the path):
        /usr/local/bin/php /home/YOURCPANELUSER/public_html/backup.php
   - It saves the database AND all uploaded documents to DATA_DIR/backups and keeps 45 days.
   - Also keep the host's own daily backup switched on.
   - Every month: download the newest files from DATA_DIR/backups (File Manager) to your computer
     and to Google Drive. Once, try a restore (phpMyAdmin > Import) so you know it works.

If uploads of PDFs fail: ask the host to raise PHP "upload_max_filesize" and "post_max_size" to 8M
(cPanel > "MultiPHP INI Editor").

-----------------------------------------------------------------------------------
FIRST TEST (do this before real data)
-----------------------------------------------------------------------------------
1. Log in as chairman. You should see "Choose the section": Car section / Truck business section.
2. Car section > Inventory > + Add a test car (chassis TEST-1). Purchases > + Add a purchase for it.
   Customers > + Add a test customer. Sales > + Add a sale of TEST-1 to that customer.
3. Inventory > "Vehicle file" on TEST-1: save Mushak details, upload a small PDF, download it again.
4. VAT / Mushak page: TEST-1 should show "Missing" until all items are present.
5. Truck section > Trucks > + Add a truck; Trips & Routes > + Add a trip (Own car delivery, car TEST-1);
   Truck expenses > + Add a repair. Check the Overview numbers.
6. Log in as manager and as accounts in another browser and check what each can see.
7. Delete the test records as chairman (Delete), or ask to start with a fresh database:
   phpMyAdmin > drop all tables > open setup.php again (upload it again for this).

-----------------------------------------------------------------------------------
DAILY USE - the order that works best
-----------------------------------------------------------------------------------
Truck section:  Trucks (add each truck once, with driver and document expiry dates)
                > Trips & Routes (pick the truck from the list; leave Driver blank to use the regular
                driver; for your own car deliveries pick the car) > Truck expenses (repairs, tyres,
                driver salary, insurance, tax token / fitness, route permit, parking, spare parts).
Car section:    Inventory (add each car by chassis number) > Purchases (foreign price x exchange rate
                + freight, duty and taxes, clearing = landed cost) > Customers (with NID and TIN) >
                Sales (one car sells once; the app blocks a second sale) > Vehicle file (VAT / Mushak
                details and documents: customer invoice PDF, NID, TIN, copies of 6.3 / 4.3 / 9.1) >
                VAT / Mushak page to see which sold cars are incomplete.

One search finds a car: Inventory search accepts chassis, customer name, phone, NID, TIN or invoice no.

WHO CAN DO WHAT (edit the PERM table at the top of index.php to change)
   chairman : everything; delete records; remove uploaded files; activity log; reset passwords
   manager  : cars, customers, purchases, sales, trucks, trips, truck expenses, VAT files
              (no car Expenses, no delete)
   accounts : customers, purchases, sales, car Expenses, truck expenses, VAT files
              (cars, trucks and trips view only; no delete)

SECURITY BUILT IN
   - Passwords hashed; 5 wrong passwords locks that user for 15 minutes
   - Auto logout after 30 minutes idle; secure session cookie; session renewed at login
   - CSRF protection on every form; prepared SQL statements; all output escaped; no scripts in pages
   - Deleted records are only hidden; every login, add, edit, delete, upload, download and export is
     in the activity log (chairman)
   - Uploaded documents live outside the web folder and are only served to logged-in staff
   - https only
Good habits: strong unique passwords, remove a person's login the day they leave (ask the developer
or use phpMyAdmin: users table > active = 0), keep the hosting panel password safe.

NOT INCLUDED (can be added): two-factor login, email alerts, photo of cars, separate logins per section.

NOTE ON MUSHAK 4.3: published guides describe 4.3 as the input-output coefficient declaration and the
purchase / sales registers as 6.1 and 6.2. The 4.3 field is included because it was requested; confirm
with your VAT consultant which forms apply, and rename labels in index.php if needed (search for "4.3").
