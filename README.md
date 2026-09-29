# Mini Social Network

Requires PHP 8.1+ with mysqli/mysqlnd, fileinfo and sessions, and MySQL 8+ or a compatible MariaDB server.

For a new installation, import mini_social_network.sql, ensure uploads/ is writable by PHP, and run php -S 127.0.0.1:8000 from this directory. Do not reimport the schema into an existing database.

Database settings use DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASSWORD environment variables. Defaults are localhost:3306, mini_social_network, root, and an empty password. Set appropriate credentials on your server.

Existing installations need no schema migration for these fixes. The application supports the original schema and maps legacy default.png avatars to the included default.svg. Fresh installs also have cascading foreign keys on likes.

All state-changing requests require POST and a session CSRF token. Forms and JavaScript include this automatically. Chat is restricted to accepted friends. Images are limited to validated JPEG, PNG, and GIF files up to 2 MB.

Run python tests/integration.py --mysql-bin C:/path/to/mysql/bin for the integration suite. It initializes a separate temporary MySQL 8 instance, imports the schema there, starts a local PHP server, exercises HTTP flows, and stops both servers afterward. It does not use your configured database. Python, PHP, mysqld and mysql must be available. Test data and uploaded images are confined to a temporary application copy.
