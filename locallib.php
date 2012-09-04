<?php

require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/grouplib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/local/secretaria/operations.php');

class local_secretaria_moodle_22 implements local_secretaria_moodle {

    private $mnethostid;
    private $transaction;

    function __construct() {
        $this->mnethostid = get_config('local_secretaria', 'mnethostid');
    }

    function check_password($password) {
        return check_password_policy($password);
    }

    function commit_transaction() {
        if ($this->transaction) {
            $this->transaction->allow_commit();
            $this->transaction = null;
        } else {
            throw new local_secretaria_exception('Internal error');
        }
    }

    function create_user($auth, $mnethostid, $username, $password,
                         $firstname, $lastname, $email) {
        global $CFG;
        $user = new stdClass;
        $user->auth = $auth;
        $user->mnethostid = $mnethostid;
        $user->username = $username;
        if ($password) {
            $user['password'] = $password;
        }
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;
        $user->confirmed = true;
        $user->lang = $CFG->lang;
        user_create_user($user);
    }

    function delete_user($record) {
        delete_user($record);
    }

    function delete_role_assignment($courseid, $userid, $roleid) {
        global $DB;

        $conditions = array('enrol' => 'secretaria', 'courseid' => $courseid);
        $enrol = $DB->get_record('enrol', $conditions, '*', MUST_EXIST);
        $context = context_course::instance($courseid);

        role_unassign($roleid, $userid, $context->id, 'enrol_secretaria', $enrol->id);

        $conditions = array(
            'component' => 'enrol_secretaria',
            'itemid' => $enrol->id,
            'contextid' => $cotnext->id,
            'userid' => $userid,
        );
        if (!$DB->record_exists('role_assignments', $conditions)) {
            $plugin = enrol_get_plugin('secretaria');
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

    function get_group_members($groupid, $mnethostid) {
        global $DB;
        $sql = 'SElECT u.username'
            . ' FROM {groups_members} gm'
            . ' JOIN {user} u ON u.id = gm.userid'
            . ' WHERE gm.groupid = :groupid'
            . ' AND u.mnethostid = :mnethostid';
        return $DB->get_records_sql($sql, array(
            'groupid' => $groupid,
            'mnethostid' => $mnethostid,
        ));
    }

    function get_groups($courseid) {
        return groups_get_all_groups($courseid);
     }

     function get_role_assignments_by_course($courseid, $mnethostid) {
         global $DB;

         $sql = 'SELECT ra.id, u.username AS user, r.shortname AS role'
             . ' FROM {enrol} e'
             . ' JOIN {user_enrolments} ue ON ue.enrolid = e.id'
             . ' JOIN {role_assignments} ra ON ra.itemid = e.id'
             . ' JOIN {context} ct ON ct.id = ra.contextid'
             . ' JOIN {user} u ON u.id = ra.userid'
             . ' JOIN {role} r ON r.id = ra.roleid'
             . ' WHERE ue.userid = ra.userid'
             . ' AND ct.instanceid = e.courseid'
             . ' AND e.enrol = :enrol'
             . ' AND e.courseid = :courseid'
             . ' AND ra.component = :component'
             . ' AND ct.contextlevel = :contextlevel'
             . ' AND u.mnethostid = :mnethostid';

         return $DB->get_records_sql($sql, array(
             'enrol' => 'secretaria',
             'courseid' => $courseid,
             'component' => 'enrol_secretaria',
             'contextlevel' => CONTEXT_COURSE,
             'mnethostid' => $mnethostid,
         ));
     }

     function get_role_assignments_by_user($userid) {
         global $DB;

         $sql = 'SELECT ra.id, c.shortname AS course, r.shortname AS role'
             . ' FROM {role_assignments} ra'
             . ' JOIN {user_enrolments} ue ON ue.enrolid = ra.itemid'
             . ' JOIN {enrol} e ON e.id = ra.itemid'
             . ' JOIN {context} ct ON ct.id = ra.contextid'
             . ' JOIN {course} c ON c.id = ct.instanceid'
             . ' JOIN {role} r ON r.id = ra.roleid'
             . ' WHERE ra.component = :component'
             . ' AND ra.userid = :userid'
             . ' AND ue.userid = ra.userid'
             . ' AND e.enrol = :enrol'
             . ' AND ct.contextlevel = :contextlevel'
             . ' AND c.id = e.courseid';

         return $DB->get_records_sql($sql, array(
             'component' => 'enrol_secretaria',
             'enrol' => 'secretaria',
             'userid' => $userid,
             'contextlevel' => CONTEXT_COURSE,
         ));
     }

     function get_role_id($role) {
         global $DB;
         return $DB->get_field('role', 'id', array('shortname' => $role));
     }

     function get_user_id($mnethostid, $username) {
         global $DB;
         return $DB->get_field('user', 'id', array(
             'mnethostid' => $mnethostid,
             'username' => $username,
             'deleted' => 0,
         ));
     }

     function get_user_record($mnethostid, $username) {
         global $DB;
         return $DB->get_record('user', array(
             'mnethostid' => $mnethostid,
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

     function groups_remove_member($groupid, $userid) {
         groups_remove_member($groupid, $userid);
     }

     function insert_role_assignment($courseid, $userid, $roleid) {
         global $DB;

         $plugin = enrol_get_plugin('secretaria');
         $conditions = array('enrol' => 'secretaria', 'courseid' => $courseid);
         $enrol = $DB->get_record('enrol', $conditions, '*', MUST_EXIST);
         $plugin->enrol_user($enrol, $userid, $roleid);
     }

     function mnet_host_id() {
         return (int) $this->mnethostid;
     }

     function mnet_localhost_id() {
         global $CFG;
         return (int) $CFG->mnet_localhost_id;
     }

     function role_assignment_exists($courseid, $userid, $roleid) {
         global $DB;

         $sql = 'SELECT ra.id'
             . ' FROM {role_assignments} ra'
             . ' JOIN {enrol} e ON e.id = ra.itemid'
             . ' JOIN {user_enrolments} ue ON ue.enrolid = e.id'
             . ' JOIN {context} ct ON ct.id = ra.contextid'
             . ' WHERE ue.userid = ra.userid'
             . ' AND e.courseid = ct.instanceid'
             . ' AND ra.component = :component'
             . ' AND ra.userid = :userid'
             . ' AND ra.roleid = :roleid'
             . ' AND e.enrol = :enrol'
             . ' AND e.courseid = :courseid'
             . ' AND ct.contextlevel = :contextlevel';

         return $DB->record_exists_sql($sql, array(
             'component' => 'enrol_secretaria',
             'userid' => $userid,
             'roleid' => $roleid,
             'enrol' => 'secretaria',
            'courseid' => $courseid,
            'contextlevel' => CONTEXT_COURSE,
        ));
    }

    function rollback_transaction(Exception $e) {
        if ($this->transaction) {
            $this->transaction->rollback($e);
        }
    }

    function send_mail($sender, $courseid, $subject, $content, $to, $cc, $bcc) {
        global $CFG;

        require_once($CFG->dirroot . '/local/mail/message.class.php');

        $message = local_mail_message::create($sender, $courseid, $subject, $content, FORMAT_HTML);

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
