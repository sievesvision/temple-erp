<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $mockBuilder = new class {
            public function where($column, $value) { return $this; }
            public function orderBy($column, $direction = 'asc') { return $this; }
            public function get() { return collect(); }
            public function sum($column) { return 0; }
        };

        DB::shouldReceive('table')
            ->andReturn($mockBuilder);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
