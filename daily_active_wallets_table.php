<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDailyActiveWalletsTable extends Migration
{
    public function up()
    {
        Schema::create('daily_active_wallets', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('count')->default(0);
            $table->json('active_wallets')->nullable();
            $table->timestamps();

            $table->unique('date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_active_wallets');
    }
}
