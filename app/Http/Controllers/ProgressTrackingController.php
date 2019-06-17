<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Auth;
use Validator;
use App\ClassModel;
use App\ResponseHelper;
use App\TrackingCode;
use App\ProgressTracking;

class ProgressTrackingController extends Controller
{
    // Validations:
    // 1. ['user_id'] should be provided and a user having user_id this value should exist in the database.
    // 2. ['tracking_code'] should be provided.
    // 3. ['class_id'] should be provided.
    // 4. Provided tracking_code should exist in database.
    // 5. Provided tracking_code should not be allocated to any user.
    // 6. A class with provided class_id should exist.
    // 7. The progress_tracking data should not already exist.
    public function createNewProgressTracking(Request $request)
    {
        $user = Auth::User();
        
        // Input validations
        $validatedData = Validator::make(
                $request->all(),
                [
                    'tracking_code' => 'required',
                    'class_id' => 'required|integer'
                ],
                [
                    'tracking_code.required' => "'tracking_code' is missing.",
                    'class_id.required' => "'class_id' is missing."
                ]
             );
    
        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // Get class details and perform validations
        $class = ClassModel::find($request->class_id);

        // Validate that class exists
        if (!$class) {
            return ResponseHelper::classDoesNotExist();
        }

        // Validate that class is owned by the user
        if ($class->user_id !== $user->user_id) {
            return ResponseHelper::trackingUnauthorizedError();
        }

        // Get tracking_code details and perform validations
        $trackingCode = TrackingCode::where('code', $request->tracking_code)->first();
        if (!$trackingCode) {
            return ResponseHelper::trackingCodeDoesNotExist();
        }

        if ($trackingCode->user_id) {
            return ResponseHelper::trackingCodeAlreadyUsed();
        }

        // Validates that progress_tracking info does not exist in the database.
        $progressTracking = ProgressTracking::where('user_id', $user->user_id)
        ->where('tracking_code', $trackingCode->code)
        ->where('class_id', $class->class_id)
        ->first();
        if ($progressTracking) {
            return ResponseHelper::progressTrackingAlreadyExists();
        }

        DB::beginTransaction();
        try {
            // Create progress_tracking
            $progressTracking = ProgressTracking::create([
                'user_id' =>  $user->user_id,
                'tracking_code' => $trackingCode->code,
                'class_id' => $class->class_id,
                'timestamp' => new \DateTime()
            ]);

            // Allocates the tracking_code to user by updating its user information in the database.
            $trackingCode->user_id = $user->user_id;
            $trackingCode->save();

            // Updates the class with tracking information.
            $class->tracking_code = $trackingCode->code;
            $class->tracking_date = new \DateTime();
            $class->save();

            // Upon successfully creating the tracking record, use the school_id field and user_type
            // field (from the tracking_codes table) to update the school_id and user_type field of
            // the user record in the users table.
            if ($trackingCode->school_id !== null) {
                $user->school_id = $trackingCode->school_id;
            }
            if ($trackingCode->user_type !== null) {
                $user->user_type = $trackingCode->user_type;
            }
            $user->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }

        return response()->json([
            'OperationStatus' => 'OK',
            'progress_tracking' => $progressTracking,
            'tracking_code' => $trackingCode,
            'class' => $class
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
