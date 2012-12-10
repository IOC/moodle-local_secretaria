<?php

require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grouplib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/local/secretaria/operations.php');

class local_secretaria_moodle_2x implements local_secretaria_moodle {

    private $transaction;

    function auth_plugin() {
        return get_config('local_secretaria', 'auth_plugin');
    }

    function check_password($password) {
        return $password and check_password_policy($password, $errormsg);
    }

    function commit_transaction() {
        if ($this->transaction) {
            $this->transaction->allow_commit();
            $this->transaction = null;
        } else {
            throw new local_secretaria_exception('Internal error');
        }
    }

    function create_survey($courseid, $section, $idnumber, $name, $summary,
                           $opendate, $closedate, $templateid) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/questionnaire/lib.php');
        require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        $context = context_course::instance($courseid);
        $module = $DB->get_record('modules', array('name' => 'questionnaire'), '*', MUST_EXIST);

        $qrecord = new stdClass;
        $qrecord->course = $course->id;
        $qrecord->name = $name;
        $qrecord->intro = $summary;
        $qrecord->introformat = FORMAT_HTML;
        $qrecord->qtype = QUESTIONNAIREONCE;
        $qrecord->respondenttype = 'anonymous';
        $qrecord->resp_view = 0;
        $qrecord->opendate = $opendate;
        $qrecord->closedate = $closedate;
        $qrecord->resume = 0;
        $qrecord->navigate = 1; // not used
        $qrecord->grade = 0;
        $qrecord->timemodified = time();

        // questionnaire_add_instance
        $cm = new stdClass;
        $qobject = new questionnaire(0, $qrecord, $course, $cm);
        $qobject->add_survey($templateid);
        $qobject->add_questions($templateid);
        $qrecord->sid = $qobject->survey_copy($course->id);
        $qrecord->id = $DB->insert_record('questionnaire', $qrecord);
        $DB->set_field('questionnaire_survey', 'realm', 'private', array('id' => $qrecord->sid));
        questionnaire_set_events($qrecord);

        // modedit.php
        $cm->course = $course->id;
        $cm->instance = $qrecord->id;
        $cm->section = $section;
        $cm->visible = 0;
        $cm->module = $module->id;
        $cm->groupmode = !empty($course->groupmodeforce) ? $course->groupmode : 0;
        $cm->groupingid = $course->defaultgroupingid;
        $cm->groupmembersonly = 0;
        $cm->idnumber = $idnumber;

        $cm->coursemodule = add_course_module($cm);
        $sectionid = add_mod_to_section($cm);
        $DB->set_field('course_modules', 'section', $sectionid, array('id' => $cm->coursemodule));
        set_coursemodule_visible($cm->coursemodule, $cm->visible);
        rebuild_course_cache($course->id);
    }

    function create_user($auth, $username, $password, $firstname, $lastname, $email) {
        global $CFG, $DB;

        $record = new stdClass;
        $record->auth = $auth;
        $record->mnethostid = $CFG->mnet_localhost_id;
        $record->username = $username;
        $record->password = $password ? hash_internal_user_password($password) : 'not cached';
        $record->firstname = $firstname;
        $record->lastname = $lastname;
        $record->email = $email;
        $record->confirmed = true;
        $record->lang = $CFG->lang;
        $record->timecreated = time();
        $record->timemodified = $record->timecreated;

        $id = $DB->insert_record('user', $record);

        get_context_instance(CONTEXT_USER, $id);
        events_trigger('user_created', $DB->get_record('user', array('id' => $id)));
    }

    function delete_user($record) {
        delete_user($record);
    }

    function delete_role_assignment($courseid, $userid, $roleid) {
        global $DB;

        $context = context_course::instance($courseid);

        role_unassign($roleid, $userid, $context->id);

        $conditions = array(
            'contextid' => $cotnext->id,
            'userid' => $userid,
        );

        if (!$DB->record_exists('role_assignments', $conditions)) {
            $conditions = array('enrol' => 'manual', 'courseid' => $courseid);
            $enrol = $DB->get_record('enrol', $conditions, '*', MUST_EXIST);
            $plugin = enrol_get_plugin('manual');
            $plugin->unenrol_user($enrol, $userid);
        }
    }

    function get_course_id($shortname) {
        global $DB;
        return $DB->get_field('course', 'id', array('shortname' => $shortname));
    }

    function get_courses() {
        global $DB;
        $select = 'id != :siteid';
        $params = array('siteid' => SITEID);
        $fields = 'id, shortname';
        return $DB->get_records_select('course', $select, $params, '', $fields);
   }

    function get_group_id($courseid, $name) {
        return groups_get_group_by_name($courseid, $name);
    }

    function get_group_members($groupid) {
        global $CFG, $DB;
        $sql = 'SElECT u.username'
            . ' FROM {groups_members} gm'
            . ' JOIN {user} u ON u.id = gm.userid'
            . ' WHERE gm.groupid = :groupid'
            . ' AND u.mnethostid = :mnethostid';
        return $DB->get_records_sql($sql, array(
            'groupid' => $groupid,
            'mnethostid' => $CFG->mnet_localhost_id,
        ));
    }

    function get_role_assignments_by_course($courseid) {
         global $CFG, $DB;

         $sql = 'SELECT ra.id, u.username AS user, r.shortname AS role'
             . ' FROM {context} ct, {enrol} e, {role} r, {role_assignments} ra,'
             . '      {user} u, {user_enrolments} ue'
             . ' WHERE ct.contextlevel = :contextlevel'
             . ' AND ct.instanceid = :courseid'
             . ' AND e.courseid = ct.instanceid'
             . ' AND e.enrol = :enrol'
             . ' AND ra.component = :component'
             . ' AND ra.contextid = ct.id'
             . ' AND ra.itemid = :itemid'
             . ' AND ra.roleid = r.id'
             . ' AND ra.userid = u.id'
             . ' AND ra.userid = ue.userid'
             . ' AND u.mnethostid = :mnethostid'
             . ' AND ue.enrolid = e.id'
             . ' AND ue.userid = u.id';

         return $DB->get_records_sql($sql, array(
             'component' => '',
             'contextlevel' => CONTEXT_COURSE,
             'courseid' => $courseid,
             'enrol' => 'manual',
             'itemid' => 0,
             'mnethostid' => $CFG->mnet_localhost_id,
         ));
     }

     function get_role_assignments_by_user($userid) {
         global $DB;

         $sql = 'SELECT ra.id, c.shortname AS course, r.shortname AS role'
             . ' FROM {context} ct, {course} c, {enrol} e, {role} r,'
             . '      {role_assignments} ra, {user_enrolments} ue'
             . ' WHERE ct.contextlevel = :contextlevel'
             . ' AND ct.instanceid = c.id'
             . ' AND e.courseid = c.id'
             . ' AND e.enrol = :enrol'
             . ' AND ra.component = :component'
             . ' AND ra.contextid = ct.id'
             . ' AND ra.itemid = :itemid'
             . ' AND ra.roleid = r.id'
             . ' AND ra.userid = :userid'
             . ' AND ue.enrolid = e.id'
             . ' AND ue.userid = ra.userid';

         return $DB->get_records_sql($sql, array(
             'component' => '',
             'contextlevel' => CONTEXT_COURSE,
             'enrol' => 'manual',
             'itemid' => 0,
             'userid' => $userid,
         ));
     }

     function get_role_id($role) {
         global $DB;
         return $DB->get_field('role', 'id', array('shortname' => $role));
     }

    function get_survey_id($courseid, $idnumber) {
        global $DB;

        $sql = 'SELECT q.sid'
            . ' FROM {modules} m'
            . ' JOIN {course_modules} cm ON cm.module = m.id'
            . ' JOIN {questionnaire} q ON q.id = cm.instance'
            . ' WHERE m.name = :module'
            . ' AND cm.course = :courseid'
            . ' AND cm.idnumber = :idnumber';

        return $DB->get_field_sql($sql, array(
            'module' => 'questionnaire',
            'courseid' => $courseid,
            'idnumber' => $idnumber,
        ));
    }

    function get_survey_templates($courseid) {
        global $DB;

        $sql = 'SELECT q.id, q.name, cm.idnumber'
            . ' FROM {modules} m'
            . ' JOIN {course_modules} cm ON cm.module = m.id'
            . ' JOIN {questionnaire} q ON q.id = cm.instance'
            . ' JOIN {questionnaire_survey} qs ON qs.id = q.sid'
            . ' WHERE m.name = :module'
            . ' AND cm.course = :courseid'
            . ' AND qs.realm = :realm'
            . ' AND qs.status != :status';

        return $DB->get_records_sql($sql, array(
            'courseid' => $courseid,
            'module' => 'questionnaire',
            'realm' => 'template',
            'status' => 4,
        ));
    }

     function get_user_id($username) {
         global $CFG, $DB;
         return $DB->get_field('user', 'id', array(
             'mnethostid' => $CFG->mnet_localhost_id,
             'username' => $username,
             'deleted' => 0,
         ));
     }

     function get_user_lastaccess($userid) {
         global $DB;

         $sql = 'SELECT l.id, c.shortname AS course, l.timeaccess AS time'
             . ' FROM {user_lastaccess} l'
             . ' JOIN {course} c ON c.id = l.courseid'
             . ' WHERE l.userid = :userid';

        return $DB->get_records_sql($sql, array('userid' => $userid));
     }

     function get_user_record($username) {
         global $CFG, $DB;
         return $DB->get_record('user', array(
             'mnethostid' => $CFG->mnet_localhost_id,
             'username' => $username,
             'deleted' => 0,
         ));
     }

     function grade_get_course_grade($userid, $courseid) {
         return grade_get_course_grade($userid, $courseid);
     }

     function grade_get_grades($courseid, $itemtype, $itemmodule, $iteminstance, $userids) {
        $grades = grade_get_grades($courseid, $itemtype, $itemmodule, $iteminstance, $userids);
        return current($grades->items)->grades;
     }

     function grade_item_fetch_all($courseid) {
        $items = grade_item::fetch_all(array('courseid' => $courseid));
        if ($items) {
            foreach ($items as $item) {
                if ($item->itemtype == 'course') {
                    $item->itemname = null;
                    $item->itemmodule = null;
                } elseif ($item->itemtype == 'category') {
                    $category = $item->load_parent_category();
                    $item->itemname = $category->get_name();
                    $item->itemmodule = null;
                }
            }
        }
        return $items;
     }

     function groups_add_member($groupid, $userid) {
         groups_add_member($groupid, $userid);
     }

     function groups_create_group($courseid, $name, $description) {
         $data = new stdClass;
         $data->courseid = $courseid;
         $data->name = $name;
         $data->description = $description;
         groups_create_group($data);
     }

     function groups_delete_group($groupid) {
         groups_delete_group($groupid);
     }

     function groups_get_all_groups($courseid, $userid=0) {
        return groups_get_all_groups($courseid, $userid);
     }

     function groups_remove_member($groupid, $userid) {
         groups_remove_member($groupid, $userid);
     }

     function insert_role_assignment($courseid, $userid, $roleid) {
         global $DB;

         $plugin = enrol_get_plugin('manual');
         $conditions = array('enrol' => 'manual', 'courseid' => $courseid);
         $enrol = $DB->get_record('enrol', $conditions, '*', MUST_EXIST);
         $plugin->enrol_user($enrol, $userid, $roleid);
     }

    function make_timestamp($year, $month, $day, $hour=0, $minute=0, $second=0) {
        return make_timestamp($year, $month, $day, $hour, $minute, $second);
    }

    function prevent_local_passwords($auth) {
        return get_auth_plugin($auth)->prevent_local_passwords();
    }

    function role_assignment_exists($courseid, $userid, $roleid) {
        global $DB;

        $sql = 'SELECT ra.id'
            . ' FROM {context} ct, {enrol} e, {role_assignments} ra, {user_enrolments} ue'
            . ' WHERE ct.contextlevel = :contextlevel'
            . ' AND ct.instanceid = :courseid'
            . ' AND e.courseid = ct.instanceid'
            . ' AND e.enrol = :enrol'
            . ' AND ra.component = :component'
            . ' AND ra.contextid = ct.id'
            . ' AND ra.itemid = :itemid'
            . ' AND ra.roleid = :roleid'
            . ' AND ra.userid = :userid'
            . ' AND ue.enrolid = e.id'
            . ' AND ue.userid = ra.userid';

        return $DB->record_exists_sql($sql, array(
            'component' => '',
            'contextlevel' => CONTEXT_COURSE,
            'courseid' => $courseid,
            'enrol' => 'manual',
            'itemid' => 0,
            'roleid' => $roleid,
            'userid' => $userid,
        ));
    }

    function rollback_transaction(Exception $e) {
        if ($this->transaction) {
            $this->transaction->rollback($e);
        }
    }

    function section_exists($courseid, $section) {
        global $DB;
        $conditions = array('course' => $courseid, 'section' => $section);
        return $DB->record_exists('course_sections', $conditions);
    }

    function send_mail($sender, $courseid, $subject, $content, $to, $cc, $bcc) {
        global $CFG;

        require_once($CFG->dirroot . '/local/mail/message.class.php');

        $message = local_mail_message::create($sender, $courseid);
        $message->save($subject, $content, FORMAT_HTML);

        foreach ($to as $userid) {
            $message->add_recipient('to', $userid);
        }
        foreach ($cc as $userid) {
            $message->add_recipient('cc', $userid);
        }
        foreach ($bcc as $userid) {
            $message->add_recipient('bcc', $userid);
        }

        $message->send();
    }

    function start_transaction() {
        global $DB;
        if ($this->transaction) {
            throw new local_secretaria_exception('Internal error');
        } else {
            $this->transaction = $DB->start_delegated_transaction();
        }
    }

    function update_record($table, $record) {
        global $DB;
        $DB->update_record($table, $record);
    }

    function update_password($userid, $password) {
        global $DB;
        $record = $DB->get_record('user', array('id' => $userid));
        update_internal_user_password($record, $password);
    }

    function user_picture_url($userid) {
        global $CFG;
        $context = context_user::instance($userid);
        return "{$CFG->httpswwwroot}/pluginfile.php/{$context->id}/user/icon/f1";
    }
}
