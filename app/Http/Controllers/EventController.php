<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Event;
use \stdClass;


class EventController extends Controller
{
    public function createEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'startTime' => 'required',
            'endTime' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid Inputs!'
            ])->setStatusCode(400);
        }

        try {
            $event = new Event();
            $event->user_id = $request->user_id;
            $event->title = $request->title;
            $event->description = $request->description ? $request->description : null;
            $event->start_time = $request->startTime;
            $event->end_time = $request->endTime;
            $event->save();

            return response()->json([
                'success' => true,
                'error' => 'Event created'
            ])->setStatusCode(201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => 'Something went wrong!!'
            ])->setStatusCode(500);
        }
    }

    public function updateEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'eventId' => 'required',
            'title' => 'required|string|max:255',
            'startTime' => 'required',
            'endTime' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => 'Invalid Inputs!'
            ])->setStatusCode(400);
        }

        try {
            $event = Event::find($request->eventId);

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'data' => 'Invalid Event!'
                ])->setStatusCode(400);
            }

            $event->user_id = $request->user_id;
            $event->title = $request->title;
            $event->description = $request->description ? $request->description : null;
            $event->start_time = $request->startTime;
            $event->end_time = $request->endTime;
            $event->update();

            return response()->json([
                'success' => true,
                'error' => 'Event updated'
            ])->setStatusCode(201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => 'Something went wrong!'
            ])->setStatusCode(500);
        }
    }

    public function getEvents(Request $request)
    {
        try {
            $events = Event::where('user_id', $request->user_id)->get();
            $response = [];

            if ($events && count($events) > 0) {
                foreach ($events as $index => $event) {
                    $res = new StdClass;
                    $res->id = $event->id;
                    $res->title = $event->title;
                    $res->description = $event->description;
                    $res->startTime = $event->start_time;
                    $res->endTime = $event->end_time;
                    array_push($response, $res);
                }
            }

            return response()->json([
                'success' => true,
                'events' => $response
            ])->setStatusCode(200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => 'Unable to fetch events'
            ])->setStatusCode(500);
        }
    }
}
