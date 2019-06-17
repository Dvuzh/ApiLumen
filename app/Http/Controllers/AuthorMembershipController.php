<?php

namespace App\Http\Controllers;

use Auth;
use Validator;
use App\User;
use App\Subject;
use App\ResponseHelper;
use App\AuthorMembership;
use App\AccessCodeMembership;
use Illuminate\Http\Request;

class AuthorMembershipController extends Controller
{
    public function getAuthorMemberships($subject_id)
    {
        
        $user = Auth::User();
        
        // 1. Make sure that the subject_id exist in the subjects table.
        $subject = Subject::find($subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 2. Make sure user_id making the request matches the user_id in the subjects
        // table for the specific subject_id or the author_memberships table.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 3. Return all author_memberships associated with the subject_id. User details
        // for each author_membership can be found from the users table. Return
        // records in alphabetical order based on first_name property.
        $authorMemberships = AuthorMembership::select('author_memberships_id', 'status', 'first_name', 'last_name', 'email')
        ->leftJoin('users', function ($join) {
            $join->on('author_memberships.user_id', '=', 'users.user_id');
        })->where('subject_id', $subject->subject_id)
        ->orderBy('first_name')
        ->get();

        return response()->json($authorMemberships, 200, [], JSON_NUMERIC_CHECK);
    }

    public function createAuthorMembership(Request $request)
    {
        
        $user = Auth::User();
        
        $validatedData = Validator::make(
            $request->all(),
            [
                'email' => 'required|email|max:256',
                'subject_id' => 'required|integer',
            ],
            [
                'email.required' => 'One or more of the required fields were not provided.',
                'subject_id.required' => 'One or more of the required fields were not provided.',
            ]
         );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 1. Make sure subject_id exists in the subjects table.
        $subject = Subject::find($request->subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 2. Make sure user_id making the request matches the user_id in the subjects
        // table for the specific subject_id provided.
        if ($subject->user_id !== $user->user_id) {
            return ResponseHelper::permissionError();
        }

        // 3. Maximum number of author memberships is 30 per subject_id.
        if ($subject->authorMemberships->count() >= 30) {
            return ResponseHelper::validationError('Limit of 30 authors exceeded.');
        }

        // 4. Use the email address provided and lookup the user_id in the users table.
        $author = User::where('email', $request->email)->first();
        if (!$author) {
            return ResponseHelper::userNotFound();
        }
        // An author-membership should not be created if the user_id of the user (established by using the 
        // email address) is equal to the user_id for the specific subject_id provided. 
        if ($subject->user_id === $author->user_id) {
            return ResponseHelper::validationError('The user is already the owner of the course and cannot be added as an author');
        }

        // 5. Only one subject_id and user_id combination is allowed in the
        // author_memberships table.
        if (AuthorMembership::where('user_id', $author->user_id)->where('subject_id', $subject->subject_id)->first()) {
            return ResponseHelper::validationError('The user is already an author for the subject.');
        }

        // 6. Create the entry in the author_memberships table.
        $authorMembership = AuthorMembership::create([
            'user_id' => $author->user_id,
            'subject_id' => $subject->subject_id,
            'status' => 'awaiting',
            'created_date' => new \Datetime()
        ]);

        

        return response()->json([
            'OperationStatus' => 'OK',
            'author_membership' => [
                'author_memberships_id' => $authorMembership->author_memberships_id,
                'status' => $authorMembership->status,
                'first_name' => $author->first_name,
                'last_name' => $author->last_name,
                'email' => $author->email
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function deleteAuthorMembership($author_membership_id)
    {
        
        $user = Auth::User();
        
        // 1. Check that author_membership_id exist in the author_memberships table.
        $authorMembership = AuthorMembership::find($author_membership_id);
        if (!$authorMembership) {
            return ResponseHelper::authorMembershipNotExist();
        }

        // 2. Using the author_membership_id provided, lookup the entry in the
        // author_memberships table to establish the subject_id.
        $subject = $authorMembership->subject;
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure that user_id making the request matches the user_id in the
        // subjects table or the author_memberships table for the specific subject_id
        // obtained from step 2.
        if ($subject->user_id !== $user->user_id && $authorMembership->user_id !== $user->user_id) {
            return ResponseHelper::permissionError();
        }

        // 4. Remove entry from the author_memberships table.
        $authorMembership->delete();

        // 5. Remove entry from the access_code_memberships table that match the
        // following 3 conditions; subject_id obtained in step 2, user_id related to the
        // entry deleted in step 4 and if the type is ‘author’.
        $accessCodeMembership = AccessCodeMembership::where('subject_id', $subject->subject_id)
        ->where('user_id', $authorMembership->user_id)
        ->where('type', 'author')
        ->first();
        if ($accessCodeMembership) {
            $accessCodeMembership->delete();
        }

        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }

    public function authorMembershipDecision($author_membership_id, Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Make sure decision field in the request is either ‘accept’ or ‘reject’.
        $validatedData = Validator::make(
            $request->all(),
            [
                'decision' => 'required|in:accept,reject',
            ],
            [
                'decision.required' => 'One or more of the required fields were not provided.',
                'decision.in' => 'The decision field has to be “accept” or “reject”'
            ]
         );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 2. Check that the author_membership_id exist in the author_memberships table.
        $authorMembership = AuthorMembership::find($author_membership_id);
        if (!$authorMembership) {
            return ResponseHelper::authorMembershipNotExist();
        }

        // 3. Make sure user_id making the request matches the user_id associated with
        // the author_memberships record.
        if ($authorMembership->user_id !== $user->user_id) {
            return ResponseHelper::permissionError();
        }

        // 4. Make sure the current status of the entry is “awaiting”.
        $authorMembership->status = 'awaiting';

        // 5. If the decision field is accept, change the author_status field to active in the
        // author_memberships table.
        if ($request->decision === 'accept') {
            $authorMembership->status = 'active';
            $authorMembership->save();

            // 6. If the decision field is accept, create an entry in the
            // access_code_memberships table and set type to “author”.
            AccessCodeMembership::create([
                'user_id' => $user->user_id,
                'type' => 'author',
                'subject_id' =>  $authorMembership->subject_id,
                'date_registered' => new \Datetime()
            ]);
        } elseif ($request->decision === 'reject') {
            // 7. If the decision field is reject, delete the entry from the author_memberships table.
            $authorMembership->delete();
        }

        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }
}
