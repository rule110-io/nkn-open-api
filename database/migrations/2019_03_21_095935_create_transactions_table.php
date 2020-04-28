<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->unsignedBigInteger('block_id')->index();

            $table->string('attributes');
            $table->bigInteger('fee');
            $table->string('hash')->index();
            $table->text('nonce');
            $table->string('txType')->index();

            $table->integer('block_height')->unsigned()->index();
            $table->dateTime('added_at');
            $table->dateTime('created_at');

            $table->foreign('block_id')
                ->references('id')->on('blocks')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
