# CSS Modules Architecture

This directory contains modular CSS files organized by concern. Each module focuses on a specific aspect of styling.

## Module Structure

- **variables.css** - Design tokens, colors, typography, spacing constants
- **base.css** - Base element styles, resets, foundational styling
- **layout.css** - Page structure, containers, grid, flexbox utilities
- **header.css** - Page header and title styling (unified across pages)
- **controls.css** - Form controls, buttons, inputs, selects, toolbars
- **tables.css** - Table styling, pagination, sorting indicators
- **modals.css** - Modal dialogs, overlays, confirm boxes
- **badges.css** - Status badges, filter badges, labels
- **pages.css** - Page-specific styles (inbox, contacts, logs)
- **responsive.css** - Media queries and responsive adjustments

## Import Order

1. variables.css (CSS custom properties must come first)
2. base.css (Foundational styles)
3. layout.css (Layout utilities)
4. header.css (Header styling)
5. controls.css (Form controls)
6. tables.css (Table styling)
7. badges.css (Badge components)
8. modals.css (Modal dialogs)
9. pages.css (Page-specific styling)
10. responsive.css (Responsive overrides)

## Merging for Production

Run: `cat modules/*.css > admin-global.min.css`

## Best Practices

- Keep modules focused and single-responsibility
- Use CSS custom properties for reusable values
- Avoid !important flags (use specificity instead)
- Maintain consistent naming conventions
- Document complex selectors with comments
