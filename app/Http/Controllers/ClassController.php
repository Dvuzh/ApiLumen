<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\ResponseHelper;
use App\ClassModel;
use App\ClassMembership;
use App\Subject;
use App\Product;

class ClassController extends Controller
{
    // Validations:
    // 1. ['user_id'] should be provided.
    // 2. ['id'] should be provided and a class having class_id as this value should exist in the database.
    // 3. user_id of the class should be same as ['user_id'].
    public function generateNewClassCode($class_id, Request $request)
    {
        $user = Auth::User();
        
        // Validate that class item exists
        $class = ClassModel::find($class_id);
        if (!$class) {
            return ResponseHelper::classDoesNotExist();
        }

        // Validate that class is owned by the user
        if ($class->user_id !== $user->user_id) {
            return ResponseHelper::updateUnauthorized();
        }

        // Generates a 6-digit random class code and checks whether it was used before.
        // It repeats this process until a unique class code achieved.
        // The function returns generated unique class code.
        $newClassCode = ClassModel::generateClassCode();

        // Update class_code of the class
        $class->class_code = $newClassCode;
        $class->save();

        return response()->json([
            'OperationStatus' => 'OK',
            'class' => $class
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    // This function creates a new class item in the MySQL database. It also generates a unique class code.
    // Validations:
    // 1. event['user_id'] should be provided and a user having user_id this value should exist in the database.
    // 2. event['body']['class_name'] should be provided.
    public function createNewClass(Request $request)
    {
        $user = Auth::User();
        
        // Validations
        $validatedData = Validator::make(
            $request->all(),
            [
                'class_name' => 'required|string|max:128',
                'icon_url' => 'required|string|max:512',
                'subject_1' => 'nullable|integer',
                'subject_2' => 'nullable|integer',
                'subject_3' => 'nullable|integer'
            ],
            [
                'class_name.required' => "'class_name' is missing.",
                'icon_url.required' => "'icon_url' is missing."
            ]
         );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        if ($request->has('subject_1') && $request->subject_1 !== '') {
            if (!Subject::find($request->subject_1)) {
                return ResponseHelper::subjectNotExist();
            }
            if (!Product::find($request->subject_1)) {
                return ResponseHelper::subjectProductDoesNotExist();
            }
        }
        if ($request->has('subject_2') && $request->subject_2 !== '') {
            if (!Subject::find($request->subject_2)) {
                return ResponseHelper::subjectNotExist();
            }
            if (!Product::find($request->subject_2)) {
                return ResponseHelper::subjectProductDoesNotExist();
            }
        }
        if ($request->has('subject_3') && $request->subject_3 !== '') {
            if (!Subject::find($request->subject_3)) {
                return ResponseHelper::subjectNotExist();
            }
            if (!Product::find($request->subject_3)) {
                return ResponseHelper::subjectProductDoesNotExist();
            }
        }


        $class = ClassModel::create([
            'user_id' => $user->user_id,
            'class_name' => $request->class_name,
            'class_code' => ClassModel::generateClassCode(),
            'created_date' => new \DateTime(),
            'class_memberships_limit' => 50,
            'icon_url' => ($request->has('icon_url') && $request->icon_url !== '') ? $request->icon_url : null,
            'subject_1' =>  ($request->has('subject_1') && $request->subject_1 !== '') ? $request->subject_1 : null,
            'subject_2' =>  ($request->has('subject_2') && $request->subject_2 !== '') ? $request->subject_2 : null,
            'subject_3' =>  ($request->has('subject_3') && $request->subject_3 !== '') ? $request->subject_3 : null
        ]);



        return response()->json([
            "OperationStatus" => "OK",
            "class" => $class
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    // This function deletes an existing class item from the MySQL database. It also deletes all class memberships associated with the class.
    // Validations:
    // 1. ['id'] and ['user_id'] should be provided.
    // 2. A class having class_id value as ['id'] should exist in the classes table.
    // 3. The class should belong to ['user_id'].
    public function deleteClass($class_id)
    {
        $user = Auth::User();
        
        // Validate that class exists
        $class = ClassModel::find($class_id);
        if (!$class) {
            return ResponseHelper::classDoesNotExist();
        }

        // Validate that class owned by the user
        if ($class->user_id !== $user->user_id) {
            return ResponseHelper::deleteUnauthorized();
        }

        // Perform deletion
        $class->delete();

        // Delete also associated class_memberships
        if ($class->classMemberships->count()) {
            ClassMembership::destroy($class->classMemberships->pluck('class_membership_id'));
        }

        return response()->json(['OperationStatus' => 'OK']);
    }

    // This function updates an existing class item in the MySQL database.
    // Validations:
    // 1. ['user_id'] should be provided.
    // 2. ['class_name'] should be provided.
    // 3. ['id'] should be provided and a class having class_id as this value should exist in the database.
    // 4. user_id of the class should be same as ['user_id'].
    public function updateClass($class_id, Request $request)
    {
        $user = Auth::User();
        
        // Validations
        $validatedData = Validator::make(
            $request->all(),
            [
                'class_name' => 'required|string|max:128',
                'icon_url' => 'nullable|string|max:512',
                'subject_1' => 'nullable|integer',
                'subject_2' => 'nullable|integer',
                'subject_3' => 'nullable|integer'
            ],
            [
                'class_name.required' => "'class_name' is missing."
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // Validate that class item exist
        $class = ClassModel::find($class_id);
        if (!$class) {
            return ResponseHelper::classDoesNotExist();
        }

        // Validate that class is owned by the user
        if ($class->user_id !== $user->user_id) {
            return ResponseHelper::updateUnauthorized();
        }

        if ($request->has('subject_1')) {
            if ($request->subject_1 == '') {
                $class->subject_1 = null;
            } else {
                if (!Subject::find($request->subject_1)) {
                    return ResponseHelper::subjectNotExist();
                }
                if (!Product::find($request->subject_1)) {
                    return ResponseHelper::subjectProductDoesNotExist();
                }
                $class->subject_1 = $request->subject_1;
            }
        }
        if ($request->has('subject_2')) {
            if ($request->subject_2 == '') {
                $class->subject_2 = null;
            } else {
                if (!Subject::find($request->subject_2)) {
                    return ResponseHelper::subjectNotExist();
                }
                if (!Product::find($request->subject_2)) {
                    return ResponseHelper::subjectProductDoesNotExist();
                }
                $class->subject_2 = $request->subject_2;
            }
        }
        if ($request->has('subject_3')) {
            if ($request->subject_3 == '') {
                $class->subject_3 = null;
            } else {
                if (!Subject::find($request->subject_3)) {
                    return ResponseHelper::subjectNotExist();
                }
                if (!Product::find($request->subject_3)) {
                    return ResponseHelper::subjectProductDoesNotExist();
                }
                $class->subject_3 = $request->subject_3;
            }
        }

        # Include class_name
        $class->class_name = $request->class_name;
        // Check and include icon_url
        if ($request->has('icon_url') && $request->icon_url !== '') {
            $class->icon_url = $request->icon_url;
        }

        $class->save();

        return response()->json([
            "OperationStatus" => "OK",
            "class" => $class
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    // This function retrieves a list of classes that belong to a user.
    // Validations:
    // 1. ['id'] and ['user_id'] should be provided.
    // 2. User ids should match: ['id'] should be equal to ['user_id'].
    public function getClasses($user_id)
    {
        $user = Auth::User();

        // validations
        if ($user->user_id !== $user_id) {
            return ResponseHelper::getUnauthorized();
        }
        $classes = \DB::table('classes')->select(
            'classes.class_id',
            'classes.class_name',
            'classes.class_code',
            'classes.icon_url',
            'classes.subject_1',
            'sb1.subject_name as subject_1_name',
            'sb1.publisher_name as subject_1_publisher_name',
            'classes.subject_2',
            'sb2.subject_name as subject_2_name',
            'sb2.publisher_name as subject_2_publisher_name',
            'classes.subject_3',
            'sb3.subject_name as subject_3_name',
            'sb3.publisher_name as subject_3_publisher_name'
        )->leftJoin('subjects as sb1', 'classes.subject_1', '=', 'sb1.subject_id')
        ->leftJoin('subjects as sb2', 'classes.subject_2', '=', 'sb2.subject_id')
        ->leftJoin('subjects as sb3', 'classes.subject_3', '=', 'sb3.subject_id')
        ->where('classes.user_id', $user_id)->orderBy('classes.class_name', 'asc')->get();

        return response()->json($classes, 200, [], JSON_NUMERIC_CHECK);
    }
}
