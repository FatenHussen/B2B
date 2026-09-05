<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    public $timestamps = false;

    protected $fillable = ['packing_job_id', 'package_no', 'qr_token'];
}
