<?php

require_once 'operations.php';
require_once 'Mockery/Loader.php';

$loader = new Mockery\Loader;
$loader->register();

Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

abstract class OperationTest extends PHPUnit_Framework_TestCase {

    protected $moodle;
    protected $operations;

    function setUp() {
        $this->moodle = Mockery::mock('local_secretaria_moodle');
        $this->moodle->shouldReceive('get_course_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_group_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_role_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_user_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_user_record')->andReturn(false)->byDefault();
        $this->operations = new local_secretaria_operations($this->moodle);
    }

    function tearDown() {
        Mockery::close();
    }

    protected function having_course_id($shortname, $courseid) {
        $this->moodle->shouldReceive('get_course_id')
            ->with($shortname)->andReturn($courseid);
    }

    protected function having_group_id($courseid, $groupname, $groupid) {
        $this->moodle->shouldReceive('get_group_id')
            ->with($courseid, $groupname)->andReturn($groupid);
    }

    protected function having_role_id($shortname, $roleid) {
        $this->moodle->shouldReceive('get_role_id')
            ->with($shortname)->andReturn($roleid);
    }

    protected function having_user_id($username, $userid) {
        $this->moodle->shouldReceive('get_user_id')
            ->with($username)->andReturn($userid);
    }

    protected function having_user_record($username, $record) {
        $this->moodle->shouldReceive('get_user_record')
            ->with($username)->andReturn((object) $record);
    }
}

/* Users */

class GetUserTest extends OperationTest {

    function setUp() {
        parent::setUp();
        $this->record = (object) array(
            'id' => 201,
            'username' => 'user',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user@example.org',
            'picture' => '1',
            'lastaccess' => '1234567890',
        );
    }

    function test() {
        $this->having_user_record('user', $this->record);
        $this->moodle->shouldReceive('user_picture_url')->with(201)
            ->andReturn('http://example.org/user/pix.php/201/f1.jpg');

        $result = $this->operations->get_user('user');

        $this->assertThat($result, $this->identicalTo(array(
            'username' => 'user',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user@example.org',
            'picture' => 'http://example.org/user/pix.php/201/f1.jpg',
            'lastaccess' => 1234567890,
        )));
    }

    function test_no_picture() {
        $this->record->picture = 0;
        $this->having_user_record('user', $this->record);
        $this->moodle->shouldReceive('user_picture_url')->with(201)
            ->andReturn('http://example.org/user/pix.php/201/f1.jpg');

        $result = $this->operations->get_user('user');

        $this->assertThat($result['picture'], $this->isNull());
    }

    function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user('user');
    }
}

class GetUserLastAccessTest extends OperationTest {

    function test() {
        $this->having_user_id('user1', 201);
        $this->having_user_id('user2', 202);
        $this->having_user_id('user3', 203);
        $records = array(
            (object) array('id' => 301, 'userid' => 201, 'course' => 'CP1', 'time' => 1234567891),
            (object) array('id' => 302, 'userid' => 201, 'course' => 'CP2', 'time' => 1234567892),
            (object) array('id' => 303, 'userid' => 202, 'course' => 'CP1', 'time' => 1234567893),
        );
        $this->moodle->shouldReceive('get_user_lastaccess')
            ->with(array(201, 202, 203))->andReturn($records);

        $result = $this->operations->get_user_lastaccess(array('user1', 'user2', 'user3'));

        $this->assertThat($result, $this->identicalTo(array(
            array('user' => 'user1', 'course' => 'CP1', 'time' => 1234567891),
            array('user' => 'user1', 'course' => 'CP2', 'time' => 1234567892),
            array('user' => 'user2', 'course' => 'CP1', 'time' => 1234567893),
        )));
    }

    function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user_lastaccess(array('user1'));
    }
}

class CreateUserTest extends OperationTest {

    function setUp() {
        parent::setUp();
        $this->properties = array(
            'username' => 'user1',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user1@example.org',
            'password' => 'abc123',
        );
    }

    function test() {
        $this->moodle->shouldReceive('auth_plugin')->with()->andReturn('manual');
        $this->moodle->shouldReceive('prevent_local_passwords')
            ->with('manual')->andReturn(false);
        $this->moodle->shouldReceive('check_password')
            ->with('abc123')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_user')
            ->with('manual', 'user1', 'abc123', 'First', 'Last', 'user1@example.org')
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_user($this->properties);
    }

    function test_prevent_local_passwords() {
        $this->moodle->shouldReceive('auth_plugin')->andReturn('msso');
        $this->moodle->shouldReceive('prevent_local_passwords')
            ->with('msso')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_user')
            ->with('msso', 'user1', false, 'First', 'Last', 'user1@example.org')
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_user($this->properties);
    }

    function test_blank_username() {
        $this->properties['username'] = '';
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_user($this->properties);
    }

    function test_blank_firstname() {
        $this->properties['firstname'] = '';
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_user($this->properties);
    }

    function test_blank_lastname() {
        $this->properties['lastname'] = '';
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_user($this->properties);
    }

    function test_blank_email() {
        $this->properties['email'] = '';
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_user($this->properties);
    }

    function test_duplicate_username() {
        $this->having_user_id('user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Duplicate username');

        $this->operations->create_user($this->properties);
    }

    function test_invalid_password() {
        $this->moodle->shouldReceive('auth_plugin')->andReturn('manual');
        $this->moodle->shouldReceive('prevent_local_passwords')
            ->with('manual')->andReturn(false);
        $this->moodle->shouldReceive('check_password')
            ->with('abc123')->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Invalid password');

        $this->operations->create_user($this->properties);
    }
}

class UpdateUserTest extends OperationTest {

    function test() {
        $record = (object) array(
            'id' => 201,
            'username' => 'user2',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        );
        $this->having_user_record('user1', array('id' => 201, 'auth' => 'manual'));
        $this->having_user_id('user2', false);
        $this->moodle->shouldReceive('prevent_local_passwords')
            ->with('manual')->andReturn(false);
        $this->moodle->shouldReceive('check_password')
            ->with('abc123')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('update_record')
            ->with('user', Mockery::mustBe($record))->once()->ordered();
        $this->moodle->shouldReceive('update_password')
            ->with(201, 'abc123')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array(
            'username' => 'user2',
            'password' => 'abc123',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        ));
    }

    function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->update_user('user1', array('username' => 'user1'));
    }

    function test_blank_username() {
        $this->having_user_record('user1', array('id' => 201));
        $this->having_user_id('user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->update_user('user1', array('username' => ''));
    }

    function test_duplicate_username() {
        $this->having_user_record('user1', array('id' => 201));
        $this->having_user_id('user2', 202);
        $this->setExpectedException('local_secretaria_exception', 'Duplicate username');

        $this->operations->update_user('user1', array('username' => 'user2'));
    }

    function test_same_username() {
        $this->having_user_record('user1', array('id' => 201));
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('username' => 'user1'));
    }

    function test_password_only() {
        $this->having_user_record('user1', array('id' => 201, 'auth' => 'manual'));
        $this->moodle->shouldReceive('prevent_local_passwords')
            ->with('manual')->andReturn(false);
        $this->moodle->shouldReceive('check_password')
            ->with('abc123')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('update_password')
            ->with(201, 'abc123')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('password' => 'abc123'));
    }

    function test_invalid_password() {
        $this->having_user_record('user1', array('id' => 201, 'auth' => 'manual'));
        $this->moodle->shouldReceive('prevent_local_passwords')
            ->with('manual')->andReturn(false);
        $this->moodle->shouldReceive('check_password')
            ->with('abc123')->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Invalid password');

        $this->operations->update_user('user1', array('password' => 'abc123'));
    }

    function test_prevent_local_passwords() {
        $this->having_user_record('user1', array('id' => 201, 'auth' => 'msso'));
        $this->moodle->shouldReceive('prevent_local_passwords')
            ->with('msso')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('password' => 'abc123'));
    }

    function test_blank_firstname() {
        $this->having_user_record('user1', array('id' => 201));
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->update_user('user1', array('firstname' => ''));
    }

    function test_blank_lastname() {
        $this->having_user_record('user1', array('id' => 201));
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->update_user('user1', array('lastname' => ''));
    }

    function test_blank_email() {
        $record = (object) array('id' => 201, 'email' => '');
        $this->having_user_record('user1', array('id' => 201));
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('update_record')
            ->with('user', Mockery::mustBe($record))->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('email' => ''));
    }
}

class DeleteUserTest extends OperationTest {

    function test() {
        $record = (object) array(
            'id' => 201,
            'username' => 'user1',
            'password' => 'abc123',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        );
        $this->having_user_record('user1', $record);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('delete_user')
            ->with(Mockery::mustBe($record))
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->delete_user('user1');
    }

    function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->delete_user('user1');
    }
}

/* Courses */

class HasCourseTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $result = $this->operations->has_course('course1');
        $this->assertThat($result, $this->isTrue());
    }

    function test_no_course() {
        $result = $this->operations->has_course('course1');
        $this->assertThat($result, $this->isFalse());
    }
}

class GetCourseTest extends OperationTest {

    function test() {
        $record = (object) array(
            'id' => '101',
            'shortname' => 'course1',
            'fullname' => 'Course 1',
            'visible' => '1',
            'startdate' => (string) mktime(0, 0, 0, 9, 17, 2012),
        );
        $this->moodle->shouldReceive('get_course')
            ->with('course1')->andReturn($record);

        $result = $this->operations->get_course('course1');

        $this->assertThat($result, $this->identicalTo(array(
            'shortname' => 'course1',
            'fullname' => 'Course 1',
            'visible' => true,
            'startdate' => array('year' => 2012, 'month' => 9, 'day' => 17),
        )));
    }

    function test_unknown_course() {
        $this->moodle->shouldReceive('get_course')
            ->with('course1')->andReturn(false);

        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->get_course('course1');
    }
}

class UpdateCourseTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', false);
        $record = (object) array(
            'id' => 101,
            'shortname' => 'course2',
            'fullname' => 'Course 2',
            'visible' => 1,
            'startdate' => mktime(0, 0, 0, 9, 17, 2012),
        );

        $this->moodle->shouldReceive('update_course')
            ->with(Mockery::mustBe($record));

        $this->operations->update_course('course1', array(
            'shortname' => 'course2',
            'fullname' => 'Course 2',
            'visible' => true,
            'startdate' => array('year' => 2012, 'month' => 9, 'day' => 17),
        ));
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->update_course('course1', array());
    }

    function test_empty_properties() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('update_course')
            ->with(Mockery::mustBe((object) array('id' => 101)));

        $this->operations->update_course('course1', array());
    }

    function test_duplicate_shortname() {
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->setExpectedException('local_secretaria_exception', 'Duplicate shortname');

        $this->operations->update_course(
            'course1', array('shortname' => 'course2'));
    }

    function test_equal_shortname() {
        $this->having_course_id('course1', 101);
        $this->having_course_id('COURSE1', 101);
        $record = (object) array(
            'id' => 101,
            'shortname' => 'COURSE1'
        );
        $this->moodle->shouldReceive('update_course')
            ->with(Mockery::mustBe($record));

        $this->operations->update_course(
            'course1', array('shortname' => 'COURSE1'));
    }

    function test_blank_shortname() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->update_course('course1', array('shortname' => ''));
    }

    function test_blank_fullname() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->update_course('course1', array('fullname' => ''));
    }
}

class GetCoursesTest extends OperationTest {

    function test() {
        $records = array(
            (object) array('id' => 101, 'shortname' => 'course1'),
            (object) array('id' => 102, 'shortname' => 'course2'),
            (object) array('id' => 103, 'shortname' => 'course3'),
        );
        $this->moodle->shouldReceive('get_courses')
            ->with()->andReturn($records);

        $result = $this->operations->get_courses();

        $this->assertThat($result, $this->identicalTo(
            array('course1', 'course2', 'course3')
        ));
    }

    function test_no_courses() {
        $this->moodle->shouldReceive('get_courses')
            ->with()->andReturn(false);

        $result = $this->operations->get_courses();

        $this->assertThat($result, $this->identicalTo(array()));
    }
}

/* Enrolments */

class GetCcourseEnrolmentsTest extends OperationTest {

    function test() {
        $records = array(
            (object) array('id' => 301, 'user' => 'user1', 'role' => 'role1'),
            (object) array('id' => 302, 'user' => 'user2', 'role' => 'role2'),
        );

        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_role_assignments_by_course')
            ->with(101)->andReturn($records);

        $result = $this->operations->get_course_enrolments('course1');

        $this->assertThat($result, $this->identicalTo(array(
            array('user' => 'user1', 'role' => 'role1'),
            array('user' => 'user2', 'role' => 'role2'),
        )));
    }

    function test_no_enrolments() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_role_assignments_by_course')
            ->with(101)->andReturn(array());

        $result = $this->operations->get_course_enrolments('course1');

        $this->assertThat($result, $this->identicalTo(array()));
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_course_enrolments('course1');
    }
}

class GetUserEnrolmentsTest extends OperationTest {

    function test() {
        $records = array(
            (object) array('id' => 301, 'course' => 'course1', 'role' => 'role1'),
            (object) array('id' => 302, 'course' => 'course2', 'role' => 'role2'),
        );
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('get_role_assignments_by_user')
            ->with(201)->andReturn($records);

        $result = $this->operations->get_user_enrolments('user1');

        $this->assertThat($result, $this->identicalTo(array(
            array('course' => 'course1', 'role' => 'role1'),
            array('course' => 'course2', 'role' => 'role2'),
        )));
    }

    function test_no_enrolments() {
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('get_role_assignments_by_user')
            ->with(201)->andReturn(array());

        $result = $this->operations->get_user_enrolments('user1');

        $this->assertThat($result, $this->identicalTo(array()));
    }

    function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user_enrolments('user1');
    }
}

class EnrolUsersTest extends OperationTest {

    function test() {
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        for ($i = 1; $i <= 3; $i++) {
            $this->having_course_id('course' . $i, 200 + $i);
            $this->having_user_id('user' . $i, 300 + $i);
            $this->having_role_id('role' . $i, 400 + $i);
            $this->moodle->shouldReceive('role_assignment_exists')
                ->with(200 + $i, 300 + $i, 400 + $i)->andReturn(false);
            $this->moodle->shouldReceive('insert_role_assignment')
                ->with(200 + $i, 300 + $i, 400 + $i)->once()->ordered();
        }
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
            array('course' => 'course3', 'user' => 'user3', 'role' => 'role3'),
        ));
    }

    function test_duplicate_enrolment() {
        $this->having_course_id('course1', 201);
        $this->having_user_id('user1', 301);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('role_assignment_exists')
                ->with(201, 301, 401)->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_course() {
        $this->having_user_id('user1', 301);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 201);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_role() {
        $this->having_course_id('course1', 201);
        $this->having_user_id('user1', 301);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown role');
        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }
}

class UnenrolUsersTest extends OperationTest {

    function test() {
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        for ($i = 1; $i <= 3; $i++) {
            $this->having_course_id('course' . $i, 200 + $i);
            $this->having_user_id('user' . $i, 300 + $i);
            $this->having_role_id('role' . $i, 400 + $i);
            $this->moodle->shouldReceive('role_assignment_exists')
                ->with(200 + $i, 300 + $i, 400 + $i)->andReturn(false);
            $this->moodle->shouldReceive('delete_role_assignment')
                ->with(200 + $i, 300 + $i, 400 + $i)->once()->ordered();
        }
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
            array('course' => 'course3', 'user' => 'user3', 'role' => 'role3'),
        ));
    }

    function test_unknown_course() {
        $this->having_user_id('user1', 301);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
       ));
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 201);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    function test_unknown_role() {
        $this->having_course_id('course1', 201);
        $this->having_user_id('user1', 301);
        $this->moodle->shouldReceive('start_transaction')->once();
        $this->setExpectedException('local_secretaria_exception', 'Unknown role');

        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }
}

/* Groups */

class GetGroupsTest extends OperationTest {

    function test() {
        $records = array(
            (object) array('id' => 201, 'name' => 'group1', 'description' => 'first group'),
            (object) array('id' => 202, 'name' => 'group2', 'description' => 'second group'),
        );
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('groups_get_all_groups')->with(101)->andReturn($records);

        $result = $this->operations->get_groups('course1');

        $this->assertThat($result, $this->identicalTo(array(
            array('name' => 'group1', 'description' => 'first group'),
            array('name' => 'group2', 'description' => 'second group'),
        )));
    }

    function test_no_groups() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('groups_get_all_groups')->with(101)->andReturn(false);

        $result = $this->operations->get_groups('course1');

        $this->assertThat($result, $this->identicalTo(array()));
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_groups('course1');
    }
}

class CreateGroupTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_create_group')
            ->with(101, 'group1', 'Group 1')
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_group('course1', 'group1', 'Group 1');
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->create_group('course1', 'group1', 'Group 1');
    }

    function test_blank_name() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_group('course1', '', 'Group 1');
    }

    function test_duplicate_group() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);

        $this->setExpectedException('local_secretaria_exception', 'Duplicate group');
        $this->operations->create_group('course1', 'group1', 'Group 1');
    }
}

class DeleteGroupTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_delete_group')->with(201)
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->delete_group('course1', 'group1');
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->delete_group('course1', 'group1');
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->delete_group('course1', 'group1');
    }
}

class GetGroupMembersTest extends OperationTest {

    function test() {
        $records = array(
            (object) array('id' => 401, 'username' => 'user1'),
            (object) array('id' => 402, 'username' => 'user2'),
        );
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('get_group_members')
            ->with(201)->andReturn($records);

        $result = $this->operations->get_group_members('course1', 'group1');

        $this->assertThat($result, $this->identicalTo(array('user1', 'user2')));
    }

    function test_no_members() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('get_group_members')
            ->with(201)->andReturn(false);

        $result = $this->operations->get_group_members('course1', 'group1');

        $this->assertThat($result, $this->identicalTo(array()));
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_group_members('course1', 'group1');
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->get_group_members('course1', 'group1');
    }
}

class AddGroupMembersTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_user_id('user1', 401);
        $this->having_user_id('user2', 402);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_add_member')
            ->with(201, 401)->once()->ordered();
        $this->moodle->shouldReceive('groups_add_member')
            ->with(201, 402)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->add_group_members('course1', 'group1', array('user1', 'user2'));
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->add_group_members('course1', 'group1', array());
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->add_group_members('course1', 'group1', array());
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->add_group_members('course1', 'group1', array('user1'));
    }
}

class RemoveGroupMembersTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_user_id('user1', 401);
        $this->having_user_id('user2', 402);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_remove_member')
            ->with(201, 401)->once()->ordered();
        $this->moodle->shouldReceive('groups_remove_member')
            ->with(201, 402)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $result = $this->operations->remove_group_members(
            'course1', 'group1', array('user1', 'user2'));
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->remove_group_members('course1', 'group1', array());
    }

    function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->remove_group_members('course1', 'group1', array());
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->remove_group_members('course1', 'group1', array('user1'));
    }
}

class GetUserGroupsTest extends OperationTest {

    function test() {
        $this->having_user_id('user1', 201);
        $this->having_course_id('course1', 301);
        $records = array((object) array('id' => 401, 'name' => 'group1'),
                         (object) array('id' => 402, 'name' => 'group2'));
        $this->moodle->shouldReceive('groups_get_all_groups')->with(301, 201)->andReturn($records);

        $result = $this->operations->get_user_groups('user1', 'course1');

        $this->assertThat($result, $this->identicalTo(array('group1', 'group2')));
    }

    function test_unknown_user() {
        $this->having_group_id(101, 'group1', 201);
        $this->having_course_id('course1', 301);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->get_user_groups('user1', 'course1');
    }

    function test_unknown_course() {
        $this->having_user_id('user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->get_user_groups('user1', 'course1');
    }

    function test_no_groups() {
        $this->having_user_id('user1', 201);
        $this->having_course_id('course1', 301);
        $this->moodle->shouldReceive('groups_get_all_groups')->with(301, 201)->andReturn(false);

        $result = $this->operations->get_user_groups('user1', 'course1');

        $this->assertThat($result, $this->identicalTo(array()));
    }
}

/* Grades */

class GetCourseGradesTest extends OperationTest {

    function test() {
        $items = array(
            array(
                'id' => 401,
                'idnumber' => 'gi1',
                'type' => 'course',
                'module' => null,
                'name' => null,
                'sortorder' => 3,
                'grademin' => '1',
                'grademax' => '10',
                'gradepass' => '5',
            ),
            array(
                'id' => 402,
                'idnumber' => 'gi2',
                'type' => 'category',
                'module' => null,
                'name' => 'Category 1',
                'sortorder' => 1,
                'grademin' => 'E',
                'grademax' => 'A',
                'gradepass' => 'C',
            ),
            array(
                'id' => 403,
                'idnumber' => null,
                'type' => 'module',
                'module' => 'assignment',
                'name' => 'Assignment 1',
                'sortorder' => 2,
                'grademin' => '',
                'grademax' => '',
                'gradepass' => '',
            ),
        );
        $this->having_course_id('course1', 101);
        $this->having_user_id('user1', 301);
        $this->having_user_id('user2', 302);
        $this->moodle->shouldReceive('get_grade_items')->with(101)->andReturn($items);
        $this->moodle->shouldReceive('get_grades')->with(401, array(301, 302))
            ->andReturn(array(301 => '5.1',  302 => '5.2'));
        $this->moodle->shouldReceive('get_grades')->with(402, array(301, 302))
            ->andReturn(array(301 => '6.1', 302 => '6.2'));
        $this->moodle->shouldReceive('get_grades')->with(403, array(301, 302))
            ->andReturn(array(301 => '7.1', 302 => '7.2'));

        $result = $this->operations->get_course_grades('course1', array('user1', 'user2'));

        $this->assertThat($result, $this->identicalTo(array(
            array(
                'idnumber' => 'gi2',
                'type' => 'category',
                'module' => null,
                'name' => 'Category 1',
                'grademin' => 'E',
                'grademax' => 'A',
                'gradepass' => 'C',
                'grades' => array(
                    array('user' => 'user1', 'grade' => '6.1'),
                    array('user' => 'user2', 'grade' => '6.2'),
                ),
            ),
            array(
                'idnumber' => '',
                'type' => 'module',
                'module' => 'assignment',
                'name' => 'Assignment 1',
                'grademin' => '',
                'grademax' => '',
                'gradepass' => '',
                'grades' => array(
                    array('user' => 'user1', 'grade' => '7.1'),
                    array('user' => 'user2', 'grade' => '7.2'),
                ),
            ),
            array(
                'idnumber' => 'gi1',
                'type' => 'course',
                'module' => null,
                'name' => null,
                'grademin' => '1',
                'grademax' => '10',
                'gradepass' => '5',
                'grades' => array(
                    array('user' => 'user1', 'grade' => '5.1'),
                    array('user' => 'user2', 'grade' => '5.2'),
                ),
            ),
        )));
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_course_grades('course1', array('user1', 'user2'));
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_course_grades('course1', array('user1', 'user2'));
    }
}

class GetUserGradesTest extends OperationTest {

    function test() {
        $this->having_user_id('user1', 201);
        $this->having_course_id('course1', 301);
        $this->having_course_id('course2', 302);
        $this->moodle->shouldReceive('get_course_grade')
            ->with(201, 301)->andReturn('5.1');
        $this->moodle->shouldReceive('get_course_grade')
            ->with(201, 302)->andReturn('6.2');

        $result = $this->operations->get_user_grades(
            'user1', array('course1', 'course2'));

        $this->assertThat($result, $this->identicalTo(array(
            array('course' => 'course1', 'grade' => '5.1'),
            array('course' => 'course2', 'grade' => '6.2'),
        )));
    }

    function test_unknown_course() {
        $this->having_user_id('user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_user_grades('user1', array('course1', 'course2'));
    }

    function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user_grades('user1', array());
    }
}

/* Assignments */

class GetAssignmentsTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $records = array(
            (object) array(
                'id' => '201',
                'name' => 'Assignment 1',
                'idnumber' => 'A1',
                'opentime' => '1234567891',
                'closetime' => '1234567892',
            ),
            (object) array(
                'id' => '202',
                'name' => 'Assignment 2',
                'idnumber' => 'A2',
                'opentime' => '0',
                'closetime' => '1234567893',
            ),
            (object) array(
                'id' => '203',
                'name' => 'Assignment 3',
                'idnumber' => null,
                'opentime' => '1234567894',
                'closetime' => '0',
            ),
        );
        $this->moodle->shouldReceive('get_assignments')->with(101)->andReturn($records);

        $result = $this->operations->get_assignments('course1');

        $this->assertThat($result, $this->identicalTo(array(
            array('idnumber' => 'A1',
                  'name' => 'Assignment 1',
                  'opentime' => 1234567891,
                  'closetime' => 1234567892),
            array('idnumber' => 'A2',
                  'name' => 'Assignment 2',
                  'opentime' => null,
                  'closetime' => 1234567893),
            array('idnumber' => '',
                  'name' => 'Assignment 3',
                  'opentime' => 1234567894,
                  'closetime' => null),
        )));
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_assignments('course1');
    }
}

class GetAssignmentSubmissionsTest extends OperationTest {

    function test() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_assignment_id')->with(101, 'A1')->andReturn(201);
        $records = array(
            (object) array(
                'id' => '301',
                'user' => 'student1',
                'grader' => 'teacher1',
                'timesubmitted' => '1234567891',
                'timegraded' => '1234567892',
                'numfiles' => '1',
            ),
            (object) array(
                'id' => '302',
                'user' => 'student2',
                'grader' => 'teacher2',
                'timesubmitted' => '1234567893',
                'timegraded' => '1234567894',
                'numfiles' => '2',
            ),
            (object) array(
                'id' => '301',
                'user' => 'student3',
                'grader' => null,
                'timesubmitted' => '1234567895',
                'timegraded' => null,
                'numfiles' => '0',
            ),
        );
        $this->moodle->shouldReceive('get_assignment_submissions')->with(201)->andReturn($records);

        $result = $this->operations->get_assignment_submissions('course1', 'A1');

        $this->assertThat($result, $this->identicalTo(array(
            array('user' => 'student1',
                  'grader' => 'teacher1',
                  'timesubmitted' => 1234567891,
                  'timegraded' => 1234567892,
                  'numfiles' => 1),
            array('user' => 'student2',
                  'grader' => 'teacher2',
                  'timesubmitted' => 1234567893,
                  'timegraded' => 1234567894,
                  'numfiles' => 2),
            array('user' => 'student3',
                  'grader' => null,
                  'timesubmitted' => 1234567895,
                  'timegraded' => null,
                  'numfiles' => 0),
        )));
    }

   function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_assignment_submissions('course1', 'A1');
    }

}


/* Surveys */

class GetSurveysTest extends OperationTest {

    function test() {
        $records = array(
            (object) array('id' => 201, 'name' => 'Survey 1',
                           'idnumber' => 'S1', 'realm' => 'private'),
            (object) array('id' => 202, 'name' => 'Survey 2',
                           'idnumber' => 'S2', 'realm' => 'public'),
            (object) array('id' => 203, 'name' => 'Survey 3',
                           'idnumber' => 'S3', 'realm' => 'template'),
            (object) array('id' => 204, 'name' => 'Survey 4',
                           'idnumber' => null, 'realm' => 'template'),
        );
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_surveys')->with(101)->andReturn($records);

        $result = $this->operations->get_surveys('course1');

        $this->assertThat($result, $this->identicalTo(array(
            array('idnumber' => 'S1', 'name' => 'Survey 1', 'type' => 'private'),
            array('idnumber' => 'S2', 'name' => 'Survey 2', 'type' => 'public'),
            array('idnumber' => 'S3', 'name' => 'Survey 3', 'type' => 'template'),
            array('idnumber' => '', 'name' => 'Survey 4', 'type' => 'template'),
        )));
    }

    function test_blank_idnumber() {
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');
        $this->operations->get_assignment_submissions('course1', '');
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_surveys('course1');
    }

    function test_unknown_assignment() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_assignment_id')->with(101, 'A1')->andReturn(false);

        $this->setExpectedException('local_secretaria_exception', 'Unknown assignment');

        $this->operations->get_assignment_submissions('course1', 'A1');
    }
}

class CreateSurveyTest extends OperationTest {

    function setUp() {
        parent::setUp();
        $this->properties = array(
            'course' => 'course2',
            'section' => 7,
            'idnumber' => 'S2',
            'name' => 'Survey 2',
            'summary' => 'Summary 2',
            'template' => array(
                'course' => 'course1',
                'idnumber' => 'S1',
            ),
        );
    }

    function test() {
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(101, 'S1')->andReturn(201);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(false);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_survey')
            ->with(102, 7, 'S2', 'Survey 2', 'Summary 2', 0, 0, 201)
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_survey($this->properties);
    }

    function test_opendate() {
        $this->properties['opendate'] = array('year' => 2012, 'month' => 10, 'day' => 22);
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(101, 'S1')->andReturn(201);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(false);
        $this->moodle->shouldReceive('make_timestamp')->with(2012, 10, 22)->andReturn(1234567890);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_survey')
            ->with(102, 7, 'S2', 'Survey 2', 'Summary 2', 1234567890, 0, 201)
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_survey($this->properties);
    }

    function test_closedate() {
        $this->properties['closedate'] = array('year' => 2012, 'month' => 10, 'day' => 22);
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(101, 'S1')->andReturn(201);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(false);
        $this->moodle->shouldReceive('make_timestamp')
            ->with(2012, 10, 22, 23, 55)->andReturn(1234567890);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_survey')
            ->with(102, 7, 'S2', 'Survey 2', 'Summary 2', 0, 1234567890, 201)
            ->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_survey($this->properties);
    }

    function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->create_survey($this->properties);
    }

    function test_unknown_section() {
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Unknown section');

        $this->operations->create_survey($this->properties);
    }

    function test_blank_idnumber() {
        $this->properties['idnumber'] = '';
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_survey($this->properties);
    }

    function test_duplicate_idnumber() {
        $this->properties['idnumber'] = 'S2';
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(202);
        $this->setExpectedException('local_secretaria_exception', 'Duplicate idnumber');

        $this->operations->create_survey($this->properties);
    }

    function test_blank_name() {
        $this->properties['name'] = '';
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_survey($this->properties);
    }

    function test_blank_summary() {
        $this->properties['summary'] = '';
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_survey($this->properties);
    }

    function test_blank_template_course() {
        $this->properties['template']['course'] = '';
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_survey($this->properties);
    }

    function test_blank_template_idnumber() {
        $this->properties['template']['idnumber'] = '';
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->create_survey($this->properties);
    }

    function test_unknown_template_course() {
        $this->having_course_id('course1', false);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->create_survey($this->properties);
    }

    function test_unknown_survey() {
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(101, 'S1')->andReturn(false);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(false);

        $this->setExpectedException('local_secretaria_exception', 'Unknown survey');

        $this->operations->create_survey($this->properties);
    }
}

/* Misc */

class SendMailTest extends OperationTest {

    function setUp() {
        parent::setUp();
        $this->message = array(
            'sender' => 'user1',
            'course' => 'course1',
            'subject' => 'subject text',
            'content' => 'content text',
            'to' => array('user2'),
        );
    }

    function test() {
        $this->message['cc'] = array('user3', 'user4');
        $this->message['bcc'] = array('user5');
        $this->having_course_id('course1', 201);
        for ($i = 1; $i <= 5; $i++) {
            $this->having_user_id('user' . $i, 300 + $i);
        }
        $this->moodle->shouldReceive('send_mail')
            ->with(301, 201, 'subject text', 'content text',
                   array(302), array(303, 304), array(305))
            ->once();

        $this->operations->send_mail($this->message);
    }

    function test_unknown_course() {
        $this->having_user_id('user1', 301);
        $this->having_user_id('user2', 302);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->send_mail($this->message);
    }

    function test_unknown_user() {
        $this->having_course_id('course1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->send_mail($this->message);
    }

    function test_duplicate_user() {
        $this->message['cc'] = array('user1');
        $this->having_course_id('course1', 201);
        $this->having_user_id('user1', 301);
        $this->having_user_id('user2', 302);

        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->send_mail($this->message);
    }

    function test_no_recipient() {
        $this->message['to'] = array();
        $this->having_course_id('course1', 201);
        $this->having_user_id('user1', 301);

        $this->setExpectedException('local_secretaria_exception', 'Invalid parameters');

        $this->operations->send_mail($this->message);
    }
}
