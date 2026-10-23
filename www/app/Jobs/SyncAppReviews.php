<?php

namespace App\Jobs;

use App\Jobs\Concerns\RecordsSyncRun;
use App\Models\StoreApp;
use App\Stores\StoreRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAppReviews implements ShouldQueue
{
    use Queueable, RecordsSyncRun;

    public function __construct(public StoreApp $app) {}

    public function handle(StoreRegistry $registry): void
    {
        $collector = $registry->for($this->app->store);
        $since = $this->app->reviews()->max('reviewed_at');

        $this->recordSyncRun($this->app->storeAccount, $this->app, function () use ($collector, $since) {
            $count = 0;

            foreach ($collector->fetchReviews($this->app, $since ? now()->parse($since)->toImmutable() : null) as $review) {
                $this->app->reviews()->updateOrCreate(
                    ['store_review_id' => $review->storeReviewId],
                    [
                        'rating' => $review->rating,
                        'title' => $review->title,
                        'body' => $review->body,
                        'author' => $review->author,
                        'language' => $review->language,
                        'country' => $review->country,
                        'app_version' => $review->appVersion,
                        'reviewed_at' => $review->reviewedAt,
                        'reply_body' => $review->replyBody,
                        'replied_at' => $review->repliedAt,
                    ],
                );
                $count++;
            }

            return $count;
        });
    }
}
