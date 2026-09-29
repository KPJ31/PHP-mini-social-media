# Mini Social Network

Requires PHP 8.1+ with mysqli/mysqlnd, fileinfo, mbstring and sessions, and MySQL 8+ or a compatible MariaDB server.

For a new installation, import mini_social_network.sql, ensure uploads/ is writable by PHP, and run php -S 127.0.0.1:8000 from this directory. Do not reimport the schema into an existing database.

Database settings use DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASSWORD environment variables. Defaults are localhost:3306, mini_social_network, root, and an empty password. Set appropriate credentials on your server.

Run `php setup_admin.php` before using the updated app on an existing installation. This adds admin, suspension, and session-version columns and an audit table without removing existing data. The application supports the original schema and maps legacy default.png avatars to the included default.svg. Fresh installs also have cascading foreign keys on likes.

All state-changing requests require POST and a session CSRF token. Forms and JavaScript include this automatically. Chat is restricted to accepted friends. Images are limited to validated JPEG, PNG, and GIF files up to 2 MB.

Run python tests/integration.py --mysql-bin C:/path/to/mysql/bin for the integration suite. It initializes a separate temporary MySQL 8 instance, imports the schema there, starts a local PHP server, exercises HTTP flows, and stops both servers afterward. It does not use your configured database. Python, PHP, mysqld and mysql must be available. Test data and uploaded images are confined to a temporary application copy.

## Interface

The interface uses a light social layout with blue actions, system typography, mist backgrounds, and rounded white cards. Shared styles are in css/style.css and css/social.css, shared assets in ui_assets.php, navigation in navbar.php, and accessible UI behavior in js/ui.js. Existing PHP routes and forms remain in use.

Bootstrap, Poppins, and Boxicons are served locally. The mobile navigation supports Escape, keyboard focus containment, and focus restoration. Without JavaScript, the navigation remains visible. Reduced-motion preferences are respected.

For browser checks on this Windows setup, install Playwright with python -m pip install --target "%TEMP%/minisocial-ui-tools" playwright, then run python tests/integration.py --mysql-bin C:/path/to/mysql/bin --ui. The browser test uses installed Microsoft Edge and saves screenshots in tests/artifacts/ui. Change the executable path in tests/ui_browser.py if Edge is installed elsewhere.

The regression suite covers HTTP flows and browser checks at 320, 390, 768, 1440, and 1920 pixels. Browser coverage includes page overflow, field labels, main headings, mobile navigation, password visibility, image previews, posting, likes, comments, and chat. Test accounts and posts are created only in the temporary database.

## Administration and refreshed social UI

Run `php setup_admin.php` once from the project directory. It creates `admin@mini.com` with a randomly generated password printed only in the terminal. Save that password and sign in through `login.php`; administrators go directly to `admin.php`. Running setup again leaves existing credentials unchanged. An existing ordinary account with that email is never silently promoted. The setup route is inaccessible over HTTP.

The dashboard includes searchable, paginated accounts, posts, comments, messages, and an activity log. Administrators can edit names, emails and bios; reset passwords; suspend/restore members; edit/delete posts and comments; and delete messages or member accounts. Private messages are visible to administrators for moderation. Deleting an account removes its content. Administrator accounts are protected from deletion and suspension. Roles are checked from the database on every request; registration never accepts an admin role. Suspension and password resets invalidate previous sessions. Password hashes are never displayed.

The social interface uses a light sidebar, blue actions, photo-focused cards, community profile shortcuts, and working For you/Friends feed filters. Shared styles are in `css/social.css`; all required fonts, icons, scripts and styles are served locally. No CDN connection is required. Profile circles link to real profiles, not disappearing stories.

Run `python tests/integration.py --mysql-bin C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin --ui` for disposable database integration checks, admin authorization/moderation coverage, and browser/accessibility checks. The test database is independent of the configured application database.

## Management roles and notifications

Run `php setup_admin.php` when upgrading. The migration adds `staff_role`, `staff_permissions`, and the notifications table without changing existing credentials or content. Do not reimport the installation SQL into a populated database.

Administrators use a dedicated management workspace. Social feed, friends, posting, likes, comments, profiles, and personal chat routes are blocked for all management identities, including direct requests. Member accounts retain the social interface. Staff identities do not appear in member search or public profiles.

From **Team & permissions**, an administrator can create manager or employee accounts with an initial password, edit their details, reset passwords, suspend/restore or delete them, and assign permissions. Both roles start with only the selected grants; the title does not confer implicit access. Available grants are view/manage for members, posts, comments and private messages, plus activity-log viewing. A manage grant includes view. Team and permission management is reserved for the administrator. Changes to staff access invalidate existing sessions. Ordinary member management cannot modify staff or administrator identities. Each team member can update their own details in **My account**.

The notification bell updates every 20 seconds while the page is visible. The paginated inbox supports unread filtering, individual read actions, and mark-all-read. Notifications are in-app; email and operating-system push are not configured. Notifications start with new activity after this update, without inventing historical events.

Notification coverage includes registration/welcome, friend requests and acceptance, friends' new posts, likes, comments, messages, profile changes, team account/access changes, and management edits/removals/restorations/suspensions. Management staff receive activity notifications only for sections they can view. Revoking a grant hides historical notifications for that section. Private message bodies and passwords are not included in notifications. Recipient checks and CSRF protect read actions; duplicate requests/acceptances/likes do not create repeated events.

Additional checks in `tests/workspace_cases.py` cover staff route separation, forged actions, view-only permissions, permission revocation, staff protection, notification delivery/deduplication, cross-account read protection, and notification privacy.
