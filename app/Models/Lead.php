<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Lead extends Model
{
    protected $table = 'leads';

     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'full_name',
        'email',
        'phone_number',
        'gender',
        'industry',
        'address',
        'country',
        'state',
        'created_by',
        'assigned_to',
        'company_info_id'
    ];

    public function companyInfo()
    {
        return $this->belongsTo(LeadCompanyInfo::class, 'company_info_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
