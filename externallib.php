<?php

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

class moodle_local_secretaria_external extends external_api {

    public static $plugin;

    private static function execute($name, $params) {
        global $CFG;

        require_once($CFG->dirroot . '/local/secretaria/locallib.php');
        require_capability('local/secretaria:manage', context_system::instance());

        $moodle = new local_secretaria_moodle_2x();
        $operations = new local_secretaria_operations($moodle);
        if (!is_callable(array($operations, $name))) {
            throw new Exception('Unknown function');
        }
        $description = call_user_func(array(get_class(), "{$name}_parameters"));
        try {
            $params = self::validate_parameters($description, $params);
        } catch (invalid_parameter_exception $e) {
            throw new local_secretaria_exception('Invalid parameters');
        }
        try {
            return call_user_func_array(array($operations, $name), $params);
        } catch (local_secretaria_exception $e) {
            $moodle->rollback_transaction($e);
            throw $e;
        } catch (Exception $e) {
            $moodle->rollback_transaction($e);
            throw new local_secretaria_exception('Internal error');
        }
    }

    private static function value_required($type, $desc) {
        return new external_value($type, $desc, VALUE_REQUIRED, null, NULL_NOT_ALLOWED);
    }

    private static function value_null($type, $desc) {
        return new external_value($type, $desc, VALUE_REQUIRED, null, NULL_ALLOWED);
    }

    private static function value_optional($type, $desc) {
        return new external_value($type, $desc, VALUE_OPTIONAL, null, NULL_NOT_ALLOWED);
    }

    private static function multiple_structure(external_description $content, $desc='') {
        return new external_multiple_structure($content, $desc, VALUE_DEFAULT, array());
    }

    /* Users */

    public static function get_user($username) {
        return self::execute('get_user', array('username' => $username));
    }

    public static function get_user_parameters() {
        return new external_function_parameters(
            array('username' => self::value_required(PARAM_USERNAME_IOC, 'Username'))
        );
    }

    public static function get_user_returns() {
        return new external_single_structure(array(
            'username' => self::value_required(PARAM_USERNAME_IOC, 'Username'),
            'firstname' => self::value_required(PARAM_NOTAGS, 'First name'),
            'lastname' => self::value_required(PARAM_NOTAGS, 'Last name'),
            'email' => self::value_required(PARAM_EMAIL, 'Email address'),
            'picture' => self::value_null(PARAM_LOCALURL, 'Picture URL'),
            'lastaccess' => self::value_required(PARAM_INT, 'Last access'),
        ));
    }

    public static function get_user_lastaccess($users) {
        return self::execute('get_user_lastaccess', array('users' => $users));
    }

    public static function get_user_lastaccess_parameters() {
        return new external_function_parameters(array(
            'users' => self::multiple_structure(
                self::value_required(PARAM_USERNAME_IOC, 'Username')
            )
        ));
    }

    public static function get_user_lastaccess_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'user' => self::value_required(PARAM_USERNAME, 'Username'),
                'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
                'time' => self::value_required(PARAM_INT, 'Access time'),
            ))
        );
    }

    public static function create_user($properties) {
        return self::execute('create_user', array('properties' => $properties));
    }

    public static function create_user_parameters() {
        return new external_function_parameters(array(
            'properties' => new external_single_structure(array(
                'username' => self::value_required(PARAM_USERNAME, 'Username'),
                'password' => self::value_optional(PARAM_RAW, 'Plain text password'),
                'firstname' => self::value_required(PARAM_NOTAGS, 'First name'),
                'lastname' => self::value_required(PARAM_NOTAGS, 'Last name'),
                'email' => self::value_optional(PARAM_EMAIL, 'Email address'),
            )),
        ));
    }

    public static function create_user_returns() {
        return null;
    }

    public static function update_user($username, $properties) {
        return self::execute('update_user', array(
            'username' => $username,
            'properties' => $properties,
        ));
    }

    public static function update_user_parameters() {
        return new external_function_parameters(array(
            'username' => self::value_required(PARAM_USERNAME_IOC, 'Username'),
            'properties' => new external_single_structure(array(
                'username' => self::value_optional(PARAM_USERNAME, 'Username'),
                'password' => self::value_optional(PARAM_RAW, 'Plain text password'),
                'firstname' => self::value_optional(PARAM_NOTAGS, 'First name'),
                'lastname' => self::value_optional(PARAM_NOTAGS, 'Last name'),
                'email' => self::value_optional(PARAM_EMAIL, 'Email address'),
            )),
        ));
    }

    public static function update_user_returns() {
        return null;
    }

    public static function delete_user($username) {
        return self::execute('delete_user', array('username' => $username));
    }

    public static function delete_user_parameters() {
        return new external_function_parameters(array(
            'username' => self::value_required(PARAM_USERNAME_IOC, 'Username'),
        ));
    }

    public static function delete_user_returns() {
        return null;
    }

    public static function get_users($usernames) {
        return self::execute('get_users', array('usernames' => $usernames));
    }

    public static function get_users_parameters() {
        return new external_function_parameters(array(
            'usernames' => self::multiple_structure(
                self::value_required(PARAM_USERNAME_IOC, 'Username')
            ),
        ));
    }

    public static function get_users_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'username' => self::value_required(PARAM_USERNAME, 'Username'),
                'firstname' => self::value_required(PARAM_NOTAGS, 'First name'),
                'lastname' => self::value_required(PARAM_NOTAGS, 'Last name'),
                'email' => self::value_required(PARAM_EMAIL, 'Email address'),
                'picture' => self::value_null(PARAM_LOCALURL, 'Picture URL'),
                'lastaccess' => self::value_required(PARAM_INT, 'Last access'),
            ))
        );
    }

    public static function reset_password($username) {
        return self::execute('reset_password', array('username' => $username));
    }

    public static function reset_password_parameters() {
        return new external_function_parameters(
            array('username' => self::value_required(PARAM_USERNAME_IOC, 'Username'))
        );
    }

    public static function reset_password_returns() {
        return null;
    }

    /* Courses */

    public static function has_course($course) {
        return self::execute('has_course', array('course' => $course));
    }

    public static function has_course_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function has_course_returns() {
        return self::value_required(PARAM_BOOL, 'Has course');
    }

    public static function get_course($course) {
        return self::execute('get_course', array('course' => $course));
    }

    public static function get_course_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function get_course_returns() {
        return new external_single_structure(array(
            'shortname' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'fullname' => self::value_required(PARAM_TEXT, 'Course fullname'),
            'visible' => self::value_required(PARAM_BOOL, 'Course visible?'),
            'startdate' => new external_single_structure(array(
               'year' => self::value_required(PARAM_INT, 'Start year'),
               'month' => self::value_required(PARAM_INT, 'Start month'),
               'day' => self::value_required(PARAM_INT, 'Start day'),
            )),
        ));
    }

    public static function update_course($course, $properties) {
        return self::execute('update_course', array(
            'course' => $course,
            'properties' => $properties,
        ));
    }

    public static function update_course_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'properties' => new external_single_structure(array(
                'shortname' => self::value_optional(PARAM_TEXT, 'Course shortname'),
                'fullname' => self::value_optional(PARAM_TEXT, 'Course fullname'),
                'visible' => self::value_optional(PARAM_BOOL, 'Course visible?'),
                'startdate' => new external_single_structure(array(
                    'year' => self::value_required(PARAM_INT, 'Start year'),
                    'month' => self::value_required(PARAM_INT, 'Start month'),
                    'day' => self::value_required(PARAM_INT, 'Start day'),
                ), '', VALUE_OPTIONAL),
            )),
        ));
    }

    public static function update_course_returns() {
        return null;
    }

    public static function get_courses() {
        return self::execute('get_courses', array());
    }

    public static function get_courses_parameters() {
        return new external_function_parameters(array());
    }

    public static function get_courses_returns() {
        return self::multiple_structure(
            self::value_required(PARAM_TEXT, 'Course shortname')
        );
    }

    public static function get_course_url($course) {
        return self::execute('get_course_url', array('course' => $course));
    }

    public static function get_course_url_parameters() {
        return new external_function_parameters(array(
             'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function get_course_url_returns() {
        return self::value_required(PARAM_URL, 'Course url');
    }

    /* Enrolments */

    public static function get_course_enrolments($course) {
        return self::execute('get_course_enrolments', array('course' => $course));
    }

    public static function get_course_enrolments_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function get_course_enrolments_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'user' => self::value_required(PARAM_USERNAME, 'Username'),
                'role' => self::value_required(PARAM_ALPHANUMEXT, 'Role shortname'),
            ))
        );
    }

    public static function get_user_enrolments($user) {
        return self::execute('get_user_enrolments', array('user' => $user));
    }

    public static function get_user_enrolments_parameters() {
        return new external_function_parameters(array(
            'user' => self::value_required(PARAM_USERNAME_IOC, 'Username'),
        ));
    }

    public static function get_user_enrolments_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
                'role' => self::value_required(PARAM_ALPHANUMEXT, 'Role shortname'),
            ))
        );
    }

    public static function enrol_users($enrolments) {
        return self::execute('enrol_users', array('enrolments' => $enrolments));
    }

    public static function enrol_users_parameters() {
        return new external_function_parameters(array(
            'enrolments' => self::multiple_structure(
                new external_single_structure(array(
                    'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
                    'user' => self::value_required(PARAM_USERNAME_IOC, 'Username'),
                    'role' => self::value_required(PARAM_ALPHANUMEXT, 'Role shortname'),
                    'recovergrades' => self::value_optional(PARAM_BOOL, 'Recover grades'),
                ))
            ),
        ));
    }

    public static function enrol_users_returns() {
        return null;
    }

    public static function unenrol_users($enrolments) {
        return self::execute('unenrol_users', array('enrolments' => $enrolments));
    }

    public static function unenrol_users_parameters() {
        return new external_function_parameters(array(
            'enrolments' => self::multiple_structure(
                new external_single_structure(array(
                    'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
                    'user' => self::value_required(PARAM_USERNAME_IOC, 'Username'),
                    'role' => self::value_required(PARAM_ALPHANUMEXT, 'Role shortname'),
                ))
            ),
        ));
    }

    public static function unenrol_users_returns() {
        return null;
    }

    /* Groups */

    public static function get_groups($course) {
        return self::execute('get_groups', array('course' => $course));
    }

    public static function get_groups_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function get_groups_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'name' => self::value_required(PARAM_TEXT, 'Group name'),
                'description' => self::value_null(PARAM_RAW, 'Group description'),
            ))
        );
    }

    public static function create_group($course, $name, $description) {
        return self::execute('create_group', array(
            'course' => $course,
            'name' => $name,
            'description' => $description,
        ));
    }

    public static function create_group_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'name' => self::value_required(PARAM_TEXT, 'Group name'),
            'description' => self::value_null(PARAM_RAW, 'Group description'),
        ));
    }

    public static function create_group_returns() {
        return null;
    }

    public static function delete_group($course, $name) {
        return self::execute('delete_group', array(
            'course' => $course,
            'name' => $name,
        ));
    }

    public static function delete_group_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'name' => self::value_required(PARAM_TEXT, 'Group name'),
        ));
    }

    public static function delete_group_returns() {
        return null;
    }

    public static function get_group_members($course, $name) {
        return self::execute('get_group_members', array(
            'course' => $course,
            'name' => $name,
        ));
    }

    public static function get_group_members_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'name' => self::value_required(PARAM_TEXT, 'Group name'),
        ));
    }

    public static function get_group_members_returns() {
        return self::multiple_structure(
            self::value_required(PARAM_USERNAME, 'Username')
        );
    }

    public static function add_group_members($course, $name, $users) {
        return self::execute('add_group_members', array(
            'course' => $course,
            'name' => $name,
            'users' => $users,
        ));
    }

    public static function add_group_members_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'name' => self::value_required(PARAM_TEXT, 'Group name'),
            'users' => self::multiple_structure(
                self::value_required(PARAM_USERNAME_IOC, 'Username')
            ),
        ));
    }

    public static function add_group_members_returns() {
        return null;
    }

    public static function remove_group_members($course, $name, $users) {
        return self::execute('remove_group_members', array(
            'course' => $course,
            'name' => $name,
            'users' => $users,
        ));
    }

    public static function remove_group_members_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'name' => self::value_required(PARAM_TEXT, 'Group name'),
            'users' => self::multiple_structure(
                self::value_required(PARAM_USERNAME_IOC, 'Username')
            ),
        ));
    }

    public static function remove_group_members_returns() {
        return null;
    }

    public static function get_user_groups($user, $course) {
        return self::execute('get_user_groups', array('user' => $user, 'course' => $course));
    }

    public static function get_user_groups_parameters() {
        return new external_function_parameters(array(
            'user' => self::value_required(PARAM_USERNAME_IOC, 'Username'),
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function get_user_groups_returns() {
        return self::multiple_structure(self::value_required(PARAM_TEXT, 'Group name'));
    }

    /* Grades */

    public static function get_course_grades($course, $users) {
        return self::execute('get_course_grades', array(
            'course' => $course,
            'users' => $users,
        ));
    }

    public static function get_course_grades_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'users' => self::multiple_structure(
                self::value_required(PARAM_USERNAME_IOC, 'Username')
            ),
        ));
    }

    public static function get_course_grades_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'type' => self::value_required(PARAM_ALPHA, 'Item type'),
                'module' => self::value_null(PARAM_RAW, 'Item module'),
                'idnumber' => self::value_null(PARAM_RAW, 'Item idnumber'),
                'name' => self::value_null(PARAM_RAW, 'Item name'),
                'grademin' => self::value_required(PARAM_RAW, 'Minimum grade'),
                'grademax' => self::value_required(PARAM_RAW, 'Maximum grade'),
                'gradepass' => self::value_required(PARAM_RAW, 'Grade to pass'),
                'hidden' => self::value_required(PARAM_INT, 'Item visibility'),
                'grades' => self::multiple_structure(
                    new external_single_structure(array(
                        'user' => self::value_required(PARAM_USERNAME, 'Username'),
                        'grade' => self::value_required(PARAM_RAW, 'Grade'),
                        'grader' => self::value_required(PARAM_USERNAME, 'Grader'),
                    ))
                ),
            ))
        );
    }

    public static function get_user_grades($user, $courses) {
        return self::execute('get_user_grades', array(
            'user' => $user,
            'courses' => $courses,
        ));
    }

    public static function get_user_grades_parameters() {
        return new external_function_parameters(array(
            'user' => self::value_required(PARAM_USERNAME_IOC, 'Username'),
            'courses' => self::multiple_structure(
                self::value_required(PARAM_TEXT, 'Course shortname')
            ),
        ));
    }

    public static function get_user_grades_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
                'grade' => self::value_required(PARAM_RAW, 'Grade'),
            ))
        );
    }

    /* Assignments */

    public static function get_assignments($course) {
        return self::execute('get_assignments', array('course' => $course));
    }

    public static function get_assignments_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function get_assignments_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'idnumber' => self::value_required(PARAM_RAW, 'Assignment idnumber'),
                'name' => self::value_required(PARAM_TEXT, 'Assignment name'),
                'opentime' => self::value_null(PARAM_INT, 'Open time'),
                'closetime' => self::value_null(PARAM_INT, 'Close time'),
            ))
        );
    }

    public static function get_assignment_submissions($course, $idnumber) {
        return self::execute('get_assignment_submissions', array(
            'course' => $course,
            'idnumber' => $idnumber,
        ));
    }

    public static function get_assignment_submissions_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'idnumber' => self::value_required(PARAM_TEXT, 'Assignment idnumber'),
        ));
    }

    public static function get_assignment_submissions_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'user' => self::value_required(PARAM_USERNAME, 'Submitter username'),
                'grader' => self::value_null(PARAM_USERNAME, 'Grader username'),
                'timesubmitted' => self::value_required(PARAM_INT, 'Time submitted'),
                'timegraded' => self::value_null(PARAM_INT, 'Time graded'),
                'numfiles' => self::value_required(PARAM_INT, 'Number of files'),
                'attempt' => self::value_required(PARAM_INT, 'Number of attempt'),
            ))
        );
    }

    /* Forums */

    public static function get_forum_stats($course) {
        return self::execute('get_forum_stats', array('course' => $course));
    }

    public static function get_forum_stats_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function get_forum_stats_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'idnumber' => self::value_required(PARAM_RAW, 'Forum idnumber'),
                'name' => self::value_required(PARAM_TEXT, 'Forum name'),
                'type' => self::value_required(PARAM_RAW, 'Forum type'),
                'stats' => self::multiple_structure(
                    new external_single_structure(array(
                        'group' => self::value_required(PARAM_TEXT, 'Group name'),
                        'discussions' => self::value_required(PARAM_INT, 'Number of discussions'),
                        'posts' => self::value_required(PARAM_INT, 'Number of posts'),
                    ))
                ),
            ))
        );
    }

    public static function get_forum_user_stats($course, $users) {
        return self::execute('get_forum_user_stats', array('course' => $course, 'users' => $users));
    }

    public static function get_forum_user_stats_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'users' => self::multiple_structure(
                self::value_required(PARAM_USERNAME_IOC, 'Username')
        )));
    }

    public static function get_forum_user_stats_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'idnumber' => self::value_required(PARAM_RAW, 'Forum idnumber'),
                'name' => self::value_required(PARAM_TEXT, 'Forum name'),
                'type' => self::value_required(PARAM_RAW, 'Forum type'),
                'stats' => self::multiple_structure(
                    new external_single_structure(array(
                        'username' => self::value_required(PARAM_USERNAME, 'Username'),
                        'group' => self::value_required(PARAM_TEXT, 'Group name'),
                        'discussions' => self::value_required(PARAM_INT, 'Number of discussions'),
                        'posts' => self::value_required(PARAM_INT, 'Number of posts'),
                    ))
                ),
            ))
        );
    }

    /* Surveys */

    public static function get_surveys($course) {
        return self::execute('get_surveys', array('course' => $course));
    }

    public static function get_surveys_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function get_surveys_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'name' => self::value_required(PARAM_TEXT, 'Survey name'),
                'idnumber' => self::value_required(PARAM_RAW, 'Survey idnumber'),
                'type' => self::value_required(PARAM_RAW, 'Survey type'),
            ))
        );
    }

    public static function get_surveys_data($course) {
        return self::execute('get_surveys_data', array('course' => $course));
    }

    public static function get_surveys_data_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function get_surveys_data_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'idnumber' => self::value_required(PARAM_RAW, 'Survey idnumber'),
                'name' => self::value_required(PARAM_TEXT, 'Survey name'),
                'type' => self::value_required(PARAM_RAW, 'Survey type'),
                'questions' => self::multiple_structure(
                    new external_single_structure(array(
                        'name' => self::value_null(PARAM_TEXT, 'Question name'),
                        'content' => self::value_required(PARAM_RAW, 'Question content'),
                        'position' => self::value_required(PARAM_INT, 'Question position'),
                        'type' => self::value_required(PARAM_TEXT, 'Question type'),
                        'has_choices' => self::value_required(PARAM_TEXT, 'Has defined choices'),
                        'choices' => self::multiple_structure(
                                self::value_optional(PARAM_RAW, 'Choices')
                        ),
                        'responses' => self::multiple_structure(
                            new external_single_structure(array(
                                'username' => self::value_required(PARAM_TEXT, 'Username'),
                                'content' => self::multiple_structure(
                                        self::value_required(PARAM_RAW, 'Response content')
                                ),
                                'rank' => self::multiple_structure(
                                        self::value_optional(PARAM_INT, 'Rank content')
                                ),
                            ))
                        ),
                    ))
                ),
            ))
        );
    }

    public static function create_survey($properties) {
        return self::execute('create_survey', array('properties' => $properties));
    }

    public static function create_survey_parameters() {
        return new external_function_parameters(array(
            'properties' => new external_single_structure(array(
                'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
                'section' => self::value_required(PARAM_INT, 'Course section'),
                'idnumber' => self::value_required(PARAM_RAW, 'Survey idnumber'),
                'name' => self::value_required(PARAM_TEXT, 'Survey name'),
                'summary' => self::value_required(PARAM_RAW, 'Survey summary'),
                'opendate' => new external_single_structure(array(
                   'year' => self::value_required(PARAM_INT, 'Open year'),
                   'month' => self::value_required(PARAM_INT, 'Open month'),
                   'day' => self::value_required(PARAM_INT, 'Open day'),
                 ), '', VALUE_OPTIONAL),
                'closedate' => new external_single_structure(array(
                   'year' => self::value_required(PARAM_INT, 'Close year'),
                   'month' => self::value_required(PARAM_INT, 'Close month'),
                   'day' => self::value_required(PARAM_INT, 'Close day'),
                 ), '', VALUE_OPTIONAL),
                'template' => new external_single_structure(array(
                   'course' => self::value_required(PARAM_TEXT, 'Template course shortname'),
                   'idnumber' => self::value_required(PARAM_RAW, 'Template survey idnumber'),
                )),
            )),
        ));
    }

    public static function create_survey_returns() {
        return null;
    }

    public static function update_survey($course, $idnumber, $properties) {
        return self::execute('update_survey', array(
            'course' => $course,
            'idnumber' => $idnumber,
            'properties' => $properties,
        ));
    }

    public static function update_survey_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
            'idnumber' => self::value_required(PARAM_RAW, 'Survey idnumber'),
            'properties' => new external_single_structure(array(
                'idnumber' => self::value_optional(PARAM_RAW, 'Survey idnumber'),
                'name' => self::value_optional(PARAM_TEXT, 'Survey name'),
            )),
        ));
    }

    public static function update_survey_returns() {
        return null;
    }

    /* Workshop */

    public static function get_workshops($course) {
        return self::execute('get_workshops', array('course' => $course));
    }

    public static function get_workshops_parameters() {
        return new external_function_parameters(array(
            'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
        ));
    }

    public static function get_workshops_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'idnumber' => self::value_required(PARAM_RAW, 'Workshop idnumber'),
                'name' => self::value_required(PARAM_TEXT, 'Workshop name'),
                'opentime' => self::value_null(PARAM_INT, 'Open time'),
                'closetime' => self::value_null(PARAM_INT, 'Close time'),
            ))
        );
    }

    /* Mail */

    public static function send_mail($message) {
        return self::execute('send_mail', array('message' => $message));
    }

    public static function send_mail_parameters() {
        return new external_function_parameters(array(
            'message' => new external_single_structure(array(
               'sender' => self::value_required(PARAM_USERNAME_IOC, 'Sender username'),
               'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
               'subject' => self::value_required(PARAM_TEXT, 'Message subject'),
               'content' => self::value_required(PARAM_RAW, 'Message content'),
               'to' => self::multiple_structure(
                   self::value_required(PARAM_USERNAME_IOC, 'Username'), 'To users'
               ),
               'cc' => self::multiple_structure(
                   self::value_required(PARAM_USERNAME_IOC, 'Username'), 'Cc users'
               ),
               'bcc' => self::multiple_structure(
                   self::value_required(PARAM_USERNAME_IOC, 'Username'), 'Bcc users'
               ),
            )),
        ));
    }

    public static function send_mail_returns() {
        return null;
    }

    public static function get_mail_stats($user, $starttime, $endtime) {
        return self::execute('get_mail_stats', array(
            'user' => $user,
            'starttime' => $starttime,
            'endtime' => $endtime,
        ));
    }

    public static function get_mail_stats_parameters() {
        return new external_function_parameters(array(
           'user' => self::value_required(PARAM_USERNAME, 'User'),
           'starttime' => self::value_required(PARAM_INT, 'Start timestamp'),
           'endtime' => self::value_required(PARAM_INT, 'End timestamp'),
        ));
    }

    public static function get_mail_stats_returns() {
        return self::multiple_structure(
            new external_single_structure(array(
                'course' => self::value_required(PARAM_RAW, 'Course shortname'),
                'received' => self::value_required(PARAM_INT, 'Number of messages received'),
                'sent' => self::value_required(PARAM_INT, 'Number of messages sent'),
            ))
        );
    }

    /* Formula */

    public static function calc_formula($formula, $variables, $values) {
        return self::execute('calc_formula', array(
            'formula' => $formula,
            'variables' => $variables,
            'values' => $values,
            ));
    }

    public static function calc_formula_parameters() {
        return new external_function_parameters(array(
            'formula' => self::value_required(PARAM_TEXT, 'Text formula'),
            'variables' => self::multiple_structure(
                self::value_optional(PARAM_TEXT, 'Names of variables')),
            'values' => self::multiple_structure(
                self::value_optional(PARAM_RAW, 'Values of variables')),
        ));
    }

    public static function calc_formula_returns() {
        return self::value_required(PARAM_RAW, 'Calculation formula result');
    }
}
