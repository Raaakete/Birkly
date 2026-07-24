# Entry Visibility - Generic Design

## The Problem
"friends-only" is too specific - it's a made-up relation just for social media projects.

## Solution: Generic Visibility

### Simple Visibility Field
Entry has a `visibility` field with generic values:

```json
{
  "visibility": "public" | "private" | "relationship"
}
```

### Frontend Implements Logic

**"public"** - Everyone can see
```html
{if {entry.visibility} == "public"}
    {entry.content}
{/if}
```

**"private"** - Only author
```html
{if {entry.visibility} == "private"}
    {if {entry.author_id} == {user_current.id}}
        {entry.content}
    {/if}
{/if}
```

**"relationship"** - Frontend decides what relationship to check
```html
{if {entry.visibility} == "relationship"}
    <!-- Frontend decides: check friends, followers, group members, etc. -->
    {if {user_in_field {entry.author.friends}}}
        {entry.content}
    {elseif {user_in_field {entry.author.followers}}}
        {entry.content}
    {elseif {user_in_field {entry.group.members}}}
        {entry.content}
    {/if}
{/if}
```

## Benefits

### ✅ Generic
- Not tied to specific relationship types
- Works for any use case (social media, forums, communities, etc.)

### ✅ Flexible
- Frontend implements custom logic
- Can check multiple relationships
- Can combine conditions

### ✅ Simple
- Just three visibility types
- Frontend handles complexity

## Alternative: No Visibility Field

**Even simpler:** Frontend checks whatever it wants:

```html
<!-- Check if user is in friends list -->
{if {user_in_field {entry.author.friends}}}
    {entry.content}
{/if}

<!-- Check if user is in group -->
{if {user_in_field {entry.group.members}}}
    {entry.content}
{/if}

<!-- Check multiple conditions -->
{if {user_in_field {entry.author.friends}} OR {user_in_field {entry.author.followers}}}
    {entry.content}
{/if}
```

**No visibility field needed!** Frontend decides access rules.

## Recommendation

**Option 1: Simple visibility field** (if you want a hint)
- `"public"` - Everyone
- `"private"` - Only author
- `"relationship"` - Frontend checks relationships

**Option 2: No visibility field** (most flexible)
- Frontend checks whatever it wants
- No backend field needed
- Maximum flexibility
