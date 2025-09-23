# DGW Revision Manager

A modern WordPress plugin for managing revisions with timeline visualization and enhanced editor integration. Built with @wordpress/scripts and React components for a seamless editing experience.

## Overview

This plugin provides a modern approach to WordPress revision management, featuring:

- **Revision Mode Control**: Choose between standard WordPress revisions or approval workflow
- **Timeline Visualization**: Interactive timeline showing current, past, and pending revisions (approval mode)
- **Sidebar Editor Panel**: React-based sidebar panel in the block editor
- **Enhanced Revision Editor**: Improved revision.php page with "Set as Current" functionality
- **Non-Destructive Switching**: Switch between revisions without losing revision history
- **Progressive Interface**: Clean, contextual controls that adapt to the selected revision mode

## Features

### 🎯 Editor Sidebar Panel
- **Revision Mode Selection**: Choose between Standard WordPress Revisions or Requires Approval
- **Progressive Interface**: Timeline and controls only show when approval mode is selected
- **Visual Timeline**: Interactive timeline with current, past, and pending revision indicators (approval mode)
- **One-click Revision Switching**: Set any revision as current with confirmation modals
- **Per-post Control**: Set revision mode individually for each post

### 📈 Timeline Visualization
```
🔮 Pending 3 (Pending)
⏳ Pending 2 (Pending)
⭐ Current (Current)
📜 Past 1 (Past)
📜 Past 2 (Past)
📜 Past 3 (Past)
```

### 🔧 Revision Editor Enhancements
- "Set as Current" button on revision.php pages
- Timeline context showing where current revision fits
- Confirmation modals for safety
- Success/error notifications

### 🔒 Status Management
- **Open**: Changes publish immediately (traditional WordPress)
- **Pending**: Changes require approval workflow
- **Locked**: Only editors can make changes

## Installation

1. **Upload the plugin** to your `/wp-content/plugins/` directory
2. **Install dependencies**:
   ```bash
   cd wp-content/plugins/dgwltd-revision-manager
   npm install
   npm run build
   ```
3. **Activate the plugin** in WordPress admin
4. **Enable revision management** in post editor sidebar

## Development

### Build Commands
- `npm run start` - Development mode with file watching
- `npm run build` - Production build
- `npm run lint:js` - Lint JavaScript files
- `npm run lint:css` - Lint CSS files

### Project Structure
```
dgwltd-revision-manager/
├── src/                           # React/JS source files
│   ├── components/               # React components
│   │   ├── RevisionManagerPanel.js
│   │   ├── RevisionTimeline.js
│   │   └── RevisionItem.js
│   ├── editor.js                 # Editor sidebar integration
│   ├── revision-editor.js        # Revision page enhancements
│   ├── admin.js                  # Admin functionality
│   └── *.scss                    # Styles
├── includes/                      # PHP classes
│   ├── Core/                     # Core plugin classes
│   │   ├── Plugin.php
│   │   └── Assets.php
│   ├── API/                      # REST API controllers
│   │   └── RevisionController.php
│   ├── Admin/                    # Admin functionality
│   │   └── EditorPanel.php
│   ├── Frontend/                 # Public-facing functionality
│   │   └── RevisionFilter.php
│   └── Utils/                    # Utility classes
│       └── RevisionHelper.php
├── build/                        # Compiled assets (auto-generated)
├── assets/                       # Static assets
├── languages/                    # Translation files
└── tests/                        # Unit and integration tests
```

## Usage

### Basic Workflow

1. **Open a post** in the block editor
2. **Find the Revision Manager panel** in the sidebar (📄 icon)
3. **Set the revision status** (open/pending/locked)
4. **View the timeline** of revisions with visual indicators
5. **Switch revisions** using "Set as Current" buttons

### Advanced Features

#### Timeline Navigation
- Visual timeline shows revision relationships
- Click "View" to open revision in comparison editor
- "Set as Current" promotes any revision to be the active version

#### Revision Editor Integration
- Enhanced `/wp-admin/revision.php` pages
- Timeline context shows where revision fits
- One-click switching with confirmation

#### Status Control
- **Per-post control** overrides global settings
- **Visual indicators** show current status
- **Real-time updates** when status changes

## API Reference

### REST API Endpoints

All endpoints are prefixed with `/wp-json/dgw-revision-manager/v1/`

#### Get Post Revisions
```
GET /revisions/{post_id}
```
Returns revision timeline data for a post.

#### Set Revision as Current
```
POST /revisions/{revision_id}/set-current
```
Sets a specific revision as the current/active version.

#### Update Post Status
```
PUT /revisions/{post_id}/status
```
Updates the revision management status for a post.

### JavaScript Integration

#### Using the Timeline Component
```javascript
import { RevisionTimeline } from './components/RevisionTimeline';

<RevisionTimeline
    revisionData={revisionData}
    onRevisionChange={handleRevisionChange}
/>
```

#### API Usage
```javascript
import apiFetch from '@wordpress/api-fetch';

// Get revision data
const revisions = await apiFetch({
    path: `/dgw-revision-manager/v1/revisions/${postId}`
});

// Set revision as current
await apiFetch({
    path: `/dgw-revision-manager/v1/revisions/${revisionId}/set-current`,
    method: 'POST'
});
```

## Technical Details

### Built With
- **@wordpress/scripts** - Modern WordPress development toolchain
- **React** - UI components and state management
- **WordPress Components** - Native WordPress UI components
- **WordPress Data** - Data layer integration
- **SCSS** - Styled with WordPress design system

### Browser Support
- Modern browsers supporting ES2017
- WordPress admin interface compatibility
- Responsive design for mobile/tablet editing

### Performance
- Efficient REST API queries
- Lightweight JavaScript bundle
- Progressive enhancement approach
- Minimal database impact

## Compatibility

- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher
- **Node.js**: 18.0 or higher (for development)
- **Browsers**: Modern browsers with ES2017 support

## Relationship to Original Plugin

This plugin is a modern reimagining of `dgwltd-pending-revisions-meta`, featuring:

- **Modern architecture** with @wordpress/scripts
- **Enhanced UX** with timeline visualization
- **Better performance** with optimized queries
- **Improved maintainability** with modern development tools

The original plugin remains available and functional. This new plugin provides a more modern approach with enhanced features.

## Development Roadmap

### Phase 1 ✅ (Current)
- [x] Core plugin architecture
- [x] Timeline visualization
- [x] Sidebar editor panel
- [x] Revision switching
- [x] Enhanced revision editor

### Phase 2 (Future)
- [ ] Advanced permissions system
- [ ] Bulk revision operations
- [ ] Revision approval workflows
- [ ] Advanced filtering options
- [ ] Performance optimizations

### Phase 3 (Future)
- [ ] Integration with existing revision plugin
- [ ] Migration tools
- [ ] Advanced reporting
- [ ] Webhook integrations

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes with tests
4. Ensure all linting passes
5. Submit a pull request

## License

GPL v2 or later - same as WordPress

## Support

For issues and feature requests, please use the repository's issue tracker.

---

**Made with ❤️ by [DGW.ltd](https://dgw.ltd/)**