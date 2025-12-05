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
        });

        // When order is created
        static::created(function ($order) {
            $order->updateTableStatus();
        });
    }

    // ADD: Method to update table status based on orders
    public function updateTableStatus()
    {
        if (!  $this->table) {
            return;
        }

        // Check if table has any active orders (pending or processing)
        $hasActiveOrders = $this->table->orders()
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        // Update table status
        $newStatus = $hasActiveOrders ?  'occupied' : 'available';

        if ($this->table->status !== $newStatus) {
            $this->table->update(['status' => $newStatus]);

            \Log::info('Table status updated', [
                'table_id' => $this->table->id,
                'table_number' => $this->table->table_number,
                'old_status' => $this->table->status,
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
