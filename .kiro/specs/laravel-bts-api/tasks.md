# Implementation Plan: laravel-bts-api

## Overview

Flat, direct implementation of the Laravel BTS API recruitment test. No service layer, no FormRequest classes, no API Resource classes. Controllers validate inline with `$request->validate()` and return `response()->json()` directly. Auth is Laravel Sanctum (ships with Laravel). No rate limiting, no caching layer.

Tasks are ordered: Docker scaffold → migrations/models → auth → product CRUD → routes → CORS/errors → Swagger → README.

---

## Tasks

- [x] 1. Scaffold Laravel project and Docker infrastructure
  - [x] 1.1 Create a new Laravel project in the repository root
    - Run `composer create-project laravel/laravel .` to initialise Laravel in `d:\projects\BTS`
    - _Requirements: 1.1_

  - [x] 1.2 Write `Dockerfile` and `docker-compose.yml`
    - `Dockerfile`: base `php:8.3-fpm`, install PHP extensions (`pdo_mysql`, `mbstring`, `xml`, `zip`, `bcmath`), install Composer, copy app source, set `CMD` to run `docker/entrypoint.sh`
    - `docker-compose.yml`: `app` service (build, ports `8000:8000`, `depends_on: db`, env from `.env`) and `db` service (`mysql:8.0`, volume `mysql_data`, port 3306 internal only)
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [x] 1.3 Write `docker/entrypoint.sh`
    - Wait for MySQL, run `php artisan migrate --force`, then `php artisan serve --host=0.0.0.0 --port=8000`
    - No Passport steps needed — Sanctum migrates automatically
    - _Requirements: 1.3, 1.4_

  - [x] 1.4 Write `.env.example`
    - Include `APP_*`, `DB_*` (pointing to the `db` service), `SANCTUM_STATEFUL_DOMAINS` placeholder
    - _Requirements: 1.1_

- [x] 2. Configure database migrations and Eloquent models
  - [x] 2.1 Create `users` migration
    - Schema: `id` BIGINT PK, `username` VARCHAR(255) UNIQUE NOT NULL, `password` VARCHAR(255) NOT NULL, timestamps
    - Remove default `email`/`email_verified_at`/`remember_token` columns
    - _Requirements: 4.2, 4.5_

  - [x] 2.2 Create `products` migration
    - Schema: `id` BIGINT PK, `title` VARCHAR(255), `price` DECIMAL(15,2), `description` TEXT NULLABLE, `category` VARCHAR(255), `images` JSON NOT NULL, `created_by` VARCHAR(255), `created_by_id` BIGINT UNSIGNED, `updated_by` VARCHAR(255) NULLABLE, `updated_by_id` BIGINT UNSIGNED NULLABLE, timestamps
    - _Requirements: 8.3, 9.3_

  - [x] 2.3 Write `User` Eloquent model
    - Traits: `HasApiTokens` (Sanctum), `HasFactory`
    - `$fillable = ['username', 'password']`; `$hidden = ['password']`; `$casts = ['password' => 'hashed']`
    - Remove email-related methods from default scaffold
    - _Requirements: 4.2, 11.1_

  - [x] 2.4 Write `Product` Eloquent model
    - `HasFactory` trait
    - `$fillable` includes all product columns
    - `$casts = ['images' => 'array', 'price' => 'float']`
    - _Requirements: 8.2, 9.2, 10.2_

- [x] 3. Install and configure Laravel Sanctum
  - [x] 3.1 Publish Sanctum config and migration
    - Sanctum ships with Laravel — run `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` to publish config if needed
    - Confirm `personal_access_tokens` migration exists (it ships with Sanctum automatically in Laravel 11)
    - In `config/auth.php`, set the `api` guard driver to `sanctum`:
      ```php
      'api' => ['driver' => 'sanctum', 'provider' => 'users'],
      ```
    - _Requirements: 11.1_

- [x] 4. Implement Authentication (Register & Login)
  - [x] 4.1 Implement `AuthController::register()`
    - File: `app/Http/Controllers/Api/AuthController.php`
    - Inline validate: `username` required|string|max:255|unique:users, `password` required|string|min:8|confirmed
    - `User::create(['username' => ..., 'password' => Hash::make(...)])`
    - Return `response()->json(['id' => $user->id, 'username' => $user->username, 'created_at' => $user->created_at], 201)`
    - Add `@OA\Post` annotation for register
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

  - [x] 4.2 Implement `AuthController::login()`
    - Inline validate: `username` required|string, `password` required|string
    - `Auth::attempt(['username' => ..., 'password' => ...])` — on failure return JSON 401
    - On success: `$user = Auth::user(); $authToken = $user->createToken('auth-token')->plainTextToken; $refreshToken = $user->createToken('refresh-token')->plainTextToken;`
    - Return `response()->json(['authentication_token' => $authToken, 'refresh_token' => $refreshToken], 200)`
    - Add `@OA\Post` annotation for login; add `@OA\Info` and `@OA\SecurityScheme` (bearerAuth) at the top of the file
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

- [x] 5. Implement Product CRUD
  - [x] 5.1 Implement `ProductController::index()`
    - File: `app/Http/Controllers/Api/ProductController.php`
    - Start with `$query = Product::query()`
    - If `search`: `$query->where(fn($q) => $q->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"))`
    - If `category`: `$query->whereRaw('LOWER(category) = ?', [strtolower($category)])`
    - If `limit` + `page`: use Laravel's `paginate($limit, ['*'], 'page', $page)`; return `{ "data": [...], "meta": { "current_page", "per_page", "total", "last_page" } }`
    - Otherwise: return `response()->json($query->get())`
    - Add `@OA\Get` annotation
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

  - [x] 5.2 Implement `ProductController::show()`
    - `$product = Product::find($id) ?? abort(404, 'Product not found.')`
    - Return `response()->json($product)`
    - Add `@OA\Get` annotation
    - _Requirements: 7.1, 7.2, 7.3_

  - [x] 5.3 Implement `ProductController::store()`
    - Inline validate: `title` required|string, `price` required|numeric|min:0, `category` required|string, `images` required|array|min:1, `images.*` string, `description` nullable|string
    - `$product = Product::create(array_merge($validated, ['created_by' => $request->user()->username, 'created_by_id' => $request->user()->id]))`
    - Return `response()->json($product, 201)`
    - Add `@OA\Post` annotation
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [x] 5.4 Implement `ProductController::update()`
    - `$product = Product::find($id) ?? abort(404, 'Product not found.')`
    - Inline validate (all fields optional): same rules as store but `sometimes`
    - `$product->update(array_merge($validated, ['updated_by' => $request->user()->username, 'updated_by_id' => $request->user()->id]))`
    - Return `response()->json($product)`
    - Add `@OA\Put` annotation
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [x] 5.5 Implement `ProductController::destroy()`
    - `$product = Product::find($id) ?? abort(404, 'Product not found.')`
    - `$product->delete()`
    - Return `response()->json(['message' => 'Product deleted successfully.'])`
    - Add `@OA\Delete` annotation
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

- [ ] 6. Wire routes in `routes/api.php`
  - [~] 6.1 Define all API routes
    - Auth group (no middleware): `POST /api/auth/register` → `AuthController@register`, `POST /api/auth/login` → `AuthController@login`
    - Products public group (no middleware): `GET /api/products` → `index`, `GET /api/products/{id}` → `show`
    - Products protected group (`auth:sanctum`): `POST /api/products` → `store`, `PUT /api/products/{id}` → `update`, `DELETE /api/products/{id}` → `destroy`
    - _Requirements: 4.1, 5.1, 6.1, 7.1, 8.1, 9.1, 10.1_

- [ ] 7. Configure CORS and exception handling
  - [~] 7.1 Configure `config/cors.php`
    - `paths = ['api/*']`, `allowed_methods = ['*']`, `allowed_origins = ['*']`, `allowed_headers = ['*']`, `supports_credentials = false`
    - Confirm `HandleCors` is in the global middleware stack (it is by default in Laravel 11's `bootstrap/app.php`)
    - _Requirements: 2.1, 2.2, 2.3_

  - [~] 7.2 Customise exception handler in `bootstrap/app.php`
    - Use `$exceptions->render()` to return JSON for:
      - `ValidationException` → 422 with `{ message, errors }`
      - `AuthenticationException` → 401 with `{ message: 'Unauthenticated.' }`
      - `ModelNotFoundException` → 404 with `{ message: 'Resource not found.' }`
      - Generic exceptions → 500 with `{ message: 'Server Error.' }`
    - `abort(404, '...')` raises `HttpException` — ensure that is also handled as JSON
    - _Requirements: 4.3, 5.3, 7.3, 8.4, 9.4, 10.3_

- [ ] 8. Add Swagger configuration and generate docs
  - [~] 8.1 Install `darkaonline/l5-swagger` and publish config
    - `composer require darkaonline/l5-swagger`
    - `php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"`
    - In `config/l5-swagger.php`: set `api.title`, `routes.api = 'api/documentation'`, `generate_always = true` for non-production
    - _Requirements: 3.1, 3.4_

  - [~] 8.2 Verify all controller annotations are complete
    - Confirm `@OA\Info`, `@OA\SecurityScheme` (bearerAuth) exist in `AuthController`
    - Confirm all 7 endpoints have `@OA\Get`/`@OA\Post`/`@OA\Put`/`@OA\Delete` blocks with request bodies, query params, and all response codes (200/201, 401, 404, 422)
    - Run `php artisan l5-swagger:generate` and confirm no errors
    - _Requirements: 3.2, 3.3, 3.4_

- [ ] 9. Write `README.md`
  - [~] 9.1 Create `README.md`
    - Sections: prerequisites (Docker, Docker Compose), quick start (`cp .env.example .env && docker-compose up --build`), environment variables, API endpoint reference table, example `curl` commands for all endpoints, link to Swagger UI at `http://localhost:8000/api/documentation`
    - _Requirements: 1.3_

---

## Notes

- No rate limiting tasks — the test marks it as Optional.
- No caching tasks — the test marks it as Optional.
- No property-based testing — keep the test suite minimal (Laravel's built-in feature tests if desired).
- No FormRequest classes, no API Resource classes, no service layer.
- Sanctum ships with Laravel 11 — no separate `composer require` needed; just publish config.
- `auth:sanctum` guard is configured in `config/auth.php` by pointing the `api` driver to `sanctum`.
- The `personal_access_tokens` table is created by Sanctum's migration which runs with `php artisan migrate`.
- Two tokens are created on login (one for `authentication_token`, one for `refresh_token`) — this is the simplest approach that satisfies the response contract.
- Laravel 11 registers global middleware and exception handling in `bootstrap/app.php`; if the scaffolded version is Laravel 10, use `app/Http/Kernel.php` and `app/Exceptions/Handler.php` instead.

---

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "1.3", "1.4"] },
    { "id": 2, "tasks": ["2.1", "2.2"] },
    { "id": 3, "tasks": ["2.3", "2.4", "3.1"] },
    { "id": 4, "tasks": ["4.1", "4.2"] },
    { "id": 5, "tasks": ["5.1", "5.2", "5.3", "5.4", "5.5"] },
    { "id": 6, "tasks": ["6.1"] },
    { "id": 7, "tasks": ["7.1", "7.2"] },
    { "id": 8, "tasks": ["8.1"] },
    { "id": 9, "tasks": ["8.2", "9.1"] }
  ]
}
```
