# Entry Visibility - Frontend Approach

## The Question
**Why do we need a backend entry visibility system when the frontend can check relationships directly?**

## Answer: You're Absolutely Right!

### Simple Frontend Approach

**Entry has a `visibility` field:**
```json
{
  "entry_id": "post123",
  "author_id": "user123",
  "visibility": "public" | "private" | "friends-only",
  "content": "..."
}
```

**Frontend template:**
```html
{if {user_logged_in}}
    {if {entry.visibility} == "public"}
        <!-- Show content to everyone -->
        {entry.content}
    {elseif {entry.visibility} == "friends-only"}
        <!-- Check if viewer is in author's friends list -->
        {set author_profile = {user_by_id {entry.author_id}}}
        {if {user_in_field {author_profile.friends}}}
            {entry.content}
        {else}
            <p>This post is only visible to friends.</p>
        {/if}
    {elseif {entry.visibility} == "private"}
        {if {entry.author_id} == {user_current.id}}
            {entry.content}
        {else}
            <p>This is a private post.</p>
        {/if}
    {/if}
{else}
    {if {entry.visibility} == "public"}
        {entry.content}
    {else}
        <p>Please log in to view this content.</p>
    {/if}
{/if}
```

## Why This Is Better

### ✅ Simpler
- No backend permission system needed
- All logic in templates (easy to understand and modify)
- No complex permission resolution

### ✅ More Flexible
- Frontend can implement any visibility rule
- Easy to add custom visibility types
- Can check multiple relationships (friends, followers, mutual friends)

### ✅ Better Performance
- No backend permission queries
- Frontend already has entry data
- Just check user fieldtype fields

## What We Actually Need

**Just a simple `visibility` field on entries:**
- `"public"` - Everyone can see
- `"private"` - Only author can see
- `"friends-only"` - Only friends can see (check user fieldtype)
- `"custom"` - Frontend implements custom logic

**Template functions we already have:**
- `{user_logged_in}` ✅
- `{user_current}` ✅
- `{user_in_field}` ✅
- `{user_by_id}` ✅

## Conclusion

**No backend visibility system needed!** Frontend handles it all using:
1. Entry's `visibility` field
2. User fieldtype checks
3. Simple template logic

This is simpler, more flexible, and more performant.
