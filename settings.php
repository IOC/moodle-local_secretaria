<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_secretaria', get_string('pluginname', 'local_secretaria'));

    $authplugins = get_enabled_auth_plugins(true);
    $options = array_combine($authplugins, $authplugins);
    $settings->add(new admin_setting_configselect('local_secretaria/auth_plugin',
                                                  get_string('auth_plugin', 'local_secretaria'), '',
                                                  'manual', $options));

    
    $settings->add(new admin_setting_configtext('local_secretaria/courses', 
    										get_string('courses', 'local_secretaria'), '', ''));
    $settings->add(new admin_setting_configtext('local_secretaria/secretarias', 
    										get_string('secretarias', 'local_secretaria'), '', ''));

    $settings->add(new admin_setting_configtext('local_secretaria/password', 
    										get_string('password', 'local_secretaria'), '', ''));

    $settings->add(new admin_setting_configtext('local_secretaria/method', 
    										get_string('method', 'local_secretaria'), '', ''));

    $ADMIN->add('localplugins', $settings);
}
