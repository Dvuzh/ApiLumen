<?php

namespace App\Http\Controllers;

use Auth;
use Validator;
use App\Favourite;
use App\Subject;
use App\AuthorMembership;
use App\Product;
use App\AccessCodeMembership;
use App\ClassModel;
use App\LearnerGroup;
use App\LearnerGroupMembership;
use App\ResponseHelper;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function getSubjects()
    {
        $user = Auth::User();

        // 1. The user_id (from JWT token) will be used to retrieve all entries in the
        // subjects table and author_memberships table that contains the user_id.
        // Entries returned from the author_memberships table will be looked up in the
        // subjects table using the subject_id. (This is in order to get more details for
        // those records)
        // 2. Only subjects with a status field set to ‘active’ will be included in the response
        // 3. Subjects are to be returned in alphabetical order based on
        // subject_internal_name
        $subjects = $user->getActiveSubjectsArray();
        
        $responseData = [];
        foreach ($subjects as $subject) {
            $responseData[] = [
                'subject_id' => $subject->subject_id,
                'subject_internal_name' => $subject->internal_subject_name,
                'subject_name' => $subject->subject_name,
                'subject_tag' => $subject->subject_tag,
                'subject_icon_url' => $subject->subject_icon_url,
                'owner' => $subject->user_id === $user->user_id ? 'yes': 'no',
                'limit' => $subject->limit,
                'owner_email' => $subject->owner_email,
                'author_memberships_id' => $subject->author_memberships_id ? $subject->author_memberships_id : null,
                'author_status' => $subject->author_memberships_status ? $subject->author_memberships_status : null,
                'marketplace_status' => $subject->marketplace_status
            ];
        }
        return response()->json($responseData, 200, [], JSON_NUMERIC_CHECK);
    }

    public function createSubject(Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform all field validation checks. (refer to last column of this file)
        $validatedData = Validator::make(
            $request->all(),
            [
                'subject_name' => 'required|string|max:50',
                'subject_internal_name' => 'required|string|max:50',
                'subject_tag' => 'nullable|string|max:25',
                'subject_icon_url' => 'required|url|max:512'
            ],
            [
                'subject_name.required' => 'One or more of the required fields were not provided',
                'subject_name.string' => 'The subject_name can only contain alphanumeric characters, spaces and apostrophes',
                'subject_internal_name.required' => 'One or more of the required fields were not provided',
                'subject_internal_name.string' => 'The subject_internal_name can only contain alphanumeric characters, spaces and apostrophes',
                'subject_tag.required' => 'One or more of the required fields were not provided',
                'subject_tag.string' => 'The subject_tag can only contain alphanumeric characters, spaces and apostrophes. (Can be null)',
                'subject_icon_url.required' => 'One or more of the required fields were not provided',
                'subject_icon_url.url' => 'The subject_icon_url is not a valid url.'
            ]
         );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 2. The user_id can have a maximum of 150 active subjects in the subjects
        // table. (status field = “active”) Therefore, check the subjects table to see if
        // user is not going to exceed.
        $subjects = Subject::where('user_id', $user->user_id)->where('status', 'active')->get();
        if ($subjects->count() > 150) {
            return ResponseHelper::limitSubjects();
        }

        // 3. Create a row in the subjects table with the information provided. Also search
        // the user’s table using the user_id and look for a number in the limit field. If a
        // number is present, set the limit field in the new record in the subjects table to
        // the number found in the user’s table, otherwise default to 3.
        $subject = Subject::create([
            'subject_name' => $request->subject_name,
            'internal_subject_name'=> $request->subject_internal_name,
            'subject_tag' => $request->subject_tag,
            'subject_icon_url' => $request->subject_icon_url,
            'limit' => $user->limit ? $user->limit : 3,
            'user_id' => $user->user_id,
            'status' => 'active',
            'marketplace_status' => 'no',
            'created_date' => new \Datetime()
        ]);
        
        // 4. Also create a new row in the products table. Store the subject_id in the
        // product_id field and the subject_name in the product_name field.
        $product = Product::create([
            'product_id' => $subject->id,
            'product_name' => $subject->subject_name
        ]);

        // Also create a row in the access_code_membership table for the particular
        // user. (type is ‘author’)
        $accessCodeMembership = AccessCodeMembership::create([
            'user_id' => $user->user_id,
            'type' => 'author',
            'subject_id' => $subject->subject_id,
            'date_registered' => new \Datetime()
        ]);

        return response()->json([
            'OperationStatus' => 'OK',
            'subject' => [
                'subject_id' => $subject->subject_id,
                'subject_internal_name' => $subject->internal_subject_name,
                'subject_name' => $subject->subject_name,
                'subject_tag' => $subject->subject_tag,
                'subject_icon_url' => $subject->subject_icon_url,
                'limit' => $subject->limit,
                'owner' => 'yes',
                'marketplace_status' => 'no'
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function updateSubject($subject_id, Request $request)
    {
        
        $user = Auth::User();

        // 1. Perform all field validation checks.
        $validatedData = Validator::make(
            $request->all(),
            [
                'subject_name' => 'required_without_all:subject_internal_name,subject_tag,subject_icon_url|string|max:50',
                'subject_internal_name' => 'required_without_all:subject_name,subject_tag,subject_icon_url|string|max:50',
                'subject_tag' => 'required_without_all:subject_name,subject_internal_name,subject_icon_url|string|max:25',
                'subject_icon_url' => 'required_without_all:subject_name,subject_internal_name,subject_tag|url|max:512'
            ],
            [
                'subject_name.string' => 'The subject_name can only contain alphanumeric characters, spaces and apostrophes',
                'subject_internal_name.string' => 'The subject_internal_name can only contain alphanumeric characters, spaces and apostrophes',
                'subject_tag.string' => 'The subject_tag can only contain alphanumeric characters, spaces and apostrophes.',
                'subject_icon_url.url' => 'The subject_icon_url is not a valid url.'
            ]
         );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 2. Check that the subject_id exist in the subjects table.
        $subject = Subject::find($subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id provided. (i.e. there
        // is a row in either table that contains both the subject_id and user_id field)
        // If no match is found, the user does not have the permission to update.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }


        if ($request->has('subject_internal_name')) {
            $subject->internal_subject_name = $request->subject_internal_name;
        }
        if ($request->has('subject_tag')) {
            $subject->subject_tag = $request->subject_tag;
        }

        $message = '';
        //4. The user should not be able to update the subject_name and icon_url field when the marketplace_status field is yes.
        if ($subject->marketplace_status !== 'yes') {
            if ($request->has('subject_name')) {
                $subject->subject_name = $request->subject_name;
            }
            if ($request->has('subject_icon_url')) {
                $subject->subject_icon_url = $request->subject_icon_url;
            }
        } elseif ($request->has('subject_name') || $request->has('subject_icon_url')) {
            $message = "The subject_name and subject_icon_url could not be updated because the subject is on the marketplace.";
        }

        $subject->save();

        return response()->json([
            'OperationStatus' => 'OK',
            'subject' => [
                'subject_id' => $subject->subject_id,
                'subject_internal_name' => $subject->internal_subject_name,
                'subject_name' => $subject->subject_name,
                'subject_tag' => $subject->subject_tag,
                'subject_icon_url' => $subject->subject_icon_url
            ],
            'message' => $message
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function deleteSubject($subject_id, Request $request)
    {
        
        $user = Auth::User();

        // 1. Check that the subject_id exist in the subjects table.
        $subject = Subject::find($subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 2. Check that the user_id making the request matches the user_id in the
        // subjects table for the specific subject_id.
        if ($subject->user_id !== $user->user_id) {
            return ResponseHelper::permissionError();
        }

        // 3. The subject will not be deleted if records are found in the
        // access_code_memberships table that contain the subject_id
        // and where the status is something other than ‘learner’ or ‘author
        if ($subject->accessCodeMemberships->count()) {
            foreach ($subject->accessCodeMemberships as $accessCodeMembership) {
                if ($accessCodeMembership->type !== 'learner' && $accessCodeMembership->type !== 'author') {
                    return ResponseHelper::validationError('Cannot be deleted because access code memberships exist');
                }
            }
        }

        // 4. The subject will not be deleted if the marketplace_status field is ‘yes’.
        if ($subject->marketplace_status === 'yes') {
            return ResponseHelper::validationError('Cannot be deleted because subject is on the marketplace.');
        }

        // 5. If all conditions are met, the subject will not actually be deleted, however the
        // ‘status’ field of the subject will be changed to ‘inactive’.
        $subject->status = 'inactive';
        $subject->save();

        // 6. In addition, all author memberships that contain the subject_id in the
        // author_memberships table will be deleted.
        if ($subject->authorMemberships->count()) {
            AuthorMembership::destroy($subject->authorMemberships->pluck('author_memberships_id')->all());
        }

        // 7. The status of all entries that shares the subject_id in the following tables will
        // be changed to ‘inactive’: skills, topics, content, multichoice_questions,
        // matching_questions, numerical_questions, study_notes.
        $subject->skills()->update(['status' => 'inactive']);
        $subject->topics()->update(['status' => 'inactive']);
        $subject->contents()->update(['status' => 'inactive']);
        $subject->multichoiceQuestions()->update(['status' => 'inactive']);
        $subject->matchingQuestions()->update(['status' => 'inactive']);
        $subject->numericalQuestions()->update(['status' => 'inactive']);
        $subject->studyNotes()->update(['status' => 'inactive']);

        // 8. Using the subject_id, find the entry in the products table (product_id) and
        // delete the record.
        Product::destroy([$subject->subject_id]);

        Favourite::where('subject_id', $subject->subject_id)->delete();

        // 9. Using the subject_id, find all entries in the classes table that has the specific
        // subject_id stored in the subject_1, subject_2 or subject_3 field. Simply
        // change the values to NULL.
        $classes = ClassModel::getBySubjectId($subject->subject_id);
        foreach ($classes as $class) {
            if ($class->subject_1 === $subject->subject_id) {
                $class->subject_1 = null;
            }
            if ($class->subject_2 === $subject->subject_id) {
                $class->subject_2 = null;
            }
            if ($class->subject_3 === $subject->subject_id) {
                $class->subject_3 = null;
            }
            $class->save();
        }

        // 10. Delete any remaining rows from the access_code_memberships table for the
        // subject_id provided and the learner_groups table and the
        // learner_group_memberships table.
        $accessCodeMemberships = $subject->accessCodeMemberships;
        if ($accessCodeMemberships->count()) {
            AccessCodeMembership::destroy($accessCodeMemberships->pluck('access_membership_id')->all());
        }
        $learnerGroups = $subject->learnerGroups;
        if ($learnerGroups->count()) {
            LearnerGroup::destroy($learnerGroups->pluck('group_id')->all());
        }
        $learnerGroupMemberships = $subject->learnerGroupMemberships;
        if ($learnerGroupMemberships->count()) {
            LearnerGroupMembership::destroy($learnerGroupMemberships->pluck('group_membership_id')->all());
        }
       
        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }
}
