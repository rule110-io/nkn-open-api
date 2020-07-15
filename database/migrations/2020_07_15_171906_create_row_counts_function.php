<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRowCountsFunction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION count_trig()
            RETURNS TRIGGER AS
            $$
            DECLARE
            BEGIN
            IF TG_OP = 'INSERT' THEN
                EXECUTE 'UPDATE row_counts set reltuples=reltuples +1 where relname = ''' || TG_RELNAME || '''';
                RETURN NEW;
            ELSIF TG_OP = 'DELETE' THEN
                EXECUTE 'UPDATE row_counts set reltuples=reltuples -1 where relname = ''' || TG_RELNAME || '''';
                RETURN OLD;
            END IF;
            END;
            $$
            LANGUAGE 'plpgsql';
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
            DROP FUNCTION IF EXISTS count_trig();
        ");
    }
}
