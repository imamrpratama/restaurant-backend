<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_number',
        'capacity',
        'status',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    // Add relationship to orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Get active orders for this table
    public function activeOrders()
    {
        return $this->orders()
            ->whereIn('status', ['pending', 'processing']);
    }
}
