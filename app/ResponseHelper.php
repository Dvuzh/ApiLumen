<?php

namespace App;

class ResponseHelper
{
    public static function validationError($error, $code=null)
    {
        return response()->json([
            "errorMessage" => "ValidationError - " . ($code ? $code." - " : "") . $error,
            "errorType"=> "ValidationError"
        ], 400);
    }

    public static function notFoundError($error, $code=null)
    {
        return response()->json([
            "errorMessage" => "NotFoundError - " . ($code ? $code." - " : "") . $error,
            "errorType"=> "NotFoundError"
        ], 404);
    }

    public static function forbiddenError($error, $code=null)
    {
        return response()->json([
            "errorMessage" => "ForbiddenError - " . ($code ? $code." - " : "") . $error,
            "errorType"=> "ForbiddenError"
        ], 403);
    }

    public static function permissionError()
    {
        return self::validationError('User does not have the permission');
    }

    public static function noAvailableCodes()
    {
        return self::notFoundError("No available access codes");
    }

    public static function subjectNotExist()
    {
        return self::validationError("The subject_id does not exist");
    }

    public static function subjectProductDoesNotExist()
    {
        return self::notFoundError("Subject does not exist in products.", 306);
    }

    public static function contentNotExist()
    {
        return self::validationError("The content_id does not exist");
    }

    public static function skillNotExist()
    {
        return self::validationError("The skill_id does not exist.");
    }

    public static function topicNotExist()
    {
        return self::validationError("The topic_id does not exist.");
    }

    public static function groupNotExist()
    {
        return self::validationError("The group_id does not exist.");
    }

    public static function authorMembershipNotExist()
    {
        return self::validationError("The author_membership_id does not exist");
    }

    public static function userNotFound()
    {
        return self::notFoundError("User does not exist.", 205);
    }

    public static function limitSubjects()
    {
        return self::validationError('You have reached the limit of 150 subjects');
    }

    public static function classDoesNotExist()
    {
        return self::notFoundError("Class does not exist.", 402);
    }

    public static function trackingCodeAlreadyUsed()
    {
        return self::validationError("This tracking code is already used.", 802);
    }

    public static function progressTrackingAlreadyExists()
    {
        return self::validationError("Progress tracking already exists.", 803);
    }

    public static function trackingCodeDoesNotExist()
    {
        return self::notFoundError("Tracking code does not exist.", 805);
    }

    public static function favouriteAlreadyExists()
    {
        return self::validationError("You already have this subject in your favourites.", 301);
    }

    public static function matchingQuestionOptionsError()
    {
        return self::validationError('There needs to be at least 2 options from each category. Also, the options should 
        correspond. For example if the following 2 fields are provided: category_a_option_1 
        and category_a_option_2, then the following fields should also be provided: 
        category_b_option_1, category_b_option_2');
    }

    public static function questionNotExist()
    {
        return self::validationError("The question_id does not exist.");
    }

    public static function typeTableQuestion()
    {
        return self::notFoundError("The type has to be multichoiceQuestion, matchingQuestion, numericalQuestion or studyNote");
    }

    public static function feedbackQuestion()
    {
        return self::notFoundError("You have already rated the question.");
    }

    public static function trackingUnauthorizedError()
    {
        return self::forbiddenError("You do not have permission to request tracking for this class.", 804);
    }

    public static function favouriteDoesNotExist()
    {
        return self::notFoundError("Favourite does not exist.", 304);
    }

    public static function deleteUnauthorized()
    {
        return self::forbiddenError("You do not have permission to delete this resource.", 102);
    }

    public static function getUnauthorized()
    {
        return self::forbiddenError("You do not have permission to retrieve this resource.", 103);
    }

    public static function userUpdateUnauthorized()
    {
        return self::forbiddenError("You do not have permission to update this user's details.", 203);
    }

    public static function classHasNoProgressTracking()
    {
        return self::validationError("The class does not have any progress_tracking associated.", 507);
    }

    public static function updateUnauthorized()
    {
        return self::validationError("You do not have permission to update this resource.", 104);
    }

    public static function classCodeDoesNotExist()
    {
        return self::notFoundError("The class code does not exist.", 502);
    }

    public static function classMembershipAlreadyExists()
    {
        return self::validationError("You are already a member of this class.", 503);
    }

    public static function classMembershipLimit()
    {
        return self::validationError("The class has reached the limit on the number of students.", 505);
    }

    public static function classMembershipDoesNotExist()
    {
        return self::notFoundError("Class membership does not exist.", 504);
    }

    public static function userDoesNotExist()
    {
        return self::notFoundError("User does not exist.", 205);
    }
}
