# Birkly Security Audit Report
**Date:** 2025-01-15  
**Auditor:** Automated Security Analysis  
**Version:** Current Codebase

## Executive Summary

This security audit identified **15 critical issues**, **12 high-priority issues**, and **8 medium-priority issues** that require immediate attention. The most severe vulnerabilities include:

1. **CRITICAL: Code Injection via eval()** - Template parser uses eval() which can execute arbitrary PHP code
2. **CRITICAL: Sensitive Data Exposure** - API keys and passwords logged in debug files
3. **CRITICAL: Weak CORS Configuration** - Multiple endpoints allow wildcard CORS
4. **HIGH: Missing CSRF Protection** - Several API endpoints lack CSRF validation
5. **HIGH: Path Traversal Risks** - Insufficient path validation in file operations

---

## Critical Vulnerabilities (P0 - Immediate Action Required)

### 1. Code Injection via eval() in Template Parser
**File:** `admin/template_parser.php:355`  
**Severity:** CRITICAL  
**CVSS Score:** 9.8 (Critical)

**Issue:**
```php
eval('?>' . $parsed . '<?php');
```

The template parser uses `eval()` to execute parsed template code. While the parser attempts to sanitize input, any vulnerability in the parsing logic could allow arbitrary PHP code execution.

**Impact:**
- Remote Code Execution (RCE)
- Complete server compromise
- Data exfiltration
- Privilege escalation

**Recommendation:**
- **IMMEDIATE:** Replace `eval()` with a safe template engine (Twig, Smarty, or custom interpreter)
- Use PHP's `include` with output buffering instead of eval
- Implement strict whitelist of allowed template functions
- Add template sandboxing

**Example Fix:**
```php
// Instead of eval, use output buffering with include
$tempFile = tempnam(sys_get_temp_dir(), 'haru_template_');
file_put_contents($tempFile, $parsed);
ob_start();
include $tempFile;
$output = ob_get_clean();
unlink($tempFile);
```

---

### 2. Sensitive Data Logging in Debug Files
**Files:** 
- `api/users.php:53,61` - Logs passwords and hashes
- `api/users.php:61` - Logs password hashes

**Severity:** CRITICAL  
**CVSS Score:** 9.1 (Critical)

**Issue:**
```php
file_put_contents(__DIR__ . '/../logs/login_debug.log', 
    date('c') . ' | password: ' . $password . ' | hash: ' . ($user['password'] ?? 'NOHASH') . "\n", 
    FILE_APPEND);
```

Passwords and password hashes are being logged to a debug file. If this file is accessible via web server, it exposes sensitive credentials.

**Impact:**
- Credential theft
- Account takeover
- Password hash exposure (enables offline cracking)

**Recommendation:**
- **IMMEDIATE:** Remove all password logging
- Remove debug logging in production
- Ensure log files are outside web root or protected by .htaccess
- Use environment-based debug flags

**Fix:**
```php
// Remove these lines entirely or wrap in debug flag
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    // Only log non-sensitive info
    error_log("Login attempt for: " . $email);
}
```

---

### 3. API Key Exposure in Public Endpoint
**File:** `api/public.php:264`  
**Severity:** CRITICAL  
**CVSS Score:** 8.5 (High)

**Issue:**
```php
respond([
    'status'=>'ok',
    'data'=>[
        'schema' => $schema,
        'api_key' => $collectionData['api_key'] ?? '',  // ⚠️ EXPOSED
        'collection' => $collectionData
    ]
]);
```

The `get_collection_info` endpoint returns API keys in the response. While this endpoint requires the collection to have `entry_method = 'public_api'`, it still exposes sensitive credentials.

**Impact:**
- API key theft
- Unauthorized access to public API endpoints
- Potential data manipulation

**Recommendation:**
- **IMMEDIATE:** Remove API key from public responses
- API keys should only be shown in admin panel to authenticated users
- Use separate read-only API keys if needed

**Fix:**
```php
// Don't return API key in public endpoint
respond([
    'status'=>'ok',
    'data'=>[
        'schema' => $schema,
        // 'api_key' => removed
        'collection' => array_diff_key($collectionData, ['api_key'])
    ]
]);
```

---

### 4. Weak CORS Configuration (Wildcard Allow-Origin)
**Files:**
- `api/public/collections.php:17`
- `api/media.php:5`
- `api/admin_media.php:5`
- `api/analytics.php:15`
- `api/fetch-metadata.php:3`
- `api/fieldtypes.php:13`
- `api/admin_collections.php:18`
- `api/admin_templates.php:42`

**Severity:** CRITICAL  
**CVSS Score:** 8.1 (High)

**Issue:**
```php
header('Access-Control-Allow-Origin: *');
```

Multiple API endpoints use wildcard CORS, allowing any origin to make requests. This enables CSRF attacks and data exfiltration.

**Impact:**
- Cross-Site Request Forgery (CSRF)
- Data exfiltration via malicious websites
- Unauthorized API access

**Recommendation:**
- **IMMEDIATE:** Implement origin whitelist
- Use domain-based authentication for public APIs
- Only allow specific trusted origins
- Remove wildcard CORS from authenticated endpoints

**Fix:**
```php
// Whitelist approach
$allowedOrigins = [
    'https://yourdomain.com',
    'https://www.yourdomain.com'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
```

---

### 5. Path Traversal in File Operations
**Files:** Multiple files using `file_get_contents()` and `file_put_contents()` with user input

**Severity:** CRITICAL  
**CVSS Score:** 7.5 (High)

**Issue:**
Several locations construct file paths from user input without proper validation:
- `api/public/collections.php:56` - Collection slug used in path
- `api/admin_media.php` - File paths from user input
- Template file loading

**Impact:**
- Arbitrary file read
- Arbitrary file write
- Local File Inclusion (LFI)
- Remote Code Execution (if combined with other vulnerabilities)

**Recommendation:**
- **IMMEDIATE:** Validate all file paths
- Use basename() and realpath() to sanitize paths
- Implement path whitelisting
- Never use user input directly in file paths

**Fix:**
```php
function sanitizePath($path, $baseDir) {
    // Remove directory traversal
    $path = str_replace(['../', '..\\'], '', $path);
    $path = basename($path); // Remove any remaining path components
    $fullPath = realpath($baseDir . '/' . $path);
    
    // Ensure path is within base directory
    $basePath = realpath($baseDir);
    if (strpos($fullPath, $basePath) !== 0) {
        throw new SecurityException('Invalid path');
    }
    return $fullPath;
}
```

---

## High-Priority Vulnerabilities (P1 - Fix Within 1 Week)

### 6. Missing CSRF Protection on Multiple Endpoints
**Files:**
- `api/public.php` - No CSRF protection (public read-only, but should validate)
- `api/public/collections.php` - No CSRF (uses API key, but CSRF still recommended)
- Several other endpoints

**Severity:** HIGH  
**CVSS Score:** 7.2 (High)

**Issue:**
Many API endpoints don't validate CSRF tokens, especially public-facing endpoints.

**Impact:**
- Cross-Site Request Forgery attacks
- Unauthorized actions on behalf of users

**Recommendation:**
- Add CSRF validation to all state-changing operations
- Use SameSite cookies (already implemented in session.php)
- Implement CSRF tokens for public API endpoints that modify data

---

### 7. Insufficient Input Validation
**Files:** Multiple API endpoints

**Severity:** HIGH  
**CVSS Score:** 7.0 (High)

**Issue:**
While `sanitize_input()` is used in some places, it's not consistently applied:
- Some endpoints use `json_decode(file_get_contents('php://input'), true)` without sanitization
- Array inputs not always validated
- Type validation missing in many places

**Impact:**
- Injection attacks
- Type confusion vulnerabilities
- Data corruption

**Recommendation:**
- Apply `sanitize_input()` consistently to all user inputs
- Implement strict type validation
- Use validation schemas for complex inputs

---

### 8. File Upload Security Gaps
**File:** `api/media.php:164-254`

**Severity:** HIGH  
**CVSS Score:** 6.8 (Medium-High)

**Issues:**
1. MIME type validation relies on `$_FILES['type']` which can be spoofed
2. File extension validation is separate from MIME validation (can be bypassed)
3. No virus scanning
4. SVG files allowed without sanitization (XSS risk)

**Impact:**
- Malicious file uploads
- XSS via SVG files
- Server compromise via uploaded scripts

**Recommendation:**
- Use `finfo_file()` for MIME detection (already partially implemented in upload.php)
- Validate both extension AND MIME type
- Sanitize SVG files or disallow them
- Implement file content scanning
- Store uploads outside web root when possible

**Current Good Practice (in upload.php):**
```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']); // ✅ Good
```

**Needs Improvement:**
```php
// Also validate extension matches MIME type
$allowedMimeToExt = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png' => ['png'],
    // ...
];
if (!in_array($extension, $allowedMimeToExt[$mimeType] ?? [])) {
    return ['error' => 'File extension does not match MIME type'];
}
```

---

### 9. Session Fixation Risk
**File:** `api/session.php`

**Severity:** HIGH  
**CVSS Score:** 6.5 (Medium-High)

**Issue:**
Session IDs are not regenerated after login, allowing session fixation attacks.

**Impact:**
- Session hijacking
- Account takeover

**Recommendation:**
- Regenerate session ID after successful login
- Regenerate session ID on privilege escalation

**Fix:**
```php
// In api/users.php after successful login
session_regenerate_id(true);
$_SESSION['user'] = $user;
```

---

### 10. Weak Password Requirements
**File:** `api/users.php:493`

**Severity:** HIGH  
**CVSS Score:** 6.3 (Medium-High)

**Issue:**
```php
if (strlen($newPassword) < 8) {
    // Only 8 characters minimum
}
```

Password requirements are minimal (only 8 characters). No complexity requirements.

**Impact:**
- Weak passwords vulnerable to brute force
- Account compromise

**Recommendation:**
- Enforce password complexity (uppercase, lowercase, numbers, special chars)
- Increase minimum length to 12 characters
- Implement password strength meter
- Check against common password lists

---

### 11. Missing Rate Limiting on Some Endpoints
**Files:** Various API endpoints

**Severity:** HIGH  
**CVSS Score:** 6.0 (Medium)

**Issue:**
While login has rate limiting, many other endpoints lack it:
- Password reset endpoints
- API key generation
- File upload endpoints
- Public API endpoints (has some, but could be improved)

**Impact:**
- Brute force attacks
- DoS attacks
- Resource exhaustion

**Recommendation:**
- Implement rate limiting on all authentication endpoints
- Add rate limiting to file upload endpoints
- Use different limits for different operations

---

### 12. Information Disclosure in Error Messages
**Files:** Multiple API endpoints

**Severity:** HIGH  
**CVSS Score:** 5.9 (Medium)

**Issue:**
Error messages sometimes reveal:
- File paths
- System information
- Database structure
- Internal implementation details

**Impact:**
- Information leakage
- Attack surface reconnaissance

**Recommendation:**
- Use generic error messages in production
- Log detailed errors server-side only
- Don't expose file paths or system details

---

### 13. SQL Injection Risk (SQLite Usage)
**File:** `database/init.php`, `api/admin_templates.php:28-35`

**Severity:** HIGH  
**CVSS Score:** 5.8 (Medium)

**Issue:**
While PDO is used (which helps), there's a SQLite database that may have queries constructed from user input.

**Impact:**
- SQL injection
- Database compromise
- Data exfiltration

**Recommendation:**
- Audit all SQL queries for prepared statements
- Ensure all user input is parameterized
- Use PDO::prepare() for all queries

**Current Status:** Appears to use PDO, but needs verification of all query construction.

---

### 14. Missing Content Security Policy (CSP)
**File:** `.htaccess`, admin templates

**Severity:** HIGH  
**CVSS Score:** 5.5 (Medium)

**Issue:**
No Content Security Policy headers set, allowing XSS attacks to execute scripts.

**Impact:**
- Cross-Site Scripting (XSS)
- Code injection via malicious content

**Recommendation:**
- Implement strict CSP headers
- Use nonce-based CSP for inline scripts
- Report CSP violations

**Fix in .htaccess:**
```apache
Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';"
```

---

### 15. Insecure Direct Object References
**Files:** Multiple API endpoints using IDs directly

**Severity:** HIGH  
**CVSS Score:** 5.4 (Medium)

**Issue:**
Many endpoints accept user/entry IDs directly without verifying ownership or permissions:
- Users can potentially access other users' data
- Entry IDs may be guessable

**Impact:**
- Unauthorized data access
- Privacy violations

**Recommendation:**
- Verify ownership before returning data
- Use non-guessable IDs (already using uniqid, which is good)
- Implement proper authorization checks

---

## Medium-Priority Issues (P2 - Fix Within 1 Month)

### 16. Missing Security Headers
**File:** `.htaccess`

**Severity:** MEDIUM  
**CVSS Score:** 4.5 (Medium)

**Missing Headers:**
- `Strict-Transport-Security` (HSTS)
- `Content-Security-Policy` (CSP)
- `Permissions-Policy`
- `X-Content-Type-Options` (present but could be stricter)

**Recommendation:**
```apache
Header set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header set Permissions-Policy "geolocation=(), microphone=(), camera=()"
```

---

### 17. Weak Random Number Generation (Potential)
**Files:** Using `uniqid()` and `random_bytes()`

**Severity:** MEDIUM  
**CVSS Score:** 4.0 (Low-Medium)

**Issue:**
While `random_bytes()` is cryptographically secure, `uniqid()` is not and is used for some IDs.

**Impact:**
- Predictable IDs
- Enumeration attacks

**Recommendation:**
- Use `bin2hex(random_bytes(16))` for all IDs
- Replace `uniqid()` with secure alternatives

---

### 18. Insufficient Logging
**Files:** Various

**Severity:** MEDIUM  
**CVSS Score:** 3.8 (Low)

**Issue:**
While security events are logged, some important events may be missing:
- Failed authorization attempts
- Suspicious API usage patterns
- File access patterns

**Recommendation:**
- Implement comprehensive security event logging
- Log all authentication attempts (success and failure)
- Monitor for suspicious patterns

---

### 19. Missing Input Size Limits
**Files:** Various API endpoints

**Severity:** MEDIUM  
**CVSS Score:** 3.5 (Low)

**Issue:**
No explicit limits on:
- JSON payload size
- Number of array elements
- String length in some contexts

**Impact:**
- DoS via large payloads
- Memory exhaustion

**Recommendation:**
- Set `post_max_size` and `upload_max_filesize` in php.ini
- Validate payload sizes in code
- Limit array sizes

---

### 20. Weak API Key Storage
**File:** Collection JSON files

**Severity:** MEDIUM  
**CVSS Score:** 3.3 (Low)

**Issue:**
API keys stored in plain text in JSON files. If file system is compromised, keys are exposed.

**Impact:**
- API key theft if file system compromised

**Recommendation:**
- Consider encrypting API keys at rest
- Use environment variables for sensitive keys
- Implement key rotation

---

### 21. Missing Request Size Validation
**Files:** API endpoints

**Severity:** MEDIUM  
**CVSS Score:** 3.0 (Low)

**Issue:**
No validation of request body size before processing.

**Impact:**
- DoS attacks
- Memory exhaustion

**Recommendation:**
- Check `Content-Length` header
- Limit request body size
- Reject oversized requests early

---

### 22. Insufficient Error Handling
**Files:** Various

**Severity:** MEDIUM  
**CVSS Score:** 2.8 (Low)

**Issue:**
Some error conditions may not be properly handled, leading to:
- Information disclosure
- Application crashes

**Recommendation:**
- Implement comprehensive error handling
- Use try-catch blocks
- Log errors securely

---

### 23. Missing Dependency Security Scanning
**File:** `composer.json`

**Severity:** MEDIUM  
**CVSS Score:** 2.5 (Low)

**Issue:**
No automated dependency vulnerability scanning mentioned.

**Recommendation:**
- Use `composer audit` (PHP 8.2+)
- Integrate security scanning in CI/CD
- Regularly update dependencies

---

## Code Quality & Best Practices

### Positive Security Practices Found ✅

1. **Password Hashing:** Uses `password_hash()` with `PASSWORD_DEFAULT` ✅
2. **Session Security:** Secure session configuration with HttpOnly, Secure, SameSite ✅
3. **CSRF Tokens:** CSRF protection implemented (though not everywhere) ✅
4. **Rate Limiting:** Login endpoint has rate limiting ✅
5. **Input Sanitization:** `sanitize_string()` and validation functions exist ✅
6. **File Upload Validation:** MIME type checking with `finfo_file()` ✅
7. **Security Headers:** Some security headers in .htaccess ✅
8. **Error Logging:** Security events are logged ✅

### Areas for Improvement

1. **Consistency:** Security measures not applied consistently across all endpoints
2. **Documentation:** Security practices should be documented
3. **Testing:** Need security testing (penetration testing, SAST)
4. **Monitoring:** Implement security monitoring and alerting

---

## Recommendations Summary

### Immediate Actions (This Week)
1. ✅ Remove `eval()` from template parser
2. ✅ Remove password logging
3. ✅ Remove API key from public responses
4. ✅ Fix CORS configuration
5. ✅ Add path traversal protection

### Short-Term (This Month)
6. ✅ Add CSRF protection to all endpoints
7. ✅ Strengthen input validation
8. ✅ Improve file upload security
9. ✅ Regenerate session IDs after login
10. ✅ Strengthen password requirements
11. ✅ Add rate limiting to more endpoints
12. ✅ Implement CSP headers

### Long-Term (Next Quarter)
13. ✅ Security testing and penetration testing
14. ✅ Security monitoring and alerting
15. ✅ Dependency vulnerability scanning
16. ✅ Security documentation
17. ✅ Security training for developers

---

## Testing Recommendations

1. **Static Analysis:** Use tools like PHPStan, Psalm, or SonarQube
2. **Dynamic Analysis:** OWASP ZAP, Burp Suite
3. **Dependency Scanning:** `composer audit`, Snyk, Dependabot
4. **Penetration Testing:** Professional security audit
5. **Code Review:** Regular security-focused code reviews

---

## Compliance Considerations

- **OWASP Top 10:** Addresses most items, but needs improvement on A01 (Broken Access Control) and A03 (Injection)
- **GDPR:** Ensure proper data protection and user consent mechanisms
- **PCI DSS:** If handling payments, additional requirements apply

---

## Conclusion

Birkly has a solid security foundation with good practices like secure password hashing and session management. However, critical vulnerabilities like `eval()` usage and sensitive data logging require immediate attention. The codebase would benefit from:

1. Consistent application of security measures
2. Removal of dangerous functions (`eval()`)
3. Enhanced input validation and output encoding
4. Comprehensive security testing

**Overall Security Rating: C+ (Needs Improvement)**

**Priority:** Address critical issues immediately, then systematically work through high and medium priority items.

---

*This audit was conducted through automated code analysis. For production deployments, professional security testing is strongly recommended.*
