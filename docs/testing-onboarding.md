# Testing the Onboarding System

This guide explains how to test the onboarding system in HaruCMS.

## Quick Test Methods

### Method 1: Simulate First-Time Setup (Recommended)

The easiest way to test is to temporarily clear your content to simulate a fresh installation:

1. **Backup your existing content** (if you want to keep it):
   ```bash
   cp -r content/collections content/collections.backup
   ```

2. **Clear collections** (or just entries):
   ```bash
   # Option A: Remove all entries but keep collections
   find content/collections -name "*.json" -path "*/entries/*" -delete
   
   # Option B: Remove specific collection entries
   rm -rf content/collections/blog/entries/*.json
   ```

3. **Clear onboarding progress**:
   ```bash
   # Remove user onboarding file to reset progress
   rm -f users/*_onboarding.json
   ```

4. **Log in to admin panel**:
   - Visit `http://localhost:8001/admin/index.php` (or your server URL)
   - You should be redirected to the onboarding wizard automatically

### Method 2: Use the Reset Script

A helper script is available to quickly reset onboarding state:

```bash
php scripts/reset-onboarding.php
```

This script will:
- Clear all entry files (keeps collections)
- Remove onboarding progress
- Keep your demo content packs intact

### Method 3: Fresh Installation Test

If you have a development environment, test with a completely fresh installation:

1. Copy HaruCMS to a new directory
2. Set up a new PHP server
3. Visit the admin panel
4. You should see the onboarding wizard

## Testing Checklist

### Step 1: Welcome Screen
- [ ] Onboarding wizard appears automatically
- [ ] Welcome message displays correctly
- [ ] "Get Started" button works
- [ ] "Skip Setup" button works (should skip to dashboard)

### Step 2: Site Information
- [ ] Form displays correctly
- [ ] Site title field works
- [ ] Site description field works
- [ ] "Back" button returns to welcome
- [ ] "Continue" button saves data and proceeds

### Step 3: Demo Content Selection
- [ ] Demo content packs are listed
- [ ] "Blog Demo" pack shows correct info
- [ ] "Portfolio Demo" pack shows correct info
- [ ] "Start Fresh" option works
- [ ] Selecting a pack highlights it
- [ ] "Continue" button works (installs or skips)

### Step 4: Demo Template Selection
- [ ] Demo templates are listed
- [ ] "Minimal Blog" template shows correctly
- [ ] "Corporate" template shows correctly
- [ ] "Skip for Now" option works
- [ ] Selecting template highlights it
- [ ] "Continue" button works

### Step 5: Completion Screen
- [ ] Success animation displays
- [ ] Next steps cards are visible
- [ ] Links to Content, Settings, Docs work
- [ ] "Go to Dashboard" button works

### Dashboard Testing
- [ ] Welcome dashboard shows for new users
- [ ] Checklist displays all items
- [ ] Progress bar updates correctly
- [ ] Completed items show checkmarks
- [ ] Quick action buttons work
- [ ] Creating a collection updates checklist
- [ ] Adding an entry updates checklist
- [ ] Normal dashboard shows after onboarding complete

## Manual Testing Steps

### Test 1: Full Onboarding Flow

1. **Reset onboarding state**:
   ```bash
   rm -f users/*_onboarding.json
   find content/collections -name "*.json" -path "*/entries/*" -delete
   ```

2. **Log in to admin**:
   - Should redirect to `/admin/index.php?page=onboarding`

3. **Complete Welcome Step**:
   - Click "Get Started"

4. **Complete Site Info**:
   - Enter site title: "Test Site"
   - Enter description: "Testing HaruCMS"
   - Click "Continue"

5. **Select Demo Content**:
   - Click on "Blog Demo"
   - Click "Continue"
   - Verify content is installed

6. **Select Demo Template**:
   - Click on "Minimal Blog"
   - Click "Continue"
   - Verify template is installed

7. **Complete Onboarding**:
   - Click "Go to Dashboard"
   - Should see welcome dashboard

### Test 2: Skip Demo Content

1. Reset onboarding state
2. Complete Welcome and Site Info steps
3. On Demo Content step, click "Start Fresh"
4. On Demo Template step, click "Skip for Now"
5. Complete onboarding
6. Verify no demo content/templates were installed

### Test 3: Skip Entire Onboarding

1. Reset onboarding state
2. Log in to admin
3. On Welcome screen, click "Skip Setup"
4. Should redirect to dashboard immediately
5. Welcome dashboard should still show (onboarding marked as skipped)

### Test 4: Welcome Dashboard Checklist

1. After completing onboarding, verify welcome dashboard shows
2. Check that checklist items update:
   - Create a collection → "Create your first collection" should check
   - Add an entry → "Add your first entry" should check
   - Upload media → "Upload media files" should check
3. Progress bar should update as items complete
4. After all items complete, dashboard should switch to normal view

## API Testing

You can also test the APIs directly:

### Get Onboarding Status
```bash
curl -X GET "http://localhost:8001/api/onboarding.php?action=status" \
  -H "Cookie: PHPSESSID=your_session_id"
```

### List Demo Content Packs
```bash
curl -X GET "http://localhost:8001/api/demo.php?action=list_content" \
  -H "Cookie: PHPSESSID=your_session_id"
```

### Install Demo Content
```bash
curl -X POST "http://localhost:8001/api/demo.php?action=install_content" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"pack_id": "blog"}'
```

### Update Progress
```bash
curl -X POST "http://localhost:8001/api/onboarding.php?action=update_progress" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"step": 2, "setup_wizard_completed": true}'
```

## Debugging

### Check Onboarding Status
```php
// Add to admin/index.php temporarily
$onboardingStatus = getOnboardingStatus($_SESSION['user']['email']);
var_dump($onboardingStatus);
```

### Check if First-Time Setup
```php
// Check detection logic
var_dump(isFirstTimeSetup());
```

### View User Onboarding File
```bash
# Find your user's onboarding file
ls -la users/*_onboarding.json

# View its contents
cat users/YOUR_USER_ID_onboarding.json
```

### Check Browser Console
Open browser DevTools (F12) and check:
- Console for JavaScript errors
- Network tab for API call responses
- Application tab for session/cookies

## Expected Behavior

### First Visit (No Content)
- ✅ Automatically redirects to onboarding
- ✅ Shows welcome screen
- ✅ `is_first_time` = true
- ✅ `completed` = false

### During Onboarding
- ✅ Progress tracked per step
- ✅ Can navigate back/forward
- ✅ Can skip at any time
- ✅ Demo content/templates install correctly

### After Completion
- ✅ Redirects to dashboard
- ✅ Shows welcome dashboard (if first-time)
- ✅ Checklist visible
- ✅ Can manually complete onboarding from settings (if needed)

### After All Checklist Items Complete
- ✅ Dashboard switches to normal view
- ✅ No more welcome dashboard
- ✅ Can access all features normally

## Common Issues

### Issue: Onboarding doesn't appear
**Solution**: 
- Check if content exists: `ls content/collections/*/entries/*.json`
- Check onboarding file: `cat users/*_onboarding.json`
- Verify `isFirstTimeSetup()` returns true

### Issue: Demo content not installing
**Solution**:
- Check demo-content directory exists: `ls demo-content/`
- Verify manifest.json files: `cat demo-content/blog/manifest.json`
- Check file permissions: `ls -la demo-content/`
- Check API response in browser DevTools Network tab

### Issue: Checklist not updating
**Solution**:
- Refresh dashboard page
- Check if collection/entry was actually created
- Verify onboarding data structure matches expected format
- Check browser console for JavaScript errors

### Issue: Stuck on onboarding step
**Solution**:
- Check JavaScript errors in console
- Verify onboarding.js is loaded: `ls admin/js/onboarding.js`
- Clear browser cache
- Try completing step manually via API

## Reset Onboarding (Development)

To reset onboarding for testing:

```bash
# Remove all user onboarding files
rm -f users/*_onboarding.json

# Remove all entries (keeps collections)
find content/collections -name "*.json" -path "*/entries/*" -delete

# Optionally remove all collections too
rm -rf content/collections/*
```

## Next Steps After Testing

Once you've tested the onboarding flow:

1. **Test edge cases**:
   - User closes browser mid-onboarding
   - Network errors during installation
   - Invalid demo pack IDs
   - Missing demo content directories

2. **Test user experience**:
   - Is the flow intuitive?
   - Are instructions clear?
   - Is progress visible?
   - Are errors handled gracefully?

3. **Performance testing**:
   - Does onboarding load quickly?
   - Does demo content install efficiently?
   - Are there any memory issues with large packs?

4. **Accessibility testing**:
   - Keyboard navigation works
   - Screen reader compatibility
   - Color contrast meets standards
   - Focus indicators visible

