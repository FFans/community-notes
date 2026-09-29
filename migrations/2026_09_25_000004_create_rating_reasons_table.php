<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('ffans_community_notes_rating_reasons', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('rating_id');
    $table->string('reason', 64);

    $table->unique(['rating_id', 'reason'], 'ffans_cnotes_reasons_rating_reason_unique');
    $table->foreign('rating_id', 'ffans_cnotes_reasons_rating_fk')->references('id')->on('ffans_community_notes_note_ratings')->cascadeOnDelete();
});
