# Theme System Documentation

## Overview
The application now supports light, dark, and system theme modes. The theme system is built using React Context and localStorage for persistence.

## Components Added

### 1. Theme Hook (`/resources/js/hooks/use-theme.tsx`)
- Provides theme context and state management
- Supports 'light', 'dark', and 'system' themes
- Persists theme preference in localStorage
- Automatically applies system preference when 'system' is selected

### 2. Theme Toggle Components
- **ThemeToggle** (`/resources/js/components/theme-toggle.tsx`): Dropdown menu with all theme options
- **ThemeToggleSimple** (`/resources/js/components/theme-toggle-simple.tsx`): Single button that cycles through themes

### 3. Appearance Settings Page (`/resources/js/pages/settings/appearance.tsx`)
- Dedicated settings page for theme selection
- Visual buttons for each theme option
- Accessible via Settings menu

## Implementation Details

### Theme Provider Setup
The app is wrapped with `ThemeProvider` in `app.tsx`:
```tsx
<ThemeProvider defaultTheme="system" storageKey="dataworldpro-ui-theme">
    <App {...props} />
</ThemeProvider>
```

### Layout Integration
Theme toggles have been added to:
- **DashboardLayout**: In the header next to user menu
- **AdminLayout**: In the header with other controls
- **GuestLayout**: Top-right corner for unauthenticated users

### CSS Variables
The theme system uses CSS custom properties defined in `app.css`:
- Light theme variables in `:root`
- Dark theme variables in `.dark` class
- Automatic color switching based on theme class

### Server-Side Theme Detection
The blade template (`app.blade.php`) includes a script that:
- Reads theme preference from localStorage
- Applies theme class before React loads
- Prevents flash of wrong theme on page load

## Usage

### Using the Theme Hook
```tsx
import { useTheme } from '@/hooks/use-theme';

function MyComponent() {
    const { theme, setTheme } = useTheme();
    
    return (
        <button onClick={() => setTheme('dark')}>
            Current theme: {theme}
        </button>
    );
}
```

### Adding Theme Toggle to Components
```tsx
import { ThemeToggle } from '@/components/theme-toggle';

function MyLayout() {
    return (
        <header>
            <ThemeToggle />
        </header>
    );
}
```

## Theme Options
- **Light**: Force light theme
- **Dark**: Force dark theme  
- **System**: Follow system preference (default)

## Storage
Theme preference is stored in localStorage with key: `dataworldpro-ui-theme`