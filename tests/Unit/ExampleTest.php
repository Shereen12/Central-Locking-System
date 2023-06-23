<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Resource;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Foundation\Testing\RefreshDatabase;


class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_action_not_present():void
    {
        $resource = Resource::factory()->create();
        $response = $this->patchJson('/api/resources/' . $resource->name, ['period' => 5])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('Error.action')->etc()
        );
    }

    public function test_action_not_valid():void
    {
        $resource = Resource::factory()->create();
        $response = $this->patchJson('/api/resources/' . $resource->name, ['action' => 'invalid', 'period' => 5])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->where('Error.action.0', 'The selected action is invalid.')->etc()
        );
    }

    public function test_period_is_right_format():void
    {
        $resource = Resource::factory()->create();
        $response = $this->patchJson('/api/resources/' . $resource->name, ['action' => 'acquire', 'period' => 'x'])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('Error.period')->etc()
        );
    }

    public function test_period_is_positive():void
    {
        $resource = Resource::factory()->create();
        $response = $this->patchJson('/api/resources/' . $resource->name, ['action' => 'acquire', 'period' => -2])
        ->assertStatus(422);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('Error.period')->etc()
        );
    }

    public function test_acquiring_not_found_resource():void
    {
        $this->patchJson('/api/resources/invalidResource/', ['action' => 'acquire', 'period' => 5])
        ->assertStatus(404);
    }


    public function test_successfull_acquiring_free_resource():void
    {
        $resource = Resource::factory()->create();

        $this->patchJson('/api/resources/' . $resource->name, ['action' => 'acquire', 'period' => 5])
        ->assertStatus(200);
    }

    public function test_acquiring_not_free_resource():void
    {
        $resource = Resource::factory()->create([
            'acquired_at' => Carbon::now()->toDateTimeString(),
            'key' => '1111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111'
        ]);

        $this->patchJson('/api/resources/' . $resource->name, ['action' => 'acquire', 'period' => 5])
        ->assertStatus(408);
    }

    public function test_acquiring_a_resource_after_some_time():void
    {
        $resource = Resource::factory()->create([
            'name' => 'new',
            'acquired_at' => Carbon::now()->toDateTimeString(),
            'key' => '8888888888888888888888888888888888888888888888888888888888888888888888888888888888888',
            'period' => 5
        ]);

        sleep(5);

        $resource->update([
            'acquired_at' => null,
            'period' => null,
            'key' => null
        ]);

        $this->patchJson('/api/resources/' . $resource->name, ['action' => 'acquire'])
        ->assertStatus(200);
    }
   public function test_realesing_resource_wrong_key():void
    {
        $resource = Resource::factory()->create([
            'name' => 'new resource',
            'acquired_at' => Carbon::now()->toDateTimeString(),
            'key' => '454545454545454545454545454545454545454545454545454545544',
            'period' => 70
        ]);
        $this->patchJson('/api/resources/' . $resource->name, ['action' => 'release', 'key' => '7777777777'])
        ->assertStatus(422);
    }

    public function test_releasing_a_not_found_resource():void
    {
        $resource = Resource::factory()->create([
            'name' => 'new resource',
            'acquired_at' => Carbon::now()->toDateTimeString(),
            'key' => '9999999999',
            'period' => 70
        ]);
        $response = $this->patchJson('/api/resources/Resource9', ['action' => 'release', 'key' => '9999999999']);
        $response->assertStatus(404); 
    }

    public function test_successfull_realising_resource():void
    {
        $resource = Resource::factory()->create([
            'name' => 'new-resource',
            'acquired_at' => Carbon::now()->toDateTimeString(),
            'key' => '1212121212121221',
            'period' => 70
        ]);
        $this->patchJson('/api/resources/new-resource', ['action' => 'release', 'key' => '1212121212121221'])->assertStatus(200);
    }
}
