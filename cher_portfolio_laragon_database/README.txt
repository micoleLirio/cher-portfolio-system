CHER MICOLE PORTFOLIO - LARAGON DATABASE VERSION
=================================================

FILES
-----
index.php
style.css
script.js
db.php
setup.php
database.sql
admin_messages.php
profile-photo-2x2.jpg

WHAT THE DATABASE DOES
----------------------
The Contact form now saves messages into MySQL.

Saved information:
- Name
- Email
- Subject
- Message
- Read/Unread status
- Date and time submitted

The admin page can:
- View all messages
- Mark messages as Read or Unread
- Delete messages
- Change the admin password

EASY LARAGON SETUP
------------------
1. Extract the ZIP file.

2. Copy the folder:
   cher_portfolio_laragon_database

3. Paste it inside:
   C:\laragon\www\

4. Open Laragon.

5. Click:
   Start All

6. Open this address in your browser:
   http://localhost/cher_portfolio_laragon_database/setup.php

7. Click:
   INSTALL DATABASE

8. Open the portfolio:
   http://localhost/cher_portfolio_laragon_database/

ADMIN MESSAGES PAGE
-------------------
Open:
http://localhost/cher_portfolio_laragon_database/admin_messages.php

Default login:
Username: admin
Password: admin123

After signing in, use the Change Admin Password form.

DATABASE INFORMATION
--------------------
Database name:
db_cher_portfolio

Default Laragon MySQL:
Host: 127.0.0.1
Username: root
Password: blank

If your MySQL password is different, edit db.php and setup.php.

PHPMYADMIN ALTERNATIVE
----------------------
You can also import database.sql using phpMyAdmin.

1. Open Laragon.
2. Click Database.
3. Open phpMyAdmin.
4. Click Import.
5. Select database.sql.
6. Click Import or Go.

IMPORTANT
---------
Open index.php through localhost. Do not double-click it directly,
because PHP and MySQL only work through Laragon/Apache.
