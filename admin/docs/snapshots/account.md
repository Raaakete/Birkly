# Account

## Summary

Your **account** is your own user profile in the Birkly admin: display name, email, password, optional avatar, optional **Birkly cloud account link**, and (if the installation supports it) two-factor authentication (2FA). You change these in the Account (or Profile) section, not in Settings—Settings are for site-wide configuration and other users. Local email + password login always works; cloud link is optional. Use a strong password and enable 2FA if available for extra security.

## Beginner

**Account** is where you manage **your** profile: the name and email you use to log in, your password, and optional profile picture. It’s separate from **Settings**, which is for things that affect the whole CMS (project name, other users, plugins). Only you (and in some cases an administrator) can change your account details.

**What you can change**

- **Display name** — The name shown in the admin (e.g. in the sidebar or next to “Last edited by”). This can be your full name or a nickname.  
  **Example:** You set your display name to “Jane” so that when you edit a post, others see “Last edited by Jane.”

- **Email** — This is usually your **login** (you sign in with email + password). Some setups let you change it (you might get a confirmation email); others require an administrator to change it. Use an email you have access to so you can receive password resets and invites.

- **Password** — You can set a new password. You’ll often be asked to enter your **current** password first. Choose a strong password (mix of letters, numbers, and symbols; avoid obvious words). Don’t reuse the same password you use elsewhere.

- **Avatar** — An optional profile picture. You can upload an image or paste a URL. It may appear in the sidebar or next to your name so your team can recognize who’s logged in.

- **Language** — If the admin supports multiple languages, you may be able to choose your preferred language here (e.g. English, German). The interface labels then appear in that language.

**Two-factor authentication (2FA)**

If your Birkly installation has 2FA, you can turn it on so that after entering your password you must also enter a **one-time code** from an app on your phone (e.g. Google Authenticator or Authy). That way, even if someone gets your password, they can’t log in without your phone.

- **Enable 2FA:** In Account (or a “Security” tab), click “Enable two-factor authentication.” You’ll see a **QR code**. Open your authenticator app, scan the code, and add the account. The app will show a 6-digit code. Enter it in Birkly to confirm and turn on 2FA. **Save the backup codes** somewhere safe—if you lose your phone, you can use one of these codes to log in and then disable or reset 2FA.
- **Log in with 2FA:** After entering email and password, the next screen asks for the 6-digit code. Open your app, get the current code, type it in, and submit.
- **Disable 2FA:** In Account, choose “Disable two-factor authentication.” You may need your password and a current code (or a backup code) to turn it off.
- **Lost phone:** Use a **backup code** to log in once, then disable 2FA and re-enable it with a new device. If you don’t have backup codes, contact an administrator—they may be able to reset 2FA for your account (if the system supports it).

**Logout**

Use **Logout** when you’re done, especially on a shared computer. That ends your session so the next person can’t use your account. Sessions may also expire after a period of inactivity (e.g. 30 minutes), depending on server configuration.

**Link Birkly cloud account (optional)**

Some installations let you connect a **Birkly cloud account** alongside your local CMS login. This does **not** replace your admin password — you still sign in with email and password on your server.

- **Link:** On the Account page, click **Link Birkly cloud account**. You are redirected to `cloud.birkly.io` (or your operator's OAuth URL), approve access, and return to the CMS. A **Linked** badge shows your cloud email.
- **Why link:** Marketplace install may sync `installation_owner_email` for support; future cloud services may use the link.
- **Unlink:** Click **Unlink** on Account. Local login is unchanged; only the cloud association is removed.
- **No link required:** Skip linking if you self-host only with local accounts.

**Who manages other users**

**Inviting** new users, **changing** someone’s role, or **deleting** users is done in **Settings → Users** (or similar) by an administrator. Your Account page is only for **your** profile. If you need another account (e.g. a new team member), ask an admin to send an invite.

## Advanced Users

**Account data:** Stored in users table or user config (email, password hash, name, avatar URL, 2FA secret if enabled). Password change: require current password, hash new password (e.g. bcrypt/argon2), update store. Email change may require verification (send token, confirm before update).

**2FA:** TOTP (e.g. RFC 6238); secret stored server-side, user scans QR (or enters key) into authenticator app. On login, after password check, require valid TOTP code (or backup code). Backup codes: one-time use, stored hashed; user shown once at 2FA enable. Reset 2FA: admin action or support flow that clears 2FA secret and optionally backup codes so user can log in with password only and re-enable.

**Session:** Session ID in cookie (httpOnly, secure in prod); server stores session data (user id, expiry). Logout invalidates session. Permissions may be cached in session or read from DB per request.

**Avatar:** Upload to media or user assets; store URL/path in user record. Or external URL if allowed.

**Cloud account link (P13):** State in `settings/cloud_account.json` (tokens stored server-side, chmod 0600). API: `api/cloud_account.php` — `start_link`, OAuth callback, `status`, `unlink`. `core/cloud_account.php` handles token refresh; status API omits secrets. Mock mode: `BIRKLY_CLOUD_ACCOUNT_MOCK=1` for CI. Hub tab reads `cloud_account_linked` from `api/marketplace.php`. Local `api/auth.php` login is unchanged when cloud is linked or unlinked.

## Related

- [Roles and permissions](roles-and-permissions.md)
- [Settings](settings.md)
- [Security](../07-operations/security.md)
