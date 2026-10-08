# Design Document

## Feature: laravel-bts-api

---

## Overview

The Laravel BTS API is a containerised REST API for the BTS.id recruitment coding test. It exposes two domains — Authentication and Product Management — secured with Laravel Sanctum (plain-text personal access tokens), documented with Swagger UI via `darkaonline/l5-swagger`, and delivered as a Docker Compose project.

The design keeps things as flat and simple as possible: controllers call Eloquent models directly, validation is done inline with `$request->validate()`, and responses are returned with `response()->json()`. No service layer, no FormRequest classes, no API Resource classes.

```
HTTP Request
    │
    ▼
Route (api.php)
    │
    ├─► CORS Middleware
    │
    ├─► Sanctum Auth Middleware (protected routes only)
    │
    ▼
Controller (validate + Eloquent + response()->json())
    │
    ▼
MySQL 8 Database
```

---

## Architecture

### Layer Summary

| Layer | Responsibility |
|---|---|
| **Routes** (`routes/api.php`) | HTTP verb + path → controller method; apply `auth:sanctum` to protected routes |
| **CORS Middleware** | `config/cors.php` — allow all origins, all methods, all headers |
| **Controllers** | Inline validation, direct Eloquent calls, `response()->json()` |
| **Models** (Eloquent) | ORM mapping, `$fillable`, `$casts` |
| **Database Migrations** | Schema for `users`, `products`, Sanctum `personal_access_tokens` |

### Container Architecture

```
┌─────────────────────────────────────────────────────────┐
│  Docker Host                                            │
│                                                         │
│  ┌──────────────────┐       ┌──────────────────────┐   │
│  │  app service     │       │  db service          │   │
│  │  PHP 8.3-FPM     │──────►│  MySQL 8.0           │   │
│  │  php artisan     │       │  Port: 3306 (internal│   │
│  │  serve           │       │  Volume: mysql_data   │   │
│  │  Port: 8000:8000 │       └──────────────────────┘   │
│  └──────────────────┘                                   │
└─────────────────────────────────────────────────────────┘
```

- `app` depends on `db`
- Entrypoint script runs `php artisan migrate --force` then starts `php artisan serve`
- No Redis, no queue worker, no cache driver beyond the default file driver

---

## Components

### 1. AuthController

**File:** `app/Http/Controllers/Api/AuthController.php`

```php
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    public function login(Request $request): JsonResponse
}
```

**register()**
- Inline validate: `username` required|string|unique:users, `password` required|string|min:8|confirmed
- Hash password, create User
- Return `response()->json(['id' => ..., 'username' => ..., 'created_at' => ...], 201)`

**login()**
- Inline validate: `username` required|string, `password` required|string
- `Auth::attempt()` — on failure return `response()->json(['message' => 'Invalid credentials.'], 401)`
- On success: `$user->createToken('auth-token')->plainTextToken` for `authentication_token`; create a second token `$user->createToken('refresh-token')->plainTextToken` for `refresh_token`
- Return `response()->json(['authentication_token' => ..., 'refresh_token' => ...], 200)`

### 2. ProductController

**File:** `app/Http/Controllers/Api/ProductController.php`

```php
class ProductController extends Controller
{
    public function index(Request $request): JsonResponse      // GET /api/products
    public function show(int $id): JsonResponse                // GET /api/products/{id}
    public function store(Request $request): JsonResponse      // POST /api/products
    public function update(Request $request, int $id): JsonResponse // PUT /api/products/{id}
    public function destroy(int $id): JsonResponse             // DELETE /api/products/{id}
}
```

**index()** — build query with optional `search`, `category`, `limit`/`page` filters; return JSON array (or paginated object with `meta`)

**show()** — `Product::find($id)` or `abort(404, 'Product not found.')`; return JSON

**store()** — inline validate; set `created_by`/`created_by_id`; `Product::create()`; return 201

**update()** — find or 404; inline validate (all fields optional); set `updated_by`/`updated_by_id`; `$product->update()`; return 200

**destroy()** — find or 404; `$product->delete()`; return `{'message': 'Product deleted successfully.'}`

### 3. Eloquent Models

#### User Model — `app/Models/User.php`

```php
use HasApiTokens, HasFactory;

protected $fillable = ['username', 'password'];
protected $hidden   = ['password'];
protected $casts    = ['password' => 'hashed'];
```

`HasApiTokens` is the Sanctum trait. No email field.

#### Product Model — `app/Models/Product.php`

```php
use HasFactory;

protected $fillable = [
    'title', 'price', 'description', 'category', 'images',
    'created_by', 'created_by_id', 'updated_by', 'updated_by_id',
];

protected $casts = [
    'images' => 'array',
    'price'  => 'float',
];
```

### 4. CORS Configuration

`config/cors.php`:

```php
return [
    'paths'            => ['api/*'],
    'allowed_methods'  => ['*'],
    'allowed_origins'  => ['*'],
    'allowed_headers'  => ['*'],
    'supports_credentials' => false,
];
```

Laravel's built-in `HandleCors` middleware handles `OPTIONS` preflight automatically (HTTP 204).

### 5. Sanctum Token Management

- `HasApiTokens` trait on the `User` model (Sanctum).
- No Passport, no OAuth2 tables, no `passport:keys`.
- Login creates two tokens: one for `authentication_token`, one for `refresh_token`.
- Protected routes use `auth:sanctum` middleware.
- Sanctum's `personal_access_tokens` table is created by `php artisan migrate` (migration ships with Sanctum).

### 6. Swagger / OpenAPI Documentation

Package: `darkaonline/l5-swagger`

OpenAPI 3.0 annotations in controller docblocks. Swagger UI served at `/api/documentation`.

`config/l5-swagger.php` key settings:
- `routes.api` → `/api/documentation`
- `generate_always` → `true` in non-production (simplifies dev)

A top-level `@OA\Info` and `@OA\SecurityScheme` (Bearer) annotation is placed in `AuthController`.

### 7. Exception Handling

`bootstrap/app.php` (Laravel 11) registers JSON responses for common exceptions:

| Exception | HTTP Code | Response |
|---|---|---|
| `ValidationException` | 422 | `{ "message": "...", "errors": { "field": ["msg"] } }` |
| `AuthenticationException` | 401 | `{ "message": "Unauthenticated." }` |
| `ModelNotFoundException` | 404 | `{ "message": "Resource not found." }` |
| Generic `Exception` | 500 | `{ "message": "Server Error." }` |

For 404s triggered by `abort(404, '...')`, the message from `abort()` is used directly.

---

## Data Models

### `users` table

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | |
| `username` | VARCHAR(255) UNIQUE NOT NULL | |
| `password` | VARCHAR(255) NOT NULL | Bcrypt hashed |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

### `products` table

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AUTO_INCREMENT | |
| `title` | VARCHAR(255) NOT NULL | |
| `price` | DECIMAL(15, 2) NOT NULL | |
| `description` | TEXT NULLABLE | |
| `category` | VARCHAR(255) NOT NULL | |
| `images` | JSON NOT NULL | Array of image URLs |
| `created_by` | VARCHAR(255) NOT NULL | Username of creator |
| `created_by_id` | BIGINT UNSIGNED NOT NULL | |
| `updated_by` | VARCHAR(255) NULLABLE | Username of last updater |
| `updated_by_id` | BIGINT UNSIGNED NULLABLE | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

### `personal_access_tokens` table (Sanctum — auto-created by migration)

| Column | Type |
|---|---|
| `id` | BIGINT UNSIGNED PK |
| `tokenable_type` | VARCHAR |
| `tokenable_id` | BIGINT UNSIGNED |
| `name` | VARCHAR |
| `token` | VARCHAR (hashed) |
| `abilities` | TEXT NULLABLE |
| `last_used_at` | TIMESTAMP NULLABLE |
| `expires_at` | TIMESTAMP NULLABLE |
| `created_at` / `updated_at` | TIMESTAMP |

---

## API Interface Contracts

### POST /api/auth/register

- **Request**: `{ "username": "string", "password": "string", "password_confirmation": "string" }`
- **Responses**:
  - `201` — `{ "id": int, "username": "string", "created_at": "datetime" }`
  - `422` — `{ "message": "string", "errors": { "field": ["error"] } }`

### POST /api/auth/login

- **Request**: `{ "username": "string", "password": "string" }`
- **Responses**:
  - `200` — `{ "authentication_token": "string", "refresh_token": "string" }`
  - `401` — `{ "message": "Invalid credentials." }`
  - `422` — `{ "message": "string", "errors": { "field": ["error"] } }`

### GET /api/products

- **Query Params**: `search` (string), `category` (string), `limit` (int), `page` (int)
- **Response 200** (no pagination): `[Product, ...]`
- **Response 200** (with `limit`/`page`): `{ "data": [Product, ...], "meta": { "current_page": int, "per_page": int, "total": int, "last_page": int } }`

### GET /api/products/{id}

- `200` — `Product`
- `404` — `{ "message": "Product not found." }`

### POST /api/products (auth required)

- **Request**: `{ "title": "string", "price": number, "description": "string?", "category": "string", "images": ["url", ...] }`
- `201` — `Product`
- `401` — `{ "message": "Unauthenticated." }`
- `422` — validation errors

### PUT /api/products/{id} (auth required)

- **Request**: same fields as POST, all optional
- `200` — `Product`
- `401` — `{ "message": "Unauthenticated." }`
- `404` — `{ "message": "Product not found." }`
- `422` — validation errors

### DELETE /api/products/{id} (auth required)

- `200` — `{ "message": "Product deleted successfully." }`
- `401` — `{ "message": "Unauthenticated." }`
- `404` — `{ "message": "Product not found." }`

### Product JSON Shape

```json
{
  "id": 1,
  "title": "Sample Product",
  "price": 49.99,
  "description": "A sample product description.",
  "category": "electronics",
  "images": ["https://example.com/img1.jpg"],
  "created_at": "2024-01-01T00:00:00.000000Z",
  "created_by": "johndoe",
  "created_by_id": 5,
  "updated_at": "2024-01-02T00:00:00.000000Z",
  "updated_by": "janedoe",
  "updated_by_id": 7
}
```

---

## Project File Structure

```
BTS/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           ├── AuthController.php
│   │           └── ProductController.php
│   └── Models/
│       ├── User.php
│       └── Product.php
├── bootstrap/
│   └── app.php          ← exception handler registered here (Laravel 11)
├── database/
│   └── migrations/
│       ├── xxxx_create_users_table.php
│       ├── xxxx_create_products_table.php
│       └── xxxx_create_personal_access_tokens_table.php (Sanctum)
├── routes/
│   └── api.php
├── config/
│   ├── cors.php
│   ├── auth.php
│   └── l5-swagger.php
├── docker/
│   └── entrypoint.sh
├── docker-compose.yml
├── Dockerfile
├── .env.example
└── README.md
```

No `app/Http/Requests/`, no `app/Http/Resources/`, no `app/Services/`.

---

## Correctness Properties

### Property 1: Registration creates user

*For any* valid unique `username` + matching `password`/`password_confirmation` ≥ 8 chars → HTTP 201 and user exists in DB.

**Validates: Requirement 4.2**

### Property 2: Registration validation rejection

*For any* payload missing a required field or with mismatched confirmation → HTTP 422 with field-level errors.

**Validates: Requirements 4.3, 4.4, 4.5**

### Property 3: Login token round-trip

*For any* registered user → login returns HTTP 200 with `authentication_token` and `refresh_token`; subsequent `Authorization: Bearer <authentication_token>` on protected endpoints returns non-401.

**Validates: Requirements 5.2, 11.2**

### Property 4: Login credential rejection

*For any* unknown username/password pair → HTTP 401.

**Validates: Requirement 5.3**

### Property 5: Product list completeness

*For any* product set with no filters → response length equals total record count.

**Validates: Requirements 6.1, 6.5**

### Property 6: Search filter correctness

*For any* keyword → every returned product contains the keyword in `title` or `description` (case-insensitive); no non-matching product appears.

**Validates: Requirement 6.2**

### Property 7: Category filter correctness

*For any* category value → every returned product matches the category; no other category appears.

**Validates: Requirement 6.3**

### Property 8: Pagination bounds

*For any* valid `limit`/`page` → at most `limit` items, `meta.total` equals full count, correct page window.

**Validates: Requirement 6.4**

### Property 9: Product CRUD audit fields

*For any* authenticated user + valid create payload → `created_by`/`created_by_id` match the token owner. *For any* authenticated update → `updated_by`/`updated_by_id` match the token owner.

**Validates: Requirements 8.3, 9.3**

### Property 10: Not-found 404

*For any* non-existent product ID on GET, PUT (authenticated), or DELETE (authenticated) → HTTP 404 with error message.

**Validates: Requirements 7.3, 9.4, 10.3**

### Property 11: Authentication enforcement

*For any* request to POST/PUT/DELETE products without a valid Bearer token → HTTP 401.

**Validates: Requirements 8.5, 9.5, 10.4**
