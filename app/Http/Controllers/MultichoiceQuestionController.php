<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\ResponseHelper;
use App\Content;
use App\MultichoiceQuestion;
use App\DynamoDbProvider;

class MultichoiceQuestionController extends Controller
{
    public function getMultichoiceQuestions($content_id)
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

        // 4. Lookup the entries in the multichoice_questions table using the content_id provided.
        // 5. Only return questions where the status field is 'active'.
        // 6. The results should be ordered by created_date. (oldest first)
        $multichoiceQuestions = $content->multichoiceQuestions()
        ->select('question_id', 'published_status', 'question_content', 'option_1', 'option_2', 'option_3', 'option_4', 'feedback', 'answer', 'time_limit')
        ->where('status', 'active')->orderBy('created_date')->get();
        
        return response()->json($multichoiceQuestions, 200, [], JSON_NUMERIC_CHECK);
    }

    public function createMultichoiceQuestion(Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform field validation checks
        $validatedData = Validator::make(
            $request->all(),
            [
                'content_id' => 'required|integer',
                'published_status' => 'required|in:published,unpublished',
                'question_content' => 'required|string',
                'option_1' => 'nullable|string',
                'option_2' => 'nullable|string',
                'option_3' => 'nullable|string',
                'option_4' => 'nullable|string',
                'feedback' => 'nullable|string',
                'answer' => 'required|in:option_1,option_2,option_3,option_4',
                'time_limit' => 'nullable|integer|min:10'
            ],
            [
                'content_id.required' => 'One or more of the required fields were not provided.',
                'published_status.required' => 'One or more of the required fields were not provided.',
                'question_content.required' => 'The question_content cannot be null/empty.',
                'answer.required' => 'One or more of the required fields were not provided.',
                'time_limit.required' => 'One or more of the required fields were not provided.',
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
                'answer.in' => 'The answer field should contain one of the following: option_1, option_2, option_3, option_4.',
                'time_limit.nullable' => 'The time_limit should be null or >=10.',
                'time_limit.min' => 'The time_limit should be null or >=10.'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // The answer field must be one of the option fields provided.
        if (!$request->has($request->answer) || $request->input($request->answer) === '' || is_null($request->input($request->answer))) {
            return ResponseHelper::validationError('The answer provided does not match one of the options provided.');
        }

        $options = collect([]);
        if ($request->has('option_1') && $request->option_1 !== '') {
            $options->put('option_1', $request->option_1);
        }
        if ($request->has('option_2') && $request->option_2 !== '') {
            $options->put('option_2', $request->option_2);
        }
        if ($request->has('option_3') && $request->option_3 !== '') {
            $options->put('option_3', $request->option_3);
        }
        if ($request->has('option_4') && $request->option_4 !== '') {
            $options->put('option_4', $request->option_4);
        }

        if ($options->count() < 2) {
            return ResponseHelper::validationError('There needs to be at least 2 options in the multichoice question. (3 options cannot be null)');
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
        // same content_id. Therefore, check the multichoice_questions table to see
        // how many entries there are with the content_id.
        if ($content->multichoiceQuestions()->where('status', 'active')->count() >= 15) {
            return ResponseHelper::validationError('You have reached the limit of 15 versions.');
        }

        // 5. The following fields can contain html and should be parsed/purified:
        // question_content, option_1, option_2, option_3, option_4 and feedback.
        $purifiedOptions = $options->map(function ($item, $key) {
            return htmlspecialchars_decode($item);
        });
        $purifiedFeedback = htmlspecialchars_decode($request->feedback);
        $purifiedQuestionContent = htmlspecialchars_decode($request->question_content);

        // 6. Create the entry in the multichoice_questions table.
        $multichoiceQuestion = MultichoiceQuestion::create([
            'subject_id' => $subject->subject_id,
            'content_id' => $content->content_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'published_status' => $request->published_status,
            'question_content' => $purifiedQuestionContent,
            'option_1' => $purifiedOptions->has('option_1') ? $purifiedOptions->get('option_1') : null,
            'option_2' => $purifiedOptions->has('option_2') ? $purifiedOptions->get('option_2') : null,
            'option_3' => $purifiedOptions->has('option_3') ? $purifiedOptions->get('option_3') : null,
            'option_4' => $purifiedOptions->has('option_4') ? $purifiedOptions->get('option_4') : null,
            'feedback' => ($purifiedFeedback !== '') ? $purifiedFeedback : null,
            'answer' => $request->answer,
            'time_limit' => ($request->input('time_limit') !== '') ? $request->input('time_limit') : null,
            'created_date' => new \DateTime()
        ]);

        return $this->prepareResponse($multichoiceQuestion);
    }

    public function updateMultichoiceQuestion($question_id, Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform field validation checks
        $validatedData = Validator::make(
            $request->all(),
            [
                'published_status' => 'required_without_all:question_content,option_1,option_2,option_3,option_4,feedback,answer,time_limit|in:published,unpublished',
                'question_content' => 'required_without_all:published_status,option_1,option_2,option_3,option_4,feedback,answer,time_limit|string',
                'option_1' => 'required_without_all:published_status,question_content,option_2,option_3,option_4,feedback,answer,time_limit|string',
                'option_2' => 'required_without_all:published_status,question_content,option_1,option_3,option_4,feedback,answer,time_limit|string',
                'option_3' => 'required_without_all:published_status,question_content,option_1,option_2,option_4,feedback,answer,time_limit|string',
                'option_4' => 'required_without_all:published_status,question_content,option_1,option_2,option_3,feedback,answer,time_limit|string',
                'feedback' => 'required_without_all:published_status,question_content,option_1,option_2,option_3,option_4,answer,time_limit|string',
                'answer' => 'required_without_all:published_status,question_content,option_1,option_2,option_3,option_4,feedback,time_limit|in:option_1,option_2,option_3,option_4',
                'time_limit' => 'required_without_all:published_status,question_content,option_1,option_2,option_3,option_4,feedback,answer|integer|min:10'
            ],
            [
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
                'answer.in' => 'The answer field should contain one of the following: option_1, option_2, option_3, option_4.',
                'time_limit.nullable' => 'The time_limit should be null or >=10.',
                'time_limit.min' => 'The time_limit should be null or >=10.'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 2. Using the question_id provided, get the subject_id belonging to the record by
        // searching the multichoice_questions table.
        $multichoiceQuestion = MultichoiceQuestion::find($question_id);
        if (!$multichoiceQuestion) {
            return ResponseHelper::validationError('The question_id provided does not exist.');
        }

        $options = collect([]);
        if ($request->has('option_1')) {
            $options->put('option_1', $request->option_1);
        } else {
            $options->put('option_1', $multichoiceQuestion->option_1);
        }
        if ($request->has('option_2')) {
            $options->put('option_2', $request->option_2);
        } else {
            $options->put('option_2', $multichoiceQuestion->option_2);
        }
        if ($request->has('option_3')) {
            $options->put('option_3', $request->option_3);
        } else {
            $options->put('option_3', $multichoiceQuestion->option_3);
        }
        if ($request->has('option_4')) {
            $options->put('option_4', $request->option_4);
        } else {
            $options->put('option_4', $multichoiceQuestion->option_4);
        }

        $optionsCount = 0;
        foreach ($options as $option) {
            if ($option !== '') {
                $optionsCount++;
            }
        }

        if ($optionsCount < 2) {
            return ResponseHelper::validationError('There needs to be at least 2 options in the multichoice question. (3 options cannot be null)');
        }

        // The answer field must be one of the option fields provided.
        if ($request->has('answer') && ($options->get($request->answer) === '' || is_null($options->get($request->answer)))) {
            return ResponseHelper::validationError('The answer provided does not match one of the options provided.');
        }

        $subject = $multichoiceQuestion->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. The following fields can contain html and should be parsed/purified:
        // question_content, option_1, option_2, option_3, option_4 and feedback.
        $purifiedOptions = $options->map(function ($item, $key) {
            return htmlspecialchars_decode($item);
        });
        if ($request->has('feedback')) {
            $purifiedFeedback = htmlspecialchars_decode($request->feedback);
        }
        if ($request->has('question_content')) {
            $purifiedQuestionContent = htmlspecialchars_decode($request->question_content);
        }
         
        // 6. Update the record.
        if ($request->has('published_status')) {
            $multichoiceQuestion->published_status = ($request->published_status !== '') ? $request->published_status : null;
        }
        if ($request->has('question_content')) {
            $multichoiceQuestion->question_content = ($purifiedQuestionContent !== '') ? $purifiedQuestionContent : null;
        }
        $purifiedOptions->each(function ($item, $key) use ($multichoiceQuestion) {
            $multichoiceQuestion->{$key} = ($item !== '') ? $item : null;
        });
        if ($request->has('feedback')) {
            $multichoiceQuestion->feedback = ($purifiedFeedback !== '') ? $purifiedFeedback : null;
        }
        if ($request->has('answer') && $request->answer !== '') {
            // 5. If the answer field is provided, search the in_progress table to see whether a
            // record exist that contains the question_id and type. If a record is found, do
            // not update the record.
            if (!$exist = DynamoDbProvider::findItem($question_id, 'multichoiceQuestion')) {
                $multichoiceQuestion->answer = $request->answer;
            } else {
                return ResponseHelper::validationError('Answer field cannot be updated because a quiz is already underway for this question.');
            }
        }
        if ($request->has('time_limit')) {
            $multichoiceQuestion->time_limit = ($request->time_limit !== '') ? $request->time_limit : null;
        }
        $multichoiceQuestion->save();

        return $this->prepareResponse($multichoiceQuestion);
    }

    public function deleteMultichoiceQuestion($question_id)
    {
        
        $user = Auth::User();
        
        // 1. Check that the question_id exist in the multichoice_questions table.
        $multichoiceQuestion = MultichoiceQuestion::find($question_id);
        if (!$multichoiceQuestion) {
            return ResponseHelper::validationError('The question_id provided does not exist.');
        }

        // 2. Using the question_id provided, get the subject_id belonging to the record by
        // searching the mulichoice_questions table.
        $subject = $multichoiceQuestion->subject;
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
        $multichoiceQuestion->status = 'inactive';
        $multichoiceQuestion->save();
        
        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }

    private function prepareResponse($multichoiceQuestion)
    {
        return response()->json([
            'OperationStatus' => 'OK',
            'multichoice_question' => [
                'question_id' => $multichoiceQuestion->question_id,
                'published_status' => $multichoiceQuestion->published_status,
                'question_content' => $multichoiceQuestion->question_content,
                'option_1' => $multichoiceQuestion->option_1,
                'option_2' => $multichoiceQuestion->option_2,
                'option_3' => $multichoiceQuestion->option_3,
                'option_4' => $multichoiceQuestion->option_4,
                'feedback' => $multichoiceQuestion->feedback,
                'answer' => $multichoiceQuestion->answer,
                'time_limit' => $multichoiceQuestion->time_limit
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
