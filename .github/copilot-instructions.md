# PushIt - Web Push Notifications for REDAXO 5

PushIt is a REDAXO CMS addon that provides web push notification capabilities for both frontend users and backend administrators. It's built with PHP 8.0+ and uses the Minishlink WebPush library for push notification delivery.

Always reference these instructions first and fallback to search or bash commands only when you encounter unexpected information that does not match the info here.

## Working Effectively

### Prerequisites and Environment Setup
- Ensure PHP 8.0+ is available: `php --version`
- Ensure Composer is available: `composer --version`
- Ensure Node.js is available for JavaScript validation: `node --version`

### Bootstrap and Validate the Repository
- Install Composer dependencies: `composer install --no-dev --quiet`
  - Takes ~1 second. NEVER CANCEL. Set timeout to 30+ seconds.
  - WARNING: Shows "lock file is not up to date" - this is normal for REDAXO addons.
- Validate PHP syntax across all files: `find . -name "*.php" -exec php -l {} \; >/dev/null && echo "All PHP files validated successfully"`
  - Takes ~26 seconds for full validation. NEVER CANCEL. Set timeout to 120+ seconds.
- Validate JavaScript syntax: `find assets/ -name "*.js" -exec node -c {} \; && echo "All JS files validated successfully"`
  - Takes ~1 second. NEVER CANCEL. Set timeout to 30+ seconds.

### No Traditional Build Process
- This is a REDAXO addon - NO compilation or build steps required.
- Files are used directly by the REDAXO CMS.
- DO NOT look for npm, webpack, or other build tools - they don't exist in this project.

## Validation

### Code Quality Validation
- ALWAYS run PHP syntax validation before making changes: `php -l [filename]`
- ALWAYS run JavaScript syntax validation: `node -c [filename]` for JS files
- Static analysis configuration exists (psalm.xml) but requires newer PHP version
- NO automated test suite exists - manual testing required in REDAXO environment

### Manual Testing Requirements
- This addon requires a REDAXO CMS installation to function
- Cannot be tested standalone - it's an addon, not a standalone application
- VAPID keys must be configured for push notifications to work
- Push notifications require HTTPS in production environments

### Validation Scenarios After Changes
- Verify PHP syntax: `php -l [modified-file]`
- Check JavaScript syntax: `node -c [modified-file]` for JS files
- Test addon installation: Review install.php for database schema changes
- Verify composer dependencies: `composer validate` (expect warnings about composer.lock being outdated)

## Repository Structure

### Key Directories and Files
```
/home/runner/work/push_it/push_it/
├── .github/copilot-instructions.md    # This file
├── README.md                          # Comprehensive documentation
├── package.yml                        # REDAXO addon configuration
├── composer.json                      # PHP dependencies
├── boot.php                          # Addon bootstrap file
├── install.php                       # Database schema setup
├── lib/                              # PSR-4 autoloaded classes
│   ├── Api/                          # REST API endpoints
│   └── Service/                      # Business logic services
├── assets/                           # Frontend resources
│   ├── backend.js                    # Backend JavaScript
│   ├── frontend.js                   # Frontend JavaScript
│   ├── sw.js                         # Service Worker
│   └── lang/                         # Language files (de.js, en.js)
└── pages/                            # Admin interface pages
```

### Important Files to Check When Making Changes
- `boot.php` - Addon initialization and configuration
- `lib/Service/` - Core business logic
- `assets/frontend.js` - Frontend push notification handling
- `assets/backend.js` - Backend admin functionality
- `package.yml` - REDAXO addon metadata

## Common Tasks

### Making Code Changes
1. Always validate syntax before and after changes
2. Focus on the `lib/` directory for PHP business logic
3. Modify `assets/` for frontend/backend JavaScript changes
4. Update `pages/` for admin interface modifications

### Debugging and Development
- Check PHP error logs when making server-side changes
- Use browser developer tools for JavaScript debugging
- Service Worker debugging: Chrome `chrome://inspect/#service-workers`
- VAPID key validation is critical for push functionality

### Dependencies
- Main dependency: `minishlink/web-push` for push notifications
- No frontend build dependencies (no npm/webpack)
- Composer manages all dependencies

### Quick Validation Commands
```bash
# Quick PHP syntax check for single file
php -l path/to/file.php

# Quick JS syntax check for single file  
node -c path/to/file.js

# Validate all PHP files in lib directory only
find lib/ -name "*.php" -exec php -l {} \; >/dev/null && echo "Lib files OK"

# Check specific service file after changes
php -l lib/Service/NotificationService.php
```

## File Contents Reference

### Repository Root Structure
```
ls -la /home/runner/work/push_it/push_it/
total 120
drwxr-xr-x  7 runner docker  4096 Sep  8 00:15 .
drwxr-xr-x  3 runner docker  4096 Sep  8 00:14 ..
drwxr-xr-x  7 runner docker  4096 Sep  8 00:15 .git
-rw-r--r--  1 runner docker   102 Sep  8 00:15 .gitignore
-rw-r--r--  1 runner docker  3557 Sep  8 00:15 IMAGES_GUIDE.md
-rw-r--r--  1 runner docker  1090 Sep  8 00:15 LICENSE.md
-rw-r--r--  1 runner docker 11398 Sep  8 00:15 README.md
drwxr-xr-x  3 runner docker  4096 Sep  8 00:15 assets
-rw-r--r--  1 runner docker  4074 Sep  8 00:15 boot.php
-rw-r--r--  1 runner docker   585 Sep  8 00:15 composer.json
-rw-r--r--  1 runner docker 38162 Sep  8 00:15 composer.lock
-rw-r--r--  1 runner docker  2705 Sep  8 00:15 install.php
drwxr-xr-x  4 runner docker  4096 Sep  8 00:15 lib
-rw-r--r--  1 runner docker  1299 Sep  8 00:15 package.yml
drwxr-xr-x  2 runner docker  4096 Sep  8 00:15 pages
-rw-r--r--  1 runner docker  1394 Sep  8 00:15 psalm.xml
-rw-r--r--  1 runner docker   376 Sep  8 00:15 uninstall.php
-rw-r--r--  1 runner docker  2945 Sep  8 00:15 update.php
drwxr-xr-x 11 runner docker  4096 Sep  8 00:15 vendor
```

### Assets Directory
```
ls -la assets/
backend.js      # Backend admin functionality
frontend.js     # Frontend push notification handling  
icon.png        # Addon icon
icon.svg        # Vector addon icon
icon.txt        # Icon text representation
lang/           # Language files (de.js, en.js)
sw.js          # Service Worker for push notifications
```

### Library Structure
```
find lib/ -name "*.php"
./lib/Api/Unsubscribe.php          # Unsubscribe API endpoint
./lib/Api/Subscribe.php            # Subscribe API endpoint
./lib/Service/HistoryManager.php   # Notification history
./lib/Service/NotificationService.php # Core notification logic
./lib/Service/SendManager.php      # Send interface management
./lib/Service/SettingsManager.php  # Configuration management
./lib/Service/TokenService.php     # Token handling
./lib/Service/BackendNotificationManager.php # Backend notifications
./lib/Service/SubscriptionManager.php # Subscription management
```

## CRITICAL Reminders

- **NEVER CANCEL long-running commands** - Even 26 seconds for PHP validation is normal
- **NO BUILD PROCESS** - This is a PHP addon, not a compiled application  
- **REQUIRES REDAXO** - Cannot test functionality without REDAXO CMS installation
- **HTTPS REQUIRED** - Push notifications only work over HTTPS in production
- **VAPID KEYS NEEDED** - Must be configured for push notifications to function
- Set timeouts of 120+ seconds for PHP validation commands
- Set timeouts of 30+ seconds for dependency installation
- Always validate syntax changes immediately after making them

## Troubleshooting

### Common Issues and Solutions
- **Composer warnings about lock file**: Normal for REDAXO addons, can be ignored
- **"No syntax errors detected" for JS files**: This means the file is valid
- **Long PHP validation times**: Normal due to autoloading checks in vendor directory
- **Missing Node.js**: Required for JavaScript validation, install with `apt-get install nodejs`

### Expected Command Outputs
```bash
# Successful PHP validation
$ php -l boot.php
No syntax errors detected in boot.php

# Successful JS validation (no output means success)
$ node -c assets/frontend.js
(no output = success)

# Successful dependency installation (with expected warning)
$ composer install --no-dev --quiet
Warning: The lock file is not up to date with the latest changes in composer.json...
```