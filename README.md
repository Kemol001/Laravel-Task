# Laravel-Task

# Flash Sale Checkout System (Laravel 12)

This project implements a high-concurrency flash-sale checkout workflow using **Laravel 12**, **MySQL**, and **atomic database transactions**. It fulfills the requirements from the task PDF.

---

## 🧱 Features

### 1. Products
Each product tracks:
- name  
- price  
- total stock  
- available stock (computed as total stock minus active, unexpired holds)

### 2. Holds (Short-Lived Reservations)
- Holds temporarily reserve stock for **2 minutes**.
- Created using `SELECT … FOR UPDATE` / `lockForUpdate()` to ensure atomicity.
- Each hold has an `expires_at` timestamp and cannot be reused once marked `used`.
- Releasing an expired hold immediately returns the reserved stock.

### 3. Orders
- Orders can be created once per hold.
- Orders cannot be created if the hold:
    - expired
    - was already used
    - already produced an order
- Marking an order as paid:
    - permanently decrements product stock
    - marks the associated hold as `used`

### 4. Idempotent Payment Webhook
- Webhook processing is idempotent (same idempotency key → same result).
- Applies a payment exactly once and logs all incoming webhook calls.
- Handles out-of-order events:
    - If order exists → apply payment immediately.  
    - If order does not exist → store webhook for later processing.  
    - When the order is later created → apply any pending webhook(s).

### 5. Concurrency Safety
Relies on:
- database transactions
- row-level locking (`lockForUpdate()`)
- atomic update operations
- unique constraints
- idempotency rules

Guarantees:
- no overselling  
- no duplicate orders  
- no race conditions

---

## 📦 Installation

### Requirements
- PHP 8.2+
- Composer
- MySQL 8.x
- Laravel 12

### Setup
```bash
git clone <repo>
cd flashsale
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database in `.env`, then run migrations and seeders:
```bash
php artisan migrate --seed
```

Start the application:
```bash
php artisan serve
```

---

## 🔌 API Endpoints

GET /api/products/{id}  
Returns product details including computed available stock.

POST /api/holds  
Creates a hold (reserves stock).

Example body:
```json
{
    "product_id": 1,
    "qty": 2
}
```

POST /api/orders  
Creates an order for a given hold.

Example body:
```json
{
    "hold_id": "uuid"
}
```

Validation ensures:
- hold exists and is active
- hold has not expired
- no previous order was created from this hold

POST /api/payments/webhook  
Idempotent payment webhook.

Example payload:
```json
{
    "idempotency_key": "abc123",
    "order_id": "uuid",
    "status": "success"
}
```

Webhook behavior:
- applies payment immediately if the order exists
- logs the webhook if the order does not yet exist
- applies pending webhooks when the order is later created

---

## 🧪 Running Tests
Run all automated tests:
```bash
php artisan test
```

The test suite includes coverage for:
- overselling prevention
- hold expiration
- idempotent webhooks
- webhooks arriving before order creation
- preventing duplicate orders
- preventing order creation from expired holds

All tests pass successfully.
