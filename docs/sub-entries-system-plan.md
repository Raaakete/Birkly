# Sub-Entries System - Comprehensive Project Plan

## Table of Contents
1. [Overview](#overview)
2. [Goals & Requirements](#goals--requirements)
3. [Use Cases & Applications](#use-cases--applications)
4. [System Architecture](#system-architecture)
5. [Data Structures](#data-structures)
6. [API Design](#api-design)
7. [User Interface Design](#user-interface-design)
8. [Implementation Phases](#implementation-phases)
9. [Technical Specifications](#technical-specifications)
10. [Examples & Templates](#examples--templates)

---

## Overview

The Sub-Entries System is a flexible, hierarchical content relationship system that allows entries in one collection to have related entries in other collections. This enables users to build complex applications like forums, e-commerce platforms, project management systems, and more - all within HaruCMS.

### Key Concepts

- **Sub-Entries**: Entries that belong to a parent entry
- **Sub-Collections**: Collections configured to contain sub-entries
- **Nested Sub-Entries**: Sub-entries that can have their own sub-entries (unlimited depth)
- **Multiple Sub-Collection Types**: A single parent entry can have multiple types of sub-entries
- **Independent Entry Methods**: Each sub-collection has its own entry submission settings (admin-only or public API)

---

## Goals & Requirements

### Primary Goals

1. **Maximum Flexibility**: Enable users to build any type of application structure
2. **Robust Relationships**: Support complex parent-child hierarchies
3. **Independent Configuration**: Each sub-collection operates independently
4. **Query Flexibility**: Rich querying capabilities for filtering, sorting, and aggregating
5. **User-Friendly**: Simple UX for managing complex relationships

### Core Requirements

- ✅ Multiple sub-collection types per parent entry
- ✅ Unlimited nesting depth
- ✅ Separate entry submission settings per sub-collection
- ✅ Rich query API (filtering, sorting, aggregations)
- ✅ Flexible parent-child relationships
- ✅ Support for cross-references and polymorphic relationships
- ✅ Permission system per sub-collection
- ✅ Multiple display modes (list, tree, threaded, etc.)
- ✅ Activity tracking and notifications
- ✅ Template helpers for rendering

---

## Use Cases & Applications

### 1. Blog with Comments & Ratings

**Structure:**
```
Blog Posts (collection)
  ├─ Comments (sub-collection)
  │   └─ Replies (nested sub-collection)
  └─ Ratings (sub-collection)
```

**Configuration:**
- Main collection: `entry_method: admin_only` (only admins create posts)
- Comments sub-collection: `entry_method: public_api` (public can comment)
- Replies sub-collection: `entry_method: public_api` (public can reply)

**Fields:**
- Comments: `author_name` (text), `comment` (editor), `created_at`
- Replies: `author_name` (text), `reply_text` (editor), `parent_comment_id`
- Ratings: `rating` (rating fieldtype), `review` (textarea)

---

### 2. Forum System

**Structure:**
```
Categories (collection)
  └─ Topics (sub-collection)
      └─ Posts (sub-collection)
          └─ Replies (sub-collection)
```

**Features:**
- Threaded discussion display
- Pinned topics
- Locked threads
- Activity-based sorting
- User reputation system

**Configuration:**
- Categories: `entry_method: admin_only`
- Topics: `entry_method: public_api` (authenticated users)
- Posts: `entry_method: public_api`
- Replies: `entry_method: public_api`

---

### 3. E-Commerce Platform

**Structure:**
```
Products (collection)
  ├─ Variants (sub-collection: size, color, SKU, price)
  ├─ Reviews (sub-collection: rating, comment, author)
  │   └─ Review Replies (sub-collection)
  ├─ Questions (sub-collection: Q&A)
  │   └─ Answers (sub-collection)
  └─ Inventory History (sub-collection: stock changes)
```

**Features:**
- Product variants management
- Customer reviews with ratings
- Q&A section
- Stock tracking
- Average rating calculations

**Configuration:**
- Products: `entry_method: admin_only`
- Variants: `entry_method: admin_only`
- Reviews: `entry_method: public_api`
- Questions: `entry_method: public_api`
- Answers: `entry_method: public_api`

---

### 4. Project Management System

**Structure:**
```
Projects (collection)
  └─ Tasks (sub-collection)
      ├─ Subtasks (sub-collection)
      ├─ Comments (sub-collection)
      ├─ Time Logs (sub-collection)
      └─ Attachments (sub-collection)
```

**Features:**
- Task hierarchies
- Time tracking
- File attachments
- Task dependencies
- Status workflows

---

### 5. Documentation System

**Structure:**
```
Documentation (collection)
  └─ Sections (sub-collection)
      └─ Articles (sub-collection)
          ├─ Versions (sub-collection: version history)
          ├─ Comments (sub-collection)
          └─ Translations (sub-collection)
```

**Features:**
- Hierarchical navigation
- Version control
- Multi-language support
- Comment threads

---

### 6. Social Network / Community

**Structure:**
```
Posts (collection)
  ├─ Comments (sub-collection)
  │   └─ Replies (sub-collection)
  ├─ Reactions (sub-collection: like, love, etc.)
  └─ Shares (sub-collection: who shared)
```

**Features:**
- Real-time activity feeds
- User notifications
- Privacy controls
- Engagement metrics

---

### 7. Help Desk / Support System

**Structure:**
```
Tickets (collection)
  ├─ Replies (sub-collection)
  ├─ Attachments (sub-collection)
  ├─ Status History (sub-collection: audit trail)
  └─ Internal Notes (sub-collection: admin-only)
```

**Features:**
- Public vs. private content
- Status transitions
- Audit trails
- SLA tracking

---

### 8. Learning Management System

**Structure:**
```
Courses (collection)
  └─ Lessons (sub-collection)
      ├─ Assignments (sub-collection)
      │   └─ Submissions (sub-collection: student work)
      ├─ Quizzes (sub-collection)
      │   └─ Attempts (sub-collection)
      └─ Discussion (sub-collection: Q&A)
```

**Features:**
- Student-specific data
- Grading workflows
- Progress tracking
- Enrollment management

---

### 9. Recipe Platform

**Structure:**
```
Recipes (collection)
  ├─ Ingredients (sub-collection: with quantities)
  ├─ Steps (sub-collection: ordered instructions)
  ├─ Reviews (sub-collection)
  ├─ Variations (sub-collection: recipe modifications)
  └─ Tips (sub-collection: cooking tips)
```

**Features:**
- Ordered lists (sequential steps)
- Quantity calculations
- Recipe scaling
- Search by ingredient

---

### 10. Event Management

**Structure:**
```
Events (collection)
  ├─ Sessions (sub-collection: time slots)
  │   └─ Attendees (sub-collection: registrations)
  ├─ Speakers (sub-collection: relation to users)
  └─ Feedback (sub-collection: post-event surveys)
```

**Features:**
- Capacity management
- Waitlists
- Check-in tracking
- Analytics

---

## System Architecture

### Relationship Model

#### Parent-Child Relationships
- One parent can have multiple child types (e.g., product has reviews, Q&A, variants)
- Children can have multiple child types (unlimited nesting)
- Track full ancestry path for deep hierarchies

#### Sibling Relationships
- Query all entries with the same parent
- Order siblings (chronological, custom, by field value)

#### Cross-References
- Reference entries from other collections
- Support for any-to-any relationships (not just parent-child)

#### Polymorphic Relationships
- An entry can belong to different parent types
- Example: A "Comment" could be on a "Post" OR a "Product"

### Data Flow

```
Collection Editor
  └─ Define Sub-Collections
      └─ Configure Entry Method (independent from parent)
          └─ Define Fields
              └─ Set Permissions
                  └─ Save Configuration

Entry Editor
  └─ View Parent Entry
      └─ Access Sub-Entry Tabs
          └─ List Sub-Entries
              └─ Add/Edit/Delete Sub-Entries
                  └─ Moderate (if public submissions)

Public API
  └─ Submit Sub-Entry
      └─ Auto-populate Parent Reference
          └─ Validate & Save
              └─ Trigger Notifications
```

---

## Data Structures

### Collection Configuration (`collection.json`)

```json
{
  "name": "Blog Posts",
  "slug": "blog",
  "entry_method": "admin_only",
  "sub_collections": [
    {
      "id": "blog_comments",
      "name": "Comments",
      "slug": "blog_comments",
      
      "entry_method": "public_api",
      "entry_settings": {
        "auto_publish": false,
        "moderation_required": true,
        "rate_limit": 10,
        "notification_email": "admin@example.com"
      },
      
      "parent_collection": "blog",
      "parent_field_name": "blog_post_id",
      "parent_required": true,
      
      "allow_multiple_parents": false,
      "parent_types": ["blog"],
      
      "allow_nested": true,
      "max_nesting_depth": 5,
      
      "display_mode": "inline",
      "default_sort": "created_at_desc",
      
      "permissions": {
        "create": "public",
        "edit": "admin",
        "delete": "admin",
        "view": "public"
      },
      
      "schema": {
        "fields": [
          {
            "name": "author_name",
            "type": "text",
            "label": "Name",
            "required": true
          },
          {
            "name": "comment",
            "type": "editor",
            "label": "Comment",
            "required": true
          },
          {
            "name": "rating",
            "type": "rating",
            "label": "Rating",
            "max_stars": 5
          }
        ]
      },
      
      "aggregations": {
        "count": true,
        "average_rating": {
          "field": "rating",
          "enabled": true
        }
      }
    }
  ]
}
```

### Sub-Entry Data Structure

```json
{
  "id": "comment_123",
  
  "parents": [
    {
      "collection": "blog",
      "entry_id": "blog_456",
      "relationship_type": "primary"
    }
  ],
  
  "parent_collection": "blog",
  "parent_entry_id": "blog_456",
  
  "ancestry": [
    {"collection": "blog", "entry_id": "blog_456"},
    {"collection": "blog_comments", "entry_id": "comment_789"}
  ],
  
  "blog_post_id": "blog_456",
  
  "author_name": "John Doe",
  "comment": "Great post!",
  "rating": 5,
  
  "status": "published",
  "created_at": "2024-01-15T10:30:00Z",
  "updated_at": "2024-01-15T10:30:00Z",
  "created_by": "user_123",
  "submitted_via": "public_api",
  "submitted_ip": "192.168.1.1"
}
```

### Nested Sub-Entry Example

```json
{
  "id": "reply_789",
  "parent_collection": "blog_comments",
  "parent_entry_id": "comment_123",
  "ancestry": [
    {"collection": "blog", "entry_id": "blog_456"},
    {"collection": "blog_comments", "entry_id": "comment_123"}
  ],
  "comment_id": "comment_123",
  "blog_post_id": "blog_456",
  "author_name": "Jane Smith",
  "reply_text": "I agree!",
  "status": "published"
}
```

---

## API Design

### Query Endpoints

#### Get Sub-Entries

```http
GET /api/content.php?action=get_sub_entries&parent_collection=blog&parent_id=123&sub_collection=comments
```

**Response:**
```json
{
  "status": "ok",
  "data": {
    "entries": [
      {
        "id": "comment_123",
        "author_name": "John Doe",
        "comment": "Great post!",
        "rating": 5,
        "created_at": "2024-01-15T10:30:00Z"
      }
    ],
    "count": 1,
    "aggregates": {
      "average_rating": 4.5,
      "total_count": 10
    }
  }
}
```

#### Get Sub-Entries with Filters

```http
GET /api/content.php?action=get_sub_entries
  &parent_collection=blog
  &parent_id=123
  &sub_collection=reviews
  &filter[rating][>=]=4
  &filter[status]=published
  &sort=created_at_desc
  &limit=10
  &offset=0
```

**Filter Operators:**
- `=` (equals)
- `!=` (not equals)
- `>` (greater than)
- `>=` (greater than or equal)
- `<` (less than)
- `<=` (less than or equal)
- `IN` (in array)
- `LIKE` (pattern match)
- `BETWEEN` (range)

#### Get Aggregated Data

```http
GET /api/content.php?action=get_aggregate
  &parent_collection=blog
  &parent_id=123
  &sub_collection=reviews
  &aggregate=average
  &field=rating
```

**Aggregation Types:**
- `count` - Count of entries
- `average` - Average of numeric field
- `sum` - Sum of numeric field
- `min` - Minimum value
- `max` - Maximum value
- `group_by` - Group by field value

#### Get Entry with All Children (Nested)

```http
GET /api/content.php?action=get_entry_with_children
  &collection=blog
  &id=123
  &include_children=true
  &max_depth=3
  &sub_collections=comments,reviews
```

**Response:**
```json
{
  "status": "ok",
  "data": {
    "entry": {
      "id": "blog_123",
      "title": "My Post",
      "content": "..."
    },
    "children": {
      "comments": [
        {
          "id": "comment_1",
          "comment": "...",
          "children": {
            "replies": [...]
          }
        }
      ],
      "reviews": [...]
    }
  }
}
```

#### Get Ancestors

```http
GET /api/content.php?action=get_ancestors
  &collection=blog_comments
  &entry_id=comment_123
```

**Response:**
```json
{
  "status": "ok",
  "data": {
    "ancestors": [
      {"collection": "blog", "entry_id": "blog_456", "title": "My Post"}
    ]
  }
}
```

#### Get Siblings

```http
GET /api/content.php?action=get_siblings
  &collection=blog_comments
  &entry_id=comment_123
  &include_self=false
```

### Submission Endpoints

#### Submit Sub-Entry

```http
POST /api/submit_sub_entry
Content-Type: application/json

{
  "parent_collection": "blog",
  "parent_entry_id": "blog_456",
  "sub_collection": "comments",
  "fields": {
    "author_name": "John Doe",
    "comment": "Great post!",
    "rating": 5
  },
  "_csrf": "token_here"
}
```

**Response:**
```json
{
  "status": "ok",
  "message": "Comment submitted successfully",
  "data": {
    "id": "comment_123",
    "status": "pending",
    "message": "Your comment is awaiting moderation"
  }
}
```

#### Submit Nested Sub-Entry

```http
POST /api/submit_sub_entry
Content-Type: application/json

{
  "parent_collection": "blog_comments",
  "parent_entry_id": "comment_123",
  "sub_collection": "replies",
  "fields": {
    "author_name": "Jane Smith",
    "reply_text": "I agree!"
  }
}
```

### Helper Functions (PHP)

```php
// Get direct children
function getChildren($parentId, $subCollectionSlug = null, $options = [])

// Get all descendants (recursive)
function getDescendants($parentId, $maxDepth = null, $subCollectionSlug = null)

// Get ancestors (walk up the tree)
function getAncestors($entryId, $collectionSlug)

// Get siblings
function getSiblings($entryId, $includeSelf = false)

// Get by relationship type
function getByRelationship($entryId, $relationshipType)

// Get aggregated data
function getAggregate($parentId, $subCollectionSlug, $aggregateType, $field = null)

// Filter children
getChildren($parentId, 'reviews', [
  'status' => 'published',
  'rating' => ['>=' => 4],
  'order_by' => 'created_at',
  'order_dir' => 'desc',
  'limit' => 10
])
```

---

## User Interface Design

### Collection Editor - Sub-Entries Section

**Location:** New tab/section in collection editor sidebar

**Layout:**
```
┌─────────────────────────────────────┐
│ Sub-Entries                        │
├─────────────────────────────────────┤
│ [Add Sub-Collection]               │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ Comments                        │ │
│ │ slug: blog_comments             │ │
│ │ Entry Method: [Public API ▼]    │ │
│ │ Fields: 3                        │ │
│ │ Nested: ✓                        │ │
│ │ [Edit] [Delete]                 │ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ Replies (nested)                │ │
│ │ slug: blog_comments_replies     │ │
│ │ Parent: Comments                │ │
│ │ Entry Method: [Public API ▼]    │ │
│ │ [Edit] [Delete]                 │ │
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘
```

**Add Sub-Collection Modal:**
- Name (required)
- Slug (auto-generated, editable)
- Entry Method (admin_only / public_api)
- Parent Collection (auto-set to current collection)
- Allow Nested (checkbox)
- Fields Builder (same as main collection)
- Permissions Settings
- Aggregation Settings

### Entry Editor - Sub-Entries Tabs

**Location:** New tabs in entry editor (after main content fields)

**Layout:**
```
┌─────────────────────────────────────┐
│ Blog Post: "My First Post"          │
├─────────────────────────────────────┤
│ [Content] [Comments] [Reviews] [Settings] │ ← Tabs
├─────────────────────────────────────┤
│ Comments (5)                        │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ John Doe | ⭐⭐⭐⭐⭐            │ │
│ │ "Great article!"                │ │
│ │ 2 hours ago | [Approve] [Delete]│ │
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ [Add Comment] (if admin)        │ │
│ │ or                              │ │
│ │ Public form appears here        │ │
│ └─────────────────────────────────┘ │
│                                     │
│ [Bulk Actions] [Approve All] [Delete Selected] │
└─────────────────────────────────────┘
```

**Features:**
- List all sub-entries for current parent
- Inline editing (if admin)
- Bulk actions (approve, delete, publish)
- Filter by status
- Sort options
- Search within sub-entries
- Add new sub-entry button (if permissions allow)
- Public submission form (if public API enabled)

### Sub-Entry Management Interface

**Separate Management Page:**
- List all sub-entries across all parents
- Filter by parent collection/entry
- Filter by status
- Bulk operations
- Export capabilities

---

## Implementation Phases

### Phase 1: Core Foundation (MVP)

**Goal:** Basic sub-entry functionality

**Tasks:**
1. Extend collection schema to support `sub_collections` array
2. Add sub-collection configuration UI in collection editor
3. Implement parent field auto-generation
4. Create sub-entry storage structure
5. Basic query API: `getSubEntries()`
6. Entry editor integration: show sub-entries tab
7. Admin interface: add/edit/delete sub-entries
8. Separate `entry_method` per sub-collection

**Deliverables:**
- Sub-collections can be created and configured
- Sub-entries can be created and linked to parents
- Basic listing of sub-entries in entry editor
- Independent entry submission settings

**Estimated Time:** 2-3 weeks

---

### Phase 2: Nesting & Advanced Relationships

**Goal:** Support nested sub-entries and complex relationships

**Tasks:**
1. Implement nested sub-collection support
2. Ancestry tracking in entry data
3. Tree traversal functions (`getDescendants`, `getAncestors`)
4. Sibling queries (`getSiblings`)
5. Multiple parent support (polymorphic relationships)
6. Cross-collection references

**Deliverables:**
- Unlimited nesting depth
- Ancestry path tracking
- Sibling queries
- Cross-reference support

**Estimated Time:** 1-2 weeks

---

### Phase 3: Query & Filtering System

**Goal:** Rich querying capabilities

**Tasks:**
1. Advanced filtering system
2. Multiple sort options
3. Pagination support
4. Field-based filtering
5. Date range queries
6. Status filtering
7. Full-text search integration

**Deliverables:**
- Comprehensive filter API
- Flexible sorting
- Pagination
- Search capabilities

**Estimated Time:** 1-2 weeks

---

### Phase 4: Aggregations & Analytics

**Goal:** Data aggregation and statistics

**Tasks:**
1. Count aggregations
2. Numeric aggregations (average, sum, min, max)
3. Group by functionality
4. Aggregate API endpoints
5. Display aggregates in UI
6. Real-time aggregate updates

**Deliverables:**
- Aggregation functions
- Aggregate API
- UI display of aggregates
- Performance optimization

**Estimated Time:** 1 week

---

### Phase 5: Public API & Submissions

**Goal:** Public submission of sub-entries

**Tasks:**
1. Public API endpoint for sub-entry submission
2. Auto-populate parent reference
3. Validation and sanitization
4. Rate limiting per sub-collection
5. Moderation workflow
6. Email notifications
7. CSRF protection
8. Spam prevention

**Deliverables:**
- Public submission API
- Moderation system
- Notification system
- Security measures

**Estimated Time:** 1-2 weeks

---

### Phase 6: Permissions & Access Control

**Goal:** Fine-grained permissions

**Tasks:**
1. Permission system per sub-collection
2. Per-entry permissions
3. Role-based access control
4. Public/authenticated/admin permissions
5. Visibility controls
6. Permission inheritance

**Deliverables:**
- Permission system
- Access control
- Role management
- Visibility settings

**Estimated Time:** 1 week

---

### Phase 7: Display Modes & UI Enhancements

**Goal:** Multiple display options

**Tasks:**
1. List display mode
2. Tree display mode
3. Threaded display (forum-style)
4. Grid display
5. Timeline display
6. Custom ordering (drag-and-drop)
7. Display mode configuration

**Deliverables:**
- Multiple display modes
- Custom ordering
- UI components
- Configuration options

**Estimated Time:** 1-2 weeks

---

### Phase 8: Activity & Notifications

**Goal:** Activity tracking and notifications

**Tasks:**
1. Activity stream system
2. Change tracking
3. Notification hooks
4. Email notifications
5. Real-time updates (optional)
6. Activity log

**Deliverables:**
- Activity tracking
- Notification system
- Email alerts
- Activity logs

**Estimated Time:** 1 week

---

### Phase 9: Template Helpers & Rendering

**Goal:** Template integration

**Tasks:**
1. Template helper functions
2. Rendering components
3. Nested rendering
4. Pagination helpers
5. Filter helpers
6. Aggregate display helpers

**Deliverables:**
- Template helpers
- Rendering components
- Documentation
- Examples

**Estimated Time:** 1 week

---

### Phase 10: Testing & Documentation

**Goal:** Quality assurance and documentation

**Tasks:**
1. Unit tests
2. Integration tests
3. User acceptance testing
4. Performance testing
5. Documentation
6. Example templates
7. Migration guides

**Deliverables:**
- Test suite
- Complete documentation
- Example implementations
- Migration tools

**Estimated Time:** 1-2 weeks

---

## Technical Specifications

### Database/Storage Structure

**File Organization:**
```
content/
  collections/
    blog/
      collection.json (with sub_collections config)
      schema.json
      entries/
        blog_123.json
    blog_comments/
      collection.json
      schema.json
      entries/
        comment_456.json (with parent references)
```

### Parent Field Auto-Generation

**Naming Convention:**
- Format: `{parent_collection_slug}_id`
- Example: `blog_post_id` for sub-entries of `blog` collection
- Stored in entry data and schema

**Field Type:**
- Special field type: `parent_reference`
- Auto-populated on creation
- Not editable by users
- Required field

### Query Performance

**Optimization Strategies:**
1. Index parent references for fast lookups
2. Cache sub-entry counts
3. Lazy loading for nested structures
4. Pagination for large result sets
5. Aggregate caching

### Security Considerations

1. **CSRF Protection**: All submission endpoints require CSRF tokens
2. **Rate Limiting**: Per sub-collection rate limits
3. **Input Validation**: Strict validation of all inputs
4. **Permission Checks**: Verify permissions before operations
5. **Sanitization**: All user inputs sanitized
6. **Spam Prevention**: Honeypot, CAPTCHA, Akismet integration

### Migration Path

**Backward Compatibility:**
- Existing collections work without changes
- Sub-collections are optional
- No breaking changes to existing APIs
- Gradual migration possible

**Migration Tools:**
- Convert existing related entries to sub-entries
- Import from external systems
- Bulk operations

---

## Examples & Templates

### Example 1: Blog with Comments

**Collection Configuration:**
```json
{
  "name": "Blog Posts",
  "slug": "blog",
  "entry_method": "admin_only",
  "sub_collections": [
    {
      "id": "blog_comments",
      "name": "Comments",
      "slug": "blog_comments",
      "entry_method": "public_api",
      "parent_collection": "blog",
      "schema": {
        "fields": [
          {"name": "author_name", "type": "text", "label": "Name"},
          {"name": "comment", "type": "editor", "label": "Comment"},
          {"name": "rating", "type": "rating", "label": "Rating", "max_stars": 5}
        ]
      }
    }
  ]
}
```

**Template Usage:**
```php
// Get blog post
$post = getEntry('blog', 'my-first-post');

// Get comments
$comments = getSubEntries($post['id'], 'blog_comments', [
  'status' => 'published',
  'sort' => 'created_at_desc'
]);

// Get average rating
$avgRating = getAggregate($post['id'], 'blog_comments', 'average', 'rating');
```

### Example 2: Forum System

**Collection Configuration:**
```json
{
  "name": "Forum Categories",
  "slug": "forum_categories",
  "entry_method": "admin_only",
  "sub_collections": [
    {
      "id": "forum_topics",
      "name": "Topics",
      "slug": "forum_topics",
      "entry_method": "public_api",
      "parent_collection": "forum_categories",
      "allow_nested": true,
      "sub_collections": [
        {
          "id": "forum_posts",
          "name": "Posts",
          "slug": "forum_posts",
          "entry_method": "public_api",
          "parent_collection": "forum_topics",
          "allow_nested": true,
          "sub_collections": [
            {
              "id": "forum_replies",
              "name": "Replies",
              "slug": "forum_replies",
              "entry_method": "public_api",
              "parent_collection": "forum_posts"
            }
          ]
        }
      ]
    }
  ]
}
```

### Example 3: E-Commerce Product

**Collection Configuration:**
```json
{
  "name": "Products",
  "slug": "products",
  "entry_method": "admin_only",
  "sub_collections": [
    {
      "id": "product_variants",
      "name": "Variants",
      "slug": "product_variants",
      "entry_method": "admin_only",
      "parent_collection": "products"
    },
    {
      "id": "product_reviews",
      "name": "Reviews",
      "slug": "product_reviews",
      "entry_method": "public_api",
      "parent_collection": "products",
      "aggregations": {
        "count": true,
        "average_rating": {"field": "rating", "enabled": true}
      }
    },
    {
      "id": "product_questions",
      "name": "Questions",
      "slug": "product_questions",
      "entry_method": "public_api",
      "parent_collection": "products",
      "allow_nested": true,
      "sub_collections": [
        {
          "id": "product_answers",
          "name": "Answers",
          "slug": "product_answers",
          "entry_method": "public_api",
          "parent_collection": "product_questions"
        }
      ]
    }
  ]
}
```

### Template Helper Functions

```php
// Get sub-entries
function getSubEntries($parentId, $subCollectionSlug, $options = []) {
  // Implementation
}

// Get with filters
getSubEntries($parentId, 'reviews', [
  'status' => 'published',
  'rating' => ['>=' => 4],
  'sort' => 'created_at_desc',
  'limit' => 10
]);

// Get aggregated data
function getAggregate($parentId, $subCollectionSlug, $type, $field = null) {
  // Implementation
}

// Get ancestors
function getAncestors($entryId, $collectionSlug) {
  // Implementation
}

// Get siblings
function getSiblings($entryId, $includeSelf = false) {
  // Implementation
}
```

### JavaScript Client API

```javascript
// Get sub-entries
haru.getSubEntries('blog', 'blog_123', 'blog_comments', {
  status: 'published',
  sort: 'created_at_desc',
  limit: 10
}).then(comments => {
  console.log(comments);
});

// Submit sub-entry
haru.submitSubEntry('blog', 'blog_123', 'blog_comments', {
  author_name: 'John Doe',
  comment: 'Great post!',
  rating: 5
}).then(result => {
  console.log('Comment submitted:', result);
});

// Get aggregate
haru.getAggregate('blog', 'blog_123', 'blog_comments', 'average', 'rating')
  .then(avgRating => {
    console.log('Average rating:', avgRating);
  });
```

---

## Future Enhancements

### Potential Additions

1. **GraphQL API**: GraphQL endpoint for flexible queries
2. **Real-time Updates**: WebSocket support for live updates
3. **Advanced Analytics**: Detailed analytics and reporting
4. **Export/Import**: Bulk export and import capabilities
5. **Versioning**: Version control for sub-entries
6. **Workflows**: Custom workflow engines
7. **Templates**: Pre-built templates for common use cases
8. **Visual Builder**: Visual relationship builder
9. **Migration Tools**: Tools to migrate from other systems
10. **Performance Monitoring**: Built-in performance metrics

---

## Success Criteria

### Functional Requirements
- ✅ Users can create sub-collections with independent entry methods
- ✅ Sub-entries can be nested to unlimited depth
- ✅ Multiple sub-collection types per parent
- ✅ Rich querying and filtering capabilities
- ✅ Aggregation functions work correctly
- ✅ Public API submissions function properly
- ✅ Permissions are enforced correctly

### Performance Requirements
- Query response time < 200ms for typical queries
- Support 10,000+ sub-entries per parent
- Efficient pagination for large datasets
- Caching reduces database load by 80%

### Usability Requirements
- Intuitive UI for managing sub-collections
- Clear documentation and examples
- Easy migration from existing setups
- Template helpers simplify common tasks

---

## Notes & Considerations

### Design Decisions

1. **File-based Storage**: Continue using file-based storage for consistency
2. **Parent Field Naming**: Auto-generate parent field names for consistency
3. **Unlimited Nesting**: No artificial limits on nesting depth
4. **Independent Settings**: Each sub-collection has independent entry method
5. **Flexible Relationships**: Support for complex relationship patterns

### Known Limitations

1. **Performance**: Deep nesting may impact performance (mitigated by caching)
2. **Complexity**: Very deep hierarchies may be difficult to manage
3. **Migration**: Existing related entries need manual migration

### Open Questions

1. Should we support circular references?
2. How to handle orphaned sub-entries when parent is deleted?
3. Should sub-entries be searchable independently?
4. How to handle sub-entry permissions in public templates?

---

## Conclusion

This Sub-Entries System provides a flexible, powerful foundation for building complex applications within HaruCMS. By supporting unlimited nesting, multiple sub-collection types, and rich querying capabilities, users can build forums, e-commerce platforms, project management systems, and much more.

The phased implementation approach ensures we can deliver value incrementally while building toward a comprehensive solution. Each phase builds upon the previous, creating a robust and flexible system.

---

**Document Version:** 1.0  
**Last Updated:** 2024-01-15  
**Status:** Planning Phase
