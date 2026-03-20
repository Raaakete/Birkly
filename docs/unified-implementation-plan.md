# Unified Implementation Plan
## Sub-Entries, Plugin System, Database Architecture & Automation

**Version:** 1.0  
**Created:** 2024-01-15  
**Status:** Planning Phase

---

## Executive Summary

This document provides a unified, phased implementation plan for four major system enhancements:
1. **Sub-Entries System** - Hierarchical content relationships
2. **Plugin System Enhancement** - Capabilities, permissions, UI injection
3. **Database Architecture** - Optional DB backend with pluggable storage
4. **Automation System** - Triggers, conditions, actions

The plan is designed to:
- **Preserve existing functionality** - No breaking changes
- **Enable incremental delivery** - Each phase delivers value
- **Minimize risk** - Careful testing and rollback at each step
- **Leverage overlaps** - Combined tasks where it makes sense
- **Maintain system stability** - Core remains functional throughout

---

## Table of Contents

1. [Dependencies & Prerequisites](#dependencies--prerequisites)
2. [Phase Overview](#phase-overview)
3. [Detailed Phase Breakdown](#detailed-phase-breakdown)
4. [Risk Mitigation](#risk-mitigation)
5. [Testing Strategy](#testing-strategy)
6. [Rollback Procedures](#rollback-procedures)
7. [Success Criteria](#success-criteria)

---

## Dependencies & Prerequisites

### Critical Dependencies

1. **Storage Abstraction Layer** (Foundation)
   - Must be completed before Database Architecture
   - Required for Sub-Entries System (query flexibility)
   - Enables Automation System (data access)

2. **Plugin Capability System** (Foundation)
   - Required before Plugin UI Injection
   - Required before Automation Plugin Blocks
   - Required before Sub-Entries Permissions

3. **Hook System Enhancement** (Foundation)
   - Required for Automation Triggers
   - Required for Plugin-to-Plugin Communication
   - Required for Sub-Entries Lifecycle Hooks

### Implementation Order

```
Phase 1: Foundation (Storage + Plugin Capabilities + Hooks)
    ↓
Phase 2: Sub-Entries Core (depends on Storage + Hooks)
    ↓
Phase 3: Plugin System Enhancement (depends on Capabilities)
    ↓
Phase 4: Database Backend (depends on Storage Abstraction)
    ↓
Phase 5: Automation System (depends on Hooks + Plugin System)
    ↓
Phase 6: Integration & Polish
```

---

## Phase Overview

| Phase | Focus | Duration | Dependencies | Risk Level |
|-------|-------|----------|--------------|------------|
| **1** | Foundation Layer | 2-3 weeks | None | Low |
| **2** | Sub-Entries MVP | 2-3 weeks | Phase 1 | Medium |
| **3** | Plugin Enhancement | 2-3 weeks | Phase 1 | Medium |
| **4** | Database Backend | 2-3 weeks | Phase 1 | Medium |
| **5** | Automation MVP | 2-3 weeks | Phase 1, 3 | Medium |
| **6** | Integration & Polish | 1-2 weeks | All phases | Low |

**Total Estimated Duration:** 11-17 weeks

---

## Detailed Phase Breakdown

### Phase 1: Foundation Layer (2-3 weeks)

**Goal:** Create the foundational infrastructure that all other systems depend on.

#### 1.1 Storage Abstraction Interface (Week 1)

**Tasks:**
- [ ] Create `StorageInterface` abstract class/interface
- [ ] Refactor existing `Storage` class to implement interface (`FlatFileStorage`)
- [ ] Update all content access code to use interface (not direct class)
- [ ] Add methods: `read()`, `write()`, `delete()`, `list()`, `query()`, `exists()`
- [ ] Add transaction support (optional, for future DB drivers)
- [ ] Create storage factory/registry pattern
- [ ] Add storage driver configuration system
- [ ] Write comprehensive tests for storage interface

**Files to Modify:**
- `api/storage.php` → `core/storage/StorageInterface.php`
- `api/storage.php` → `core/storage/FlatFileStorage.php`
- `api/content.php` - Update to use interface
- `api/public.php` - Update to use interface
- `api/admin_entries.php` - Update to use interface
- All other files using `new Storage()`

**Deliverables:**
- Storage abstraction interface
- Refactored flat-file storage
- All existing code using interface
- 100% backward compatibility
- Test suite for storage layer

**Testing:**
- All existing functionality works identically
- No performance regression
- Storage factory can switch drivers (even if only flat-file exists)

---

#### 1.2 Plugin Capability System (Week 1-2)

**Tasks:**
- [ ] Extend `plugin.json` schema for capabilities declaration
- [ ] Create permission storage system (`settings/plugin_permissions.json`)
- [ ] Create denylist storage (`settings/plugin_denylist.json`)
- [ ] Implement `has_plugin_capability()` function
- [ ] Implement `require_plugin_capability()` function
- [ ] Implement `is_resource_protected()` function (denylist check)
- [ ] Add permission checking to existing plugin hooks
- [ ] Create admin UI for permission management (Settings → Plugins → Permissions)
- [ ] Create admin UI for denylist management (Settings → Plugins → Global Security)
- [ ] Add permission audit logging
- [ ] Update plugin loader to read capabilities from `plugin.json`

**Files to Create:**
- `core/plugins/capabilities.php` - Capability checking functions
- `core/plugins/permissions.php` - Permission storage and management
- `admin/settings/plugin-permissions.html` - Permission management UI
- `admin/settings/plugin-denylist.html` - Denylist management UI
- `api/plugins_permissions.php` - Permission API endpoints

**Files to Modify:**
- `core/plugins.php` - Add capability system integration
- `plugins/sample-plugin/plugin.json` - Add example capabilities
- `plugins/community_users/plugin.json` - Add example capabilities

**Deliverables:**
- Capability declaration system
- Permission storage and checking
- Denylist system (checked first, before capabilities)
- Admin UI for permissions
- Audit logging
- Backward compatible (existing plugins work without capabilities)

**Testing:**
- Plugins without capabilities still work
- Permission checks work correctly
- Denylist blocks access even with permissions
- Admin UI saves and loads permissions correctly

---

#### 1.3 Hook System Enhancement (Week 2)

**Tasks:**
- [ ] Add priority system to `register_hook()` (default: 10)
- [ ] Add capability checking to hook registration
- [ ] Implement `add_filter()` and `apply_filters()` functions
- [ ] Enhance `do_action()` to support priority ordering
- [ ] Add context passing to hooks (`do_action_with_context()`)
- [ ] Add hook debugging tools (list registered hooks, execution logs)
- [ ] Document all existing hooks
- [ ] Add hook execution isolation (errors don't break other hooks)
- [ ] Add hook performance monitoring (optional, for debugging)

**Files to Modify:**
- `core/plugins.php` - Enhance hook system
- `core/hooks.php` (new) - Filter system and enhanced hooks

**Files to Create:**
- `admin/tools/hooks-debug.html` - Hook debugging UI (optional)

**Deliverables:**
- Priority-based hook system
- Filter system (modify data)
- Context-aware hooks
- Hook debugging tools
- Comprehensive hook documentation
- Error isolation (one hook failure doesn't break others)

**Testing:**
- Existing hooks still work
- Priority ordering works correctly
- Filters modify data correctly
- Hook errors are isolated
- Performance impact is minimal

---

### Phase 2: Sub-Entries System MVP (2-3 weeks)

**Goal:** Basic sub-entry functionality with parent-child relationships.

#### 2.1 Sub-Collection Configuration (Week 1)

**Tasks:**
- [ ] Extend `collection.json` schema to support `sub_collections` array
- [ ] Add sub-collection configuration UI in collection editor
- [ ] Implement sub-collection validation (slug uniqueness, parent reference)
- [ ] Add parent field auto-generation (`{parent_collection_slug}_id`)
- [ ] Create sub-collection storage structure (separate directories)
- [ ] Add sub-collection schema management
- [ ] Implement sub-collection entry method settings (independent from parent)

**Files to Create:**
- `admin/collection-editor-sub-entries.html` - Sub-collection configuration UI
- `admin/collection-editor-sub-entries.js` - Sub-collection management JS
- `api/sub_collections.php` - Sub-collection API endpoints

**Files to Modify:**
- `api/admin_collections.php` - Add sub-collection handling
- `admin/collection-editor.html` - Add sub-entries tab/section

**Deliverables:**
- Sub-collection configuration in collection editor
- Parent field auto-generation
- Independent entry method per sub-collection
- Sub-collection storage structure

**Testing:**
- Can create sub-collections
- Parent fields are auto-generated correctly
- Entry methods are independent
- Validation prevents invalid configurations

---

#### 2.2 Sub-Entry Storage & Basic Queries (Week 1-2)

**Tasks:**
- [ ] Create sub-entry storage structure (mirrors main entries)
- [ ] Implement `getSubEntries()` function
- [ ] Add parent reference validation on save
- [ ] Implement basic parent-child linking
- [ ] Add sub-entry ID generation
- [ ] Create sub-entry CRUD operations
- [ ] Add sub-entry listing API endpoint
- [ ] Implement basic filtering (by parent, by status)

**Files to Create:**
- `core/sub_entries.php` - Sub-entry core functions
- `api/sub_entries.php` - Sub-entry API endpoints

**Files to Modify:**
- `api/content.php` - Add sub-entry actions
- `api/admin_entries.php` - Add sub-entry admin operations

**Deliverables:**
- Sub-entry storage system
- Basic query API (`getSubEntries()`)
- Parent-child linking
- CRUD operations for sub-entries

**Testing:**
- Can create sub-entries linked to parents
- Can query sub-entries by parent
- Parent references are validated
- Sub-entries are stored correctly

---

#### 2.3 Entry Editor Integration (Week 2)

**Tasks:**
- [ ] Add sub-entries tabs to entry editor
- [ ] Implement sub-entry listing in entry editor
- [ ] Add "Add Sub-Entry" functionality (admin)
- [ ] Implement sub-entry inline editing (admin)
- [ ] Add sub-entry deletion (admin)
- [ ] Create sub-entry management UI components
- [ ] Add sub-entry status filtering
- [ ] Implement bulk actions (approve, delete, publish)

**Files to Create:**
- `admin/entry-editor-sub-entries.html` - Sub-entry management UI
- `admin/entry-editor-sub-entries.js` - Sub-entry management JS

**Files to Modify:**
- `admin/entry-editor.html` - Add sub-entries tabs
- `admin/entry-editor.js` - Integrate sub-entry management

**Deliverables:**
- Sub-entries visible in entry editor
- Admin can manage sub-entries from entry editor
- Sub-entry listing and filtering
- Bulk operations

**Testing:**
- Sub-entries appear in entry editor
- Can add/edit/delete sub-entries
- Filtering and bulk actions work
- UI is responsive and intuitive

---

#### 2.4 Public API for Sub-Entries (Week 2-3)

**Tasks:**
- [ ] Create public API endpoint for sub-entry submission
- [ ] Implement auto-population of parent reference
- [ ] Add validation and sanitization
- [ ] Implement moderation workflow (if `auto_publish: false`)
- [ ] Add CSRF protection
- [ ] Implement rate limiting per sub-collection
- [ ] Add email notifications (if configured)
- [ ] Create public submission form templates

**Files to Create:**
- `api/public_sub_entry.php` - Public sub-entry submission API
- `templates/sub-entry-form.html` - Public submission form template

**Files to Modify:**
- `api/public.php` - Add sub-entry endpoints (if needed)

**Deliverables:**
- Public sub-entry submission API
- Auto-populated parent references
- Moderation workflow
- Rate limiting
- Email notifications

**Testing:**
- Public can submit sub-entries
- Parent references are auto-populated
- Moderation workflow works
- Rate limiting prevents abuse
- Email notifications are sent

---

### Phase 3: Plugin System Enhancement (2-3 weeks)

**Goal:** Complete plugin system with UI injection, plugin-to-plugin communication, and enhanced security.

#### 3.1 UI Injection System (Week 1)

**Tasks:**
- [ ] Define injection point hooks (collection editor, entry editor, settings, dashboard, navigation)
- [ ] Implement `register_ui_injection()` function
- [ ] Implement `render_ui_injections()` function
- [ ] Create component rendering system
- [ ] Add injection points to core templates
- [ ] Build JavaScript component system (`HaruPluginUI`)
- [ ] Add capability checking for UI injections
- [ ] Implement position/priority system for injections

**Files to Create:**
- `core/plugins/ui_injection.php` - UI injection system
- `admin/js/plugin-ui.js` - JavaScript component system

**Files to Modify:**
- `admin/collection-editor.html` - Add injection points
- `admin/entry-editor.html` - Add injection points
- `admin/settings.html` - Add injection points
- `admin/dashboard.html` - Add injection points
- `admin/navigation.html` - Add injection points

**Deliverables:**
- UI injection API
- Component rendering system
- Injection points in all major admin pages
- JavaScript component system
- Capability-gated injections

**Testing:**
- Plugins can inject UI components
- Components render correctly
- Capability checks work
- Position/priority ordering works
- JavaScript components initialize correctly

---

#### 3.2 Plugin-to-Plugin Communication (Week 1-2)

**Tasks:**
- [ ] Implement service registry (`$haru_plugin_services`)
- [ ] Create `register_plugin_service()` function
- [ ] Create `get_plugin_service()` function
- [ ] Implement service discovery API
- [ ] Add service version checking
- [ ] Implement event broadcasting system
- [ ] Add shared data storage (with permissions)
- [ ] Create dependency checking system
- [ ] Add service contract/interface validation

**Files to Create:**
- `core/plugins/services.php` - Service registry and discovery
- `core/plugins/events.php` - Event broadcasting system
- `core/plugins/shared_data.php` - Shared data storage

**Files to Modify:**
- `core/plugins.php` - Integrate service system

**Deliverables:**
- Service registry and discovery
- Event broadcasting
- Shared data storage
- Dependency checking
- Service version compatibility

**Testing:**
- Plugins can register services
- Plugins can discover and use services
- Service version checking works
- Event broadcasting works
- Shared data access respects permissions

---

#### 3.3 Security Hardening (Week 2)

**Tasks:**
- [ ] Implement plugin sandboxing (isolated execution)
- [ ] Add input validation system for plugin APIs
- [ ] Create output sanitization functions
- [ ] Enhance CSRF protection for plugin endpoints
- [ ] Add security audit logging
- [ ] Implement plugin health checks
- [ ] Add plugin error isolation
- [ ] Create plugin testing harness (sandbox runner)

**Files to Create:**
- `core/plugins/security.php` - Security functions
- `core/plugins/validation.php` - Input validation
- `core/plugins/sandbox.php` - Sandbox execution (optional)

**Files to Modify:**
- `api/plugins.php` - Add security checks
- `core/plugins.php` - Add error isolation

**Deliverables:**
- Plugin sandboxing
- Input validation
- Output sanitization
- Enhanced CSRF protection
- Security audit logging
- Error isolation

**Testing:**
- Plugins cannot access unauthorized resources
- Input validation prevents malicious data
- Output is sanitized
- CSRF protection works
- Errors are isolated

---

#### 3.4 Plugin Management UX (Week 2-3)

**Tasks:**
- [ ] Enhance plugin list UI (capabilities summary, denylist indicators)
- [ ] Create plugin detail page
- [ ] Add plugin health status indicators
- [ ] Implement plugin update system (if needed)
- [ ] Add plugin dependency visualization
- [ ] Create plugin conflict detection
- [ ] Add plugin performance metrics

**Files to Modify:**
- `admin/settings/plugins.html` - Enhanced plugin list
- `admin/plugins/plugin-detail.html` - Plugin detail page

**Deliverables:**
- Enhanced plugin management UI
- Plugin health monitoring
- Dependency visualization
- Conflict detection

**Testing:**
- Plugin UI is intuitive
- Health status is accurate
- Dependencies are shown correctly
- Conflicts are detected

---

### Phase 4: Database Backend (2-3 weeks)

**Goal:** Optional database backend with pluggable storage drivers.

#### 4.1 SQL Storage Driver (Week 1-2)

**Tasks:**
- [ ] Create `SqlStorage` class implementing `StorageInterface`
- [ ] Implement PostgreSQL driver (with JSONB support)
- [ ] Implement MySQL/MariaDB driver
- [ ] Implement SQLite driver
- [ ] Create database schema migration system
- [ ] Implement data model mapping (collections → tables, entries → rows)
- [ ] Add flexible fields storage (JSON/JSONB column)
- [ ] Implement query system with filters and pagination
- [ ] Add transaction support
- [ ] Create connection pooling (where applicable)

**Files to Create:**
- `core/storage/SqlStorage.php` - Base SQL storage class
- `core/storage/drivers/PostgresStorage.php` - PostgreSQL driver
- `core/storage/drivers/MySqlStorage.php` - MySQL/MariaDB driver
- `core/storage/drivers/SqliteStorage.php` - SQLite driver
- `core/storage/migrations.php` - Migration system
- `database/schema.sql` - Base schema for all SQL databases

**Deliverables:**
- SQL storage drivers (Postgres, MySQL, SQLite)
- Schema migration system
- Data model mapping
- Query system with filters
- Transaction support

**Testing:**
- All drivers work correctly
- Data is stored and retrieved correctly
- Queries work with filters
- Transactions work
- Performance is acceptable

---

#### 4.2 Migration & Rollback System (Week 2)

**Tasks:**
- [ ] Create flat-file to database migration tool
- [ ] Create database to flat-file export tool
- [ ] Implement dry-run validation
- [ ] Add migration snapshot system
- [ ] Create rollback functionality
- [ ] Add integrity checks (slug uniqueness, required fields)
- [ ] Implement batch import for large datasets
- [ ] Add migration progress tracking

**Files to Create:**
- `core/storage/migration.php` - Migration tools
- `api/storage_migration.php` - Migration API endpoints
- `admin/settings/storage-migration.html` - Migration UI

**Deliverables:**
- Migration tools (flat-file ↔ DB)
- Dry-run validation
- Snapshot system
- Rollback functionality
- Progress tracking

**Testing:**
- Migration works correctly
- Rollback restores previous state
- Dry-run catches errors
- Large datasets migrate successfully
- Data integrity is maintained

---

#### 4.3 Settings UI & Health Checks (Week 2-3)

**Tasks:**
- [ ] Create storage settings page (Settings → Storage)
- [ ] Add driver selection UI
- [ ] Create connection form (host, port, DB, user, pass, TLS)
- [ ] Implement health check functionality
- [ ] Add migration/rollback buttons
- [ ] Create status display (current backend, last health check, last migration)
- [ ] Add connection testing
- [ ] Implement configuration validation

**Files to Create:**
- `admin/settings/storage.html` - Storage settings UI
- `admin/settings/storage.js` - Storage settings JS
- `api/storage_health.php` - Health check API

**Deliverables:**
- Storage settings UI
- Health check system
- Connection testing
- Migration/rollback UI

**Testing:**
- Settings UI works correctly
- Health checks are accurate
- Connection testing works
- Migration UI is intuitive

---

### Phase 5: Automation System MVP (2-3 weeks)

**Goal:** Basic automation system with triggers, conditions, and actions.

#### 5.1 Automation Core & Storage (Week 1)

**Tasks:**
- [ ] Create automation data model (trigger, conditions, actions, metadata)
- [ ] Implement automation storage system
- [ ] Create automation registry
- [ ] Add automation CRUD operations
- [ ] Implement automation execution engine
- [ ] Create automation queue system (async execution)
- [ ] Add execution logging
- [ ] Implement error handling and isolation

**Files to Create:**
- `core/automation.php` - Automation core functions
- `core/automation/registry.php` - Block registry
- `core/automation/executor.php` - Execution engine
- `core/automation/queue.php` - Queue system
- `api/automation.php` - Automation API endpoints

**Deliverables:**
- Automation data model
- Storage system
- Execution engine
- Queue system
- Execution logging

**Testing:**
- Automations are stored correctly
- Execution engine works
- Queue processes jobs
- Logging captures execution details
- Errors are isolated

---

#### 5.2 Core Blocks (Triggers, Conditions, Actions) (Week 1-2)

**Tasks:**
- [ ] Implement core triggers: `entry_created`, `entry_updated`, `entry_deleted`, `status_changed`
- [ ] Implement core conditions: field match, status check, collection filter
- [ ] Implement core actions: email, webhook, log, set status/field
- [ ] Create block registry system
- [ ] Add block schema definitions
- [ ] Implement block validation
- [ ] Create block execution system
- [ ] Add template system for actions (`{{entry.title}}`, etc.)

**Files to Create:**
- `core/automation/blocks/triggers.php` - Core triggers
- `core/automation/blocks/conditions.php` - Core conditions
- `core/automation/blocks/actions.php` - Core actions
- `core/automation/templates.php` - Template system

**Deliverables:**
- Core trigger blocks
- Core condition blocks
- Core action blocks
- Block registry
- Template system

**Testing:**
- Triggers fire correctly
- Conditions evaluate correctly
- Actions execute correctly
- Templates render correctly
- Block validation works

---

#### 5.3 Automation Builder UI (Week 2)

**Tasks:**
- [ ] Create automation list page (view/search/edit all automations)
- [ ] Build automation editor (drag-and-drop or form-based)
- [ ] Create block palette (triggers, conditions, actions)
- [ ] Implement block configuration panel
- [ ] Add validation UI (required fields, URL/email validation)
- [ ] Create test mode (run with sample data)
- [ ] Add enable/disable toggle
- [ ] Create execution log viewer

**Files to Create:**
- `admin/automation/list.html` - Automation list page
- `admin/automation/editor.html` - Automation editor
- `admin/automation/editor.js` - Automation editor JS
- `admin/automation/logs.html` - Execution log viewer

**Deliverables:**
- Automation list page
- Automation editor
- Block palette
- Test mode
- Log viewer

**Testing:**
- UI is intuitive
- Editor works correctly
- Validation prevents errors
- Test mode works
- Logs are displayed correctly

---

#### 5.4 Integration with Existing Systems (Week 2-3)

**Tasks:**
- [ ] Integrate automation triggers with entry lifecycle hooks
- [ ] Add automation triggers to sub-entry system
- [ ] Integrate with plugin system (plugin blocks)
- [ ] Add automation context to hook system
- [ ] Create automation API for plugins
- [ ] Add automation permissions (who can create/edit automations)

**Files to Modify:**
- `api/admin_entries.php` - Add automation trigger calls
- `api/sub_entries.php` - Add automation trigger calls
- `core/plugins.php` - Add automation block registration
- `core/automation.php` - Add plugin integration

**Deliverables:**
- Automation triggers fire on entry events
- Sub-entry triggers work
- Plugins can register blocks
- Automation permissions work

**Testing:**
- Triggers fire on entry events
- Sub-entry triggers work
- Plugin blocks are registered
- Permissions are enforced

---

### Phase 6: Integration & Polish (1-2 weeks)

**Goal:** Integrate all systems, add advanced features, and polish the experience.

#### 6.1 Sub-Entries Advanced Features (Week 1)

**Tasks:**
- [ ] Implement nested sub-entries (unlimited depth)
- [ ] Add ancestry tracking
- [ ] Implement sibling queries
- [ ] Add advanced filtering and sorting
- [ ] Implement aggregations (count, average, sum, etc.)
- [ ] Add multiple display modes (list, tree, threaded)
- [ ] Create template helpers for rendering

**Files to Modify:**
- `core/sub_entries.php` - Add advanced features
- `api/sub_entries.php` - Add advanced query endpoints

**Deliverables:**
- Nested sub-entries
- Advanced queries
- Aggregations
- Display modes
- Template helpers

---

#### 6.2 Automation Advanced Features (Week 1)

**Tasks:**
- [ ] Add scheduled triggers (cron-like)
- [ ] Implement retries and backoff
- [ ] Add loop prevention
- [ ] Implement rate limiting
- [ ] Add action priorities
- [ ] Create webhook inbound triggers (optional)
- [ ] Add plugin event triggers

**Files to Modify:**
- `core/automation/blocks/triggers.php` - Add scheduled triggers
- `core/automation/executor.php` - Add retries, rate limiting

**Deliverables:**
- Scheduled triggers
- Retry system
- Loop prevention
- Rate limiting
- Plugin event triggers

---

#### 6.3 Cross-System Integration (Week 1-2)

**Tasks:**
- [ ] Integrate sub-entries with automation (sub-entry triggers)
- [ ] Integrate plugin system with sub-entries (plugin sub-collection types)
- [ ] Integrate database backend with sub-entries (sub-entry queries in DB)
- [ ] Add automation blocks for sub-entry operations
- [ ] Create unified permission system (sub-entries + plugins + automation)
- [ ] Add cross-system audit logging

**Files to Modify:**
- Multiple files across all systems

**Deliverables:**
- All systems work together
- Unified permissions
- Cross-system audit logs

---

#### 6.4 Documentation & Examples (Week 2)

**Tasks:**
- [ ] Write comprehensive documentation for all systems
- [ ] Create example implementations
- [ ] Write migration guides
- [ ] Create video tutorials (optional)
- [ ] Write API documentation
- [ ] Create developer guides

**Deliverables:**
- Complete documentation
- Example implementations
- Migration guides
- API documentation

---

## Risk Mitigation

### High-Risk Areas

1. **Storage Abstraction Refactoring**
   - **Risk:** Breaking existing functionality
   - **Mitigation:** 
     - Comprehensive test suite before refactoring
     - Incremental refactoring (one file at a time)
     - Keep old code until new code is proven
     - Extensive testing after each change

2. **Database Migration**
   - **Risk:** Data loss during migration
   - **Mitigation:**
     - Always create snapshots before migration
     - Dry-run validation
     - Rollback functionality
     - Incremental migration (batch processing)
     - Data integrity checks

3. **Plugin System Changes**
   - **Risk:** Breaking existing plugins
   - **Mitigation:**
     - Backward compatibility layer
     - Gradual migration path
     - Plugin validation before activation
     - Clear deprecation notices

4. **Automation System Performance**
   - **Risk:** Slow execution, blocking requests
   - **Mitigation:**
     - Async queue system
     - Rate limiting
     - Timeout mechanisms
     - Performance monitoring

### Testing Strategy

1. **Unit Tests**
   - Test each function/class in isolation
   - Mock dependencies
   - Test edge cases

2. **Integration Tests**
   - Test system interactions
   - Test data flow
   - Test error handling

3. **End-to-End Tests**
   - Test complete user workflows
   - Test real-world scenarios
   - Test performance under load

4. **Backward Compatibility Tests**
   - Ensure existing functionality works
   - Test with existing plugins
   - Test with existing content

---

## Rollback Procedures

### For Each Phase

1. **Before Starting:**
   - Create full backup of system
   - Document current state
   - Create rollback checklist

2. **During Implementation:**
   - Keep old code commented/available
   - Use feature flags where possible
   - Test rollback procedure

3. **If Rollback Needed:**
   - Restore from backup
   - Revert code changes
   - Verify system functionality
   - Document issues

### Rollback Triggers

- Critical bugs discovered
- Performance degradation > 20%
- Data integrity issues
- Security vulnerabilities
- User complaints

---

## Success Criteria

### Phase 1: Foundation
- ✅ Storage abstraction works
- ✅ All existing code uses interface
- ✅ Plugin capabilities system works
- ✅ Hook system enhanced
- ✅ No breaking changes

### Phase 2: Sub-Entries
- ✅ Can create sub-collections
- ✅ Can create sub-entries
- ✅ Sub-entries appear in entry editor
- ✅ Public API works
- ✅ Basic queries work

### Phase 3: Plugin System
- ✅ UI injection works
- ✅ Plugin-to-plugin communication works
- ✅ Security hardening in place
- ✅ Plugin management UI complete

### Phase 4: Database Backend
- ✅ SQL drivers work
- ✅ Migration tools work
- ✅ Rollback works
- ✅ Settings UI complete

### Phase 5: Automation
- ✅ Core blocks work
- ✅ Automation editor works
- ✅ Triggers fire correctly
- ✅ Actions execute correctly

### Phase 6: Integration
- ✅ All systems work together
- ✅ Advanced features work
- ✅ Documentation complete
- ✅ Examples provided

---

## Technical Decisions (Based on Recommendations)

**See `technical-recommendations.md` for detailed reasoning.**

### 1. Storage Abstraction: Transactions
**Decision:** Include transaction methods in interface, but mark as optional.
- Flat-file storage: Implement as no-ops (do nothing, return true)
- SQL drivers: Full transaction support
- **Rationale:** Future-proof design without breaking changes

### 2. Sub-Entries: Independent Searchability
**Decision:** Design for global search, but implement in Phase 6 (not MVP).
- MVP: Sub-entries only queryable through parent
- Phase 6: Add global sub-entry search
- **Rationale:** Faster MVP delivery, system designed for future enhancement

### 3. Plugin System: Capability Requirements
**Decision:** Balanced approach - Denylist (hard barrier) + Capabilities (grants).
- Denylist checked FIRST (blocks all access to protected resources)
- Capabilities granted by admin (granular permissions)
- New plugins: Zero permissions by default (secure by default)
- **Rationale:** Secure by default, flexible for legitimate use cases

### 4. Database Backend: MongoDB
**Decision:** Defer MongoDB to Phase 4+ or community contribution.
- Phase 4 MVP: PostgreSQL, MySQL/MariaDB, SQLite only
- MongoDB: Add later if there's demand, or let community build it
- **Rationale:** Different query model adds complexity, most users use SQL

### 5. Automation: Webhook Inbound Triggers
**Decision:** Defer to Phase 5+ (not in MVP).
- MVP: Entry lifecycle triggers, scheduled triggers, status transitions
- Phase 5+: Webhook inbound triggers, user events, plugin events
- **Rationale:** Core functionality first, advanced features later

### 6. Automation Builder UI
**Decision:** Start with form-based, consider drag-and-drop later.
- MVP: Form-based builder (simpler, faster to implement)
- Phase 6: Consider drag-and-drop if users request it
- **Rationale:** Form-based is more accessible, faster to build, easier to maintain

### 7. Orphaned Sub-Entries
**Decision:** Handle gracefully with clear admin options.
- When parent is deleted: Mark sub-entries as orphaned (status: `orphaned`)
- Admin options: Delete orphaned sub-entries, or reassign to new parent
- Query behavior: Orphaned sub-entries excluded from normal queries (unless explicitly requested)
- **Rationale:** Prevents data loss, gives admin control, maintains data integrity

---

## Next Steps

1. ✅ **Technical decisions made** (see recommendations above)
2. **Review this plan** - All decisions documented
3. **Set up development environment** (testing, staging)
4. **Create detailed task breakdown** for Phase 1
5. **Begin Phase 1 implementation**

**Reference Documents:**
- `technical-recommendations.md` - Detailed reasoning for all technical decisions
- `unified-implementation-plan.md` - This document (complete implementation plan)

---

**Document Status:** Ready for Review  
**Next Review Date:** TBD  
**Owner:** Development Team
