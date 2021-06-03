<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;



class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //password $2y$10$UzN5SbYx7atUgqYniORapORWuptWjTt27L.7th7wc3I7qfBh2Ac6S : Secret!
        DB::table('users')->insert([
            'first_name' => 'Ilia',
            'last_name' => 'Svinin',
            'email' => 'ilia.svinin@gmail.com',
            'password' =>'$2y$10$NoeIggc0yyZJnfYty0OFj.trOKQRyxBSubY0jSvRAniD0sqQAkQoa',
            'role'=>'admin',
            'email_verified_at'=> Carbon::now(),
            'email_verified_by'=>'ilia.svinin@gmail.com'
        ]);
    }
}
