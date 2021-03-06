<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';
    protected $fillable = [
        'service_stages_id',
        'date_start_prog',
        'name',
        'description',
        'date_end_prog',
        'date_start',
        'date_end',
        'observation',
        'status'
    ];

    public function serviceStage() {
        return $this->belongsTo( 'App\Models\ServiceStage', 'service_stages_id', 'id' );
    }

    public function AssignedWorkers() {
        return $this->hasMany( 'App\Models\AssignedWorker', 'tasks_id', 'id' );
    }

    public function getTasks( $stage, $search = '' ) {
        $data = self::where( 'service_stages_id', $stage )
            ->where( 'status', '!=', 2 )
            ->orderBy( 'date_start_prog', 'asc' )
            ->orderBy( 'date_end_prog', 'asc' )
            ->search( $search )
            ->get();

        return $data;
    }

    public function scopeSearch( $query, $search ) {
        if( ! empty( $search ) ) {
            return $query->where( 'name', '%', $search . '%' );
        }
        return $query;
    }
}
