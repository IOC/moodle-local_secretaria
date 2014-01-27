<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_secretaria', get_string('pluginname', 'local_secretaria'));

    $authplugins = get_enabled_auth_plugins(true);
    $options = array_combine($authplugins, $authplugins);
    $settings->add(new admin_setting_configselect('local_secretaria/auth_plugin',
                                                  get_string('auth_plugin', 'local_secretaria'), '',
                                                  'manual', $options));

    $ADMIN->add('localplugins', $settings);
}
