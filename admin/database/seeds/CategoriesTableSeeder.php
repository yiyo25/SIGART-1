<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $insert = [
            [
                'name' => 'Otros Servicios',
                'status' => 1,
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ],
            [
                'name' => 'Madera',
                'status' => 1,
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ],
            [
                'name' => 'Pintura',
                'status' => 1,
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ],
            [
                'name' => 'Metal',
                'status' => 1,
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ],
            [
                'name' => 'Barniz',
                'status' => 1,
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ],
            [
                'name' => 'Pegamento',
                'status' => 1,
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ],
            [
                'name' => 'Laca',
                'status' => 1,
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ],
            [
                'name' => 'Puertas',
                'status' => 1,
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ],
            [
                'name' => 'Ventanas',
                'status' => 1,
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ],
            [
                'name' => 'Otros',
                'status' => 1,
                'created_at' => date( 'Y-m-d H:i:s' ),
                'updated_at' => date( 'Y-m-d H:i:s' )
            ]
        ];

        DB::table( 'categories' )->insert( $insert );
    }
}
