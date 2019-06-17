<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Auth;
use Validator;
use App\ClassModel;
use App\ResponseHelper;
use App\ProgressTracking;
use App\Subject;


class SubjectProgressController extends Controller
{
    // This function returns progress data for the class and subject that is provided in event.
    // Validations:
    // 1. 'class_id', 'subject_id' and 'user_id' should be provided.
    // 2.'user_id' should be equal to user_id of the class.
    // 3. class should have a tracking id.
    public function getProgressData($class_id, $subject_id, Request $request)
    {
        
        $user = Auth::User();
        
        // validations
        $class = ClassModel::find($class_id);
        if (!$class) {
            return ResponseHelper::classDoesNotExist();
        }

        // Validate that user is the owner of the class
        if ($class->user_id !== $user->user_id) {
            return ResponseHelper::getUnauthorized();
        }

        // Check whether progress_tracking exists with class_id
        $progressTracking = $class->progressTracking;
        if (!$progressTracking) {
            return ResponseHelper::classHasNoProgressTracking();
        }

        // Get and check subject 
        $subject = Subject::find($subject_id);

        // Validate that subject exists
        if (!$subject) {
            return ResponseHelper::subjectNotExist();
        }

        // Retrieves a monthly count of question_results belonging to members of a class.
        $activityDict = \DB::table('question_results')->selectRaw('DATE_FORMAT(STR_TO_DATE(MONTH(`timestamp`), "%m"), "%b") as `month`, count(*) as `count`')
        ->whereRaw('YEAR(timestamp) = YEAR(CURDATE())')->whereRaw('`user_id` in (SELECT user_id from `class_memberships` WHERE `class_id` = ?)', [$class_id])
        ->where('subject_id', $subject_id)->groupBy(\DB::raw('MONTH(timestamp)'))->get();

        $yearActivity = collect([]);
        // When there is no activity, MySQL returns an array with one item having count = 0 and month = None.
        // To avoid returning meaningless arrays, we remove this case from the result using the if clause below
        if ($activityDict->count() > 1 || ($activityDict->count() === 1 && !empty($activityDict->first()->month))) {
            // Format activity results
            $days = $activityDict->map(function($item, $key) {
                return $item->month;
            });
            $yearActivity->put('days', $days);
            $quantity = $activityDict->map(function($item, $key) {
                return $item->count;
            });
            $yearActivity->put('quantity', $quantity);
        }

        // Retrieves all members of a class from the database.
        $classMembers = \DB::table('class_memberships')
        ->select('users.user_id', \DB::raw('CONCAT(`users`.`first_name`," ",`users`.`last_name`) as name'))
        ->leftJoin('users', 'users.user_id', '=', 'class_memberships.user_id')
        ->where('class_memberships.class_id', $class_id)->orderBy('name', 'asc')->get();

        // Retrieves all topics of a subject from the database.
        $topics = $subject->topics()->where('status', 'active')->where('published_status', 'published')->get();

        $subjectProgressList = collect([]);
        foreach ($topics as $topic) {
            $topicProgressData = collect([]);
            $topicProgressData->put('topic-name', $topic->topic_name);
            $skills = $topic->skills()->where('status', 'active')->where('published_status', 'published')->orderBy('order')->get();
            if ($skills->count()) {
                $skillNames = $skills->map(function($item, $key) {
                    return $item->skill_name;
                }); 
            } else {
                $skillNames = collect([]);
            }
            $topicProgressData->put('skill-names', $skillNames);

            foreach ($classMembers as $member) {
                // Retrieves quiz_results of a user for skills of a topic and subject.

                $userTopicSkillResults = \DB::select(
                    'SELECT qr.percentage, skills.skill_id
                    FROM skills
                    LEFT JOIN (
                        SELECT qr1.percentage, qr1.skill_id
                        FROM quiz_results qr1
                        LEFT JOIN quiz_results qr2 ON qr1.user_id = qr2.user_id AND qr1.timestamp < qr2.timestamp AND
                        qr1.skill_id = qr2.skill_id and qr1.subject_id = qr2.subject_id
                        WHERE qr1.subject_id = :subject_id and qr1.user_id = :user_id and qr2.timestamp IS NULL
                        and qr1.timestamp >= Date_add(Now(), interval - 12 month)
                    ) qr ON qr.skill_id = skills.skill_id
                    WHERE skills.topic_id = :topic_id AND skills.status = "active" AND published_status = "published"
                    ORDER BY skills.order ASC',
                    ['subject_id' => $subject_id, 'user_id' => $member->user_id, 'topic_id' => $topic->topic_id]
                );

                $userTopicSkillResults = collect($userTopicSkillResults);
                $percentages = $userTopicSkillResults->count() ? $userTopicSkillResults->pluck('percentage') : null;
                $topicProgressData->put($member->name, $percentages);
            }
            $subjectProgressList->push($topicProgressData);
        }
        return response()->json([
            'subject-questions-year-activity' => $yearActivity,
            'subject-progress' => $subjectProgressList
        ], 200, [], JSON_NUMERIC_CHECK);
    }
}
