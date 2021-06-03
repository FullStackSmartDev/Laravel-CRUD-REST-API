<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\Models\User;
use App\Models\Lead;
use App\Models\OutgoingCall;
use App\Models\LeadCompanyInfo;
use File;
use Illuminate\Support\Facades\Validator;
use \stdClass;


class LeadController extends Controller
{
    /**
     * Creating a new lead.
     *
     * @return \Illuminate\Http\Response
     */
    public function createLead(Request $request)
    {
        if ($request->role !== 'admin' && $request->role !== 'lead_generator') {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to create lead'
            ])->setStatusCode(403);
        }

        /**
         * Validate a lead
         * Validate unique email and phone number
         */
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|string',
            'phoneNumber' => 'required',
            'gender' => 'required',
            'industry' => 'required',
            'streetAddress' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'country' => 'required',
            'state' => 'required',
            'zipCode' => 'required',
            'createdBy' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid Inputs!'
            ])->setStatusCode(400);
        }

        $isValidLeadCredential = Lead::where('email', $request->email)->orWhere('phone_number', $request->phoneNumber)->get();

        if (count($isValidLeadCredential) > 0) {
            return response()->json([
                'success' => false,
                'error' => 'Please enter unique email and phone number'
            ])->setStatusCode(400);
        }

        try {
            $createdBy = User::where('email', $request->createdBy)->first();
            $createdById = '';

            if ($createdBy) {
                $createdById = $createdBy->id;
            }

            $agents = User::where('role', 'call_center_agent')->pluck('id')->toArray();
            $assignee = 1;

            if (count($agents) > 0) {
                $assignee = $agents[array_rand($agents)];
            }

            DB::beginTransaction();

            $leadCompanyInfo = new LeadCompanyInfo();

            if ($request->companyName && $request->companyEmail && $request->companyNumber) {
                $leadCompanyInfo->company_name = $request->companyName;
                $leadCompanyInfo->company_email = $request->companyEmail;
                $leadCompanyInfo->website_url = $request->websiteUrl ? $request->websiteUrl : '';
                $leadCompanyInfo->company_number = $request->companyNumber ? $request->companyNumber : '';
                $leadCompanyInfo->street_address = $request->companyStreetAddress;
                $leadCompanyInfo->unit_number = $request->companyUnitNumber ? $request->companyUnitNumber : null;
                $leadCompanyInfo->city = $request->companyCity;
                $leadCompanyInfo->country = $request->companyCountry;
                $leadCompanyInfo->state = $request->companyState;
                $leadCompanyInfo->zip_code = $request->companyZipCode;
                $leadCompanyInfo->facebook_url = $request->facebookUrl ? $request->facebookUrl : '';
                $leadCompanyInfo->twitter_url = $request->twitterUrl ? $request->twitterUrl : '';
                $leadCompanyInfo->linkedin_url = $request->linkedinUrl ? $request->linkedinUrl : '';
                $leadCompanyInfo->save();
            }

            $lead = new Lead();
            $lead->first_name = $request->firstName;
            $lead->last_name = $request->lastName;
            $lead->email = $request->email;
            $lead->phone_number = $request->phoneNumber;
            $lead->gender = $request->gender;
            $lead->industry = $request->industry;
            $lead->street_address = $request->streetAddress;
            $lead->unit_number = $request->unitNumber ? $request->unitNumber : null;
            $lead->city = $request->city;
            $lead->state = $request->state;
            $lead->country = $request->country;
            $lead->zip_code = $request->zipCode;
            $lead->created_by = $createdById;
            $lead->assigned_to = $assignee;
            $lead->company_info_id = $leadCompanyInfo && $leadCompanyInfo->id ? $leadCompanyInfo->id : null;
            $lead->comments = null;

            $lead->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => 'Lead generated successfully!',
            ])->setStatusCode(200);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Something went wrong!'
            ])->setStatusCode(500);
        }
    }

    /**
     * Display all leads in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getLeads(Request $request)
    {
        // Only Lead Generator,Agent and Admin will have access to view the Lead
        if ($request->role !== 'admin' && $request->role != 'lead_generator' && $request->role !== 'call_center_agent') {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to create lead'
            ])->setStatusCode(403);
        }

        $result = [];
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not found'
            ])->setStatusCode(500);
        }

        if ($request->role === 'lead_generator') {
            $leads = Lead::where('created_by', $user->id)->get();
        } else if ($request->role === 'call_center_agent') {
            $leads = Lead::where('assigned_to', $user->id)->get();
        } else {
            $leads = Lead::all();
        }

        foreach ($leads as  $index => $lead) {
            $response = new StdClass;

            if ($request->role === 'admin') {
                $response->assignedTo = $lead->assignee ? $lead->assignee->email : '';
                $response->createdBy = $lead->createdBy->email;
            }

            $response = $this->getLeadObject($lead);
            array_push($result, $response);
        }

        return response()->json([
            'success' => true,
            'leads' => $result
        ]);
    }

    /**
     * Convert CSV file to array.
     * Accepts CSV file as request
     * Returns array of object
     */
    function csvToArray($filename = '', $delimiter = ',')
    {
        $header = null;
        $data = array();

        if (($handle = fopen($filename, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false)
            {
                if (!$header)
                    $header = $row;
                else
                    $data[] = array_combine($header, $row);
            }

            fclose($handle);
        }

        return $data;
    }


    /**
     * Validate Lead request.
     * Accept Lead Object
     * Returns true/false based on whether input lead is valid or not
     */
    function validateLeadRequest($lead)
    {
        $isValid = true;

        if ($lead['companyName'] && $lead['companyEmail'] && $lead['companyNumber']) {
            if (!$lead['firstName'] || !$lead['lastName'] || !$lead['email'] || !$lead['phoneNumber'] || ! $lead['gender']
            || !$lead['industry'] || !$lead['country'] || !$lead['state'] || !$lead['companyName']
            || ! $lead['companyEmail'] || !$lead['companyNumber'] || !$lead['companyStreetAddress'] ||
             !$lead['companyCity'] || !$lead['companyZipCode'] || !$lead['companyCountry'] || !$lead['companyState']) {
                $isValid = false;
            } else {
                if (in_array($lead['gender'], ['male', 'female', 'other'])) {
                    $isValid = true;
                    if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL) || !filter_var($lead['companyEmail'], FILTER_VALIDATE_EMAIL)) {
                        $isValid = false;
                    }
                } else {
                    $isValid = false;
                }
            }
        } else {
            if (!$lead['firstName'] || !$lead['lastName'] || ! $lead['email'] || ! $lead['phoneNumber'] || ! $lead['gender']
            || ! $lead['industry'] || ! $lead['country'] || ! $lead['state'] ) {
                $isValid = false;
            } else {
                if (in_array($lead['gender'], ['male', 'female', 'other'])) {
                    $isValid = true;
                    if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
                        $isValid = false;
                    }
                } else {
                    $isValid = false;
                }
            }
        }

        return $isValid;
    }

    public function getLeadObject($lead)
    {
        $response = new StdClass;
        $response->leadId = $lead->id;
        $response->firstName = $lead->first_name;
        $response->lastName = $lead->last_name;
        $response->email = $lead->email;
        $response->gender = $lead->gender;
        $response->phoneNumber = $lead->phone_number;
        $response->industry = $lead->industry;
        $response->streetAddress = $lead->street_address;
        $response->unitNumber = $lead->unit_number ? $lead->unit_number : '';
        $response->city = $lead->city;
        $response->state = $lead->state;
        $response->country = $lead->country;
        $response->zipCode = $lead->zip_code;
        $response->companyName = $lead->company_info_id ? $lead->companyInfo->company_name : '';
        $response->companyEmail = $lead->company_info_id ? $lead->companyInfo->company_email : '';
        $response->websiteUrl = $lead->company_info_id  && $lead->companyInfo->website_url ? $lead->companyInfo->website_url : '';
        $response->companyNumber = $lead->company_info_id && $lead->companyInfo->company_number ? $lead->companyInfo->company_number : '';
        $response->companyStreetAddress = $lead->company_info_id ? $lead->companyInfo->street_address : '';
        $response->companyUnitNumber = $lead->company_info_id && $lead->companyInfo->unit_number ? $lead->companyInfo->unit_number : '';
        $response->companyCity = $lead->company_info_id ? $lead->companyInfo->city : '';
        $response->companyState = $lead->company_info_id ? $lead->companyInfo->state : '';
        $response->companyCountry = $lead->company_info_id ? $lead->companyInfo->country : '';
        $response->companyZipCode = $lead->company_info_id ? $lead->companyInfo->zip_code : '';
        $response->companyFacebookUrl = $lead->company_info_id  && $lead->companyInfo->facebook_url ? $lead->companyInfo->facebook_url : '';
        $response->companyTwitterUrl = $lead->company_info_id  && $lead->companyInfo->twitter_url ? $lead->companyInfo->twitter_url : '';
        $response->companyLinkedinUrl = $lead->company_info_id && $lead->companyInfo->linkedin_url ? $lead->companyInfo->linkedin_url : '';
        return $response;
    }

     /**
     * Bulk store lead.
     *
     * @return \Illuminate\Http\Response
     */
    public function bulkCreateLead(Request $request)
    {
        $user = User::find($request->user_id);

        if ($user->role !== 'admin' && $user->role !== 'lead_generator') {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to create lead'
            ])->setStatusCode(403);
        }

        $leads = $request->leads;
        $errorLead = [];
        $leads = $this->csvToArray($request->file);

        if ($leads && count($leads) > 0) {
            foreach ($leads as $leadObj) {
                $isLeadValid = $this->validateLeadRequest($leadObj);
                $err = new StdClass;

                if (!$isLeadValid) {
                    $err->email = $leadObj['email'];
                    $err->phoneNumber = $leadObj['phoneNumber'];
                    $err->errorMessage = 'Invalid Inputs';
                }

                $isLeadUnique = Lead::where('email', $leadObj['email'])->orWhere('phone_number', $leadObj['phoneNumber'])->get();

                if (count($isLeadUnique) > 0) {
                    $errorObj = (array) $err;

                    if (count ($errorObj) == 0) {
                        $err->email = $leadObj['email'];
                        $err->phoneNumber = $leadObj['phoneNumber'];
                        $err->errorMessage = 'Please enter unique Email/Phone number';
                    }
                }

                if ($isLeadValid && count($isLeadUnique) == 0) {
                    try {
                        $agents = User::where('role', 'call_center_agent')->pluck('id')->toArray();
                        $assignee = 1;

                        if (count($agents) > 0 ) {
                            $assignee = $agents[array_rand($agents)];
                        }

                        DB::beginTransaction();
                        $leadCompanyInfo = new LeadCompanyInfo();

                        if ($leadObj['companyEmail'] && $leadObj['companyNumber']) {
                            $leadCompanyInfo->company_name = $leadObj['companyName'];
                            $leadCompanyInfo->company_email = $leadObj['companyEmail'];
                            $leadCompanyInfo->website_url = $leadObj['websiteUrl'] ? $leadObj['websiteUrl'] : null;
                            $leadCompanyInfo->company_number = $leadObj['companyNumber'] ? $leadObj['companyNumber'] : null;
                            $leadCompanyInfo->street_address = $leadObj['companyStreetAddress'];
                            $leadCompanyInfo->unit_number = $leadObj['companyUnitNumber'] ? $leadObj['companyUnitNumber'] : null;
                            $leadCompanyInfo->city = $leadObj['companyCity'];
                            $leadCompanyInfo->country = $leadObj['companyCountry'];
                            $leadCompanyInfo->state = $leadObj['companyState'];
                            $leadCompanyInfo->zip_code = $leadObj['companyZipCode'];
                            $leadCompanyInfo->facebook_url = $leadObj['facebookUrl'] ? $leadObj['facebookUrl'] : null;
                            $leadCompanyInfo->twitter_url = $leadObj['twitterUrl'] ? $leadObj['twitterUrl'] : null;
                            $leadCompanyInfo->linkedin_url = $leadObj['linkedinUrl'] ? $leadObj['linkedinUrl'] : null;
                            $leadCompanyInfo->save();
                        }

                        $lead = new Lead();
                        $lead->first_name = $leadObj['firstName'];
                        $lead->last_name = $leadObj['lastName'];
                        $lead->email = $leadObj['email'];
                        $lead->phone_number = $leadObj['phoneNumber'];
                        $lead->gender = $leadObj['gender'];
                        $lead->industry = $leadObj['industry'];
                        $lead->street_address = $leadObj['streetAddress'];
                        $lead->unit_number = $leadObj['unitNumber'] ? $leadObj['unitNumber'] : null;
                        $lead->city = $leadObj['city'];
                        $lead->state = $leadObj['state'];
                        $lead->country = $leadObj['country'];
                        $lead->zip_code = $leadObj['zipCode'];
                        $lead->created_by = $user->id;
                        $lead->assigned_to = $assignee;
                        $lead->company_info_id = $leadCompanyInfo && $leadCompanyInfo->id ? $leadCompanyInfo->id : null;
                        $lead->comments = null;
                        $lead->save();
                        DB::commit();
                    } catch (Exception $e) {
                        DB::rollback();
                        $err->email = $leadObj['email'];
                        $err->phoneNumber = $leadObj['phoneNumber'];
                        $err->errorMessage = 'Please enter unique Email/Phone number';
                    }
                }

                $errorArray = (array) $err;

                if (count($errorArray) > 0) {
                    array_push($errorLead, $err);
                }
            }

            $statusCode = count($errorLead) > 0 ? 400 : 200;
            $isSuccess = count($errorLead) > 0 ? false : true;

            return response()->json([
                'success' => $isSuccess,
                'error' => $errorLead
            ])->setStatusCode($statusCode);
        }
    }

    public function searchLead(Request $request)
    {
        try {
            $search_query = $request->searchQuery;
            $result = [];

            if ($search_query && strlen($search_query) > 2) {
                $leads = DB::table('leads')
                    ->where('first_name', 'like', $search_query .'%')
                    ->orWhere('email', 'like', $search_query .'%')
                    ->orWhere('phone_number', 'like', $search_query .'%')
                    ->get();
            } else {
                $leads = Lead::all();
            }

            if (count($leads) > 0) {
                foreach ($leads as  $index => $lead) {
                    $response = new StdClass;

                    if ($lead->company_info_id) {
                        $leadCompanyInfo = LeadCompanyInfo::find($lead->company_info_id);
                    }

                    if ($request->role === 'admin') {
                        $response->assignedTo = $lead->assignee ? $lead->assignee->email : '';
                        $response->createdBy = $lead->createdBy->email;
                    }

                    $response = $this->getLeadObject($lead);
                    array_push($result, $response);
                }
            }

            return response()->json([
                'success' => true,
                'leads' => $result
            ])->setStatusCode(200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'leads' => [],
                'error' => 'Something went wrong!'
            ])->setStatusCode(500);
        }
    }

    public function getLeadById(Request $request)
    {
        if ($request->role !== 'admin' && $request->role != 'lead_generator' && $request->role !== 'call_center_agent') {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to create lead'
            ])->setStatusCode(403);
        }

        $lead = Lead::find($request->leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'error' => 'Lead not found!'
            ])->setStatusCode(400);
        }

        $response = $this->getLeadObject($lead);
        $latest_call_details = OutgoingCall::where('lead_id', $request->leadId)
            ->orderBy('created_at', 'desc')
            ->limit(1)
            ->first();

        $response->currentLeadStatus = $latest_call_details && $latest_call_details->lead_status ? $latest_call_details->lead_status : '';
        $response->lastRemark = $latest_call_details && $latest_call_details->remarks ? $latest_call_details->remarks : '';

        return response()->json([
            'success' => true,
            'lead' => $response
        ])->setStatusCode(200);
    }
}
