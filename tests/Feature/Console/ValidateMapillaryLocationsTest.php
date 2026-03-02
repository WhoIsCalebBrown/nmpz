<?php

namespace Tests\Feature\Console;

use App\Models\Location;
use App\Models\Map;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ValidateMapillaryLocationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mapillary.access_token' => 'test-token']);
    }

    public function test_fails_without_token(): void
    {
        config(['services.mapillary.access_token' => null]);

        $this->artisan('mapillary:validate')
            ->expectsOutput('Mapillary access token is not configured. Set MAPILLARY_ACCESS_TOKEN or VITE_MAPILLARY_ACCESS_TOKEN.')
            ->assertExitCode(1);
    }

    public function test_blacklists_locations_without_coverage(): void
    {
        $map = Map::factory()->create();
        $location = Location::factory()->for($map)->create(['lat' => 10.0, 'lng' => 20.0]);

        Http::fake([
            'graph.mapillary.com/*' => Http::response(['data' => []]),
        ]);

        $this->artisan('mapillary:validate', ['--sleep' => 0])
            ->assertExitCode(0);

        $location->refresh();
        $this->assertNotNull($location->blacklisted_at);
    }

    public function test_does_not_blacklist_locations_with_coverage(): void
    {
        $map = Map::factory()->create();
        $location = Location::factory()->for($map)->create(['lat' => 10.0, 'lng' => 20.0]);

        Http::fake([
            'graph.mapillary.com/*' => Http::response(['data' => [['id' => 'img-1']]]),
        ]);

        $this->artisan('mapillary:validate', ['--sleep' => 0])
            ->assertExitCode(0);

        $location->refresh();
        $this->assertNull($location->blacklisted_at);
    }

    public function test_restores_blacklisted_locations_with_recheck(): void
    {
        $map = Map::factory()->create();
        $location = Location::factory()->for($map)->blacklisted()->create(['lat' => 10.0, 'lng' => 20.0]);

        Http::fake([
            'graph.mapillary.com/*' => Http::response(['data' => [['id' => 'img-1']]]),
        ]);

        $this->artisan('mapillary:validate', ['--recheck' => true, '--sleep' => 0])
            ->assertExitCode(0);

        $location->refresh();
        $this->assertNull($location->blacklisted_at);
    }

    public function test_skips_blacklisted_locations_without_recheck(): void
    {
        $map = Map::factory()->create();
        $location = Location::factory()->for($map)->blacklisted()->create();

        Http::fake([
            'graph.mapillary.com/*' => Http::response(['data' => [['id' => 'img-1']]]),
        ]);

        $this->artisan('mapillary:validate', ['--sleep' => 0])
            ->assertExitCode(0);

        $location->refresh();
        $this->assertNotNull($location->blacklisted_at);
    }

    public function test_dry_run_does_not_modify_database(): void
    {
        $map = Map::factory()->create();
        $location = Location::factory()->for($map)->create(['lat' => 10.0, 'lng' => 20.0]);

        Http::fake([
            'graph.mapillary.com/*' => Http::response(['data' => []]),
        ]);

        $this->artisan('mapillary:validate', ['--dry-run' => true, '--sleep' => 0])
            ->assertExitCode(0);

        $location->refresh();
        $this->assertNull($location->blacklisted_at);
    }

    public function test_filters_by_map_name(): void
    {
        $map1 = Map::factory()->create(['name' => 'map-one']);
        $map2 = Map::factory()->create(['name' => 'map-two']);
        $loc1 = Location::factory()->for($map1)->create();
        $loc2 = Location::factory()->for($map2)->create();

        Http::fake([
            'graph.mapillary.com/*' => Http::response(['data' => []]),
        ]);

        $this->artisan('mapillary:validate', ['--map' => 'map-one', '--sleep' => 0])
            ->assertExitCode(0);

        $loc1->refresh();
        $loc2->refresh();
        $this->assertNotNull($loc1->blacklisted_at);
        $this->assertNull($loc2->blacklisted_at);
    }

    public function test_fails_with_invalid_map_name(): void
    {
        $this->artisan('mapillary:validate', ['--map' => 'nonexistent'])
            ->expectsOutput("Map 'nonexistent' not found.")
            ->assertExitCode(1);
    }

    public function test_does_not_blacklist_on_api_error(): void
    {
        $map = Map::factory()->create();
        $location = Location::factory()->for($map)->create();

        Http::fake([
            'graph.mapillary.com/*' => Http::response(null, 500),
        ]);

        $this->artisan('mapillary:validate', ['--sleep' => 0])
            ->assertExitCode(0);

        $location->refresh();
        $this->assertNull($location->blacklisted_at);
    }

    public function test_reports_no_locations_to_validate(): void
    {
        $this->artisan('mapillary:validate', ['--sleep' => 0])
            ->expectsOutput('No locations to validate.')
            ->assertExitCode(0);
    }
}
