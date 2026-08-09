<?php

namespace App\Tests\DataProviders;

class UUIDDataProvider
{
    public static function uuidGenerationProvider(): array
    {
        // We'll return a dataset to run the test 3 times.
        // The string inside is just a description for clarity.
        return [
            'first run'  => ['first generation attempt'],
            'second run' => ['second generation attempt'],
            'third run'  => ['third generation attempt'],
        ];
    }
}