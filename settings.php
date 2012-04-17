<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    require_once($CFG->dirroot.'/mnet/lib.php');

    $settings = new admin_settingpage('settings', get_string('pluginname', 'local_secretaria'));

    $options = array($CFG->mnet_localhost_id => '');
    foreach (mnet_get_hosts() as $host) {
        if ($host->id != $CFG->mnet_all_hosts_id) {
            $options[$host->id] = $host->name;
        }
    }
    $settings->add(new admin_setting_configselect('local_secretaria/mnethostid',
                                                  get_string('remotehost', 'mnet'), '',
                                                  $CFG->mnet_localhost_id, $options));

    $ADMIN->add('localplugins', $settings);
}
