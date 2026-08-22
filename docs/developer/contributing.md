# Contributing

Thank you for your interest in contributing to ProductBay! This guide will help you set up a local development environment.

## Prerequisites

| Tool | Version | Purpose |
|------|---------|---------|
| **Node.js** | 18+ | JavaScript runtime |
| **Bun** | Latest | Package manager & script runner |
| **Composer** | 2+ | PHP dependency manager |
| **PHP** | 7.4+ (8.0+ recommended) | Backend language |
| **WordPress** | 6.8+ | CMS platform |
| **WooCommerce** | 6.1+ | E-commerce dependency |

You'll also need a local WordPress development environment such as XAMPP, LAMP, LocalWP, or similar.

## Setup

### 1. Clone the Repository

```bash
cd wp-content/plugins
git clone https://github.com/wpanchorbay/productbay.git
cd productbay
```

### 2. Install Dependencies

Install JavaScript dependencies:
```bash
bun install
```

Install PHP dependencies:
```bash
composer install
```

### 3. Configure Environment

Copy the example environment file:
```bash
cp .env.example .env
```

Edit `.env` and set the appropriate values for your local setup.

### 4. Start Development

```bash
bun start
```

This runs both Webpack (for JS/TS compilation) and Tailwind CLI (for CSS) in parallel watch mode. Changes hot-reload automatically.

### 5. Build for Production

```bash
bun run build
```

This creates optimized, minified assets in the `assets/` directory.

## Project Structure

```
productbay/
├── app/          # PHP backend (Admin, Api, Core, Data, Frontend, Http)
├── src/          # React/TypeScript frontend source
├── assets/       # Compiled JS/CSS output
├── languages/    # Translation files
├── scripts/      # Build helper scripts
├── docs/         # This documentation site (VitePress)
├── vendor/       # Composer dependencies
└── node_modules/ # JavaScript dependencies
```

## Coding Standards

### PHP
- Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Run PHPCS: `./vendor/bin/phpcs`
- Use `snake_case` for functions/variables, `PascalCase` for classes
- Add PHPDoc blocks to all classes and methods
- Sanitize all inputs, escape all outputs

### JavaScript / TypeScript
- TypeScript strict mode (ES2020)
- Use `@wordpress/i18n` for translatable strings
- Path alias: `@/*` → `src/*`
- Use CVA + clsx + tailwind-merge for dynamic class names

### CSS
- Tailwind CSS v4 with utility-first approach
- All styles scoped to `#productbay-root`
- Frontend table classes prefixed with `productbay-`

## Internationalization (i18n)

All user-facing strings must be translatable:

**PHP:**
```php
__( 'Text', 'productbay' )   // Return translated string
_e( 'Text', 'productbay' )   // Echo translated string
```

**React/TypeScript:**
```typescript
import { __ } from '@wordpress/i18n';
const label = __( 'Text', 'productbay' );
```

Generate translation files:
```bash
bun run i18n:make-pot     # Extract strings to .pot
bun run i18n:make-json    # Convert .po to .json for React
```

## Reporting Issues

Found a bug or have a suggestion? Please open an issue on the [GitHub Issue Tracker](https://github.com/wpanchorbay/productbay/issues).

When reporting, include:
1. A clear description of the problem
2. Steps to reproduce
3. Screenshots or error logs (if applicable)
4. Your WordPress, WooCommerce, and PHP versions

## Contact Developers

ProductBay is developed by [WPAnchorBay](https://wpanchorbay.com). If you need help or have questions, please [contact us](https://wpanchorbay.com/contact-us/) or directly email us at [support@wpanchorbay.com](mailto:[support@wpanchorbay.com]). If you need urgent help, reach out to the lead developer [Forhad Khan](https://forhadkhan.com).