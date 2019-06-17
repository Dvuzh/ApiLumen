<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\ResponseHelper;
use App\Skill;
use App\Content;
use App\MultichoiceQuestion;
use App\MatchingQuestion;
use App\NumericalQuestion;
use App\StudyNote;

class ContentController extends Controller
{
    public function createContentItem(Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform field validation checks for all provided fields.
        $validatedData = Validator::make(
            $request->all(),
            [
                'skill_id' => 'required|integer',
                'type' => 'required|in:multichoiceQuestion,matchingQuestion,numericalQuestion,studyNote',
                'order' => 'required|integer',
                'content_item_name' => 'required|string|max:50'
            ],
            [
                'skill_id.required' => 'One or more of the required fields were not provided.',
                'type.required' => 'One or more of the required fields were not provided.',
                'order.required' => 'One or more of the required fields were not provided.',
                'content_item_name.required' => 'One or more of the required fields were not provided.',
                'type.in' => 'The type should be one of the following: multichoiceQuestion, matchingQuestion, numericalQuestion, studyNote',
                'content_item_name.string' => 'The content_item_name can only contain alphanumeric characters, spaces and apostrophes',
                'order.integer' => 'The order field should be an integer'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 2. Using the skill_id provided, get the subject_id and the topic_id for the skill.
        $skill = Skill::find($request->skill_id);
        if (!$skill) {
            return ResponseHelper::skillNotExist();
        }
        $subject = $skill->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained in step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. Each subject_id can have a maximum of 1000 active content items. Do not
        // add if this is exceeded. (status field is ‘active’)
        if ($subject->contents()->where('status', 'active')->count() >= 1000) {
            return ResponseHelper::validationError('You have reached the limit of 1000 content items per subject.');
        }

        // 5. There can only be a maximum of 15 content items per skill. Therefore, check
        // how many entries exist in the content table with the status field of ‘active’ and
        // with the skill_id provided.
        if ($skill->contents()->where('status', 'active')->count() >= 15) {
            return ResponseHelper::validationError('You have reached the maximum number of learning activities that you can add per skill.');
        }

        // 6. The published_status field for new records are set to ‘unpublished’.
        // 7. Create the record
        $content = Content::create([
            'content_item_name' => $request->content_item_name,
            'subject_id' => $subject->subject_id,
            'skill_id' => $request->skill_id,
            'order' => $request->order,
            'type' => $request->type,
            'status' => 'active',
            'published_status' => 'unpublished',
            'created_date' => new \DateTime(),
            'user_id' => $user->user_id
        ]);

        return response()->json([
            'OperationStatus' => 'OK',
            'content' => [
                'content_id' => $content->content_id,
                'content_item_name' => $content->content_item_name,
                'topic_id' => $skill->topic_id,
                'skill_id' => $content->skill_id,
                'order' => $content->order,
                'type' => $content->type,
                'published_status' => $content->published_status
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function updateContentItem($content_id, Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform field validation checks
        $validatedData = Validator::make(
            $request->all(),
            [
                'published_status' => 'required_without_all:content_item_name|in:published,unpublished',
                'content_item_name' => 'required_without_all:published_status|string|max:50'
            ],
            [
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
                'content_item_name.string' => 'The subject_name can only contain alphanumeric characters, spaces and apostrophes'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        $content = Content::find($content_id);
        if (!$content) {
            return ResponseHelper::contentNotExist();
        }

        // 2. Using the content_id provided, get the subject_id belonging to the record by
        // searching the content table.
        $subject = $content->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. The published_status field can only be set to ‘published’ or ‘unpublished’.
        // 5. Update the record.
        if ($request->has('published_status')) {
            $content->published_status = $request->published_status;
        }
        if ($request->has('content_item_name')) {
            $content->content_item_name = $request->content_item_name;
        }
        $content->save();

        return response()->json([
            'OperationStatus' => 'OK',
            'content' => [
                'content_id' => $content->content_id,
                'content_item_name' => $content->content_item_name,
                'topic_id' => $content->skill->topic_id,
                'skill_id' => $content->skill_id,
                'order' => $content->order,
                'type' => $content->type,
                'published_status' => $content->published_status
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function deleteContentItem($content_id)
    {
        
        $user = Auth::User();
        
        // 1. Using the content_id provided, get the subject_id belonging to the record by
        // searching the content table.
        $content = Content::find($content_id);
        if (!$content) {
            return ResponseHelper::contentNotExist();
        }
        $subject = $content->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 2. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 1.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 3. Do not actually delete the content entry, simply change the status field to
        // ‘inactive’. Also change the status to inactive for all the entries in the following
        // tables that shares the content_id: multichoice_questions, matching_questions, numerical_questions, study_notes.
        $content->status = 'inactive';
        $content->save();
        MultichoiceQuestion::where('content_id', $content->content_id)->update(['status' => 'inactive']);
        MatchingQuestion::where('content_id', $content->content_id)->update(['status' => 'inactive']);
        NumericalQuestion::where('content_id', $content->content_id)->update(['status' => 'inactive']);
        StudyNote::where('content_id', $content->content_id)->update(['status' => 'inactive']);

        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }
}
