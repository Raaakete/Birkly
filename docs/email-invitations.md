# Email Invitation System

## Overview
Birkly includes a built-in email invitation system that allows administrators to invite new users via email. Users receive an invitation link where they can set their own password.

## How It Works

### 1. **Invitation Creation**
- Admin goes to **Settings → Users tab**
- Clicks **"Invite User"** button
- Fills out email, role, and expiration time
- System generates secure invitation token
- Email is sent via PHP's built-in `mail()` function

### 2. **Email Delivery**
- Uses PHP's native `mail()` function (no external dependencies)
- Works on most web servers out of the box
- HTML email with invitation link
- Includes expiration date and role information

### 3. **Invitation Acceptance**
- User clicks link in email → goes to `/invite.php`
- User sets their password
- Account is created automatically
- Invitation is marked as "accepted"

## File Structure
```
/data/invitations/          # Invitation storage (JSON files)
/api/invitations.php        # Invitation API
/invite.php                 # Public invitation acceptance page
```

## Email Configuration

### Default Setup
The system uses PHP's built-in `mail()` function, which works on most servers without additional configuration.

### Server Requirements
- PHP with `mail()` function enabled
- Server must be able to send emails (most shared hosting supports this)

### Custom Email Configuration
If you need custom SMTP settings, you can modify the `sendInvitationEmail()` function in `/api/invitations.php` to use PHPMailer or similar libraries.

## Security Features

### Token Security
- 64-character random tokens (32 bytes hex-encoded)
- Tokens are single-use and expire automatically
- Stored securely in file system

### Validation
- Email format validation
- Password strength requirements (minimum 6 characters)
- Duplicate user prevention
- Expiration checking

### Logging
- All invitation events are logged
- Failed attempts are tracked
- Security events are recorded

## Usage

### For Administrators
1. Go to **Settings → Users**
2. Click **"Invite User"**
3. Enter email address and select role
4. Choose expiration time (1, 7, or 30 days)
5. Click **"Send Invitation"**

### For Invited Users
1. Check email for invitation
2. Click invitation link
3. Set password (minimum 6 characters)
4. Confirm password
5. Click **"Create Account"**
6. Login to admin panel

## Troubleshooting

### Email Not Sending
- Check server mail configuration
- Verify `mail()` function is enabled
- Check server logs for mail errors
- Test with a simple PHP mail script

### Invitation Links Not Working
- Check if `/invite.php` is accessible
- Verify invitation hasn't expired
- Check invitation status in logs

### User Creation Fails
- Check file permissions on `/users/` directory
- Verify invitation token is valid
- Check for duplicate email addresses

## Privacy & Compliance
- Invitations are stored as JSON files (no database required)
- Email addresses are only used for invitations
- Users can set their own passwords
- Invitations expire automatically
- All actions are logged for audit purposes
