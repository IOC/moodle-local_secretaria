<?php

class local_secretaria_exception extends Exception {
    public $errorcode;
}

class local_secretaria_operations {

    public function __construct($moodle=null) {
        $this->moodle = $moodle;
    }

    /* Users */

    public function get_user($username) {
        if (!$record = $this->moodle->get_user($username)) {
            throw new local_secretaria_exception('Unknown user');
        }

        $pixurl = $this->moodle->user_picture_url($record->id);

        return array(
            'username' => $username,
            'firstname' => $record->firstname,
            'lastname' => $record->lastname,
            'email' => $record->email,
            'picture' => $record->picture ? $pixurl : null,
            'lastaccess' => (int) $record->lastaccess,
        );
    }

    public function get_user_lastaccess($users) {
        $usernames = array();
        foreach ($users as $username) {
            if (!$userid = $this->moodle->get_user_id($username)) {
                throw new local_secretaria_exception('Unknown user');
            }
            $usernames[$userid] = core_text::strtolower($username);
        }

        $result = array();

        if ($records = $this->moodle->get_user_lastaccess(array_keys($usernames))) {
            foreach ($records as $record) {
                $result[] = array('user' => $usernames[$record->userid],
                                  'course' => $record->course,
                                  'time' => (int) $record->time);
            }
        }

        return $result;
    }

    public function create_user($properties) {
        if (!$properties['username'] or !$properties['firstname'] or !$properties['lastname']) {
            throw new local_secretaria_exception('Invalid username or firstname or lastname');
        }

        if ($this->moodle->get_user_id($properties['username'])) {
            throw new local_secretaria_exception('Duplicate username');
        }

        $auth = $this->moodle->auth_plugin();

        if ($this->moodle->prevent_local_passwords($auth)) {
            $properties['password'] = false;
        } else if (!isset($properties['password']) or
                  !$this->moodle->check_password($properties['password'])) {
            throw new local_secretaria_exception('Invalid password');
        }

        $this->moodle->start_transaction();
        $this->moodle->create_user(
            $auth,
            $properties['username'],
            $properties['password'],
            $properties['firstname'],
            $properties['lastname'],
            isset($properties['email']) ? $properties['email'] : ''
        );
        $this->moodle->commit_transaction();
    }

    public function update_user($username, $properties) {
        if (!$user = $this->moodle->get_user($username)) {
            throw new local_secretaria_exception('Unknown user');
        }

        $password = false;

        if (isset($properties['username'])) {
            if (empty($properties['username'])) {
                throw new local_secretaria_exception('Empty username');
            }
            $newuserid = $this->moodle->get_user_id($properties['username']);
            if ($newuserid and $newuserid !== $user->id) {
                throw new local_secretaria_exception('Duplicate username');
            }
        }

        if (isset($properties['password'])) {
            if (!$this->moodle->prevent_local_passwords($user->auth)) {
                if (!$this->moodle->check_password($properties['password'])) {
                    throw new local_secretaria_exception('Invalid password');
                }
                $password = $properties['password'];
            }
            unset($properties['password']);
        }

        if (isset($properties['firstname']) and empty($properties['firstname'])) {
            throw new local_secretaria_exception('Empty firstname');
        }

        if (isset($properties['lastname']) and empty($properties['lastname'])) {
            throw new local_secretaria_exception('Empty lastname');
        }

        $this->moodle->start_transaction();
        if ($properties) {
            $properties['id'] = $user->id;
            $this->moodle->update_user((object) $properties);
        }
        if ($password) {
            $this->moodle->update_password($user->id, $password);
        }
        $this->moodle->commit_transaction();
    }

    public function delete_user($username) {
        if (!$userid = $this->moodle->get_user_id($username)) {
            throw new local_secretaria_exception('Unknown user');
        }
        $this->moodle->start_transaction();
        $this->moodle->delete_user($userid);
        $this->moodle->commit_transaction();
    }

    public function get_users($usernames) {
        $result = array();
        if ($records = $this->moodle->get_users($usernames)) {
            foreach ($records as $record) {
                $pixurl = $this->moodle->user_picture_url($record->id);
                $result[] = array(
                    'username' => $record->username,
                    'firstname' => $record->firstname,
                    'lastname' => $record->lastname,
                    'email' => $record->email,
                    'picture' => $record->picture ? $pixurl : null,
                    'lastaccess' => $record->lastaccess,
                );
            }
        }
        return $result;
    }

    public function reset_password($username) {
        if (!$user = $this->moodle->get_user($username)) {
            throw new local_secretaria_exception('Unknown user');
        }
        $this->moodle->reset_password($user);
    }

    /* Courses */

    public function has_course($shortname) {
        return (bool) $this->moodle->get_course_id($shortname);
    }

    public function get_course($shortname) {
        if (!$record = $this->moodle->get_course($shortname)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $date = getdate($record->startdate);
        return array(
            'shortname' => $record->shortname,
            'fullname' => $record->fullname,
            'visible' => (bool) $record->visible,
            'startdate' => array(
                'year' => $date['year'],
                'month' => $date['mon'],
                'day' => $date['mday'],
            ),
        );
    }

    public function update_course($shortname, $properties) {
        if (!$courseid = $this->moodle->get_course_id($shortname)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $record = new stdClass;
        $record->id = $courseid;

        if (isset($properties['shortname'])) {
            if (empty($properties['shortname'])) {
                throw new local_secretaria_exception('Empty shortname');
            }
            $otherid = $this->moodle->get_course_id($properties['shortname']);
            if ($otherid and $otherid != $courseid) {
                throw new local_secretaria_exception('Duplicate shortname');
            }
            $record->shortname = $properties['shortname'];
        }

        if (isset($properties['fullname'])) {
            if (empty($properties['fullname'])) {
                throw new local_secretaria_exception('Empty fullname');
            }
            $record->fullname = $properties['fullname'];
        }

        if (isset($properties['visible'])) {
            $record->visible = (int) $properties['visible'];
        }

        if (isset($properties['startdate'])) {
            $record->startdate = mktime(0, 0, 0,
                                        $properties['startdate']['month'],
                                        $properties['startdate']['day'],
                                        $properties['startdate']['year']);
        }

        $this->moodle->update_course($record);
    }

    public function get_courses() {
        $result = array();
        if ($records = $this->moodle->get_courses()) {
            foreach ($records as $record) {
                $result[] = $record->shortname;
            }
        }
        return $result;
    }

    public function get_course_url($course) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        return $this->moodle->get_course_url($courseid);
    }

    /* Enrolments */

    public function get_course_enrolments($course) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $enrolments = array();
        if ($records = $this->moodle->get_role_assignments_by_course($courseid)) {
            foreach ($records as $record) {
                $enrolments[] = array('user' => $record->user, 'role' => $record->role);
            }
        }

        return $enrolments;
    }

    public function get_user_enrolments($username) {
        if (!$userid = $this->moodle->get_user_id($username)) {
            throw new local_secretaria_exception('Unknown user');
        }

        $enrolments = array();
        if ($records = $this->moodle->get_role_assignments_by_user($userid)) {
            foreach ($records as $record) {
                $enrolments[] = array('course' => $record->course, 'role' => $record->role);
            }
        }

        return $enrolments;
    }

    public function enrol_users($enrolments) {
        $this->moodle->start_transaction();

        foreach ($enrolments as $enrolment) {
            if (!$courseid = $this->moodle->get_course_id($enrolment['course'])) {
                throw new local_secretaria_exception('Unknown course');
            }
            if (!$userid = $this->moodle->get_user_id($enrolment['user'])) {
                continue;
            }
            if (!$roleid = $this->moodle->get_role_id($enrolment['role'])) {
                throw new local_secretaria_exception('Unknown role');
            }
            if (!$this->moodle->role_assignment_exists($courseid, $userid, $roleid)) {
                $recovergrades = isset($enrolment['recovergrades']) ? $enrolment['recovergrades'] : false;
                $this->moodle->insert_role_assignment($courseid, $userid, $roleid, $recovergrades);
            }
        }

        $this->moodle->commit_transaction();
    }

    public function unenrol_users($enrolments) {
        $this->moodle->start_transaction();

        foreach ($enrolments as $enrolment) {
            if (!$courseid = $this->moodle->get_course_id($enrolment['course'])) {
                throw new local_secretaria_exception('Unknown course');
            }
            if (!$userid = $this->moodle->get_user_id($enrolment['user'])) {
                continue;
            }
            if (!$roleid = $this->moodle->get_role_id($enrolment['role'])) {
                throw new local_secretaria_exception('Unknown role');
            }
            $this->moodle->delete_role_assignment($courseid, $userid, $roleid);
        }

        $this->moodle->commit_transaction();
    }

    /* Groups */

    public function get_groups($course) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $groups = array();

        if ($records = $this->moodle->groups_get_all_groups($courseid)) {
            foreach ($records as $record) {
                $groups[] = array('name' => $record->name,
                                  'description' => $record->description);
            }
        }

        return $groups;
    }

    public function create_group($course, $name, $description) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (empty($name)) {
            throw new local_secretaria_exception('Empty group name');
        }
        if ($this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Duplicate group');
        }
        $this->moodle->start_transaction();
        $this->moodle->groups_create_group($courseid, $name, $description);
        $this->moodle->commit_transaction();
    }

    public function delete_group($course, $name) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }
        $this->moodle->start_transaction();
        $this->moodle->groups_delete_group($groupid);
        $this->moodle->commit_transaction();
    }

    public function get_group_members($course, $name) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }
        $users = array();
        if ($records = $this->moodle->get_group_members($groupid)) {
            foreach ($records as $record) {
                $users[] = $record->username;
            }
        }
        return $users;
    }

    public function add_group_members($course, $name, $users) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }

        $this->moodle->start_transaction();

        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($user)) {
                continue;
            }
            $this->moodle->groups_add_member($groupid, $userid);
        }

        $this->moodle->commit_transaction();
    }

    public function remove_group_members($course, $name, $users) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }

        $this->moodle->start_transaction();

        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($user)) {
                continue;
            }
            $this->moodle->groups_remove_member($groupid, $userid);
        }

        $this->moodle->commit_transaction();
    }

    public function get_user_groups($user, $course) {
        if (!$userid = $this->moodle->get_user_id($user)) {
            throw new local_secretaria_exception('Unknown user');
        }
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $groups = array();

        if ($records = $this->moodle->groups_get_all_groups($courseid, $userid)) {
            foreach ($records as $record) {
                $groups[] = $record->name;
            }
        }

        return $groups;
    }

    /* Grades */

    public function get_course_grades($course, $users) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $usernames = array();
        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($user)) {
                throw new local_secretaria_exception('Unknown user');
            }
            $usernames[$userid] = core_text::strtolower($user);
        }
        $userids = array_keys($usernames);

        $result = array();

        $items = $this->moodle->get_grade_items($courseid);
        usort($items, function ($a, $b) {
            return $a['sortorder'] - $b['sortorder'];
        });

        foreach ($items as $item) {
            $grades = array();
            if ($userids) {
                foreach ($this->moodle->get_grades($item['id'], $userids) as $userid => $data) {
                    $grades[] = array('user' => $usernames[$userid], 'grade' => $data[0], 'grader' => $data[1]);
                }
            }
            $result[] = array(
                'idnumber' => $item['idnumber'] ?: '',
                'type' => $item['type'],
                'module' => $item['module'],
                'name' => $item['name'],
                'grademin' => $item['grademin'],
                'grademax' => $item['grademax'],
                'gradepass' => $item['gradepass'],
                'hidden' => $item['hidden'],
                'grades' => $grades,
            );
        }

        return $result;
    }

    public function get_user_grades($user, $courses) {
        if (!$userid = $this->moodle->get_user_id($user)) {
            throw new local_secretaria_exception('Unknown user');
        }

        $result = array();

        foreach ($courses as $course) {
            if (!$courseid = $this->moodle->get_course_id($course)) {
                throw new local_secretaria_exception('Unknown course');
            }
            $result[] = array(
                'course' => $course,
                'grade' => $this->moodle->get_course_grade($userid, $courseid),
            );
        }

        return $result;
    }

    /* Assignments */

    public function get_assignments($course) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $result = array();

        if ($records = $this->moodle->get_assignments($courseid)) {
            foreach ($records as $record) {
                $result[] = array(
                    'idnumber' => $record->idnumber ?: '',
                    'name' => $record->name,
                    'opentime' => (int) $record->opentime ?: null,
                    'closetime' => (int) $record->closetime ?: null,
                );
            }
        }

        return $result;
    }

    public function get_assignment_submissions($course, $idnumber) {
        if (!$idnumber) {
            throw new local_secretaria_exception('Invalid idnumber');
        }

        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        if (!$assignmentid = $this->moodle->get_assignment_id($courseid, $idnumber)) {
            throw new local_secretaria_exception('Unknown assignment');
        }

        $result = array();

        if ($records = $this->moodle->get_assignment_submissions($assignmentid)) {
            foreach ($records as $record) {
                $result[] = array(
                    'user' => $record->user,
                    'grader' => $record->grader,
                    'timesubmitted' => (int) $record->timesubmitted,
                    'timegraded' => (int) $record->timegraded ?: null,
                    'numfiles' => (int) $record->numfiles,
                    'attempt' => (int) $record->attempt,
                );
            }
        }

        return $result;
    }

    /* Forums */

    public function get_forum_stats($course) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $result = array();

        if ($forums = $this->moodle->get_forums($courseid)) {
            foreach ($forums as $forum) {
                $stats = array();
                if ($records = $this->moodle->get_forum_stats($forum->id)) {
                    foreach ($records as $record) {
                        $stats[] = array(
                            'group' => $record->groupname ?: '',
                            'discussions' => (int) $record->discussions,
                            'posts' => (int) $record->posts,
                        );
                    }
                }
                $result[] = array(
                    'idnumber' => $forum->idnumber ?: '',
                    'name' => $forum->name,
                    'type' => $forum->type,
                    'stats' => $stats,
                );
            }
        }

        return $result;
    }

    public function get_forum_user_stats($course, $users) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $result = array();

        if ($forums = $this->moodle->get_forums($courseid)) {
            foreach ($forums as $forum) {
                $stats = array();
                if ($records = $this->moodle->get_forum_user_stats($forum->id, $users)) {
                    foreach ($records as $record) {
                        $stats[] = array(
                            'username' => $record->username,
                            'group' => $record->groupname?:'',
                            'discussions' => $record->discussions,
                            'posts' => $record->posts,
                        );
                    }
                }
                $result[] = array(
                    'idnumber' => $forum->idnumber ?: '',
                    'name' => $forum->name,
                    'type' => $forum->type,
                    'stats' => $stats,
                );
            }
        }

        return $result;
    }

    /* Surveys */

    public function get_surveys($course) {
        $result = array();

        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        if ($records = $this->moodle->get_surveys($courseid)) {
            foreach ($records as $record) {
                $result[] = array(
                    'idnumber' => $record->idnumber ?: '',
                    'name' => $record->name,
                    'type' => $record->realm,
                );
            }
        }

        return $result;
    }

    public function get_surveys_data($course) {
        $result = array();

        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        if ($surveys = $this->moodle->get_surveys($courseid)) {
            $questiontypes = $this->moodle->get_survey_question_types();
            foreach ($surveys as $survey) {
                $stats = array();
                if (!empty($survey->idnumber)) {
                    $surveyid = $this->moodle->get_survey_id($courseid, $survey->idnumber);
                    $questions = $this->moodle->get_survey_questions($surveyid);

                    $groupquestions = array();
                    foreach ($questions as $question) {
                        if (!isset($groupquestions[$question->type_id])) {
                            $groupquestions[$question->type_id] = array();
                        }
                        $groupquestions[$question->type_id][] = $question->id;
                        $questions[$question->id]->type = $questiontypes[$question->type_id];
                    }
                    $responsestats = array();
                    $questionchoices = array();
                    foreach ($groupquestions as $type => $question) {
                        switch ($questiontypes[$type]) {
                            case 'response_bool':
                            case 'response_text':
                            case 'response_date':
                                $responses = $this->moodle->get_survey_responses_simple($question, $questiontypes[$type]);
                                $choices = array();
                                break;
                            default:
                                if (empty($questiontypes[$type])) {
                                    $responses = array();
                                    $choices = array();
                                } else {
                                    $responses = $this->moodle->get_survey_responses_multiple($question, $questiontypes[$type]);
                                    $choices = $this->moodle->get_survey_question_choices($question, $questiontypes[$type]);
                                }
                        }
                        if (!empty($responses)) {
                            foreach ($responses as $response) {
                                if (!isset($responsestats[$response->questionid])) {
                                    $responsestats[$response->questionid] = array();
                                }
                                if (isset($response->other) and strpos($response->content, '!other') !== false) {
                                    if (strpos($response->content, '!other=') !== false) {
                                        $response->content = preg_replace('/^\!other\=/', '', $response->content);
                                        $response->content .= ' ' . $response->other;
                                    } else {
                                        $response->content = $response->other;
                                    }
                                }
                                if (!isset($responsestats[$response->questionid][$response->responseid])) {
                                    $responsestats[$response->questionid][$response->responseid] = array (
                                        'username' => $response->username,
                                        'content' => array($response->content),
                                        'rank' => isset($response->rankvalue) ? array($response->rankvalue) : array(),
                                    );
                                } else {
                                    $responsestats[$response->questionid][$response->responseid]['content'][] = $response->content;
                                    if (isset($response->rankvalue)) {
                                        $responsestats[$response->questionid][$response->responseid]['rank'][] = $response->rankvalue;
                                    }
                                }
                            }
                        }
                        if (!empty($choices)) {
                            if ($questiontypes[$type] == 'response_rank') {
                                foreach ($choices as $choice) {
                                    $questionchoices[$choice->questionid] = range(1, $choice->content);
                                }
                            } else {
                                foreach ($choices as $choice) {
                                    if (!isset($questionchoices[$choice->questionid])) {
                                        $questionchoices[$choice->questionid] = array();
                                    }
                                    $questionchoices[$choice->questionid][] = $choice->content;
                                }
                            }
                        }
                    }
                    foreach ($questions as $key => $question) {
                        // Avoid question types QUESPAGEBREAK and QUESSECTIONTEXT
                        if ($question->type_id != 99 && $question->type_id != 100) {
                            $stats[] = array(
                                'name' => $question->name,
                                'content' => $question->content,
                                'position' => $question->position,
                                'type' => $question->type,
                                'has_choices' => $question->has_choices,
                                'choices' => isset($questionchoices[$question->id]) ? $questionchoices[$question->id] : array(),
                                'responses' => isset($responsestats[$question->id]) ? $responsestats[$question->id] : array(),
                            );
                        }
                    }
                }
                $result[] = array(
                    'idnumber' => $survey->idnumber ?: '',
                    'name' => $survey->name,
                    'type' => $survey->realm,
                    'questions' => isset($stats) ? $stats : array(),
                );
            }
        }
        return $result;
    }

    public function create_survey($properties) {
        if (empty($properties['idnumber']) or
            empty($properties['name']) or
            empty($properties['summary']) or
            empty($properties['template']['course']) or
            empty($properties['template']['idnumber'])) {
            throw new local_secretaria_exception('Empty idnumber or name or summary or template/course or template/idnumber');
        }

        if (!$courseid = $this->moodle->get_course_id($properties['course'])) {
            throw new local_secretaria_exception('Unknown course');
        }

        if (!$this->moodle->section_exists($courseid, $properties['section'])) {
            throw new local_secretaria_exception('Unknown section');
        }

        if ($this->moodle->get_survey_id($courseid, $properties['idnumber'])) {
            throw new local_secretaria_exception('Duplicate idnumber');
        }

        if (!$templatecourseid = $this->moodle->get_course_id($properties['template']['course'])) {
            throw new local_secretaria_exception('Unknown course');
        }

        if (!$templateid = $this->moodle->get_survey_id($templatecourseid,
                                                        $properties['template']['idnumber'])) {
            throw new local_secretaria_exception('Unknown survey');
        }

        $opendate = (isset($properties['opendate']) ?
                     mktime(0, 0, 0,
                            $properties['opendate']['month'],
                            $properties['opendate']['day'],
                            $properties['opendate']['year'])
                     : 0);

        $closedate = (isset($properties['closedate']) ?
                      mktime(23, 55, 0,
                             $properties['closedate']['month'],
                             $properties['closedate']['day'],
                             $properties['closedate']['year'])
                      : 0);

        $this->moodle->start_transaction();
        $this->moodle->create_survey($courseid, $properties['section'], $properties['idnumber'],
                                     $properties['name'], $properties['summary'],
                                     $opendate, $closedate, $templateid);
        $this->moodle->commit_transaction();
    }

    public function update_survey($course, $idnumber, $properties) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        if (!$questionnaireid = $this->moodle->get_questionnaire_id($courseid, $idnumber)) {
            throw new local_secretaria_exception('Unknown questionnaire');
        }

        if (isset($properties['idnumber'])) {
            if (empty($properties['idnumber'])) {
                throw new local_secretaria_exception('Empty idnumber');
            }
            if ($this->moodle->get_questionnaire_id($courseid, $properties['idnumber'])) {
                throw new local_secretaria_exception('Duplicated idnumber');
            }
            $this->moodle->update_survey_idnumber($courseid, $idnumber, $properties['idnumber']);
        }

        if (isset($properties['name'])) {
            if (empty($properties['name'])) {
                throw new local_secretaria_exception('Empty name');
            }
            $record = new stdClass;
            $record->id = $questionnaireid;
            $record->name = $properties['name'];

            $this->moodle->update_survey($record);
        }
    }

    /* Workshops */

    public function get_workshops($course) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $result = array();

        if ($records = $this->moodle->get_workshops($courseid)) {
            foreach ($records as $record) {
                $result[] = array(
                    'idnumber' => $record->idnumber ?: '',
                    'name' => $record->name,
                    'opentime' => (int) $record->opentime ?: null,
                    'closetime' => (int) $record->closetime ?: null,
                );
            }
        }

        return $result;
    }

    /* Mail */

    public function send_mail($message) {
        if (!$courseid = $this->moodle->get_course_id($message['course'])) {
            throw new local_secretaria_exception('Unknown course');
        }

        $usernames = array_merge(array($message['sender']), $message['to']);
        if (isset($message['cc'])) {
            $usernames = array_merge($usernames, $message['cc']);
        }
        if (isset($message['bcc'])) {
            $usernames = array_merge($usernames, $message['bcc']);
        }
        if (!$message['to'] or count($usernames) != count(array_unique($usernames))) {
            throw new local_secretaria_exception('Invalid recipient or duplicated recipient');
        }

        $sender = false;
        $to = array();
        $cc = array();
        $bcc = array();

        foreach ($usernames as $username) {
            if (!$userid = $this->moodle->get_user_id($username)) {
                throw new local_secretaria_exception('Unknown user');
            }
            if ($username == $message['sender']) {
                $sender = $userid;
            } else if (in_array($username, $message['to'])) {
                $to[] = $userid;
            } else if (in_array($username, $message['cc'])) {
                $cc[] = $userid;
            } else if (in_array($username, $message['bcc'])) {
                $bcc[] = $userid;
            }
        }

        $this->moodle->send_mail($sender, $courseid, $message['subject'],
                                 $message['content'], $to, $cc, $bcc);
    }

    public function get_mail_stats($user, $starttime, $endtime) {
        if (!$userid = $this->moodle->get_user_id($user)) {
            throw new local_secretaria_exception('Unknown user');
        }

        $courses = array();
        $received = array();
        $sent = array();

        if ($records = $this->moodle->get_mail_stats_received($userid, $starttime, $endtime)) {
            foreach ($records as $id => $record) {
                $courses[$id] = $record->course;
                $received[$id] = (int) $record->messages;
            }
        }

        if ($records = $this->moodle->get_mail_stats_sent($userid, $starttime, $endtime)) {
            foreach ($records as $id => $record) {
                $courses[$id] = $record->course;
                $sent[$id] = (int) $record->messages;
            }
        }

        $result = array();

        foreach ($courses as $id => $course) {
            $result[] = array(
                'course' => $course,
                'received' => isset($received[$id]) ? $received[$id] : 0,
                'sent' => isset($sent[$id]) ? $sent[$id] : 0,
            );
        }

        return $result;
    }

    public function calc_formula($formula, $variables, $values) {
        if (!$formula) {
            throw new local_secretaria_exception("Empty formula");
        }
        if (count($variables) != count($values)) {
            throw new local_secretaria_exception("Not equal number of elements in arrays");
        }
        $params = array_combine($variables, $values);
        if (!$result = $this->moodle->calc_formula($formula, $params)) {
            throw new local_secretaria_exception("Invalid formula");
        }
        return $result;
    }
}

interface local_secretaria_moodle {
    public function auth_plugin();
    public function calc_formula($formula, $params);
    public function check_password($password);
    public function commit_transaction();
    public function create_survey($courseid, $section, $name, $summary, $idnumber,
                           $opendate, $closedate, $templateid);
    public function create_user($auth, $username, $password, $firstname, $lastname, $email);
    public function delete_user($record);
    public function delete_role_assignment($courseid, $userid, $roleid);
    public function get_assignment_id($courseid, $idnumber);
    public function get_assignment_submissions($assignmentid);
    public function get_assignments($courseid);
    public function get_course($shortname);
    public function get_course_id($shortname);
    public function get_course_url($courseid);
    public function get_courses();
    public function get_course_grade($userid, $courseid);
    public function get_forum_stats($forumid);
    public function get_forum_user_stats($forumid, $users);
    public function get_forums($courseid);
    public function get_grade_items($courseid);
    public function get_grades($itemid, $userids);
    public function get_group_id($courseid, $name);
    public function get_group_members($groupid);
    public function get_mail_stats_sent($userid, $starttime, $endtime);
    public function get_mail_stats_received($userid, $starttime, $endtime);
    public function get_questionnaire_id($courseid, $idnumber);
    public function get_role_assignments_by_course($courseid);
    public function get_role_assignments_by_user($userid);
    public function get_role_id($role);
    public function get_survey_id($courseid, $idnumber);
    public function get_surveys($courseid);
    public function get_survey_question_types();
    public function get_survey_questions($surveyid);
    public function get_survey_responses_simple($questionids, $type);
    public function get_survey_responses_multiple($questionids, $type);
    public function get_survey_question_choices($questionids, $type);
    public function get_user($username);
    public function get_user_id($username);
    public function get_user_username($userid);
    public function get_user_lastaccess($userids);
    public function get_users($usernames);
    public function groups_add_member($groupid, $userid);
    public function groups_create_group($courseid, $name, $description);
    public function groups_delete_group($groupid);
    public function groups_get_all_groups($courseid, $userid=0);
    public function groups_remove_member($groupid, $userid);
    public function insert_role_assignment($courseid, $userid, $roleid, $recovergrades=false);
    public function prevent_local_passwords($auth);
    public function reset_password($user);
    public function role_assignment_exists($courseid, $userid, $roleid);
    public function rollback_transaction(Exception $e);
    public function section_exists($courseid, $section);
    public function send_mail($sender, $courseid, $subject, $content, $to, $cc, $bcc);
    public function start_transaction();
    public function update_course($record);
    public function update_password($userid, $password);
    public function update_survey($record);
    public function update_survey_idnumber($courseid, $oldidnumber, $newidnumber);
    public function update_user($user);
    public function user_picture_url($userid);
}
