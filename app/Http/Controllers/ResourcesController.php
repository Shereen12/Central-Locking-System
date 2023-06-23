<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use App\Models\Resource;
use App\Jobs\ResourceJob;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Collection;

class ResourcesController extends Controller
{
    /**
     * Retrieves all resources
     *
     * @return void
     */
    public function index(): Collection
    {
        return Resource::all();
    }

    /**
     * Takes request and calls for handler method according to action type specified by the user
     *
     * @param [type] $resourceName
     * @param Request $request
     * @return JsonResponse
     */
    public function action($resourceName, Request $request): JsonResponse
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

    /**
     * Handles acquiring of resource
     *
     * @param [type] $resourceName
     * @param Request $request
     * @return JsonResponse
     */
    private function acquire($resourceName, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ([
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
                'key' => Str::random(60)
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
        'Resource Status' => "Resource acquired successfully",
        'Key' => $resource->key
    ], 200);
    }

    /**
     * Checks resource status
     *
     * @param [type] $resourceName
     * @return bool
     */
    private function checkResource($resourceName): bool
    {
        $resource = Resource::where('name', $resourceName)->first();
        if($resource->acquired_at == null){
            return true;
        }
        return false;
    }

    /**
     * Handles releasing a resource
     *
     * @param [type] $resourceName
     * @param Request $request
     * @return JsonResponse
     */
    private function release($resourceName, Request $request): JsonResponse
    {
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
