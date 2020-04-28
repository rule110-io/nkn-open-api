<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSigchainsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sigchains', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->unsignedBigInteger('payload_id')->unique()->index();

            $table->integer('nonce')->unsigned();
            $table->integer('dataSize')->unsigned();
            $table->string('blockHash');
            $table->string('srcId')->nullable();
            $table->string('srcPubkey')->index();
            $table->string('destId')->nullable();
            $table->string('destPubkey')->index();

            $table->dateTime('added_at');
            $table->dateTime('created_at');

            $table->foreign('payload_id')
            ->references('id')->on('payloads')
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
        Schema::dropIfExists('sigchains');
    }
}
