<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\OutgoingCall;
use App\Models\Lead;
use App\Models\User;
use Twilio\Rest\Client;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VoiceGrant;
use Twilio\TwiML\VoiceResponse;
use \stdClass;
use Twilio\Jwt\ClientToken;
use Illuminate\Support\Facades\Log;


class AgentController extends Controller
{
    /**
     * Create a new capability token
     *
     * @return \Illuminate\Http\Response
     */
    public function getToken(Request $request)
    {
//        $apiKey = env('API_KEY');
//        $apiSecret = env('API_SECRET');
        $accountSid = env('ACCOUNT_SID');
        $applicationSid = env('APPLICATION_SID');
        $authToken = env('AUTH_TOKEN');
        $clientToken = new ClientToken($accountSid, $authToken);

        // Create Voice grant
        $clientToken->allowClientOutgoing($applicationSid);

        $token = $clientToken->generateToken();
        return response()->json(['token' => $token]);

        // render token to string
    }

    public function makeOutgoingCall(Request $request)
    {
        $response = new VoiceResponse();
        $callerIdNumber = env('TW_NUMBER');
        $phoneNumberToDial = $request->input('To');

        $dial = $response->dial($phoneNumberToDial, [
            'callerId' => $callerIdNumber,
            'record' => true
        ]);
        $phoneNumberToDial = $request->input('phoneNumber');
//        if (isset($phoneNumberToDial)) {
//            $dial->number($phoneNumberToDial);
//        } else {
//            $dial->client('customer');
//        }

//        $outgoing = new OutgoingCall();
//        $outgoing->agent = auth()->user();
//        $outgoing->save();
        // $response->record();
        return $response;
    }

    public function getCallDetailsBySID($call_sid)
    {
        $sid = env('ACCOUNT_SID');
        $token = env('AUTH_TOKEN');
        $twilio = new Client($sid, $token);
        $call_details = $twilio->calls($call_sid)->fetch();
        return $call_details;
    }

    public function createOutgoingCall(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'To' => 'required|string',
            'callSid' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid Inputs!'
            ])->setStatusCode(400);
        }

        try {
            $leadId = null;
            $agentId = null;

            $lead = Lead::where('phone_number', $request->To)->first();
            $agentId = $request->user_id;

            if ($lead) {
                $leadId = $lead->id;
            }

            $call_details = $this->getCallDetailsBySID($request->callSid);
            $is_call = OutgoingCall::where("call_sid", $request->callSid)->first();

            if ($call_details && ! $is_call) {
                $outgoing_call = new OutgoingCall();
                $outgoing_call->call_sid = $request->callSid;
                $outgoing_call->call_status = $call_details->status ? $call_details->status : 'queued';
                $outgoing_call->duration = $call_details->duration ? $call_details->duration : 0;
                $outgoing_call->call_cost = $call_details->price ? round(abs($call_details->price), 3) : 0;
                $outgoing_call->agent_id = $agentId;
                $outgoing_call->lead_id = $leadId;
                $outgoing_call->lead_status = $request->leadStatus;
                $outgoing_call->remarks = $request->remarks ? $request->remarks : null;
                $outgoing_call->save();
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal Server Error!'
            ]);
        }
    }

    public function getAgentCallDetails(Request $request)
    {
        if ($request->role !== 'call_center_agent' && $request->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission'
            ])->setStatusCode(403);
        }

        /**
         * Validate the rtequest
         */
        $validator = Validator::make($request->all(), [
            'agentEmail' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid Inputs!'
            ])->setStatusCode(400);
        }

        $agent = User::where('email', $request->agentEmail)->first();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'error' => 'Agent not found!'
            ])->setStatusCode(400);
        }

        if (!$request->leadId) {
            $calls = OutgoingCall::where('agent_id', $agent->id)->get();
        } else {
            $calls = OutgoingCall::where('agent_id', $agent->id)
                ->where('lead_id', $request->leadId)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        $response = [];

        if ($calls && count($calls) > 0) {
            foreach ($calls as $call) {
                $res = new StdClass;
                $res->name = $call->lead ? $call->lead->first_name . " " . $call->lead->last_name : '';
                $res->email = $call->lead ? $call->lead->email : '';
                $res->callSid = $call->call_sid;
                $res->startDate = $call->created_at;
                $res->duration = $call->duration;
                $res->callStatus = $call->call_status;
                $res->leadStatus = $call->lead_status;
                $res->remarks = $call->remarks;
                array_push($response, $res);
            }
        }

        return response()->json([
            'success' => true,
            'calls' => $response
        ])->setStatusCode(200);
    }

    public function updateCallDetails(Request $request)
    {
        if ($request->role !== 'call_center_agent' && $request->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission'
            ])->setStatusCode(403);
        }

        /**
         * Validate the rtequest
         */
        $validator = Validator::make($request->all(), [
            'callSid' => 'required|string|max:255',
            'leadStatus' => 'required',
            'remarks' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid Inputs!'
            ])->setStatusCode(400);
        }

        try {
            $call_details = OutgoingCall::where('call_sid', $request->callSid)->first();

            if (!$call_details) {
                return response()->json([
                    'success' => 'false',
                    'error' => 'Invalid call sid!'
                ]);
            }

            $call_details->lead_status = $request->leadStatus;
            $call_details->remarks = $request->remarks;
            $call_details->update();

            return response()->json([
                'success' => false,
                'error' => 'Call Details Updated successfully!'
            ])->setStatusCode(200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal Server error!'
            ])->setStatusCode(500);
        }
    }

    public function updateLeadComments(Request $request)
    {
        if ($request->role !== 'call_center_agent' && $request->role !== 'admin') {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission'
            ])->setStatusCode(403);
        }

        $lead = Lead::find($request->leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'error' => 'Lead doesnot exist'
            ])->setStatusCode(400);
        }

        $lead->comments = $request->comments ? $request->comments : null;

        $lead->update();

        return response()->json([
            'success' => true,
            'data' => 'Comment Added successfully!'
        ]);
    }

    public function getAllAgents() {
        $result = [];
        $agents = Agent::all();

        foreach ($agents as $agent) {
            try {
                $user = $agent->user;
                $userProfile = $user->userProfile;
                $supervisor = $agent->supervisor;
                $outgoingCalls = $agent->outgoingCalls;

                $data = new StdClass();
                $data->id = strval($agent->id);
                $data->fullName = $user->first_name . ' ' . $user->last_name;
                $data->needHelp = $agent->need_help;
                $data->availability = $agent->availability;

                if ($supervisor) {
                    $data->supervisor = $supervisor->id;
                    $data->teamLead = $supervisor->user->first_name . ' ' . $supervisor->user->last_name;
                }

                if ($userProfile) {
                    $data->avatar = $userProfile ? $userProfile->profile_image : '';
                }

                if (empty($outgoingCalls)) {
                    $data->totalCalls = 61;
                    $data->totalCallsTime = '117:21:21';
                    $data->leadsToCustomers = '51:27';
                    $data->totalCallsCost = 48.59;
                }

                array_push($result, $data);
            } catch (\Exception $e) {}
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function assignAgent(Request $request)
    {
        $agentId = $request->input('agentId');
        $supervisorId = $request->input('supervisorId');
        $agent = Agent::find($agentId);
        $agent->supervisor_id = $supervisorId;
        $agent->update();

        return response()->json([
            'success' => true,
            'data' => 'Agent assigned successfully!'
        ]);
    }

    public function unassignAgent(Request $request)
    {
        $agentId = $request->input('agentId');
        $agent = Agent::find($agentId);
        $agent->supervisor_id = null;
        $agent->update();

        return response()->json([
            'success' => true,
            'data' => 'Agent unassigned successfully!'
        ]);
    }
}
