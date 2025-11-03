# Public API Feature Proposal

## Naming Recommendation

**Recommended: "Entry Method"**
- **"Admin Only"** (default) - Only allows entries created through admin panel
- **"Public API"** - Allows entries to be created via external API

**Alternative Options:**
- "Submission Type": "Manual" / "Public"
- "Content Access": "Internal Only" / "Public Submissions"
- "Collection Type": "Private" / "Public"

## Architecture Overview

### Collection Settings
Add to `collection.json`:
```json
{
  "entry_method": "admin_only" | "public_api",
  "api_key": "generated-key-here", // Only when public_api
  "api_settings": {
    "rate_limit": 10,              // Submissions per hour
    "rate_limit_window": 3600,     // Window in seconds
    "auto_publish": false,         // Auto-publish or moderation queue
    "require_captcha": false,      // Optional CAPTCHA
    "notification_email": "",      // Email for notifications
    "allowed_fields": []            // Whitelist of allowed fields (empty = all)
  }
}
```

### Security Features

1. **API Key Authentication**
   - Unique key per collection
   - Sent via header: `X-API-Key: your-key`
   - Or via query: `?api_key=your-key`

2. **Rate Limiting**
   - Per API key
   - Configurable limits (default: 10/hour)
   - IP-based fallback

3. **Field Validation**
   - Validates against collection schema
   - Whitelist allowed fields
   - Type validation (email, number, etc.)

4. **CSRF Protection**
   - For web forms (not needed for API)
   - Token-based validation

5. **Optional CAPTCHA**
   - Google reCAPTCHA or hCaptcha
   - Configurable per collection

6. **Moderation Queue**
   - Auto-publish or manual approval
   - Status: "draft" (default) or "published"

## API Endpoint

### Public Endpoint
`POST /api/public/collections/{slug}/entries`

**Headers:**
```
X-API-Key: your-collection-api-key
Content-Type: application/json
```

**Request Body:**
```json
{
  "title": "Contact Form Submission",
  "email": "user@example.com",
  "message": "Hello, I have a question...",
  "captcha_token": "optional-captcha-token"
}
```

**Response:**
```json
{
  "status": "ok",
  "message": "Entry created successfully",
  "data": {
    "entry_id": "entry-123",
    "status": "draft" | "published"
  }
}
```

## Implementation Steps

1. ✅ Add "Entry Method" dropdown in collection editor (Settings tab)
2. ✅ Generate API key when "Public API" is selected
3. ✅ Create `/api/public/collections/{slug}/entries` endpoint
4. ✅ Add security options UI in collection editor
5. ✅ Generate embeddable form code (HTML/JS)

## Security Best Practices

1. **Always validate input** against schema
2. **Sanitize all user input** before storing
3. **Rate limit aggressively** to prevent abuse
4. **Log all API requests** for audit trail
5. **Use HTTPS only** for API endpoints
6. **Implement IP whitelisting** (optional, advanced)

## Example Use Cases

1. **Contact Forms** - Submit to "contact" collection
2. **Newsletter Signups** - Submit to "subscribers" collection
3. **User Feedback** - Submit to "feedback" collection
4. **Event Registrations** - Submit to "registrations" collection

