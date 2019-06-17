<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Auth;
use Validator;
use App\ResponseHelper;
use App\Subject;
use App\Favourite;

class FavouriteController extends Controller
{
    public function createFavourite(Request $request)
    {
        
        $user = Auth::User();
        
        // Validations
        $validatedData = Validator::make(
            $request->all(),
            [
                "subject_id" => "required|integer",
            ],
            [
                "subject_id.required" => "'subject_id' is missing.",
                "subject_id.integer" => "'subject_id' should be integer."
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // Validate that subject exists
        $subject = Subject::find($request->subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // Validate that user_id-subject_id pair does not exist to avoid duplicates
        $favourite = Favourite::where('user_id', $user->user_id)->where('subject_id', $request->subject_id)->first();

        if ($favourite) {
            return ResponseHelper::favouriteAlreadyExists();
        }

        // Create a new favourite
        $favourite = Favourite::create([
            "user_id" => $user->user_id,
            "subject_id" => $subject->subject_id,
            "date_added" => new \DateTime()
        ]);

        $favouriteArray = array_merge(
            $favourite->toArray(), 
            [
                'icon_url' => $subject->subject_icon_url,
                'publisher_name' => $subject->publisher_name,
                'subject_name' => $subject->subject_name
            ]
        );

        return response()->json([
            'OperationStatus' => 'OK',
            'favourite' => $favouriteArray
        ], 200, [], JSON_NUMERIC_CHECK);
    }

    public function deleteFavourite($favourite_id)
    {
        
        $user = Auth::User();
        
        // Validate that favourite exist and owned by the user
        $favourite = Favourite::find($favourite_id);
        if (!$favourite) {
            return ResponseHelper::favouriteDoesNotExist();
        }

        if ($favourite->user_id !== $user->user_id) {
            return ResponseHelper::deleteUnauthorized();
        }

        // Perform deletion
        $favourite->delete();

        return response()->json(["OperationStatus" => "OK"]);
    }

    public function getFavourites($user_id)
    {
        $user = Auth::User();
        if ($user->user_id !== $user_id) {
            return ResponseHelper::getUnauthorized();
        }

        $favourites = \DB::table('favourites')
        ->select('favourites.favourite_id', 'subjects.subject_id', 'subjects.subject_name', 'subjects.subject_icon_url as icon_url', 'subjects.publisher_name')
        ->leftJoin('subjects', 'favourites.subject_id', '=', 'subjects.subject_id')
        ->where('favourites.user_id', $user_id)->orderBy('favourites.favourite_id', 'asc')->get();

        return response()->json($favourites, 200, [], JSON_NUMERIC_CHECK);
    }
}
