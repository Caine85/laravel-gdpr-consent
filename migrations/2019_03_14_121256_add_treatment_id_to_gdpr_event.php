<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTreatmentIdToGdprEvent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gdpr_event', function(Blueprint $table){
            $table->uuid('treatment_id')->nullable()->default(null)->after('consent_id');

            $table
                ->foreign('treatment_id')
                ->references('id')
                ->on('gdpr_treatment')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gdpr_event', function(Blueprint $table){
            $table->dropColumn('treatment_id');
        });
    }
}
