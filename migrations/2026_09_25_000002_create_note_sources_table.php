<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('ffans_community_notes_note_sources', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('note_id');
    $table->string('url', 2048);
    $table->unsignedInteger('position');
    $table->dateTime('created_at');

    $table->index(['note_id', 'position', 'id'], 'ffans_cnotes_sources_position_idx');
    $table->foreign('note_id', 'ffans_cnotes_sources_note_fk')->references('id')->on('ffans_community_notes_notes')->cascadeOnDelete();
});
