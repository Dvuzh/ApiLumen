<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\ResponseHelper;
use App\Content;
use App\MatchingQuestion;

class MatchingQuestionController extends Controller
{
    public function getMatchingQuestions($content_id)
    {
        
        $user = Auth::User();
        
        // 1. Check whether the content_id exist in the content table.
        $content = Content::find($content_id);
        if (!$content) {
            return ResponseHelper::contentNotExist();
        }

        // 2. Using the content_id provided, get the subject_id belonging to the record by
        // searching the content’s table.
        $subject = $content->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure the user_id making the request is either found in the subjects
        // table or the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. Lookup the entries in the matching_questions table using the content_id provided.
        // 5. Only return questions where the status field is 'active'.
        // 6. The results should be ordered by created_date. (oldest first)
        $matchingQuestions = $content->matchingQuestions()
        ->select(
            'question_id',
            'published_status',
            'question_content',
            'category_a_option_1',
            'category_a_option_2',
            'category_a_option_3',
            'category_a_option_4',
            'category_b_option_1',
            'category_b_option_2',
            'category_b_option_3',
            'category_b_option_4',
            'feedback',
            'time_limit'
        )
        ->where('status', 'active')->orderBy('created_date')->get();
        
        return response()->json($matchingQuestions, 200, [], JSON_NUMERIC_CHECK);
    }

    public function createMatchingQuestion(Request $request)
    {
        $user = Auth::User();
        
        // 1. Perform field validation checks
        $validatedData = Validator::make(
            $request->all(),
            [
                'content_id' => 'required|integer',
                'published_status' => 'required|in:published,unpublished',
                'question_content' => 'required|string',
                'category_a_option_1' => 'nullable|string',
                'category_a_option_2' => 'nullable|string',
                'category_a_option_3' => 'nullable|string',
                'category_a_option_4' => 'nullable|string',
                'category_b_option_1' => 'nullable|string',
                'category_b_option_2' => 'nullable|string',
                'category_b_option_3' => 'nullable|string',
                'category_b_option_4' => 'nullable|string',
                'feedback' => 'nullable|string',
                'time_limit' => 'nullable|integer|min:10'
            ],
            [
                'content_id.required' => 'One or more of the required fields were not provided.',
                'published_status.required' => 'One or more of the required fields were not provided.',
                'question_content.required' => 'The question_content cannot be null/empty.',
                'time_limit.required' => 'One or more of the required fields were not provided.',
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
                'time_limit.nullable' => 'The time_limit should be null or >=10.',
                'time_limit.min' => 'The time_limit should be null or >=10.'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        $optionsA = collect([]);
        for ($i=1; $i<=4; $i++) {
            if ($request->has('category_a_option_'.$i) && $request->input('category_a_option_'.$i) !== '') {
                $optionsA->put('option_'.$i, $request->{'category_a_option_'.$i});
            }
        }
        if ($optionsA->count() < 2) {
            return ResponseHelper::matchingQuestionOptionsError();
        }

        $optionsB = collect([]);
        for ($i=1; $i<=4; $i++) {
            if ($request->has('category_b_option_'.$i) && $request->input('category_b_option_'.$i) !== '') {
                $optionsB->put('option_'.$i, $request->{'category_b_option_'.$i});
            }
        }
        if ($optionsB->count() < 2) {
            return ResponseHelper::matchingQuestionOptionsError();
        }

        if (array_diff_key($optionsA->all(), $optionsB->all())) {
            return ResponseHelper::matchingQuestionOptionsError();
        }

        // 2. Using the content_id provided, get the subject_id belonging to the record by
        // searching the content table.
        $content = Content::find($request->content_id);
        if (!$content) {
            return ResponseHelper::contentNotExist();
        }

        $subject = $content->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. Only a maximum of 15 active entries (status field is ‘active’) can share the
        // same content_id. Therefore, check the matching_questions table to see
        // how many entries there are with the content_id.
        if ($content->matchingQuestions()->where('status', 'active')->count() >= 15) {
            return ResponseHelper::validationError('You have reached the limit of 15 versions.');
        }

        // 5. The following fields can contain html and should be parsed/purified:
        // question_content, category_a_option_1, category_a_option_2,
        // category_a_option_3, category_a_option_4, category_b_option_1,
        // category_b_option_2, category_b_option_3, category_b_option_4, feedback.
        $purifiedOptionsA = $optionsA->map(function ($item, $key) {
            return htmlspecialchars_decode($item);
        });
        $purifiedOptionsB = $optionsB->map(function ($item, $key) {
            return htmlspecialchars_decode($item);
        });
 
        $purifiedFeedback = htmlspecialchars_decode($request->feedback);
        $purifiedQuestionContent = htmlspecialchars_decode($request->question_content);

        // 6. Create the entry in the matching_questions table.
        $matchingQuestion = MatchingQuestion::create([
            'subject_id' => $subject->subject_id,
            'content_id' => $content->content_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'published_status' => $request->published_status,
            'question_content' => $purifiedQuestionContent,
            'category_a_option_1' => $purifiedOptionsA->has('option_1') ? $purifiedOptionsA->get('option_1') : null,
            'category_a_option_2' => $purifiedOptionsA->has('option_2') ? $purifiedOptionsA->get('option_2') : null,
            'category_a_option_3' => $purifiedOptionsA->has('option_3') ? $purifiedOptionsA->get('option_3') : null,
            'category_a_option_4' => $purifiedOptionsA->has('option_4') ? $purifiedOptionsA->get('option_4') : null,
            'category_b_option_1' => $purifiedOptionsB->has('option_1') ? $purifiedOptionsB->get('option_1') : null,
            'category_b_option_2' => $purifiedOptionsB->has('option_2') ? $purifiedOptionsB->get('option_2') : null,
            'category_b_option_3' => $purifiedOptionsB->has('option_3') ? $purifiedOptionsB->get('option_3') : null,
            'category_b_option_4' => $purifiedOptionsB->has('option_4') ? $purifiedOptionsB->get('option_4') : null,
            'feedback' => ($purifiedFeedback !== '') ? $purifiedFeedback : null,
            'time_limit' => ($request->has('time_limit') && $request->time_limit !== '') ? $request->time_limit : null,
            'created_date' => new \DateTime()
        ]);

        return $this->prepareResponse($matchingQuestion);
    }

    public function updateMatchingQuestion($question_id, Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform field validation checks
        $requestCollection = collect($request->all());
        $requiredFields = collect([
            'published_status',
            'question_content',
            'category_a_option_1',
            'category_a_option_2',
            'category_a_option_3',
            'category_a_option_4',
            'category_b_option_1',
            'category_b_option_2',
            'category_b_option_3',
            'category_b_option_4',
            'feedback',
            'time_limit'
        ]);

        if (!$requiredFields->intersect($requestCollection->keys())->count()) {
            return ResponseHelper::validationError('At least one parameter is required');
        }


        $validatedData = Validator::make(
            $request->all(),
            [
                'published_status' => 'in:published,unpublished',
                'question_content' => 'string',
                'category_a_option_1' => 'string',
                'category_a_option_2' => 'string',
                'category_a_option_3' => 'string',
                'category_a_option_4' => 'string',
                'category_b_option_1' => 'string',
                'category_b_option_2' => 'string',
                'category_b_option_3' => 'string',
                'category_b_option_4' => 'string',
                'feedback' => 'string',
                'time_limit' => 'integer|min:10'
            ],
            [
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
                'time_limit.nullable' => 'The time_limit should be null or >=10.',
                'time_limit.min' => 'The time_limit should be null or >=10.'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }


        // 2. Using the question_id provided, get the subject_id belonging to the record by
        // searching the matching_questions table.
        $matchingQuestion = MatchingQuestion::find($question_id);
        if (!$matchingQuestion) {
            return ResponseHelper::validationError('The question_id provided does not exist.');
        }

        $optionsA = collect([]);
        for ($i=1; $i<=4; $i++) {
            if ($request->has('category_a_option_'.$i)) {
                $optionsA->put('option_'.$i, $request->{'category_a_option_'.$i});
            } elseif ($matchingQuestion->{'category_a_option_'.$i}) {
                $optionsA->put('option_'.$i, $matchingQuestion->{'category_a_option_'.$i});
            }
        }

        $optionsACount = 0;
        foreach ($optionsA as $optionA) {
            if ($optionA !== '') {
                $optionsACount++;
            }
        }

        if ($optionsACount < 2) {
            return ResponseHelper::matchingQuestionOptionsError();
        }

        $optionsB = collect([]);
        for ($i=1; $i<=4; $i++) {
            if ($request->has('category_b_option_'.$i)) {
                $optionsB->put('option_'.$i, $request->{'category_b_option_'.$i});
            } elseif ($matchingQuestion->{'category_b_option_'.$i}) {
                $optionsB->put('option_'.$i, $matchingQuestion->{'category_b_option_'.$i});
            }
        }

        $optionsBCount = 0;
        foreach ($optionsB as $optionB) {
            if ($optionB !== '') {
                $optionsBCount++;
            }
        }

        if ($optionsBCount < 2) {
            return ResponseHelper::matchingQuestionOptionsError();
        }

        if (array_diff_key($optionsA->all(), $optionsB->all())) {
            return ResponseHelper::matchingQuestionOptionsError();
        }

        $subject = $matchingQuestion->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. The following fields can contain html and should be parsed/purified:
        // question_content, category_a_option_1, category_a_option_2,
        // category_a_option_3, category_a_option_4, category_b_option_1,
        // category_b_option_2, category_b_option_3, category_b_option_4, feedback.
        $purifiedOptionsA = $optionsA->map(function ($item, $key) {
            return htmlspecialchars_decode($item);
        });
        $purifiedOptionsB = $optionsB->map(function ($item, $key) {
            return htmlspecialchars_decode($item);
        });
        if ($request->has('feedback')) {
            $purifiedFeedback = htmlspecialchars_decode($request->feedback);
        }
        if ($request->has('question_content')) {
            $purifiedQuestionContent = htmlspecialchars_decode($request->question_content);
        }

        // 5. Update the entry
        if ($request->has('published_status')) {
            $matchingQuestion->published_status = ($request->published_status !== '') ? $request->published_status : null;
        }
        if ($request->has('question_content')) {
            $matchingQuestion->question_content = ($purifiedQuestionContent !== '') ? $purifiedQuestionContent : null;
        }
        $purifiedOptionsA->each(function ($item, $key) use ($matchingQuestion) {
            $matchingQuestion->{'category_a_'.$key} = ($item !== '') ? $item : null;
        });
        $purifiedOptionsB->each(function ($item, $key) use ($matchingQuestion) {
            $matchingQuestion->{'category_b_'.$key} = ($item !== '') ? $item : null;
        });
        if ($request->has('feedback')) {
            $matchingQuestion->feedback = ($purifiedFeedback !== '') ? $purifiedFeedback : null;
        }
        if ($request->has('time_limit')) {
            $matchingQuestion->time_limit = ($request->time_limit !== '') ? $request->time_limit : null;
        }
        $matchingQuestion->save();

        return $this->prepareResponse($matchingQuestion);
    }

    public function deleteMatchingQuestion($question_id)
    {
        
        $user = Auth::User();
        
        // 1. Check if the question_id exist in the matching_questions table.
        $matchingQuestion = MatchingQuestion::find($question_id);
        if (!$matchingQuestion) {
            return ResponseHelper::validationError('The question_id provided does not exist.');
        }
        
        // 2. Using the question_id provided, get the subject_id belonging to the record by
        // searching the matching_questions table.
        $subject = $matchingQuestion->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }
        
        // 3. Make sure the user_id making the request is either found in the subjects
        // table or the author_memberships table for the specific subject_id obtained
        // from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }
        
        // 4. If match is found, do not actually delete the question, simply change the
        // status field to ‘inactive’.
        $matchingQuestion->status = 'inactive';
        $matchingQuestion->save();
                
        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }

    private function prepareResponse(MatchingQuestion $matchingQuestion)
    {
        return response()->json([
            'OperationStatus' => 'OK',
            'matching_question' => [
                'question_id' => $matchingQuestion->question_id,
                'published_status' => $matchingQuestion->published_status,
                'question_content' => $matchingQuestion->question_content,
                'category_a_option_1' => $matchingQuestion->category_a_option_1,
                'category_a_option_2' => $matchingQuestion->category_a_option_2,
                'category_a_option_3' => $matchingQuestion->category_a_option_3,
                'category_a_option_4' => $matchingQuestion->category_a_option_4,
                'category_b_option_1' => $matchingQuestion->category_b_option_1,
                'category_b_option_2' => $matchingQuestion->category_b_option_2,
                'category_b_option_3' => $matchingQuestion->category_b_option_3,
                'category_b_option_4' => $matchingQuestion->category_b_option_4,
                'feedback' => $matchingQuestion->feedback,
                'time_limit' => $matchingQuestion->time_limit
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
