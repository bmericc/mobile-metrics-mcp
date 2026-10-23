<?php

namespace App\Stores\Data;

use Carbon\CarbonImmutable;

final readonly class ReviewData
{
    public function __construct(
        public string $storeReviewId,
        public int $rating,
        public CarbonImmutable $reviewedAt,
        public ?string $title = null,
        public ?string $body = null,
        public ?string $author = null,
        public ?string $language = null,
        public ?string $country = null,
        public ?string $appVersion = null,
        public ?string $replyBody = null,
        public ?CarbonImmutable $repliedAt = null,
    ) {}
}
