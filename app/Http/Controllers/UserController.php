<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Supervisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Mail\RegisterationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\BlockedUser;
use JWTAuth;
use JWTAuthException;
use Carbon\Carbon;
use DB;
use \stdClass;


class UserController extends Controller
{
    /**
     * Register the user.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    private function getToken($email, $password)
    {
        $token = null;

        try {
            if (!$token = JWTAuth::attempt( [
                'email' => $email,
                'password' => $password
            ])) {
                return response()->json([
                    'response' => 'error',
                    'message' => 'Password or email is invalid',
                    'token' => $token
                ]);
            }
        } catch (JWTAuthException $e) {
            return response()->json([
                'response' => 'error',
                'message' => 'Token creation failed',
            ]);
        }

        return $token;
    }

    public function updatePassword(Request $request)
    {
        $user = User::where('id', $request->user_id)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'status' => 201,
            'success'=> true,
            'message' => "password successfully updated"
        ]);
    }

    public function updateUserInfo(Request $request)
    {
        $user = User::where('id', $request->userId)->first();
        $user->first_name = $request->firstName;
        $user->last_name = $request->lastName;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'status' => 201,
            'success' => true,
            'message' => "user info successfully updated"
        ]);
    }

    public function registerUser(Request $request)
    {
        // Validate all the required parameters have been sent.
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
            ]);
        }

        try {
            $error = '';
            $is_user_exist = User::where('email', $request->email)->first();

            if($is_user_exist) {
                $error = 'User already exist!';
            } else {
                $user = new User();
                $user->first_name = $request->firstName;
                $user->last_name = $request->lastName;
                $user->email = $request->email;
                $user->password = Hash::make($request->password);
                $user->role = $request->role;

                if ($user->save()) {
                    $token = Str::random('130');
                    // $token = self::getToken($request->email, $request->password); // generate user token
                    $user->auth_token = $token; // update user token
                    $user->update();

                    if ($user->role == 'call_center_agent') {
                        $agent = new Agent();
                        $user->agent()->save($agent);
                    } else if ($user->role == 'call_agent_supervisor') {
                        $supervisor = new Supervisor();
                        $user->supervisor()->save($supervisor);
                    }

                    return response()->json([
                        'status' => 201,
                        'success' => true,
                        'access_token' => $token,
                        'data' => [
                            'firstName' => $user->first_name,
                            'lastName' => $user->last_name,
                            'email' => $user->email,
                            'role' => $user->role
                        ]
                    ]);
                } else {
                  $error = 'Something went wrong!Please try again';
                }
            }

            return response()->json([
                'success' => false,
                'error' => $error
            ])->setStatusCode(400);
        } catch (Exception $e) {
           throw $e;
        }
    }

    /**
     * Login user.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->where('deleted_at', null)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            if(is_null($user->email_verified_by)) {
                return response()->json([
                    'success' => false,
                    'error' => 'User Account not approved!'
                ])->setStatusCode(400);
            } else {
                $is_block_user = $user->block;

                if(is_null($is_block_user) || !$is_block_user->block_status) {
                    $token = Str::random('130');
                    // $token = self::getToken($request->email, $request->password);
                    $user->auth_token = $token;
                    $user->save();

                    return response()->json([
                        'success' => true,
                        'auth_token' => $token,
                        'user' => [
                            'id' => $user->id,
                            'firstName' => $user->first_name,
                            'lastName' => $user->last_name,
                            'email' => $user->email,
                            'role' => $user->role
                        ]
                    ])->setStatusCode(200);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'You are blocked by the admin.'
                    ])->setStatusCode(400);
                }
            }
        } else {
            return response()->json([
                'success' => false,
                'error' => 'Invalid Login Credentials'
            ])->setStatusCode(400);
        }
    }

    public function getPendingAccounts(Request $request)
    {
        $pageNumber = $request->pageNumber ? $request->pageNumber : 0;
        $pageLimit = $request->pageLimit ? $request->pageLimit : 10;

        $pendingAccounts = User::select('first_name', 'last_name', 'email', 'role')
            ->where('email_verified_at', null)
            ->where('role', '!=', 'admin')
            ->where('deleted_at', null)
            ->skip($pageNumber)
            ->take($pageLimit)
            ->get();

        return response()->json([
            'success' => true,
            'pending_records' => $pendingAccounts
        ])->setStatusCode(200);
    }

    public function updateAccountStatus(Request $request)
    {
        // Validate all the required parameters have been sent.
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'email' => 'required|string|max:255',
            'verifiedBy' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => 'Invalid Inputs!'
            ])->setStatusCode(400);
        }

        try {
            $user = User::where('email', $request->email)->first();
            $name = $user->first_name . " " . $user->last_name;

            if ($request->status) {
                $user->email_verified_by = $request->verifiedBy;
                $user->email_verified_at = Carbon::now();
                $user->update();
                $response = 'User Account Approved successfully!';
            } else {
                $user->delete();
                $response = 'User Account Rejected successfully!';
            }

            Mail::to("asalheen1997@gmail.com")->queue(new RegisterationMail($request->status, $name));

            return response()->json([
                'success' => true,
                'data' => $response
            ])->setStatusCode(200);
        } catch(Exception $e) {
            return response()->json([
                'status' => '500',
                'success' => false,
                'data' => $e
            ])->setStatusCode(500);
        }
    }

    public function getAllUsers(Request $request)
    {
        if ($request->role !== 'admin') {
            return response()->json([
                'success' => false,
                'data' => 'You do not have permission'
            ])->setStatusCode(403);
        }
        // $pageNumber = $request->pageNumber ? $request->pageNumber : 1;
        // $pageLimit = $request->pageLimit ? $request->pageLimit : 10;
        // $offset = ($pageNumber-1) * $pageLimit;

        $users = User::where('deleted_at', null)
            ->where('email_verified_at', '!=', null)
            ->where('role', '!=', 'admin')
            ->get();
        $response = [];

        foreach($users as $key => $user){
            $res = new StdClass;
            $res->userProfile = new StdClass;
            $blockedUser = $user->block;
            $res->id = $user->id;
            $res->firstName = $user->first_name;
            $res->lastName = $user->last_name;
            $res->email = $user->email;
            $res->role = $user->role;
            $res->blockStatus =! empty($blockedUser) && $blockedUser->block_status == 1 ? true : false;
            $res->blockReason =! empty($blockedUser) && $blockedUser->block_reason ? $blockedUser->block_reason : '';

            $userProfile = UserProfile::where('user_id', '=', $user->id)->first();

            if (!is_null($userProfile)) {
                $res->userProfile->gender = $userProfile->gender;
                $res->userProfile->mobile = $userProfile->mobile;
                $res->userProfile->profileImage = $userProfile->profile_image;
                $res->userProfile->address = $userProfile->address;
                $res->userProfile->state = $userProfile->state;
                $res->userProfile->country = $userProfile->country;
            } else {
                $res->userProfile = null;
            }

            array_push($response, $res);
        }

        return response()->json([
            'success' => true,
            'data' => $response
        ])->setStatusCode(200);
    }

    public function deactivateUser(Request $request)
    {
        if ($request->role !== 'admin') {
            return response()->json([
                'success' => false,
                'data' => 'You do not have permission'
            ])->setStatusCode(403);
        }

        $deactivated_user = User::where('email', $request->email);

        if (is_null($deactivated_user)) {
            return response()->json([
                'success' => false,
                'data' => 'User does not exist!'
            ])->setStatusCode(400);
        } else{
            $deactivated_user->delete();
            return response()->json([
                'success' => true,
                'data' => 'User Deactivated successfully'
            ])->setStatusCode(200);
        }
    }

    public function updateBlockStatus(Request $request)
    {
        if ($request->role !== 'admin') {
            return response()->json([
                'success' => false,
                'data' => 'You do not have permission'
            ])->setStatusCode(403);
        }

        $validator = Validator::make($request->all(), [
            'blockStatus' => 'required|boolean',
            'blockReason' => 'string|max:255',
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => 'Invalid Inputs!'
            ])->setStatusCode(400);
        }

        try{
            $user = User::where('email', $request->email)->first();

            if (is_null($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid User'
                ])->setStatusCode(400);
            }

            $block_user = $user->block;

            if (is_null($block_user)) {
                $block_user = new BlockedUser;
                $block_user->user_id = $user->id;
                $block_user->block_status = $request->blockStatus;
                $block_user->block_reason = $request->blockReason;
                $block_user->save();
            } else {
                $block_user->block_status = $request->blockStatus;
                $block_user->block_reason = $request->blockReason;
                $block_user->update();
            }

            $user->auth_token = null;
            $user->update();

            return response()->json([
                'success' => true,
                'data' => 'User Block Status changed successfully!'
            ])->setStatusCode(201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e
            ])->setStatusCode(500);
        }
    }

    public function registerUserByAdmin(Request $request)
    {
        if ($request->createBy !== 'admin') {
            return response()->json([
                'success' => false,
                'data' => 'You do not have permission'
            ])->setStatusCode(403);
        }

        // Validate all the required parameters have been sent.
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid input',
            ]);
        }

        try {
            $error = '';
            $is_user_exist = User::where('email', $request->email)->first();

            if ($is_user_exist) {
                $error = 'User already exist!';
            } else {
                $user = new User();
                $user->first_name = $request->firstName;
                $user->last_name = $request->lastName;
                $user->email = $request->email;
                $user->password = Hash::make($request->password);
                $user->role = $request->role;

                if ($user->save()) {
                    $token = Str::random('130');
                    $user->auth_token = $token; // update user token
                    $user->update();

                    if ($user->role == 'call_center_agent') {

                        if ($request->supervisor && $request->supervisor != -1) {
                            $agent = new Agent(['supervisor_id' => $request->supervisor]);
                        } else {
                            $agent = new Agent();
                        }

                        $user->agent()->save($agent);
                    } else if ($user->role == 'call_agent_supervisor') {
                        $supervisor = new Supervisor();
                        $user->supervisor()->save($supervisor);
                    }

                    return response()->json([
                        'status' => 201,
                        'success' => true,
                        'access_token' => $token,
                        'data' => [
                            'firstName' => $user->first_name,
                            'lastName' => $user->last_name,
                            'email' => $user->email,
                            'role' => $user->role
                        ]
                    ]);
                } else {
                  $error = 'Something went wrong!Please try again';
                }
            }

            return response()->json([
                'success' => false,
                'error' => $error
            ])->setStatusCode(400);
        } catch (Exception $e) {
            return $this->responseServerError('Error creating user profile.');
        }
    }

    public function searchUser(Request $request)
    {
        try {
            $search_query = $request->searchQuery;
            $result = [];

            if ($search_query && strlen($search_query) > 2) {
                $users = DB::table('users')
                    ->where('first_name', 'like', $search_query . '%')
                    ->orWhere('email', 'like', $search_query . '%')
                    ->get();
            } else {
                $users = User::all();
            }

            if (count($users) > 0) {
                foreach ($users as $index => $user) {
                    if (!$user->deleted_at && $user->role !== 'admin' && $user->email_verified_at) {
                        $response = new StdClass;
                        $blockedUser = BlockedUser::where('user_id', $user->id)->first();
                        $response->firstName = $user->first_name;
                        $response->lastName = $user->last_name;
                        $response->email = $user->email;
                        $response->role = $user->role;
                        $response->blockStatus = $blockedUser && $blockedUser->block_status == 1 ? true : false;
                        $response->blockReason = $blockedUser && $blockedUser->block_reason ? $blockedUser->block_reason : '';
                        array_push($result, $response);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'users' => $result
            ])->setStatusCode(200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'users' => [],
                'error' => 'Something went wrong!'
            ])->setStatusCode(500);
        }
    }
}
