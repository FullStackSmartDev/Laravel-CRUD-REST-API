<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\UserProfile;
use JWTAuthException;
use \stdClass;


class UserProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $file = public_path()."/images/profile-pic/test.png";
            $userProfile = UserProfile::where('user_id', $request->user_id)->first();
            $response = new StdClass;

            if (is_null($userProfile)) {
                return response()->json([
                    'success' => true,
                    'profile' => $response,
                    ])->setStatusCode(200);
            } else {
                $response->gender = $userProfile->gender;
                $response->country = $userProfile->country;
                $response->state = $userProfile->state;
                $response->mobile = $userProfile->mobile;
                $response->address = $userProfile->address;
                $response->profileImage = $userProfile->profile_image ? asset($userProfile->profile_image) : '';

                return response()->json([
                    'success' => true,
                    'profile' => $response,
                    ])->setStatusCode(200);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e,
            ])->setStatusCode(500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gender' => 'required',
            'address' => 'required|string',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => 'Invalid Inputs!'
            ])->setStatusCode(400);
        } else {
            if ($request->userId) {
                $userProfile = UserProfile::where('user_id', $request->userId)->first();
            } else {
                $userProfile = UserProfile::where('user_id', $request->user_id)->first();
            }

            try {
                // Store User Profile
                if (is_null($userProfile)) {
                    $userProfile = new UserProfile();

                    if ($request->userId) {
                        $userProfile->user_id = $request->userId;
                    } else {
                        $userProfile->user_id = $request->user_id;
                    }

                    $userProfile->gender = $request->gender;
                    $userProfile->profile_image = $request->profile_image;
                    $userProfile->mobile = $request->mobile;
                    $userProfile->address = $request->address;
                    $userProfile->state = $request->state;
                    $userProfile->country = $request->country;
                    $userProfile->save();
                } else{
                    if ($request->userId) {
                        $userProfile->user_id = $request->userId;
                    } else {
                        $userProfile->user_id = $request->user_id;
                    }

                    $userProfile->user_id = $request->user_id;
                    $userProfile->gender = $request->gender;
                    $userProfile->profile_image = $request->profile_image;
                    $userProfile->mobile = $request->mobile;
                    $userProfile->address = $request->address;
                    $userProfile->state = $request->state;
                    $userProfile->country = $request->country;
                    $userProfile->update();
                }

                return response()->json([
                    'success' => true,
                    'data' => 'User Profile successfully updated!'
                ]);
            } catch (JWTAuthException $e) {
                return response()->json([
                    'success' => true,
                    'error' => $e
                ]);
            }
        }
    }

    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid Image!'
            ])->setStatusCode(400);
        }

        $fileName = $request->image->getClientOriginalName();
        $fileExtension = $request->image->extension();
        $imageName = time() . '-' . $fileName;
        $filePath = $request->image->move(public_path('images/profile-pic'), $imageName);

        return response()->json([
            'status' => 201,
            'message' => 'User Profile Image Uploaded.',
            'profileImage' => '/images/profile-pic/' . $imageName
        ], 201);
    }
}
