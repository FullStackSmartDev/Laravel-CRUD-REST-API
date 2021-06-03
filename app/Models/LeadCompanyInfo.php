<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class LeadCompanyInfo extends Model
{
    protected $table = 'leads_company_info';

     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'company_name',
        'company_email',
        'website_url',
        'company_number',
        'company_address',
        'country',
        'state',
        'facebook_url',
        'twitter_url',
        'linkedin_url'
    ];
}
