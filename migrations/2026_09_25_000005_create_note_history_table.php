<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('ffans_community_notes_note_history', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('note_id');
    $table->string('event', 32);
    $table->unsignedInteger('actor_user_id')->nullable();
    $table->string('from_status', 32)->nullable();
    $table->string('to_status', 32)->nullable();
    $table->decimal('score', 12, 10)->nullable();
    $table->unsignedInteger('rating_count');
    $table->text('reason')->nullable();
    $table->dateTime('created_at');

    $table->index(['note_id', 'created_at', 'id'], 'ffans_cnotes_history_note_time_idx');
    $table->foreign('note_id', 'ffans_cnotes_history_note_fk')->references('id')->on('ffans_community_notes_notes')->cascadeOnDelete();
    $table->foreign('actor_user_id', 'ffans_cnotes_history_actor_fk')->references('id')->on('users')->nullOnDelete();
});
