@php($reviews = $report->latestReviews())
<x-filament-widgets::widget>
    @include('filament.product-report._styles')
    <x-filament::section heading="Son yorumlar">
        @if ($reviews->isEmpty())
            <p class="kr-empty">Henüz yorum yok.</p>
        @else
            <table class="kr-table">
                <tbody>
                    @foreach ($reviews as $review)
                        <tr>
                            <td>
                                <span class="kr-stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                <span class="kr-muted">
                                    {{ $review->storeApp->store->label() }}
                                    · {{ $review->reviewed_at->format('d.m.Y') }}
                                    @if ($review->country) · {{ $review->country }} @endif
                                    @if ($review->app_version) · v{{ $review->app_version }} @endif
                                </span>
                                @if ($review->title)
                                    <div><strong>{{ $review->title }}</strong></div>
                                @endif
                                <div>{{ \Illuminate\Support\Str::limit($review->body, 220) }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
