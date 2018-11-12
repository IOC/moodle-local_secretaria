<?php

require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grouplib.php');
require_once($CFG->libdir . '/mathslib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/login/lib.php');
require_once($CFG->dirroot . '/local/secretaria/operations.php');

class local_secretaria_moodle_2x implements local_secretaria_moodle {

    private $transaction;

    public function auth_plugin() {
        return get_config('local_secretaria', 'auth_plugin');
    }

    public function check_password($password) {
        return $password and check_password_policy($password, $errormsg);
    }

    public function commit_transaction() {
        if ($this->transaction) {
            $this->transaction->allow_commit();
            $this->transaction = null;
        } else {
            throw new local_secretaria_exception('Internal error');
        }
    }

    public function create_survey($courseid, $section, $idnumber, $name, $summary,
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

    public function create_user($auth, $username, $password, $firstname, $lastname, $email) {
        global $CFG, $DB;

        $record = new stdClass;
        $record->auth = $auth;
        $record->mnethostid = $CFG->mnet_localhost_id;
        $record->username = core_text::strtolower($username);
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

        context_user::instance($id);
        \core\event\user_created::create_from_userid($id)->trigger();
    }

    public function delete_user($userid) {
        global $DB;
        $record = $DB->get_record('user', array('id' => $userid), 'id, username');
        delete_user($record);
    }

    public function delete_role_assignment($courseid, $userid, $roleid) {
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

    public function get_assignment_id($courseid, $idnumber) {
        global $DB;
        $sql = 'SELECT a.id'
            . ' FROM {course_modules} cm '
            . ' JOIN {modules} m ON m.id = cm.module'
            . ' JOIN {assign} a ON a.id = cm.instance'
            . ' WHERE cm.course = ? AND cm.idnumber = ?'
            . ' AND m.name = ? AND a.course = ?';
        return $DB->get_field_sql($sql, array($courseid, $idnumber, 'assign', $courseid));
    }

    public function get_assignment_submissions($assignmentid) {
        global $DB;

        $modid = $DB->get_field('modules', 'id', array('name' => 'assign'));
        $conditions = array('module' => $modid, 'instance' => $assignmentid);
        $cmid = $DB->get_field('course_modules', 'id', $conditions);
        $context = context_module::instance($cmid);

        $sql = 'SELECT s.id, us.username AS user, ug.username AS grader,'
            . ' s.timemodified AS timesubmitted, g.timemodified AS timegraded,'
            . ' COUNT(f.id) AS numfiles, s.attemptnumber AS attempt'
            . ' FROM {assign_submission} s'
            . ' JOIN {user} us ON us.id = s.userid'
            . ' LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid'
            . ' LEFT JOIN {user} ug ON ug.id = g.grader'
            . ' LEFT JOIN {files} f ON f.itemid = s.id'
            . ' AND f.contextid = :contextid AND f.filename != :filename'
            . ' WHERE s.assignment = :assignmentid'
            . ' AND s.status != :status'
            . ' GROUP BY user, grader, timesubmitted, timegraded';

        return $DB->get_records_sql($sql, array(
            'contextid' => $context->id,
            'filename' => '.',
            'assignmentid' => $assignmentid,
            'status' => 'new',
        ));
    }

    public function get_assignments($courseid) {
        global $DB;
        $sql = 'SELECT a.id, cm.idnumber, a.name,'
            . ' a.allowsubmissionsfromdate AS opentime, a.duedate AS closetime'
            . ' FROM {course_modules} cm '
            . ' JOIN {modules} m ON m.id = cm.module'
            . ' JOIN {assign} a ON a.id = cm.instance'
            . ' WHERE cm.course = ? AND m.name = ? AND a.course = ?';
        return $DB->get_records_sql($sql, array($courseid, 'assign', $courseid));
    }

    public function get_workshops($courseid) {
        global $DB;
        $sql = 'SELECT w.id, cm.idnumber, w.name,'
            . ' w.submissionstart AS opentime, w.submissionend AS closetime'
            . ' FROM {course_modules} cm '
            . ' JOIN {modules} m ON m.id = cm.module'
            . ' JOIN {workshop} w ON w.id = cm.instance'
            . ' WHERE cm.course = ? AND m.name = ? AND w.course = ?';
        return $DB->get_records_sql($sql, array($courseid, 'workshop', $courseid));
    }

    public function get_course($shortname) {
        global $DB;
        return $DB->get_record('course', array('shortname' => $shortname),
                               'id, shortname, fullname, visible, startdate');
    }

    public function get_course_grade($userid, $courseid) {
        grade_regrade_final_grades($courseid);
        $gradeitem = grade_item::fetch_course_item($courseid);
        $gradegrade = grade_grade::fetch(array('userid' => $userid, 'itemid' => $gradeitem->id));
        $value = grade_format_gradevalue($gradegrade->finalgrade, $gradeitem);
        return $gradeitem->needsupdate ? get_string('error') : $value;
    }

    public function get_course_id($shortname) {
        global $DB;
        return $DB->get_field('course', 'id', array('shortname' => $shortname));
    }

    public function get_courses() {
        global $DB;
        $select = 'id != :siteid';
        $params = array('siteid' => SITEID);
        $fields = 'id, shortname';
        return $DB->get_records_select('course', $select, $params, '', $fields);
    }

    public function get_course_url($courseid) {
        global $CFG;
        return "{$CFG->wwwroot}/course/view.php?id={$courseid}";
    }

    public function get_forum_stats($forumid) {
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

    public function get_forum_user_stats($forumid, $users) {
        global $DB;

        $sqlin = '';
        $usernameparams = array();

        if (!empty($users)) {
            $users = array_map('core_text::strtolower', $users);
            list($sqlusernames, $usernameparams) = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED, 'username');
            $sqlin = ' AND u.username ' . $sqlusernames;
        }

        $sql = 'SELECT u.username, g.name AS groupname, count(DISTINCT di.id) AS discussions, COUNT(p.id) AS posts'
            . ' FROM {forum_posts} p'
            . ' JOIN {user} u ON u.id = p.userid'
            . ' JOIN {forum_discussions} d ON p.discussion = d.id'
            . ' LEFT JOIN {groups} g ON g.id = d.groupid'
            . ' LEFT JOIN {forum_discussions} di ON di.userid = u.id AND p.discussion = di.id'
            . ' WHERE d.forum = :forumid'
            . $sqlin
            . ' GROUP BY u.username';
        return $DB->get_records_sql($sql, array_merge(array('forumid' => $forumid), $usernameparams));
    }

    public function get_forums($courseid) {
        global $DB;
        $sql = 'SELECT f.id, cm.idnumber, f.name, f.type'
            . ' FROM {course_modules} cm'
            . ' JOIN {module}s m ON m.id = cm.module'
            . ' JOIN {forum} f ON f.id = cm.instance AND f.course = cm.course'
            . ' WHERE cm.course = :courseid AND m.name = :module';
        $params = array('module' => 'forum', 'courseid' => $courseid);
        return $DB->get_records_sql($sql, $params);
    }

    public function get_grade_items($courseid) {
        $result = array();

        $gradeitems = grade_item::fetch_all(array('courseid' => $courseid)) ?: array();

        foreach ($gradeitems as $gradeitem) {
            if ($gradeitem->itemtype == 'course') {
                $name = null;
            } else if ($gradeitem->itemtype == 'category') {
                $gradecategory = $gradeitem->load_parent_category();
                $name = $gradecategory->get_name();
            } else {
                $name = $gradeitem->itemname;
            }
            $result[] = array(
                'id' => $gradeitem->id,
                'idnumber' => $gradeitem->idnumber,
                'type' => $gradeitem->itemtype,
                'module' => $gradeitem->itemmodule,
                'name' => $name,
                'sortorder' => $gradeitem->sortorder,
                'grademin' => grade_format_gradevalue($gradeitem->grademin, $gradeitem),
                'grademax' => grade_format_gradevalue($gradeitem->grademax, $gradeitem),
                'gradepass' => grade_format_gradevalue($gradeitem->gradepass, $gradeitem),
                'hidden' => $gradeitem->hidden,
            );
        }

        return $result;
    }

    public function get_grades($itemid, $userids) {
        $result = array();

        $gradeitem = grade_item::fetch(array('id' => $itemid));
        $errors = grade_regrade_final_grades($gradeitem->courseid);
        $gradegrades = grade_grade::fetch_users_grades($gradeitem, $userids);

        foreach ($userids as $userid) {
            $value = grade_format_gradevalue($gradegrades[$userid]->finalgrade, $gradeitem);
            $grader = !empty($gradegrades[$userid]->usermodified) ? self::get_user_username($gradegrades[$userid]->usermodified) : '';
            $result[$userid] = array(isset($errors[$itemid]) ? get_string('error') : $value, $grader);
        }

        return $result;
    }

    public function get_group_id($courseid, $name) {
        return groups_get_group_by_name($courseid, $name);
    }

    public function get_group_members($groupid) {
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

    public function get_mail_stats_received($userid, $starttime, $endtime) {
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

    public function get_mail_stats_sent($userid, $starttime, $endtime) {
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

    public function get_role_assignments_by_course($courseid) {
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

    public function get_role_assignments_by_user($userid) {
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

    public function get_role_id($role) {
        global $DB;
        return $DB->get_field('role', 'id', array('shortname' => $role));
    }

    public function get_questionnaire_id($courseid, $idnumber) {
        global $DB;

        $sql = 'SELECT q.id'
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

    public function get_survey_id($courseid, $idnumber) {
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

    public function get_surveys($courseid) {
        global $DB;

        $sql = 'SELECT q.id, q.name, cm.idnumber, qs.realm'
            . ' FROM {modules} m'
            . ' JOIN {course_modules} cm ON cm.module = m.id'
            . ' JOIN {questionnaire} q ON q.id = cm.instance'
            . ' JOIN {questionnaire_survey} qs ON qs.id = q.sid'
            . ' WHERE m.name = :module'
            . ' AND cm.course = :courseid'
            . ' AND qs.courseid = :owner'
            . ' AND qs.status != :status';

        return $DB->get_records_sql($sql, array(
            'courseid' => $courseid,
            'module' => 'questionnaire',
            'owner' => $courseid,
            'status' => 4,
        ));
    }

    public function get_survey_question_types() {
        global $DB;

        return $DB->get_records_menu('questionnaire_question_type', null, '', 'typeid, response_table');
    }

    public function get_survey_questions($surveyid) {
        global $DB;

        $sql = 'SELECT q.id, q.name, q.content, q.type_id, q.position, qt.has_choices'
            . ' FROM {questionnaire_question} q'
            . ' JOIN {questionnaire_question_type} qt ON qt.typeid = q.type_id'
            . ' WHERE q.surveyid = :surveyid';

        return $DB->get_records_sql($sql, array(
            'surveyid' => $surveyid
        ));
    }

    public function get_survey_responses_simple($questionids, $type) {
        global $DB;

        $content = '';
        list($sqlquestionids, $questionidparams) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'questionid');

        if ($type == 'response_bool') {
            $content = ', t.choice_id as content';
        } else if ($type == 'response_text' or $type == 'response_date') {
            $content = ', t.response as content';
        }

        $sql = 'SELECT t.id, t.response_id as responseid, t.question_id as questionid, u.username' . $content
            . ' FROM {questionnaire_' . $type. '} t'
            . ' JOIN {questionnaire_response} r ON r.id = t.response_id'
            . ' JOIN {user} u ON u.id = r.userid'
            . ' WHERE t.question_id ' . $sqlquestionids;

        return $DB->get_records_sql($sql, $questionidparams);
    }

    public function get_survey_responses_multiple($questionids, $type) {
        global $DB;

        list($sqlquestionids, $questionidparams) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'questionid');

        if ($type == 'response_rank') {
            $field = ', t.rankvalue';
            $sqlro = '';
            $paramc = array();
        } else if ($type == 'resp_single') {
            $field = ' ';
            $sqlro = '';
            $paramc = array();
        } else {
            $field = ', ro.response AS other';
            $sqlro = ' LEFT JOIN {questionnaire_response_other} ro ON ro.response_id = t.response_id AND c.content LIKE :content';
            $paramc = array('content' => '!other%');
        }

        $params = array_merge($paramc, $questionidparams);

        $sql = 'SELECT t.id, t.response_id as responseid, t.question_id as questionid, c.content, u.username' . $field
            . ' FROM {questionnaire_' . $type . '} t'
            . ' JOIN {questionnaire_quest_choice} c ON t.choice_id = c.id'
            . ' JOIN {questionnaire_response} r ON r.id=t.response_id'
            . ' JOIN {user} u ON u.id = r.userid'
            . $sqlro
            . ' WHERE t.question_id ' . $sqlquestionids;

        return $DB->get_records_sql($sql, $params);
    }

    public function get_survey_question_choices($questionids, $type) {
        global $DB;

        list($sqlquestionids, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'questionid');

        if ($type == 'response_rank') {
            $sql = 'SELECT id as questionid, length as content'
                . ' FROM {questionnaire_question}'
                . ' WHERE id ' . $sqlquestionids;
        } else {
            $sql = 'SELECT id, question_id as questionid, content'
                . ' FROM {questionnaire_quest_choice}'
                . ' WHERE question_id ' . $sqlquestionids;
        }

        return $DB->get_records_sql($sql, $params);
    }

    public function get_user($username) {
         global $CFG, $DB;

         $conditions = array(
             'mnethostid' => $CFG->mnet_localhost_id,
             'username' => core_text::strtolower($username),
             'deleted' => 0,
         );

         $fields = 'id, auth, username, firstname, lastname, email, lastaccess, picture';

         return $DB->get_record('user', $conditions, $fields);
    }

    public function get_user_id($username) {
         global $CFG, $DB;
         return $DB->get_field('user', 'id', array(
             'mnethostid' => $CFG->mnet_localhost_id,
             'username' => core_text::strtolower($username),
             'deleted' => 0,
         ));
    }

    public function get_user_username($userid) {
         global $DB;
         return $DB->get_field('user', 'username', array(
             'id' => $userid,
             'deleted' => 0,
         ));
    }

    public function get_user_lastaccess($userids) {
         global $DB;

         $sql = 'SELECT l.id, l.userid, c.shortname AS course, l.timeaccess AS time'
             . ' FROM {user_lastaccess} l'
             . ' JOIN {course} c ON c.id = l.courseid'
             . ' WHERE l.userid IN (' . implode(',', $userids) . ')';

         return $userids ? $DB->get_records_sql($sql) : false;
    }

    public function get_users($usernames) {
        global $CFG, $DB;

        $usersql = '';
        if (!empty($usernames)) {
            $usernames = array_map('core_text::strtolower', $usernames);
            list($usersql, $userparams) = $DB->get_in_or_equal($usernames);
        }
        $select = 'mnethostid = ? AND deleted = ? AND username <> ?';
        $params = array($CFG->mnet_localhost_id, false, 'guest');
        if (!empty($usersql)) {
            $select .= ' AND username ' . $usersql;
            $params = array_merge($params, $userparams);
        }
        $fields = 'id, username, firstname, lastname, email, picture, lastaccess';
        return $DB->get_records_select('user', $select, $params, '', $fields);
    }

    public function reset_password($user) {
        global $CFG, $DB;

        if (!empty($CFG->loginhttps)) {
            $CFG->httpswwwroot = str_replace('http:', 'https:', $CFG->wwwroot);
        }

        $resetinprogress = $DB->get_record('user_password_resets', array('userid' => $user->id));
        if (!empty($resetinprogress)) {
            $DB->delete_records('user_password_resets', array('id' => $resetinprogress->id));
        }
        $resetrecord = core_login_generate_password_reset($user);
        send_password_change_confirmation_email($user, $resetrecord);
    }

    public function groups_add_member($groupid, $userid) {
         groups_add_member($groupid, $userid);
    }

    public function groups_create_group($courseid, $name, $description) {
         $data = new stdClass;
         $data->courseid = $courseid;
         $data->name = $name;
         $data->description = $description;
         groups_create_group($data);
    }

    public function groups_delete_group($groupid) {
         groups_delete_group($groupid);
    }

    public function groups_get_all_groups($courseid, $userid=0) {
        return groups_get_all_groups($courseid, $userid);
    }

    public function groups_remove_member($groupid, $userid) {
        if (groups_remove_member_allowed($groupid, $userid)) {
            groups_remove_member($groupid, $userid);
        }
    }

    public function insert_role_assignment($courseid, $userid, $roleid, $recovergrades = false) {
         global $DB;

         $plugin = enrol_get_plugin('manual');
         $conditions = array('enrol' => 'manual', 'courseid' => $courseid);
         $enrol = $DB->get_record('enrol', $conditions, '*', MUST_EXIST);
         $plugin->enrol_user($enrol, $userid, $roleid, 0, 0, null, $recovergrades);
    }

    public function make_timestamp($year, $month, $day, $hour=0, $minute=0, $second=0) {
        return make_timestamp($year, $month, $day, $hour, $minute, $second);
    }

    public function prevent_local_passwords($auth) {
        return get_auth_plugin($auth)->prevent_local_passwords();
    }

    public function role_assignment_exists($courseid, $userid, $roleid) {
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

    public function rollback_transaction(Exception $e) {
        if ($this->transaction) {
            $this->transaction->rollback($e);
        }
    }

    public function section_exists($courseid, $section) {
        global $DB;
        $conditions = array('course' => $courseid, 'section' => $section);
        return $DB->record_exists('course_sections', $conditions);
    }

    public function send_mail($sender, $courseid, $subject, $content, $to, $cc, $bcc) {
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

    public function start_transaction() {
        global $DB;
        if ($this->transaction) {
            throw new local_secretaria_exception('Internal error');
        } else {
            $this->transaction = $DB->start_delegated_transaction();
        }
    }

    public function update_course($record) {
        global $DB;
        $record->timemodified = time();
        if (isset($record->visible)) {
            $record->visibleold = $DB->get_field('course', 'visible', array('id' => $record->id));
        }
        $DB->update_record('course', $record);
    }

    public function update_password($userid, $password) {
        global $DB;
        $record = $DB->get_record('user', array('id' => $userid));
        update_internal_user_password($record, $password);
    }

    public function update_survey($record) {
        global $DB;
        $DB->update_record('questionnaire', $record);
    }

    public function update_survey_idnumber($courseid, $oldidnumber, $newidnumber) {
        global $DB;
        $conditions = array('course' => $courseid, 'idnumber' => $oldidnumber);
        $record = $DB->get_record('course_modules', $conditions, '*', MUST_EXIST);
        $record->idnumber = $newidnumber;
        $DB->update_record('course_modules', $record);
    }

    public function update_user($record) {
        global $DB;
        $DB->update_record('user', $record);
    }

    public function user_picture_url($userid) {
        global $CFG;
        $context = context_user::instance($userid);
        return "{$CFG->httpswwwroot}/pluginfile.php/{$context->id}/user/icon/f1";
    }

    public function calc_formula($formula, $params) {
        $formulaparser = new calc_formula($formula, $params);
        return $formulaparser->evaluate();
    }
}
