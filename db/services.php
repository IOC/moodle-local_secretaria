<?php

$functions = array(

    /* Users */

    'secretaria_get_user' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_user',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get user',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_get_user_lastaccess' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_user_lastaccess',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get user last access',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_create_user' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'create_user',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Create user',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    'secretaria_update_user' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'update_user',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Update user',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    'secretaria_delete_user' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'delete_user',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Delete user',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    'secretaria_get_users' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_users',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get users',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_reset_password' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'reset_password',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Reset user password',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    /* Courses */

    'secretaria_has_course' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'has_course',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Has course',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_get_course' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_course',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get course',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_update_course' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'update_course',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Update course',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_get_courses' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_courses',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get courses',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_get_course_url' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_course_url',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get course url',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    /* Enrolments */

    'secretaria_get_course_enrolments' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_course_enrolments',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get course enrolments',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_get_user_enrolments' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_user_enrolments',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get user enrolments',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_enrol_users' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'enrol_users',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Enrol users',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    'secretaria_unenrol_users' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'unenrol_users',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Unenrol users',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    /* Groups */

    'secretaria_get_groups' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_groups',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get groups',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_create_group' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'create_group',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Create group',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    'secretaria_delete_group' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'delete_group',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Delete group',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    'secretaria_get_group_members' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_group_members',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get group members',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_add_group_members' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'add_group_members',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Add group members',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    'secretaria_remove_group_members' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'remove_group_members',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Remove group members',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    'secretaria_get_user_groups' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_user_groups',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get user groups',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    /* Grades */

    'secretaria_get_course_grades' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_course_grades',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get course grades',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_get_user_grades' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_user_grades',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get user grades',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    /* Asignments */

    'secretaria_get_assignments' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_assignments',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get assignments',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_get_assignment_submissions' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_assignment_submissions',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get assignment submissions',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    /* Forums */

    'secretaria_get_forum_stats' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_forum_stats',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get forum stats',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_get_forum_user_stats' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_forum_user_stats',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get forum user stats',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    /* Surveys */

    'secretaria_get_surveys' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_surveys',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get surveys',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_get_surveys_data' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_surveys_data',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get surveys data',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    'secretaria_create_survey' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'create_survey',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Create survey',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    'secretaria_update_survey' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'update_survey',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Update survey',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    /* Workshops */

    'secretaria_get_workshops' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_workshops',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get workshops',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    /* Mail */

    'secretaria_send_mail' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'send_mail',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Send mail',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'write',
    ),

    'secretaria_get_mail_stats' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'get_mail_stats',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Get mail stats',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),

    /* Formula */

    'secretaria_calc_formula' => array(
        'classname'    => 'moodle_local_secretaria_external',
        'methodname'   => 'calc_formula',
        'classpath'    => 'local/secretaria/externallib.php',
        'description'  => 'Result from formula calculation',
        'capabilities' => 'local/secretaria:manage',
        'type'         => 'read',
    ),
);
