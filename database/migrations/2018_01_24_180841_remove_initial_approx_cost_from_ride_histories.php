<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveInitialApproxCostFromRideHistories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('ride_histories', 'initial_approx_cost'))
        {
            Schema::table('ride_histories', function ($table) {
                $table->dropColumn("initial_approx_cost");
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('ride_histories', 'initial_approx_cost'))
        {
            Schema::table('ride_histories', function ($table) {
                $table->dropColumn("initial_approx_cost");
            });
        }
    }
}
