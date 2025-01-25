![laravel-mysql](https://github.com/user-attachments/assets/15b91c71-4fec-468a-b074-ed15eba2837c)
# Laravel Database Backup 💾📂🌐
A lightweight package for automated MySQL database backups in Laravel applications. This package seamlessly integrates with both local storage and Google Drive, providing a reliable solution for database backup management.

## Features
- Simple and automated MySQL database backups
- Multiple storage options:
  - Local storage
  - Google Drive integration
  - Simultaneous backup to multiple locations
- Command-line interface for easy management
- Automatic cleanup of old backups
- Customizable backup settings
- Minimal configuration required

## Installation

### Step 1: Install the Package
```bash
composer require marekmiklusek/laravel-database-backup
```

### Step 2: Publish Configuration
```bash
php artisan vendor:publish --tag=database-backup-config
```

## Local Backup Configuration
Backups are stored locally by default using the `local` disk.

## Usage

### Run a Backup
To create a new backup, run the following Artisan command:
```bash
php artisan db-backup:run
```

### Clean Up Old Backups
You can clean up old backups in two ways:
1. Automatically after each backup by setting `automatic` to true
2. Manually by running the following Artisan command:
```bash
php artisan db-backup:cleanup
```

## Google Drive Integration

### Step 1: Configure Google Drive
Add the following configuration to `config/filesystems.php`:
```php
'google' => [  // Can be renamed to your preferred disk name
    'driver' => 'google',
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'refresh_token' => env('GOOGLE_REFRESH_TOKEN'),
    'folder_id' => env('GOOGLE_FOLDER_ID'),
]
```
NOTE: you can customize the disk name (e.g., `google_db_backup` instead of `google`) 

### Step 2: Environment Variables
Add these variables to your `.env` file:
```env
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_REFRESH_TOKEN=your_refresh_token
GOOGLE_FOLDER_ID=your_folder_id
```

## Google Drive API Setup Guide

This guide will walk you through setting up Google Drive API credentials and obtaining the necessary tokens for integration with your Laravel application.

### Step 1: Create Google Cloud Project
1. Visit [Google Cloud Console](https://console.cloud.google.com)
2. Click "Select a project" → "New Project"
3. Enter a project name and click "CREATE"
4. Wait for the project to be created and select it

### Step 2: Enable Google Drive API
1. In the left sidebar, navigate to "APIs & Services" → "Library"
2. Search for "Google Drive API"
3. Click on "Google Drive API"
4. Click "ENABLE"

### Step 3: Configure OAuth Consent Screen
1. Go to "APIs & Services" → "OAuth consent screen"
2. Select "External" user type and click "CREATE"
3. Fill in the required information:
   - App name
   - User support email
   - Developer contact information
4. Click "SAVE AND CONTINUE"
5. Skip scopes screen by clicking "SAVE AND CONTINUE"
6. Add test users (your email) if in testing mode
7. Click "SAVE AND CONTINUE"

### Step 4: Create Credentials
For Web Application:
1. Go to "APIs & Services" → "Credentials"
2. Click "+ CREATE CREDENTIALS" → "OAuth client ID"
3. Select "Web application" as application type
4. Enter a name for your client
5. Under "Authorized redirect URIs" add:
   ```
   https://developers.google.com/oauthplayground
   ```
6. Click "CREATE"
7. A popup will show your Client ID and Client Secret
8. Add them to your `.env` file as `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`

For Desktop Application:
1. Go to "APIs & Services" → "Credentials"
2. Click "+ CREATE CREDENTIALS" → "OAuth client ID"
3. Select "Desktop app" as application type
4. Enter a name for your client
6. Click "CREATE"
7. A popup will show your Client ID and Client Secret
8. Add them to your `.env` file as `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`

### Step 5: Get Refresh Token
For Web Application:
1. Go to [Google OAuth Playground](https://developers.google.com/oauthplayground/)
2. Click the gear icon (⚙️) in the top right corner
3. Check "Use your own OAuth credentials"
4. Enter your:
   - OAuth Client ID
   - OAuth Client Secret
5. Click "Close"
6. In the left sidebar, find "Drive API v3"
7. Select `https://www.googleapis.com/auth/drive.file`
8. Click "Authorize APIs"
9. Log in with your Google account and accept permissions
10. Click "Exchange authorization code for tokens"
11. Copy the refresh token from the response and add it to your `.env` file as `GOOGLE_REFRESH_TOKEN`

For Desktop Application:
1. Open the following URL in your web browser (replace `YOUR_CLIENT_ID` with your actual client ID):
   https://accounts.google.com/o/oauth2/auth?client_id=YOUR_CLIENT_ID&redirect_uri=urn:ietf:wg:oauth:2.0:oob&response_type=code&scope=https://www.googleapis.com/auth/drive.file&access_type=offline&prompt=consent
   -  Using the scope `https://www.googleapis.com/auth/drive.file` allows your application to view 
      and manage Google Drive files and folders that were opened or created by the app
2. Sign in with your Google account and grant access to the requested permissions
3. Google will display an **authorization code** on the screen. Copy this code
4. In your terminal run the following `curl` command, to exchange the authorization code for tokens
   (replace YOUR_AUTHORIZATION_CODE, YOUR_CLIENT_ID, and YOUR_CLIENT_SECRET with the actual values):
   ```bash
   curl --request POST \
      --url https://oauth2.googleapis.com/token \
      --header 'Content-Type: application/x-www-form-urlencoded' \
      --data 'code=YOUR_AUTHORIZATION_CODE&client_id=YOUR_CLIENT_ID&client_secret=YOUR_CLIENT_SECRET&redirect_uri=urn:ietf:wg:oauth:2.0:oob&grant_type=authorization_code'
   ```
5. The response will include a refresh token. Copy it and store it in your `.env` file as `GOOGLE_REFRESH_TOKEN`

### Step 6: Get Google Drive Folder ID
1. Go to [Google Drive](https://drive.google.com)
2. Create a new folder or select an existing one for backups
3. Open the folder
4. Copy the folder ID from the URL
   - The URL will look like: `https://drive.google.com/drive/folders/1A2B3C4D5E6F7...`
   - The folder ID is the string after `/folders/`
5. Add this folder ID to your `.env` file as `GOOGLE_FOLDER_ID`

### That's it! 🚀 You're now ready to use this package — have fun and enjoy! 😊🎉

## Events

This package emits the following events that you can listen to in your application:

- `MarekMiklusek\LaravelDatabaseBackup\Events\BackupCreated`

Triggered when a database backup is successfully created.

- `MarekMiklusek\LaravelDatabaseBackup\Events\BackupFailed`

Triggered when a database backup fails.

## Common Issues

1. **Redirect URI Mismatch**: Make sure you've added the exact OAuth Playground URL to authorized redirect URIs
2. **Invalid Credentials**: Double-check all IDs and tokens are copied correctly
3. **Token Expiration**: Refresh tokens don't expire unless revoked, but if you create new credentials, you'll need a new refresh token
4. **Permission Issues**: Ensure the Google Drive API is enabled and the correct scope is selected

## Security Notes

- Keep all credentials secure and never commit them to version control
- Use environment variables for all sensitive information
- Consider implementing credential rotation for production environments

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
