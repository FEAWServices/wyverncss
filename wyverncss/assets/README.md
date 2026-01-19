# WyvernCSS - Frontend Assets

React/TypeScript frontend for the WyvernCSS Gutenberg integration.

## Quick Start

```bash
# Install dependencies
npm install

# Development mode (hot reload)
npm start

# Production build
npm run build

# Run tests
npm test

# Run tests in watch mode
npm run test:watch

# Check TypeScript types
npm run check-types

# Lint code
npm run lint

# Fix linting issues
npm run lint:fix
```

## Project Structure

```
src/
├── components/          # React components
│   ├── Sidebar/        # Main sidebar container
│   ├── ElementSelector/# Element selection UI
│   ├── DesignPrompt/   # Prompt input interface
│   └── StylePreview/   # Style preview & controls
├── hooks/              # Custom React hooks
│   ├── useDesignAPI.ts # API integration hook
│   └── useSettings.ts  # Settings management hook
├── services/           # API services
│   └── api.ts         # REST API client
├── types/             # TypeScript type definitions
│   └── index.ts       # Global types
├── styles/            # Global styles
│   └── main.css       # CSS variables & utilities
├── __tests__/         # Test files
│   ├── setup.ts       # Test configuration
│   ├── components/    # Component tests
│   └── hooks/         # Hook tests
└── index.tsx          # Entry point
```

## Component Overview

### Sidebar Components (FRONTEND-1)
- **WyvernCSSSidebar**: Main sidebar orchestrator
- **SidebarHeader**: Branding and logo

### Element Selector (FRONTEND-2)
- **ElementSelector**: Click-to-select interface
- **ElementHighlight**: Visual overlay for selection
- **ElementInfo**: Selected element details
- **useElementSelection**: Custom selection hook

### Design Prompt (FRONTEND-3)
- **DesignPrompt**: Main prompt interface
- **PatternSuggestions**: Quick design suggestions
- **PromptHistory**: Recent prompts list

### Style Preview (FRONTEND-4)
- **StylePreview**: Main preview container
- **BeforeAfter**: Comparison view
- **PreviewControls**: Apply/Cancel buttons
- **AccessibilityReview**: A11y warnings

## Development

### TypeScript Configuration
- Strict mode enabled
- No `any` types allowed
- Full type safety enforced

### Code Standards
- Functional components with hooks
- Props validation with TypeScript
- ARIA labels on all interactive elements
- Keyboard navigation support
- WCAG 2.1 AA compliance
- 80%+ test coverage required

### Testing
- Jest + React Testing Library
- jest-axe for accessibility testing
- Coverage thresholds enforced

### Build Process
Uses `@wordpress/scripts` which provides:
- Webpack bundling
- TypeScript compilation
- Hot module replacement (dev mode)
- Code splitting
- Asset optimization

## API Integration

The frontend communicates with WordPress REST API endpoints at:
- `POST /wp-json/wyverncss/v1/style` - Generate styles
- `GET /wp-json/wyverncss/v1/history` - Get history
- `POST /wp-json/wyverncss/v1/history` - Save to history

All requests include `X-WP-Nonce` header for authentication.

## Accessibility Features

- Full keyboard navigation (Tab, Enter, Esc, Arrow keys)
- ARIA labels and live regions
- Screen reader support
- Focus management
- Color contrast compliance
- Semantic HTML

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Troubleshooting

**Build fails:**
```bash
rm -rf node_modules package-lock.json
npm install
npm run build
```

**TypeScript errors:**
```bash
npm run check-types
```

**Tests failing:**
```bash
npm run test -- --verbose
```

## License

GPL v2 or later
