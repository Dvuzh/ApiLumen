<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\ResponseHelper;
use App\Content;
use App\StudyNote;

class StudyNoteController extends Controller
{
    public function getStudyNotes($content_id)
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

        // 4. Lookup the entries in the study_notes table using the content_id provided.
        // 5. Only return question where the status field is ‘active’
        // 6. The results should be presented by created_date. (oldest first)
        $studyNotes = $content->studyNotes()
        ->select('question_id', 'published_status', 'study_note_content')
        ->where('status', 'active')->orderBy('created_date')->get();
        
        return response()->json($studyNotes, 200, [], JSON_NUMERIC_CHECK);
    }

    public function createStudyNote(Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform field validation checks.
        $validatedData = Validator::make(
            $request->all(),
            [
                'content_id' => 'required|integer',
                'published_status' => 'required|in:published,unpublished',
                'study_note_content' => 'required|string'
            ],
            [
                'content_id.required' => 'One or more of the required fields were not provided.',
                'published_status.required' => 'One or more of the required fields were not provided.',
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
                'study_note_content.required' => 'The study_note_content cannot be null/empty.'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
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
        // same content_id. Therefore, check the study_notes table to see how
        // many entries there are with the content_id.
        if ($content->studyNotes()->where('status', 'active')->count() >= 15) {
            return ResponseHelper::validationError('You have reached the limit of 15 versions.');
        }

        // 5. The study_note_content field can contain html and should be parsed and purified.
        $purifiedStudyNoteContent = htmlspecialchars_decode($request->input('study_note_content'));

        // 6. Create the entry in the study_notes table.
        $studyNote = StudyNote::create([
            'subject_id' => $subject->subject_id,
            'content_id' => $content->content_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'published_status' => $request->input('published_status'),
            'study_note_content' => $purifiedStudyNoteContent,
            'created_date' => new \DateTime()
        ]);

        return $this->prepareResponse($studyNote);
    }

    public function updateStudyNote($question_id, Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Perform validation checks
        $validatedData = Validator::make(
            $request->all(),
            [
                'published_status' => 'required_without_all:study_note_content|in:published,unpublished',
                'study_note_content' => 'required_without_all:published_status|string',
            ],
            [
                'published_status.in' => 'The published_status field has to be "published" or "unpublished"',
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 2. Using the question_id provided, get the subject_id belonging to the record by
        // searching the study_notes table.
        $studyNote = StudyNote::find($question_id);
        if (!$studyNote) {
            return ResponseHelper::validationError('The question_id provided does not exist.');
        }

        $subject = $studyNote->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id obtained from step 2.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 4. The study_note_content field can contain html and should be parsed and purified.
        if ($request->has('study_note_content')) {
            $purifiedStudyNoteContent = htmlspecialchars_decode($request->study_note_content);
        }

        // 5. Update the entry.
        if ($request->has('published_status')) {
            $studyNote->published_status = $request->published_status;
        }
        if ($request->has('study_note_content')) {
            $studyNote->study_note_content = $purifiedStudyNoteContent;
        }
        
        $studyNote->save();

        return $this->prepareResponse($studyNote);
    }

    public function deleteStudyNote($question_id)
    {
        
        $user = Auth::User();
        
        // 1. Check that the question_id exist in the study_notes table.
        $studyNote = StudyNote::find($question_id);
        if (!$studyNote) {
            return ResponseHelper::validationError('The question_id provided does not exist.');
        }

        // 2. Using the question_id provided, get the subject_id belonging to the record by 
        // searching the study_notes table.
        $subject = $studyNote->subject;
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
        $studyNote->status = 'inactive';
        $studyNote->save();
                
        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }

    private function prepareResponse($studyNote)
    {
        return response()->json([
            'OperationStatus' => 'OK',
            'study_note' => [
                'question_id' => $studyNote->question_id,
                'published_status' => $studyNote->published_status,
                'study_note_content' => $studyNote->study_note_content
            ]
        ], 200, [], JSON_NUMERIC_CHECK); 
    }
}
