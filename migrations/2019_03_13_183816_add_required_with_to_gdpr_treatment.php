<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRequiredWithToGdprTreatment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gdpr_treatment', function(Blueprint $table){
            $table->text('required_with')->nullable()->default(null)->after('required');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gdpr_treatment', function(Blueprint $table){
            $table->dropColumn('required_with');
        });
    }
}
