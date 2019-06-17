<?php

namespace App\Http\Controllers;

use App\User;
use Auth;
use Validator;
use Illuminate\Http\Request;
use App\Feedback;
use App\ResponseHelper;

class FeedbackController extends Controller
{
    
    public function submitFeedback(Request $request)
    {
         // Perform field validation checks.
        $user = Auth::User();
        $validatedData = Validator::make(
            $request->all(),
            [                
            'feedback' => 'required|integer|in:0,1',
            'question_id' => 'required|integer',
            'type' => 'required|string|in:multichoiceQuestion,matchingQuestion,numericalQuestion,studyNote',
            ],
            [            
            'question_id.required' => 'One or more of the required fields were not provided',
            'type.required' => 'One or more of the required fields were not provided',
            'feedback.required' => 'One or more of the required fields were not provided',
            'type.in' => 'The type should be one of the following: multichoiceQuestion, matchingQuestion, numericalQuestion, studyNote',
            'feedback.in' => 'The feedback field should be 0 or 1'
            ]);
        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }
        
        $feedback = Feedback::where('question_id',$request->question_id)->where('type',$request->type)->where('user_id',$user->user_id);
        // Ensure that a record does not exist in the feedback table for the user_id, question_id and type combination.
        if( $feedback->count() > 0 ){
            $feedback = $feedback->first();
            $feedback->feedback = $request->feedback;
            $feedback->created_date = new \DateTime();
            $feedback->save();
        } else {
            // Store result in feedback table if no match found.
                Feedback::create([
                'user_id' =>$user->user_id,
                'feedback' => $request->feedback,
                'question_id' => $request->question_id,
                'type' =>$request->type,
                'created_date' => new \DateTime()
            ]);
        }

        return response()->json([            
            "OperationStatus" => "OK"
        ]);
       
    }   
}

