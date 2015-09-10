<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Secretaria plugin: settings
 *
 * @package    local
 * @subpackage secretaria
 * @copyright  Institut Obert de Catalunya (IOC) 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_secretaria', get_string('pluginname', 'local_secretaria'));

    $authplugins = get_enabled_auth_plugins(true);
    $options = array_combine($authplugins, $authplugins);
    $settings->add(new admin_setting_configselect('local_secretaria/auth_plugin',
                                                  get_string('auth_plugin', 'local_secretaria'), '',
                                                  'manual', $options));

    $settings->add(new admin_setting_heading('secretariaws', get_string('ws', 'local_secretaria'), ''));


    $settings->add(new admin_setting_configtext('local_secretaria/courses',
                                            get_string('courses', 'local_secretaria'), '', ''));
    $settings->add(new admin_setting_configtext('local_secretaria/registrars',
                                            get_string('registrars', 'local_secretaria'), '', ''));

    $settings->add(new admin_setting_configtext('local_secretaria/password',
                                            get_string('password', 'local_secretaria'), '', ''));

    $settings->add(new admin_setting_configtext('local_secretaria/method',
                                            get_string('method', 'local_secretaria'), '', ''));

    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('server', new admin_externalpage('local_secretaria/mailcheck',
                                            get_string('mailcheck', 'local_secretaria'),
                                            new moodle_url('/local/secretaria/mailcheck.php')));
}
