# Sub-Entries System Documentation

## Overview

The Sub-Entries System allows you to create hierarchical content relationships by attaching sub-entries to parent entries. This enables features like comments, reviews, nested content, and more.

## Core Concepts

### Sub-Collections
Sub-collections are defined in the collection schema and specify what types of sub-entries can be attached to entries in that collection.

### Sub-Entries
Sub-entries are child entries that belong to a parent entry. They have their own data structure and can be queried independently.

### Ancestry Tracking
Sub-entries automatically track their ancestry path, enabling nested sub-entries (sub-entries of sub-entries) with unlimited depth.

## Configuration

### Defining Sub-Collections

In your collection schema (`collection.json`), add a `sub_collections` array:

```json
{
  "name": "Blog Posts",
  "slug": "blog_posts",
  "fields": [...],
  "sub_collections": [
    {
      "name": "Comments",
      "slug": "comments",
      "entry_method": "public",
      "auto_publish": false,
      "schema": {
        "fields": [
          {
            "name": "author",
            "type": "Text",
            "required": true
          },
          {
            "name": "content",
            "type": "Textarea",
            "required": true
          }
        ]
      }
    }
  ]
}
```

### Sub-Collection Options

- **name**: Display name for the sub-collection
- **slug**: Unique identifier (auto-generated if not provided)
- **entry_method**: `public` (anyone can submit) or `admin` (admin only)
- **auto_publish**: If `true`, submitted entries are automatically published
- **schema**: Field definitions for sub-entries

## API Reference

### List Sub-Entries
```
GET /api/sub_entries.php?action=list&collection=blog_posts&entry_id=123&sub_collection=comments
```

With filters and options:
```
POST /api/sub_entries.php?action=list
{
    "collection": "blog_posts",
    "entry_id": "123",
    "sub_collection": "comments",
    "filters": {
        "status": "published"
    },
    "options": {
        "sort": "created_at",
        "order": "desc",
        "limit": 10,
        "offset": 0
    }
}
```

### Get Single Sub-Entry
```
GET /api/sub_entries.php?action=get&collection=blog_posts&entry_id=123&sub_collection=comments&sub_entry_id=sub_456
```

### Create Sub-Entry
```
POST /api/sub_entries.php?action=create
{
    "collection": "blog_posts",
    "entry_id": "123",
    "sub_collection": "comments",
    "data": {
        "author": "John Doe",
        "content": "Great post!",
        "status": "published"
    }
}
```

### Update Sub-Entry
```
POST /api/sub_entries.php?action=update
{
    "collection": "blog_posts",
    "entry_id": "123",
    "sub_collection": "comments",
    "sub_entry_id": "sub_456",
    "data": {
        "content": "Updated comment"
    }
}
```

### Delete Sub-Entry
```
POST /api/sub_entries.php?action=delete
{
    "collection": "blog_posts",
    "entry_id": "123",
    "sub_collection": "comments",
    "sub_entry_id": "sub_456"
}
```

### Count Sub-Entries
```
GET /api/sub_entries.php?action=count&collection=blog_posts&entry_id=123&sub_collection=comments
```

### Get Siblings
Get other sub-entries with the same parent:
```
GET /api/sub_entries.php?action=siblings&collection=blog_posts&entry_id=123&sub_collection=comments&sub_entry_id=sub_456
```

### Aggregations
Get sub-entries with aggregations:
```
POST /api/sub_entries.php?action=aggregations
{
    "collection": "blog_posts",
    "entry_id": "123",
    "sub_collection": "comments",
    "filters": {
        "status": "published"
    },
    "aggregations": {
        "count": true,
        "avg": "rating",
        "sum": "votes"
    }
}
```

### Nested Sub-Entries
Get sub-entries of a sub-entry:
```
POST /api/sub_entries.php?action=nested
{
    "collection": "blog_posts",
    "entry_id": "123",
    "sub_collection": "comments",
    "parent_sub_entry_id": "sub_456",
    "filters": {},
    "options": {}
}
```

## Public Submission API

For public sub-entry submission (when `entry_method` is `public`):

```
POST /api/public_sub_entry.php
{
    "parent_collection": "blog_posts",
    "parent_entry_id": "123",
    "sub_collection": "comments",
    "data": {
        "author": "John Doe",
        "content": "My comment"
    }
}
```

The API includes:
- Rate limiting
- Honeypot CSRF protection
- Automatic status setting based on `auto_publish`
- Email notifications for moderation

## Filtering

Filters support multiple operators:

```php
$filters = [
    'status' => 'published',  // Equals
    'rating' => ['operator' => '>', 'value' => 4],  // Greater than
    'author' => ['operator' => 'contains', 'value' => 'John'],  // Contains
    'category' => ['operator' => 'in', 'value' => ['tech', 'news']]  // In array
];
```

Available operators:
- `=` (default): Equals
- `!=`: Not equals
- `>`: Greater than
- `>=`: Greater than or equal
- `<`: Less than
- `<=`: Less than or equal
- `contains`: String contains
- `in`: Value in array

## Sorting

Sort by any field:

```php
$options = [
    'sort' => 'created_at',
    'order' => 'desc'  // or 'asc'
];
```

## Aggregations

Supported aggregation operations:
- **count**: Count of entries
- **sum**: Sum of numeric field
- **avg**: Average of numeric field
- **min**: Minimum value
- **max**: Maximum value

## Admin UI

Sub-entries are managed in the entry editor:
1. Open an entry for editing
2. Click the **Sub-Entries** tab in the sidebar
3. View, add, edit, or delete sub-entries

## Hooks

The system fires hooks for integration:

- `sub_entry_created`: When a sub-entry is created
- `sub_entry_updated`: When a sub-entry is updated
- `sub_entry_deleted`: When a sub-entry is deleted

## Automation Integration

Sub-entries trigger automation events:
- `entry_created` (when sub-entry is created)
- `entry_updated` (when sub-entry is updated)
- `entry_deleted` (when sub-entry is deleted)
- `status_changed` (when sub-entry status changes)

The context includes:
- `is_sub_entry`: `true`
- `parent_collection`: Parent collection slug
- `parent_entry_id`: Parent entry ID

## Best Practices

1. **Use Sub-Collections for Related Content**: Comments, reviews, attachments
2. **Set Appropriate Permissions**: Use `entry_method` to control who can create sub-entries
3. **Use Auto-Publish Carefully**: Only enable for trusted sources
4. **Leverage Aggregations**: Use aggregations for statistics and summaries
5. **Cache Results**: The system includes caching for better performance
