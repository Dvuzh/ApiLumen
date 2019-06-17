<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\ResponseHelper;
use App\User;

class UserController extends Controller
{
    //This function creates a new user if not exists in MySQL database.
    public function createUser(Request $request)
    {
        // Validations
        $validatedData = Validator::make(
            $request->all(),
            [
                "user_id" => "required|string",
                "email" => "required|email",
                "first_name" => "required|string",
                "last_name" => "required|string"
            ],
            [
                "user_id.required" => "'user_id' is missing.",
                "email.required" => "'email' is missing.",
                "first_name.required" => "'first_name' is missing.",
                "last_name.required" => "'last_name' is missing."
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        $user = User::where('user_id', $request->user_id)->first();

        if (!$user) {
            
            # Insert the new user.
            $user = User::create([
                'user_id' => $request->user_id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'created_at' => new \DateTime(),
                'limit' => 3
            ]);
        }

        return response()->json([
            "OperationStatus" => "OK",
            "user" => $user
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    // This function updates an existing user in the MySQL database.
    // Validations:
    // 1. User ids should match: ['id'] should be equal to ['user_id'].
    // 2. At least one of these parameters should be provided:
    //     'first_name', 'last_name', 'email'
    // 3. The updated user should exist in database.
    public function updateUser($user_id, Request $request)
    {
        // Input validations
        $validatedData = Validator::make(
            $request->all(),
            [
                "email" => "required_without_all:first_name,last_name|email",
                "first_name" => "required_without_all:email,last_name|string",
                "last_name" => "required_without_all:email,first_name|string"
            ],
            [
                "email.required_without_all" => "At least one of 'email', 'first_name' or 'last_name' should be provided to update.",
                "first_name.required_without_all" => "At least one of 'email', 'first_name' or 'last_name' should be provided to update.",
                "last_name.required_without_all" => "At least one of 'email', 'first_name' or 'last_name' should be provided to update."
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }
        
        $user = Auth::User();
        if ($user->user_id !== $user_id) {
            return ResponseHelper::userUpdateUnauthorized();
        }
     
        // Update the user
        if ($request->has('email')) {
            $user->email = $request->email !== '' ? $request->email : null;
        }
        if ($request->has('first_name')) {
            $user->first_name = $request->first_name !== '' ? $request->first_name : null;
        }
        if ($request->has('last_name')) {
            $user->last_name = $request->last_name !== '' ? $request->last_name : null;
        }

        $user->save();

        return response()->json([
            "OperationStatus" => "OK",
            "user" => $user
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
