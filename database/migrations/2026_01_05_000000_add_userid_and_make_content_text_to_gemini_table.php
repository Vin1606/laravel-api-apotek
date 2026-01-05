<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // If table exists, alter it; otherwise create it with necessary columns
        if (Schema::hasTable('gemini')) {
            Schema::table('gemini', function (Blueprint $table) {
                // make content a text column to hold longer responses
                // wrap in try/catch in case the driver doesn't support change
                try {
                    $table->text('content')->change();
                } catch (\Throwable $e) {
                    // Some DB drivers / setups require doctrine/dbal for change(); ignore if unavailable
                }

                // add nullable user id for history per user if not exists
                if (! Schema::hasColumn('gemini', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('gemini_id');
                }

                // index for faster pruning/lookup
                $table->index(['user_id', 'created_at']);
            });
        } else {
            Schema::create('gemini', function (Blueprint $table) {
                $table->id('gemini_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('role');
                $table->text('content');
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('gemini')) {
            // If we created the table in up(), it's safe to drop it; otherwise attempt to revert changes
            try {
                Schema::dropIfExists('gemini');
            } catch (\Throwable $e) {
                // fallback: try to drop added column/index if present
                Schema::table('gemini', function (Blueprint $table) {
                    if (Schema::hasColumn('gemini', 'user_id')) {
                        $table->dropIndex(['user_id', 'created_at']);
                        $table->dropColumn('user_id');
                    }
                    try {
                        $table->string('content')->change();
                    } catch (\Throwable $e) {
                        // ignore
                    }
                });
            }
        }
    }
};
