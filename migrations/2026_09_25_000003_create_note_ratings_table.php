<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('ffans_community_notes_note_ratings', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('note_id');
    $table->unsignedInteger('user_id')->nullable();
    $table->string('value', 32);
    $table->dateTime('created_at');
    $table->dateTime('updated_at');

    $table->unique(['note_id', 'user_id'], 'ffans_cnotes_ratings_note_user_unique');
    $table->foreign('note_id', 'ffans_cnotes_ratings_note_fk')->references('id')->on('ffans_community_notes_notes')->cascadeOnDelete();
    $table->foreign('user_id', 'ffans_cnotes_ratings_user_fk')->references('id')->on('users')->nullOnDelete();
});
