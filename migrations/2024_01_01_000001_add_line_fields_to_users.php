<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) {
            $table->string('line_user_id', 64)->nullable()->unique()->after('email');
            $table->string('line_display_name', 255)->nullable()->after('line_user_id');
            $table->timestamp('line_linked_at')->nullable()->after('line_display_name');
        });
    },

    'down' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn(['line_user_id', 'line_display_name', 'line_linked_at']);
        });
    },
];
