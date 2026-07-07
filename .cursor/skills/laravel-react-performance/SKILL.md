---
name: laravel-react-performance
description: Best practices for optimizing Laravel + React (Inertia) applications for performance, scalability, and maintainability.
---

# Laravel + React Performance Skill

You are an expert Laravel, React, Inertia.js, and TypeScript engineer.

Every code suggestion should prioritize:

- Performance
- Scalability
- Readability
- Maintainability
- Low database usage
- Minimal network payload
- Excellent UX

---

# General Principles

Always:

- Minimize database queries.
- Minimize API payloads.
- Avoid unnecessary React rerenders.
- Lazy load expensive resources.
- Cache expensive operations.
- Paginate large datasets.
- Prefer async processing.
- Write production-ready code.

Never optimize prematurely, but never introduce obvious performance issues.

---

# Laravel Rules

## Database

Always:

- Use eager loading.
- Avoid N+1 queries.
- Select only required columns.
- Add indexes for searchable fields.
- Prefer cursor pagination for very large tables.
- Use exists() instead of count() when checking existence.
- Prefer chunk() or lazy() for processing large datasets.

Good

```php
Event::query()
    ->select(['id', 'title', 'status'])
    ->with([
        'tickets:id,event_id,name,price'
    ])
    ->paginate();
```

Bad

```php
Event::all();
```

Never use:

```php
Model::all()
```

unless explicitly requested.

---

## Controllers

Controllers should remain thin.

Business logic belongs in:

- Actions
- Services
- Jobs

Controllers should only:

- Validate
- Authorize
- Call services
- Return responses

---

## Queries

Avoid

```php
foreach ($events as $event) {
    $event->tickets;
}
```

Use

```php
Event::with('tickets')
```

---

## Caching

Use Cache::remember for:

- Dashboard statistics
- Event lists
- Settings
- Configuration
- Frequently viewed pages

Never cache user-specific data globally.

---

## Queues

Always recommend queues for:

- Email
- SMS
- Notifications
- PDF generation
- QR generation
- Image processing
- Imports
- Exports

Never execute these during HTTP requests if they can be deferred.

---

## Validation

Prefer Form Requests.

Never place validation logic directly in services.

---

## Authorization

Prefer Policies or Gates.

Never duplicate authorization logic.

---

## API Resources

Always use API Resources for APIs.

Avoid returning entire Eloquent models.

---

## Inertia

Keep props minimal.

Prefer:

```php
return Inertia::render('Events/Index', [
    'events' => EventResource::collection($events),
]);
```

Never send entire models if only a few fields are needed.

---

## Deferred Props

Recommend deferred props for:

- Statistics
- Charts
- Reports
- Expensive queries

---

## Partial Reloads

Prefer:

```js
router.reload({
    only: ['events'],
});
```

instead of reloading everything.

---

# React Rules

Use TypeScript.

Prefer functional components.

---

## Memoization

Recommend:

- React.memo
- useMemo
- useCallback

only when beneficial.

Avoid unnecessary memoization.

---

## Lists

Never render thousands of elements directly.

Recommend virtualization using:

- TanStack Virtual
- react-window

---

## State

Keep state local.

Avoid unnecessary global state.

Derived state should use useMemo.

---

## Components

Split large components.

Maximum recommendation:

300-500 lines per component.

Extract:

- Hooks
- Components
- Utilities

---

## Lazy Loading

Recommend

```tsx
const Reports = lazy(() => import('./Reports'));
```

for heavy pages.

---

## Forms

Prefer:

- React Hook Form
- Zod

Avoid excessive controlled inputs where unnecessary.

---

## Assets

Recommend:

- WebP
- AVIF
- lazy loading

---

# Tables

Always paginate.

Never load entire datasets.

Recommend server-side filtering.

Recommend server-side sorting.

---

# Search

Use debouncing.

Recommend:

300ms

for search inputs.

---

# Dashboard

Recommend:

- Deferred props
- Cached statistics
- Lazy charts

---

# Ticketing Systems

Special rules:

Seat maps:

- Load seats on demand.
- Do not preload every venue.
- Cache static seat layouts.
- Fetch seat availability separately.
- Use optimistic updates when reserving seats.

Orders:

- Paginate.
- Filter server-side.
- Queue ticket generation.
- Queue emails.
- Queue PDF generation.

Events:

- Cache published events.
- Select only required columns.

QR Codes:

Generate asynchronously whenever possible.

---

# File Uploads

Always:

- Queue image optimization.
- Validate mime types.
- Store originals.
- Generate thumbnails asynchronously.

---

# SQL

Prefer EXISTS over COUNT.

Good

```sql
SELECT EXISTS(
    SELECT 1
    FROM orders
    WHERE id = ?
)
```

Avoid

```sql
SELECT COUNT(*)
```

for existence checks.

---

# Production

Recommend:

```
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
npm run build
```

---

# Performance Checklist

Before finishing any implementation, verify:

- No N+1 queries
- Minimal selected columns
- Pagination used
- Proper indexes recommended
- No unnecessary rerenders
- Lazy loading considered
- Expensive operations queued
- Cache opportunities identified
- Thin controllers
- Services/Actions used appropriately
- TypeScript types defined
- Production-ready code
- Scalable architecture

If any item is missing, recommend improvements before completing the solution.
