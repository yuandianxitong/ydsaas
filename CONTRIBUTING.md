# Contributing to YdAdmin SaaS

Thank you for your interest in contributing! This guide will help you get started.

## Development Setup

### Docker (recommended)

```bash
git clone https://github.com/yuandianxitong/ydsaas.git
cd ydsaas
make setup
```

### Local Development

```bash
git clone https://github.com/yuandianxitong/ydsaas.git
cd ydsaas/server
composer install
php think saas:install
php think saas:create-platform-admin
```

**Requirements:** PHP 8.4+, Node 20+, pnpm 9+, MySQL 8.0+, Redis 7+

## Project Structure

- `server/` — Backend (ThinkPHP 8)
- `platform/` — Platform superadmin UI (Vue 3 + Element Plus)
- `tenant/` — Tenant admin UI (Vue 3 + Element Plus)
- `pc/` — Public frontend (Nuxt 3)
- `uniapp/` — Mobile app (UniApp + Vue 3)
- `docker/` — Docker configuration

## Coding Standards

### Backend

- PSR-12 code style (php-cs-fixer)
- PHPStan static analysis
- Architecture: Controller → Service → Repository → Model

```bash
cd server
composer lint       # Check style
composer lint:fix   # Auto-fix
composer analyse    # Static analysis
composer test       # Run tests
```

### Frontend

- ESLint + Prettier
- TypeScript strict mode
- Vue 3 Composition API with `<script setup>`

```bash
cd platform  # or tenant/
pnpm lint        # Lint + fix
pnpm type-check  # Type checking
pnpm test        # Run tests
```

## Branch Strategy

- `main` — Stable release branch
- `develop` — Integration branch
- `feat/xxx` — Feature branches (from develop)
- `fix/xxx` — Bug fix branches (from develop)

## Commit Convention

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>
```

**Types:** `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`

**Examples:**
- `feat(saas): add tenant auto-renewal job`
- `fix(auth): prevent cross-scope token reuse`

## Pull Request Process

1. Fork the repository and create a branch from `develop`
2. Write tests for new functionality
3. Ensure all tests pass
4. Ensure linting passes
5. Submit PR with a clear description

## License

By contributing, you agree that your contributions will be licensed under the [Apache License 2.0](LICENSE).
