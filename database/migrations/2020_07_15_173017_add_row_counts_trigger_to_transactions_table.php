<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRowCountsTriggerToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared("
            CREATE TRIGGER transactions_count BEFORE INSERT OR DELETE ON transactions FOR EACH ROW EXECUTE PROCEDURE count_trig();
            INSERT INTO row_counts (relname,reltuples) VALUES('transactions',0);
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
            DROP TRIGGER transactions_count ON transactions;
        ");

    }
}
