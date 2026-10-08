# Requirements Document

## Introduction

This document defines the requirements for a Laravel REST API built for the BTS.id recruitment coding test. The API provides two domains: Product Management and Authentication. It runs in Docker Compose (app + MySQL 8), uses Laravel Sanctum for token-based auth, is documented through Swagger UI at `/api/documentation`, and is delivered as a public GitHub repository.

## Glossary

- **API**: The Laravel REST API application served at the base URL.
- **Auth Service**: The subsystem responsible for user registration and login at `/api/auth`.
- **Product Service**: The subsystem responsible for CRUD operations on products at `/api/products`.
- **Client**: An HTTP consumer calling the API.
- **Authenticated Client**: A Client that supplies a valid Bearer token in the `Authorization` header.
- **Authentication Token**: A Laravel Sanctum plain-text personal access token issued on login, returned in the `authentication_token` field.
- **Refresh Token**: A second Sanctum token issued alongside the Authentication Token, returned in the `refresh_token` field.
- **Product**: A resource with fields: `id`, `title`, `price`, `description`, `category`, `images` (array), `created_at`, `created_by`, `created_by_id`, `updated_at`, `updated_by`, `updated_by_id`.
- **User**: A registered entity with `username` and `password`.
- **Swagger UI**: The API documentation interface served at `/api/documentation` via `darkaonline/l5-swagger`.
- **Docker Compose**: The container orchestration configuration defining the `app` and `db` services.

---

## Requirements

### Requirement 1 — Project Infrastructure

**User Story:** As a developer evaluating this submission, I want the application to run entirely with Docker Compose so that I can reproduce the environment without manual setup.

#### Acceptance Criteria

1. THE Docker Compose file SHALL define an `app` service running the Laravel application and a `db` service running MySQL 8.
2. THE `db` service SHALL use MySQL 8 as its database engine.
3. WHEN the command `docker-compose up` is executed from the project root, THE Docker Compose file SHALL start both the `app` service and the `db` service without requiring additional manual configuration steps.
4. THE `app` service SHALL depend on the `db` service so that the application container does not start before the database container is ready.
5. THE API SHALL be accessible on a host port exposed by the `app` service after `docker-compose up` completes.

---

### Requirement 2 — CORS

**User Story:** As a front-end developer consuming the API from any domain, I want CORS to be open so that browser clients are not blocked by origin policies.

#### Acceptance Criteria

1. THE API SHALL include Laravel's built-in CORS middleware applied to all `/api/*` routes.
2. WHEN a Client sends a request with any `Origin` header value, THE API SHALL respond with an `Access-Control-Allow-Origin` header that permits that origin.
3. WHEN a Client sends a CORS preflight (`OPTIONS`) request to any `/api/*` endpoint, THE API SHALL respond with HTTP 204 and the appropriate CORS headers.

---

### Requirement 3 — API Documentation

**User Story:** As a developer reviewing the API, I want interactive Swagger UI documentation so that I can explore and test all endpoints without a separate HTTP client.

#### Acceptance Criteria

1. THE API SHALL serve Swagger UI at the path `/api/documentation` using the `darkaonline/l5-swagger` package.
2. THE Swagger UI SHALL document all endpoints in the `/api/auth` and `/api/products` groups.
3. THE Swagger documentation SHALL include request body schemas, query parameter descriptions, and response schemas for every endpoint.
4. WHEN a Client navigates to `/api/documentation`, THE API SHALL return an HTTP 200 response with the Swagger UI HTML page.

---

### Requirement 4 — User Registration

**User Story:** As a new user, I want to register an account so that I can authenticate and manage products.

#### Acceptance Criteria

1. THE Auth Service SHALL expose a `POST /api/auth/register` endpoint.
2. WHEN a Client sends a valid registration request with `username`, `password`, and `password_confirmation`, THE Auth Service SHALL create a new User record and return HTTP 201 with the created user's data.
3. IF a registration request omits `username`, `password`, or `password_confirmation`, THEN THE Auth Service SHALL return HTTP 422 with a JSON body describing each validation error.
4. IF a registration request supplies a `password` value that does not match `password_confirmation`, THEN THE Auth Service SHALL return HTTP 422 with a JSON body describing the mismatch.
5. IF a registration request supplies a `username` that already exists, THEN THE Auth Service SHALL return HTTP 422 with a JSON body stating the username is taken.

---

### Requirement 5 — User Login

**User Story:** As a registered user, I want to log in with my credentials so that I receive tokens to authenticate further requests.

#### Acceptance Criteria

1. THE Auth Service SHALL expose a `POST /api/auth/login` endpoint.
2. WHEN a Client sends a valid login request with a matching `username` and `password`, THE Auth Service SHALL return HTTP 200 with a JSON body containing `authentication_token` and `refresh_token`.
3. IF a login request supplies an incorrect `username` or `password`, THEN THE Auth Service SHALL return HTTP 401 with a JSON body describing the authentication failure.
4. IF a login request omits `username` or `password`, THEN THE Auth Service SHALL return HTTP 422 with a JSON body describing each missing field.

---

### Requirement 6 — List Products

**User Story:** As a Client, I want to retrieve a list of all products with optional filtering and pagination so that I can browse or search the product catalogue.

#### Acceptance Criteria

1. THE Product Service SHALL expose a `GET /api/products` endpoint that returns HTTP 200 with a JSON array of all Product records.
2. WHEN a Client includes the query parameter `search=<keyword>`, THE Product Service SHALL return only Products whose `title` or `description` contains the keyword (case-insensitive).
3. WHEN a Client includes the query parameter `category=<value>`, THE Product Service SHALL return only Products whose `category` matches the provided value (case-insensitive).
4. WHEN a Client includes both `limit=<n>` and `page=<p>` query parameters, THE Product Service SHALL return a paginated response containing at most `n` Products for page `p`, along with pagination metadata.
5. WHEN a Client includes no query parameters, THE Product Service SHALL return all Products without filtering or pagination constraints.
6. WHEN a `GET /api/products` request matches no Products, THE Product Service SHALL return HTTP 200 with an empty JSON array.

---

### Requirement 7 — Get Single Product

**User Story:** As a Client, I want to retrieve a single product by its ID so that I can view its full details.

#### Acceptance Criteria

1. THE Product Service SHALL expose a `GET /api/products/{id}` endpoint.
2. WHEN a Client requests a Product by an existing `id`, THE Product Service SHALL return HTTP 200 with a JSON object containing all Product fields.
3. IF a Client requests a Product by an `id` that does not exist, THEN THE Product Service SHALL return HTTP 404 with a JSON body containing an error message.

---

### Requirement 8 — Create Product

**User Story:** As an Authenticated Client, I want to create a new product so that it appears in the product catalogue.

#### Acceptance Criteria

1. THE Product Service SHALL expose a `POST /api/products` endpoint.
2. WHEN an Authenticated Client sends a valid create request with `title`, `price`, `category`, and `images` (containing at least one item), THE Product Service SHALL persist the Product and return HTTP 201 with the created Product's JSON representation.
3. THE Product Service SHALL set `created_by` to the authenticated User's `username` and `created_by_id` to the authenticated User's `id` when creating a Product.
4. IF a create request omits `title`, `price`, `category`, or supplies an empty `images` array, THEN THE Product Service SHALL return HTTP 422 with a JSON body describing each validation error.
5. IF an unauthenticated Client sends a `POST /api/products` request, THEN THE Product Service SHALL return HTTP 401.

---

### Requirement 9 — Update Product

**User Story:** As an Authenticated Client, I want to update an existing product so that its information stays current.

#### Acceptance Criteria

1. THE Product Service SHALL expose a `PUT /api/products/{id}` endpoint.
2. WHEN an Authenticated Client sends a valid update request for an existing Product `id`, THE Product Service SHALL persist the updated fields and return HTTP 200 with the updated Product's JSON representation.
3. THE Product Service SHALL set `updated_by` to the authenticated User's `username` and `updated_by_id` to the authenticated User's `id` when updating a Product.
4. IF an update request targets an `id` that does not exist, THEN THE Product Service SHALL return HTTP 404 with a JSON body containing an error message.
5. IF an unauthenticated Client sends a `PUT /api/products/{id}` request, THEN THE Product Service SHALL return HTTP 401.

---

### Requirement 10 — Delete Product

**User Story:** As an Authenticated Client, I want to delete a product so that it is removed from the catalogue.

#### Acceptance Criteria

1. THE Product Service SHALL expose a `DELETE /api/products/{id}` endpoint.
2. WHEN an Authenticated Client sends a delete request for an existing Product `id`, THE Product Service SHALL remove the Product record and return HTTP 200 with a JSON body confirming the deletion.
3. IF a delete request targets an `id` that does not exist, THEN THE Product Service SHALL return HTTP 404 with a JSON body containing an error message.
4. IF an unauthenticated Client sends a `DELETE /api/products/{id}` request, THEN THE Product Service SHALL return HTTP 401.

---

### Requirement 11 — Token Management

**User Story:** As an Authenticated Client, I want token-based auth managed by Laravel Sanctum so that access is secure.

#### Acceptance Criteria

1. THE Auth Service SHALL issue tokens using Laravel Sanctum personal access tokens.
2. WHEN a token is included in a request's `Authorization: Bearer <token>` header, THE API SHALL treat the request as authenticated.
3. IF a request includes an invalid or non-existent token, THEN THE API SHALL return HTTP 401.
