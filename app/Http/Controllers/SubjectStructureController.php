<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\ResponseHelper;
use App\Subject;
use App\Content;
use App\Topic;
use App\Skill;

class SubjectStructureController extends Controller
{
    public function getSubjectStructure($subject_id)
    {

        $user = Auth::User();  
        // 1. Make sure subject_id exist in the subjects table.
        $subject = Subject::find($subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }
        
        // 2. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id provided.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 3. Simply get all records from the topics, skills and content table with status set to ‘active’
        // 4. The records should be returned in ascending order based on the order field.
        return $this->prepareResponse($subject);
    }


    public function updateSubjectStructure($subject_id, Request $request)
    {
        
        $user = Auth::User();
        
        $validatedData = Validator::make(
            $request->all(),
            [
                'topics.*.topic_id' => 'required|integer',
                'topics.*.order' => 'integer',
                'skills.*.skill_id' => 'required|integer',
                'skills.*.topic_id' => 'integer',
                'skills.*.order' => 'integer',
                'content.*.content_id' => 'required|integer',
                'content.*.skill_id' => 'integer',
                'content.*.order' => 'integer'
            ],
            [
                'topics.*.topic_id.required' => 'A topic_id does not exist (topics array)',
                'skills.*.skill_id.required' => 'A skill_id does not exist (skills array)',
                'content.*.content_id.required' => 'A content_id does not exist (content array)',
                'topics.*.order.integer' => 'The order numbers provided has to be integers.',
                'skills.*.order.integer' => 'The order numbers provided has to be integers.',
                'content.*.order.integer' => 'The order numbers provided has to be integers.'
            ]
        );

        if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        // 1. Make sure subject_id exist in the subjects table.
        $subject = Subject::find($subject_id);
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // 2. Make sure user_id making the request is either found in the subjects table or
        // the author_memberships table for the specific subject_id provided.
        if (!$subject->checkUserPermission($user->user_id)) {
            return ResponseHelper::permissionError();
        }

        // 3. Loop through the array of topics, skills and content items provided. Update
        // the records with the new details provided if the subject_id of the record that is
        // to be updated matches the subject_id provided. Also perform to validation checks.

        if ($request->has('topics')) {
            foreach ($request->topics as $topicData) {
                $topic = Topic::where('topic_id', $topicData['topic_id'])->first();
                if (!$topic) {
                    return ResponseHelper::topicNotExist();
                } elseif ($topic->subject_id != $subject_id) {
                    return ResponseHelper::validationError('The topic_id does not belong to the subject_id');
                }
                if (array_key_exists('order', $topicData)) {
                    $topic->order = $topicData['order'];
                    $topic->save();
                }
            }
        }

        if ($request->has('skills')) {
            foreach ($request->skills as $skillData) {
                $skill = Skill::where('skill_id', $skillData['skill_id'])->first();
                if (!$skill) {
                    return ResponseHelper::skillNotExist();
                } elseif ($skill->subject_id != $subject_id) {
                    return ResponseHelper::validationError('The skill_id does not belong to the subject_id');
                }
                if (array_key_exists('topic_id', $skillData) && $skillData['topic_id']) {
                    $topic = Topic::where('topic_id', $skillData['topic_id'])->first();
                    if (!$topic) {
                        return ResponseHelper::topicNotExist();
                    } elseif ($topic->subject_id != $subject_id) {
                        return ResponseHelper::validationError('The topic_id does not belong to the subject_id');
                    }
                    $skill->topic_id = $skillData['topic_id'];
                }
                if (array_key_exists('order', $skillData)) {
                    $skill->order = $skillData['order'];
                }
                $skill->save();
            }
        }

        if ($request->has('content')) {
            foreach ($request->content as $contentData) {
                $content = Content::where('content_id', $contentData['content_id'])->first();
                if (!$content) {
                    return ResponseHelper::contentNotExist();
                } elseif ($content->subject_id != $subject_id) {
                    return ResponseHelper::validationError('The content_id does not belong to the subject_id');
                }
                if (array_key_exists('skill_id', $contentData) && $contentData['skill_id']) {
                    $skill = Skill::where('skill_id', $contentData['skill_id'])->first();
                    if (!$skill) {
                        return ResponseHelper::skillNotExist();
                    } elseif ($skill->subject_id != $subject_id) {
                        return ResponseHelper::validationError('The skill_id does not belong to the subject_id');
                    }
                    $content->skill_id = $contentData['skill_id'];
                }
                if (array_key_exists('order', $contentData)) {
                    $content->order = $contentData['order'];
                }
                $content->save();
            }
        }

        return $this->prepareResponse($subject);
    }

    private function prepareResponse(Subject $subject)
    {
        $contents = \DB::table('content')->select('content_id', 'content_item_name', 'skill_id', 'order', 'type', 'published_status')
        ->where('status', 'active')->where('subject_id', $subject->subject_id)->get();
       
        $types = collect([]);
        $contentsWithKeys = collect([]);
        foreach ($contents as $content) {
            if (!$types->has($content->type)) {
                $types->put($content->type, collect([]));
            }
            $types->get($content->type)->push($content);
            $contentsWithKeys->put($content->content_id, $content);
            $contentsWithKeys->get($content->content_id)->question_count = 0;
        }


        $multichoiceQuestionCountsQuery = null;
        if ($types->has('multichoiceQuestion')) {
            $multichoiceQuestionCountsQuery = \DB::table('multichoice_questions')->select('content_id', \DB::raw('count(*) as question_count'))
            ->whereIn('content_id', $types->get('multichoiceQuestion')->pluck('content_id'))
            ->where('status', 'active')
            ->groupBy('content_id');
        }
        $matchingQuestionCountsQuery = null;
        if ($types->has('matchingQuestion')) {
            $matchingQuestionCountsQuery = \DB::table('matching_questions')->select('content_id', \DB::raw('count(*) as question_count'))
            ->whereIn('content_id', $types->get('matchingQuestion')->pluck('content_id'))
            ->where('status', 'active')
            ->groupBy('content_id');
        }
        $numericalQuestionCountsQuery = null;
        if ($types->has('numericalQuestion')) {
            $numericalQuestionCountsQuery = \DB::table('numerical_questions')->select('content_id', \DB::raw('count(*) as question_count'))
            ->whereIn('content_id', $types->get('numericalQuestion')->pluck('content_id'))
            ->where('status', 'active')
            ->groupBy('content_id');
        }
        $studyNoteCountsQuery = null;
        if ($types->has('studyNote')) {
            $studyNoteCountsQuery = \DB::table('study_notes')->select('content_id', \DB::raw('count(*) as question_count'))
            ->whereIn('content_id', $types->get('studyNote')->pluck('content_id'))
            ->where('status', 'active')
            ->groupBy('content_id');
        }

        $query = null;
        if ($multichoiceQuestionCountsQuery) {
            $query = $multichoiceQuestionCountsQuery;
        }
        if ($matchingQuestionCountsQuery) {
            if (!$query) {
                $query = $matchingQuestionCountsQuery;
            } else {
                $query->union($matchingQuestionCountsQuery);
            }
        }
        if ($numericalQuestionCountsQuery) {
            if (!$query) {
                $query = $numericalQuestionCountsQuery;
            } else {
                $query->union($numericalQuestionCountsQuery);
            }
        }
        if ($studyNoteCountsQuery) {
            if (!$query) {
                $query = $studyNoteCountsQuery;
            } else {
                $query->union($studyNoteCountsQuery);
            }
        }
        
        if ($query) {
            $questionCounts = $query->get();
            foreach ($questionCounts as $questionCount) {
                if ($contentsWithKeys->has($questionCount->content_id)) {
                    $contentsWithKeys->get($questionCount->content_id)->question_count = $questionCount->question_count;
                }
            }
        }
       
    
        return response()->json([
            'topics' => $subject->topics()->select('topic_id', 'topic_name', 'order', 'published_status')->where('status', 'active')->get()->all(),
            'skills' => $subject->skills()->select('skill_id', 'skill_name', 'topic_id', 'order', 'published_status')->where('status', 'active')->get()->all(),
            'content' => $contentsWithKeys->count() ? $contentsWithKeys->values()->all() : []
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
