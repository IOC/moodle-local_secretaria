<?php

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

class moodle_local_secretaria_external extends external_api {

    static $plugin;

    private static function execute($name, $params) {
        global $CFG;

        require_once($CFG->dirroot . '/local/secretaria/locallib.php');
        require_capability('local/secretaria:manage', get_context_instance(CONTEXT_SYSTEM));

        $moodle = new local_secretaria_moodle_22();
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

    /* Users */

    public static function get_user($username) {
        return self::execute('get_user', array('username' => $username));
    }

    public static function get_user_parameters() {
        return new external_function_parameters(
            array('username' => self::value_required(PARAM_USERNAME, 'Username'))
        );
    }

    public static function get_user_returns() {
        return new external_single_structure(array(
            'username' => self::value_required(PARAM_USERNAME, 'Username'),
            'firstname' => self::value_required(PARAM_NOTAGS, 'First name'),
            'lastname' => self::value_required(PARAM_NOTAGS, 'Last name'),
            'email' => self::value_required(PARAM_EMAIL, 'Email address'),
            'picture' => self::value_null(PARAM_LOCALURL, 'Picture URL'),
        ));
    }

    public static function create_user($properties) {
        return self::execute('create_user', array('properties' => $properties));
    }

    public static function create_user_parameters() {
        return new external_function_parameters(array(
            'properties' => new external_single_structure(array(
                'username' => self::value_required(PARAM_USERNAME, 'Username'),
                'password' => self::value_required(PARAM_RAW, 'Plain text password'),
                'firstname' => self::value_required(PARAM_NOTAGS, 'First name'),
                'lastname' => self::value_required(PARAM_NOTAGS, 'Last name'),
                'email' => self::value_required(PARAM_EMAIL, 'Email address'),
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
            'username' => self::value_required(PARAM_USERNAME, 'Username'),
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
            'username' => self::value_required(PARAM_USERNAME, 'Username'),
        ));
    }

    public static function delete_user_returns() {
        return null;
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
        return new external_multiple_structure(
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
            'user' => self::value_required(PARAM_USERNAME, 'Username'),
        ));
    }

    public static function get_user_enrolments_returns() {
        return new external_multiple_structure(
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
            'enrolments' => new external_multiple_structure(
                new external_single_structure(array(
                    'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
                    'user' => self::value_required(PARAM_USERNAME, 'Username'),
                    'role' => self::value_required(PARAM_ALPHANUMEXT, 'Role shortname'),
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
            'enrolments' => new external_multiple_structure(
                new external_single_structure(array(
                    'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
                    'user' => self::value_required(PARAM_USERNAME, 'Username'),
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
        return new external_multiple_structure(
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
        return new external_multiple_structure(
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
            'users' => new external_multiple_structure(
                self::value_required(PARAM_USERNAME, 'Username')
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
            'users' => new external_multiple_structure(
                self::value_required(PARAM_USERNAME, 'Username')
            ),
        ));
    }

    public static function remove_group_members_returns() {
        return null;
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
            'users' => new external_multiple_structure(
                self::value_required(PARAM_USERNAME, 'Username')
            ),
        ));
    }

    public static function get_course_grades_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'type' => self::value_required(PARAM_ALPHA, 'Item type'),
                'module' => self::value_null(PARAM_RAW, 'Item module'),
                'idnumber' => self::value_null(PARAM_RAW, 'Item idnumber'),
                'name' => self::value_null(PARAM_RAW, 'Item name'),
                'grades' => new external_multiple_structure(
                    new external_single_structure(array(
                        'user' => self::value_required(PARAM_USERNAME, 'Username'),
                        'grade' => self::value_required(PARAM_RAW, 'Grade'),
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
            'user' => self::value_required(PARAM_USERNAME, 'Username'),
            'courses' => new external_multiple_structure(
                self::value_required(PARAM_TEXT, 'Course shortname')
            ),
        ));
    }

    public static function get_user_grades_returns() {
        return new external_multiple_structure(
            new external_single_structure(array(
                'course' => self::value_required(PARAM_TEXT, 'Course shortname'),
                'grade' => self::value_required(PARAM_RAW, 'Grade'),
            ))
        );
    }

    /* Misc */

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

    public static function get_courses() {
        return self::execute('get_courses', array());
    }

    public static function get_courses_parameters() {
        return new external_function_parameters(array());
    }

    public static function get_courses_returns() {
        return new external_multiple_structure(
            self::value_required(PARAM_TEXT, 'Course shortname')
        );
    }
}
