<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProgramsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->unsignedBigInteger('transaction_id')->index();

            $table->string('code')->nullable();
            $table->string('parameter')->nullable();
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
        Schema::dropIfExists('programs');
    }
}
