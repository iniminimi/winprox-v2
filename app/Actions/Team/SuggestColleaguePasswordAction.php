<?php

namespace App\Actions\Team;

/**
 * Stelt een leesbaar maar sterk wachtwoord voor bij een nieuwe collega.
 * Geen tenant: er wordt niets opgeslagen.
 */
class SuggestColleaguePasswordAction
{
    private const LENGTH = 16;

    public function handle(): string
    {
        $sets = [
            'abcdefghijkmnopqrstuvwxyz',
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            '23456789',
            '!@#$%&*-_+=?',
        ];

        $chars = [];
        foreach ($sets as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }

        $pool = implode('', $sets);
        $poolLength = strlen($pool);
        while (count($chars) < self::LENGTH) {
            $chars[] = $pool[random_int(0, $poolLength - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
