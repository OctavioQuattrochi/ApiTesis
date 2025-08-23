<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHeightCmAndWidthCmToQuotesTable extends Migration
{
    public function up()
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('quotes', 'height_cm')) {
                $table->float('height_cm')->nullable();
            }
            if (!Schema::hasColumn('quotes', 'width_cm')) {
                $table->float('width_cm')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (Schema::hasColumn('quotes', 'height_cm')) {
                $table->dropColumn('height_cm');
            }
            if (Schema::hasColumn('quotes', 'width_cm')) {
                $table->dropColumn('width_cm');
            }
        });
    }
}