<?php

namespace App\Http\Controllers;

use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Http\Request;
use \stdClass;

class SupervisorController extends Controller
{
    /**
     * POST /
     * Purpose: Get supervisor by user id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSupervisor(Request $request)
    {
        $userId = $request->input('userId');
        $user = User::find($userId);
        $supervisor = $user->supervisor;

        return response()->json([
            'success' => true,
            'data' => $supervisor
        ]);
    }

    /**
     * GET /get-available-supervisors
     * Purpose: Get available supervisors
     */
    public function getAvailableSupervisors(Request $request)
    {
        $result = [];
        $supervisors = Supervisor::all();

        foreach ($supervisors as $supervisor) {
            $agentNumber = $supervisor->agents->count();
            $data = new StdClass;
            $data->id = $supervisor->id;
            $data->firstName = $supervisor->user->first_name;
            $data->lastName = $supervisor->user->last_name;

            if ($agentNumber < 5) {
                array_push($result, $data);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
