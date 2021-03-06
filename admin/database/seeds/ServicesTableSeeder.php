<?php

use Illuminate\Database\Seeder;

class ServicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $insert = [];
        $count = 1;

        while( $count <= 50 ) {
            $insert[] = [
                'service_requests_id' => $count,
                'user_reg' => rand( 1, 52 ),
                'date_reg' => date( 'Y-m-d' ),
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ];
            $count++;
        }


        DB::table( 'services' )->insert( $insert );
    }
}
