<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_secretaria', get_string('pluginname', 'local_secretaria'));

    $auth_plugins = get_enabled_auth_plugins(true);
    $options = array_combine($auth_plugins, $auth_plugins);
    $settings->add(new admin_setting_configselect('local_secretaria/auth_plugin',
                                                  get_string('auth_plugin', 'local_secretaria'), '',
                                                  'manual', $options));

    $ADMIN->add('localplugins', $settings);
}
