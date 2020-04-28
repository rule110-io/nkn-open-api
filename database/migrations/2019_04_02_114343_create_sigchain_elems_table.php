<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSigchainElemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sigchain_elems', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sigchain_id')->index();

            $table->string('id2')->index();
            $table->string('pubkey')->index();
            $table->string('wallet')->index();
            $table->string('nextPubkey')->nullable();
            $table->boolean('mining')->index();
            $table->string('sigAlgo');
            $table->string('signature');
            $table->string('vrf')->nullable();
            $table->string('proof')->nullable();

            $table->dateTime('added_at');
            $table->dateTime('created_at');

            $table->foreign('sigchain_id')
            ->references('id')->on('sigchains')
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
        Schema::dropIfExists('sigchain_elems');
    }
}
