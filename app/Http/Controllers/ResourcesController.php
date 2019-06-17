<?php

namespace App\Http\Controllers;
use App\DynamoDbProvider;
use App\AccessCodeMembership;
use App\MultichoiceQuestion;
use App\NumericalQuestion;
use App\ResponseHelper;
use App\MatchingQuestion;
use App\Content;
use App\QuizResult;
use App\QuestionResult;
use App\StudyNote;
use App\Skill;
use App\User;
use Auth;
use Validator;
use Illuminate\Http\Request;

class ResourcesController extends Controller
{
    public function checkStatus($skill_id, Request $request)
    {
        $user = Auth::User();

        // 1. Using the skill_id provided and the user_id from the JWT token, search the
        // DynamoDB table to see whether a record exist with user_id and skill_id key.
        // (the key is the user_id and skill_id combined i.e. 3489576036345_11)
        $value = $user->user_id . '_' . $skill_id;
       
        $results = DynamoDbProvider::find($value);

        // 2. if no key is found, return no data. If the key is yes, iterate over the records
        // contained in the JSON document associated with the key and search the
        // respective tables for the question details using the ‘type’ field and
        // ‘question_id’ field for
        if (is_null($results)) {
             return response()->json([	            
                "errorMessage" => "NotFoundError - 305 - Resources does not exist",	
                "errorType" => "NotFoundError",	
            ]);	
        }
        $responseData = [];
           
        // 3. Return the records as shown in the Successful Response column in the
        // same order in which the records were stored in the JSON document
        // retrieved from DynamoDB.
        foreach ($results as $result) {
            foreach ($result as $item) {
                $timeUsed = DynamoDbProvider::getValue($item, "time_used");
                $timeLimit = DynamoDbProvider::getValue($item, "time_limit");
                $resultItem = DynamoDbProvider::getValue($item, "result");
                $type = DynamoDbProvider::getValue($item, "type");
                $question_id = DynamoDbProvider::getValue($item, "question_id");
                
                switch ($type) {
                    case 'multichoiceQuestion':
                        $subjects = MultichoiceQuestion::where('question_id', $question_id)->get();
                        foreach ($subjects as $subject) {
                            array_push(
                                $responseData,
                                [
                                    'type' => $type,
                                    'question_id' => $question_id,
                                    'question_content' => $subject->question_content,
                                    'option_1' => $subject->option_1,
                                    'option_2' => $subject->option_2,
                                    'option_3' => $subject->option_3,
                                    'option_4' => $subject->option_4,
                                    'result' => $resultItem,
                                    'time_used' => $timeUsed,
                                    'time_limit' => $subject->time_limit,
                                ]
                            );
                        }
                        break;

                    case 'numericalQuestion':
                        $subjects = NumericalQuestion::where('question_id', $question_id)->get();
                        foreach ($subjects as $subject) {
                            array_push($responseData, [
                                'type' => $type,
                                'question_id' => $question_id,
                                'question_content' => $subject->question_content,
                                'result' => $resultItem,
                                'time_used' => $timeUsed,
                                'time_limit' => $subject->time_limit,
                            ]);
                        }
                        break;
                    case 'matchingQuestion':
                        $subjects =  MatchingQuestion::where('question_id', $question_id)->get();
                        foreach ($subjects as $subject) {
                            $category= self::getCategory();
                            $cat_1 = $category[0];
                            $cat_2 = $category[1];
                            $cat_3 = $category[2];
                            $cat_4 = $category[3];
                            array_push($responseData, [
                                'type' => $type,
                                'question_id' => $question_id,
                                'question_content' => $subject->question_content,
                                'category_a_option_1'=> $subject->{'category_a_option_'.$cat_1},
                                'category_a_option_2'=> $subject->{'category_a_option_'.$cat_2},
                                'category_a_option_3'=> $subject->{'category_a_option_'.$cat_3},
                                'category_a_option_4'=> $subject->{'category_a_option_'.$cat_4},
                                'category_b_option_1'=> $subject->{'category_b_option_'.$cat_1},
                                'category_b_option_2'=> $subject->{'category_b_option_'.$cat_2},
                                'category_b_option_3'=> $subject->{'category_b_option_'.$cat_3},
                                'category_b_option_4'=> $subject->{'category_b_option_'.$cat_4},
                                'result' => $resultItem,
                                'time_used' => $timeUsed,
                                'time_limit' => $subject->time_limit,
                            ]);
                        }
                        break;
                    case 'studyNote':
                        $subjects = StudyNote::where('question_id', $question_id)->get();
                        foreach ($subjects as $subject) {
                            array_push($responseData, [
                                'type' => $type,
                                'question_id' => $question_id,
                                'study_note_content'=> $subject->study_note_content ,
                                'result' => $resultItem
                            ]);
                        }
                        break;
                }
            }
        }

        return response()->json($responseData, 200, [], JSON_NUMERIC_CHECK);
    }

    public static function learningResources($skill_id, Request $request){

        $user = Auth::User();    
        $skill = Skill::find($skill_id);
        //1. Perform field validations on provided fields.
        if (is_null($skill)) {
            return ResponseHelper::skillNotExist();
        }
        //2. Using the skill_id provided, get the subject_id belonging to the record by
        //searching the skills table.
        $subject_id = $skill->subject_id;

        //3. Make sure that an entry exist in the access_code_memberships table with
        //the user_id of the requestor and the subject_id obtained from step 2.
        if (AccessCodeMembership::where('user_id', $user->user_id)->where('subject_id', $subject_id)->count() == 0) {
            return ResponseHelper::permissionError();
        }
                
        // 4.Using the skill_id provided, search the content table to reveal all the content
        // entries that contains the skill_id, has the status field set to “active” and has
        // a published_status of “published”.
        $contents = Content::where('skill_id', $skill_id)->where('status', 'active')->where('published_status', 'published')->orderBy('order')
        ->orderBy('content_id')->get();
        
        $table = ['multichoiceQuestion', 'studyNote', 'matchingQuestion', 'numericalQuestion'];
        $responseData = [];
        $responseDataForDynamoDb = [];
        foreach ($contents as $content) {
            $randomType = $content->type;
            //$table[array_rand($table)];
            // $randomType = 'studyNote';
            
            switch ($randomType) {
                case 'multichoiceQuestion':
                    $subject = MultichoiceQuestion::where('content_id', $content->content_id)->where('status', 'active')->where('published_status', 'published')->inRandomOrder()->first();
                    if ($subject) {
                            array_push($responseData, [
                                'type' => $content->type,
                                'question_id' => $subject->question_id,
                                'question_content' => $subject->question_content,
                                'option_1' => $subject->option_1,
                                'option_2' => $subject->option_2,
                                'option_3' => $subject->option_3,
                                'option_4' => $subject->option_4,
                                'time_limit' => $subject->time_limit
                            ]);

                            array_push($responseDataForDynamoDb, [
                            "M" =>[
                            'type' => DynamoDbProvider::setValue("type", $randomType),
                            'question_id' => DynamoDbProvider::setValue("question_id", $subject->question_id) ,
                            'result' => DynamoDbProvider::setValue("result", null),
                            'time_used' => DynamoDbProvider::setValue("time_used", null),
                            'time_limit' => DynamoDbProvider::setValue("time_limit", null)
                            ]]);
                    }
                    break;

                case 'numericalQuestion':
                    $subject = NumericalQuestion::where('content_id', $content->content_id)->where('status', 'active')->where('published_status', 'published')->inRandomOrder()->first();
                    if ($subject) {
                        array_push($responseData, [
                        'type' => $content->type,
                        'question_id' => $subject->question_id,
                        'question_content' => $subject->question_content,
                        'time_limit' => $subject->time_limit
                        ]);

                        array_push($responseDataForDynamoDb, [
                        "M" =>[
                        'type' => DynamoDbProvider::setValue("type", $randomType),
                        'question_id' => DynamoDbProvider::setValue("question_id", $subject->question_id) ,
                        'result' => DynamoDbProvider::setValue("result", null),
                        'time_used' => DynamoDbProvider::setValue("time_used", null),
                        'time_limit' => DynamoDbProvider::setValue("time_limit", null)
                        ]]);
                    }
                    
                    break;
                case 'matchingQuestion':
                    $subject =  MatchingQuestion::where('content_id', $content->content_id)->where('status', 'active')->where('published_status', 'published')->inRandomOrder()->first();
                        $category= self::getCategory();
                        $cat_1 = $category[0];
                        $cat_2 = $category[1];
                        $cat_3 = $category[2];
                        $cat_4 = $category[3];
                    if ($subject) {
                        array_push($responseData, [
                        'type' => $content->type,
                        'question_id' => $subject->question_id,
                        'question_content' => $subject->question_content,
                        'category_a_option_1' => $subject->{'category_a_option_'.$cat_1},
                        'category_a_option_2' => $subject->{'category_a_option_'.$cat_2},
                        'category_a_option_3' => $subject->{'category_a_option_'.$cat_3},
                        'category_a_option_4' => $subject->{'category_a_option_'.$cat_4},
                        'category_b_option_1' => $subject->{'category_b_option_'.$cat_1},
                        'category_b_option_2' => $subject->{'category_b_option_'.$cat_2},
                        'category_b_option_3' => $subject->{'category_b_option_'.$cat_3},
                        'category_b_option_4' => $subject->{'category_b_option_'.$cat_4},
                        'time_limit' => $subject->time_limit,
                        ]);

                        array_push($responseDataForDynamoDb, [
                        "M" =>[
                        'type' => DynamoDbProvider::setValue("type", $randomType),
                        'question_id' => DynamoDbProvider::setValue("question_id", $subject->question_id),
                        'result' => DynamoDbProvider::setValue("result", null) ,
                        'time_used' => DynamoDbProvider::setValue("time_used", null) ,
                        'time_limit' => DynamoDbProvider::setValue("time_limit", null)
                        ]
                        ]);
                    }
                    
                    break;

                case 'studyNote':
                    $subject =  StudyNote::where('content_id', $content->content_id)->where('status', 'active')->where('published_status', 'published')->inRandomOrder()->first();
                    if ($subject) {
                        array_push($responseData, [
                        'type' => $content->type,
                        'question_id' => $subject->question_id ,
                        'study_note_content'=> $subject->study_note_content
                        ]);

                        array_push($responseDataForDynamoDb, [
                        "M" =>[
                        'type' => DynamoDbProvider::setValue("type", $randomType),
                        'question_id' => DynamoDbProvider::setValue("question_id", $subject->question_id) ,
                        'result' => DynamoDbProvider::setValue("result", null),
                        'time_used' => DynamoDbProvider::setValue("time_used", null),
                        'time_limit' => DynamoDbProvider::setValue("time_limit", null)
                        ]]);
                    }
                    break;
                }
            }

        // 6.Search the DynamoDB table and remove the record that contains the skill_id
        // and user_id key if it exists.
        $valueKey = $user->user_id . '_' . $skill_id;
        $results = DynamoDbProvider::find($valueKey);
        if(!is_null($results)){
            DynamoDbProvider::delete($valueKey);
        }

        //7. Insert a new entry into the DynamoDB table
        DynamoDbProvider::addItem($valueKey, $responseDataForDynamoDb);
        return response()->json($responseData, 200, [], JSON_NUMERIC_CHECK);    
    }

    public function checkAnswer(Request $request){
        
        // Perform field validation checks.
        $user = Auth::User();
        $validatedData = Validator::make(
            $request->all(),
            [                
                'skill_id' => 'required|integer',
                'question_id' => 'required|integer',
                'type' => 'required|string|in:multichoiceQuestion,matchingQuestion,numericalQuestion,studyNote',
                'time_used' => 'integer|nullable',
                'multichoiceAnswer' => 'in:option_1,option_2,option_3,option_4',
                'numericalAnswer' => 'numeric',
                'studyNoteAnswer' => 'in:1',
            ],
            [
                'in' => 'multichoiceQuestionAnswer must be "option_1", "option_2", "option_3" or "option_4"',
                'question_id.required' => 'One or more of the required fields were not provided',
                'type.required' => 'One or more of the required fields were not provided',
                'time_used.integer' => 'The time_used fields must be an integer',
                'type.in' => 'The type should be one of the following: multichoiceQuestion, matchingQuestion, numericalQuestion, studyNote',
                'skill_id.required' => 'One or more of the required fields were not provided'
            ]
            );
        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }
                    
        // Proceed to lookup the question_id in the respective question table to see if
        // the entry exist and to retrieve the additional information such as the skill_id,
        // time_limit and other fields that are required below.

        $type = $request->type;
        $subject = null;
        $answer = null;
        $result = 1;
        switch ($type) {
            case 'multichoiceQuestion':
                $subject = MultichoiceQuestion::find($request->question_id);
                if (is_null($subject)) {
                    return ResponseHelper::questionNotExist();
                }
                if($request->has('multichoiceAnswer')){
                    
                    if($subject->answer != $request->multichoiceAnswer){
                        $result = 0;
                    }
                    $answer = $subject->answer;
                } else{
                    $result = 0; 
                }
                
                break;

            case 'numericalQuestion':
                $subject = NumericalQuestion::find($request->question_id);
                if (is_null($subject)) {
                    return ResponseHelper::questionNotExist();
                }
                if($request->has('numericalAnswer')){
                    if($subject->answer != $request->numericalAnswer){
                        $result = 0;
                    }
                    $answer = $subject->answer;
                } else {
                    $result = 0;
                }
            
                break;
            case 'matchingQuestion':
                $subject =  MatchingQuestion::find($request->question_id);
                if (is_null($subject)) {
                    return ResponseHelper::questionNotExist();
                }
                if($request->has('matchingAnswer')){
                    $itemsMathingAnswer = explode(',', $request->matchingAnswer);
                    $resultArray = ["11","22","33","44"];
                    foreach($itemsMathingAnswer as $item){
                        if(! In_array($item,$resultArray)){
                            $result = 0;
                        }
                    }
                    $answer = '11,22,33,44';
                } else {
                    $result = 0;
                }
                
                
                break;
            case 'studyNote':
                $subject =  StudyNote::find($request->question_id);
                if (is_null($subject)) {
                    return ResponseHelper::questionNotExist();
                }
                if($request->has('studyNoteAnswer')){
                    if($request->studyNoteAnswer != 1){
                        $result = 0;
                    }
                    $answer = $request->studyNoteAnswer;
                } else {
                    $result = 0;
                }
                
                break;
            default:
                return ResponseHelper::typeTableQuestion();
            }

        $feedback = $subject->feedback;

        // Check if the answer field that was provided matches the entry with the
        // question_id in the respective table. (Where the type is multichoiceQuestion or
        // numericalQuestion.) Where the type is studyNote the studyNoteAnswer field
        // should be checked to see if it is 1. (No database check required) Where it is
        // 1, the result returned will be “correct”. Where the type is matchingQuestion
        // the result field returned will be “correct” when each number (separated by
        // comma) in the matchingAnswer field matches any of the following numbers:
        // 11, 22, 33 or 44. (No database check required)


        // Store the result in the questions_results table (all fields).
        $questionsResults = QuestionResult::create([
            'user_id' => $user->user_id,
            'subject_id' => $subject->subject_id,
            'skill_id' => $request->skill_id,
            'question_id' => $request->question_id,
            'type' => $type,
            'timestamp' => new \Datetime(),
            'result' => $result,
            'time_limit' => $subject->time_limit,
            'time_used' => $request->has('time_used') ? $request->time_used : NULL,
        ]);

        //Also search the in_progress table for the key (user_id + skill_id) and update the result
        $value = $user->user_id . '_' . $request->skill_id;        
        $responseDataForDynamoDb =  [
            "M" =>[
            'type' => DynamoDbProvider::setValue("type", $type),
            'question_id' => DynamoDbProvider::setValue("question_id", $request->question_id) ,
            'result' => DynamoDbProvider::setValue("result", $result ),
            'time_used' => DynamoDbProvider::setValue("time_used", $request->has('time_used') ? $request->time_used : NULL ),
            'time_limit' => DynamoDbProvider::setValue("time_limit", $subject->time_limit )
        ]];

        $results = DynamoDbProvider::find($value);

        if (is_null($results)) {
            http_response_code(404);
            return response()->json([
                "errorMessage" => "NotFoundError - 305 - No results were found.",
                "errorType" => "NotFoundError",
            ]);
        }else {
            $updateTableItem = DynamoDBProvider::updateItem($results, $type, $responseDataForDynamoDb, $value);
        }

        $responseData = [
            'OperationStatus' => 'OK',
            'question_result' => [
                'question_id' => $request->question_id,
                'result' => $result == 1 ? 1 : 0,
                'feedback' => $feedback,
                'answer' => $answer
            ]
        ];

        return response()->json($responseData, 200, [], JSON_NUMERIC_CHECK);
    }

    public function submitSkillResults(Request $request){
        
        $user = Auth::User();
        
        // Perform field validations on provided fields.
        $validatedData = Validator::make(
            $request->all(), [
                'skill_id' => 'required|integer',                
            ], [                            
                'skill_id.required' => 'One or more of the required fields were not provided'
            ]
         );
        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        $skill = Skill::find($request->skill_id);
        if (is_null($skill)) {
            return ResponseHelper::skillNotExist();
        }

        // Lookup the skill_id to obtain the subject_id and additional details.
        $subject_id = $skill->subject_id;
        //Using the user_id and skill_id, lookup the results in the in_progress DynamoDB table.
        $value = $user->user_id . '_' . $skill->skill_id;
       
        $results = DynamoDbProvider::find($value);
        if (is_null($results)) {
            http_response_code(404);
            return response()->json([
                "errorMessage" => "NotFoundError - 305 - No results were found.",
                "errorType" => "NotFoundError",
            ]);
        }
        $calcResult = DynamoDBProvider::calculatedItem($results);

        //Create a row in the quiz_results table
        $quizResult = QuizResult::create([
            'percentage' => $calcResult['percentage'],
            'user_id' => $user->user_id,
            'subject_id' => $subject_id,
            'skill_id' => $skill->skill_id,
            'time_limit' => $calcResult['time_limit'],
            'used_time' => $calcResult['time_used'],
            'timestamp' => new \Datetime()
        ]);
        //Remove the entry from the DynamoDB database.
        DynamoDbProvider::delete($value);
        
        $responseData[] = [
            'OperationStatus' => 'OK',
            'quiz_result' => [
                'skill_id' => $skill->skill_id,
                'percentage' => number_format($calcResult['percentage'],2) 
            ]
        ];
        
        return response()->json($responseData, 200, [], JSON_NUMERIC_CHECK);
    }

    private static function getCategory(){
        $input = [1, 2, 3, 4];
        $rand_key = rand(2,4);
        unset($input[0]);
        unset($input[array_search($rand_key, $input)]);
        $input = array_values($input);
        $category = [       
        '0' => $rand_key,
        '1' => (int)(in_array("2",$input) ? ($input[0] == "2" ? $input[1] : $input[0]) : "1"),
        '2' => (int)(in_array("3",$input) ? ($input[0] == "3" ? $input[1] : $input[0]) : "1"),
        '3' => (int)(in_array("4",$input) ? ($input[0] == "4" ? $input[1] : $input[0]) : "1")
        ];
        return $category;
    }
}
