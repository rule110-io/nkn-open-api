<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePayloadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payloads', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->unsignedBigInteger('transaction_id')->unique()->index();

            $table->string('payloadType')->index();
            $table->string('sender')->nullable()->index();
            $table->string('senderWallet')->nullable()->index();
            $table->string('recipient')->nullable()->index();
            $table->string('recipientWallet')->nullable()->index();
            $table->bigInteger('amount')->nullable();
            $table->string('submitter')->nullable()->index();
            $table->string('registrant')->nullable()->index();
            $table->string('registrantWallet')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('subscriber')->nullable()->index();
            $table->string('identifier')->nullable();
            $table->string('topic')->nullable();
            $table->integer('bucket')->unsigned()->nullable()->index();
            $table->integer('duration')->unsigned()->nullable();
            $table->text('meta')->nullable();
            $table->string('public_key')->nullable();
            $table->bigInteger('registration_fee')->nullable();
            $table->text('nonce')->nullable();
            $table->integer('txn_expiration')->unsigned()->nullable();
            $table->string('symbol')->nullable();
            $table->bigInteger('total_supply')->nullable();
            $table->integer('precision')->unsigned()->nullable();
            $table->integer('nano_pay_expiration')->unsigned()->nullable();

            $table->string('signerPk')->nullable()->index();

            $table->dateTime('added_at');
            $table->dateTime('created_at');

            $table->foreign('transaction_id')
                ->references('id')->on('transactions')
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
        Schema::dropIfExists('payloads');
    }
}
