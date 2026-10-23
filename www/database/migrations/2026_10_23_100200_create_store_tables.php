<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bir workspace'te mağaza başına birden fazla geliştirici hesabı olabilir.
        Schema::create('store_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('store', 20);
            $table->string('label');
            $table->text('credentials');
            $table->string('status', 20)->default('pending');
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        // Aynı uygulamanın üç mağazadaki karşılıklarını gruplar.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('store_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('store', 20);
            $table->string('external_id');
            $table->string('name');
            $table->string('icon_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_account_id', 'external_id']);
        });

        // Long format: mağazalar farklı metrik sunduğu için şema değişmeden yeni metrik eklenebilir.
        // country = 'ZZ' ülke kırılımı olmayan toplamı ifade eder (unique index NULL ile çalışmadığı için).
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_app_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->char('country', 2)->default('ZZ');
            $table->string('metric', 40);
            $table->decimal('value', 20, 4);
            $table->timestamps();

            $table->unique(['store_app_id', 'date', 'country', 'metric']);
            $table->index(['metric', 'date']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_app_id')->constrained()->cascadeOnDelete();
            $table->string('store_review_id');
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('author')->nullable();
            $table->string('language', 10)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->timestamp('reviewed_at');
            $table->text('reply_body')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique(['store_app_id', 'store_review_id']);
            $table->index(['store_app_id', 'reviewed_at']);
        });

        // Ham CSV/TSV dosyaları saklanır; parser değişirse yeniden işlenebilir.
        Schema::create('raw_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_app_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 60);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('path');
            $table->string('checksum', 64);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['store_account_id', 'checksum']);
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_app_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job', 60);
            $table->string('status', 20);
            $table->unsignedInteger('rows')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['store_account_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('raw_reports');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('daily_metrics');
        Schema::dropIfExists('store_apps');
        Schema::dropIfExists('products');
        Schema::dropIfExists('store_accounts');
    }
};
