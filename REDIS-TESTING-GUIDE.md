# Redis Cache Testing Guide

## ✅ Redis is Working!

Your Laravel application is successfully using Redis for caching. The cache data is stored in Redis database 1.

### Understanding TTL (Time To Live)

When you see the cache monitor, each item shows its remaining time before expiration:

-   `3600s` = 1 hour remaining
-   `60s` = 1 minute remaining
-   `permanent` = never expires
-   `expired` = key has expired or doesn't exist

The TTL counts down in real-time. Use `--watch` mode to see it live!

## How to Verify Cache is Working

### 1. Monitor Cache in Real-time

```bash
php artisan redis:monitor --watch
```

This will show live updates of your cache every 2 seconds.

### 2. Test Cache Functionality

```bash
php artisan redis:test
```

This simulates frontend requests and shows cache hits/misses.

### 3. Check Redis Directly

```bash
# View all keys in cache database
docker exec restaurant_redis redis-cli -n 1 KEYS "*"

# Get a specific cached value
docker exec restaurant_redis redis-cli -n 1 GET "restaurantapp-database-restaurant_categories"

# Monitor Redis commands in real-time
docker exec -it restaurant_redis redis-cli -n 1 MONITOR
```

## What's Happening When Your Frontend Makes Requests

1. **First Request** (Cache MISS):

    - Frontend requests `/api/categories`
    - Laravel fetches from MySQL database
    - Data is stored in Redis with 3600 second (1 hour) TTL
    - Response sent to frontend

2. **Subsequent Requests** (Cache HIT):
    - Frontend requests `/api/categories`
    - Laravel checks Redis first
    - Data retrieved from Redis (much faster!)
    - Response sent to frontend

## Cache Keys Being Used

-   `categories` - All categories (1 hour TTL)
-   `menus:all` - All menus (1 hour TTL)
-   `tables:all` - All tables (1 hour TTL)
-   `orders:list:status:{status}:table:{table_id}` - Orders filtered by status/table (5 minutes TTL)
-   `kitchen_display:{search}` - Kitchen display orders (30 seconds TTL)

## Clear Cache When Needed

```bash
# Clear all application cache
php artisan cache:clear

# Clear specific cache key
php artisan tinker --execute="Cache::forget('categories');"
```

## Performance Improvement

-   **Database query**: ~50-200ms
-   **Redis cache hit**: ~0.5-2ms
-   **Speed improvement**: 25-400x faster! ⚡

## Your Setup

-   Redis Server: Docker container `restaurant_redis`
-   Redis Port: 6379
-   Cache Database: 1
-   Connection: Working ✅
-   Cache Driver: Redis ✅
