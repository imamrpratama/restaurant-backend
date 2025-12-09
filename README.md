# Restaurant Management System - Backend Setup Guide

## Table of Contents

1. [Overview](#overview)
2. [System Requirements](#system-requirements)
3. [Installation Steps](#installation-steps)
4. [Database Setup](#database-setup)
5. [Redis Configuration](#redis-configuration)
6. [MinIO Configuration](#minio-configuration)
7. [Environment Variables](#environment-variables)
8. [Testing](#testing)
9. [API Documentation](#api-documentation)
10. [Troubleshooting](#troubleshooting)

---

## Overview

The Restaurant Management System backend is built with Laravel 11, providing a comprehensive API for managing restaurants, orders, menus, tables, and kitchen operations. The system includes real-time kitchen display functionality, Redis caching for performance optimization, and OAuth 2.0 Google Sign-In integration.

Key features include:

-   RESTful API with Sanctum authentication
-   Real-time kitchen display system with caching
-   Two-factor authentication (2FA)
-   Google OAuth 2.0 login integration
-   Rate limiting for security
-   Automated testing with PHPUnit
-   Redis caching layer for performance

---

## System Requirements

-   PHP 8.2 or higher
-   Laravel 11
-   MySQL 8.0 or higher
-   Redis 6.0 or higher
-   MinIO 2023.01 or higher (for file storage)
-   Composer 2.x
-   Node.js 16+ (for frontend development)

Optional:

-   Docker and Docker Compose (for containerized setup)
-   Postman (for API testing)

---

## Installation Steps

### Step 1: Clone the Repository

```bash
git clone https://github.com/imamrpratama/restaurant-backend.git
cd restaurant-backend
```

### Step 2: Install Dependencies

```bash
composer install
```

### Step 3: Copy Environment Configuration

```bash
cp .env.example .env
```

### Step 4: Generate Application Key

```bash
php artisan key:generate
```

### Step 5: Create SQLite Database (for testing)

```bash
touch database/database.sqlite
```

### Step 6: Run Migrations

```bash
php artisan migrate
```

### Step 7: Seed Database (Optional - for sample data)

```bash
php artisan db:seed
```

### Step 8: Start Development Server

```bash
php artisan serve
```

The API will be available at: http://localhost:8000/api

---

## Database Setup

### MySQL Configuration

1. Create a new database for the application:

```bash
mysql -u root -p
CREATE DATABASE restaurant_management;
EXIT;
```

2. Update your .env file with database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=restaurant_management
DB_USERNAME=root
DB_PASSWORD=your_password
```

3. Run migrations:

```bash
php artisan migrate
```

### Database Schema

The application includes the following tables:

-   users: User accounts and authentication
-   personal_access_tokens: Sanctum API tokens
-   categories: Food categories
-   menus: Menu items
-   tables: Restaurant tables
-   orders: Customer orders
-   order_items: Individual items in orders
-   cache: Laravel cache table

### Running Migrations

To run specific migrations:

```bash
php artisan migrate --step
```

To rollback migrations:

```bash
php artisan migrate:rollback
```

To refresh all migrations:

```bash
php artisan migrate:refresh
```

---

## Redis Configuration

### Installation

For Windows (using WSL or Docker):

```bash
docker run -d -p 6379:6379 redis:latest
```

For Linux/Mac (using Homebrew):

```bash
brew install redis
brew services start redis
```

For Windows (direct installation):

Download Redis from: https://github.com/microsoftarchive/redis/releases

### Configuration

Update your .env file:

```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis
```

### Redis Features in Your Application

1. Order Caching (30-second TTL)

    - Caches all active orders
    - Auto-refreshes every 30 seconds
    - Cleared on order creation/update

2. Kitchen Display Caching (30-second TTL)

    - Caches pending and cooking orders
    - Real-time updates for kitchen staff
    - Optimized for performance

3. Menu Caching (60-minute TTL)

    - Caches categories and menu items
    - Reduced database queries
    - Cleared on menu updates

4. Table Caching (60-minute TTL)
    - Caches table availability
    - Fast table status lookups
    - Cleared on table updates

### Testing Redis Connection

```bash
php artisan tinker
Redis::ping()
```

Expected output: "PONG"

### Monitoring Redis

Access the Redis monitoring endpoint:

```
GET http://localhost:8000/api/test-redis
```

This shows all cached keys and their TTL values.

Access the Redis monitor command:

```bash
php artisan redis:monitor --watch
```

---

## MinIO Configuration

MinIO is a high-performance, S3-compatible object storage system used for storing menu images, user avatars, and other media files.

### Installation

For Windows (using Docker):

```bash
docker run -d --name minio -p 9000:9000 -p 9001:9001 -e MINIO_ROOT_USER=minioadmin -e MINIO_ROOT_PASSWORD=minioadmin minio/minio server /data --console-address ":9001"
```

For Linux/Mac (using Homebrew):

```bash
brew install minio/stable/minio
brew services start minio/stable/minio
```

For direct installation, download from: https://min.io/download#/linux

### Configuration

Update your .env file:

```env
FILESYSTEM_DISK=minio

MINIO_ENDPOINT=http://127.0.0.1:9000
MINIO_KEY=minioadmin
MINIO_SECRET=minioadmin
MINIO_BUCKET=restaurant-app
MINIO_REGION=us-east-1
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

### Bucket Setup

Create the bucket for your application:

```bash
# Access MinIO console at http://localhost:9001
# Login with credentials: minioadmin / minioadmin
# Create a bucket named 'restaurant-app'
```

Or use MinIO CLI:

```bash
# Install MinIO CLI
brew install minio-mc

# Configure MinIO alias
mc alias set minio http://localhost:9000 minioadmin minioadmin

# Create bucket
mc mb minio/restaurant-app
```

### File Storage Configuration

Laravel configuration in `config/filesystems.php`:

```php
'disks' => [
    'minio' => [
        'driver' => 's3',
        'key' => env('MINIO_KEY'),
        'secret' => env('MINIO_SECRET'),
        'region' => env('MINIO_REGION'),
        'bucket' => env('MINIO_BUCKET'),
        'url' => env('MINIO_URL'),
        'endpoint' => env('MINIO_ENDPOINT'),
        'use_path_style_endpoint' => env('MINIO_USE_PATH_STYLE_ENDPOINT', false),
    ],
]
```

### Uploading Files

Store file in MinIO:

```php
use Illuminate\Support\Facades\Storage;

// Upload file
$path = Storage::disk('minio')->put('menu-images', $file);

// Get file URL
$url = Storage::disk('minio')->url($path);

// Delete file
Storage::disk('minio')->delete($path);
```

### MinIO Features in Your Application

1. Menu Images

    - Store restaurant menu item photos
    - Scalable storage for multiple images
    - Fast retrieval with S3 API

2. User Avatars

    - Store user profile pictures
    - Automatic resizing and optimization
    - Accessible via signed URLs

3. Document Storage
    - Store receipts and invoices
    - Archive order documentation
    - Long-term retention capability

### Accessing MinIO Console

MinIO Console URL: http://localhost:9001

Default credentials:

-   Username: minioadmin
-   Password: minioadmin

### Production Configuration

For production deployment:

1. Change default credentials:

```env
MINIO_KEY=your-production-key
MINIO_SECRET=your-production-secret
```

2. Use HTTPS endpoint:

```env
MINIO_ENDPOINT=https://minio.yourdomain.com
```

3. Enable SSL/TLS:

```bash
# Place SSL certificates in /etc/minio/certs/
# MinIO will automatically use them
```

4. Configure bucket policies:

```bash
mc policy set upload minio/restaurant-app
```

5. Enable versioning (for data protection):

```bash
mc version enable minio/restaurant-app
```

### Backup and Disaster Recovery

Backup MinIO data:

```bash
# Full backup
mc mirror minio/restaurant-app ~/backup/restaurant-app

# Restore from backup
mc mirror ~/backup/restaurant-app minio/restaurant-app
```

### Performance Optimization

1. Enable compression in MinIO:

```bash
mc admin config set minio compression extension .txt,.log,.csv
```

2. Set object lifecycle policies (auto-delete old files):

```bash
mc ilm import minio/restaurant-app < lifecycle.json
```

3. Monitor MinIO performance:

```bash
# Check MinIO metrics
curl http://localhost:9000/minio/v2/metrics/cluster
```

### Troubleshooting MinIO

Issue: Connection refused

Solution: Ensure MinIO is running:

```bash
# Check if MinIO is running
docker ps | grep minio

# Or restart MinIO
docker restart minio
```

Issue: Authentication failed

Solution: Verify credentials in .env match MinIO configuration:

```bash
# Reset MinIO credentials
export MINIO_ROOT_USER=minioadmin
export MINIO_ROOT_PASSWORD=minioadmin
```

Issue: Bucket not found

Solution: Create bucket in MinIO console or via CLI:

```bash
mc mb minio/restaurant-app
```

Issue: File upload fails

Solution: Check bucket permissions:

```bash
mc policy set upload minio/restaurant-app
```

---

## Environment Variables

### Required Variables

```env
APP_NAME="Restaurant Management System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY=

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=restaurant_management
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

FILESYSTEM_DISK=minio
MINIO_ENDPOINT=http://127.0.0.1:9000
MINIO_KEY=minioadmin
MINIO_SECRET=minioadmin
MINIO_BUCKET=restaurant-app
MINIO_REGION=us-east-1
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

### Google OAuth Configuration

```env
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
```

To obtain Google credentials:

1. Go to Google Cloud Console: https://console.cloud.google.com
2. Create a new project
3. Enable Google+ API
4. Create OAuth 2.0 credentials (Web application type)
5. Add authorized redirect URIs:

    - http://localhost:8000/api/google-callback
    - http://localhost:3000/auth/callback (for frontend)

6. Copy Client ID and Client Secret to .env

### Email Configuration (Optional)

For email notifications, configure your mail driver:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@restaurantapp.com
MAIL_FROM_NAME="Restaurant App"
```

### Queue Configuration

```env
QUEUE_CONNECTION=sync
```

For production, use database or Redis:

```env
QUEUE_CONNECTION=redis
```

---

## Testing

### Running All Tests

```bash
php artisan test
```

### Running Specific Test File

```bash
php artisan test tests/Feature/AuthTest.php
```

### Running Specific Test Method

```bash
php artisan test tests/Feature/AuthTest.php --filter test_user_can_login
```

### Running with Coverage Report

```bash
php artisan test --coverage
```

### Test Categories

Authentication Tests:

-   User registration
-   User login
-   Two-factor authentication requirement
-   Email existence checking

Order Tests:

-   Order creation
-   Order status updates
-   Order retrieval

Rate Limiting Tests:

-   Login endpoint rate limiting (10 requests/minute)
-   Rate limit headers verification

### Test Results

All tests should pass with 8 tests and 66+ assertions:

---

## API Documentation

### Base URL

```
http://localhost:8000/api
```

### Authentication

Most endpoints require Sanctum token authentication. Include token in request headers:

```
Authorization: Bearer {your_token_here}
```

### Rate Limiting

Public authentication endpoints are rate-limited to 10 requests per minute:

-   POST /register
-   POST /login
-   POST /google-login
-   POST /check-email

Response when rate limit exceeded:

```
HTTP/1.1 429 Too Many Requests

Headers:
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 0
X-RateLimit-Reset: {unix_timestamp}
Retry-After: 60
```

### Key Endpoints

Authentication:

-   POST /register - Register new user
-   POST /login - Login with email/password
-   POST /google-login - Login with Google OAuth
-   POST /check-email - Check if email exists
-   POST /logout - Logout user

Orders:

-   POST /orders - Create order
-   GET /orders - Get all orders
-   GET /orders/{id} - Get single order
-   PUT /orders/{id} - Update order
-   DELETE /orders/{id} - Delete order
-   GET /kitchen-display - Get kitchen display (pending/cooking orders)

For complete API documentation, see API.md or test with Postman collection.

---

## Troubleshooting

### Common Issues

Issue: "SQLSTATE[HY000]: General error: 1030 Got error 28"

Solution: Ensure sufficient disk space and database permissions.

Issue: Redis connection refused

Solution: Ensure Redis is running:

```bash
redis-cli ping
```

Expected output: PONG

Issue: "Class 'Predis\Client' not found"

Solution: Install Predis:

```bash
composer require predis/predis
```

Issue: Migrations not running

Solution: Ensure database connection is correct:

```bash
php artisan migrate:status
```

Issue: Google OAuth not working

Solution: Verify Google credentials in .env and ensure callback URLs match those configured in Google Cloud Console.

Issue: Cache not working

Solution: Test Redis connection and check CACHE_DRIVER in .env is set to 'redis'.

### Performance Optimization

Enable caching for improved performance:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Support and Contribution

Documentation last updated: 9 December, 2025
