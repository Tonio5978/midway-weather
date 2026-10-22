<?php

declare(strict_types=1);

namespace App\Weather\Support;

use App\Weather\Contracts\ConditionPhraseSource;

/**
 * Curated, offline-safe copy for the household display.
 */
final class ConditionPhraseLibrary implements ConditionPhraseSource
{
    /** @var array<string, list<string>> */
    private const PHRASES = [
        'oppressive' => [
            'The air is soup and the sun is rude.',
            'Georgia has turned the humidity all the way up.',
            'Hot, heavy, and making a strong case for staying inside.',
            'The pool is not optional weather today.',
            'Summer is leaning on the thermostat with both hands.',
            'Dense heat outside. Cold drinks have entered the chat.',
            'The porch is warm. The shade is also warm. Everything is warm.',
            'Muggy enough to make the mailbox feel far away.',
        ],
        'hot_humid' => [
            'Hot and muggy with no interest in personal space.',
            'The pool is accepting applications immediately.',
            'Warm air, extra humidity, and very little mercy.',
            'A fine day to admire the yard from indoors.',
            'The heat brought humidity as its plus-one.',
            'Sticky outside. Hydrangeas seem pleased about it.',
            'The air has texture today.',
            'Hot enough to make shade feel like a destination.',
        ],
        'hot_dry' => [
            'Strong sun and dry heat are running the show.',
            'Hot, bright, and ready to toast the driveway.',
            'The sun is working overtime without supervision.',
            'Pool weather with a side of crispy grass.',
            'Bright heat outside. Shade is premium real estate.',
            'The yard is asking for water and better management.',
            'Hot and dry with excellent laundry-on-the-line ambition.',
            'The pavement is doing its best impression of a griddle.',
        ],
        'warm_breezy' => [
            'Breezy enough to make the warmth behave itself.',
            'Warm, sunny, and moving just enough air around.',
            'A friendly breeze is keeping summer honest.',
            'Good porch weather, assuming the breeze keeps its shift.',
            'Warm with a breeze doing useful work for once.',
            'The yard is warm, but at least the air is moving.',
            'Pool weather with complimentary natural air conditioning.',
            'Warm and breezy. The patio has potential.',
        ],
        'warm_calm' => [
            'Warm, calm, and taking the scenic route through the day.',
            'The air is still and the pool is making a persuasive argument.',
            'Comfortably warm with barely a leaf moving.',
            'A quiet warm day with strong porch energy.',
            'Warm and settled. The yard work excuse is getting weaker.',
            'Not much wind, not much drama, plenty of warmth.',
            'The kind of warm day that makes dinner outside sound smart.',
            'Calm warmth and absolutely no urgency.',
        ],
        'mild_clear' => [
            'Clear, mild, and almost suspiciously reasonable.',
            'The weather has decided to cooperate for a change.',
            'Mild sunshine and no obvious complaints.',
            'A very respectable day to be outside.',
            'Clear skies with the thermostat showing restraint.',
            'The yard work window is officially open.',
            'Mild, bright, and behaving beautifully.',
            'Outside is currently worth the trip.',
        ],
        'cool_breezy' => [
            'Cool with a breeze that means business.',
            'Fresh air moving through like it owns the place.',
            'A brisk little breeze has joined the forecast.',
            'Cool and lively. A light jacket would not be dramatic.',
            'The air is crisp and the leaves have opinions.',
            'Breezy, cool, and excellent for waking everybody up.',
            'A fresh morning with free-range air conditioning.',
            'Cool wind outside. Grab a jacket if you run civilized.',
        ],
        'cool_calm' => [
            'Cool, calm, and pleasantly unbothered.',
            'A quiet cool day with excellent porch potential.',
            'The air is crisp and minding its own business.',
            'Cool enough for a jacket, calm enough to enjoy it.',
            'Still, cool, and taking things easy.',
            'A calm little pocket of sweater weather.',
            'Cool air, quiet yard, no complaints filed.',
            'The thermostat outside has found some manners.',
        ],
        'cold_clear' => [
            'Cold, clear, and personally disrespectful.',
            'Bright skies, cold air, grab the serious jacket.',
            'The sunshine looks warmer than it is. Do not be fooled.',
            'Cold and clear with deceptive window-view charm.',
            'The sky is lovely. The air is not your friend.',
            'Crisp, bright, and requiring sleeves with commitment.',
            'Clear winter air and zero interest in compromise.',
            'Bundle up. The sunshine is mostly decorative.',
        ],
        'cold_windy' => [
            'Cold wind outside. The jacket needs backup.',
            'Sharp, windy, and deeply committed to winter.',
            'The wind has found every gap in every coat.',
            'Cold and gusty with indoor plans gaining momentum.',
            'A brisk wind is making the temperature feel personal.',
            'Winter is moving through at speed.',
            'Cold air with enough wind to rearrange your priorities.',
            'The yard can wait. The wind has spoken.',
        ],
        'frigid' => [
            'Frigid outside. The good jacket is now the minimum jacket.',
            'The air is sharp enough to have corners.',
            'Cold has completed its transformation into a personality.',
            'Stay inside weather, presented in high-definition cold.',
            'The thermometer is being unnecessarily dramatic.',
            'A deeply unreasonable amount of cold is happening.',
            'Bundle up like the mailbox is an expedition.',
            'The outside has been placed in the freezer.',
        ],
        'rainy' => [
            'Rain is here. The yard approves.',
            'A steady rinse for everything that forgot to come inside.',
            'The garden ordered rain and apparently got delivery.',
            'Wet outside. Excellent weather for not cutting grass.',
            'Rain on the roof and zero pressure to leave the porch.',
            'The lawn is drinking. Let it have the afternoon.',
            'Grab the umbrella. The clouds have started a project.',
            'Rainy, useful, and a little inconvenient.',
        ],
        'heavy_rain' => [
            'The rain has upgraded from suggestion to commitment.',
            'Heavy rain outside. The yard is getting the deluxe wash.',
            'A proper downpour is handling the watering today.',
            'Rain is arriving in bulk. Indoor plans look excellent.',
            'The gutters are earning their keep.',
            'Serious rain outside. Give the roads a little patience.',
            'The clouds opened the big faucet.',
            'No need to water anything. Nature has taken over.',
        ],
        'stormy' => [
            'Stormy outside. The porch can wait.',
            'Thunder has entered the neighborhood conversation.',
            'The sky is making noise and rearranging the afternoon.',
            'A storm is handling the outdoor schedule now.',
            'Dark clouds, active weather, indoor plans recommended.',
            'The atmosphere has chosen drama.',
            'Storms nearby. The yard work committee is adjourned.',
            'The sky is putting on a loud presentation.',
        ],
        'overcast' => [
            'Gray skies doing absolutely nothing helpful.',
            'Overcast and committed to the color gray.',
            'The sun has delegated today to cloud cover.',
            'Cloudy, quiet, and a little short on enthusiasm.',
            'The sky is wearing its indoor voice.',
            'A soft gray lid has settled over the day.',
            'Clouds everywhere, drama nowhere.',
            'The sun is technically still employed, just off-site.',
        ],
        'high_uv' => [
            'The sunshine is bright and not being subtle about it.',
            'Strong sun today. Shade has excellent reviews.',
            'The UV is high and the sun means business.',
            'Bright outside. The pool is making a solid pitch.',
            'Sunshine with the brightness dial turned all the way up.',
            'A bold sun is supervising the yard today.',
            'Plenty of sun and very little atmospheric restraint.',
            'The shade is open for business.',
        ],
        'falling_pressure' => [
            'Pressure is falling. The weather may be planning something.',
            'The barometer is dropping and keeping its reasons private.',
            'Falling pressure with a hint of weather plot development.',
            'The atmosphere is quietly changing the subject.',
            'Pressure is sliding downward. Keep an eye on the sky.',
            'The barometer has begun a slow descent.',
            'Something may be brewing, according to the pressure.',
            'Falling pressure and a sky worth checking later.',
        ],
        'rising_pressure' => [
            'Pressure is rising and the weather is tidying up.',
            'The barometer is climbing toward better manners.',
            'Rising pressure with calmer intentions ahead.',
            'The atmosphere appears to be getting itself together.',
            'Pressure is on the way up. The sky may follow.',
            'The barometer has chosen optimism.',
            'Conditions are settling in the right direction.',
            'Rising pressure and a little less weather drama.',
        ],
        'dead_calm' => [
            'Calm, muggy, and suspiciously still.',
            'Not a leaf moving. The air has clocked out.',
            'The wind is apparently taking a personal day.',
            'Still air and a yard waiting for somebody else to move first.',
            'Calm enough to hear the humidity thinking.',
            'The atmosphere is holding very, very still.',
            'No breeze, no hurry, no help from the air.',
            'Everything outside is paused except the temperature.',
        ],
        'windy' => [
            'Windy outside. Secure the lightweight porch ambitions.',
            'The trees are providing a full weather report.',
            'A determined wind is rearranging the neighborhood.',
            'Breezy has officially become windy.',
            'The air is moving with purpose today.',
            'Wind is doing most of the talking outside.',
            'The yard is lively and the loose cushions look nervous.',
            'Plenty of wind and no shortage of opinions from the trees.',
        ],
        'muggy' => [
            'Muggy outside. The air has misplaced its boundaries.',
            'The humidity is making itself part of every conversation.',
            'Sticky air and a strong preference for indoor fans.',
            'The atmosphere is wearing a damp sweater.',
            'Muggy enough to notice before the door closes behind you.',
            'The air is soft, heavy, and entirely too familiar.',
            'Humidity has settled in and unpacked.',
            'A damp little day with ambitious hair weather.',
        ],
        'pleasant' => [
            'Gorgeous outside. Somebody write this date down.',
            'The weather is excellent and would like credit for it.',
            'A genuinely lovely day has appeared without warning.',
            'Open-a-window weather has entered the building.',
            'The porch is accepting reservations.',
            'This is the weather everybody claims to prefer.',
            'A fine day for the yard, the pool, or doing absolutely nothing.',
            'Outside is showing off in the nicest possible way.',
        ],
        'fallback' => [
            'Weather is happening. Details remain delightfully ordinary.',
            'Outside is doing a perfectly reasonable weather impression.',
            'Current conditions are calm enough to skip the speech.',
            'The weather has nothing dramatic to report right now.',
        ],
    ];

    /**
     * @return array<string, string>
     */
    public function phrasesFor(string $category, bool $isDaytime = true): array
    {
        $phrases = self::PHRASES[$category] ?? self::PHRASES['fallback'];

        $result = [];
        foreach ($phrases as $index => $phrase) {
            if (! $isDaytime && $this->isDaylightSpecific($phrase)) {
                continue;
            }
            $result[sprintf('%s.%02d', $category, $index + 1)] = $phrase;
        }

        // Some daylight-only categories (for example high UV) intentionally
        // have no nighttime prose. The caller will fall back to a neutral
        // category rather than accidentally returning sun-specific copy.
        return $result;
    }

    /** @return list<string> */
    public function categories(): array
    {
        return array_keys(self::PHRASES);
    }

    public function count(): int
    {
        return array_sum(array_map('count', self::PHRASES));
    }

    public function editablePhrases(): array
    {
        $rows = [];
        foreach (self::PHRASES as $category => $phrases) {
            foreach ($phrases as $index => $text) {
                $rows[] = ['id' => sprintf('%s.%02d', $category, $index + 1), 'text' => $text, 'enabled' => true, 'contexts' => [$category], 'daylight_only' => $this->isDaylightSpecific($text)];
            }
        }

        return $rows;
    }

    private function isDaylightSpecific(string $phrase): bool
    {
        return preg_match('/\b(?:sun|sunshine|sunny|bright|shade)\b/i', $phrase) === 1;
    }
}
