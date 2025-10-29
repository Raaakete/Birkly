# HaruCMS Project Plan

## Overview
HaruCMS is a headless content management system designed to be simple, lightweight, and focused on content management without frontend templating responsibilities.

## Core Philosophy
- **Headless Architecture**: Content is delivered via API, separate from presentation
- **Separation of Concerns**: Clear boundaries between content management and frontend
- **Simplicity**: Easy to understand, deploy, and maintain
- **Extensibility**: Plugin system for custom functionality
- **Self-Contained Components**: Fieldtypes and plugins work independently

## Completed Features

### ✅ Core CMS System
- [x] **Database Schema**: Collections, entries, media, settings tables
- [x] **API System**: RESTful endpoints for content management
- [x] **Content Management**: CRUD operations for collections and entries
- [x] **Media Management**: File upload and organization
- [x] **Settings System**: Configuration management
- [x] **Security**: CSRF protection, input validation

### ✅ Admin Panel System
- [x] **Templating Engine**: PHP-based template system with inheritance
- [x] **Admin Templates**: HTML-only templates with templating language
- [x] **JavaScript Engine**: Dynamic page loading and functionality
- [x] **Navigation System**: Hash-based routing
- [x] **Dashboard**: Overview with statistics and quick actions
- [x] **Collection Editor**: Create and edit content structures
- [x] **Entry Editor**: Create and edit content entries
- [x] **Entries List**: View and manage entries
- [x] **Media Library**: File management interface
- [x] **Settings Interface**: System configuration

### ✅ Fieldtype System
- [x] **Self-Contained Fieldtypes**: Each fieldtype is independent
- [x] **Dynamic Discovery**: Automatic fieldtype detection
- [x] **Configuration UI**: Fieldtype-specific settings
- [x] **Built-in Fieldtypes**:
  - [x] **Text**: Single line text input
  - [x] **Select**: Dropdown with configurable options
  - [x] **Checkbox**: Boolean input
  - [x] **Media**: File upload and selection
- [x] **Fieldtype API**: Standardized interface for all fieldtypes
- [x] **Complete Modularity**: Fieldtypes are entirely self-contained with no admin system dependencies

### ✅ Plugin System
- [x] **Plugin Architecture**: Extensible system for custom functionality
- [x] **Hook System**: Event-driven plugin integration
- [x] **Plugin Management**: Install, enable, disable, uninstall
- [x] **Plugin API**: Standardized interface for plugins

### ✅ Admin Templating System
- [x] **Template Inheritance**: Base templates with block system
- [x] **Variable Interpolation**: `{{ variable }}` syntax
- [x] **Conditional Logic**: `{{ if condition }}...{{ endif }}`
- [x] **Loops**: `{{ for item in items }}...{{ endfor }}`
- [x] **Function Calls**: `{{ formatDate date 'relative' }}`
- [x] **Template Includes**: `{{ include "component.html" }}`
- [x] **Pure HTML Templates**: No inline JavaScript in templates
- [x] **Dynamic Loading**: JavaScript loaded separately from templates

## Architecture Decisions

### Admin Panel Separation
**Problem**: Original SPA approach had issues with persistent JavaScript, memory leaks, and complex script management.

**Solution**: Implemented templating system that:
- **Eliminates script conflicts**: Fresh template rendering on each page load
- **Simplifies state management**: Stateless templates, no persistent scripts
- **Improves maintainability**: Isolated page rendering, easier debugging
- **Aligns with CMS philosophy**: Mirrors headless nature of main CMS
- **Enables customization**: Users can modify HTML/CSS without touching PHP/JavaScript

### Fieldtype Self-Containment
**Problem**: Fieldtypes were hardcoded in admin UI, requiring manual updates.

**Solution**: Implemented dynamic fieldtype system that:
- **Auto-discovers fieldtypes**: Scans `fieldtypes/` directory
- **Dynamic loading**: Loads JavaScript only when needed
- **Self-contained**: Each fieldtype works independently
- **Configuration UI**: Fieldtype-specific settings rendered dynamically
- **No admin UI changes**: Adding fieldtypes requires no code changes

### Fieldtype Modularity Refactor (2025-08-04)
**Problem**: Admin system contained fieldtype-specific logic, violating separation of concerns.

**Solution**: Complete refactor that:
- **Removed all fieldtype-specific code** from admin-engine.js and other admin files
- **Eliminated hardcoded fieldtype rendering** in admin system
- **Implemented pure API-based fieldtype loading** via admin_fieldtypes.php
- **Made fieldtypes completely self-initializing** without admin system intervention
- **Achieved true modularity** where fieldtypes work independently when added to fieldtype folder

**Benefits**:
- **Maintainability**: Fieldtype changes don't require admin system modifications
- **Extensibility**: New fieldtypes can be added without touching admin code
- **Reliability**: Fieldtype bugs are isolated to the fieldtype module
- **Performance**: Only necessary fieldtype code is loaded when needed
- **Clean Architecture**: Admin system contains only HTML templates and templating language

### Template Engine Design
**Problem**: Needed simple, powerful templating for admin panel.

**Solution**: Created templating system with:
- **Familiar syntax**: Similar to popular templating engines
- **Limited complexity**: Prevents over-engineering
- **Function support**: Built-in formatting functions
- **Template inheritance**: Consistent layouts
- **Component system**: Reusable template parts

## Technical Implementation

### Admin Engine
- **Hash-based routing**: Simple client-side navigation
- **Template rendering**: Server-side template processing
- **Dynamic loading**: JavaScript loaded per page
- **Event delegation**: Efficient event handling
- **Error handling**: Graceful error display

### Fieldtype System
- **Discovery API**: `api/fieldtypes.php` for fieldtype metadata
- **Dynamic loading**: Scripts loaded on-demand
- **Standard interface**: `render()`, `getValue()`, `setValue()`, `renderConfig()`
- **Configuration UI**: Fieldtype-specific settings
- **Self-contained**: No dependencies on admin UI

### Plugin System
- **Hook system**: Event-driven integration points
- **Dynamic loading**: Plugins loaded at runtime
- **Management interface**: Install, enable, disable
- **Standard API**: Consistent plugin interface
- **Isolation**: Plugins don't interfere with each other

## File Structure
```
HaruCMS/
├── api/                    # API endpoints
│   ├── admin_templates.php # Admin template serving
│   ├── content.php        # Content management
│   ├── fieldtypes.php     # Fieldtype discovery
│   ├── media.php          # Media management
│   ├── plugins.php        # Plugin management
│   └── settings.php       # Settings management
├── admin/                 # Admin panel
│   ├── templates/         # HTML templates
│   │   ├── base.html     # Base template
│   │   ├── dashboard.html # Dashboard
│   │   ├── content.html  # Content management
│   │   ├── collection-editor.html
│   │   ├── entry-editor.html
│   │   └── entries.html  # Entries list
│   ├── js/               # JavaScript
│   │   ├── admin-engine.js
│   │   ├── collection-editor.js
│   │   ├── entry-editor.js
│   │   └── plugin-system.js
│   └── css/              # Stylesheets
├── fieldtypes/           # Fieldtype definitions
│   ├── Text/
│   │   └── fieldtype.js
│   ├── Select/
│   │   └── fieldtype.js
│   ├── Checkbox/
│   │   └── fieldtype.js
│   └── Media/
│       └── fieldtype.js
├── plugins/              # Plugin directory
├── media/               # Uploaded files
└── database/            # SQLite database
```

## Future Enhancements

### Potential Improvements
- [ ] **Advanced Fieldtypes**: WYSIWYG editor, date picker, color picker
- [ ] **User Management**: Multi-user support with roles
- [ ] **API Documentation**: Auto-generated API docs
- [ ] **Webhooks**: Event-driven integrations
- [ ] **Caching**: Performance optimization
- [ ] **Backup System**: Automated backups
- [ ] **Import/Export**: Data migration tools
- [ ] **Themes**: Admin panel customization
- [ ] **Multi-language**: Internationalization
- [ ] **Advanced Search**: Full-text search capabilities

### Plugin Ideas
- [ ] **SEO Plugin**: Meta tag management
- [ ] **Analytics Plugin**: Usage tracking
- [ ] **Backup Plugin**: Automated backups
- [ ] **Import Plugin**: Data import tools
- [ ] **API Plugin**: Custom API endpoints
- [ ] **Webhook Plugin**: Event notifications
- [ ] **Cache Plugin**: Performance optimization
- [ ] **Search Plugin**: Advanced search functionality

## Development Guidelines

### Code Standards
- **PHP**: PSR-4 autoloading, PSR-12 coding style
- **JavaScript**: ES6+ features, modular structure
- **HTML**: Semantic markup, accessibility
- **CSS**: BEM methodology, responsive design
- **Documentation**: Inline comments, README files

### Security Practices
- **Input Validation**: All user input validated
- **CSRF Protection**: Token-based protection
- **SQL Injection**: Prepared statements
- **XSS Prevention**: Output escaping
- **File Upload**: Secure file handling
- **Access Control**: Proper permissions

### Performance Considerations
- **Lazy Loading**: JavaScript loaded on-demand
- **Template Caching**: Compiled templates
- **Database Optimization**: Proper indexing
- **Asset Optimization**: Minified CSS/JS
- **CDN Integration**: External asset delivery

## Deployment

### Requirements
- **PHP**: 8.0 or higher
- **SQLite**: Built-in database
- **Web Server**: Apache/Nginx
- **File Permissions**: Writable media directory

### Installation
1. **Download**: Clone or download HaruCMS
2. **Configure**: Set up web server
3. **Permissions**: Make media directory writable
4. **Database**: SQLite database auto-created
5. **Access**: Navigate to admin panel

### Configuration
- **Database**: SQLite file location
- **Media**: Upload directory path
- **Plugins**: Plugin directory
- **Templates**: Template directory
- **Settings**: System configuration

## Conclusion

HaruCMS provides a complete, self-contained content management system with:
- **Simple architecture**: Easy to understand and maintain
- **Extensible design**: Plugin and fieldtype systems
- **Modern admin**: Clean, responsive interface
- **Headless approach**: Content via API, separate frontend
- **Self-contained components**: Independent fieldtypes and plugins

The system successfully balances simplicity with functionality, providing a solid foundation for content management while remaining extensible for custom needs. 