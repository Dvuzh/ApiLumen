<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\ResponseHelper;
use App\ClassModel;
use App\ClassMembership;

class ClassMembershipController extends Controller
{
    // This function creates a new class_membership using the class_code provided.
    // Validations:
    // 1. event['user_id'] should be provided and a user having user_id this value should exist in the database.
    // 2. ['class_code'] should be provided.
    // 3. A class should exist with the class_code provided.
    // 4. The class_membership should not exist.
    public function createClassMembership(Request $request)
    {
        
        $user = Auth::User();
        
        // Validations
        $validatedData = Validator::make(
            $request->all(),
            [
                'class_code' => 'required',
            ],
            [
                'class_code.required' => "'class_code' is missing."
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // Validate that class exists
        $class = ClassModel::where('class_code', $request->class_code)->first();
        if (!$class) {
            return ResponseHelper::classCodeDoesNotExist();
        }

        // Validate that class_membership does not already exist
        $classMembership = ClassMembership::where('class_id', $class->class_id)->where('user_id', $user->user_id)->first();
        if ($classMembership) {
            return ResponseHelper::classMembershipAlreadyExists();
        }

        // Validate that new membership will not exceed class membership limit
        if ($class->classMemberships()->count() >= $class->class_memberships_limit) {
            return ResponseHelper::classMembershipLimit();
        }

        // Insert a new class_membership
        $classMembership = ClassMembership::create([
            'user_id' => $user->user_id,
            'class_id' => $class->class_id
        ]);

        return response()->json([
            'OperationStatus' => 'OK',
            'class_membership' => $classMembership
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function deleteClassMembership($class_membership_id)
    {
        
        $user = Auth::User();
        
        // Find class_membership details
        $classMembership = ClassMembership::find($class_membership_id);

        // Validate that class membership exists
        if (!$classMembership) {
            return ResponseHelper::classMembershipDoesNotExist();
        }

        // Get class details
        $class = $classMembership->classModel;
        if (!$class) {
            return ResponseHelper::classDoesNotExist();
        }

        if ($class->user_id !== $user->user_id) {
            return ResponseHelper::deleteUnauthorized();
        }

        $classMembership->delete();
        return response()->json([
            "OperationStatus" => "OK"
        ]);
    }


    // This function retrieves a list of class membership that belong to a class from the MySQL database.
    // Validations:
    // 1. ['id'] and ['user_id'] should be provided.
    // 2. A class having class_id value as ['id'] should exist.
    // 3. User ids should match: user_id of the class should be equal to ['user_id'].
    public function getClassMemberships($class_id)
    {
        
        $user = Auth::User();
        
        $class = ClassModel::find($class_id);

        // Validate that class exists
        if (!$class) {
            return ResponseHelper::classCodeDoesNotExist();
        }

        // Validate that class belongs to the user
        if ($class->user_id !== $user->user_id) {
            return ResponseHelper::getUnauthorized();
        }

        $classMemberships = \DB::table("class_memberships")
        ->select("class_memberships.class_membership_id", "users.first_name", "users.last_name", "users.email")
        ->leftJoin("users", "class_memberships.user_id", "=", "users.user_id")
        ->where("class_memberships.class_id", $class_id)
        ->orderBy("users.first_name", "asc")
        ->orderBy("users.last_name", "asc")
        ->get();

        return response()->json($classMemberships, 200, [], JSON_NUMERIC_CHECK);
    }
}
