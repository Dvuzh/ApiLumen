<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\LearnerGroup;
use App\Subject;
use App\AvailableSubjectCode;
use App\ResponseHelper;
use App\LearnerGroupMembership;
use App\AccessCodeMembership;

class SubjectGroupController extends Controller
{
    public function createGroup(Request $request)
    {
        
        $user = Auth::User();
        
        $validatedData = Validator::make(
            $request->all(),
            [
                'subject_id' => 'required|integer',
                'group_name' => 'required|string|max:64',
                'limit' => 'required|integer'
            ],
            [
                'subject_id.required' => 'The subject_id does not exist.',
                'group_name.string' => 'The group_name can only contain alphanumeric characters, spaces and apostrophes',
                'limit.integer' => 'The limit field has to be an integer.'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 1. Check that the subject_id exist in the subjects table.
        $subject = Subject::find($request->subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 2. Check that the user_id making the request matches the user_id in the
        // subjects table or the author_memberships table for the specific subject_id.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // The limit field provided should not be higher than the limit field of the related subject.
        if ($subject->limit < $request->limit) {
            return ResponseHelper::validationError(
                "The limit field of the group cannot be higher than the learner limit for this course"
            );
        }

        // 3. Check that the limit of 100 groups per subject_id have not been reached by
        // searching the learner_groups table using the subject_id.
        if ($subject->learnerGroups->count() >= 100) {
            return ResponseHelper::validationError('You have a reached the limit of 100 groups per course.');
        }

        // 4. Simply grab a new access code from available_subject_codes table. Delete
        // entry from the available_subject_codes table.
        $availableSubjectCode = AvailableSubjectCode::first();
        if (!$availableSubjectCode) {
            return ResponseHelper::noAvailableCodes();
        }
        $subjectCode = $availableSubjectCode->access_code;
        $availableSubjectCode->delete();

        // 5. Create the new record in the learner_groups table.
        $learnerGroup = LearnerGroup::create([
            'group_name' => $request->group_name,
            'subject_id' => $subject->subject_id,
            'limit' => $request->limit,
            'group_access_code' => $subjectCode,
            'user_id' => $user->user_id,
            'created_date' => new \DateTime()
        ]);

        return response()->json([
            'OperationStatus' => 'OK',
            'subject' => [
                'group_id' => $learnerGroup->group_id,
                'group_name' => $learnerGroup->group_name,
                'group_access_code' => $learnerGroup->group_access_code,
                'limit' => $learnerGroup->limit
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function updateGroup($group_id, Request $request)
    {
        
        $user = Auth::User();
        
        $validatedData = Validator::make(
            $request->all(),
            [
                'group_name' => 'required_without_all:limit|string|max:64',
                'limit' => 'required_without_all:group_name|integer'
            ],
            [
                'group_name.string' => 'The group_name can only contain alphanumeric characters, spaces and apostrophes',
                'limit.integer' => 'The limit field must be an integer.'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 1. Search the learner_group’s table to see whether the group_id exist and to
        // obtain the related subject_id.
        $learnerGroup = LearnerGroup::find($group_id);
        if (!$learnerGroup) {
            return ResponseHelper::groupNotExist();
        }

        // 2. Check that the user_id making the request matches the user_id in the
        // subjects table or the author_memberships table for the specific subject_id.
        $subject = $learnerGroup->subject;
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 3. If the limit field is provided for the update, make sure that the number
        // provided is above or equal to the number of records for the specific group_id
        // in the the learner_group_memberships
        if ($request->has('limit')) {
            // The limit field provided should not be higher than the limit field of the related subject.
            if ($subject->limit < $request->limit) {
                return ResponseHelper::validationError(
                    "The limit field of the group cannot be higher than the learner limit for this course"
                );
            }
            if ($learnerGroup->learnerGroupMemberships->count() > $request->limit) {
                return ResponseHelper::validationError(
                    "ValidationError - The limit cannot be updated to the number specified as the number of learners in the group 
                    is already above the new limit. Please remove learners and try again."
                );
            }
        }

        // 4. Update the entry in the learner_group’s table.
        if ($request->has('group_name')) {
            $learnerGroup->group_name = $request->group_name;
        }
        if ($request->has('limit')) {
            $learnerGroup->limit = $request->limit;
        }
        $learnerGroup->save();

        return response()->json([
            'OperationStatus' => 'OK',
            'subject' => [
                'group_id' => $learnerGroup->group_id,
                'group_name' => $learnerGroup->group_name,
                'group_access_code' => $learnerGroup->group_access_code,
                'limit' => $learnerGroup->limit
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function deleteGroup($group_id, Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Search the learner_group’s table to see whether the group_id exist and to
        // obtain the related subject_id.
        $learnerGroup = LearnerGroup::find($group_id);
        if (!$learnerGroup) {
            return ResponseHelper::groupNotExist();
        }

        // 2. Check that the user_id making the request matches the user_id in the
        // subjects table or the author_memberships table for the specific subject_id.
        $subject = $learnerGroup->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 3. Delete the entry in the learner_groups table if the status field is not ‘locked’.
        // Then also search the learner_group_memberships table and delete all
        // entries in the learner_group_memberships table that shares the group_id. (If
        // status is not locked)
        if ($learnerGroup->status === 'locked') {
            return ResponseHelper::validationError('The group cannot be deleted because the status field is locked.');
        }
        $learnerGroup->delete();
        LearnerGroupMembership::destroy($learnerGroup->learnerGroupMemberships->pluck('group_membership_id')->all());

        // 4. Also delete all the entries in the access_code_membership table that shares
        // the subject_id, user_id obtained from step 2 and has the type field set to
        // ‘learner’. (if the status was not locked)
        $accessCodeMemberships = $subject->accessCodeMemberships()->where('user_id', $user->user_id)->where('type', 'learner')->get();
        if ($accessCodeMemberships->count()) {
            AccessCodeMembership::destroy($accessCodeMemberships->pluck('access_membership_id')->all());
        }

        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }
}
