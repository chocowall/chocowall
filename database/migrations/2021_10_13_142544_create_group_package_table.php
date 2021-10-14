<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupPackageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_package', function (Blueprint $table) {
            $table->id();
            // $table->primary(['group_id','nuget_packages_id']);
            $table->bigInteger('group_id')->unsigned();
            $table->bigInteger('package_id')->unsigned();
            $table->timestamps();
            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->onDelete('cascade');;
            $table->foreign('package_id')
                ->references('id')
                ->on('packages')
                ->onDelete('cascade');;
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_package');
    }
}
