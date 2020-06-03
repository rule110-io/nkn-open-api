<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddressStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('address_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('address')->nullable()->index();
            $table->bigInteger('transaction_count');
            $table->dateTime('first_transaction');
            $table->dateTime('last_transaction');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('address_statistics');
    }
}
