# HaruCMS Robustness Roadmap

## Overview
This document outlines the improvements needed to make HaruCMS production-ready and competitive with other CMS platforms.

## Priority 1: Core Robustness (2-3 weeks)

### 1. Performance Optimizations
**Current State:** No caching, scans all files for every request
**Target:** Fast performance with 1000+ entries

**Implementation:**
- [ ] Add file-based cache for entries list (`cache/collections/{slug}/entries.json`)
- [ ] Create index file for search (`cache/collections/{slug}/index.json`)
- [ ] Implement cache invalidation on entry save/delete
- [ ] Add lazy loading for large collections (pagination)
- [ ] Optimize file reading (cache directory listings)

**Files to Modify:**
- `api/admin_entries.php` - Add caching layer
- `api/content.php` - Add caching for public API
- `core/cache.php` - New cache management class

**Expected Impact:**
- 10x faster entries list loading
- 100x faster search queries
- Handles 1000+ entries without performance issues

### 2. Advanced Full-Text Search
**Current State:** Only searches titles
**Target:** Search across all fields, full-text indexing

**Implementation:**
- [ ] Create search index structure (JSON file per collection)
- [ ] Index all text fields when saving entries
- [ ] Implement full-text search algorithm
- [ ] Add search UI with filters (field, date, status)
- [ ] Add search highlighting in results

**Files to Modify:**
- `api/admin_entries.php` - Add search indexing
- `api/search.php` - New search API endpoint
- `admin/js/entries.js` - Add search UI
- `admin/templates/entries.html` - Add search interface

**Expected Impact:**
- Users can find content quickly
- Search across all fields, not just titles
- Better content discovery

### 3. Bulk Operations
**Current State:** No bulk edit/delete/publish
**Target:** Select multiple entries, bulk actions

**Implementation:**
- [ ] Add checkboxes to entries table
- [ ] Add bulk action toolbar (edit, delete, publish, unpublish)
- [ ] Implement bulk processing API
- [ ] Add confirmation dialogs for bulk actions
- [ ] Add progress indicator for bulk operations

**Files to Modify:**
- `admin/templates/entries.html` - Add checkboxes and toolbar
- `admin/js/entries.js` - Add bulk selection logic
- `api/admin_entries.php` - Add bulk operations endpoint

**Expected Impact:**
- Save hours of manual work
- Essential for managing large collections
- Better user experience

## Priority 2: Workflow Features (2-3 weeks)

### 4. Visual Version Diff UI
**Current State:** Version history exists but no diff view
**Target:** Side-by-side comparison, highlight changes

**Implementation:**
- [ ] Add diff algorithm (compare JSON structures)
- [ ] Create visual diff UI component
- [ ] Add side-by-side comparison view
- [ ] Highlight changes (additions, deletions, modifications)
- [ ] Add "Restore this version" button

**Files to Modify:**
- `admin/js/entry-editor.js` - Enhance version history modal
- `admin/templates/entry-editor.html` - Add diff UI
- `api/admin_entries.php` - Add diff calculation endpoint

**Expected Impact:**
- Makes version history usable
- Easy to see what changed
- Confident rollbacks

### 5. Content Approval Workflow UI
**Current State:** Backend code exists but no UI
**Target:** Review interface, approval buttons, status indicators

**Implementation:**
- [ ] Add "Pending Review" status badge
- [ ] Create review interface for editors
- [ ] Add "Approve", "Reject", "Request Changes" buttons
- [ ] Add review comments/notes
- [ ] Add notification system for reviewers

**Files to Modify:**
- `admin/templates/entry-editor.html` - Add review UI
- `admin/js/entry-editor.js` - Add review actions
- `api/admin_entries.php` - Enhance review workflow (already exists)

**Expected Impact:**
- Enables team workflows
- Content quality control
- Better collaboration

### 6. Advanced Filtering & Sorting
**Current State:** Basic status filtering
**Target:** Filter by date ranges, multiple fields, complex queries

**Implementation:**
- [ ] Add filter UI (date range, status, fields)
- [ ] Implement query builder for filters
- [ ] Add saved filter presets
- [ ] Add multi-field sorting
- [ ] Add filter indicators (show active filters)

**Files to Modify:**
- `admin/templates/entries.html` - Add filter UI
- `admin/js/entries.js` - Add filter logic
- `api/admin_entries.php` - Add filter processing

**Expected Impact:**
- Better content management
- Find content quickly
- Customizable views

## Priority 3: Production Features (2-3 weeks)

### 7. Export/Import System
**Current State:** No backup/restore or migration tools
**Target:** Export collections, import from other CMS, backup/restore

**Implementation:**
- [ ] Create export API (JSON format)
- [ ] Add import validation
- [ ] Add backup system (full site backup)
- [ ] Add restore functionality
- [ ] Add import from WordPress/Strapi/etc.

**Files to Create:**
- `api/export.php` - Export API
- `api/import.php` - Import API
- `admin/templates/backup.html` - Backup UI

**Expected Impact:**
- Essential for production use
- Easy migration from other CMS
- Peace of mind with backups

### 8. Image Optimization
**Current State:** No automatic optimization
**Target:** WebP/AVIF generation, responsive sizes, lazy loading

**Implementation:**
- [ ] Add image processing library (GD or Imagick)
- [ ] Generate WebP/AVIF on upload
- [ ] Generate responsive sizes (thumbnail, medium, large)
- [ ] Add lazy loading to client library
- [ ] Add image optimization settings

**Files to Modify:**
- `api/upload.php` - Add image processing
- `api/admin_media.php` - Add optimization options
- `harucms-client.js` - Add lazy loading

**Expected Impact:**
- Better performance
- Smaller file sizes
- Faster page loads

### 9. Multi-Language Support (i18n)
**Current State:** No i18n system
**Target:** Language files, content translations, UI localization

**Implementation:**
- [ ] Create language file system (`lang/en.json`, `lang/de.json`)
- [ ] Add translation function
- [ ] Add language switcher in admin
- [ ] Add content translation system
- [ ] Link translations together

**Files to Create:**
- `core/i18n.php` - Translation system
- `lang/en.json` - English translations
- `lang/de.json` - German translations (example)

**Expected Impact:**
- Required for international use
- Multi-language content support
- Better global reach

## Priority 4: Polish (1-2 weeks)

### 10. Content Relationships Visualization
**Current State:** Relations exist but no visualization
**Target:** Visual graph of related content

**Implementation:**
- [ ] Add graph visualization library
- [ ] Create relationship explorer
- [ ] Add relationship indicators in entries
- [ ] Add relationship management UI

**Expected Impact:**
- Better understanding of content structure
- Visual content mapping
- Better content organization

### 11. Activity Feed Enhancement
**Current State:** Basic activity logging
**Target:** Real-time updates, filters, better UI

**Implementation:**
- [ ] Add real-time updates (polling or WebSocket)
- [ ] Add activity filters (user, action, date)
- [ ] Improve activity UI
- [ ] Add activity export

**Expected Impact:**
- Better collaboration visibility
- Audit trail
- Team awareness

### 12. Accessibility Improvements
**Current State:** Basic accessibility
**Target:** ARIA labels, keyboard navigation, screen reader support

**Implementation:**
- [ ] Audit all admin pages for accessibility
- [ ] Add ARIA labels to all interactive elements
- [ ] Improve keyboard navigation
- [ ] Add screen reader support
- [ ] Test with screen readers

**Expected Impact:**
- Required for compliance
- Better for all users
- Professional polish

## Implementation Timeline

### Week 1-2: Performance & Search
- Performance optimizations (caching, indexing)
- Advanced full-text search

### Week 3-4: Bulk Operations & Workflow
- Bulk operations
- Visual version diff UI
- Content approval workflow UI

### Week 5-6: Filters & Production Features
- Advanced filtering & sorting
- Export/import system
- Image optimization

### Week 7-8: Polish & i18n
- Multi-language support
- Content relationships visualization
- Activity feed enhancement
- Accessibility improvements

## Success Metrics

### Performance
- [ ] Entries list loads in < 500ms (1000 entries)
- [ ] Search returns results in < 200ms
- [ ] Page load time < 2s

### User Experience
- [ ] Users can find content in < 3 clicks
- [ ] Bulk operations complete in < 5s (100 entries)
- [ ] Version diff shows changes clearly

### Production Readiness
- [ ] Handles 1000+ entries without issues
- [ ] Backup/restore works reliably
- [ ] Multi-language content supported
- [ ] WCAG 2.1 AA compliance

## Notes

- Each feature should be tested with 1000+ entries
- Performance improvements should be measured before/after
- All features should maintain backward compatibility
- UI/UX should be consistent with existing design
