# Testing Checklist for Birkly Alpha

After branding changes (HaruCMS → Birkly) and security fixes, use this checklist to verify the system works correctly.

## Critical Functionality Tests

### 1. Authentication & Access Control
- [x] **Login/Logout**
  - [x] Can log in with valid credentials
  - [x] Cannot log in with invalid credentials
  - [x] Logout works and redirects to login page
  - [x] Session persists across page navigation
  - [x] 2FA works (if enabled)

- [x] **Access Control**
  - [x] Non-admin users cannot access admin panel
  - [x] Role-based permissions work (admin, editor, viewer)
  - [x] Redirects work correctly for unauthorized access

### 2. Admin Panel UI
- [ ] **Layout & Navigation**
  - [x] Sidebar navigation works on all pages
  - [x] User menu (avatar, dropdown) appears in top-right
  - [x] Notifications icon appears in top-right
  - [x] Search icon appears in top-right
  - [x] No `?>` tag visible on any page
  - [x] All pages load without JavaScript errors
  - [x] Responsive design works on mobile/tablet

- [x] **Page Loading**
  - [x] Dashboard loads correctly
  - [x] Content/Entries page loads
  - [x] Media library loads
  - [x] Users page loads
  - [x] Settings page loads
  - [x] Help page loads
  - [x] All plugin pages load (if plugins are active)

### 3. Content Management
- [ ] **Collections**
  - [ ] Can view list of collections
  - [ ] Can create new collection
  - [ ] Can edit existing collection
  - [ ] Can delete collection
  - [ ] Collection fields save correctly

- [ ] **Entries**
  - [ ] Can view entries list
  - [ ] Can create new entry
  - [ ] Can edit existing entry
  - [ ] Can delete entry
  - [ ] Can publish/unpublish entries
  - [ ] Entry editor loads all fieldtypes correctly
  - [ ] Rich text editor works
  - [ ] File uploads work
  - [ ] Image uploads work
  - [ ] Media library integration works

### 4. Media Management
- [ ] **Media Library**
  - [ ] Can upload files
  - [ ] Can view uploaded files
  - [ ] Can delete files
  - [ ] Can search/filter media
  - [ ] Image previews work
  - [ ] File metadata displays correctly

### 5. User Management
- [ ] **Users**
  - [ ] Can view user list
  - [ ] Can create new user
  - [ ] Can edit user details
  - [ ] Can delete user
  - [ ] Can change user roles
  - [ ] User invitations work (if enabled)
  - [ ] User avatars display correctly

- [ ] **Roles & Permissions**
  - [ ] Can view roles
  - [ ] Can create/edit roles
  - [ ] Role permissions work correctly
  - [ ] Users with different roles see appropriate UI

### 6. Settings
- [ ] **General Settings**
  - [ ] Can view settings page
  - [ ] Can save settings
  - [ ] Settings persist after page reload
  - [ ] Branding settings work (logo, project name)
  - [ ] Language settings work

- [ ] **Security Settings**
  - [ ] CSRF protection works
  - [ ] Security headers are set correctly
  - [ ] Password requirements work
  - [ ] Session timeout works

### 7. API Functionality
- [ ] **Public API**
  - [ ] Public API endpoints respond correctly
  - [ ] API authentication works (if required)
  - [ ] API returns correct data formats
  - [ ] CORS headers work (if configured)

- [ ] **Admin API**
  - [ ] All admin API endpoints work
  - [ ] API returns JSON correctly
  - [ ] Error handling works
  - [ ] API rate limiting works (if enabled)

### 8. Plugins
- [ ] **Plugin System**
  - [ ] Can view plugins list
  - [ ] Can activate/deactivate plugins
  - [ ] Plugin pages load correctly
  - [ ] Plugin settings save correctly
  - [ ] Plugin hooks work (if applicable)

### 9. Internationalization
- [ ] **Language Support**
  - [ ] Language switcher works
  - [ ] All text is translated correctly
  - [ ] Date/time formats work for different locales
  - [ ] RTL languages work (if applicable)

### 10. Error Handling
- [ ] **Error Pages**
  - [ ] 404 errors display correctly
  - [ ] 500 errors display correctly (in development)
  - [ ] Error messages are user-friendly
  - [ ] Error logging works (check logs)

### 11. Performance
- [ ] **Page Load Times**
  - [ ] Dashboard loads quickly (< 2 seconds)
  - [ ] Content pages load quickly
  - [ ] Media library loads efficiently
  - [ ] No memory leaks (check over time)

- [ ] **Caching**
  - [ ] Template caching works
  - [ ] API response caching works (if enabled)
  - [ ] Browser caching works correctly

### 12. Security
- [ ] **Input Validation**
  - [ ] XSS protection works (try injecting scripts)
  - [ ] SQL injection protection works
  - [ ] File upload validation works
  - [ ] CSRF tokens work on all forms

- [ ] **Output Security**
  - [ ] All user input is escaped in output
  - [ ] No sensitive data in HTML source
  - [ ] Security headers are present

### 13. Browser Compatibility
- [ ] **Cross-Browser Testing**
  - [ ] Works in Chrome/Edge (latest)
  - [ ] Works in Firefox (latest)
  - [ ] Works in Safari (latest)
  - [ ] Works on mobile browsers

### 14. Data Integrity
- [ ] **Database**
  - [ ] All data saves correctly
  - [ ] No data corruption
  - [ ] Database migrations work (if any)
  - [ ] Backup/restore works (if applicable)

### 15. Branding
- [ ] **Branding Elements**
  - [ ] "Birkly" appears instead of "HaruCMS" everywhere
  - [ ] Logo displays correctly (if custom logo set)
  - [ ] Favicon displays correctly
  - [ ] Project name displays correctly
  - [ ] No references to "HaruCMS" in UI

## Regression Tests

### Previously Fixed Issues
- [ ] `?>` tag does NOT appear at top of pages
- [ ] JavaScript syntax errors are resolved
- [ ] User menu, notifications, search appear in top-right
- [ ] No infinite console warnings
- [ ] All API endpoints return 200 (not 404)

### Known Issues to Verify Fixed
- [ ] All branding references updated
- [ ] Session functions use "birkly" prefix
- [ ] Security headers are set correctly
- [ ] CSRF protection works on all forms

## Quick Smoke Test (5 minutes)

If time is limited, at minimum test:
1. [ ] Can log in
2. [ ] Dashboard loads without errors
3. [ ] Can navigate to Content page
4. [ ] Can create/edit an entry
5. [ ] User menu appears in top-right
6. [ ] No `?>` tag visible
7. [ ] No JavaScript console errors
8. [ ] Can log out

## Notes

- Test with different user roles (admin, editor, viewer)
- Test with different browsers
- Test on different screen sizes
- Check browser console for errors on each page
- Check server logs for PHP errors
- Verify all API calls return expected responses

## Reporting Issues

When reporting issues, include:
- Browser and version
- User role
- Steps to reproduce
- Expected behavior
- Actual behavior
- Console errors (if any)
- Screenshots (if applicable)
