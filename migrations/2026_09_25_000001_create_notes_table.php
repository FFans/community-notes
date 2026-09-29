<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('ffans_community_notes_notes', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('post_id');
    $table->unsignedInteger('user_id')->nullable();
    $table->string('reason', 64);
    $table->text('content');
    $table->string('status', 32)->default('needs_more_ratings');
    $table->unsignedInteger('rating_count')->default(0);
    $table->unsignedInteger('helpful_count')->default(0);
    $table->unsignedInteger('somewhat_helpful_count')->default(0);
    $table->unsignedInteger('not_helpful_count')->default(0);
    $table->decimal('score', 12, 10)->nullable();
    $table->dateTime('status_changed_at');
    $table->boolean('is_hidden')->default(false);
    $table->dateTime('hidden_at')->nullable();
    $table->unsignedInteger('hidden_by_user_id')->nullable();
    $table->text('hidden_reason')->nullable();
    $table->dateTime('created_at');
    $table->dateTime('updated_at');

    $table->index('post_id', 'ffans_cnotes_notes_post_idx');
    $table->index('user_id', 'ffans_cnotes_notes_user_idx');
    $table->index('status', 'ffans_cnotes_notes_status_idx');
    $table->index('is_hidden', 'ffans_cnotes_notes_hidden_idx');
    $table->index(['post_id', 'status', 'is_hidden'], 'ffans_cnotes_notes_public_idx');
    $table->unique(['post_id', 'user_id'], 'ffans_cnotes_notes_post_user_unique');
    $table->foreign('post_id', 'ffans_cnotes_notes_post_fk')->references('id')->on('posts')->cascadeOnDelete();
    $table->foreign('user_id', 'ffans_cnotes_notes_user_fk')->references('id')->on('users')->nullOnDelete();
    $table->foreign('hidden_by_user_id', 'ffans_cnotes_notes_hidden_by_fk')->references('id')->on('users')->nullOnDelete();
});
