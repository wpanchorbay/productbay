# ProductBay — AI Agent Guide

Welcome, AI Agent! This document is a technical quick-start guide to help you navigate, understand, and safely modify the ProductBay codebase. It captures core architectures, development commands, design patterns, coding standards, and specific guidelines for agent work.

---

## Project DNA

ProductBay is a high-performance WooCommerce product table plugin. It is built as a hybrid application:
- **Backend (PHP 7.4+)**: Integrates with WordPress core, registers Custom Post Types, and provides a REST API namespace (`productbay/v1`).
- **Frontend (React 18 + TypeScript)**: Powered by a Single Page Application (SPA) in the WordPress admin dashboard. Styled with **Tailwind CSS v4** scoped to `#productbay-root`, and state-managed by **Zustand**.
- **Data Persistence**: Tables are stored as a Custom Post Type (`productbay_table`), with configurations serialized under the post meta key `_productbay_config`. Settings are stored as a global option (`productbay_settings`).
- **Extensibility**: Hook-based structure allowing independent add-ons (such as **ProductBay Pro**) to inject features via actions/filters and SlotFills in React without modifying the free core.

---

## Directory Reference Map

```
productbay/
├── app/                  # PHP Backend Source Code
│   ├── Admin/            # WP menu registration, asset enqueuing
│   ├── Api/              # REST API controllers (CRUD, products, status, settings)
│   ├── Blocks/           # Gutenberg block assets & server-side render registration
│   ├── Core/             # Plugin bootstrapper & lifecycle hooks
│   ├── Data/             # Repositories (TableRepository)
│   ├── Frontend/         # [productbay] Shortcode, TableRenderer, AjaxRenderer
│   ├── Http/             # REST routing & Request wrappers
│   └── Utils/            # Helper & formatting utilities
├── src/                  # React / TypeScript Frontend Source Code
│   ├── blocks/           # Gutenberg block source code
│   ├── components/       # Reusable React components (Table, Settings, UI)
│   ├── context/          # React context providers (Toast)
│   ├── hooks/            # Custom hooks (e.g., URL parameters, copy-paste)
│   ├── layouts/          # Page layout components (AdminLayout)
│   ├── pages/            # Page components (Dashboard, Settings, Tables, Table Editor)
│   ├── store/            # Zustand state stores (tableStore, settingsStore)
│   ├── styles/           # Tailwind CSS directives
│   ├── types/            # TypeScript interface definitions
│   └── utils/            # Axios API clients & route utilities
├── blocks/               # Gutenberg Blocks frontend components
├── assets/               # Compiled build artifacts (JS, CSS, asset maps)
├── docs/                 # Documentation site source (VitePress)
├── scripts/              # Build, release, and demo environment utilities
└── graphify-out/         # Local knowledge graph of the codebase
```

---

## Core Development Workflows

To set up and run development tasks:

### 1. Install Dependencies
```bash
# Install JavaScript dependencies
bun install

# Install PHP dependencies
composer install
```

### 2. Start Development (Watch Mode)
```bash
# Run Webpack compile and Tailwind build in parallel watch mode
bun start
```

### 3. Build for Production
```bash
# Compiles optimized and minified assets into assets/ directory
bun run build
```

### 4. Create Release Package
```bash
# Compiles assets and builds a distribution-ready productbay.zip
bun run release
```

### 5. Internationalization (i18n)
```bash
# Extract translatable strings from PHP and JS into productbay.pot
bun run i18n:make-pot

# Convert .po files to JSON for client-side translations
bun run i18n:make-json
```

---

## Key Architectural Patterns & Constraints

### 1. Repository Pattern (PHP Backend)
Do not write raw SQL queries or direct `$wpdb` calls for table operations. Always use [TableRepository.php](file:///var/www/html/wp-content/plugins/productbay/app/Data/TableRepository.php):
```php
$repository = new \ProductBay\Data\TableRepository();
$table = $repository->find($table_id);
```

### 2. Extensibility Layer & Hooks
The backend is highly extensible. If implementing a feature that might be overridden or augmented by Pro modules, utilize the filter list:
- `productbay_query_args`: Customize WooCommerce product queries.
- `productbay_cell_output`: Customize how table cell data is rendered.
- `productbay_table_columns`: Alter table columns before frontend generation.

Refer to [.meta-worktree/notes/PRO_ARCHITECTURE.md](file:///var/www/html/wp-content/plugins/productbay/.meta-worktree/notes/PRO_ARCHITECTURE.md) for a complete list of 30+ extension hooks.

### 3. Global UI Component Sharing (SlotFill & Proxy)
To keep the bundle sizes small, the free plugin shares its React component library globally via the `window` object:
- Free exports components to `window.productbay.ui` in [src/index.tsx](file:///var/www/html/wp-content/plugins/productbay/src/index.tsx).
- Pro imports these components using proxy modules (e.g., `import { Button } from '@/components/ui'`).
- **Badge Guarding**: The table builder uses `<Slot>` components (e.g., `productbay-pro-options`) to let Pro inject controls dynamically. If Pro is active (`window.productbay.proActive`), UI elements are displayed natively; otherwise, disabled placeholders or `<ProBadge />` notices are rendered.

---

## Coding Standards & Quality Guidelines

### PHP
- **Linting**: Ensure code conforms to WordPress PHP Coding Standards. Run PHPCS locally:
  ```bash
  ./vendor/bin/phpcs
  ```
- **Security**: 
  - Verify Nonces on AJAX and REST API requests (e.g., using `check_ajax_referer` or REST controller permission callbacks).
  - Use capability checks. ProductBay uses the `productbay_admin_capability` filter (defaulting to `manage_options`).
  - Sanitize all input values: `sanitize_text_field()`, `sanitize_textarea_field()`, or `absint()`.
  - Escape all output parameters: `esc_html()`, `esc_attr()`, `esc_url()`, and `wp_kses_post()`.

### JavaScript & React
- **TypeScript**: Adhere to strict type definitions. Avoid using `any` types.
- **Styling**: Scopes are governed by Tailwind CSS v4. Standardize admin styles by prefixing customized wrapper elements, and ensure elements are properly nested inside the `#productbay-root` container to avoid bleeding classes.
- **i18n**: All UI text must be translatable via `@wordpress/i18n` (e.g., `__('Text', 'productbay')`).

---

## AI Agent Integration (Graphify & LLMS)

To assist you in reading and updating the architecture:

### 1. Graphify Knowledge Graph
This project includes a **Graphify** knowledge graph.
- **Lookup Location**: Visual reports are located in [graphify-out/](file:///var/www/html/wp-content/plugins/productbay/graphify-out/).
- **Read First**: Prior to addressing architectural questions, read [GRAPH_REPORT.md](file:///var/www/html/wp-content/plugins/productbay/graphify-out/GRAPH_REPORT.md) to inspect god nodes, import cycles, and community clusters.
- **Query Command**: Use the CLI subcommands to inspect cross-module structures:
  ```bash
  # Search the graph structure for a question
  graphify-out/.venv/bin/graphify query "How do columns render?"
  
  # Trace the shortest path between two modules
  graphify-out/.venv/bin/graphify path "TableRenderer" "AjaxRenderer"
  
  # Explain a specific code node
  graphify-out/.venv/bin/graphify explain "useTableStore"
  ```
- **Graph Updates**: After modifying code files, always run:
  ```bash
  graphify-out/.venv/bin/graphify update .
  ```
  This updates [graph.json](file:///var/www/html/wp-content/plugins/productbay/graphify-out/graph.json) and [GRAPH_REPORT.md](file:///var/www/html/wp-content/plugins/productbay/graphify-out/GRAPH_REPORT.md) (runs AST analysis locally, no API cost).

### 2. Compiled Documentation (LLMS)
A single aggregated developer documentation file exists for fast text ingestion:
- **Generation**: Created using [generate-llms.js](file:///var/www/html/wp-content/plugins/productbay/docs/generate-llms.js).
- **Outputs**:
  - [llms.txt](file:///var/www/html/wp-content/plugins/productbay/docs/public/llms.txt): Listing of all available page links.
  - [llms-full.txt](file:///var/www/html/wp-content/plugins/productbay/docs/public/llms-full.txt): Single-file text output containing the entire cleaned content of all documentation pages, free of presentational HTML and VitePress tags.
- Read this file if you need full feature-level or API-level specification without crawling individual markdown guides.
