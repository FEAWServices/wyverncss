# WyvernCSS AI Admin Console - Quick Start Guide

## Overview

This guide will help you get the AI Admin Console running in your WordPress environment.

---

## Prerequisites

- WordPress 6.7+
- Node.js 18+
- npm 9+
- WyvernCSS plugin installed

---

## Development Setup

### 1. Install Dependencies

```bash
cd /workspace/plugin/assets
npm install
```

### 2. Start Development Server

```bash
npm start
```

This starts webpack in watch mode. Any changes to React components will auto-rebuild.

### 3. Build for Production

```bash
npm run build
```

Output files will be in `/workspace/plugin/assets/build/`:
- `ai-console.js` (JavaScript bundle)
- `ai-console.css` (Styles)
- `ai-console.asset.php` (WordPress dependencies)

---

## WordPress Setup

### 1. Activate Plugin

Navigate to WordPress admin:
- **Plugins** → **Installed Plugins**
- Find **WyvernCSS**
- Click **Activate**

### 2. Access Admin Console

Navigate to:
- **WyvernCSS AI** (in left admin menu)

You'll see the React app load!

### 3. Configure API Key

1. Click **Settings** button in header
2. Enter your OpenRouter API key (get from https://openrouter.ai/keys)
3. Click **Save & Verify**
4. Wait for verification success message

### 4. Create First Conversation

1. Click **Back to Chat** button
2. In sidebar, click **New** button
3. Enter conversation title (optional)
4. Click **Create**

### 5. Start Chatting!

1. Type a message in the input box at bottom
2. Press **Enter** to send (or click **Send** button)
3. Watch AI respond with actions!

---

## File Structure

```
/workspace/plugin/assets/
├── src/
│   ├── admin/                    # Admin Console source
│   │   ├── components/           # React components
│   │   ├── hooks/                # Custom hooks
│   │   ├── services/             # API client
│   │   ├── types/                # TypeScript types
│   │   ├── styles/               # CSS
│   │   └── index.tsx             # Entry point
│   └── index.tsx                 # Gutenberg sidebar (existing)
├── build/                        # Compiled output
│   ├── ai-console.js
│   ├── ai-console.css
│   └── ai-console.asset.php
├── package.json
├── webpack.config.js
└── tsconfig.json
```

---

## Common Development Tasks

### Make Changes to UI

1. Edit files in `src/admin/components/`
2. Save file
3. Webpack auto-rebuilds
4. Refresh WordPress admin page to see changes

### Add New Component

```bash
# 1. Create component file
touch src/admin/components/MyComponent/MyComponent.tsx

# 2. Add TypeScript types
# Edit src/admin/types/index.ts

# 3. Import and use in App.tsx or other component
```

### Add New API Endpoint

```bash
# 1. Add to services/api.ts
# 2. Add TypeScript types to types/index.ts
# 3. Use in hooks or components
```

### Update Styles

```bash
# Edit src/admin/styles/main.css
# Webpack will auto-rebuild CSS
```

### Run Type Checking

```bash
npm run check-types
```

### Run Linter

```bash
npm run lint
npm run lint:fix  # Auto-fix issues
```

---

## Troubleshooting

### Asset Not Loading

**Problem**: Admin page shows "assets not built" warning

**Solution**:
```bash
cd /workspace/plugin/assets
npm run build
```

### TypeScript Errors

**Problem**: Build fails with TypeScript errors

**Solution**:
```bash
# Check errors
npm run check-types

# Common fixes
npm install
rm -rf node_modules package-lock.json
npm install
```

### API Errors

**Problem**: "WordPress data not found" error

**Solution**:
- Verify `Admin_Page.php` is enqueuing scripts correctly
- Check browser console for errors
- Verify REST API is accessible at `/wp-json/wyverncss/v1`

### No Conversations Showing

**Problem**: Sidebar is empty

**Solution**:
- Click **New** button to create first conversation
- Check browser console for API errors
- Verify database tables exist
- Check user has `edit_posts` capability

### Styling Issues

**Problem**: Layout broken or styles not applying

**Solution**:
- Verify `ai-console.css` file exists in `build/`
- Check browser console for 404 errors
- Hard refresh browser (Ctrl+Shift+R)
- Clear WordPress cache

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Enter` | Send message (when input focused) |
| `Shift+Enter` | New line in message |
| `Tab` | Navigate between elements |
| `Escape` | Close modals |

---

## Browser DevTools

### React DevTools

Install React DevTools extension:
- Chrome: https://chrome.google.com/webstore
- Firefox: https://addons.mozilla.org/firefox

Features:
- Inspect component tree
- View props and state
- Profile performance
- Debug hooks

### Debug Mode

Enable WordPress debug mode in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs:
```bash
tail -f /path/to/wordpress/wp-content/debug.log
```

---

## Testing

### Manual Testing Checklist

- [ ] Admin page loads
- [ ] React app renders
- [ ] Can configure API key
- [ ] Can create conversations
- [ ] Can send messages
- [ ] AI responses appear
- [ ] Tool executions display
- [ ] Can delete conversations
- [ ] Settings save correctly
- [ ] Keyboard navigation works
- [ ] Mobile responsive

### Automated Tests (Future)

```bash
# Run tests
npm test

# Watch mode
npm run test:watch

# Coverage
npm run test:coverage
```

---

## Production Deployment

### 1. Build Production Assets

```bash
cd /workspace/plugin/assets
NODE_ENV=production npm run build
```

### 2. Verify Output

```bash
ls -lh build/ai-console.*
```

Should see:
- `ai-console.js` (~22 KB)
- `ai-console.css` (~6.5 KB)
- `ai-console.asset.php`

### 3. Deploy Plugin

1. Copy entire `plugin/` directory to production
2. Activate plugin in WordPress
3. Configure API key in settings
4. Test functionality

### 4. Monitor Performance

- Check page load time (<100ms for assets)
- Monitor API response times
- Watch for JavaScript errors in console
- Test on multiple browsers/devices

---

## Architecture Overview

### Data Flow

```
User Input
    ↓
MessageInput Component
    ↓
useChat Hook
    ↓
api.chat.sendMessage()
    ↓
WordPress REST API
    ↓
OpenRouter API
    ↓
AI Response
    ↓
useChat Hook (updates state)
    ↓
MessageList Component
    ↓
MessageBubble Component
    ↓
Display to User
```

### State Management

```
App Component
    ├── useSettings() → Settings state
    ├── useConversations() → Conversation list
    └── ChatInterface
            └── useChat() → Messages
```

### Component Hierarchy

```
<App>
    <Header>
        <Button>Settings</Button>
    </Header>
    <ConversationList>
        <ConversationItem />
        <ConversationItem />
    </ConversationList>
    <ChatInterface>
        <MessageList>
            <MessageBubble>
                <ToolExecution />
            </MessageBubble>
        </MessageList>
        <MessageInput />
    </ChatInterface>
</App>
```

---

## API Reference

See `/workspace/plugin/assets/src/admin/README.md` for complete API documentation.

### Quick Examples

#### Send Message

```typescript
const { sendMessage } = useChat(conversationId);
await sendMessage('Create a blog post about AI');
```

#### Create Conversation

```typescript
const { createConversation } = useConversations();
const id = await createConversation({ title: 'My Conversation' });
```

#### Save Settings

```typescript
const { saveApiKey } = useSettings();
const success = await saveApiKey('sk-or-v1-...');
```

---

## Resources

### Documentation
- Main README: `/workspace/plugin/assets/src/admin/README.md`
- Implementation Summary: `/workspace/ADMIN_CONSOLE_IMPLEMENTATION_SUMMARY.md`
- REST API Spec: `/workspace/docs/REST_API_SPECIFICATION.md`

### External Links
- React Docs: https://react.dev
- TypeScript Handbook: https://www.typescriptlang.org/docs
- WordPress Components: https://developer.wordpress.org/block-editor/reference-guides/components
- OpenRouter API: https://openrouter.ai/docs

---

## Support

### Getting Help

1. Check documentation in `/docs/`
2. Review browser console for errors
3. Enable WordPress debug mode
4. Check REST API endpoints directly
5. Verify database tables exist

### Common Issues

| Issue | Solution |
|-------|----------|
| White screen | Check console for errors, rebuild assets |
| API errors | Verify nonce, check user permissions |
| No conversations | Create first conversation manually |
| Styles broken | Hard refresh, check CSS file exists |
| Build fails | Delete node_modules, reinstall |

---

## Next Steps

After getting the console running:

1. ✅ Test basic functionality
2. ✅ Configure API key
3. ✅ Create test conversations
4. ✅ Send test messages
5. ✅ Verify tool executions work
6. ✅ Test on mobile devices
7. ✅ Check accessibility with screen reader
8. ✅ Deploy to production

---

**Happy Coding!** 🚀
