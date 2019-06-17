<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Subject;
use App\Topic;
use App\Content;
use App\ResponseHelper;
use App\MultichoiceQuestion;
use App\MatchingQuestion;
use App\NumericalQuestion;
use App\StudyNote;

class TopicController extends Controller
{
    public function createTopic(Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform field validation checks on provided parameters.
        $validatedData = Validator::make(
            $request->all(),
            [
                'subject_id' => 'required|integer',
                'topic_name' => 'required|string|max:110',
                'order' => 'required|integer'
            ],
            [
                'subject_id.required' => 'One or more of the required fields were not provided.',
                'topic_name.required' => 'One or more of the required fields were not provided.',
                'topic_name.string' => 'The subject_name can only contain alphanumeric characters, spaces and apostrophes',
                'order.required' => 'One or more of the required fields were not provided.',
                'order.integer' => 'The order field should be an integer'
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

        // 3. Each subject_id can have a maximum of 50 active topics. (status field = active)
        if ($subject->topics()->where('status', 'active')->count() >= 50) {
            return ResponseHelper::validationError('You have reached the limit of 50 active topics per subject.');
        }

        // 4. Create topic entry in the topics table (status field is set to active for new entries)
        $topic = Topic::create([
            'topic_name' => $request->topic_name,
            'subject_id' => $request->subject_id,
            'order' => $request->order,
            'status' => 'active',
            'published_status' => 'unpublished',
            'created_date' => new \DateTime(),
            'user_id' => $user->user_id
        ]);

        return response()->json([
            'OperationStatus' => 'OK',
            'topic' => [
                'topic_id' => $topic->topic_id,
                'topic_name' => $topic->topic_name,
                'subject_id' => $topic->subject_id,
                'order' => $topic->order,
                'published_status' => $topic->published_status
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function updateTopic($topic_id, Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform field validation checks on the fields provided.
        $validatedData = Validator::make(
            $request->all(),
            [
                'published_status' => 'required_without_all:topic_name,order|in:published,unpublished',
                'topic_name' => 'required_without_all:published_status,order|string|max:110',
                'order' => 'required_without_all:published_status,topic_name|integer'
            ],
            [
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
                'topic_name.string' => 'The subject_name can only contain alphanumeric characters, spaces and apostrophes',
                'order.integer' => 'The order field should be an integer'
            ]
         );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        $topic = Topic::find($topic_id);
        if (!$topic) {
            return ResponseHelper::topicNotExist();
        }

        // 2. Establish the subject_id that belongs to the topic_id by searching the topics table.
        $subject = $topic->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }
    
        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. Update specific entry in the topics table if match was found.
        if ($request->has('topic_name')) {
            $topic->topic_name = $request->topic_name;
        }
        if ($request->has('order')) {
            $topic->order = $request->order;
        }
        if ($request->has('published_status')) {
            $topic->published_status = $request->published_status;
        }
        $topic->save();

        return response()->json([
            'OperationStatus' => 'OK',
            'topic' => [
                'topic_id' => $topic->topic_id,
                'topic_name' => $topic->topic_name,
                'subject_id' => $topic->subject_id,
                'order' => $topic->order,
                'published_status' => $topic->published_status
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function deleteTopic($topic_id)
    {
        
        $user = Auth::User();
        
        // 1. Check that the topic_id exist in the topics table.
        $topic = Topic::find($topic_id);
        if (!$topic) {
            return ResponseHelper::topicNotExist();
        }

        // 2. Establish the subject_id that belongs to the topic_id by searching the topics table.
        $subject = $topic->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. Do not actually delete the topic entry, simply change status field to inactive.
        // Also change the status to inactive for all entries in the following tables that
        // shares the topic_id: skills, content (through its relationship in the skills table),
        // and the following tables through its relationship in the skills and content
        // tables; multichoice_questions, matching_questions, numerical_questions,
        // study_notes.
        $topic->status = 'inactive';
        $topic->save();
        $topic->skills()->update(['status' => 'inactive']);
        $skillIds = $topic->skills()->pluck('skill_id')->all();
        if ($skillIds) {
            Content::whereIn('skill_id', $skillIds)->update(['status' => 'inactive']);
            $contentIds = Content::whereIn('skill_id', $skillIds)->pluck('content_id')->all();
            if ($contentIds) {
                MultichoiceQuestion::whereIn('content_id', $contentIds)->update(['status' => 'inactive']);
                MatchingQuestion::whereIn('content_id', $contentIds)->update(['status' => 'inactive']);
                NumericalQuestion::whereIn('content_id', $contentIds)->update(['status' => 'inactive']);
                StudyNote::whereIn('content_id', $contentIds)->update(['status' => 'inactive']);
            }
        }

        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }
}
