<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    /**
     * Get all orders
     */
    public function index(Request $request)
    {
        $query = Order::with(['items.menu', 'table', 'user']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by table
        if ($request->has('table_id')) {
            $query->where('table_id', $request->table_id);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json($orders);
    }

    /**
     * Get kitchen display orders (pending and processing only)
     */
    public function kitchenDisplay(Request $request)
    {
        $search = $request->query('search');

        // Try to get from cache first, if not found or has search, query from database
        if (!$search && Cache::has('kitchen_display:all')) {
            $orders = Cache::get('kitchen_display:all');
            Log::info('Kitchen display orders fetched from cache', [
                'count' => $orders->count(),
            ]);
        } else {
            $query = Order::with(['items.menu', 'table', 'user'])
                ->whereIn('status', ['pending', 'processing'])
                ->orderBy('created_at', 'asc');

            // Search by order number, table number, or menu item name
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhereHas('table', function ($tq) use ($search) {
                          $tq->where('table_number', 'like', "%{$search}%");
                      })
                      ->orWhereHas('items.menu', function ($mq) use ($search) {
                          $mq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $orders = $query->get();

            // Cache if no search
            if (!$search) {
                Cache::put('kitchen_display:all', $orders, 360);
            }

            Log::info('Kitchen display orders fetched from database', [
                'count' => $orders->count(),
                'search' => $search
            ]);
        }

        // Convert to arrays to ensure items are included
        return response()->json($orders->map(fn($order) => $order->toArray())->all());
    }

    /**
     * Create new order
     */
    public function store(Request $request)
    {
        Log::info('Creating new order', ['data' => $request->all()]);

        $request->validate([
            'table_id' => 'required|exists:tables,id',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Generate order number
            $orderNumber = 'ORD-' . strtoupper(substr(uniqid(), -8));

            // Calculate total
            $totalAmount = collect($request->items)->sum(function ($item) {
                return $item['price'] * $item['quantity'];
            });

            // Create order
            $order = Order::create([
                'user_id' => $request->user()->id,
                'table_id' => $request->table_id,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
            ]);

            // Create order items
            foreach ($request->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $item['menu_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // Update table status to occupied
            $table = Table::find($request->table_id);
            if ($table && $table->status !== 'occupied') {
                $table->update(['status' => 'occupied']);
                Cache::forget('tables:all');

                Log::info('Table status updated to occupied', [
                    'table_id' => $table->id,
                    'table_number' => $table->table_number
                ]);
            }

            DB::commit();

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'order_number' => $orderNumber
            ]);

            return response()->json($order->load(['items.menu', 'table']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single order
     */
    public function show(Order $order)
    {
        return response()->json($order->load(['items.menu', 'table', 'user']));
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, Order $order)
    {
        Log::info('Updating order status', [
            'order_id' => $order->id,
            'current_status' => $order->status,
            'new_status' => $request->status,
            'table_id' => $order->table_id
        ]);

        $request->validate([
            'status' => 'required|in:pending,processing,done,cancelled',
        ]);

        try {
            DB::beginTransaction();

            $oldStatus = $order->status;
            $order->update(['status' => $request->status]);

            // If order is done or cancelled, check if we should free the table
            if (in_array($request->status, ['done', 'cancelled'])) {
                $table = $order->table;

                if ($table) {
                    // Check if this table has any other active orders
                    $hasActiveOrders = Order::where('table_id', $table->id)
                        ->whereIn('status', ['pending', 'processing'])
                        ->exists();

                    $newTableStatus = $hasActiveOrders ?  'occupied' : 'available';

                    if ($table->status !== $newTableStatus) {
                        $table->update(['status' => $newTableStatus]);
                        Cache::forget('tables:all');

                        Log::info('Table status updated', [
                            'table_id' => $table->id,
                            'table_number' => $table->table_number,
                            'old_status' => $table->status,
                            'new_status' => $newTableStatus,
                            'reason' => 'Order ' . $request->status
                        ]);
                    }
                }
            }

            DB::commit();

            Log::info('Order status updated successfully', [
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $order->status
            ]);

            return response()->json([
                'message' => 'Order status updated successfully',
                'order' => $order->load(['items.menu', 'table', 'user'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update order status', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to update order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete order
     */
    public function destroy(Order $order)
    {
        try {
            DB::beginTransaction();

            $tableId = $order->table_id;

            // Delete order items first
            $order->items()->delete();

            // Delete order
            $order->delete();

            // Check if table should be set to available
            if ($tableId) {
                $table = Table::find($tableId);
                if ($table) {
                    $hasActiveOrders = Order::where('table_id', $tableId)
                        ->whereIn('status', ['pending', 'processing'])
                        ->exists();

                    if (! $hasActiveOrders && $table->status === 'occupied') {
                        $table->update(['status' => 'available']);
                        Cache::forget('tables:all');

                        Log::info('Table freed after order deletion', [
                            'table_id' => $table->id,
                            'table_number' => $table->table_number
                        ]);
                    }
                }
            }

            DB::commit();

            Log::info('Order deleted', ['order_id' => $order->id]);

            return response()->json(['message' => 'Order deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to delete order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
