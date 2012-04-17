<?php

$functions = array(

    'secretaria_get_user' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'get_user',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Get user',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'read',
    ),

    'secretaria_create_user' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'create_user',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Create user',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'write',
    ),

    'secretaria_update_user' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'update_user',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Update user',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'write',
    ),

    'secretaria_delete_user' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'delete_user',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Delete user',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'write',
    ),

    'secretaria_get_course_enrolments' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'get_course_enrolments',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Get course enrolments',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'read',
    ),

    'secretaria_get_user_enrolments' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'get_user_enrolments',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Get user enrolments',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'read',
    ),

    'secretaria_enrol_users' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'enrol_users',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Enrol users',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'write',
    ),

    'secretaria_unenrol_users' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'unenrol_users',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Unenrol users',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'write',
    ),

    'secretaria_get_groups' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'get_groups',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Get groups',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'read',
    ),

    'secretaria_create_group' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'create_group',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Create group',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'write',
    ),

    'secretaria_delete_group' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'delete_group',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Delete group',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'write',
    ),

    'secretaria_get_group_members' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'get_group_members',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Get group members',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'read',
    ),

    'secretaria_add_group_members' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'add_group_members',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Add group members',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'write',
    ),

    'secretaria_remove_group_members' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'remove_group_members',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Remove group members',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'write',
    ),

    'secretaria_get_course_grades' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'get_course_grades',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Get course grades',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'read',
    ),

    'secretaria_get_user_grades' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'get_user_grades',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Get user grades',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'read',
    ),

    'secretaria_has_course' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'has_course',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Has course',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'read',
    ),

    'secretaria_get_courses' => array(
        'classname'   => 'moodle_local_secretaria_external',
        'methodname'  => 'get_courses',
        'classpath'   => 'local/secretaria/externallib.php',
        'description' => 'Get courses',
        'capabilities'=> 'local/secretaria:manage',
        'type'        => 'read',
    ),
);
