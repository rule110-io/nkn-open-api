<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHeadersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('headers', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->unsignedBigInteger('block_id')->index();

            $table->string('signerId');
            $table->string('hash');
            $table->integer('height')->unsigned()->index();
            $table->string('prevBlockHash');
            $table->text('randomBeacon');
            $table->text('signature');
            $table->string('signerPk')->index();
            $table->string('stateRoot');
            $table->dateTime('timestamp');
            $table->string('transactionsRoot');
            $table->integer('version')->unsigned();
            $table->string('winnerHash');
            $table->string('winnerType');

            $table->string('wallet')->index();
            $table->string('benificiaryWallet')->nullable()->index();

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
        Schema::dropIfExists('headers');
    }
}
