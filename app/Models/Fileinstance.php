<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fileinstance extends Model
{
	 /**
     * Set primary-key-name
     */
    protected $primaryKey = 'ID';

     /**
     * No automated timestamps
     */
    public $timestamps = false;

    /**
     * all mass assignable
     */
    protected $guarded = [];

    /**
     * File relationship
     */
    public function file()
    {
    	return $this->belongsTo(File::class,'ID','File_ID');
    }
}
