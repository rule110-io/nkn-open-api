<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRowCountsTriggerToAddressStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared("
            CREATE TRIGGER address_statistics_count BEFORE INSERT OR DELETE ON address_statistics FOR EACH ROW EXECUTE PROCEDURE count_trig();
            INSERT INTO row_counts (relname,reltuples) VALUES('address_statistics',0);
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("
            DROP TRIGGER address_statistics_count ON address_statistics;
        ");

    }
}
