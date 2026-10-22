<?php

declare(strict_types=1);

namespace App\Weather\Contracts;

/**
 * Supplies stable phrase IDs and display copy for normalized conditions.
 *
 * A future AI-generated phrase cache can implement this contract without
 * changing ConditionSummaryService or the display payload.
 */
interface ConditionPhraseSource
{
    /**
     * @return array<string, string> Phrase ID => display text.
     */
    public function phrasesFor(string $category, bool $isDaytime = true): array;
}
