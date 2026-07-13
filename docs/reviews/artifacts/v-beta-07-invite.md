# V-BETA-07 — Team invite accept flow

**Date:** 2026-07-13 · **Phase:** P28-M17  
**Journey:** J-INVITE

Admin creates invitation, team member accepts via invite.php, logs in.

## Prerequisites

- Birkly dev server running
- Clean `users/` and `data/invitations/` directories

## Steps

1. **Admin creates invitation**
   - Admin account: `journey-invite-admin-{pid}@birkly.test`
   - Skip onboarding wizard
   - API: `POST /api/invitations.php`
   - Request body:
     - `email`: `journey-invitee-{pid}@birkly.test`
     - `role`: `editor`
     - `expires_days`: 7
     - CSRF token
   - Response includes invitation `token`

2. **Invitation email (simulated)**
   - In production: email sent to invitee with token URL
   - Test: directly navigate to invite URL
   - URL format: `/invite.php?token={token}`

3. **Invite accept page**
   - Navigate to `/invite.php?token={token}`
   - Page shows invitation details:
     - Invited by: admin display name
     - Role: editor
   - Password setup form displayed
   - Fields: password, confirm_password

4. **Accept invitation**
   - POST to `/invite.php?token={token}`
   - Form data: password, confirm_password
   - Server validates token:
     - Not expired
     - Not already used
     - Email not already registered
   - Creates user account with editor role
   - Success message displayed

5. **Verify user file created**
   - User JSON file: `users/{md5(email)}.json`
   - Contains: email, hashed password, role, created_at
   - Invitation marked as used in `/data/invitations/`

6. **Invited user login**
   - Navigate to `/admin/login.php`
   - Login with invited email and password
   - Session established
   - Dashboard accessible: `/admin/index.php?page=dashboard`
   - User role permissions enforced

## Automated verification

```bash
./scripts/journey-runner.php --journey J-INVITE
```

Journey creates admin, generates invitation via API, navigates to invite.php, accepts invitation, verifies user file created, logs in as invited user, confirms dashboard access.

## Status

✅ Automated journey passes (J-INVITE)
