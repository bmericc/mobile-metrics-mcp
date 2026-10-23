<?php

namespace App\Stores\Data;

final readonly class AppDescriptor
{
    public function __construct(
        public string $externalId,
        public string $name,
        public ?string $iconUrl = null,
    ) {}
}
