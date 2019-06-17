<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Auth;
use Validator;
use App\ResponseHelper;
use App\Subject;
use App\AccessCodeMembership;

class SubjectSkillsController extends Controller
{
    public function getSubjectTopicsSkills($subject_id, $user_id, Request $request)
    {
        // Input validations
        $user = Auth::User();
        if ($user->user_id !== $user_id) {
            return ResponseHelper::userUpdateUnauthorized();
        }

        $subject = Subject::where('subject_id', $subject_id)->first();
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        $accessCodeMembership = AccessCodeMembership::where('user_id', $user->user_id)->where('subject_id', $subject_id)->first();
        $permission = $accessCodeMembership ? "Yes" : "No";

        $topics = $subject->topics()->where('topics.status', 'active')->where('topics.published_status', 'published')->with(['skills' => function($query) {
            $query->where('skills.status', 'active')->where('skills.published_status', 'published');
        }])->get();


        $topicsCollection = collect([]);
        foreach ($topics as $topic) {
            $skillCollections = collect([]);
            foreach ($topic->skills as $skill) {
                $skillCollection = collect($skill);
                $skillResult = $skill->quizResults()->select('percentage')->where('user_id', $user->user_id)->orderBy('timestamp', 'desc')->first();
                if ($skillResult) {
                    $skillCollection->put("skill_result", $skillResult->percentage);
                }
                $filteredSkillCollection = $skillCollection->only('skill_id', 'skill_name', 'iframe_url', 'skill_result');
                $filteredSkillCollection->put("skill_url", $filteredSkillCollection->get('iframe_url'));
                $filteredSkillCollection->put("permission", $permission);
                $skillCollections->push($filteredSkillCollection->forget('iframe_url'));
            }
            $topicCollection = collect($topic);
            $skillCollectionSorted = $skillCollections->sortBy("order")->values();
            $topicCollection->put("skills", $skillCollectionSorted);
            $topicCollection->put("count", $topic->skills->count());
            $topicsCollection->push($topicCollection->only('topic_name', 'skills', 'count'));
        }

        $subjectCollection = collect($subject);

        $subjectCollection->put("topics", $topicsCollection->sortBy("order")->values());

        $filteredSubjectCollection = $subjectCollection->only(['subject_id', 'subject_name', 'subject_icon_url', 'ebook_url', 'ebook_enabled',
        'ebook_image_url', 'author_image_url', 'publisher_name', 'ml_enabled', 'topics']);
        $filteredSubjectCollection->put('icon_url', $filteredSubjectCollection->get('subject_icon_url'));
        $filteredSubjectCollection->forget("subject_icon_url");
    
        return response()->json($filteredSubjectCollection, 200, [], JSON_NUMERIC_CHECK);
    }
}
