# HaruCMS Entry Editor Brainstorming

## Core Features

### 1. Field Types
- [x] Text (single line)
- [x] Textarea (multi-line)
- [ ] Rich Text (WYSIWYG)
  - [ ] TinyMCE integration
  - [ ] Image upload/insert
  - [ ] Link management
  - [ ] Table support
  - [ ] Code view
- [x] Number
  - [x] Min/max validation
  - [x] Step control
- [x] Select (single choice)
- [x] Checkbox
- [x] Date
- [x] DateTime
- [ ] Media
  - [ ] Image upload
  - [ ] File upload
  - [ ] Media library integration
  - [ ] Image cropping/resizing
  - [ ] Alt text and caption
- [ ] Reference
  - [ ] Link to other entries
  - [ ] Search/filter interface
  - [ ] Preview of linked content
- [ ] Repeater
  - [ ] Add/remove items
  - [ ] Reorder items
  - [ ] Nested fields
- [ ] Markdown
  - [ ] Live preview
  - [ ] Syntax highlighting
- [ ] Code
  - [ ] Syntax highlighting
  - [ ] Language selection
  - [ ] Line numbers
- [ ] Color
  - [ ] Color picker
  - [ ] Color palette
  - [ ] Opacity control
- [ ] Location
  - [ ] Map picker
  - [ ] Coordinates
  - [ ] Address lookup

### 2. Field Organization
- [x] Field groups
- [ ] Tabs
- [ ] Collapsible sections
- [ ] Conditional fields
  - [ ] Show/hide based on other field values
  - [ ] Complex conditions
- [ ] Field dependencies
  - [ ] Required if
  - [ ] Validation rules
  - [ ] Default values

### 3. Entry Management
- [x] Create/Edit/Delete
- [x] Draft system
- [ ] Version control
  - [ ] Save versions
  - [ ] Compare versions
  - [ ] Restore versions
- [ ] Publishing workflow
  - [x] Draft/Published/Archived states
  - [x] Scheduled publishing
  - [x] Expiry dates
  - [ ] Review process
  - [ ] Approval workflow
- [ ] Bulk operations
  - [ ] Bulk edit
  - [ ] Bulk delete
  - [ ] Bulk status change
- [ ] Import/Export
  - [ ] CSV import
  - [ ] JSON import
  - [ ] Export to various formats

### 4. SEO & Metadata
- [x] Meta title
- [x] Meta description
- [x] Custom slug
- [ ] Open Graph data
  - [ ] OG title
  - [ ] OG description
  - [ ] OG image
- [ ] Twitter Cards
- [ ] Canonical URLs
- [ ] Robots meta
- [ ] Schema.org markup

### 5. Media Management
- [ ] Media library
  - [ ] Grid/List view
  - [ ] Search/Filter
  - [ ] Tags/Categories
  - [ ] Usage tracking
- [ ] Image processing
  - [ ] Automatic thumbnails
  - [ ] Responsive images
  - [ ] WebP conversion
  - [ ] Lazy loading
- [ ] File management
  - [ ] File type restrictions
  - [ ] Size limits
  - [ ] Virus scanning
  - [ ] Version control

### 6. Validation & Error Handling
- [ ] Field validation
  - [ ] Required fields
  - [ ] Format validation
  - [ ] Custom validation rules
  - [ ] Cross-field validation
- [ ] Error display
  - [ ] Inline errors
  - [ ] Error summary
  - [ ] Error highlighting
- [ ] Auto-save
  - [ ] Periodic saving
  - [ ] Conflict resolution
  - [ ] Recovery

### 7. User Experience
- [ ] Keyboard shortcuts
- [ ] Drag and drop
- [ ] Auto-complete
- [ ] Field templates
- [ ] Quick edit
- [ ] Preview
  - [ ] Live preview
  - [ ] Mobile preview
  - [ ] Print preview
- [ ] Accessibility
  - [ ] ARIA labels
  - [ ] Keyboard navigation
  - [ ] Screen reader support

### 8. Performance
- [ ] Lazy loading
- [ ] Field virtualization
- [ ] Optimistic updates
- [ ] Caching
- [ ] Background saving

### 9. Security
- [ ] CSRF protection
- [ ] XSS prevention
- [ ] Input sanitization
- [ ] File upload security
- [ ] Permission checks

### 10. API Integration
- [ ] Webhooks
- [ ] External services
- [ ] Custom endpoints
- [ ] Authentication

## Implementation Priorities

### Phase 1 (Current)
1. Basic field types
2. Field groups
3. Entry CRUD
4. Basic validation
5. Media upload

### Phase 2
1. Rich text editor
2. Media library
3. Version control
4. Advanced validation
5. Auto-save

### Phase 3
1. Repeater fields
2. Reference fields
3. Conditional fields
4. Bulk operations
5. Import/Export

### Phase 4
1. Advanced media features
2. Publishing workflow
3. Preview system
4. Performance optimizations
5. API integrations

## Technical Considerations

### Frontend
- React/Vue.js for complex UI components
- State management (Redux/Vuex)
- Form handling library
- Rich text editor integration
- Media processing
- Real-time updates

### Backend
- File system operations
- Media processing
- Caching strategy
- API design
- Security measures

### Database
- File structure
- Indexing strategy
- Search implementation
- Version control storage
- Media metadata

## Future Enhancements
- AI-powered content suggestions
- Content analytics
- A/B testing
- Multi-language support
- Custom field types
- Plugin system
- Theme system
- API documentation
- Developer tools 