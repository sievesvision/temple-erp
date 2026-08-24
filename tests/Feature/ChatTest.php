<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MockDbBuilder {
    public static $inserted = [];
    public static $updated = [];
    public static $queries = [];

    public static function reset() {
        self::$inserted = [];
        self::$updated = [];
        self::$queries = [];
    }

    public function where($column, $operator = null, $value = null) {
        self::$queries[] = ['where', func_get_args()];
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null) {
        self::$queries[] = ['orWhere', func_get_args()];
        return $this;
    }

    public function first() {
        // Return a mock active session
        $session = new \stdClass();
        $session->session_id = 1;
        $session->devotee_id = 1;
        $session->status = 'active';
        $session->mode = 'bot';
        return $session;
    }

    public function insertGetId($data) {
        self::$inserted[] = $data;
        return 1;
    }

    public function insert($data) {
        self::$inserted[] = $data;
        return true;
    }

    public function update($data) {
        self::$updated[] = $data;
        return true;
    }

    public function exists() {
        return true;
    }

    public function get() {
        return collect([]);
    }

    public function __call($name, $arguments) {
        return $this;
    }
}

class ChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MockDbBuilder::reset();
    }

    public function test_devotee_can_start_chat_session(): void
    {
        DB::shouldReceive('table')
            ->andReturn(new MockDbBuilder());

        // Create mock user
        $user = new User();
        $user->id = 1;
        $user->name = 'Test Devotee';
        $user->role = 'Devotee';
        $user->status = 'Active';

        $response = $this->actingAs($user)->getJson(route('devotee.chat.session'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);
    }

    public function test_devotee_gets_phone_number_on_call_query(): void
    {
        DB::shouldReceive('table')
            ->andReturn(new MockDbBuilder());

        $user = new User();
        $user->id = 1;
        $user->name = 'Test Devotee';
        $user->role = 'Devotee';
        $user->status = 'Active';

        $response = $this->actingAs($user)->postJson(route('devotee.chat.send'), [
            'message' => 'Please call me'
        ]);

        $response->assertStatus(200);

        // Verify that a message was inserted with the correct phone number
        $found = false;
        foreach (MockDbBuilder::$inserted as $row) {
            if (isset($row['sender_type']) && $row['sender_type'] === 'bot' && str_contains($row['message_text'], '9901476678')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Bot response containing 9901476678 was not sent");
    }

    public function test_devotee_switches_to_agent_mode_on_chat_query(): void
    {
        DB::shouldReceive('table')
            ->andReturn(new MockDbBuilder());

        $user = new User();
        $user->id = 1;
        $user->name = 'Test Devotee';
        $user->role = 'Devotee';
        $user->status = 'Active';

        $response = $this->actingAs($user)->postJson(route('devotee.chat.send'), [
            'message' => 'I want to chat with staff'
        ]);

        $response->assertStatus(200);

        // Verify that chat mode was updated to agent
        $updatedMode = false;
        foreach (MockDbBuilder::$updated as $row) {
            if (isset($row['mode']) && $row['mode'] === 'agent') {
                $updatedMode = true;
                break;
            }
        }
        $this->assertTrue($updatedMode, "Chat mode was not updated to agent");
    }

    public function test_staff_can_view_ended_sessions(): void
    {
        DB::shouldReceive('table')
            ->andReturn(new MockDbBuilder());

        $user = new User();
        $user->id = 2;
        $user->name = 'Test Staff';
        $user->role = 'Staff';
        $user->status = 'Active';

        $response = $this->actingAs($user)->getJson(route('staff.chats.history'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);
    }

    public function test_admin_can_view_ended_sessions(): void
    {
        DB::shouldReceive('table')
            ->andReturn(new MockDbBuilder());

        $user = new User();
        $user->id = 3;
        $user->name = 'Test Admin';
        $user->role = 'Admin';
        $user->status = 'Active';

        $response = $this->actingAs($user)->getJson(route('admin.chats.history'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);
    }
}
