# HaruCMS Update & Delivery System

## 🎯 Overview

HaruCMS features a comprehensive update and delivery system that allows seamless updates from GitHub releases without requiring server configuration or manual intervention.

## 🏗️ System Architecture

```
GitHub Repository → GitHub Actions → Release → CDN → CMS Update Check → Download & Install
     ↓                ↓              ↓        ↓           ↓              ↓
Version Tags    → Automated Build → GitHub  → jsDelivr → Admin Panel → Backup & Update
```

## 🔧 Components

### 1. **Version Management (`core/version.php`)**
- **Current Version Detection** - Reads from `VERSION` file
- **Update Checking** - Queries GitHub API for latest releases
- **Download Management** - Secure download with integrity verification
- **Installation Process** - Automated backup and update installation
- **Rollback Support** - Automatic backup creation before updates

### 2. **Update API (`api/updates.php`)**
- **Authentication** - Admin-only access with CSRF protection
- **Update Checking** - `check_updates` action
- **Download Management** - `download_update` action
- **Installation** - `install_update` action
- **Status Monitoring** - `get_update_status` action

### 3. **Admin Interface (`admin/templates/updates.html`)**
- **Version Display** - Current version and update status
- **Update Notifications** - Visual indicators for available updates
- **Progress Tracking** - Real-time update progress
- **Settings Management** - Update preferences and frequency
- **Update History** - Track of previous updates

### 4. **GitHub Integration**
- **Automated Releases** - GitHub Actions workflow
- **Version Tagging** - Semantic versioning (v1.0.0)
- **Package Creation** - Clean release packages
- **Checksum Generation** - SHA256 verification
- **CDN Distribution** - jsDelivr for fast downloads

## 🚀 How It Works

### **For Developers (You)**

1. **Make Changes** - Develop features, fix bugs
2. **Commit & Push** - Standard Git workflow
3. **Create Release** - Tag version (e.g., `v1.0.1`)
4. **Automated Build** - GitHub Actions creates release package
5. **CDN Distribution** - Package available via jsDelivr

### **For Users (CMS Installations)**

1. **Automatic Check** - CMS checks for updates periodically
2. **Notification** - Admin panel shows update availability
3. **Download** - Secure download with integrity verification
4. **Backup** - Automatic backup before installation
5. **Install** - Seamless update installation
6. **Verification** - Post-installation verification

## 📋 Setup Instructions

### **1. GitHub Repository Setup**

```bash
# Create GitHub repository
git init
git remote add origin https://github.com/yourusername/harucms.git

# Add files
git add .
git commit -m "Initial HaruCMS release"
git push -u origin main

# Create first release
git tag v1.0.0
git push origin v1.0.0
```

### **2. Configure Update URLs**

Edit `core/version.php`:

```php
private static $updateCheckUrl = 'https://api.github.com/repos/yourusername/harucms/releases/latest';
private static $downloadBaseUrl = 'https://cdn.jsdelivr.net/gh/yourusername/harucms@';
```

### **3. GitHub Actions Setup**

The `.github/workflows/release.yml` file handles:
- **Automatic Releases** - Triggered by version tags
- **Package Creation** - Clean release packages
- **Checksum Generation** - SHA256 verification
- **Asset Upload** - Release files and checksums

### **4. CDN Configuration**

jsDelivr automatically serves files from GitHub:
- **Release Files** - `https://cdn.jsdelivr.net/gh/yourusername/harucms@v1.0.1/harucms.zip`
- **Latest Release** - `https://cdn.jsdelivr.net/gh/yourusername/harucms@latest/harucms.zip`

## 🔒 Security Features

### **1. Integrity Verification**
- **SHA256 Checksums** - Verify download integrity
- **File Size Validation** - Prevent corrupted downloads
- **ZIP Validation** - Ensure valid package format
- **Required Files Check** - Verify essential files exist

### **2. Authentication & Authorization**
- **Admin Only** - Update operations require admin privileges
- **CSRF Protection** - Prevent cross-site request forgery
- **Session Validation** - Secure session management
- **Rate Limiting** - Prevent abuse

### **3. Backup & Rollback**
- **Automatic Backup** - Create backup before updates
- **Rollback Support** - Restore from backup on failure
- **Data Preservation** - Protect user data during updates
- **Cleanup** - Remove temporary files after installation

## 📊 Update Process Flow

### **1. Update Check**
```
Admin Panel → API Call → GitHub API → Version Comparison → Status Display
```

### **2. Download Process**
```
Download Request → CDN Download → Integrity Check → Temporary Storage
```

### **3. Installation Process**
```
Backup Creation → File Extraction → File Replacement → Version Update → Cleanup
```

## 🎛️ Admin Panel Features

### **Update Status**
- **Current Version** - Display installed version
- **Latest Version** - Show available version
- **Update Available** - Visual indicator
- **Release Notes** - Display changelog

### **Update Controls**
- **Check for Updates** - Manual update check
- **Download Update** - Download latest version
- **Install Update** - Install downloaded update
- **Update History** - Track previous updates

### **Settings**
- **Auto-check Frequency** - Daily, weekly, monthly, manual
- **Notifications** - Show update notifications
- **Auto-backup** - Create backups before updates
- **Update Preferences** - Configure update behavior

## 🔧 Configuration Options

### **Update Settings**
```php
// In core/version.php
private static $currentVersion = '1.0.0';
private static $versionFile = __DIR__ . '/../VERSION';
private static $updateCheckUrl = 'https://api.github.com/repos/yourusername/harucms/releases/latest';
private static $downloadBaseUrl = 'https://cdn.jsdelivr.net/gh/yourusername/harucms@';
```

### **Admin Settings**
- **Check Frequency** - How often to check for updates
- **Notification Settings** - When to show update notifications
- **Backup Preferences** - Automatic backup settings
- **Update Behavior** - Manual vs automatic updates

## 🚨 Error Handling

### **Common Issues**
- **Network Errors** - Handle connection failures gracefully
- **Download Failures** - Retry mechanism with exponential backoff
- **Installation Errors** - Automatic rollback to previous version
- **Permission Issues** - Clear error messages and solutions

### **Recovery Procedures**
- **Failed Downloads** - Clean up temporary files
- **Installation Failures** - Restore from backup
- **Corrupted Updates** - Re-download and verify
- **Permission Problems** - Guide user through fixes

## 📈 Monitoring & Logging

### **Security Logging**
- **Update Attempts** - Log all update operations
- **Download Events** - Track download activities
- **Installation Logs** - Record installation process
- **Error Tracking** - Monitor and log errors

### **Performance Monitoring**
- **Download Speed** - Track download performance
- **Installation Time** - Monitor update duration
- **Success Rates** - Track update success rates
- **User Feedback** - Collect update experience data

## 🔮 Future Enhancements

### **Planned Features**
- **Delta Updates** - Only download changed files
- **Rolling Updates** - Gradual rollout of updates
- **A/B Testing** - Test updates with subset of users
- **Update Scheduling** - Schedule updates for maintenance windows

### **Advanced Features**
- **Plugin Updates** - Separate plugin update system
- **Theme Updates** - Theme-specific update mechanism
- **Database Migrations** - Handle database schema changes
- **Multi-site Updates** - Update multiple installations

## 📚 Best Practices

### **For Developers**
- **Semantic Versioning** - Use proper version numbering
- **Release Notes** - Provide clear changelog
- **Testing** - Test updates thoroughly before release
- **Backward Compatibility** - Maintain API compatibility

### **For Users**
- **Regular Backups** - Create backups before updates
- **Test Environment** - Test updates in staging first
- **Monitor Logs** - Check logs after updates
- **Report Issues** - Report problems promptly

## 🆘 Troubleshooting

### **Update Check Fails**
- Check internet connection
- Verify GitHub API access
- Check firewall settings
- Review error logs

### **Download Fails**
- Check disk space
- Verify permissions
- Check network connectivity
- Review download logs

### **Installation Fails**
- Check file permissions
- Verify disk space
- Review installation logs
- Restore from backup

## 📞 Support

For issues with the update system:
- **GitHub Issues** - Report bugs and request features
- **Documentation** - Check this guide for solutions
- **Community** - Ask questions in discussions
- **Professional Support** - Contact for enterprise support

---

This update system ensures HaruCMS can be easily maintained and updated while maintaining the core principles of simplicity, security, and user-friendliness.
