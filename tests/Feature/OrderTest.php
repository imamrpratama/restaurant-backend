<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Table;
use App\Models\Menu;
use App\Models\Category;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_order()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $table = Table::factory()->create();
        $category = Category::factory()->create();
        $menu = Menu::factory()->create(['category_id' => $category->id, 'price' => 10.00]);

        $response = $this->postJson('/api/orders', [
            'table_id' => $table->id,
            'items' => [
                [
                    'menu_id' => $menu->id,
                    'quantity' => 2,
                    'price' => 10.00,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'order_number', 'status', 'total_amount']);

        $this->assertDatabaseHas('orders', [
            'table_id' => $table->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_order_status_can_be_updated()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->create(['status' => 'pending']);

        $response = $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'processing',
        ]);
    }
}
