<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $fillable = ['visitable_type', 'visitable_id', 'visit_type', 'is_mobile', 'public_user_id', 'source'];

    public function setUpdatedAtAttribute($value)
    {
        // do nothing -- to disable updated_at
    }

    // public $visit_types = [
    //     0 => 'generic',
    //     1 => 'long',
    //     2 => 'download'
    // ]

    // public $source_types = [
    //     0 => 'proscholy',
    //     1 => 'regenschori',
    //     2 => 'app/android'
    //     3 => 'app/ios'
    // ]
}
