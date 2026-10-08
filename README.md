# BTS API

REST API for BTS.id Backend Developer Recruitment Test.

Built with **Laravel 12** · **Laravel Sanctum** · **MySQL 8** · **Docker**

---

## Requirements

- [Docker](https://www.docker.com/) & Docker Compose

---

## Quick Start

```bash
# 1. Clone the repo
git clone https://github.com/bayuandikap/BTS.git
cd BTS

# 2. Copy environment file
cp .env.example .env

# 3. Build and start all containers
docker-compose up --build
```

The API will be available at **http://localhost:8000**

> On first run, Docker will install dependencies, run database migrations, and start the server automatically.

---

## API Documentation (Swagger)

Visit **http://localhost:8000/api/documentation** in your browser.

---

## API Endpoints

### Authentication

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/auth/register` | Register a new user | No |
| POST | `/api/auth/login` | Login, returns tokens | No |

### Products

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/products` | List all products | No |
| GET | `/api/products/{id}` | Get a single product | No |
| POST | `/api/products` | Create a product | Yes |
| PUT | `/api/products/{id}` | Update a product | Yes |
| DELETE | `/api/products/{id}` | Delete a product | Yes |

---

## Usage Examples (curl)

### Register

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "jhon_doe",
    "password": "supersecret",
    "password_confirmation": "supersecret"
  }'
```

### Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "jhon_doe",
    "password": "supersecret"
  }'
```

Response:
```json
{
  "authentication_token": "1|abc123...",
  "refresh_token": "2|xyz456..."
}
```

### List Products

```bash
curl http://localhost:8000/api/products
```

With filters:
```bash
# Search
curl "http://localhost:8000/api/products?search=shirt"

# Filter by category
curl "http://localhost:8000/api/products?category=Clothes"

# Paginate
curl "http://localhost:8000/api/products?limit=10&page=1"
```

### Create a Product

```bash
curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "title": "Awesome T-Shirt",
    "price": 99.99,
    "description": "High-quality cotton t-shirt",
    "category": "Clothes",
    "images": ["https://placeimg.com/640/480/any"]
  }'
```

### Update a Product

```bash
curl -X PUT http://localhost:8000/api/products/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "price": 79.99
  }'
```

### Delete a Product

```bash
curl -X DELETE http://localhost:8000/api/products/1 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_KEY` | Laravel application key (auto-generated) | — |
| `DB_HOST` | MySQL host | `db` |
| `DB_DATABASE` | Database name | `bts_db` |
| `DB_USERNAME` | Database user | `bts_user` |
| `DB_PASSWORD` | Database password | `bts_password` |

---

## Product Data Structure

```json
{
  "id": 1,
  "title": "Awesome T-Shirt",
  "price": 99.99,
  "description": "High-quality cotton t-shirt",
  "category": "Clothes",
  "images": ["https://placeimg.com/640/480/any"],
  "created_at": "2025-01-01T15:01:04.000000Z",
  "created_by": "jhon_doe",
  "created_by_id": 1,
  "updated_at": "2025-01-01T15:01:04.000000Z",
  "updated_by": null,
  "updated_by_id": null
}
```
