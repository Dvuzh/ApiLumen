<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\ResponseHelper;
use App\Content;
use App\NumericalQuestion;
use App\DynamoDbProvider;

class NumericalQuestionController extends Controller
{
    public function getNumericalQuestions($content_id)
    {
        
        $user = Auth::User();
        
        // 1. Check whether the content_id exist in the content table.
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

        // 3. Make sure the user_id making the request is either found in the subjects
        // table or the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. Lookup the entries in the numerical_questions table using the content_id provided.
        // 5. Only return question where the status field is ‘active’
        // 6. The results should be presented by created_date. (oldest first)
        $numericalQuestions = $content->numericalQuestions()
        ->select('question_id', 'published_status', 'question_content', 'answer', 'feedback', 'time_limit')
        ->where('status', 'active')->orderBy('created_date')->get();
        
        return response()->json($numericalQuestions, 200, [], JSON_NUMERIC_CHECK);
    }

    public function createNumericalQuestion(Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform field validation checks.
        $validatedData = Validator::make(
            $request->all(),
            [
                'content_id' => 'required|integer',
                'published_status' => 'required|in:published,unpublished',
                'question_content' => 'required|string',
                'answer' => 'required|numeric',
                'feedback' => 'nullable|string',
                'time_limit' => 'nullable|integer|min:10'
            ],
            [
                'content_id.required' => 'One or more of the required fields were not provided.',
                'published_status.required' => 'One or more of the required fields were not provided.',
                'question_content.required' => 'The question_content cannot be null/empty.',
                'answer.required' => 'The answer cannot be null.',
                'time_limit.required' => 'One or more of the required fields were not provided.',
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
                'time_limit.nullable' => 'The time_limit should be null or >=10.',
                'time_limit.min' => 'The time_limit should be null or >=10.',
                'answer.numeric' => 'The answer field can only contain numbers with up to 2 decimals.'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        $answerArray = (explode('.', $request->answer));
        if (isset($answerArray[1]) && strlen($answerArray[1]) > 2) {
            return ResponseHelper::validationError('The answer field can only contain numbers with up to 2 decimals.');
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
        // same content_id. Therefore, check the numerical_questions table to see how
        // many entries there are with the content_id.
        if ($content->numericalQuestions()->where('status', 'active')->count() >= 15) {
            return ResponseHelper::validationError('You have reached the limit of 15 versions.');
        }

        // 5. The following fields can contain html and should be parsed and purified:
        // question_content, feedback.
        $purifiedFeedback = htmlspecialchars_decode($request->input('feedback'));
        $purifiedQuestionContent = htmlspecialchars_decode($request->input('question_content'));

        // 6. Create the entry in the numerical_questions table.
        $numericalQuestion = NumericalQuestion::create([
            'subject_id' => $subject->subject_id,
            'content_id' => $content->content_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'published_status' => $request->input('published_status'),
            'question_content' => $purifiedQuestionContent,
            'feedback' => ($purifiedFeedback !== '') ? $purifiedFeedback : null,
            'answer' => $request->input('answer'),
            'time_limit' => ($request->input('time_limit') !== '') ? $request->input('time_limit') : null,
            'created_date' => new \DateTime()
        ]);

        return $this->prepareResponse($numericalQuestion);
    }

    public function updateNumericalQuestion($question_id, Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform validation checks
        $validatedData = Validator::make(
            $request->all(),
            [
                'published_status' => 'required_without_all:question_content,feedback,answer,time_limit|in:published,unpublished',
                'question_content' => 'required_without_all:published_status,feedback,answer,time_limit|string',
                'feedback' => 'required_without_all:published_status,question_content,answer,time_limit|string',
                'time_limit' => 'required_without_all:published_status,question_content,feedback,answer|integer|min:10',
                'answer' => 'required_without_all:published_status,question_content,feedback,time_limit|numeric'
            ],
            [
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
                'answer.numeric' => 'The answer field as to be a number (max of 2 decimals)',
                'time_limit.nullable' => 'The time_limit should be null or >=10.',
                'time_limit.min' => 'The time_limit should be null or >=10.'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        if ($request->has('answer')) {
            $answerArray = (explode('.', $request->answer));
            if (isset($answerArray[1]) && strlen($answerArray[1]) > 2) {
                return ResponseHelper::validationError('The answer field can only contain numbers with up to 2 decimals.');
            }
        }

        // 2. Using the question_id provided, get the subject_id belonging to the record by
        // searching the numerical_questions table.
        $numericalQuestion = NumericalQuestion::find($question_id);
        if (!$numericalQuestion) {
            return ResponseHelper::validationError('The question_id provided does not exist.');
        }

        $subject = $numericalQuestion->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. The following fields can contain html and should be parsed and purified:
        // question_content, feedback.
        if ($request->has('feedback')) {
            $purifiedFeedback = htmlspecialchars_decode($request->feedback);
        }
        if ($request->has('question_content')) {
            $purifiedQuestionContent = htmlspecialchars_decode($request->question_content);
        }

        // 6. Update the entry.
        if ($request->has('published_status')) {
            $numericalQuestion->published_status = ($request->published_status !== '') ? $request->published_status : null;
        }
        if ($request->has('question_content')) {
            $numericalQuestion->question_content = ($purifiedQuestionContent !== '') ? $purifiedQuestionContent : null;
        }
        if ($request->has('feedback')) {
            $numericalQuestion->feedback = ($purifiedFeedback !== '') ? $purifiedFeedback : null;
        }
        if ($request->has('time_limit')) {
            $numericalQuestion->time_limit = ($request->time_limit !== '') ? $request->time_limit : null;
        }
        if ($request->has('answer') && $request->answer !== '') {
            // 5. If the answer field is provided, search the in_progress table to see whether a
            // record exist that contains the question_id and type. If a record is found, do
            // not update the record.
            if (!$exist = DynamoDbProvider::findItem($question_id, 'numericalQuestion')) {
                $numericalQuestion->answer = $request->answer;
            } else {
                return ResponseHelper::validationError('Answer field cannot be updated because a quiz is already underway for this question.');
            }
        }
        $numericalQuestion->save();

        return $this->prepareResponse($numericalQuestion);
    }

    public function deleteNumericalQuestion($question_id)
    {
        
        $user = Auth::User();
        
        // 1. Check that the question_id exist in the numerical_questions table.
        $numericalQuestion = NumericalQuestion::find($question_id);
        if (!$numericalQuestion) {
            return ResponseHelper::validationError('The question_id provided does not exist.');
        }

        // 2. Using the question_id provided, get the subject_id belonging to the record by
        // searching the numerical_questions table.
        $subject = $numericalQuestion->subject;
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
        $numericalQuestion->status = 'inactive';
        $numericalQuestion->save();
                
        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }

    private function prepareResponse($numericalQuestion)
    {
        return response()->json([
            'OperationStatus' => 'OK',
            'numerical_question' => [
                'question_id' => $numericalQuestion->question_id,
                'published_status' => $numericalQuestion->published_status,
                'question_content' => $numericalQuestion->question_content,
                'answer' => $numericalQuestion->answer,
                'feedback' => $numericalQuestion->feedback,
                'time_limit' => $numericalQuestion->time_limit
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
