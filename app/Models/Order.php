<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'table_id',
        'order_number',
        'status',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    // ADD: Boot method to handle status changes
    protected static function boot()
    {
        parent::boot();

        // When order status is updated
        static::updated(function ($order) {
            // Check if status was changed
            if ($order->isDirty('status')) {
                $order->updateTableStatus();
            }
            // Refresh kitchen display cache
            $kitchenOrders = Order::with(['items.menu', 'table', 'user'])
                ->whereIn('status', ['pending', 'processing'])
                ->latest()
                ->get();
            \Cache::put('kitchen_display:all', $kitchenOrders, 360);

            // Refresh orders cache
            $allOrders = Order::all();
            \Cache::put('order:all', $allOrders, 360);
        });

        // When order is created
        static::created(function ($order) {
            $order->updateTableStatus();
            // Refresh kitchen display cache
            $kitchenOrders = Order::with(['items.menu', 'table', 'user'])
                ->whereIn('status', ['pending', 'processing'])
                ->latest()
                ->get();
            \Cache::put('kitchen_display:all', $kitchenOrders, 360);

            // Refresh orders cache
            $allOrders = Order::all();
            \Cache::put('order:all', $allOrders, 360);
        });
    }

    // ADD: Method to update table status based on orders
    public function updateTableStatus()
    {
        // Check if table_id exists
        if (!$this->table_id) {
            return;
        }

        // Explicitly load the table relationship to ensure it's a model instance
        $table = Table::find($this->table_id);
        if (!$table) {
            return;
        }

        // Check if table has any active orders (pending or processing)
        $hasActiveOrders = $table->orders()
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        // Update table status
        $newStatus = $hasActiveOrders ?  'occupied' : 'available';

        if ($table->status !== $newStatus) {
            $table->update(['status' => $newStatus]);

            \Log::info('Table status updated', [
                'table_id' => $table->id,
                'table_number' => $table->table_number,
                'old_status' => $table->status,
                'new_status' => $newStatus,
                'order_id' => $this->id,
                'order_status' => $this->status
            ]);

            // Clear table cache
            \Cache::forget('tables:all');
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
