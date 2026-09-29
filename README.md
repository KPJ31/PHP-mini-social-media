# Mini Social Network

Requires PHP 8.1+ with mysqli/mysqlnd, fileinfo, mbstring and sessions, and MySQL 8+ or a compatible MariaDB server.

For a new installation, import mini_social_network.sql, ensure uploads/ is writable by PHP, and run php -S 127.0.0.1:8000 from this directory. Do not reimport the schema into an existing database.

Database settings use DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASSWORD environment variables. Defaults are localhost:3306, mini_social_network, root, and an empty password. Set appropriate credentials on your server.

Existing installations need no schema migration for these fixes. The application supports the original schema and maps legacy default.png avatars to the included default.svg. Fresh installs also have cascading foreign keys on likes.

All state-changing requests require POST and a session CSRF token. Forms and JavaScript include this automatically. Chat is restricted to accepted friends. Images are limited to validated JPEG, PNG, and GIF files up to 2 MB.

Run python tests/integration.py --mysql-bin C:/path/to/mysql/bin for the integration suite. It initializes a separate temporary MySQL 8 instance, imports the schema there, starts a local PHP server, exercises HTTP flows, and stops both servers afterward. It does not use your configured database. Python, PHP, mysqld and mysql must be available. Test data and uploaded images are confined to a temporary application copy.

## Interface

The interface follows the Bootstrap adaptation of UI_STYLE_GUIDE.md: navy navigation, Poppins, blue-violet accents, gold actions, mist backgrounds, and rounded white cards. Shared styles are in css/style.css, shared assets in ui_assets.php, navigation in navbar.php, and accessible UI behavior in js/ui.js. Existing PHP routes and forms remain in use.

Bootstrap, Poppins, and Boxicons load from their existing/public CDNs, so an internet connection is needed for those assets. The mobile navigation supports Escape, keyboard focus containment, and focus restoration. Without JavaScript, the navigation remains visible. Reduced-motion preferences are respected.

For browser checks on this Windows setup, install Playwright with python -m pip install --target "%TEMP%/minisocial-ui-tools" playwright, then run python tests/integration.py --mysql-bin C:/path/to/mysql/bin --ui. The browser test uses installed Microsoft Edge and saves screenshots in tests/artifacts/ui. Change the executable path in tests/ui_browser.py if Edge is installed elsewhere.

Verified with the 67 HTTP regression checks and browser checks at 320, 390, 768, 1440, and 1920 pixels. Browser coverage includes page overflow, field labels, main headings, mobile navigation, password visibility, image previews, posting, likes, comments, and chat. Test accounts and posts are created only in the temporary database.
