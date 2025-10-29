Issues:
- Login is buggy und ugly
- entry tables need to be more responsive
- general settings needs to be rethought
- user management - add new user doesn't work
- make sure plugins are totally self-contained
- relation fieldtype isn't advanced enough
- pushing data from the website to the cms needs to get tested and probably improved (like a contact form on the website)
- authentication/login feels a bit random. sometimes I can visit the cms after a while, click for some seconds through the system until i need to re login again. might be also a security risk?


wishlist:
- multi item fieldtype (one fieldtype which can create multiple other fieldtypes)
- collection type: intern, public
- entry tables need to be sortable, filtable and searchabe as well as draggable row to change the order
- collection cards should show how many entries they have
- full-screen editor modals
- plugin security messurements (need to ask for confirmation by the user to access specific data of the cms)
- plugin updating system? How it will work?
- cms updating system? how it will work?
- optional work with database instead of flat-file system
- localization/Internationalization system
- entries can be edited, but not directly published the changes - will be marked as unpublished changes or something like thats
- admin user management with proper roles system
- entry lists can be customized by what should be displayed in the list, by checking boxes for each fieldtype of the collection, the list can be either fully displayed, or just a small amount to have a cleaner list of entries
- invite team members feature
- login/signup with third-party profiles (google etc)
- brainstorming for more useful fieldtypes, like multi item field, maps, files, block editor etc research what are the most requested fieldtypes or content blocks in other headless cms



NEW

- Fieldtype which can implement third-party scripts and api's
- enhance the community user plugin
- fieldtype for tables
- Singleton Collection?
- multi select fieldtype
- ability to make a relation between fieldtype and entries (for example: multi select have a relation to the collection "Categories" and then all categories will be available for selection)
- admin ui needs to be decoubled and built up with the cms own templating language, similar as building an external website. This way, ui changes will not affect teh functioanility of the cms and make updating the ui much mor eeasy
- analytics for the dahsboard
- mcp server to connect haru cms directly with a LLM
- SEO & LLM optimizing
- frontend rendering preview for entries ?
- Templates for collections, which automatically creates from a logic - for examples menu, which creates a collection called menu which automatically creates entries with relations to other collections to get used as a menu
- code fieldtype
- gallery fieldtype
- match the web templating engine with teh admin templating engine (for example quotes "" to call user specified collections or entries)
- Schedule publishing
- Bulk editing for entries
- duplicate entry as unpluplished
- content to mcp to llm. prepare content to get displayed for llm. siilar like meta info for web, we need an ai info for llm


Missing Fieldtypes:
- Code/script which can execute code, but not render
- table fieldtype?
- color picker
- map
- location/address
- email
- embed (youtube, spotify etc)

- Media: Image, Video, Document, File, Gallery, Audio, Any
- Content: Text, TextArea, Editor, Table, Number, Tags, map, address/location, email
- Options: Select, Switch, Radio, Checkbox, Date/time, Color Picker
- Logic: Relations, Scripts
- General: Dynamic Items, 




AI IDEAS:

 Potential Additional Enhancements
1. Advanced Fieldtype Features
Fieldtype Templates: Pre-built fieldtype templates for common use cases
Fieldtype Builder: Visual drag-and-drop fieldtype builder
Fieldtype Marketplace: System for sharing and installing third-party fieldtypes
Fieldtype Versioning: Version control for fieldtypes with rollback capabilities
Fieldtype Dependencies: Automatic dependency management between fieldtypes
2. Enhanced Developer Experience
Live Code Editor: In-browser code editor for fieldtype development
Fieldtype Testing Framework: Automated testing for fieldtypes
Performance Profiler: Detailed performance analysis with recommendations
Memory Leak Detector: Automatic detection and reporting of memory leaks
Code Coverage Analysis: Track which parts of fieldtypes are being used
3. Advanced Configuration System
Visual Configuration Builder: Drag-and-drop configuration interface
Configuration Validation: Real-time validation of fieldtype configurations
Configuration Templates: Pre-built configuration templates
Configuration Import/Export: Save and share fieldtype configurations
Environment-Specific Configs: Different configurations for dev/staging/prod
4. Enhanced Event System
Event Visualizer: Visual representation of event flow
Event Replay: Replay events for debugging
Event Filtering: Advanced filtering and search for events
Event Analytics: Analytics on event frequency and patterns
Custom Event Types: Allow fieldtypes to define custom event types
5. Advanced Validation System
Visual Validation Builder: Drag-and-drop validation rule builder
Validation Templates: Pre-built validation templates
Cross-Field Validation: Validation rules that depend on multiple fields
Conditional Validation: Validation rules that change based on field values
Validation Testing: Test validation rules with sample data
6. Performance Enhancements
Predictive Loading: Predict which fieldtypes will be needed
Smart Caching: AI-powered caching decisions
Bundle Optimization: Automatic JavaScript/CSS bundling and minification
CDN Integration: Automatic CDN deployment for fieldtype assets
Performance Budgets: Set and enforce performance budgets
7. Documentation & Help System
Interactive Documentation: Clickable documentation with live examples
Context-Sensitive Help: Help that appears based on what you're doing
Video Tutorials: Embedded video tutorials for complex features
Community Documentation: User-contributed documentation
API Documentation: Auto-generated API documentation
8. Advanced Debugging Tools
Time Travel Debugger: Step through fieldtype execution history
State Inspector: Visual representation of fieldtype state
Network Inspector: Monitor API calls and data flow
Error Prediction: Predict potential errors before they happen
Debug Session Sharing: Share debug sessions with team members
9. Collaboration Features
Real-time Collaboration: Multiple developers working on the same fieldtype
Code Review System: Built-in code review for fieldtypes
Team Permissions: Role-based access to different parts of the system
Change Tracking: Track who made what changes and when
Approval Workflows: Require approval for fieldtype changes
10. Integration & Extensibility
Plugin System: Allow third-party plugins to extend the system
API Gateway: Centralized API management for fieldtypes
Webhook System: Trigger webhooks on fieldtype events
External Service Integration: Connect to external services (GitHub, etc.)
CI/CD Integration: Automatic testing and deployment
🎯 Priority Recommendations
Based on the current system, I'd recommend these high-impact enhancements in order of priority:
Phase 7: Advanced Fieldtype Features (Next Priority)
Fieldtype Templates - Pre-built templates for common use cases
Visual Configuration Builder - Drag-and-drop configuration interface
Fieldtype Testing Framework - Automated testing for fieldtypes
Phase 8: Enhanced Developer Experience
Live Code Editor - In-browser code editor for fieldtype development
Performance Profiler - Detailed performance analysis
Interactive Documentation - Clickable documentation with live examples
Phase 9: Advanced Validation & Events
Visual Validation Builder - Drag-and-drop validation rule builder
Event Visualizer - Visual representation of event flow
Cross-Field Validation - Validation rules that depend on multiple fields
Phase 10: Collaboration & Integration
Real-time Collaboration - Multiple developers working together
Plugin System - Allow third-party plugins to extend the system
CI/CD Integration - Automatic testing and deployment