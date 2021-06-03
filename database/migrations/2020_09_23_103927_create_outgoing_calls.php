<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOutgoingCalls extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('outgoing_calls', function (Blueprint $table) {
            $table->id();
            $table->string('call_sid');
            $table->string('call_status');
            $table->string('duration');
            $table->string('call_cost');
            $table->foreignId('agent_id')->nullable();
            $table->foreignId('lead_id')->nullable();
            $table->enum('lead_status',['pending','approved','rejected']);
            $table->longText('remarks')->nullable();
            $table->timestamps();
            $table->index('call_sid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('outgoing_calls');
    }
}
