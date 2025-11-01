# HaruCMS Onboarding System - Implementation Plan

## Overview
A comprehensive onboarding system to help new users get started with HaruCMS quickly and effectively.

## Core Components

### 1. Integrated Documentation
**What it is:**
- In-app help center accessible from any page
- Context-sensitive documentation panels
- Searchable knowledge base
- Quick reference guides

**Implementation:**
- Documentation API endpoint (`/api/docs.php`)
- Documentation files stored in `/docs/` directory (markdown format)
- Help modal/sidebar component
- Search functionality
- Topic categorization (Getting Started, Fieldtypes, Templates, API, etc.)

**UI Elements:**
- Help button (question mark icon) in header
- "Learn more" links on key features
- Contextual tooltips linking to relevant docs
- Documentation sidebar/modal overlay

---

### 2. First-Time Visit Guide
**What it is:**
- Welcome screen for new users
- Interactive setup wizard
- Feature introduction tour
- Progress tracking

**Implementation:**
- Detection of first-time visit (check if any collections exist)
- Welcome modal/wizard component
- Step-by-step setup flow:
  1. Welcome message
  2. Site configuration (title, description, logo)
  3. Choose demo content (optional)
  4. Choose demo website (optional)
  5. Quick tour of admin interface
- Save onboarding completion status to user settings

**UI Elements:**
- Full-screen welcome modal
- Progress indicator (steps 1/5, 2/5, etc.)
- Skip button for experienced users
- "Get Started" and "Skip Setup" buttons

---

### 3. Demo Content (Optional)
**What it is:**
- Pre-configured collections with sample data
- Realistic example content structures
- Ready-to-use fieldtype examples
- Sample entries demonstrating features

**Available Demo Packs:**
- **Blog Demo**: Blog posts collection with featured images, categories, tags
- **Portfolio Demo**: Projects collection with images, client info, skills
- **Documentation Site**: Pages collection with navigation, categories
- **E-commerce Demo**: Products, categories, reviews collections
- **Corporate Site**: Services, team, testimonials, case studies

**Implementation:**
- Demo pack installer (`/api/demo.php`)
- Demo content files in `/demo-content/` directory
- Each pack contains:
  - Collection schemas (`schema.json`)
  - Sample entries (JSON files)
  - Sample media files
  - Optional templates
- Install button in onboarding wizard
- Can be installed during setup or later from settings

**UI Elements:**
- Demo pack selection screen (cards with preview images)
- "Install [Demo Name]" buttons
- "Preview" link to see demo structure
- "Already have content" skip option

---

### 4. Demo Website/Frontend (Optional)
**What it is:**
- Complete example frontend templates
- Demonstrates HaruCMS templating language
- Multiple style options (minimal, corporate, blog, portfolio)
- Working example site visitors can browse

**Available Demo Templates:**
- **Minimal Blog**: Clean blog design
- **Corporate**: Professional business site
- **Portfolio**: Creative portfolio showcase
- **Documentation**: Knowledge base/help center style

**Implementation:**
- Demo templates in `/demo-templates/` directory
- Each template includes:
  - Homepage template
  - Collection listing pages
  - Entry detail pages
  - Stylesheet (CSS)
  - Sample navigation structure
  - README explaining template structure
- Template installer (`/api/demo-templates.php`)
- Can be installed during onboarding or separately
- Option to preview before installing

**UI Elements:**
- Template selection screen with previews
- "Install Template" button
- "Preview Demo Site" link (opens in new tab)
- Template switcher in settings (if multiple installed)

---

## Additional Onboarding Features

### 5. Interactive Tutorial/Walkthrough
**What it is:**
- Guided tour of admin interface
- Step-by-step tutorials for common tasks
- Highlight key features with tooltips
- Interactive task completion

**Tutorials to Include:**
- "Create Your First Entry" (3-5 steps)
- "Set Up a Collection" (fieldtype selection, configuration)
- "Manage Media Files" (upload, organize, use)
- "Configure Settings" (site info, users, security)
- "Use Templates" (basic templating concepts)

**Implementation:**
- Tutorial engine (`/admin/js/tutorial.js`)
- Tutorial data files (JSON) defining steps
- Progress saving (user settings)
- "Start Tutorial" button on dashboard
- "Show me how" quick links on relevant pages

**UI Elements:**
- Tour overlay with step indicators
- Highlighted elements (pulse animation)
- Tooltip balloons with instructions
- "Next" / "Previous" / "Skip" buttons
- Completion badge/certificate

---

### 6. Setup Wizard
**What it is:**
- Initial configuration assistant
- Helps configure essential settings
- Can be run on first login or manually

**Steps:**
1. **Welcome** - Introduction to HaruCMS
2. **Site Information** - Title, description, logo upload
3. **Admin User** - Create first admin (if not exists)
4. **Security** - Set password policy, enable 2FA option
5. **Content Setup** - Choose demo content or start fresh
6. **Frontend** - Choose demo template or skip
7. **Completion** - Summary and next steps

**Implementation:**
- Wizard component (`/admin/templates/setup-wizard.html`)
- API endpoint (`/api/setup.php`)
- Step validation and progress saving
- Can be accessed from settings if skipped

---

### 7. Getting Started Checklist
**What it is:**
- Visual checklist of important first steps
- Shows on dashboard for new users
- Can be dismissed when complete
- Links directly to relevant pages

**Checklist Items:**
- [ ] Complete site setup
- [ ] Create your first collection
- [ ] Add your first entry
- [ ] Upload media files
- [ ] Configure site settings
- [ ] Set up your first template (optional)
- [ ] Explore the documentation

**Implementation:**
- Checklist component on dashboard
- Progress tracking (check off items)
- Auto-detect completion (e.g., "first entry created")
- API endpoint to track checklist status

---

### 8. Contextual Help System
**What it is:**
- Helpful tooltips on UI elements
- "What's this?" explanations
- Quick help panels
- Links to relevant documentation

**Implementation:**
- Tooltip library (`/admin/js/help-tooltips.js`)
- Help data file (JSON) mapping elements to help text
- "?" icons next to key features
- Keyboard shortcut (?) to toggle help mode

**Examples:**
- Help icon next to "Collections" explains what they are
- Tooltip on fieldtype selector shows fieldtype descriptions
- Help panel on entry editor explaining features

---

### 9. Welcome Dashboard
**What it is:**
- Special dashboard view for new users
- Different from standard dashboard
- Highlights key actions and features
- Progress indicators

**Content:**
- Welcome message with user's name
- Quick action cards (Create Collection, Add Entry, etc.)
- Getting started checklist
- Recent activity (if any)
- Helpful links and resources
- "Take a Tour" button

**Implementation:**
- Check if user has completed onboarding
- Show welcome dashboard if `onboarding_complete === false`
- Transition to normal dashboard after onboarding complete
- Can manually switch between views

---

### 10. Progress Tracking
**What it is:**
- Track onboarding completion
- User progress metrics
- Achievement badges (optional)
- Completion percentage

**Metrics to Track:**
- Setup wizard completed
- First collection created
- First entry created
- First template created (optional)
- Documentation read (optional)
- Tutorials completed

**Implementation:**
- User settings field: `onboarding_progress` (object)
- API to update progress
- Progress indicator component
- Completion screen with next steps

---

### 11. Sample Content Generator
**What it is:**
- Generate realistic sample content programmatically
- Useful for testing templates
- Populate collections with varied data

**Implementation:**
- Content generator API (`/api/generate-sample.php`)
- Template-based content generation
- Random text generators (lorem ipsum style)
- Random image placeholders
- Configurable amount of content

---

### 12. Help Center Integration
**What it is:**
- Centralized help resource
- FAQ section
- Video tutorials (embedded or linked)
- Community links
- Support contact information

**Implementation:**
- Help center page (`/admin/templates/help-center.html`)
- FAQ database (JSON file or API)
- Video tutorial links/cards
- Search functionality
- Category organization

---

## Implementation Priority

### Phase 1: Core Onboarding (Essential)
1. ✅ First-time visit detection
2. ✅ Welcome screen/wizard
3. ✅ Demo content installer
4. ✅ Demo template installer
5. ✅ Onboarding progress tracking

### Phase 2: Interactive Features (High Value)
6. ✅ Getting started checklist
7. ✅ Interactive tutorial/walkthrough
8. ✅ Contextual help system
9. ✅ Welcome dashboard

### Phase 3: Documentation & Resources (Nice to Have)
10. ✅ Integrated documentation system
11. ✅ Help center
12. ✅ Sample content generator

---

## Technical Architecture

### File Structure
```
/admin/
  templates/
    onboarding/
      welcome.html
      setup-wizard.html
      demo-selector.html
      tutorial.html
  js/
    onboarding.js
    tutorial.js
    help-tooltips.js
  css/
    onboarding.css
    tutorial.css

/api/
  onboarding.php      # Onboarding API
  demo.php           # Demo content installer
  demo-templates.php # Demo template installer
  setup.php          # Setup wizard API
  docs.php           # Documentation API

/docs/
  getting-started.md
  tutorials/
  api/
  fieldtypes/

/demo-content/
  blog/
  portfolio/
  documentation/
  corporate/

/demo-templates/
  minimal-blog/
  corporate/
  portfolio/
  documentation/

/settings/
  onboarding.json    # User onboarding progress
```

### Database/Storage
- Onboarding progress: `settings/onboarding.json`
- User-specific onboarding: `users/{user_id}/onboarding.json`
- Tutorial progress: `users/{user_id}/tutorials.json`

### API Endpoints
- `GET /api/onboarding.php?action=status` - Get onboarding status
- `POST /api/onboarding.php?action=complete` - Mark onboarding complete
- `GET /api/demo.php?action=list` - List available demo packs
- `POST /api/demo.php?action=install&pack=blog` - Install demo pack
- `GET /api/docs.php?action=search&q=query` - Search documentation
- `GET /api/docs.php?action=get&topic=fieldtypes` - Get specific doc

---

## User Experience Flow

### New User Journey
1. **First Login** → Welcome screen appears
2. **Setup Wizard** → Configure basic settings (optional)
3. **Demo Selection** → Choose demo content/templates (optional)
4. **Dashboard** → Welcome dashboard with checklist
5. **Tutorial** → "Take a Tour" button (optional)
6. **First Actions** → Guided creation of collection/entry
7. **Progress** → Checklist updates as user completes tasks
8. **Completion** → Normal dashboard, help always available

### Returning User
- Skip onboarding, go straight to dashboard
- Help always accessible via header icon
- Can restart tutorial anytime
- Can install demo content from settings

---

## Success Metrics
- Time to first entry created
- Onboarding completion rate
- Feature discovery rate
- Support ticket reduction
- User satisfaction scores

---

## Next Steps
1. Create onboarding detection logic
2. Build welcome screen/wizard
3. Create demo content packs
4. Build demo template installer
5. Implement tutorial system
6. Add contextual help
7. Integrate documentation system

