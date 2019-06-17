<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Subject;
use App\Topic;
use App\Skill;
use App\ResponseHelper;
use App\MultichoiceQuestion;
use App\MatchingQuestion;
use App\NumericalQuestion;
use App\StudyNote;

class SkillController extends Controller
{
    public function createSkill(Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform field validation checks on the fields provided.
        $validatedData = Validator::make(
            $request->all(),
            [
                'subject_id' => 'required|integer',
                'topic_id' => 'required|integer',
                'skill_name' => 'required|string|max:110',
                'order' => 'required|integer'
            ],
            [
                'subject_id.required' => 'One or more of the required fields were not provided.',
                'topic_id.required' => 'One or more of the required fields were not provided.',
                'skill_name.required' => 'One or more of the required fields were not provided.',
                'order.required' => 'One or more of the required fields were not provided.',
                'topic_id.integer' => 'The topic_id field should be an integer',
                'order.integer' => 'The order field should be an integer',
                'skill_name.string' => 'The subject_name can only contain alphanumeric characters, spaces and apostrophes'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 2. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id provided.
        $subject = Subject::find($request->subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }
        
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 3. Make sure the topic_id provided belongs to the subject_id provided. (Check
        // that specific subject_id field is present in the row with the topic_id provided in
        // the topics table.)
        $topic = Topic::find($request->topic_id);
        if ($topic->subject_id != $request->subject_id) {
            return ResponseHelper::validationError('The topic_id provided does not belong to the subject_id');
        }

        // 4. Each subject_id can have a maximum of 250 active skills. (status field = active)
        if ($subject->skills()->where('status', 'active')->count() >= 250) {
            return ResponseHelper::validationError('You have reached the limit of 250 active skills per subject.');
        }

        // 5. Create skill entry in the skills table if all conditions are met.
        $skill = Skill::create([
            'skill_name' => $request->skill_name,
            'topic_id' => $request->topic_id,
            'subject_id' => $request->subject_id,
            'order' => $request->order,
            'status' => 'active',
            'published_status' => 'unpublished',
            'created_date' => new \DateTime(),
            'user_id' => $user->user_id
        ]);

        return response()->json([
            'OperationStatus' => 'OK',
            'skills' => [
                'skill_id' => $skill->skill_id,
                'skill_name' => $skill->skill_name,
                'topic_id' => $skill->topic_id,
                'order' => $skill->order,
                'published_status' => $skill->published_status
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function updateSkill($skill_id, Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Check that the skill_id exist in the skills table and perform field validation
        // checks on the fields provided.

        $validatedData = Validator::make(
            $request->all(),
            [
                'skill_name' => 'required_without_all:published_status|string|max:110',
                'published_status' => 'required_without_all:skill_name|in:published,unpublished'
            ],
            [
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
                'skill_name.string' => 'The subject_name can only contain alphanumeric characters, spaces and apostrophes'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        $skill = Skill::find($skill_id);
        if (!$skill) {
            return ResponseHelper::skillNotExist();
        }

        // 2. Using the skill_id provided, find the subject_id associated with the skill_id by
        // searching the skills table.
        $subject = $skill->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. Update the record
        if ($request->has('skill_name')) {
            $skill->skill_name = $request->skill_name;
        }
        if ($request->has('published_status')) {
            $skill->published_status = $request->published_status;
        }
        $skill->save();

        return response()->json([
            'OperationStatus' => 'OK',
            'skills' => [
                'skill_id' => $skill->skill_id,
                'skill_name' => $skill->skill_name,
                'topic_id' => $skill->topic_id,
                'order' => $skill->order,
                'published_status' => $skill->published_status
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function deleteSkill($skill_id, Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Check that the skill_id exist in the skills table
        $skill = Skill::find($skill_id);
        if (!$skill) {
            return ResponseHelper::skillNotExist();
        }

        // 2. Using the skill_id provided, find the subject_id associated with the skill_id by
        // searching the skills table.
        $subject = $skill->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. Do not actually delete the skill entry, simply change status field to inactive.
        // Also change the status to inactive for all entries in the following tables that
        // shares the skill_id: content table and the following tables through its
        // relationship in the content table; multichoice_questions, matching_questions,
        // numerical_questions, study_notes.
        $skill->status = 'inactive';
        $skill->save();
        $skill->contents()->update(['status' => 'inactive']);
        $contentIds = $skill->contents()->pluck('content_id')->all();
        if ($contentIds) {
            MultichoiceQuestion::whereIn('content_id', $contentIds)->update(['status' => 'inactive']);
            MatchingQuestion::whereIn('content_id', $contentIds)->update(['status' => 'inactive']);
            NumericalQuestion::whereIn('content_id', $contentIds)->update(['status' => 'inactive']);
            StudyNote::whereIn('content_id', $contentIds)->update(['status' => 'inactive']);
        }

        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }
}
