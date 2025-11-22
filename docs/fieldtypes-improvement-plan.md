# Fieldtypes Improvement Plan

## Issues Identified

### 1. **Validation & Error Handling**
- Inconsistent validation across fieldtypes
- Some fieldtypes have empty JavaScript validation
- Date/DateTime fieldtypes lack proper format validation
- Number fieldtype always converts to float (should handle integers)
- Missing proper type checking in processValue methods

### 2. **Code Quality**
- Some fieldtypes return empty JavaScript/CSS strings
- Inconsistent HTML escaping in render methods
- Missing sanitization in some processValue methods
- Complex initialization logic (Media, Relation) could fail silently

### 3. **Missing Common Fieldtypes**
- **Email** - Email validation
- **URL** - URL validation and formatting
- **Color** - Color picker
- **Time** - Time input (separate from DateTime)
- **Range/Slider** - Numeric range input
- **Radio** - Radio button group
- **MultiSelect** - Multiple selection dropdown

### 4. **Missing Advanced Fieldtypes**
- **JSON** - JSON editor with validation
- **Code** - Code editor with syntax highlighting
- **Markdown** - Markdown editor (separate from Editor)
- **Repeater** - Repeatable field groups
- **Group** - Field grouping/collapsible sections

### 5. **BaseField Improvements Needed**
- Add common validation helpers (email, URL, etc.)
- Better error handling utilities
- Consistent default value handling
- Better type coercion helpers

## Improvements to Implement

### Phase 1: Fix Existing Issues
1. Add proper validation to Date/DateTime fieldtypes
2. Fix Number fieldtype to handle integers vs floats
3. Add consistent error handling across all fieldtypes
4. Improve HTML escaping in render methods
5. Add proper sanitization to processValue methods

### Phase 2: Add Common Fieldtypes
1. Email fieldtype with validation
2. URL fieldtype with validation
3. Color picker fieldtype
4. Time fieldtype
5. Range/Slider fieldtype
6. Radio fieldtype
7. MultiSelect fieldtype

### Phase 3: Add Advanced Fieldtypes
1. JSON editor fieldtype
2. Code editor fieldtype
3. Markdown editor fieldtype
4. Repeater fieldtype
5. Group fieldtype

### Phase 4: Enhance BaseField
1. Add validation helper methods
2. Add error handling utilities
3. Improve type coercion
4. Add common formatting helpers

## Fieldtype Specifications

### Email Fieldtype
- Input type: email
- Validation: RFC 5322 compliant email validation
- Features: Auto-formatting, domain suggestions

### URL Fieldtype
- Input type: url
- Validation: Valid URL format
- Features: Auto-prepend http://, URL preview

### Color Fieldtype
- Input type: color
- Features: Color picker UI, hex/rgb/hsl support

### Time Fieldtype
- Input type: time
- Validation: Valid time format
- Features: 12/24 hour format option

### Range/Slider Fieldtype
- Input type: range
- Features: Min/max/step, value display, labels

### Radio Fieldtype
- Input type: radio
- Features: Option groups, inline/stacked layout

### MultiSelect Fieldtype
- Input type: select multiple
- Features: Search, select all, badges

### JSON Fieldtype
- Textarea with JSON validation
- Features: Syntax highlighting, format button, validation

### Code Fieldtype
- Code editor with syntax highlighting
- Features: Language selection, theme, line numbers

### Markdown Fieldtype
- Markdown editor
- Features: Preview, toolbar, syntax highlighting

### Repeater Fieldtype
- Repeatable field groups
- Features: Add/remove rows, drag to reorder, min/max rows

### Group Fieldtype
- Collapsible field group
- Features: Collapse/expand, nested groups

