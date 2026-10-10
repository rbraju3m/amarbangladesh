<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard speed with months of events (checked with ~500k): most numbers are "distinct visitors
 * since X", with or without an event name. (created_at, visitor_id) serves the ones without a name,
 * and (name, created_at, visitor_id) replaces (name, created_at) so the named ones never read the rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->index(['created_at', 'visitor_id']);
            $table->index(['name', 'created_at', 'visitor_id']);
            $table->dropIndex(['name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->index(['name', 'created_at']);
            $table->dropIndex(['name', 'created_at', 'visitor_id']);
            $table->dropIndex(['created_at', 'visitor_id']);
        });
    }
};
