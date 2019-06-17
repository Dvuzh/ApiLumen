<?php

namespace App\Http\Controllers;

use App\LearnerGroup;
use App\AccessCode;
use Auth;
use DB;
use App\Subject;
use App\AvailableSubjectCode;
use App\ResponseHelper;
use App\AccessCodeMembership;
use App\LearnerGroupMembership;
use App\AuthorCode;
use Illuminate\Http\Request;
use Validator;

class AccessCodeController extends Controller
{
    public function generateCode($group_id)
    {
        $user = Auth::User();
        
        // 1. Make sure group_id exist in the learner_groups table.
        $learnerGroup = LearnerGroup::find($group_id);
        if (!$learnerGroup) {
            return ResponseHelper::groupNotExist();
        }

        // 2. Lookup the subject_id by searching the learner_groups table with the
        // group_id provided
        $subject = Subject::find($learnerGroup->subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 3. Make sure user_id making the request matches the user_id in the subjects
        // table for the specific subject_id provided or the author_memberships table.
        $authorMembership = $subject->getAuthorMembershipByUserid($user->user_id);
        if ($subject->user_id !== $user->user_id && !$authorMembership) {
            return ResponseHelper::permissionError();
        }

        // 4. Simply grab a new access code from available_subject_codes table. Update
        // group_access_code field in learner_groups table with the newly obtained
        // code and delete entry from the available_subject_codes table.
        $availableSubjectCode = AvailableSubjectCode::first();
        if (!$availableSubjectCode) {
            return ResponseHelper::noAvailableCodes();
        }
        $learnerGroup->group_access_code = $availableSubjectCode->access_code;
        $learnerGroup->save();
        $availableSubjectCode->delete();

        return response()->json([
            'OperationStatus' => 'OK',
            'subject' => [
                'group_id' => $learnerGroup->group_id,
                'group_access_code' => $learnerGroup->group_access_code
            ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function getMemberships($subject_id)
    {
        
        $user = Auth::User();
        
        // 1. Make sure the subject_id exist in the subjects table.
        $subject = Subject::find($subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 2. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id provided. (i.e. there
        // is a row in either table that contains both the subject_id and user_id field)
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 3. If match is found, simply return all records from the
        // access_code_membership table that contains the subject_id and where the
        // type field is ‘learner’. Additional user details for each membership record can
        // be found from the users table, learner_group table (through a join with the
        // learner_group_memberships table). Users will be returned in alphabetical
        // order based on first name.
        $learners = AccessCodeMembership::select(
            'access_code_memberships.access_membership_id',
            'users.first_name',
            'users.last_name',
            'users.email',
            'learner_group_memberships.group_id'
            )->join('users', function ($join) {
                $join->on('access_code_memberships.user_id', '=', 'users.user_id');
            })->join('learner_group_memberships', function ($join) {
                $join->on('access_code_memberships.user_id', '=', 'learner_group_memberships.user_id');
            })->join('learner_groups', function ($join) use ($subject_id) {
                $join->on('learner_group_memberships.group_id', '=', 'learner_groups.group_id')->where('learner_groups.subject_id', $subject_id);
            })->where('access_code_memberships.subject_id', $subject_id)
            ->where('access_code_memberships.type', 'learner')
            ->orderBy('users.first_name')
            ->get();
        
        // 4. Also, return all groups from the learner_groups table where the subject_id
        // field matches the subject_id provided.
        $groups = $subject->learnerGroups()->select('group_id', 'group_name', 'group_access_code', 'limit')->get();

        return response()->json([
            'groups' => $groups->all(),
            'learners' => $learners->all()
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function deleteMembership($access_membership_id)
    {
        
        $user = Auth::User();
        
        // 1. Check if the access_membership_id exist in the access_code_memberships table.
        $accessCodeMembership = AccessCodeMembership::find($access_membership_id);
        if (!$accessCodeMembership) {
            return ResponseHelper::notFoundError('Membership does not exist');
        }

        // 2. Using the access_membership_id provided, lookup the subject_id and
        // user_id associated with the entry in the access_code_memberships table.
        $subject = $accessCodeMembership->subject;
        $user_id = $accessCodeMembership->user_id;

        // 3. Then, using the subject_id obtained from step 2, make sure that the user_id
        // making the request matches the user_id associated with the row in the
        // subjects table or an entry in the author_memberships table that shares the
        // user_id and the subject_id obtained from step 2.
        if (!$subject->checkUserPermission( $user->user_id )) {
            return ResponseHelper::permissionError();
        }

        // 4. The access_membership_id entry can only be deleted if the type field in the
        // access_code_memberships table is ‘learner’. Also delete the entry in the
        // learner_group_memberships table for the subject_id and user_id
        // combination.
        if ($accessCodeMembership->type !== 'learner') {
            return ResponseHelper::validationError("A membership entry can only be deleted if it's type is 'learner'");
        }
        $accessCodeMembership->delete();
        LearnerGroupMembership::where('user_id', $user_id)->where('subject_id', $subject->subject_id)->delete();

        return response()->json([
            'OperationStatus' => 'OK'
        ]);
    }

    public function createAccessCodeMembership(Request $request)
    {
        
        $user = Auth::User();
        
        $validatedData = Validator::make(
            $request->all(),
            ['access_code' => 'required|string'],
            [
            'access_code.required' => 'One or more of the required fields were not provided',
            ]
        );
        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }


        // 1. Check the access_code provided against the access_codes table and the
        // learner_group’s table. (specifically the access_code column and
        // group_access_code column respectively)
        $accessCode = AccessCode::where('access_code', $request->access_code)->first();

        $learningGroup = LearnerGroup::where('group_access_code', $request->access_code)->first();

        if (!$accessCode && !$learningGroup) {
            return ResponseHelper::validationError('No match was found for your access_code');
        }

        $accessMemberships = collect([]);

        // 2. If a match was found in the learner_groups table, please ensure that an entry
        // does not exist in the access_code_memberships table that contains the
        // subject_id, user_id and where type field is ‘author or learner’. Do not continue
        // if an entry exist.
        if ($learningGroup) {
            $accessMembership = AccessCodeMembership::where('subject_id', $learningGroup->subject_id)->where('user_id', $user->user_id)->where(function ($query) {
                $query->where('type', 'learner')->orWhere('type', 'author');
            })->first();
            if ($accessMembership) {
                return ResponseHelper::validationError('You already have access to the subject for this access code.');
            }

            // 3. If no entry existed in step 2 and if the match was found in the learner_groups
            // table, simply check to see that the number of access_code_memberships
            // entries with the type field of ‘learner’ and the subject_id is below the limit
            // field in the subjects table for the subject_id and below the limit field in the
            // learner_groups table (number of entries in the learner_group_memberships
            // table for subject_id and group_id is below the limit field). If it is below,
            // continue to create the entry and set the type field to ‘learner’ in the
            // access_code_memberships table. Also create a record in the
            // learner_group_memberships table.
            $subject = $learningGroup->subject;

            $accessCodeMembershipCount = AccessCodeMembership::where('type', 'learner')->where('subject_id', $subject->subject_id)->count();
            if ($accessCodeMembershipCount >= $subject->limit) {
                return ResponseHelper::validationError('Learner limit has been reached for the specific access code.');
            }

            $learnerGroupMembershipCount = LearnerGroupMembership::where('subject_id', $subject->subject_id)->where('group_id', $learningGroup->group_id)->count();
            if ($learnerGroupMembershipCount >= $learningGroup->limit) {
                return ResponseHelper::validationError('Learner limit has been reached for the specific access code.');
            }
            
            $newAccessCodeMembership = AccessCodeMembership::create([
                'user_id' => $user->user_id,
                'subject_id' => $subject->subject_id,
                'date_registered' => new \DateTime(),
                'access_code' => $request->access_code,
                'type' => 'learner',
                'group_id' => $learningGroup->group_id
            ]);
            $accessMemberships->push($newAccessCodeMembership);

            LearnerGroupMembership::create([
                'group_id' => $learningGroup->group_id,
                'user_id' =>$user->user_id,
                'subject_id' => $subject->subject_id,
                'created_date' => new \DateTime()
            ]);
        }
            
        // 4. If the match was found in access_codes table, make sure that user_id has
        // not been assigned to the access code already. (If user_id field is not NULL,
        // then do not create membership entries) If the user_id field is NULL, create a
        // separate entry in access_code_memberships table for each subject_id in the
        // permissions column (separated by comma) and assign the user_id to user_id
        // field in the access_codes table. Also populate all fields using the information
        // in the access_codes table associated with the access_code.  Also, if the
        // match was found in the access_codes table and a group_id is present (not
        // null) in the group_id column, then also create an entry in the
        // learner_group_memberships table.
        if ($accessCode) {
            if ($accessCode->user_id) {
                return ResponseHelper::validationError('The access code has already been used.');
            }
            if ($accessCode->permissions) {
                $permissionsArray = explode(',', $accessCode->permissions);
                foreach ($permissionsArray as $permission) {
                    $newAccessCodeMembership = AccessCodeMembership::create([
                        'access_code_id' => $accessCode->access_code_id,
                        'user_id' => $user->user_id,
                        'subject_id' => $permission,
                        'date_registered' => new \DateTime(),
                        'access_code' => $accessCode->access_code,
                        'type' => $accessCode->type,
                        'plan' => $accessCode->plan,
                        'order_number' => $accessCode->order_number,
                        'group_id' => $accessCode->group_id,
                        'expiration_date' => $accessCode->expiration_date
                    ]);
                    $accessMemberships->push($newAccessCodeMembership);

                    if ($accessCode->group_id) {
                        LearnerGroupMembership::create([
                            'group_id' =>  $accessCode->group_id,
                            'user_id' =>$user->user_id,
                            'subject_id' => $permission,
                            'created_date' => new \DateTime()
                        ]);
                    }
                }
            }
            $accessCode->user_id = $user->user_id;
            $accessCode->save();

            // Upon successfully creating the memberships, use the school_id field and user_type field (from the access_codes table)
            // to update the school_id and user_type field of the user record in the users table.
            if ($accessMemberships->count()) {
                if ($accessCode->school_id !== null) {
                    $user->school_id = $accessCode->school_id;
                }
                if ($accessCode->user_type !== null) {
                    $user->user_type = $accessCode->user_type;
                }
                $user->save();
            }
        }
        return response()->json($accessMemberships->all(), 200, [], JSON_NUMERIC_CHECK);
    }

    public function registerAuthorCode(Request $request)
    {
        
        $user = Auth::User();
        
        // 1. Check that the author_code was provided.
        $validatedData = Validator::make(
            $request->all(),
            ['author_code' => 'required|string|max:8'],
            [
            'author_code.required' => 'One or more of the required fields were not provided',
            ]
        );
        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 2. Check the author_code provided against the author_codes table (specifically
        // the author_code field.)
        $authorCode = AuthorCode::where('author_code', $request->author_code)->first();
        if (!$authorCode) {
            return ResponseHelper::validationError('The author_code does not exist.');
        }
        
        // 3. If a match is found, look to see whether the user_id field is null and obtain the
        // other details in the row. If not null, return error.
        if ($authorCode->user_id) {
            return ResponseHelper::validationError('The author_code has already been registered.');
        }

        // 4. If the user_id field is null, update the user_id of the row with the user_id of the
        // requestor. (Obtained from the JWT Token)

        $authorCode->user_id = $user->user_id;
        $authorCode->save();

        // 5. Update the limit field of the user in the users table
        $user->limit = $authorCode->limit;

        // 6. Update the limit field of all the rows in the subjects table belonging to the user_id.
        \DB::table('subjects')->where('user_id', $user->user_id)->update(['limit' => $user->limit]);

        // 7. Upon successfully creating the memberships, use the school_id field and user_type field 
        // (from the author_codes table) to update the school_id and user_type field of the user record in the users table.
        if ($authorCode->school_id !== null) {
            $user->school_id = $authorCode->school_id;
        }
        if ($authorCode->user_type !== null) {
            $user->user_type = $authorCode->user_type;
        }
        
        $user->save();

        return response()->json([
                "OperationStatus" => "OK",
                "author_code_membership" => [
                    "author_code_id" => $authorCode->author_code_id,
                    "limit" => $authorCode->limit
                ]
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    // This function retrieves a list of distinct subjects of access_code_memberships that belong to a user from the MySQL database.
    // Validations:
    // 1. ['id'] and ['user_id'] should be provided.
    // 2. User ids should match: ['id'] should be equal to ['user_id'].
    public function accessCodeMembershipsList($user_id)
    {
        $user = Auth::User();
        if ($user->user_id !== $user_id) {
            return ResponseHelper::getUnauthorized();
        }

        $accessCodeMemberships = \DB::table('access_code_memberships')->distinct()
        ->select('access_code_memberships.subject_id', 'subjects.subject_name', 'subjects.publisher_name')
        ->leftJoin('subjects', 'access_code_memberships.subject_id', '=', 'subjects.subject_id')
        ->where('access_code_memberships.user_id', $user_id)->orderBy('subjects.subject_name', 'asc')->get();
        return response()->json($accessCodeMemberships, 200, [], JSON_NUMERIC_CHECK);
    }
}
