<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('settings', get_string('pluginname', 'local_secretaria'));

    $ADMIN->add('localplugins', $settings);
}
