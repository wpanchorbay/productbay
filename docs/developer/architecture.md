# Architecture

This page provides a technical overview of ProductBay's internal architecture. It's intended for developers who want to understand how the plugin is built or contribute to it.

## High-Level Architecture

ProductBay follows a **Hybrid Architecture** pattern:

```
┌──────────────────────────────────────────────────────────┐
│                    WordPress Core                        │
├────────────────────────┬─────────────────────────────────┤
│     Admin (React SPA)  │       Frontend (PHP Render)     │
│                        │                                 │
│  React 18 + TypeScript │  TableRenderer + AjaxRenderer   │
│  Tailwind CSS v4       │  Pure PHP + minimal jQuery      │
│  Zustand State         │  Scoped CSS per instance        │
│  React Router (Hash)   │  SEO-friendly HTML              │
│                        │                                 │
│  ┌──────────────────┐  │  ┌───────────────────────────┐  │
│  │   REST API       │◄─┼──│   Shortcode System        │  │
│  │  /productbay/v1  │  │  │   [productbay id="X"]     │  │
│  └────────┬─────────┘  │  └───────────────────────────┘  │
│           │            │                                 │
├───────────┼────────────┴─────────────────────────────────┤
│           ▼                                              │
│  ┌─────────────────┐  ┌──────────────────────────────┐   │
│  │  Controllers    │  │  TableRepository             │   │
│  │  (API Layer)    │──│  (Data Layer / CPT)          │   │
│  └─────────────────┘  └──────────────────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

## Backend (PHP)

### Directory Structure

```
app/
├── Admin/           # WP admin page registration, asset enqueuing
├── Api/             # REST API controllers
│   ├── ApiController.php       # Base controller
│   ├── TablesController.php    # Table CRUD operations
│   ├── ProductsController.php  # Product search & categories
│   ├── SettingsController.php  # Plugin settings management
│   ├── SystemController.php    # System status & onboarding
│   └── PreviewController.php   # Live preview rendering
├── Core/            # Plugin bootstrapper, activation/deactivation
├── Data/            # Data repositories (TableRepository)
├── Frontend/        # Shortcode, TableRenderer, AjaxRenderer
├── Http/            # Router (REST route registration), Request wrapper
└── Utils/           # Utility classes
```

### Key Classes

| Class | Responsibility |
|-------|---------------|
| `Core\Plugin` | Main plugin bootstrapper — initializes all components |
| `Http\Router` | Registers all REST API routes with permission checks |
| `Http\Request` | Wraps `$_REQUEST` with sanitization helpers |
| `Data\TableRepository` | CRUD operations for the `productbay_table` custom post type |
| `Frontend\Shortcode` | Registers `[productbay]` shortcode and triggers rendering |
| `Frontend\TableRenderer` | Generates the full HTML table from table config |
| `Frontend\AjaxRenderer` | Handles AJAX requests (search, filter, pagination, add-to-cart) |

### Data Storage
- **Tables** are stored as a custom post type (`productbay_table`)
- **Table configurations** are stored across four post meta keys (`_productbay_source`, `_productbay_columns`, `_productbay_settings`, `_productbay_style`); the legacy single key `_productbay_config` is retired and blanked on every save
- **Plugin settings** are stored in `wp_options` (`productbay_settings`)

## Frontend (React/TypeScript)

### Directory Structure

```
src/
├── components/    # Reusable UI components
├── context/       # React context providers
├── hooks/         # Custom React hooks
├── layouts/       # Page layout wrappers
├── pages/         # Main page components
│   ├── Dashboard.tsx    # Welcome/onboarding dashboard
│   ├── Tables.tsx       # Product Tables list view
│   ├── Table.tsx        # Table creation/edit wizard
│   └── Settings.tsx     # Plugin settings page
├── store/         # Zustand state stores
├── styles/        # Tailwind CSS entry point
├── types/         # TypeScript type definitions
└── utils/         # Utility functions
```

### Tech Stack

| Technology | Purpose |
|-----------|---------|
| React 18 | UI library (via `@wordpress/element`) |
| TypeScript | Type-safe JavaScript (ES2020, strict mode) |
| Tailwind CSS v4 | Utility-first styling, scoped to `#productbay-root` |
| Zustand | Lightweight state management |
| React Router DOM v7 | SPA routing (HashRouter) |
| Lucide React | Icon library |
| dnd-kit | Drag-and-drop for column reordering |

## Build System

| Tool | Purpose |
|------|---------|
| Webpack | Module bundler (extends `@wordpress/scripts`) |
| `@wordpress/scripts` | WP build toolchain (JS/TS transpilation) |
| `@tailwindcss/cli` v4 | CSS compilation |
| Bun | Package manager and script runner |
| Composer | PHP dependency management (PSR-4 autoloading) |
