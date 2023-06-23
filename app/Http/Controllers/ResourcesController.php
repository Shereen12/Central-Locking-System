<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use App\Models\Resource;
use App\Jobs\ResourceJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ResourcesController extends Controller
{
    public function index()
    {
        return Resource::all();
    }

    public function action($resourceName, Request $request)
    {
        $validator =  Validator::make($request->all(), ([
            'action' => 'required|in:acquire,release'
        ]));

        if($validator->fails()){
            return response()->json([
                'Error' => $validator->errors()
            ], 422);
        }

        if($request->action == 'acquire')
        {
            return $this->acquire($resourceName, $request);
        }

        return $this->release($resourceName, $request);
    }

    public function acquire($resourceName, Request $request)
    {
        $validator = Validator::make($request->all(), ([
            'key' => 'required|string|min:10|max:10|unique:resources,key',
            'period' => 'sometimes|integer|gt:0'
        ]));

        if($validator->fails()){
            return response()->json([
                'Error' => $validator->errors()
            ], 422);
        }

        $resource = Resource::where('name', $resourceName)->first();
        if(!$resource){
            return response()->json([
                'Error' => "Resource not found."
            ], 404);
        }
        if($resource->acquired_at != null){
            sleep(5);
            if(!$this->checkResource($resourceName)){
                return response()->json([
                    'Error' => "Request Timeout: Resource is not free."
                ], 408);
            }
        }
        DB::beginTransaction();

        try{

            $resource->lockForUpdate();
            $resource->update([
                'acquired_at' => Carbon::now()->toDateTimeString(),
                'period' => $request->period? $request->period : null,
                'key' => $request->key
            ]);

            if($request->period)
            {
                ResourceJob::dispatch($resource)->delay(now()->addSeconds($request->period));
            }
            DB::commit();

            
        } catch (Throwable $e){
            DB::rollBack();

            report($e);
            return response()->json([
                'Error' => "Error while acquiring resource."
            ], 500);
        }
    return response()->json([
        'Resource Status' => "Resource acquired successfully"
    ], 200);
    }

    private function checkResource($resourceName){
        $resource = Resource::where('name', $resourceName)->first();
        if($resource->acquired_at == null){
            return true;
        }
        return false;
    }

    public function release($resourceName, Request $request){
        $validator = Validator::make($request->all(), ([
            'key' => 'required|string|exists:resources,key'
        ]));

        if($validator->fails()){
            return response()->json([
                'Error' => $validator->errors()
            ], 422);
        }
        $resource = Resource::where('name', $resourceName)->first();
        if(!$resource){
            return response()->json([
                'Error' => "Resource not found."
            ], 404);
        }
        if($resource->acquired_at == null){
            return response()->json([
                'Error' => "Resource is already free."
            ], 409);
        }

        if($request->key != $resource->key){
            return response()->json([
                'Error' => "Not authorized"
            ], 401);
        }

        try{
            $resource->update([
                'acquired_at' => null,
                'period' => null,
                'key' => null
            ]);
        } catch(Throwable $e){
            report($e);
            return response()->json([
                'Error' => "Error while acquiring resource."
            ], 500);
        }

        return response()->json([
            'Resource Status' => "Resource released successfully"
        ], 200);
    }
}
