<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

Route::group(['middleware' => 'is_authorized'], function() use ($router) {
    $router->get('/subjects', 'SubjectController@getSubjects');
    $router->post('/subjects', 'SubjectController@createSubject');
    $router->patch('/subjects/{subject_id}', 'SubjectController@updateSubject');
    $router->delete('/subjects/{subject_id}', 'SubjectController@deleteSubject');

    $router->post('/learner-groups', 'SubjectGroupController@createGroup');
    $router->patch('/learner-groups/{group_id}', 'SubjectGroupController@updateGroup');
    $router->delete('/learner-groups/{group_id}', 'SubjectGroupController@deleteGroup');

    $router->patch('/group-access-codes/{group_id}', 'AccessCodeController@generateCode');

    $router->get('/access-memberships/{subject_id}', 'AccessCodeController@getMemberships');
    $router->delete('/access-memberships/{access_membership_id}', 'AccessCodeController@deleteMembership');

    $router->get('/subject-authors/{subject_id}', 'AuthorMembershipController@getAuthorMemberships');
    $router->post('/subject-authors', 'AuthorMembershipController@createAuthorMembership');
    $router->delete('/subject-authors/{author_membership_id}', 'AuthorMembershipController@deleteAuthorMembership');

    $router->post('/subject-author-decision/{author_membership_id}', 'AuthorMembershipController@authorMembershipDecision');

    $router->post('/topics', 'TopicController@createTopic');
    $router->patch('/topics/{topic_id}', 'TopicController@updateTopic');
    $router->delete('/topics/{topic_id}', 'TopicController@deleteTopic');

    $router->post('/skills', 'SkillController@createSkill');
    $router->patch('/skills/{skill_id}', 'SkillController@updateSkill');
    $router->delete('/skills/{skill_id}', 'SkillController@deleteSkill');

    $router->post('/content-items', 'ContentController@createContentItem');
    $router->patch('/content-items/{content_id}', 'ContentController@updateContentItem');
    $router->delete('/content-items/{content_id}', 'ContentController@deleteContentItem');

    $router->get('/subject-structures/{subject_id}', 'SubjectStructureController@getSubjectStructure');
    $router->patch('/subject-structures/{subject_id}', 'SubjectStructureController@updateSubjectStructure');

    $router->get('/multichoice-questions/{content_id}', 'MultichoiceQuestionController@getMultichoiceQuestions');
    $router->post('/multichoice-questions', 'MultichoiceQuestionController@createMultichoiceQuestion');
    $router->patch('/multichoice-questions/{question_id}', 'MultichoiceQuestionController@updateMultichoiceQuestion');
    $router->delete('/multichoice-questions/{question_id}', 'MultichoiceQuestionController@deleteMultichoiceQuestion');

    $router->get('/matching-questions/{content_id}', 'MatchingQuestionController@getMatchingQuestions');
    $router->post('/matching-questions', 'MatchingQuestionController@createMatchingQuestion');
    $router->patch('/matching-questions/{question_id}', 'MatchingQuestionController@updateMatchingQuestion');
    $router->delete('/matching-questions/{question_id}', 'MatchingQuestionController@deleteMatchingQuestion');

    $router->get('/numerical-questions/{content_id}', 'NumericalQuestionController@getNumericalQuestions');
    $router->post('/numerical-questions', 'NumericalQuestionController@createNumericalQuestion');
    $router->patch('/numerical-questions/{question_id}', 'NumericalQuestionController@updateNumericalQuestion');
    $router->delete('/numerical-questions/{question_id}', 'NumericalQuestionController@deleteNumericalQuestion');

    $router->get('/study-notes/{content_id}', 'StudyNoteController@getStudyNotes');
    $router->post('/study-notes', 'StudyNoteController@createStudyNote');
    $router->patch('/study-notes/{question_id}', 'StudyNoteController@updateStudyNote');
    $router->delete('/study-notes/{question_id}', 'StudyNoteController@deleteStudyNote');

    $router->get('/in-progress-resources/{skill_id}', 'ResourcesController@checkStatus');
    $router->get('/learning-resources/{skill_id}', 'ResourcesController@learningResources');

    $router->post('/image-uploads', 'UploadsController@uploadFile');
    $router->post('/check-answers', 'ResourcesController@checkAnswer');

    $router->post('/submit-results', 'ResourcesController@submitSkillResults');

    $router->post('/access-memberships', 'AccessCodeController@createAccessCodeMembership');
    $router->post('/feedback', 'FeedbackController@submitFeedback');

    $router->post('/author-code-memberships', 'AccessCodeController@registerAuthorCode');

    $router->patch('/users/{user_id}', 'UserController@updateUser');

    $router->post('/progress-tracking', 'ProgressTrackingController@createNewProgressTracking');

    $router->get('/subject-skills/{subject_id}/user/{user_id}', 'SubjectSkillsController@getSubjectTopicsSkills');

    $router->post('/favourites', 'FavouriteController@createFavourite');
    $router->delete('/favourites/{favourite_id}', 'FavouriteController@deleteFavourite');
    $router->get('/favourites/user/{user_id}', 'FavouriteController@getFavourites');

    $router->get('/subject-progress/classes/{class_id}/subjects/{subject_id}', 'SubjectProgressController@getProgressData');

    $router->patch('/class-codes/classes/{class_id}', 'ClassController@generateNewClassCode');
    $router->delete('/classes/{class_id}', 'ClassController@deleteClass');
    $router->patch('/classes/{class_id}', 'ClassController@updateClass');
    $router->get('/classes/user/{user_id}', 'ClassController@getClasses');
    $router->post('/classes', 'ClassController@createNewClass');

    $router->post('/class-memberships', 'ClassMembershipController@createClassMembership');
    $router->delete('/class-memberships/{class_membership_id}', 'ClassMembershipController@deleteClassMembership');
    $router->get('/class-memberships/classes/{class_id}', 'ClassMembershipController@getClassMemberships');

    $router->get('/access-memberships/user/{user_id}', 'AccessCodeController@accessCodeMembershipsList');
});

$router->post('/users', 'UserController@createUser');