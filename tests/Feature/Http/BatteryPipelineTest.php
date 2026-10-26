<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Weather\Services\WeatherCollectionService;
use App\Weather\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MakesWeatherData;
use Tests\TestCase;

final class BatteryPipelineTest extends TestCase
{
    use MakesWeatherData;
    use RefreshDatabase;

    public static function batteryReadings(): array
    {
        return [
            'healthy integers' => [['battout' => 1, 'battin' => 1], true, true, true],
            'low outdoor' => [['battout' => 0, 'battin' => 1], false, true, false],
            'low indoor' => [['battout' => 1, 'battin' => 0], true, false, false],
            'numeric strings' => [['battout' => '0', 'battin' => '1'], false, true, false],
            'outdoor only' => [['battout' => 1], true, null, true],
            'indoor only' => [['battin' => 0], null, false, false],
            'explicit nulls' => [['battout' => null, 'battin' => null], null, null, null],
            'missing flags' => [[], null, null, null],
            'extra sensor is not the outdoor array' => [['batt1' => 1], null, null, null],
        ];
    }

    #[DataProvider('batteryReadings')]
    public function test_ambient_flags_survive_collection_storage_api_and_html(array $flags, ?bool $outdoor, ?bool $indoor, ?bool $all): void
    {
        config([
            'weather.ambient.api_key' => 'test-api-key',
            'weather.ambient.application_key' => 'test-app-key',
            'weather.ambient.device_mac' => 'AA:BB:CC:DD:EE:FF',
            'weather.ambient.retry_times' => 1,
        ]);
        $body = $this->ambientDevicesBody(['dateutc' => CarbonImmutable::now('UTC')->getTimestampMs()]);
        unset($body[0]['lastData']['battout'], $body[0]['lastData']['battin']);
        $body[0]['lastData'] = array_merge($body[0]['lastData'], $flags);
        Http::fake(['*' => Http::response($body)]);

        $result = app(WeatherCollectionService::class)->collect();
        $this->assertTrue($result['success']);
        $stored = $result['observation']->fresh();
        $this->assertSame($outdoor, $stored->battery_outdoor_ok);
        $this->assertSame($indoor, $stored->battery_indoor_ok);
        $this->assertEquals($body[0]['lastData'], $stored->raw);
        foreach ($flags as $key => $value) {
            $this->assertSame($value, $stored->raw[$key]);
        }

        $this->getJson('/api/display/weather')->assertOk()
            ->assertJsonPath('observation.battery.outdoor_ok', $outdoor)
            ->assertJsonPath('observation.battery.indoor_ok', $indoor)
            ->assertJsonPath('observation.battery.all_ok', $all)
            ->assertDontSee('test-api-key');
        $html = $this->get('/')->assertOk();
        $html->assertSee('id="battery-outdoor">'.($outdoor === null ? Format::DASH : ($outdoor ? 'OK' : 'Low')), false)
            ->assertSee('id="battery-indoor">'.($indoor === null ? Format::DASH : ($indoor ? 'OK' : 'Low')), false)
            ->assertSee($all === null ? Format::DASH : ($all ? 'All OK' : 'Low battery'));
        // Public rendering reads the stored observation, not the provider again.
        Http::assertSentCount(1);
    }
}
