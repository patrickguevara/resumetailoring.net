# Contributing to Resume Tailoring

Thank you for your interest in contributing! This guide will help you get started with the development workflow.

## Development Workflow

This project uses a **branch-based workflow** to ensure code quality and maintainability. All changes must go through pull requests - no direct pushes to `main` are allowed.

### Step 1: Create a Feature Branch

Create a descriptive branch for your work:

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/bug-description
# or
git checkout -b refactor/component-name
```

Branch naming conventions:
- `feature/` - New features or enhancements
- `fix/` - Bug fixes
- `refactor/` - Code refactoring
- `docs/` - Documentation updates
- `test/` - Test additions or improvements

### Step 2: Make Your Changes

Work on your feature, making commits as you go:

```bash
git add .
git commit -m "feat: add user authentication"
# or
git commit -m "fix: resolve login redirect issue"
```

Commit message prefixes:
- `feat:` - New feature
- `fix:` - Bug fix
- `refactor:` - Code refactoring
- `docs:` - Documentation changes
- `test:` - Test updates
- `chore:` - Maintenance tasks

### Step 3: Test Locally

Before pushing, test your changes thoroughly:

1. **Visit the local development site:**
   ```bash
   # Make sure your Laravel Herd environment is running
   # Visit: http://waltonfamilydentistry.com.test
   ```

2. **Test all affected features manually**

### Step 4: Run Quality Checks (REQUIRED)

**You MUST run all of these checks before pushing.** They will also run automatically in GitHub Actions, so running them locally saves time:

```bash
# 1. PHP formatting check
./vendor/bin/pint --test

# 2. JavaScript/Vue formatting check
npm run format:check

# 3. Linting check
npm run lint:check

# 4. Run test suite
php artisan test

# 5. Build verification
npm run build
```

#### Fixing Issues

If any checks fail:

```bash
# Fix PHP formatting automatically
./vendor/bin/pint

# Fix JS/Vue formatting automatically
npm run format

# Fix linting issues automatically (some may need manual fixes)
npm run lint
```

### Step 5: Push Your Branch

Once all quality checks pass:

```bash
git push -u origin feature/your-feature-name
```

### Step 6: Create a Pull Request

1. Go to the [repository on GitHub](https://github.com/patrickguevara/resumetailoring.net)
2. Click "Pull requests" → "New pull request"
3. Select your branch
4. Fill out the PR template (see below)
5. Click "Create pull request"

#### Pull Request Template

```markdown
## Description
[Describe what this PR does]

## Type of Change
- [ ] New feature
- [ ] Bug fix
- [ ] Refactoring
- [ ] Documentation update
- [ ] Test update

## Testing Steps
1. [Step 1]
2. [Step 2]
3. [Expected result]

## Screenshots (if applicable)
[Add screenshots here]

## Checklist
- [ ] All quality checks pass locally (Pint, formatting, linting, tests, build)
- [ ] I've tested the changes locally
- [ ] I've updated relevant documentation
- [ ] I've added/updated tests if needed
```

### Step 7: Wait for Review & CI Checks

GitHub Actions will automatically run:
- ✅ **Linter workflow** - Checks code formatting and linting
- ✅ **Tests workflow** - Runs the test suite

Both must pass before your PR can be merged.

### Step 8: Merge

Once approved and all checks pass, your PR will be merged into `main`.

## Common Issues & Solutions

### Case Sensitivity in Import Paths

⚠️ **IMPORTANT:** Import paths are case-sensitive in production builds but not in local development!

**Always use lowercase paths:**

```typescript
// ✅ CORRECT
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';

// ❌ WRONG (will break in production)
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button } from '@/Components/UI/button';
```

The correct case for imports:
- `@/components/` (lowercase)
- `@/layouts/` (lowercase)
- `@/composables/` (lowercase)
- `@/lib/` (lowercase)
- `@/pages/` (lowercase)

### Fixing Failed Quality Checks

If your PR fails CI checks:

1. Pull the latest changes from your branch
2. Run the failed check locally (see Step 4)
3. Fix the issues
4. Commit and push again

### Build Failures

If `npm run build` fails:

1. Clear the build cache: `rm -rf public/build`
2. Clear node_modules: `rm -rf node_modules && npm install`
3. Try building again: `npm run build`

## Development Environment

### Prerequisites

- PHP 8.2+
- Node.js 18+
- Composer 2.x
- Laravel Herd (for local development)

### Setup

```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Build assets
npm run build
```

### Running Development Server

With Laravel Herd, your site should automatically be available at:
- http://waltonfamilydentistry.com.test

To build assets during development:

```bash
# Watch for changes and rebuild
npm run dev
```

## Available Scripts

### PHP

```bash
./vendor/bin/pint              # Fix PHP formatting
./vendor/bin/pint --test       # Check PHP formatting
php artisan test               # Run tests
composer test                  # Run tests (alias)
```

### JavaScript/TypeScript

```bash
npm run dev                    # Development server (Vite)
npm run build                  # Production build
npm run format                 # Fix formatting (Prettier)
npm run format:check           # Check formatting
npm run lint                   # Fix linting issues (ESLint)
npm run lint:check             # Check linting
```

## Code Style Guidelines

### PHP

We use [Laravel Pint](https://laravel.com/docs/pint) for PHP code formatting, which follows Laravel's coding style.

Key conventions:
- Use strict types: `declare(strict_types=1);`
- Type hints for all parameters and return types
- Use single quotes for strings (unless interpolation is needed)

### JavaScript/TypeScript

We use [Prettier](https://prettier.io/) for formatting and [ESLint](https://eslint.org/) for linting.

Key conventions:
- Use TypeScript for type safety
- Use composition API in Vue components
- Prefer `const` over `let`
- Use arrow functions
- No unused variables
- Avoid duplicate keys in Vue components

### Vue Components

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';

// Props
const props = defineProps<{
    title: string;
}>();

// State
const count = ref(0);

// Computed
const doubled = computed(() => count.value * 2);
</script>

<template>
    <div>
        <h1>{{ props.title }}</h1>
        <p>Count: {{ count }}</p>
    </div>
</template>
```

## Questions?

If you have questions or run into issues:

1. Check existing issues on GitHub
2. Review this guide and the README
3. Open a new issue with details about your problem

---

**Remember:** Always create a feature branch, run quality checks, and create a PR. Never push directly to `main`!
