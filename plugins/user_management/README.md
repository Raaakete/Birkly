# User Management Plugin

A fully self-contained, universal user management plugin for Birkly that enables user registration, authentication, permissions, tier-based access, and seamless integration with the collection/entry system.

## Features

### ✅ Core Features

- **User Storage**: Encrypted data storage in `data/plugins/user_management/`
- **Authentication**: Login, logout, session management with timeout
- **Registration**: Public, invite-only, and admin-created user registration
- **Email Verification**: Token-based email verification system
- **Password Reset**: Secure password reset via email
- **Two-Factor Authentication (2FA)**: TOTP-based 2FA with backup codes
- **Permissions**: Role-based, collection-level, and entry-level permissions with backend enforcement
- **Tier System**: Tier management with access limits
- **User Groups**: Group management with member control
- **Hybrid Profile System**: Plugin-managed basic info + optional collection entry profiles
- **User Fieldtype**: Dynamic fieldtype for referencing users in collections
- **Admin UI**: Comprehensive tabbed interface for all user management tasks
- **API Endpoints**: RESTful APIs for all operations
- **Template Functions**: Rich set of template functions for frontend integration
- **Collection Integration**: Automatic permission checks on entry operations

### Admin Interface

The plugin provides a tabbed admin interface with 6 tabs:

1. **General**: Plugin settings (registration, security, profile collection settings)
2. **User Profile**: Define custom profile fields and mandatory registration information
3. **User List**: Full user management (list, create, edit, delete, filter, export)
4. **Groups**: Group management (create, edit, delete, manage members)
5. **Tiers**: Tier management (create, edit, delete, configure limits)
6. **Permissions**: Collection-level permission configuration per role

## Installation

1. Copy the `user_management` folder to `plugins/`
2. Activate the plugin in Admin → Settings → Plugins
3. Access via Admin → Users

## Configuration

Plugin settings are available in Admin → Users → General:

### Registration Settings
- **Allow Public Registration**: Allow new users to register
- **Require Email Verification**: Require email verification before activation
- **Require Admin Approval**: Require admin approval before activation
- **Auto-approve**: Automatically approve users after email verification
- **Default Role**: Default role for new users (member, subscriber, premium, vip)

### Security Settings
- **Session Timeout**: Session timeout in seconds (default: 3600)
- **Max Login Attempts**: Maximum failed attempts before lockout (default: 5)
- **Lockout Duration**: Lockout duration in minutes (default: 30)

### Profile Collection Settings
- **Enable Collection Profiles**: Connect user accounts to collection entries for extended profiles
- **Profile Collection**: Select which collection to use for user profiles
- **Auto-create Profiles**: Automatically create profile entries for new users
- **Migrate Existing Users**: Bulk migration tool for existing users

## User Roles

The plugin uses website user roles (not CMS admin roles):

- **member**: Basic member access
- **subscriber**: Subscriber-level access
- **premium**: Premium member access
- **vip**: VIP member access

## Template Functions

Template functions are automatically available in all Birkly templates when the plugin is active. They provide access to user data, permissions, and authentication state.

### Authentication & User Info

#### `user_current`
Get the current logged-in user object. Returns `null` if no user is logged in.

**Properties available:**
- `id` - User ID (e.g., `user_7e10db3e5d5773faa3862d336492a259`)
- `name` - User's display name
- `email` - User's email address
- `username` - Username (if set)
- `role` - User role (member, subscriber, premium, vip)
- `tier` - User tier
- `status` - Account status (active, inactive, pending)
- `verified` - Email verification status (true/false)
- `approved` - Admin approval status (true/false)

**Example:**
```html
{if user_current exist}
    <p>Welcome, {user_current.name}!</p>
    <p>Email: {user_current.email}</p>
    <p>Role: {user_current.role}</p>
{else}
    <p>Please log in to continue.</p>
{endif}
```

**Real-world example (Navigation):**
```html
<nav>
    <a href="/feed">Feed</a>
    {if user_logged_in}
        <a href="/profile?user_id={user_current.id}">My Profile</a>
        <a href="/logout">Logout</a>
    {else}
        <a href="/login">Login</a>
        <a href="/register">Register</a>
    {endif}
</nav>
```

#### `user_logged_in`
Check if a user is currently logged in. Returns `true` or `false`.

**Example:**
```html
{if user_logged_in}
    <a href="/logout">Logout</a>
    <p>You are logged in as {user_current.name}</p>
{else}
    <a href="/login">Login</a>
    <a href="/register">Register</a>
{endif}
```

**Real-world example (Conditional content):**
```html
{if user_logged_in}
    <form action="/api/public_sub_entry.php" method="POST">
        <input type="hidden" name="parent_collection" value="social_posts" />
        <input type="hidden" name="parent_entry_id" value="{entry.id}" />
        <input type="hidden" name="sub_collection" value="comments" />
        <textarea name="text" placeholder="Write a comment..."></textarea>
        <button type="submit">Post Comment</button>
    </form>
{else}
    <p>Please <a href="/login">log in</a> to comment.</p>
{endif}
```

#### `user_by_id`
Get a user by their ID. Useful for displaying author information.

**Example:**
```html
{set author = user_by_id entry.author_user_id}
{if author exist}
    <p>Author: <strong>{author.name}</strong></p>
    <p>Email: {author.email}</p>
{endif}
```

**Real-world example (Post author):**
```html
<div class="post">
    <div class="post-meta">
        <strong>{entry.author_name}</strong>
        <span class="text-muted">{entry.created_at}</span>
    </div>
    <div class="post-content">{entry.content}</div>
    {if user_logged_in && entry.author_user_id == user_current.id}
        <button onclick="deletePost('{entry.id}')">Delete Post</button>
    {endif}
</div>
```

### Permissions

#### `user_can`
Check if the current user can perform an action on a collection or entry.

**Syntax:**
- `user_can 'action' 'collection'` - Check collection-level permission
- `user_can 'action' entry` - Check entry-level permission (uses entry's collection)

**Actions:**
- `create` - Create new entries
- `read` - View entries
- `edit` - Edit entries (checks `edit_own` or `edit_all`)
- `delete` - Delete entries (checks `delete_own` or `delete_all`)
- `publish` - Publish entries

**Example:**
```html
{if user_can 'create' 'blog'}
    <a href="/create-post">Create Post</a>
{endif}

{if user_can 'edit' entry}
    <a href="/edit/{entry.id}">Edit</a>
{endif}

{if user_can 'delete' entry && entry.author_user_id == user_current.id}
    <button onclick="deleteEntry('{entry.id}')">Delete</button>
{endif}
```

**Real-world example (Social media post):**
```html
<div class="post-actions">
    {if user_logged_in}
        <form action="/api/public_sub_entry.php" method="POST">
            <input type="hidden" name="operation" value="toggle" />
            <input type="hidden" name="parent_collection" value="social_posts" />
            <input type="hidden" name="parent_entry_id" value="{entry.id}" />
            <input type="hidden" name="sub_collection" value="likes" />
            <button type="submit">❤️ Like</button>
        </form>
        
        {if entry.author_user_id == user_current.id}
            <button onclick="deletePost('{entry.id}')">🗑️ Delete Post</button>
        {endif}
    {else}
        <a href="/login">🔐 Login to like</a>
    {endif}
</div>
```

#### `user_in_field`
Check if the current user is in a User fieldtype field. Useful for relationship-based permissions.

**Example:**
```html
{if user_in_field entry.friends}
    <p>You are friends with this user!</p>
    <p>You can see their private posts.</p>
{endif}

{if user_in_field entry.allowed_viewers}
    <p>You have access to this content.</p>
{endif}
```

**Real-world example (Visibility check):**
```html
{if entry.visibility == 'public' || 
    (entry.visibility == 'relationship' && user_in_field entry.allowed_viewers) ||
    entry.author_user_id == user_current.id}
    <!-- Show post content -->
    <div class="post-content">{entry.content}</div>
{else}
    <p>This post is private.</p>
{endif}
```

### User Profile

#### `user_profile`
Get user profile data (merged from plugin and collection entry if linked).

**Example:**
```html
{set profile = user_profile user_current.id}
{if profile exist}
    <div class="profile">
        <h2>{profile.name}</h2>
        {if profile.bio exist}
            <p class="bio">{profile.bio}</p>
        {endif}
        {if profile.location exist}
            <p class="location">📍 {profile.location}</p>
        {endif}
        {if profile.website exist}
            <p class="website"><a href="{profile.website}">{profile.website}</a></p>
        {endif}
    </div>
{endif}
```

**Real-world example (User profile page):**
```html
{set profile_user_id = query.user_id || user_current.id}
{set profile = user_profile profile_user_id}

<div class="profile-header">
    <h1>{profile.name}</h1>
    <p class="text-muted">{profile.email}</p>
    {if profile.bio exist}
        <p>{profile.bio}</p>
    {endif}
</div>

{if profile_user_id == user_current.id}
    <a href="/edit-profile">Edit Profile</a>
{endif}
```

### Verification & 2FA

#### `user_is_verified`
Check if the current user's email is verified.

**Example:**
```html
{if not user_is_verified}
    <div class="alert alert-warning">
        <p>Please verify your email address to access all features.</p>
        <a href="/resend-verification">Resend verification email</a>
    </div>
{endif}
```

#### `user_requires_2fa`
Check if 2FA is required for the current user's role.

**Example:**
```html
{if user_requires_2fa && not user_current.two_factor_enabled}
    <div class="alert alert-info">
        <p>Two-factor authentication is required for your account.</p>
        <a href="/settings/2fa">Enable 2FA</a>
    </div>
{endif}
```

### Tiers

#### `user_tier`
Get the current user's tier.

**Example:**
```html
{if user_tier == 'premium'}
    <div class="premium-badge">Premium Member</div>
    <p>You have access to premium features!</p>
{elseif user_tier == 'vip'}
    <div class="vip-badge">VIP Member</div>
    <p>You have access to all features!</p>
{else}
    <a href="/upgrade">Upgrade to Premium</a>
{endif}
```

### Template Function List

**Authentication:**
- `user_current` - Current user object
- `user_logged_in` - Boolean check if logged in
- `user_by_id` - Get user by ID

**Permissions:**
- `user_can` - Check permissions
- `user_in_field` - Check if user in fieldtype field

**Profile:**
- `user_profile` - Get user profile data

**Verification:**
- `user_is_verified` - Check email verification
- `user_requires_2fa` - Check 2FA requirement

**Tiers:**
- `user_tier` - Get user tier

## API Endpoints

All API endpoints are accessed via `/api/index.php?route={route}`. Most endpoints require authentication and CSRF tokens.

### Authentication

#### Login
```http
POST /api/index.php?route=user_auth&action=login
Content-Type: application/x-www-form-urlencoded

email=user@example.com&password=password123&csrf_token=...
```

**Response:**
```json
{
  "status": "ok",
  "message": "Login successful",
  "data": {
    "user": {
      "id": "user_123",
      "name": "John Doe",
      "email": "user@example.com",
      "role": "member"
    },
    "requires_2fa": false
  }
}
```

**With 2FA:**
```json
{
  "status": "ok",
  "message": "2FA required",
  "data": {
    "requires_2fa": true,
    "session_token": "2fa_session_token_here"
  }
}
```

#### Logout
```http
POST /api/index.php?route=user_auth&action=logout
Content-Type: application/x-www-form-urlencoded

csrf_token=...
```

#### Check Session
```http
GET /api/index.php?route=user_auth&action=check
```

**Response:**
```json
{
  "status": "ok",
  "logged_in": true,
  "data": {
    "user": {
      "id": "user_123",
      "name": "John Doe",
      "email": "user@example.com"
    }
  }
}
```

#### Verify 2FA
```http
POST /api/index.php?route=user_auth&action=verify_2fa
Content-Type: application/x-www-form-urlencoded

code=123456&session_token=2fa_session_token_here
```

### Registration
- `POST /api/index.php?route=user_registration&action=register` - Register new user
- `GET /api/index.php?route=user_registration&action=invite&code={code}` - Validate invite

### Email Verification
- `GET /api/index.php?route=user_verification&action=verify&token={token}&user={user_id}` - Verify email
- `POST /api/index.php?route=user_verification&action=resend` - Resend verification email
- `GET /api/index.php?route=user_verification&action=check_status&user_id={user_id}` - Check verification status

### Password Reset
- `POST /api/index.php?route=password_reset&action=request` - Request password reset
- `GET /api/index.php?route=password_reset&action=verify&token={token}&user={user_id}` - Verify reset token
- `POST /api/index.php?route=password_reset&action=reset` - Reset password

### Two-Factor Authentication
- `POST /api/index.php?route=user_2fa&action=generate_secret` - Generate 2FA secret
- `POST /api/index.php?route=user_2fa&action=enable` - Enable 2FA
- `POST /api/index.php?route=user_2fa&action=disable` - Disable 2FA
- `POST /api/index.php?route=user_2fa&action=verify` - Verify 2FA code
- `POST /api/index.php?route=user_2fa&action=regenerate_backup_codes` - Regenerate backup codes
- `GET /api/index.php?route=user_2fa&action=status` - Get 2FA status

### User Management (Admin)
- `GET /api/index.php?route=user_admin&action=list` - List users
- `GET /api/index.php?route=user_admin&action=get&id={id}` - Get user
- `POST /api/index.php?route=user_admin&action=create` - Create user
- `POST /api/index.php?route=user_admin&action=update` - Update user
- `POST /api/index.php?route=user_admin&action=delete` - Delete user
- `POST /api/index.php?route=user_admin&action=verify` - Verify user email
- `POST /api/index.php?route=user_admin&action=delete_account` - Delete user account
- `GET /api/index.php?route=user_admin&action=stats` - Get statistics

### User Groups
- `GET /api/index.php?route=user_groups&action=list` - List groups
- `GET /api/index.php?route=user_groups&action=get&id={id}` - Get group
- `POST /api/index.php?route=user_groups&action=create` - Create group
- `POST /api/index.php?route=user_groups&action=update` - Update group
- `POST /api/index.php?route=user_groups&action=delete` - Delete group
- `POST /api/index.php?route=user_groups&action=add_member` - Add member to group
- `POST /api/index.php?route=user_groups&action=remove_member` - Remove member from group

### User Tiers
- `GET /api/index.php?route=user_tiers&action=list` - List tiers
- `GET /api/index.php?route=user_tiers&action=get&id={id}` - Get tier
- `POST /api/index.php?route=user_tiers&action=create` - Create tier
- `POST /api/index.php?route=user_tiers&action=update` - Update tier
- `POST /api/index.php?route=user_tiers&action=delete` - Delete tier

### Settings
- `GET /api/index.php?route=user_settings&action=get` - Get plugin settings
- `POST /api/index.php?route=user_settings&action=save_settings` - Save general settings
- `POST /api/index.php?route=user_settings&action=save_profile_schema` - Save profile schema
- `GET /api/index.php?route=user_settings&action=get_collections` - Get collections list
- `POST /api/index.php?route=user_settings&action=migrate_profiles` - Migrate users to collection profiles

### Collection Permissions
- `GET /api/index.php?route=collection_permissions&action=get` - Get collection permissions
- `POST /api/index.php?route=collection_permissions&action=save` - Save collection permissions

## Data Storage

All plugin data is stored in `data/plugins/user_management/`:

```
data/plugins/user_management/
├── users/                    # User data (encrypted)
├── permissions/              # Permission configurations
├── tiers/                    # Tier definitions
├── groups/                   # User groups
├── sessions/                 # Session data
├── invites/                  # Invite codes
├── activity/                 # Activity logs
├── metadata/                 # Plugin metadata
├── verification_tokens/      # Email verification tokens
├── password_reset_tokens/    # Password reset tokens
└── 2fa/                      # 2FA secrets and backup codes
```

## Security Features

- **Encryption**: All sensitive data (email, password) is encrypted at rest
- **Password Hashing**: Argon2ID password hashing
- **Rate Limiting**: Rate limiting on login attempts
- **Account Lockout**: Account lockout after failed attempts
- **Session Timeout**: Automatic session timeout handling
- **CSRF Protection**: CSRF protection on all state-changing operations
- **Email Verification**: Token-based email verification
- **Password Reset**: Secure password reset with expiration
- **Two-Factor Authentication**: TOTP-based 2FA with backup codes
- **Backend Permission Enforcement**: Server-side permission checks on all API endpoints

## Hybrid Profile System

The plugin supports a hybrid profile approach:

### Default (Plugin-Managed)
- Basic user info (username, email, password, user_id) always stored in plugin
- Works out of the box, no setup needed
- Resilient: User accounts work even if collections are deleted

### Optional (Collection Profiles)
- Connect user accounts to collection entries for extended profiles
- Full collection features available (sub-entries, relationships, etc.)
- Basic info synced from plugin to entry (redundant but safe)
- Auto-create profile entries for new users when enabled
- Bulk migration tool for existing users

**Benefits:**
- Simple projects: Use plugin-managed only
- Complex projects: Enable collection profiles for full flexibility
- Backward compatible: Existing users work without changes

## User Fieldtype

The plugin automatically registers a **User** fieldtype that can be used in any collection:

- **Single User**: Reference one user
- **Multiple Users**: Reference multiple users (e.g., friends list)
- **Template Usage**: `{entry.author.name}`, `{entry.friends}`
- **Permission Checks**: `{if user_in_field entry.friends}`

The fieldtype is only available when the plugin is active and disappears when deactivated.

## Permissions

### Permission Levels

1. **Collection-Level**: Who can create/edit/delete entries in a collection
2. **Entry-Level**: Who can view/edit/delete specific entries
3. **Sub-Entry-Level**: Who can create comments/sub-entries

### Permission Enforcement

- **Frontend Checks**: Template functions for UX (showing/hiding elements)
- **Backend Enforcement**: Server-side checks on all API endpoints (security)
- **Automatic Filtering**: Entries filtered by permissions in `api/content.php`, `api/public.php`, and `api/search.php`

### Entry Ownership

When a user creates an entry, the plugin automatically sets:
- `author_user_id` - The user ID of the creator
- `author_name` - The user's display name (synced from user data)

**Example:**
```html
{for each post in social_posts}
    <div class="post">
        <div class="post-header">
            <strong>{post.author_name}</strong>
            <span class="text-muted">{post.created_at}</span>
        </div>
        <div class="post-content">{post.content}</div>
        
        {if user_logged_in && post.author_user_id == user_current.id}
            <button onclick="deletePost('{post.id}')">Delete</button>
        {endif}
    </div>
{endfor}
```

### Entry Visibility

Entries can have visibility settings that control who can view them:

- **Public**: Anyone can view (default)
- **Private**: Only the owner can view
- **Relationship**: Only users in specified User fieldtype fields can view

**Example (Social Media Post):**
```html
{for each post in social_posts}
    {if post.visibility == 'public' || 
        (post.visibility == 'relationship' && user_in_field post.allowed_viewers) ||
        post.author_user_id == user_current.id}
        
        <div class="post">
            <div class="post-header">
                <strong>{post.author_name}</strong>
                {if post.visibility == 'private'}
                    <span class="badge">🔒 Private</span>
                {elseif post.visibility == 'relationship'}
                    <span class="badge">👥 Connections Only</span>
                {endif}
            </div>
            <div class="post-content">{post.content}</div>
        </div>
    {else}
        <div class="post-locked">
            <p>🔒 This post is private.</p>
        </div>
    {endif}
{endfor}
```

**Setting Visibility on Entry Creation:**
```html
<form action="/api/public.php" method="POST">
    <input type="hidden" name="action" value="create_entry" />
    <input type="hidden" name="collection" value="social_posts" />
    
    <textarea name="content" placeholder="What's on your mind?"></textarea>
    
    <select name="visibility">
        <option value="public">Public</option>
        <option value="private">Private</option>
        <option value="relationship">Connections Only</option>
    </select>
    
    <button type="submit">Post</button>
</form>
```

### Sub-Entries (Comments, Likes, etc.)

The plugin works seamlessly with Birkly sub-entries system. Users can create sub-entries (like comments) on entries they have permission to view.

**Example (Comments on a Post):**
```html
<div class="post">
    <h2>{entry.title}</h2>
    <p>{entry.content}</p>
    
    <!-- Comment Form -->
    {if user_logged_in}
        <form action="/api/public_sub_entry.php" method="POST">
            <input type="hidden" name="parent_collection" value="social_posts" />
            <input type="hidden" name="parent_entry_id" value="{entry.id}" />
            <input type="hidden" name="sub_collection" value="comments" />
            <input type="hidden" name="user_id" value="{user_current.id}" />
            <input type="hidden" name="user_name" value="{user_current.name}" />
            <textarea name="text" placeholder="Write a comment..."></textarea>
            <button type="submit">Post Comment</button>
        </form>
    {endif}
    
    <!-- Display Comments -->
    {if comments exist}
        <div class="comments">
            {for each comment in comments}
                {if comment.parent_comment_id == "" || !comment.parent_comment_id}
                    <!-- Top-level comment -->
                    <div class="comment">
                        <strong>{comment.user_name}</strong>
                        <p>{comment.text}</p>
                        
                        <!-- Nested Replies -->
                        <div class="replies">
                            {for each reply_check in comments}
                                {if reply_check.parent_comment_id == comment.id}
                                    <div class="reply">
                                        <strong>{reply_check.user_name}</strong>
                                        <p>{reply_check.text}</p>
                                    </div>
                                {endif}
                            {endfor}
                        </div>
                    </div>
                {endif}
            {endfor}
        </div>
    {endif}
</div>
```

**Example (Likes):**
```html
<div class="post-actions">
    {if user_logged_in}
        <form action="/api/public_sub_entry.php" method="POST">
            <input type="hidden" name="operation" value="toggle" />
            <input type="hidden" name="parent_collection" value="social_posts" />
            <input type="hidden" name="parent_entry_id" value="{entry.id}" />
            <input type="hidden" name="sub_collection" value="likes" />
            <input type="hidden" name="id" value="like_{user_current.id}" />
            <input type="hidden" name="user_id" value="{user_current.id}" />
            <button type="submit">
                {if user_in_field entry.likes}
                    ❤️ Unlike
                {else}
                    🤍 Like
                {endif}
            </button>
        </form>
    {endif}
    
    <span class="likes-count">{entry.likes_count} likes</span>
</div>
```

**Prefetching Sub-Entries:**
```html
<!-- Prefetch comments for a post -->
<meta
    data-haru-prefetch="sub_entries"
    data-haru-into="comments"
    data-parent-collection="social_posts"
    data-parent-entry-id="{id}"
    data-sub-collection="comments"
/>
```

## Public Pages

The plugin provides public pages:

- `/verify-email?token={token}&user={user_id}` - Email verification page
- `/reset-password?token={token}&user={user_id}` - Password reset page
- `/reset-password` - Password reset request page

## Social Demo Implementation

A complete social media demo is included at `/demo-website/social/` that demonstrates:

### Features Demonstrated

1. **User Registration & Login**
   - Public registration form
   - Email/password authentication
   - Session management

2. **Social Posts Collection**
   - Collection: `social_posts`
   - Entries: Individual posts
   - Sub-collections: `comments` and `likes`
   - Visibility: Public, private, relationship-based

3. **User Profiles**
   - Profile pages for each user
   - User information display
   - Post history

4. **Comments System**
   - Nested comments (replies)
   - Comment creation via sub-entries
   - Comment deletion (owner only)

5. **Likes System**
   - Like/unlike toggle via sub-entries
   - Like count display
   - User-specific like tracking

### Demo Structure

```
demo-website/social/
├── index.html          # Main feed page
├── login.html          # Login page
├── register.html       # Registration page
├── profile.html        # User profile page
├── post.html           # Individual post with comments
└── social.css          # Styling
```

### Key Implementation Examples

**Login Form:**
```html
<form method="POST" action="/api/index.php">
    <input type="hidden" name="route" value="user_auth" />
    <input type="hidden" name="action" value="login" />
    <input type="hidden" name="_haru_redirect" value="/demo-website/social/index.html" />
    <input name="email" type="email" />
    <input name="password" type="password" />
    <button type="submit">Login</button>
</form>
```

**Registration Form:**
```html
<form method="POST" action="/api/index.php">
    <input type="hidden" name="route" value="user_registration" />
    <input type="hidden" name="action" value="register" />
    <input type="hidden" name="_haru_redirect" value="/demo-website/social/login.html" />
    <input name="name" type="text" />
    <input name="email" type="email" />
    <input name="password" type="password" />
    <button type="submit">Register</button>
</form>
```

**Post Creation:**
```html
{if user_logged_in}
    <form action="/api/public.php" method="POST">
        <input type="hidden" name="action" value="create_entry" />
        <input type="hidden" name="collection" value="social_posts" />
        <textarea name="content" placeholder="What's on your mind?"></textarea>
        <select name="visibility">
            <option value="public">Public</option>
            <option value="private">Private</option>
        </select>
        <button type="submit">Post</button>
    </form>
{endif}
```

**Comment Creation:**
```html
{if user_logged_in}
    <form data-haru-url="/api/public_sub_entry.php" 
          data-haru-success="redirect" 
          data-haru-redirect="self">
        <input type="hidden" name="parent_collection" value="social_posts" />
        <input type="hidden" name="parent_entry_id" value="{entry.id}" />
        <input type="hidden" name="sub_collection" value="comments" />
        <input type="hidden" name="user_id" value="{user_current.id}" />
        <input type="hidden" name="user_name" value="{user_current.name}" />
        <textarea name="text" placeholder="Write a comment..."></textarea>
        <button type="submit">Send</button>
    </form>
{endif}
```

**Like Toggle:**
```html
{if user_logged_in}
    <form data-haru-url="/api/public_sub_entry.php" 
          data-haru-success="redirect" 
          data-haru-redirect="self">
        <input type="hidden" name="operation" value="toggle" />
        <input type="hidden" name="parent_collection" value="social_posts" />
        <input type="hidden" name="parent_entry_id" value="{entry.id}" />
        <input type="hidden" name="sub_collection" value="likes" />
        <input type="hidden" name="id" value="like_{user_current.id}" />
        <input type="hidden" name="user_id" value="{user_current.id}" />
        <button type="submit">❤️ Like / Unlike</button>
    </form>
{endif}
```

### Demo Accounts

The social demo includes test accounts (password: `demo12345`):
- `alice@demo.social`
- `bob@demo.social`
- `carol@demo.social`

These accounts can be used to test:
- User interactions
- Post visibility
- Comments and replies
- Like functionality
- Profile viewing

## Hooks

The plugin fires the following hooks:

- `before_user_register` - Before user registration
- `after_user_register` - After user registration
- `before_user_update` - Before user update
- `after_user_update` - After user update
- `before_user_delete` - Before user deletion
- `after_user_delete` - After user deletion
- `user_logged_in` - When user logs in
- `user_logged_out` - When user logs out
- `user_email_verified` - When email is verified
- `user_tier_changed` - When user tier changes

## Development Status

**Phase 0**: ✅ Plugin Infrastructure  
**Phase 1**: ✅ Core Infrastructure & Storage  
**Phase 2**: ✅ Authentication & Session Management  
**Phase 3**: ✅ User Registration  
**Phase 4**: ✅ Permission System  
**Phase 5**: ✅ Admin UI  
**Phase 6**: ✅ Tier System  
**Phase 7**: ✅ Advanced Features (Email Verification, 2FA, Password Reset, Profile System)  
**Phase 8**: ✅ Security & Hardening  
**Phase 9**: 🚧 Documentation & Examples  
**Phase 10**: 📋 Testing & Quality Assurance  
**Phase 11**: 📋 Release Preparation  

## Form Handling

The plugin works seamlessly with Birkly's client-side form handling. Forms can use special attributes for automatic handling:

### Client-Side Form Attributes

**Automatic Redirect:**
```html
<form method="POST" action="/api/index.php">
    <input type="hidden" name="route" value="user_auth" />
    <input type="hidden" name="action" value="login" />
    <input type="hidden" name="_haru_redirect" value="/dashboard" />
    <!-- Form fields -->
</form>
```

**Success Callback:**
```html
<form data-haru-url="/api/public_sub_entry.php" 
      data-haru-success="redirect" 
      data-haru-redirect="self">
    <!-- Form fields -->
</form>
```

**Available options:**
- `data-haru-url` - API endpoint URL
- `data-haru-success` - Action on success (`redirect`, `reload`, `callback`)
- `data-haru-redirect` - Redirect URL (use `self` for current page)
- `data-haru-callback` - JavaScript function name to call on success

### Example Forms

**Login with Redirect:**
```html
<form method="POST" action="/api/index.php">
    <input type="hidden" name="route" value="user_auth" />
    <input type="hidden" name="action" value="login" />
    <input type="hidden" name="_haru_redirect" value="/dashboard" />
    
    <input name="email" type="email" required />
    <input name="password" type="password" required />
    <button type="submit">Login</button>
</form>
```

**Registration with Success Message:**
```html
<form method="POST" action="/api/index.php">
    <input type="hidden" name="route" value="user_registration" />
    <input type="hidden" name="action" value="register" />
    <input type="hidden" name="_haru_redirect" value="/login?registered=1" />
    
    <input name="name" type="text" required />
    <input name="email" type="email" required />
    <input name="password" type="password" required minlength="8" />
    <button type="submit">Register</button>
</form>
```

**Comment Form (Auto-reload):**
```html
<form data-haru-url="/api/public_sub_entry.php" 
      data-haru-success="redirect" 
      data-haru-redirect="self">
    <input type="hidden" name="parent_collection" value="social_posts" />
    <input type="hidden" name="parent_entry_id" value="{entry.id}" />
    <input type="hidden" name="sub_collection" value="comments" />
    <input type="hidden" name="user_id" value="{user_current.id}" />
    <input type="hidden" name="user_name" value="{user_current.name}" />
    
    <textarea name="text" required></textarea>
    <button type="submit">Post Comment</button>
</form>
```

## Best Practices

### Security

1. **Always use CSRF tokens** in forms that modify data
2. **Validate permissions** on both frontend (UX) and backend (security)
3. **Use HTTPS** in production for authentication
4. **Set appropriate session timeouts** based on your use case
5. **Enable email verification** for user accounts
6. **Use 2FA** for sensitive accounts

### Performance

1. **Prefetch sub-entries** using `<meta data-haru-prefetch="...">` tags
2. **Limit collection queries** with appropriate filters
3. **Use pagination** for large lists
4. **Cache user data** when appropriate

### User Experience

1. **Show clear error messages** when authentication fails
2. **Provide feedback** during form submissions
3. **Use conditional rendering** to show/hide content based on permissions
4. **Display user-friendly messages** for verification and approval status

### Template Organization

1. **Separate authentication logic** into reusable template snippets
2. **Use template functions** for permission checks
3. **Leverage nested loops** for comments and sub-entries
4. **Keep forms simple** and use client-side form handling

## Troubleshooting

### Common Issues

**User not logged in after login:**
- Check session timeout settings
- Verify CSRF token is included
- Check browser cookies are enabled

**Permission checks not working:**
- Verify collection permissions are configured
- Check user role and tier settings
- Ensure backend permission enforcement is enabled

**Sub-entries not displaying:**
- Verify prefetch meta tags are correct
- Check sub-collection name matches
- Ensure parent entry ID is correct

**Template functions not available:**
- Verify plugin is activated
- Check plugin is loaded before template rendering
- Ensure user data is hydrated correctly

## License

Part of Birkly plugin ecosystem.
