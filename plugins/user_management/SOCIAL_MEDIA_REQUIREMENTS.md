# Social Media Site Requirements for User Management Plugin

## Overview
To build a social media site with Birkly, we need to extend the user management plugin with social networking features. This document outlines what's needed.

## Current Capabilities ✅
- User registration and authentication
- User profiles with custom fields
- User roles (member, subscriber, premium, vip)
- Collection permissions (users can create/edit entries)
- User groups
- Tiers system

## Missing Features for Social Media

### 1. **User Connections/Friends System** 🔴 Critical
**What's needed:**
- Friend request system (send, accept, reject, cancel)
- Friend list management
- Follow/unfollow functionality (one-way connections)
- Block/unblock users
- Connection status (pending, accepted, blocked)
- Mutual friends discovery

**Data Structure:**
```json
{
  "user_id": "user123",
  "connected_user_id": "user456",
  "status": "pending|accepted|blocked|rejected",
  "type": "friend|follow",
  "initiated_by": "user123",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**API Endpoints Needed:**
- `POST /api/user_connections` - Send friend request / follow
- `GET /api/user_connections` - List connections (friends, followers, following)
- `PUT /api/user_connections` - Accept/reject friend request
- `DELETE /api/user_connections` - Unfriend / unfollow / unblock

**UI Needed:**
- Friend request notifications
- Friend list page
- "Add Friend" / "Follow" buttons on profiles
- Connection status indicators

---

### 2. **Enhanced User Profiles** 🟡 Important
**What's needed:**
- Profile pictures/avatars (file upload)
- Cover photos
- Custom profile URLs/slugs (e.g., `/user/john-doe`)
- Profile visibility settings (public, friends only, private)
- Profile completion status
- Activity status (online, offline, last seen)
- Profile verification badges

**Data Structure:**
```json
{
  "user_id": "user123",
  "profile_slug": "john-doe",
  "avatar_url": "/media/avatars/user123.jpg",
  "cover_url": "/media/covers/user123.jpg",
  "bio": "Software developer...",
  "location": "New York, USA",
  "website": "https://johndoe.com",
  "birthday": "1990-01-01",
  "privacy": {
    "profile_visibility": "public|friends|private",
    "show_email": false,
    "show_birthday": "friends_only"
  },
  "verification_status": "verified|pending|none",
  "last_seen": "2024-01-01T12:00:00Z"
}
```

**API Endpoints Needed:**
- `GET /api/user_profile/{user_id}` - Get public profile
- `PUT /api/user_profile` - Update own profile
- `POST /api/user_profile/avatar` - Upload avatar
- `POST /api/user_profile/cover` - Upload cover photo
- `GET /api/user_profile/check_slug` - Check if slug is available

---

### 3. **Post Visibility & Privacy** 🔴 Critical
**What's needed:**
- Post visibility levels (public, friends only, private, custom lists)
- Post privacy controls per collection
- Ability to restrict who can comment
- Ability to restrict who can react

**Collection Schema Extension:**
```json
{
  "collection": "posts",
  "privacy_options": {
    "visibility_levels": ["public", "friends", "private", "custom"],
    "allow_comments": true,
    "comment_privacy": "public|friends|private",
    "allow_reactions": true
  }
}
```

**Entry Schema Extension:**
```json
{
  "entry_id": "post123",
  "author_id": "user123",
  "visibility": "friends",
  "allowed_viewers": ["user456", "user789"], // if custom
  "allow_comments": true,
  "comment_privacy": "friends"
}
```

---

### 4. **Reactions System** 🟡 Important
**What's needed:**
- Multiple reaction types (like, love, laugh, angry, etc.)
- Reaction counts per type
- User's reaction status on each post
- Reaction notifications

**Data Structure:**
```json
{
  "entry_id": "post123",
  "user_id": "user456",
  "reaction_type": "like|love|laugh|angry|sad",
  "created_at": "2024-01-01T00:00:00Z"
}
```

**API Endpoints Needed:**
- `POST /api/entry_reactions` - Add/change reaction
- `DELETE /api/entry_reactions` - Remove reaction
- `GET /api/entry_reactions/{entry_id}` - Get all reactions for entry

---

### 5. **Comments System** 🟡 Important
**What's needed:**
- Nested comments (replies to comments)
- Comment reactions
- Comment editing/deletion
- Comment notifications
- Comment moderation

**Data Structure:**
```json
{
  "comment_id": "comment123",
  "entry_id": "post123",
  "author_id": "user456",
  "parent_comment_id": null, // for nested comments
  "content": "Great post!",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z",
  "reactions": [],
  "replies_count": 0
}
```

**API Endpoints Needed:**
- `POST /api/entry_comments` - Create comment
- `GET /api/entry_comments/{entry_id}` - Get comments for entry
- `PUT /api/entry_comments/{comment_id}` - Edit comment
- `DELETE /api/entry_comments/{comment_id}` - Delete comment

---

### 6. **Activity Feed / Timeline** 🔴 Critical
**What's needed:**
- Personalized feed showing posts from friends
- Feed algorithm (chronological or algorithmic)
- Feed filtering (all, friends only, trending)
- Infinite scroll pagination
- Real-time updates (optional)

**API Endpoints Needed:**
- `GET /api/user_feed` - Get personalized feed
- `GET /api/user_feed/trending` - Get trending posts
- `GET /api/user_feed/{user_id}` - Get specific user's posts

**Feed Algorithm:**
- Show posts from friends first
- Show posts user interacted with
- Show trending posts
- Filter by date range

---

### 7. **Notifications System** 🟡 Important
**What's needed:**
- Real-time notifications (friend requests, reactions, comments, mentions)
- Notification preferences
- Notification history
- Unread notification count
- Email notifications (optional)

**Data Structure:**
```json
{
  "notification_id": "notif123",
  "user_id": "user456",
  "type": "friend_request|reaction|comment|mention|message",
  "from_user_id": "user123",
  "related_entry_id": "post123",
  "message": "John Doe liked your post",
  "read": false,
  "created_at": "2024-01-01T00:00:00Z"
}
```

**API Endpoints Needed:**
- `GET /api/user_notifications` - Get notifications
- `PUT /api/user_notifications/{id}/read` - Mark as read
- `PUT /api/user_notifications/read_all` - Mark all as read
- `GET /api/user_notifications/unread_count` - Get unread count
- `PUT /api/user_notifications/preferences` - Update preferences

---

### 8. **Mentions & Hashtags** 🟢 Nice to Have
**What's needed:**
- @username mentions in posts/comments
- #hashtag support
- Mention notifications
- Hashtag pages/feeds
- Trending hashtags

**Implementation:**
- Parse @username and #hashtag in content
- Create mention records
- Link hashtags to entries
- Generate hashtag feeds

---

### 9. **Direct Messaging** 🟡 Important
**What's needed:**
- One-on-one messages
- Group messages
- Message read receipts
- Message search
- File attachments in messages

**Data Structure:**
```json
{
  "message_id": "msg123",
  "conversation_id": "conv123",
  "sender_id": "user123",
  "recipient_id": "user456",
  "content": "Hey, how are you?",
  "read": false,
  "read_at": null,
  "created_at": "2024-01-01T00:00:00Z"
}
```

**API Endpoints Needed:**
- `GET /api/user_messages` - Get conversations
- `GET /api/user_messages/{conversation_id}` - Get messages
- `POST /api/user_messages` - Send message
- `PUT /api/user_messages/{id}/read` - Mark as read
- `DELETE /api/user_messages/{id}` - Delete message

---

### 10. **User Search & Discovery** 🟢 Nice to Have
**What's needed:**
- Search users by name, username, email
- Advanced filters (location, interests, etc.)
- "People you may know" suggestions
- User recommendations based on connections

**API Endpoints Needed:**
- `GET /api/user_search` - Search users
- `GET /api/user_suggestions` - Get user suggestions

---

### 11. **Privacy Settings** 🟡 Important
**What's needed:**
- Granular privacy controls
- Block list management
- Who can see profile
- Who can send friend requests
- Who can message
- Activity visibility

**Data Structure:**
```json
{
  "user_id": "user123",
  "privacy": {
    "profile_visibility": "public|friends|private",
    "who_can_friend": "everyone|friends_of_friends|nobody",
    "who_can_message": "everyone|friends|nobody",
    "show_online_status": true,
    "show_last_seen": "everyone|friends|nobody",
    "show_activity": true,
    "blocked_users": ["user789"]
  }
}
```

---

### 12. **Activity Log / Timeline** 🟢 Nice to Have
**What's needed:**
- User activity history
- Activity visibility controls
- Activity feed on profile
- "Recent Activity" widget

---

## Implementation Priority

### Phase 1: Core Social Features (MVP)
1. ✅ User profiles with avatars
2. ✅ Friend/connection system
3. ✅ Post visibility controls
4. ✅ Basic reactions (like)
5. ✅ Basic comments
6. ✅ Activity feed

### Phase 2: Enhanced Social Features
1. ✅ Notifications system
2. ✅ Advanced reactions
3. ✅ Nested comments
4. ✅ Mentions
5. ✅ Privacy settings

### Phase 3: Advanced Features
1. ✅ Direct messaging
2. ✅ Hashtags
3. ✅ User search & discovery
4. ✅ Activity log
5. ✅ Real-time updates

---

## Technical Considerations

### Database/Storage
- All social data should be stored in `data/plugins/user_management/`
- Use JSON files for connections, reactions, comments
- Consider indexing for performance

### Performance
- Cache friend lists
- Cache feed data
- Use pagination for all lists
- Optimize queries for large datasets

### Security
- Validate all user inputs
- Check permissions before actions
- Rate limiting for API calls
- CSRF protection (already implemented)

### Scalability
- Consider using a proper database for large sites
- Implement caching layer
- Use background jobs for notifications
- Optimize feed generation

---

## Template Functions Needed

```php
// User connections
{user_is_friend user_id="123"} // Check if users are friends
{user_friend_count user_id="123"} // Get friend count
{user_friends user_id="123" limit="10"} // List friends

// Profile
{user_profile user_id="123"} // Get user profile
{user_avatar user_id="123" size="medium"} // Get avatar URL
{user_profile_url user_id="123"} // Get profile URL

// Feed
{user_feed limit="20"} // Get user's feed
{user_posts user_id="123" limit="10"} // Get user's posts

// Reactions
{entry_reactions entry_id="post123"} // Get reactions
{user_reacted entry_id="post123" user_id="123"} // Check if user reacted

// Comments
{entry_comments entry_id="post123" limit="10"} // Get comments

// Notifications
{user_notifications limit="10"} // Get notifications
{user_notification_count} // Get unread count
```

---

## Next Steps

1. **Create API endpoints** for each feature
2. **Create storage classes** for social data
3. **Create admin UI** for managing social features
4. **Create template functions** for frontend
5. **Create frontend components** (if needed)
6. **Test with real scenarios**

---

## Questions to Consider

1. **One-way (follow) vs Two-way (friend) connections?**
   - Twitter-style (follow) vs Facebook-style (friends)
   - Or both?

2. **Feed Algorithm:**
   - Chronological or algorithmic?
   - How to handle trending?

3. **Real-time Updates:**
   - WebSockets or polling?
   - Server-Sent Events?

4. **Scalability:**
   - How many users expected?
   - Need database migration path?

5. **Moderation:**
   - User reporting system?
   - Admin moderation tools?
