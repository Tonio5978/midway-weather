<?php

namespace App\Weather\Configuration;

use App\Weather\Contracts\ConditionPhraseSource;

final class ConfiguredPhraseSource implements ConditionPhraseSource
{
    public function phrasesFor(string $category, bool $isDaytime = true): array
    {
        $hour = now(config('weather.timezone'))->hour;
        $period = match (true) {
            $hour < 6 => 'night',$hour < 12 => 'morning',$hour < 17 => 'afternoon',$hour < 21 => 'evening',default => 'night'
        };
        $aliases = match ($category) {
            'stormy' => ['storms'],'heavy_rain','rainy' => ['rain'],'hot_dry' => ['hot'],'oppressive','hot_humid' => ['hot', 'humid'],'frigid','cold_clear','cold_windy' => ['cold'],'muggy' => ['humid'],'overcast' => ['cloudy'],'pleasant','mild_clear' => ['clear'],default => []
        };
        $contexts = [$category, ...$aliases, $period];
        if ($category === 'fallback') {
            $contexts[] = 'general';
        }
        $matched = [];
        $general = [];
        foreach (app(WeatherSettings::class)->read()['values']['quips'] as $q) {
            if (! $q['enabled'] || (! $isDaytime && $q['daylight_only'])) {
                continue;
            }
            if (array_intersect($q['contexts'], $contexts)) {
                $matched[$q['id']] = $q['text'];
            } elseif (in_array('general', $q['contexts'], true)) {
                $general[$q['id']] = $q['text'];
            }
        }

        return $matched ?: $general;
    }
}
