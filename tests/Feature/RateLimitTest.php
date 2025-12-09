<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_limit()
    {
        // Rate limit is 10 requests per 1 minute
        // Make requests and verify responses are either 401/422 (invalid credentials) or 429 (rate limited)
        for ($i = 0; $i < 15; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'figma@gmail.com',
                'password' => 'Abcd1234',
            ]);

            // Status should be 401/422 (unauthorized/validation) or 429 (rate limited)
            $this->assertThat(
                $response->status(),
                $this->logicalOr(
                    $this->equalTo(401),
                    $this->equalTo(422),
                    $this->equalTo(429)
                )
            );
        }
    }

    /**
     * Test: Show how rate limit decreases with each request
     */
    public function test_rate_limit_decreased()
    {
        echo "\n\n=== Rate Limit Counter Decreasing Demo ===\n";
        echo "Limit: 10 requests per minute\n";
        echo "Making 12 requests to show blocking:\n\n";

        for ($i = 1; $i <= 12; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'figma@gmail.com',
                'password' => 'Abcd1234',
            ]);

            $remaining = $response->headers->get('X-RateLimit-Remaining');
            $status = $response->status();
            $statusText = ($status === 429) ? '❌ BLOCKED' : '✅ ALLOWED';

            echo "Request $i:  Status $status ($statusText) | Remaining: $remaining/10\n";

            // This proves the rate limit works:
            // Requests 1-10 should pass (not be 429)
            // Requests 11-12 should be 429 (blocked)
            if ($i <= 10) {
                $this->assertNotEquals(429, $status, "Request $i should not be rate limited");
            } else {
                $this->assertEquals(429, $status, "Request $i should be rate limited");
            }
        }

        echo "\n========================================\n\n";
    }
}
