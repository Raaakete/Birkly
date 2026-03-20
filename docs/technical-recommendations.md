# Technical Recommendations
## Decision Guidance for Unified Implementation Plan

**Purpose:** This document provides technical recommendations for the questions raised in the unified implementation plan, based on best practices, system architecture, and practical considerations.

---

## 1. Storage Abstraction: Transaction Support

### Question
Should we support transactions from the start, or add later?

### Recommendation: **Add Interface Support Now, Full Implementation Later**

**Reasoning:**
- **Why include in interface:** Transactions are critical for data integrity, especially when migrating to databases. If we don't design for them now, adding them later will require breaking changes.
- **Why defer full implementation:** Flat-file storage doesn't need transactions (file operations are atomic). SQL databases need them, but we can implement that when we build the SQL drivers.

**Implementation Approach:**
```php
interface StorageInterface {
    // Mark transactions as optional
    public function beginTransaction(); // Optional - can throw "not supported"
    public function commit();           // Optional
    public function rollback();        // Optional
    
    // For flat-file: these are no-ops (do nothing, return true)
    // For SQL: these wrap actual database transactions
}
```

**Benefits:**
- ✅ Future-proof design
- ✅ No breaking changes later
- ✅ Flat-file can ignore (no-op)
- ✅ SQL drivers can implement properly

**Action:** Include transaction methods in the interface, but mark them as optional. Flat-file storage implements them as no-ops. SQL drivers implement them fully.

---

## 2. Sub-Entries: Independent Searchability

### Question
Should sub-entries be searchable independently (not just through parent)?

### Recommendation: **Design for It, But Not in MVP**

**Reasoning:**
- **Why design for it:** Users will want to search all comments across all blog posts, or find all reviews for a product category. This is a common use case.
- **Why not in MVP:** Adds complexity to the query system. We can deliver value faster by focusing on parent-child relationships first.

**Implementation Approach:**
- **MVP (Phase 2):** Sub-entries are only queryable through parent (`getSubEntries($parentId)`)
- **Phase 6:** Add global sub-entry search (`searchSubEntries($collection, $filters)`)
- **Design consideration:** Store sub-entries in a way that makes global search easy later (e.g., include `parent_collection` and `parent_entry_id` in all sub-entries)

**Benefits:**
- ✅ Faster MVP delivery
- ✅ System designed for future enhancement
- ✅ No breaking changes when adding later

**Action:** Design the data structure to support global search, but implement parent-scoped queries in MVP. Add global search in Phase 6.

---

## 3. Plugin System: Capability Requirements Strictness

### Question
How strict should capability requirements be?

### Recommendation: **Balanced Approach with Denylist as Hard Barrier**

**Reasoning:**
- **Too strict:** Plugins can't do anything useful, frustrating developers
- **Too loose:** Security risk, plugins can access sensitive data
- **Best approach:** Denylist blocks everything sensitive, capabilities grant specific permissions

**Implementation Approach:**

1. **Denylist (Hard Barrier - Always Checked First)**
   - Protected collections/spaces are completely off-limits
   - No plugin can access them, regardless of capabilities
   - Admin configurable in Global Security settings
   - Example: `payroll`, `legal_documents`, `hr_files`

2. **Capabilities (Permission Grants)**
   - Plugins declare what they need
   - Admin grants specific capabilities
   - Granular control (global, collection, entry level)
   - Example: `read_entries`, `modify_collections`, `manage_users`

3. **Default Behavior**
   - New plugins: No capabilities granted by default (secure by default)
   - Admin must explicitly grant permissions
   - Clear UI showing what plugin wants vs. what's granted

**Benefits:**
- ✅ Secure by default
- ✅ Flexible for legitimate use cases
- ✅ Clear security boundaries
- ✅ Admin has full control

**Action:** Implement denylist as hard barrier (checked first), then capability checks. New plugins start with zero permissions, admin grants as needed.

---

## 4. Database Backend: MongoDB in MVP

### Question
Should we support MongoDB from the start, or add later?

### Recommendation: **No, Add Later (Phase 4+ or Community)**

**Reasoning:**
- **Different query model:** MongoDB is document-based, different from SQL. Requires different query builder, different indexing strategy.
- **Complexity:** Adds significant complexity to the storage abstraction layer
- **Limited benefit:** Most users will use PostgreSQL, MySQL, or SQLite. MongoDB users are a small subset.
- **Better approach:** Focus on SQL databases first (Postgres, MySQL, SQLite), which share similar query models.

**Implementation Approach:**

**Phase 4 (MVP):**
- PostgreSQL (with JSONB) - Recommended for production
- MySQL/MariaDB - Widely available hosting
- SQLite - Simple local/on-prem deployments

**Phase 4+ or Community:**
- MongoDB driver (if there's user demand)
- Or let community build it

**Benefits:**
- ✅ Faster MVP delivery
- ✅ Focus on most common use cases
- ✅ Can add MongoDB later without breaking changes
- ✅ Community can contribute MongoDB driver

**Action:** Focus on SQL databases (Postgres, MySQL, SQLite) in Phase 4. MongoDB can be added later if there's demand, or left to community.

---

## 5. Automation: Webhook Inbound Triggers in MVP

### Question
Should we support webhook inbound triggers in MVP, or add in Phase 2?

### Recommendation: **Not in MVP, Add in Phase 2**

**Reasoning:**
- **Complexity:** Requires webhook endpoint management, security (signature verification), routing, and error handling
- **Not critical for MVP:** Entry lifecycle triggers cover most use cases initially
- **Better UX:** Get the core automation system working first, then add advanced triggers

**Implementation Approach:**

**Phase 5 (MVP):**
- Entry lifecycle triggers: `entry_created`, `entry_updated`, `entry_deleted`, `status_changed`
- Scheduled triggers: cron-like (daily at 09:00)
- Status transition triggers: `draft → published`

**Phase 5+ (Later):**
- Webhook inbound triggers: `webhook_received` with URL routing
- User events (if user plugin present): `user_created`, `user_login`
- Plugin events: `payment_succeeded`, `subscription_renewed`

**Benefits:**
- ✅ Faster MVP delivery
- ✅ Core functionality works first
- ✅ Can add webhooks later without breaking changes
- ✅ Most users will start with entry triggers anyway

**Action:** Focus on entry lifecycle and scheduled triggers in MVP. Add webhook inbound triggers in a later phase (Phase 5+ or Phase 6).

---

## Summary of Recommendations

| Question | Recommendation | Priority | Phase |
|----------|---------------|----------|-------|
| **Transactions** | Include in interface, implement later | High | Phase 1 (interface), Phase 4 (SQL) |
| **Sub-Entry Search** | Design for it, implement later | Medium | Phase 2 (design), Phase 6 (implement) |
| **Capability Strictness** | Balanced: Denylist + Capabilities | High | Phase 1 |
| **MongoDB Support** | Add later or community | Low | Phase 4+ or Community |
| **Webhook Triggers** | Add in Phase 2 | Medium | Phase 5+ |

---

## Updated Implementation Priorities

Based on these recommendations, here's the adjusted focus:

### Phase 1: Foundation (Critical)
- ✅ Storage interface with optional transaction methods
- ✅ Plugin capability system with denylist-first approach
- ✅ Enhanced hook system

### Phase 2: Sub-Entries (High Priority)
- ✅ Parent-child relationships (MVP)
- ✅ Design for global search (implement later)
- ✅ Public API for submissions

### Phase 3: Plugin System (High Priority)
- ✅ UI injection
- ✅ Plugin-to-plugin communication
- ✅ Security hardening

### Phase 4: Database Backend (Medium Priority)
- ✅ SQL drivers (Postgres, MySQL, SQLite)
- ✅ Transaction support (full implementation)
- ⏸️ MongoDB (defer to later or community)

### Phase 5: Automation (High Priority)
- ✅ Entry lifecycle triggers
- ✅ Scheduled triggers
- ⏸️ Webhook inbound triggers (defer to Phase 5+)

### Phase 6: Integration & Polish
- ✅ Global sub-entry search
- ✅ Webhook inbound triggers (if needed)
- ✅ All advanced features

---

## Risk Assessment

### Low Risk (Proceed with Confidence)
- ✅ Transaction interface design (no-op for flat-file is safe)
- ✅ Capability system with denylist (secure by default)
- ✅ Deferring MongoDB (can add later)

### Medium Risk (Monitor Closely)
- ⚠️ Sub-entry search design (ensure data structure supports it)
- ⚠️ Webhook triggers (ensure automation system can extend)

### Mitigation Strategies
1. **Design for future:** Always design data structures and interfaces to support future features
2. **Documentation:** Document what's planned for later phases
3. **Extension points:** Ensure systems can be extended without breaking changes

---

## Final Recommendations

**Proceed with these decisions:**
1. ✅ Include transaction methods in storage interface (optional, no-op for flat-file)
2. ✅ Design sub-entries for global search, implement in Phase 6
3. ✅ Use denylist-first approach for plugin security
4. ✅ Focus on SQL databases in Phase 4, defer MongoDB
5. ✅ Focus on entry triggers in automation MVP, defer webhooks

**These decisions:**
- ✅ Minimize risk (start simple, add complexity later)
- ✅ Deliver value faster (MVP focuses on core features)
- ✅ Maintain flexibility (can add features later)
- ✅ Follow best practices (secure by default, extensible design)

---

**Next Steps:**
1. Update unified implementation plan with these decisions
2. Begin Phase 1 implementation
3. Monitor and adjust as needed

---

**Document Status:** Final Recommendations  
**Date:** 2024-01-15  
**Based On:** Best practices, system architecture analysis, risk assessment
