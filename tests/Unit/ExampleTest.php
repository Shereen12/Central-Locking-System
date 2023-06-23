<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Resource;
use App\Jobs\ResourceJob;
use Illuminate\Testing\Fluent\AssertableJson;


class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */

    public function setuUp():void
    {
        parent::setUp();
        Resource::where('id', '>=', 1)->update([
            'acquired_at' => null,
            'period' => null,
            'key' => null
        ]);
    }

    public function test_action_not_present():void
    {
        $response = $this->patchJson('/api/resources/Resource1', ['key' => '5555555555', 'period' => 5])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('Error.action')->etc()
        );
    }

    public function test_action_not_valid():void
    {
        $response = $this->patchJson('/api/resources/Resource1', ['action' => 'invalid', 'key' => '5555555555', 'period' => 5])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->where('Error.action.0', 'The selected action is invalid.')->etc()
        );
    }

    public function test_key_is_present():void
    {
        $response = $this->patchJson('/api/resources/Resource1', ['action' => 'acquire', 'period' => 5])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('Error.key')->etc()
        );
    }

    public function test_key_is_right_format():void
    {
        $response = $this->patchJson('/api/resources/Resource1', ['action' => 'acquire', 'key' => 5, 'period' => 5])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('Error.key')->etc()
        );
    }

    public function test_key_is_not_less_than_ten_characters():void
    {
        $response = $this->patchJson('/api/resources/Resource1', ['action' => 'acquire', 'key' => '5555', 'period' => 5])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('Error.key')->etc()
        );
    }

    public function test_key_is_not_more_than_ten_characters():void
    {
        $response = $this->patchJson('/api/resources/Resource1', ['action' => 'acquire','key' => '989898989898', 'period' => 5])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('Error.key')->etc()
        );
    }

    public function test_period_is_right_format():void
    {
        $response = $this->patchJson('/api/resources/Resource1', ['action' => 'acquire','key' => '1234567891', 'period' => 'x'])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('Error.period')->etc()
        );
    }

    public function test_period_is_positive():void
    {
        $response = $this->patchJson('/api/resources/Resource1', ['action' => 'acquire','key' => '1234567891', 'period' => -2])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('Error.period')->etc()
        );
    }

    public function test_acquiring_not_found_resource():void
    {
        $this->patchJson('/api/resources/Resource9/', ['action' => 'acquire', 'key' => '1234567891', 'period' => 5])
        ->assertStatus(404);
    }

    public function test_not_unique_key_for_free_resource():void
    {
        Resource::where('id', 2)->update([
            'acquired_at' => Carbon::now()->toDateTimeString(),
            'key' => '1111111111'
        ]);

        $response = $this->patchJson('/api/resources/Resource1', ['action' => 'acquire', 'key' => '1111111111', 'period' => 5])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->where("Error.key.0", "The key has already been taken.")->etc());
    }

    public function test_successfull_acquiring_free_resource():void
    {
        Resource::where('id', 2)->update([
            'acquired_at' => null,
            'period' => null,
            'key' => null,
        ]);

        $this->patchJson('/api/resources/Resource2', ['action' => 'acquire', 'key' => '1111111111', 'period' => 5])
        ->assertStatus(200);
    }

    public function test_acquiring_not_free_resource():void
    {
        Resource::where('id', 1)->update([
            'acquired_at' => Carbon::now()->toDateTimeString(),
            'key' => '1111111111'
        ]);

        $this->patchJson('/api/resources/Resource1', ['action' => 'acquire', 'key' => '3333333333', 'period' => 5])
        ->assertStatus(408);
    }

    public function test_acquiring_a_resource_after_some_time():void
    {
        Resource::where('id', 1)->update([
            'acquired_at' => Carbon::now()->toDateTimeString(),
            'key' => '999999999',
            'period' => 5
        ]);

        $resource = Resource::where('id', 1)->first();

        ResourceJob::dispatch($resource)->delay(now()->addSeconds($resource->period));

        sleep(5);

        $this->patchJson('/api/resources/Resource1/', ['action' => 'acquire', 'key' => '8888888888', 'period' => 5])
        ->assertStatus(200);
    }

    public function test_realising_resource_wrong_key():void
    {
        $this->patchJson('/api/resources/Resource1', ['action' => 'release', 'key' => '7777777777'])
        ->assertStatus(422);
    }

    public function test_realising_a_not_found_resource():void
    {
        Resource::where('id', 1)->update([
            'acquired_at' => Carbon::now()->toDateTimeString(),
            'key' => '9999999999',
        ]);
        $this->patchJson('/api/resources/Resource9', ['action' => 'release', 'key' => '9999999999'])
        ->assertStatus(404);
    }

    public function test_successfull_realising_resource():void
    {
        $this->patchJson('/api/resources/Resource1', ['action' => 'release', 'key' => '9999999999'])
        ->assertStatus(200);
    }
}
