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
        require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');
        require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

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
        $sectionid = course_add_cm_to_section($cm->course, $cm->coursemodule, $cm->section);
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
        $record->maildisplay = 0;
        $record->autosubscribe = 0;
        $record->trackforums = 1;
        $record->timecreated = time();
        $record->timemodified = $record->timecreated;

        $id = $DB->insert_record('user', $record);

        get_context_instance(CONTEXT_USER, $id);
        events_trigger('user_created', $DB->get_record('user', array('id' => $id)));
    }

    function delete_user($userid) {
        global $DB;
        $record = $DB->get_record('user', array('id' => $userid), 'id, username');
        delete_user($record);
    }

    function delete_role_assignment($courseid, $userid, $roleid) {
        global $DB;

        $context = context_course::instance($courseid);

        role_unassign($roleid, $userid, $context->id);

        $conditions = array(
            'contextid' => $context->id,
            'userid' => $userid,
        );

        if (!$DB->record_exists('role_assignments', $conditions)) {
            $conditions = array('enrol' => 'manual', 'courseid' => $courseid);
            $enrol = $DB->get_record('enrol', $conditions, '*', MUST_EXIST);
            $plugin = enrol_get_plugin('manual');
            $plugin->unenrol_user($enrol, $userid);
        }
    }

    function get_assignment_id($courseid, $idnumber) {
        global $DB;
        $sql = 'SELECT a.id'
            . ' FROM {course_modules} cm '
            . ' JOIN {modules} m ON m.id = cm.module'
            . ' JOIN {assign} a ON a.id = cm.instance'
            . ' WHERE cm.course = ? AND cm.idnumber = ?'
            . ' AND m.name = ? AND a.course = ?';
        return $DB->get_field_sql($sql, array($courseid, $idnumber, 'assign', $courseid));
    }

    function get_assignment_submissions($assignmentid) {
        global $DB;

        $modid = $DB->get_field('modules', 'id', array('name' => 'assign'));
        $conditions = array('module' => $modid, 'instance' => $assignmentid);
        $cmid = $DB->get_field('course_modules', 'id', $conditions);
        $context = context_module::instance($cmid);

        $sql = 'SELECT s.id, us.username AS user, ug.username AS grader,'
            . ' s.timemodified AS timesubmitted, g.timemodified AS timegraded,'
            . ' COUNT(f.id) AS numfiles'
            . ' FROM {assign_submission} s'
            . ' JOIN {user} us ON us.id = s.userid'
            . ' LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid'
            . ' LEFT JOIN {user} ug ON ug.id = g.grader'
            . ' LEFT JOIN {files} f ON f.userid = s.userid'
            . ' AND f.contextid = :contextid AND f.filename != :filename'
            . ' WHERE s.assignment = :assignmentid'
            . ' GROUP BY user, grader, timesubmitted, timegraded';

        return $DB->get_records_sql($sql, array(
            'contextid' => $context->id,
            'filename' => '.',
            'assignmentid' => $assignmentid,
        ));
    }

    function get_assignments($courseid) {
        global $DB;
        $sql = 'SELECT a.id, cm.idnumber, a.name,'
            . ' a.allowsubmissionsfromdate AS opentime, a.duedate AS closetime'
            . ' FROM {course_modules} cm '
            . ' JOIN {modules} m ON m.id = cm.module'
            . ' JOIN {assign} a ON a.id = cm.instance'
            . ' WHERE cm.course = ? AND m.name = ? AND a.course = ?';
        return $DB->get_records_sql($sql, array($courseid, 'assign', $courseid));
    }

    function get_course($shortname) {
        global $DB;
        return $DB->get_record('course', array('shortname' => $shortname),
                               'id, shortname, fullname, visible, startdate');
    }

    function get_course_grade($userid, $courseid) {
        grade_regrade_final_grades($courseid);
        $grade_item = grade_item::fetch_course_item($courseid);
        $grade_grade = grade_grade::fetch(array('userid' => $userid, 'itemid' => $grade_item->id));
        $value = grade_format_gradevalue($grade_grade->finalgrade, $grade_item);
        return $grade_item->needsupdate ? get_string('error') : $value;
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

    function get_forum_stats($forumid) {
        global $DB;
        $sql = 'SELECT d.groupid, g.name AS groupname, COUNT(p.id) AS posts,'
            . ' COUNT(DISTINCT d.id) AS discussions'
            . ' FROM {forum_discussions} d'
            . ' JOIN {forum_posts} p ON p.discussion = d.id'
            . ' LEFT JOIN {groups} g ON g.id = d.groupid'
            . ' WHERE d.forum = :forumid'
            . ' GROUP BY d.groupid, g.name';
        return $DB->get_records_sql($sql, array('forumid' => $forumid));
    }

    function get_forum_user_stats($forumid, $users) {
        global $DB;

        $sqlin = '';
        $usernameparams = array();

        if (!empty($users)) {
            list($sqlusernames, $usernameparams) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED, 'username');
            $sqlin = ' AND u.username ' . $sqlusernames;
        }

        $sql = 'SELECT u.username, g.name AS groupname, count(DISTINCT di.id) as discussions, COUNT(p.id) AS posts'
            . ' FROM {forum_posts} p'
            . ' JOIN {user} u ON u.id = p.userid'
            . ' JOIN {forum_discussions} d ON p.discussion = d.id'
            . ' LEFT JOIN {groups} g ON g.id = d.groupid'
            . ' LEFT JOIN {forum_discussions} di ON di.userid = u.id AND p.discussion = di.id'
            . ' WHERE d.forum = :forumid'
            . $sqlin
            . ' group by u.username';
        return $DB->get_records_sql($sql, array_merge(array('forumid' => $forumid), $usernameparams));
    }

    function get_forums($courseid) {
        global $DB;
        $sql = 'SELECT f.id, cm.idnumber, f.name, f.type'
            . ' FROM {course_modules} cm'
            . ' JOIN {module}s m ON m.id = cm.module'
            . ' JOIN {forum} f ON f.id = cm.instance AND f.course = cm.course'
            . ' WHERE cm.course = :courseid AND m.name = :module';
        $params = array('module' => 'forum', 'courseid' => $courseid);
        return $DB->get_records_sql($sql, $params);
    }

    function get_grade_items($courseid) {
        $result = array();

        $grade_items = grade_item::fetch_all(array('courseid' => $courseid)) ?: array();

        foreach ($grade_items as $grade_item) {
            if ($grade_item->itemtype == 'course') {
                $name = null;
            } elseif ($grade_item->itemtype == 'category') {
                $grade_category = $grade_item->load_parent_category();
                $name = $grade_category->get_name();
            } else {
                $name = $grade_item->itemname;
            }
            $result[] = array(
                'id' => $grade_item->id,
                'idnumber' => $grade_item->idnumber,
                'type' => $grade_item->itemtype,
                'module' => $grade_item->itemmodule,
                'name' => $name,
                'sortorder' => $grade_item->sortorder,
                'grademin' => grade_format_gradevalue($grade_item->grademin, $grade_item),
                'grademax' => grade_format_gradevalue($grade_item->grademax, $grade_item),
                'gradepass' => grade_format_gradevalue($grade_item->gradepass, $grade_item),
            );
        }

        return $result;
    }

    function get_grades($itemid, $userids) {
        $result = array();

        $grade_item = grade_item::fetch(array('id' => $itemid));
        $errors = grade_regrade_final_grades($grade_item->courseid);
        $grade_grades = grade_grade::fetch_users_grades($grade_item, $userids);

        foreach ($userids as $userid) {
            $value = grade_format_gradevalue($grade_grades[$userid]->finalgrade, $grade_item);
            $result[$userid] = isset($errors[$itemid]) ? get_string('error') : $value;
        }

        return $result;
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

    function get_mail_stats_received($userid, $starttime, $endtime) {
        global $DB;
        $sql = 'SELECT c.id, c.shortname AS course, COUNT(*) as messages'
            . ' FROM {local_mail_index} i'
            . ' JOIN {local_mail_message_users} mu ON mu.messageid = i.messageid'
            . ' JOIN {local_mail_messages} m ON m.id = mu.messageid'
            . ' JOIN {course} c ON c.id = m.courseid'
            . ' WHERE i.userid = :userid1 AND i.type IN (:type1, :type2) AND i.item = 0'
            . ' AND i.time >= :starttime AND i.time < :endtime'
            . ' AND mu.userid = :userid2 AND mu.role != :role'
            . ' GROUP BY c.id, c.shortname';
        return $DB->get_records_sql($sql, array(
            'userid1' => $userid,
            'userid2' => $userid,
            'starttime' => $starttime,
            'endtime' => $endtime,
            'type1' => 'inbox',
            'type2' => 'trash',
            'role' => 'from',
        ));
    }

    function get_mail_stats_sent($userid, $starttime, $endtime) {
        global $DB;
        $sql = 'SELECT c.id, c.shortname AS course, COUNT(*) AS messages'
            . ' FROM {local_mail_index} i'
            . ' JOIN {local_mail_message_users} mu ON mu.messageid = i.messageid'
            . ' JOIN {local_mail_messages} m ON m.id = mu.messageid'
            . ' JOIN {course} c ON c.id = m.courseid'
            . ' WHERE i.userid = :userid1 AND i.type IN (:type1, :type2) AND i.item = 0'
            . ' AND i.time >= :starttime AND i.time < :endtime'
            . ' AND mu.userid = :userid2 AND mu.role = :role'
            . ' GROUP BY c.id, c.shortname';
        return $DB->get_records_sql($sql, array(
            'userid1' => $userid,
            'userid2' => $userid,
            'starttime' => $starttime,
            'endtime' => $endtime,
            'type1' => 'sent',
            'type2' => 'trash',
            'role' => 'from',
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

    function get_surveys($courseid) {
        global $DB;

        $sql = 'SELECT q.id, q.name, cm.idnumber, qs.realm'
            . ' FROM {modules} m'
            . ' JOIN {course_modules} cm ON cm.module = m.id'
            . ' JOIN {questionnaire} q ON q.id = cm.instance'
            . ' JOIN {questionnaire_survey} qs ON qs.id = q.sid'
            . ' WHERE m.name = :module'
            . ' AND cm.course = :courseid'
            . ' AND qs.owner = :owner'
            . ' AND qs.status != :status';

        return $DB->get_records_sql($sql, array(
            'courseid' => $courseid,
            'module' => 'questionnaire',
            'owner' => $courseid,
            'status' => 4,
        ));
    }

     function get_user($username) {
         global $CFG, $DB;

         $conditions = array(
             'mnethostid' => $CFG->mnet_localhost_id,
             'username' => $username,
             'deleted' => 0,
         );

         $fields = 'id, auth, username, firstname, lastname, email, lastaccess, picture';

         return $DB->get_record('user', $conditions, $fields);
     }

     function get_user_id($username) {
         global $CFG, $DB;
         return $DB->get_field('user', 'id', array(
             'mnethostid' => $CFG->mnet_localhost_id,
             'username' => $username,
             'deleted' => 0,
         ));
     }

     function get_user_lastaccess($userids) {
         global $DB;

         $sql = 'SELECT l.id, l.userid, c.shortname AS course, l.timeaccess AS time'
             . ' FROM {user_lastaccess} l'
             . ' JOIN {course} c ON c.id = l.courseid'
             . ' WHERE l.userid IN (' . implode(',', $userids) . ')';

         return $userids ? $DB->get_records_sql($sql) : false;
     }

    function get_users() {
        global $CFG, $DB;
        $select = 'mnethostid = ? AND deleted = ? AND username <> ?';
        $params = array($CFG->mnet_localhost_id, false, 'guest');
        $fields = 'id, username';
        return $DB->get_records_select('user', $select, $params, '', $fields);
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
         grade_recover_history_grades($userid, $courseid);
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

    function update_course($record) {
        global $DB;
        $record->timemodified = time();
        if (isset($record->visible)) {
            $record->visibleold = $DB->get_field('course', 'visible', array('id' => $record->id));
        }
        $DB->update_record('course', $record);
    }

    function update_password($userid, $password) {
        global $DB;
        $record = $DB->get_record('user', array('id' => $userid));
        update_internal_user_password($record, $password);
    }

    function update_user($record) {
        global $DB;
        $DB->update_record('user', $record);
    }

    function user_picture_url($userid) {
        global $CFG;
        $context = context_user::instance($userid);
        return "{$CFG->httpswwwroot}/pluginfile.php/{$context->id}/user/icon/f1";
    }
}
