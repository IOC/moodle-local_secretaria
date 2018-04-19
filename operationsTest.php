<?php

require_once('operations.php');

$loader = new Mockery\Loader;
$loader->register();

Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

date_default_timezone_set('Europe/Madrid');

abstract class OperationTest extends PHPUnit_Framework_TestCase {

    protected $moodle;
    protected $operations;

    public function setUp() {
        $this->moodle = Mockery::mock('local_secretaria_moodle');
        $this->moodle->shouldReceive('get_course_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_group_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_role_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_user_id')->andReturn(false)->byDefault();
        $this->moodle->shouldReceive('get_user')->andReturn(false)->byDefault();
        $this->operations = new local_secretaria_operations($this->moodle);
    }

    public function tearDown() {
        Mockery::close();
    }

    protected function having_course_id($shortname, $courseid) {
        $this->moodle->shouldReceive('get_course_id')->with($shortname)->andReturn($courseid);
    }

    protected function having_group_id($courseid, $groupname, $groupid) {
        $this->moodle->shouldReceive('get_group_id')->with($courseid, $groupname)->andReturn($groupid);
    }

    protected function having_role_id($shortname, $roleid) {
        $this->moodle->shouldReceive('get_role_id')->with($shortname)->andReturn($roleid);
    }

    protected function having_user_id($username, $userid) {
        $this->moodle->shouldReceive('get_user_id')->with($username)->andReturn($userid);
    }

    protected function having_user($username, $record) {
        $this->moodle->shouldReceive('get_user')->with($username)->andReturn((object) $record);
    }
}

/* Users */

class GetUserTest extends OperationTest {

    public function setUp() {
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

    public function test() {
        $this->having_user('user', $this->record);
        $this->moodle->shouldReceive('user_picture_url')->with(201)->andReturn('http://example.org/user/pix.php/201/f1.jpg');

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

    public function test_no_picture() {
        $this->record->picture = 0;
        $this->having_user('user', $this->record);
        $this->moodle->shouldReceive('user_picture_url')->with(201)->andReturn('http://example.org/user/pix.php/201/f1.jpg');

        $result = $this->operations->get_user('user');

        $this->assertThat($result['picture'], $this->isNull());
    }

    public function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user('user');
    }
}

class GetUserLastAccessTest extends OperationTest {

    public function test() {
        $this->having_user_id('user1', 201);
        $this->having_user_id('user2', 202);
        $this->having_user_id('user3', 203);
        $records = array(
            (object) array('id' => 301, 'userid' => 201, 'course' => 'CP1', 'time' => 1234567891),
            (object) array('id' => 302, 'userid' => 201, 'course' => 'CP2', 'time' => 1234567892),
            (object) array('id' => 303, 'userid' => 202, 'course' => 'CP1', 'time' => 1234567893),
        );
        $this->moodle->shouldReceive('get_user_lastaccess')->with(array(201, 202, 203))->andReturn($records);

        $result = $this->operations->get_user_lastaccess(array('user1', 'user2', 'user3'));

        $this->assertThat($result, $this->identicalTo(array(
            array('user' => 'user1', 'course' => 'CP1', 'time' => 1234567891),
            array('user' => 'user1', 'course' => 'CP2', 'time' => 1234567892),
            array('user' => 'user2', 'course' => 'CP1', 'time' => 1234567893),
        )));
    }

    public function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user_lastaccess(array('user1'));
    }
}

class CreateUserTest extends OperationTest {

    public function setUp() {
        parent::setUp();
        $this->properties = array(
            'username' => 'user1',
            'firstname' => 'First',
            'lastname' => 'Last',
            'email' => 'user1@example.org',
            'password' => 'abc123',
        );
    }

    public function test() {
        $this->moodle->shouldReceive('auth_plugin')->with()->andReturn('manual');
        $this->moodle->shouldReceive('prevent_local_passwords')->with('manual')->andReturn(false);
        $this->moodle->shouldReceive('check_password')->with('abc123')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_user')->with('manual', 'user1', 'abc123', 'First', 'Last', 'user1@example.org')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_user($this->properties);
    }

    public function test_prevent_local_passwords() {
        $this->moodle->shouldReceive('auth_plugin')->andReturn('msso');
        $this->moodle->shouldReceive('prevent_local_passwords')->with('msso')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_user')->with('msso', 'user1', false, 'First', 'Last', 'user1@example.org')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_user($this->properties);
    }

    public function test_no_email() {
        unset($this->properties['email']);
        $this->moodle->shouldReceive('auth_plugin')->with()->andReturn('manual');
        $this->moodle->shouldReceive('prevent_local_passwords')->with('manual')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_user')->with('manual', 'user1', false, 'First', 'Last', '')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_user($this->properties);
    }

    public function test_blank_username() {
        $this->properties['username'] = '';
        $this->setExpectedException('local_secretaria_exception', 'Invalid username or firstname or lastname');

        $this->operations->create_user($this->properties);
    }

    public function test_blank_firstname() {
        $this->properties['firstname'] = '';
        $this->setExpectedException('local_secretaria_exception', 'Invalid username or firstname or lastname');

        $this->operations->create_user($this->properties);
    }

    public function test_blank_lastname() {
        $this->properties['lastname'] = '';
        $this->setExpectedException('local_secretaria_exception', 'Invalid username or firstname or lastname');

        $this->operations->create_user($this->properties);
    }

    public function test_duplicate_username() {
        $this->having_user_id('user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Duplicate username');

        $this->operations->create_user($this->properties);
    }

    public function test_invalid_password() {
        $this->moodle->shouldReceive('auth_plugin')->andReturn('manual');
        $this->moodle->shouldReceive('prevent_local_passwords')->with('manual')->andReturn(false);
        $this->moodle->shouldReceive('check_password')->with('abc123')->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Invalid password');

        $this->operations->create_user($this->properties);
    }
}

class UpdateUserTest extends OperationTest {

    public function test() {
        $record = (object) array(
            'id' => 201,
            'username' => 'user2',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        );
        $this->having_user('user1', array('id' => 201, 'auth' => 'manual'));
        $this->having_user_id('user2', false);
        $this->moodle->shouldReceive('prevent_local_passwords')->with('manual')->andReturn(false);
        $this->moodle->shouldReceive('check_password')->with('abc123')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('update_user')->with(Mockery::mustBe($record))->once()->ordered();
        $this->moodle->shouldReceive('update_password')->with(201, 'abc123')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array(
            'username' => 'user2',
            'password' => 'abc123',
            'firstname' => 'First2',
            'lastname' => 'Last2',
            'email' => 'user2@example.org',
        ));
    }

    public function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->update_user('user1', array('username' => 'user1'));
    }

    public function test_blank_username() {
        $this->having_user('user1', array('id' => 201));
        $this->having_user_id('user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Empty username');

        $this->operations->update_user('user1', array('username' => ''));
    }

    public function test_duplicate_username() {
        $this->having_user('user1', array('id' => 201));
        $this->having_user_id('user2', 202);
        $this->setExpectedException('local_secretaria_exception', 'Duplicate username');

        $this->operations->update_user('user1', array('username' => 'user2'));
    }

    public function test_same_username() {
        $record = (object) array('id' => 201, 'username' => 'USER1');
        $this->having_user('user1', array('id' => 201));
        $this->having_user_id('USER1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('update_user')->with(Mockery::mustBe($record))->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('username' => 'USER1'));
    }

    public function test_password_only() {
        $this->having_user('user1', array('id' => 201, 'auth' => 'manual'));
        $this->moodle->shouldReceive('prevent_local_passwords')->with('manual')->andReturn(false);
        $this->moodle->shouldReceive('check_password')->with('abc123')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('update_password')->with(201, 'abc123')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('password' => 'abc123'));
    }

    public function test_invalid_password() {
        $this->having_user('user1', array('id' => 201, 'auth' => 'manual'));
        $this->moodle->shouldReceive('prevent_local_passwords')->with('manual')->andReturn(false);
        $this->moodle->shouldReceive('check_password')->with('abc123')->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Invalid password');

        $this->operations->update_user('user1', array('password' => 'abc123'));
    }

    public function test_prevent_local_passwords() {
        $this->having_user('user1', array('id' => 201, 'auth' => 'msso'));
        $this->moodle->shouldReceive('prevent_local_passwords')->with('msso')->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('password' => 'abc123'));
    }

    public function test_blank_firstname() {
        $this->having_user('user1', array('id' => 201));
        $this->setExpectedException('local_secretaria_exception', 'Empty firstname');

        $this->operations->update_user('user1', array('firstname' => ''));
    }

    public function test_blank_lastname() {
        $this->having_user('user1', array('id' => 201));
        $this->setExpectedException('local_secretaria_exception', 'Empty lastname');

        $this->operations->update_user('user1', array('lastname' => ''));
    }

    public function test_blank_email() {
        $record = (object) array('id' => 201, 'email' => '');
        $this->having_user('user1', array('id' => 201));
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('update_user')->with(Mockery::mustBe($record))->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->update_user('user1', array('email' => ''));
    }
}

class DeleteUserTest extends OperationTest {

    public function test() {
        $this->having_user_id('user1', 101);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('delete_user')->with(101)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->delete_user('user1');
    }

    public function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->delete_user('user1');
    }
}

class GetUsersTest extends OperationTest {

    public function test() {
        $records = array(
            (object) array(
                    'id' => 101,
                    'username' => 'user1',
                    'firstname' => 'User',
                    'lastname' => '1',
                    'email' => 'user1@example.org',
                    'picture' => '1',
                    'lastaccess' => '1234567890'
            ),
            (object) array(
                    'id' => 102,
                    'username' => 'user2',
                    'firstname' => 'User',
                    'lastname' => '2',
                    'email' => 'user2@example.org',
                    'picture' => '2',
                    'lastaccess' => '1234567890'
            ),
            (object) array(
                    'id' => 103,
                    'username' => 'user3',
                    'firstname' => 'User',
                    'lastname' => '3',
                    'email' => 'user3@example.org',
                    'picture' => '3',
                    'lastaccess' => '1234567890'
            ),
        );
        $users = array(
            'user1',
            'user2',
            'user3',
        );
        $this->moodle->shouldReceive('get_users')->with($users)->andReturn($records);
        $this->moodle->shouldReceive('user_picture_url')->with(101)->andReturn('http://example.org/user/pix.php/101/f1.jpg');
        $this->moodle->shouldReceive('user_picture_url')->with(102)->andReturn('http://example.org/user/pix.php/102/f1.jpg');
        $this->moodle->shouldReceive('user_picture_url')->with(103)->andReturn('http://example.org/user/pix.php/103/f1.jpg');

        $result = $this->operations->get_users($users);

        $this->assertThat($result, $this->identicalTo(
                array(
                    array(
                        'username' => 'user1',
                        'firstname' => 'User',
                        'lastname' => '1',
                        'email' => 'user1@example.org',
                        'picture' => 'http://example.org/user/pix.php/101/f1.jpg',
                        'lastaccess' => '1234567890'
                    ),
                    array(
                        'username' => 'user2',
                        'firstname' => 'User',
                        'lastname' => '2',
                        'email' => 'user2@example.org',
                        'picture' => 'http://example.org/user/pix.php/102/f1.jpg',
                        'lastaccess' => '1234567890'
                    ),
                    array(
                        'username' => 'user3',
                        'firstname' => 'User',
                        'lastname' => '3',
                        'email' => 'user3@example.org',
                        'picture' => 'http://example.org/user/pix.php/103/f1.jpg',
                        'lastaccess' => '1234567890'
                    ),
                )
        ));
    }

    public function test_no_users() {
        $users = array(
                    (object) array(
                        'id' => 101,
                        'username' => 'user1',
                        'firstname' => 'User',
                        'lastname' => '1',
                        'email' => 'user1@example.org',
                        'picture' => '1',
                        'lastaccess' => '1234567890'
                    ),
                    (object) array(
                        'id' => 101,
                        'username' => 'user2',
                        'firstname' => 'User',
                        'lastname' => '2',
                        'email' => 'user2@example.org',
                        'picture' => '2',
                        'lastaccess' => '1234567890'
                    ),
                    (object) array(
                        'id' => 101,
                        'username' => 'user3',
                        'firstname' => 'User',
                        'lastname' => '3',
                        'email' => 'user3@example.org',
                        'picture' => '3',
                        'lastaccess' => '1234567890'
                    ),
        );
        $this->moodle->shouldReceive('get_users')->with($users)->andReturn(false);

        $result = $this->operations->get_users($users);

        $this->assertThat($result, $this->identicalTo(array()));
    }
}

/* Courses */

class HasCourseTest extends OperationTest {

    public function test() {
        $this->having_course_id('course1', 101);
        $result = $this->operations->has_course('course1');
        $this->assertThat($result, $this->isTrue());
    }

    public function test_no_course() {
        $result = $this->operations->has_course('course1');
        $this->assertThat($result, $this->isFalse());
    }
}

class GetCourseTest extends OperationTest {

    public function test() {
        $record = (object) array(
            'id' => '101',
            'shortname' => 'course1',
            'fullname' => 'Course 1',
            'visible' => '1',
            'startdate' => (string) mktime(0, 0, 0, 9, 17, 2012),
        );
        $this->moodle->shouldReceive('get_course')->with('course1')->andReturn($record);

        $result = $this->operations->get_course('course1');

        $this->assertThat($result, $this->identicalTo(array(
            'shortname' => 'course1',
            'fullname' => 'Course 1',
            'visible' => true,
            'startdate' => array('year' => 2012, 'month' => 9, 'day' => 17),
        )));
    }

    public function test_unknown_course() {
        $this->moodle->shouldReceive('get_course')->with('course1')->andReturn(false);

        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->get_course('course1');
    }
}

class UpdateCourseTest extends OperationTest {

    public function test() {
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', false);
        $record = (object) array(
            'id' => 101,
            'shortname' => 'course2',
            'fullname' => 'Course 2',
            'visible' => 1,
            'startdate' => mktime(0, 0, 0, 9, 17, 2012),
        );

        $this->moodle->shouldReceive('update_course')->with(Mockery::mustBe($record));

        $this->operations->update_course('course1', array(
            'shortname' => 'course2',
            'fullname' => 'Course 2',
            'visible' => true,
            'startdate' => array('year' => 2012, 'month' => 9, 'day' => 17),
        ));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->update_course('course1', array());
    }

    public function test_empty_properties() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('update_course')->with(Mockery::mustBe((object) array('id' => 101)));

        $this->operations->update_course('course1', array());
    }

    public function test_duplicate_shortname() {
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->setExpectedException('local_secretaria_exception', 'Duplicate shortname');

        $this->operations->update_course(
            'course1', array('shortname' => 'course2'));
    }

    public function test_equal_shortname() {
        $this->having_course_id('course1', 101);
        $this->having_course_id('COURSE1', 101);
        $record = (object) array(
            'id' => 101,
            'shortname' => 'COURSE1'
        );
        $this->moodle->shouldReceive('update_course')->with(Mockery::mustBe($record));

        $this->operations->update_course(
            'course1', array('shortname' => 'COURSE1'));
    }

    public function test_blank_shortname() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Empty shortname');

        $this->operations->update_course('course1', array('shortname' => ''));
    }

    public function test_blank_fullname() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Empty fullname');

        $this->operations->update_course('course1', array('fullname' => ''));
    }
}

class GetCoursesTest extends OperationTest {

    public function test() {
        $records = array(
            (object) array('id' => 101, 'shortname' => 'course1'),
            (object) array('id' => 102, 'shortname' => 'course2'),
            (object) array('id' => 103, 'shortname' => 'course3'),
        );
        $this->moodle->shouldReceive('get_courses')->with()->andReturn($records);

        $result = $this->operations->get_courses();

        $this->assertThat($result, $this->identicalTo(
            array('course1', 'course2', 'course3')
        ));
    }

    public function test_no_courses() {
        $this->moodle->shouldReceive('get_courses')->with()->andReturn(false);

        $result = $this->operations->get_courses();

        $this->assertThat($result, $this->identicalTo(array()));
    }
}

class GetCourseUrlTest extends OperationTest {

    public function test() {
        $url = 'http://example.org/course/view.php?id=101';

        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_course_url')->with(101)->andReturn($url);
        $result = $this->operations->get_course_url('course1');
        $this->assertThat($result, $this->identicalTo($url));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_course_url('course1');
    }
}

/* Enrolments */

class GetCcourseEnrolmentsTest extends OperationTest {

    public function test() {
        $records = array(
            (object) array('id' => 301, 'user' => 'user1', 'role' => 'role1'),
            (object) array('id' => 302, 'user' => 'user2', 'role' => 'role2'),
        );

        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_role_assignments_by_course')->with(101)->andReturn($records);

        $result = $this->operations->get_course_enrolments('course1');

        $this->assertThat($result, $this->identicalTo(array(
            array('user' => 'user1', 'role' => 'role1'),
            array('user' => 'user2', 'role' => 'role2'),
        )));
    }

    public function test_no_enrolments() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_role_assignments_by_course')->with(101)->andReturn(array());

        $result = $this->operations->get_course_enrolments('course1');

        $this->assertThat($result, $this->identicalTo(array()));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_course_enrolments('course1');
    }
}

class GetUserEnrolmentsTest extends OperationTest {

    public function test() {
        $records = array(
            (object) array('id' => 301, 'course' => 'course1', 'role' => 'role1'),
            (object) array('id' => 302, 'course' => 'course2', 'role' => 'role2'),
        );
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('get_role_assignments_by_user')->with(201)->andReturn($records);

        $result = $this->operations->get_user_enrolments('user1');

        $this->assertThat($result, $this->identicalTo(array(
            array('course' => 'course1', 'role' => 'role1'),
            array('course' => 'course2', 'role' => 'role2'),
        )));
    }

    public function test_no_enrolments() {
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('get_role_assignments_by_user')->with(201)->andReturn(array());

        $result = $this->operations->get_user_enrolments('user1');

        $this->assertThat($result, $this->identicalTo(array()));
    }

    public function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user_enrolments('user1');
    }
}

class EnrolUsersTest extends OperationTest {

    public function test() {
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        for ($i = 1; $i <= 3; $i++) {
            $this->having_course_id('course' . $i, 200 + $i);
            $this->having_user_id('user' . $i, 300 + $i);
            $this->having_role_id('role' . $i, 400 + $i);
            $this->moodle->shouldReceive('role_assignment_exists')->with(200 + $i, 300 + $i, 400 + $i)->andReturn(false);
            $this->moodle->shouldReceive('insert_role_assignment')->with(200 + $i, 300 + $i, 400 + $i, false)->once()->ordered();
        }
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
            array('course' => 'course3', 'user' => 'user3', 'role' => 'role3'),
        ));
    }

    public function test_duplicate_enrolment() {
        $this->having_course_id('course1', 201);
        $this->having_user_id('user1', 301);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('role_assignment_exists')->with(201, 301, 401)->andReturn(true);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    public function test_unknown_course() {
        $this->having_user_id('user1', 301);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    public function test_unknown_user() {
        $this->having_course_id('course1', 201);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->enrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    public function test_unknown_role() {
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

    public function test() {
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        for ($i = 1; $i <= 3; $i++) {
            $this->having_course_id('course' . $i, 200 + $i);
            $this->having_user_id('user' . $i, 300 + $i);
            $this->having_role_id('role' . $i, 400 + $i);
            $this->moodle->shouldReceive('role_assignment_exists')->with(200 + $i, 300 + $i, 400 + $i)->andReturn(false);
            $this->moodle->shouldReceive('delete_role_assignment')->with(200 + $i, 300 + $i, 400 + $i)->once()->ordered();
        }
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
            array('course' => 'course2', 'user' => 'user2', 'role' => 'role2'),
            array('course' => 'course3', 'user' => 'user3', 'role' => 'role3'),
        ));
    }

    public function test_unknown_course() {
        $this->having_user_id('user1', 301);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();

        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    public function test_unknown_user() {
        $this->having_course_id('course1', 201);
        $this->having_role_id('role1', 401);
        $this->moodle->shouldReceive('start_transaction')->once();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->unenrol_users(array(
            array('course' => 'course1', 'user' => 'user1', 'role' => 'role1'),
        ));
    }

    public function test_unknown_role() {
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

    public function test() {
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

    public function test_no_groups() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('groups_get_all_groups')->with(101)->andReturn(false);

        $result = $this->operations->get_groups('course1');

        $this->assertThat($result, $this->identicalTo(array()));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_groups('course1');
    }
}

class CreateGroupTest extends OperationTest {

    public function test() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_create_group')->with(101, 'group1', 'Group 1')->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_group('course1', 'group1', 'Group 1');
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->create_group('course1', 'group1', 'Group 1');
    }

    public function test_blank_name() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Empty group name');

        $this->operations->create_group('course1', '', 'Group 1');
    }

    public function test_duplicate_group() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);

        $this->setExpectedException('local_secretaria_exception', 'Duplicate group');
        $this->operations->create_group('course1', 'group1', 'Group 1');
    }
}

class DeleteGroupTest extends OperationTest {

    public function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_delete_group')->with(201)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->delete_group('course1', 'group1');
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->delete_group('course1', 'group1');
    }

    public function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->delete_group('course1', 'group1');
    }
}

class GetGroupMembersTest extends OperationTest {

    public function test() {
        $records = array(
            (object) array('id' => 401, 'username' => 'user1'),
            (object) array('id' => 402, 'username' => 'user2'),
        );
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('get_group_members')->with(201)->andReturn($records);

        $result = $this->operations->get_group_members('course1', 'group1');

        $this->assertThat($result, $this->identicalTo(array('user1', 'user2')));
    }

    public function test_no_members() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('get_group_members')->with(201)->andReturn(false);

        $result = $this->operations->get_group_members('course1', 'group1');

        $this->assertThat($result, $this->identicalTo(array()));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_group_members('course1', 'group1');
    }

    public function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->get_group_members('course1', 'group1');
    }
}

class AddGroupMembersTest extends OperationTest {

    public function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_user_id('user1', 401);
        $this->having_user_id('user2', 402);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_add_member')->with(201, 401)->once()->ordered();
        $this->moodle->shouldReceive('groups_add_member')->with(201, 402)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->add_group_members('course1', 'group1', array('user1', 'user2'));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->add_group_members('course1', 'group1', array());
    }

    public function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->add_group_members('course1', 'group1', array());
    }

    public function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->add_group_members('course1', 'group1', array('user1'));
    }
}

class RemoveGroupMembersTest extends OperationTest {

    public function test() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->having_user_id('user1', 401);
        $this->having_user_id('user2', 402);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('groups_remove_member')->with(201, 401)->once()->ordered();
        $this->moodle->shouldReceive('groups_remove_member')->with(201, 402)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $result = $this->operations->remove_group_members(
            'course1', 'group1', array('user1', 'user2'));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->remove_group_members('course1', 'group1', array());
    }

    public function test_unknown_group() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown group');
        $this->operations->remove_group_members('course1', 'group1', array());
    }

    public function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->having_group_id(101, 'group1', 201);
        $this->moodle->shouldReceive('start_transaction')->once();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->remove_group_members('course1', 'group1', array('user1'));
    }
}

class GetUserGroupsTest extends OperationTest {

    public function test() {
        $this->having_user_id('user1', 201);
        $this->having_course_id('course1', 301);
        $records = array((object) array('id' => 401, 'name' => 'group1'),
                         (object) array('id' => 402, 'name' => 'group2'));
        $this->moodle->shouldReceive('groups_get_all_groups')->with(301, 201)->andReturn($records);

        $result = $this->operations->get_user_groups('user1', 'course1');

        $this->assertThat($result, $this->identicalTo(array('group1', 'group2')));
    }

    public function test_unknown_user() {
        $this->having_group_id(101, 'group1', 201);
        $this->having_course_id('course1', 301);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->get_user_groups('user1', 'course1');
    }

    public function test_unknown_course() {
        $this->having_user_id('user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->get_user_groups('user1', 'course1');
    }

    public function test_no_groups() {
        $this->having_user_id('user1', 201);
        $this->having_course_id('course1', 301);
        $this->moodle->shouldReceive('groups_get_all_groups')->with(301, 201)->andReturn(false);

        $result = $this->operations->get_user_groups('user1', 'course1');

        $this->assertThat($result, $this->identicalTo(array()));
    }
}

/* Grades */

class GetCourseGradesTest extends OperationTest {

    public $items;

    public function setUp() {
        parent::setUp();

        $this->items = array(
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
                'hidden' => 0,
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
                'hidden' => 0,
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
                'hidden' => 1,
            ),
        );
    }

    public function test() {
        $this->having_course_id('course1', 101);
        $this->having_user_id('user1', 301);
        $this->having_user_id('user2', 302);
        $this->moodle->shouldReceive('get_grade_items')->with(101)->andReturn($this->items);
        $this->moodle->shouldReceive('get_grades')->with(401, array(301, 302))->andReturn(array(301 => array('5.1', 'teacher1'),  302 => array('5.2', 'teacher1')));
        $this->moodle->shouldReceive('get_grades')->with(402, array(301, 302))->andReturn(array(301 => array('6.1', 'teacher2'), 302 => array('6.2', 'teacher2')));
        $this->moodle->shouldReceive('get_grades')->with(403, array(301, 302))->andReturn(array(301 => array('7.1', 'teacher3'), 302 => array('7.2', 'teacher3')));

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
                'hidden' => 0,
                'grades' => array(
                    array('user' => 'user1', 'grade' => '6.1', 'grader' => 'teacher2'),
                    array('user' => 'user2', 'grade' => '6.2', 'grader' => 'teacher2'),
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
                'hidden' => 1,
                'grades' => array(
                    array('user' => 'user1', 'grade' => '7.1', 'grader' => 'teacher3'),
                    array('user' => 'user2', 'grade' => '7.2', 'grader' => 'teacher3'),
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
                'hidden' => 0,
                'grades' => array(
                    array('user' => 'user1', 'grade' => '5.1', 'grader' => 'teacher1'),
                    array('user' => 'user2', 'grade' => '5.2', 'grader' => 'teacher1'),
                ),
            ),
        )));
    }

    public function test_no_users() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_grade_items')->with(101)->andReturn($this->items);

        $result = $this->operations->get_course_grades('course1', array());

        $this->assertThat($result, $this->identicalTo(array(
            array(
                'idnumber' => 'gi2',
                'type' => 'category',
                'module' => null,
                'name' => 'Category 1',
                'grademin' => 'E',
                'grademax' => 'A',
                'gradepass' => 'C',
                'hidden' => 0,
                'grades' => array(),
            ),
            array(
                'idnumber' => '',
                'type' => 'module',
                'module' => 'assignment',
                'name' => 'Assignment 1',
                'grademin' => '',
                'grademax' => '',
                'gradepass' => '',
                'hidden' => 1,
                'grades' => array(),
            ),
            array(
                'idnumber' => 'gi1',
                'type' => 'course',
                'module' => null,
                'name' => null,
                'grademin' => '1',
                'grademax' => '10',
                'gradepass' => '5',
                'hidden' => 0,
                'grades' => array(),
            ),
        )));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_course_grades('course1', array('user1', 'user2'));
    }

    public function test_unknown_user() {
        $this->having_course_id('course1', 101);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_course_grades('course1', array('user1', 'user2'));
    }
}

class GetUserGradesTest extends OperationTest {

    public function test() {
        $this->having_user_id('user1', 201);
        $this->having_course_id('course1', 301);
        $this->having_course_id('course2', 302);
        $this->moodle->shouldReceive('get_course_grade')->with(201, 301)->andReturn('5.1');
        $this->moodle->shouldReceive('get_course_grade')->with(201, 302)->andReturn('6.2');

        $result = $this->operations->get_user_grades(
            'user1', array('course1', 'course2'));

        $this->assertThat($result, $this->identicalTo(array(
            array('course' => 'course1', 'grade' => '5.1'),
            array('course' => 'course2', 'grade' => '6.2'),
        )));
    }

    public function test_unknown_course() {
        $this->having_user_id('user1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_user_grades('user1', array('course1', 'course2'));
    }

    public function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');
        $this->operations->get_user_grades('user1', array());
    }
}

/* Assignments */

class GetAssignmentsTest extends OperationTest {

    public function test() {
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

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_assignments('course1');
    }
}

class GetAssignmentSubmissionsTest extends OperationTest {

    public function test() {
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
                'attempt' => '0',
            ),
            (object) array(
                'id' => '301',
                'user' => 'student1',
                'grader' => 'teacher1',
                'timesubmitted' => '1234567893',
                'timegraded' => '1234567894',
                'numfiles' => '1',
                'attempt' => '1',
            ),
            (object) array(
                'id' => '302',
                'user' => 'student2',
                'grader' => 'teacher2',
                'timesubmitted' => '1234567893',
                'timegraded' => '1234567894',
                'numfiles' => '2',
                'attempt' => '0',
            ),
            (object) array(
                'id' => '302',
                'user' => 'student2',
                'grader' => null,
                'timesubmitted' => '1234567894',
                'timegraded' => null,
                'numfiles' => '2',
                'attempt' => '1',
            ),
            (object) array(
                'id' => '301',
                'user' => 'student3',
                'grader' => null,
                'timesubmitted' => '1234567895',
                'timegraded' => null,
                'numfiles' => '0',
                'attempt' => '0',
            ),
        );
        $this->moodle->shouldReceive('get_assignment_submissions')->with(201)->andReturn($records);

        $result = $this->operations->get_assignment_submissions('course1', 'A1');

        $this->assertThat($result, $this->identicalTo(array(
            array('user' => 'student1',
                  'grader' => 'teacher1',
                  'timesubmitted' => 1234567891,
                  'timegraded' => 1234567892,
                  'numfiles' => 1,
                  'attempt' => 0),
            array('user' => 'student1',
                  'grader' => 'teacher1',
                  'timesubmitted' => 1234567893,
                  'timegraded' => 1234567894,
                  'numfiles' => 1,
                  'attempt' => 1),
            array('user' => 'student2',
                  'grader' => 'teacher2',
                  'timesubmitted' => 1234567893,
                  'timegraded' => 1234567894,
                  'numfiles' => 2,
                  'attempt' => 0),
            array('user' => 'student2',
                  'grader' => null,
                  'timesubmitted' => 1234567894,
                  'timegraded' => null,
                  'numfiles' => 2,
                  'attempt' => 1),
            array('user' => 'student3',
                  'grader' => null,
                  'timesubmitted' => 1234567895,
                  'timegraded' => null,
                  'numfiles' => 0,
                  'attempt' => 0),
        )));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_assignment_submissions('course1', 'A1');
    }

}

/* Forums */

class GetForumStats extends OperationTest {

    public function test() {
        $forums = array(
            (object) array(
                'id' => '201',
                'idnumber' => 'F1',
                'name' => 'Forum 1',
                'type' => 'general',
            ),
            (object) array(
                'id' => '202',
                'idnumber' => 'F2',
                'name' => 'Forum 2',
                'type' => 'eachuser',
            ),
            (object) array(
                'id' => '203',
                'idnumber' => null,
                'name' => 'Forum 3',
                'type' => 'news',
            ),
        );
        $stats1 = array(
            (object) array('groupname' => 'group1', 'discussions' => '8', 'posts' => '45'),
            (object) array('groupname' => 'group2', 'discussions' => '5', 'posts' => '32'),
        );
        $stats2 = array(
            (object) array('groupname' => 'group1', 'discussions' => '11', 'posts' => '19'),
            (object) array('groupname' => 'group2', 'discussions' => '17', 'posts' => '25'),
        );
        $stats3 = array(
            (object) array('groupname' => null, 'discussions' => '5', 'posts' => '6'),
            (object) array('groupname' => 'group1', 'discussions' => '3', 'posts' => '3'),
            (object) array('groupname' => 'group2', 'discussions' => '2', 'posts' => '2'),
        );

        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_forums')->with(101)->andReturn($forums);
        $this->moodle->shouldReceive('get_forum_stats')->with(201)->andReturn($stats1);
        $this->moodle->shouldReceive('get_forum_stats')->with(202)->andReturn($stats2);
        $this->moodle->shouldReceive('get_forum_stats')->with(203)->andReturn($stats3);

        $result = $this->operations->get_forum_stats('course1');

        $this->assertThat($result, $this->identicalTo(array(
            array(
                'idnumber' => 'F1',
                'name' => 'Forum 1',
                'type' => 'general',
                'stats' => array(
                   array('group' => 'group1', 'discussions' => 8, 'posts' => 45),
                   array('group' => 'group2', 'discussions' => 5, 'posts' => 32),
                ),
            ),
            array(
                'idnumber' => 'F2',
                'name' => 'Forum 2',
                'type' => 'eachuser',
                'stats' => array(
                   array('group' => 'group1', 'discussions' => 11, 'posts' => 19),
                   array('group' => 'group2', 'discussions' => 17, 'posts' => 25),
                ),
            ),
            array(
                'idnumber' => '',
                'name' => 'Forum 3',
                'type' => 'news',
                'stats' => array(
                   array('group' => '', 'discussions' => 5, 'posts' => 6),
                   array('group' => 'group1', 'discussions' => 3, 'posts' => 3),
                   array('group' => 'group2', 'discussions' => 2, 'posts' => 2),
                ),
            ),
        )));
    }

    public function test_no_forums() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_forums')->with(101)->andReturn(false);

        $result = $this->operations->get_forum_stats('course1');

        $this->assertThat($result, $this->identicalTo(array()));
    }

    public function test_no_stats() {
        $forums = array(
            (object) array(
                'id' => '201',
                'idnumber' => 'F1',
                'name' => 'Forum 1',
                'type' => 'general',
            ),
        );

        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_forums')->with(101)->andReturn($forums);
        $this->moodle->shouldReceive('get_forum_stats')->with(201)->andReturn(false);

        $result = $this->operations->get_forum_stats('course1');

        $this->assertThat($result, $this->identicalTo(array(
            array(
                'idnumber' => 'F1',
                'name' => 'Forum 1',
                'type' => 'general',
                'stats' => array(),
            ),
        )));
    }

    public function test_unkown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $result = $this->operations->get_forum_stats('course1');
    }
}

class GetForumUserStats extends OperationTest {

    public function test() {
        $forums = array(
            (object) array(
                'id' => 201,
                'idnumber' => 'F1',
                'name' => 'Forum1',
                'type' => 'general'
            ),
            (object) array(
                'id' => 202,
                'idnumber' => 'F2',
                'name' => 'Forum2',
                'type' => 'general'
            ),
        );

        $stats1 = array(
            (object) array('username' => 'user1', 'groupname' => 'group1', 'discussions' => '2', 'posts' => '6'),
            (object) array('username' => 'user2', 'groupname' => 'group2' , 'discussions' => '5', 'posts' => '10'),
        );

        $stats2 = array(
            (object) array('username' => 'user1', 'groupname' => 'group1', 'discussions' => '3', 'posts' => '8'),
            (object) array('username' => 'user2', 'groupname' => 'group2' , 'discussions' => '1', 'posts' => '1'),
        );

        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_forums')->with(101)->andReturn($forums);
        $this->moodle->shouldReceive('get_forum_user_stats')->with(201, array('user1', 'user2'))->andReturn($stats1);
        $this->moodle->shouldReceive('get_forum_user_stats')->with(202, array('user1', 'user2'))->andReturn($stats2);

        $result = $this->operations->get_forum_user_stats('course1', array('user1', 'user2'));

        $this->assertThat($result, $this->identicalTo(array(
                array(
                    'idnumber' => 'F1',
                    'name' => 'Forum1',
                    'type' => 'general',
                    'stats' => array(
                        array('username' => 'user1', 'group' => 'group1', 'discussions' => '2', 'posts' => '6', ),
                        array('username' => 'user2', 'group' => 'group2', 'discussions' => '5', 'posts' => '10', ),
                    )
                ),
                array(
                    'idnumber' => 'F2',
                    'name' => 'Forum2',
                    'type' => 'general',
                    'stats' => array(
                        array('username' => 'user1', 'group' => 'group1', 'discussions' => '3', 'posts' => '8', ),
                        array('username' => 'user2', 'group' => 'group2', 'discussions' => '1', 'posts' => '1', ),
                    )
                )
        )));
    }

    public function test_no_forums() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_forums')->with(101)->andReturn(false);

        $result = $this->operations->get_forum_user_stats('course1', array('user1' , 'user2'));

        $this->assertThat($result, $this->identicalTo(array()));
    }

    public function test_no_users() {
        $forums = array(
            (object) array(
                'id' => 201,
                'idnumber' => 'F1',
                'name' => 'Forum1',
                'type' => 'general'
            ),
            (object) array(
                'id' => 202,
                'idnumber' => 'F2',
                'name' => 'Forum2',
                'type' => 'general'
            ),
        );

        $stats1 = array(
            (object) array('username' => 'user1', 'groupname' => 'group1', 'discussions' => '2', 'posts' => '6'),
            (object) array('username' => 'user2', 'groupname' => 'group2' , 'discussions' => '5', 'posts' => '10'),
            (object) array('username' => 'user3', 'groupname' => 'group2' , 'discussions' => '0', 'posts' => '1'),
            (object) array('username' => 'user4', 'groupname' => 'group1' , 'discussions' => '0', 'posts' => '2'),
        );

        $stats2 = array(
            (object) array('username' => 'user1', 'groupname' => 'group1', 'discussions' => '3', 'posts' => '8'),
            (object) array('username' => 'user2', 'groupname' => 'group2' , 'discussions' => '1', 'posts' => '1'),
            (object) array('username' => 'user3', 'groupname' => 'group2' , 'discussions' => '0', 'posts' => '1'),
        );

        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_forums')->with(101)->andReturn($forums);
        $this->moodle->shouldReceive('get_forum_user_stats')->with(201, array())->andReturn($stats1);
        $this->moodle->shouldReceive('get_forum_user_stats')->with(202, array())->andReturn($stats2);

        $result = $this->operations->get_forum_user_stats('course1', array());

        $this->assertThat($result, $this->identicalTo(array(
                array(
                    'idnumber' => 'F1',
                    'name' => 'Forum1',
                    'type' => 'general',
                    'stats' => array(
                        array('username' => 'user1', 'group' => 'group1', 'discussions' => '2', 'posts' => '6'),
                        array('username' => 'user2', 'group' => 'group2', 'discussions' => '5', 'posts' => '10'),
                        array('username' => 'user3', 'group' => 'group2', 'discussions' => '0', 'posts' => '1'),
                        array('username' => 'user4', 'group' => 'group1', 'discussions' => '0', 'posts' => '2'),
                    )
                ),
                array(
                    'idnumber' => 'F2',
                    'name' => 'Forum2',
                    'type' => 'general',
                    'stats' => array(
                        array('username' => 'user1', 'group' => 'group1', 'discussions' => '3', 'posts' => '8'),
                        array('username' => 'user2', 'group' => 'group2', 'discussions' => '1', 'posts' => '1'),
                        array('username' => 'user3', 'group' => 'group2', 'discussions' => '0', 'posts' => '1'),
                    )
                )
        )));
    }

    public function test_no_stats() {
        $forums = array(
            (object) array(
                'id' => '201',
                'idnumber' => 'F1',
                'name' => 'Forum1',
                'type' => 'general',
            ),
        );

        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_forums')->with(101)->andReturn($forums);
        $this->moodle->shouldReceive('get_forum_user_stats')->with(201, array('user1', 'user2'))->andReturn(false);

        $result = $this->operations->get_forum_user_stats('course1', array('user1', 'user2'));

        $this->assertThat($result, $this->identicalTo(array(
            array(
                'idnumber' => 'F1',
                'name' => 'Forum1',
                'type' => 'general',
                'stats' => array(),
            ),
        )));
    }

    public function test_unkown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $result = $this->operations->get_forum_stats('course1');
    }
}

/* Surveys */

class GetSurveysTest extends OperationTest {

    public function test() {
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

    public function test_blank_idnumber() {
        $this->setExpectedException('local_secretaria_exception', 'Invalid idnumber');
        $this->operations->get_assignment_submissions('course1', '');
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_surveys('course1');
    }

    public function test_unknown_assignment() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_assignment_id')->with(101, 'A1')->andReturn(false);

        $this->setExpectedException('local_secretaria_exception', 'Unknown assignment');

        $this->operations->get_assignment_submissions('course1', 'A1');
    }
}

class GetSurveysDataTest extends OperationTest {
    public function test() {

        $records = array(
            (object) array('id' => 201, 'name' => 'Survey 1',
                           'idnumber' => 'S1', 'realm' => 'private'),
        );

        $questions = array(
            '1001' => (object) array('id' => '1001', 'name' => 'completat', 'content' => 'Has completat tot el curs?',
                           'type_id' => '1' , 'position' => 1, 'has_choices' => 'n'),
            '1002' => (object) array('id' => '1002', 'name' => 'valora', 'content' => 'Valora els materials',
                           'type_id' => '5', 'position' => 2, 'has_choices' => 'y'),
            '1003' => (object) array('id' => '1003', 'name' => 'millores', 'content' => 'Què milloraries del mòdul?',
                           'type_id' => '3', 'position' => 3, 'has_choices' => 'n'),
            '1004' => (object) array('id' => '1004', 'name' => 'nota', 'content' => 'Valora entre 1 i 5 les activitats següents',
                           'type_id' => '8', 'position' => 4, 'has_choices' => 'y'),
        );

        $responsesbool = array(
            (object) array('responseid' => '2001', 'questionid' => '1001', 'username' => 'student1', 'content' => 'y'),
            (object) array('responseid' => '2002', 'questionid' => '1001', 'username' => 'student2', 'content' => 'n'),
            (object) array('responseid' => '2003', 'questionid' => '1001', 'username' => 'student3', 'content' => 'y'),
        );

        $responsestext = array(
            (object) array('responseid' => '2007', 'questionid' => '1003', 'username' => 'student1', 'content' => 'Més implicació del professorat'),
            (object) array('responseid' => '2008', 'questionid' => '1003', 'username' => 'student2', 'content' => 'No canviaria res'),
            (object) array('responseid' => '2009', 'questionid' => '1003', 'username' => 'student3', 'content' => 'Millorar els materials'),
        );

        $responsesmultiple = array(
            (object) array('responseid' => '2004', 'questionid' => '1002', 'username' => 'student1', 'content' => 'Adequats'),
            (object) array('responseid' => '2004', 'questionid' => '1002', 'username' => 'student1', 'content' => 'Didàctics'),
            (object) array('responseid' => '2005', 'questionid' => '1002', 'username' => 'student2', 'content' => 'Didàctics'),
            (object) array('responseid' => '2006', 'questionid' => '1002', 'username' => 'student3', 'content' => 'Poc Adequats'),
        );

        $choicesmultiple = array(
            (object) array('questionid' => '1002', 'content' => 'Molt adequats'),
            (object) array('questionid' => '1002', 'content' => 'Adequats'),
            (object) array('questionid' => '1002', 'content' => 'Didàctics'),
            (object) array('questionid' => '1002', 'content' => 'Poc Adequats'),
        );

        $responsesmultiplerank = array(
            (object) array('responseid' => '2010', 'questionid' => '1004', 'username' => 'student1', 'content' => 'Forums', 'rank' => 3),
            (object) array('responseid' => '2010', 'questionid' => '1004', 'username' => 'student1', 'content' => 'Tasques', 'rank' => 4),
            (object) array('responseid' => '2011', 'questionid' => '1004', 'username' => 'student2', 'content' => 'Forums', 'rank' => 1),
            (object) array('responseid' => '2011', 'questionid' => '1004', 'username' => 'student2', 'content' => 'Tasques', 'rank' => 2),
            (object) array('responseid' => '2012', 'questionid' => '1004', 'username' => 'student3', 'content' => 'Forums', 'rank' => 4),
            (object) array('responseid' => '2012', 'questionid' => '1004', 'username' => 'student3', 'content' => 'Tasques', 'rank' => 4),
        );

        $choicesmultiplerank = array(
            (object) array('questionid' => '1004', 'content' => '5'),
        );

        $questionstypes = array(
            '1' => 'response_bool',
            '2' => 'response_text',
            '3' => 'response_text',
            '4' => 'resp_single',
            '5' => 'resp_multiple',
            '6' => 'resp_single',
            '8' => 'response_rank',
            '9' => 'response_date',
            '10' => 'response_text',
        );

        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_surveys')->with(101)->andReturn($records);
        $this->moodle->shouldReceive('get_survey_id')->with(101, 'S1')->andReturn(123);
        $this->moodle->shouldReceive('get_survey_questions')->with(123)->andReturn($questions);
        $this->moodle->shouldReceive('get_survey_question_types')->with()->andReturn($questionstypes);

        $idquestions = array();
        foreach ($questions as $question) {
            if (!isset($idquestions[$question->type_id])) {
                $idquestions[$question->type_id] = array();
            }
            $idquestions[$question->type_id][] = $question->id;
        }

        $this->moodle->shouldReceive('get_survey_responses_simple')->with($idquestions[1], $questionstypes[1])->andReturn($responsesbool);
        $this->moodle->shouldReceive('get_survey_responses_simple')->with($idquestions[3], $questionstypes[3])->andReturn($responsestext);
        $this->moodle->shouldReceive('get_survey_responses_multiple')->with($idquestions[5], $questionstypes[5])->andReturn($responsesmultiple);
        $this->moodle->shouldReceive('get_survey_question_choices')->with($idquestions[5], $questionstypes[5])->andReturn($choicesmultiple);
        $this->moodle->shouldReceive('get_survey_responses_multiple')->with($idquestions[8], $questionstypes[8])->andReturn($responsesmultiplerank);
        $this->moodle->shouldReceive('get_survey_question_choices')->with($idquestions[8], $questionstypes[8])->andReturn($choicesmultiplerank);

        $result = $this->operations->get_surveys_data('course1');

        $this->assertThat($result, $this->identicalTo(array(
            array(
                'idnumber' => 'S1',
                'name' => 'Survey 1',
                'type' => 'private',
                'questions' => array(
                    array(
                        'name' => 'completat',
                        'content' => 'Has completat tot el curs?',
                        'position' => 1,
                        'type' => 'response_bool',
                        'has_choices' => 'n',
                        'choices' => array(),
                        'responses' => array(
                                            '2001' => array(
                                                    'username' => 'student1',
                                                    'content' => array('y'),
                                                    'rank' => array()
                                                ),
                                            '2002' => array(
                                                    'username' => 'student2',
                                                    'content' => array('n'),
                                                    'rank' => array()
                                                ),
                                            '2003' => array(
                                                    'username' => 'student3',
                                                    'content' => array('y'),
                                                    'rank' => array()
                                                ),
                        )
                    ),
                    array(
                        'name' => 'valora',
                        'content' => 'Valora els materials',
                        'position' => 2,
                        'type' => 'resp_multiple',
                        'has_choices' => 'y',
                        'choices' => array('Molt adequats', 'Adequats', 'Didàctics', 'Poc Adequats'),
                        'responses' => array(
                                            '2004' => array(
                                                    'username' => 'student1',
                                                    'content' => array('Adequats', 'Didàctics'),
                                                    'rank' => array()
                                                ),
                                            '2005' => array(
                                                    'username' => 'student2',
                                                    'content' => array('Didàctics'),
                                                    'rank' => array()
                                                ),
                                            '2006' => array(
                                                    'username' => 'student3',
                                                    'content' => array('Poc Adequats'),
                                                    'rank' => array()
                                                ),
                        )
                    ),
                    array(
                        'name' => 'millores',
                        'content' => 'Què milloraries del mòdul?',
                        'position' => 3,
                        'type' => 'response_text',
                        'has_choices' => 'n',
                        'choices' => array(),
                        'responses' => array(
                                            '2007' => array(
                                                    'username' => 'student1',
                                                    'content' => array('Més implicació del professorat'),
                                                    'rank' => array()
                                                ),
                                            '2008' => array(
                                                    'username' => 'student2',
                                                    'content' => array('No canviaria res'),
                                                    'rank' => array()
                                                ),
                                            '2009' => array(
                                                    'username' => 'student3',
                                                    'content' => array('Millorar els materials'),
                                                    'rank' => array()
                                                ),
                        )
                    ),
                    array(
                        'name' => 'nota',
                        'content' => 'Valora entre 1 i 5 les activitats següents',
                        'position' => 4,
                        'type' => 'response_rank',
                        'has_choices' => 'y',
                        'choices' => array(1, 2, 3, 4, 5),
                        'responses' => array(
                                            '2010' => array(
                                                    'username' => 'student1',
                                                    'content' => array('Forums', 'Tasques'),
                                                    'rank' => array(3, 4)
                                                ),
                                            '2011' => array(
                                                    'username' => 'student2',
                                                    'content' => array('Forums', 'Tasques'),
                                                    'rank' => array(1, 2)
                                                ),
                                            '2012' => array(
                                                    'username' => 'student3',
                                                    'content' => array('Forums', 'Tasques'),
                                                    'rank' => array(4, 4)
                                                ),
                        )
                    ),
                )
            ),
        )));
    }

    public function test_blank_idnumber() {
        $this->setExpectedException('local_secretaria_exception', 'Invalid idnumber');
        $this->operations->get_assignment_submissions('course1', '');
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_surveys('course1');
    }
}

class CreateSurveyTest extends OperationTest {

    public function setUp() {
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

    public function test() {
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(101, 'S1')->andReturn(201);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(false);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_survey')->with(102, 7, 'S2', 'Survey 2', 'Summary 2', 0, 0, 201)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_survey($this->properties);
    }

    public function test_opendate() {
        $this->properties['opendate'] = array('year' => 2012, 'month' => 10, 'day' => 22);
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(101, 'S1')->andReturn(201);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(false);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_survey')->with(102, 7, 'S2', 'Survey 2', 'Summary 2', mktime(0, 0, 0, 10, 22, 2012), 0, 201)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_survey($this->properties);
    }

    public function test_closedate() {
        $this->properties['closedate'] = array('year' => 2012, 'month' => 10, 'day' => 22);
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(101, 'S1')->andReturn(201);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(false);
        $this->moodle->shouldReceive('start_transaction')->once()->ordered();
        $this->moodle->shouldReceive('create_survey')->with(102, 7, 'S2', 'Survey 2', 'Summary 2', 0, mktime(23, 55, 0, 10, 22, 2012), 201)->once()->ordered();
        $this->moodle->shouldReceive('commit_transaction')->once()->ordered();

        $this->operations->create_survey($this->properties);
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->create_survey($this->properties);
    }

    public function test_unknown_section() {
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Unknown section');

        $this->operations->create_survey($this->properties);
    }

    public function test_blank_idnumber() {
        $this->properties['idnumber'] = '';
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->setExpectedException('local_secretaria_exception', 'Empty idnumber or name or summary or template/course or template/idnumber');

        $this->operations->create_survey($this->properties);
    }

    public function test_duplicate_idnumber() {
        $this->properties['idnumber'] = 'S2';
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(202);
        $this->setExpectedException('local_secretaria_exception', 'Duplicate idnumber');

        $this->operations->create_survey($this->properties);
    }

    public function test_blank_name() {
        $this->properties['name'] = '';
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->setExpectedException('local_secretaria_exception', 'Empty idnumber or name or summary or template/course or template/idnumber');

        $this->operations->create_survey($this->properties);
    }

    public function test_blank_summary() {
        $this->properties['summary'] = '';
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->setExpectedException('local_secretaria_exception', 'Empty idnumber or name or summary or template/course or template/idnumber');

        $this->operations->create_survey($this->properties);
    }

    public function test_blank_template_course() {
        $this->properties['template']['course'] = '';
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->setExpectedException('local_secretaria_exception', 'Empty idnumber or name or summary or template/course or template/idnumber');

        $this->operations->create_survey($this->properties);
    }

    public function test_blank_template_idnumber() {
        $this->properties['template']['idnumber'] = '';
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->setExpectedException('local_secretaria_exception', 'Empty idnumber or name or summary or template/course or template/idnumber');

        $this->operations->create_survey($this->properties);
    }

    public function test_unknown_template_course() {
        $this->having_course_id('course1', false);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->create_survey($this->properties);
    }

    public function test_unknown_survey() {
        $this->having_course_id('course1', 101);
        $this->having_course_id('course2', 102);
        $this->moodle->shouldReceive('section_exists')->with(102, 7)->andReturn(true);
        $this->moodle->shouldReceive('get_survey_id')->with(101, 'S1')->andReturn(false);
        $this->moodle->shouldReceive('get_survey_id')->with(102, 'S2')->andReturn(false);

        $this->setExpectedException('local_secretaria_exception', 'Unknown survey');

        $this->operations->create_survey($this->properties);
    }
}

class UpdateSurveyTest extends OperationTest {

    public function test() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_questionnaire_id')->with(101, 'S1')->andReturn(201);
        $this->moodle->shouldReceive('get_questionnaire_id')->with(101, 'S2')->andReturn(false);

        $this->moodle->shouldReceive('update_survey_idnumber')->with(101, 'S1', 'S2')->andReturn(false);

        $record = (object) array(
            'id' => 201,
            'name' => 'Survey 2',
        );

        $this->moodle->shouldReceive('update_survey')->with(Mockery::mustBe($record));

        $this->operations->update_survey('course1', 'S1', array(
            'idnumber' => 'S2',
            'name' => 'Survey 2',
        ));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->update_survey('course22', 'S1', array());
    }


    public function test_unknown_questionnaire() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_questionnaire_id')->with(101, 'S5')->andReturn(false);
        $this->setExpectedException('local_secretaria_exception', 'Unknown questionnaire');

        $this->operations->update_survey('course1', 'S5', array());
    }

    public function test_empty_properties() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_questionnaire_id')->with(101, 'S1')->andReturn(201);

        $this->operations->update_survey('course1', 'S1', array());
    }

    public function test_duplicate_idnumber() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_questionnaire_id')->with(101, 'S1')->andReturn(201);
        $this->moodle->shouldReceive('get_questionnaire_id')->with(101, 'S2')->andReturn(202);
        $this->setExpectedException('local_secretaria_exception', 'Duplicated idnumber');

        $this->operations->update_survey(
            'course1', 'S1', array('idnumber' => 'S2'));
    }

    public function test_blank_idnumber() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_questionnaire_id')->with(101, 'S1')->andReturn(201);
        $this->setExpectedException('local_secretaria_exception', 'Empty idnumber');

        $this->operations->update_survey('course1', 'S1', array('idnumber' => ''));
    }

    public function test_blank_name() {
        $this->having_course_id('course1', 101);
        $this->moodle->shouldReceive('get_questionnaire_id')->with(101, 'S1')->andReturn(201);
        $this->setExpectedException('local_secretaria_exception', 'Empty name');

        $this->operations->update_survey('course1', 'S1', array('name' => ''));
    }
}

/* Workshops */

class GetWorkshopsTest extends OperationTest {

    public function test() {
        $this->having_course_id('course1', 101);
        $records = array(
            (object) array(
                'id' => '201',
                'name' => 'Workshop 1',
                'idnumber' => 'W1',
                'opentime' => '1234567891',
                'closetime' => '1234567892',
            ),
            (object) array(
                'id' => '202',
                'name' => 'Workshop 2',
                'idnumber' => 'W2',
                'opentime' => '0',
                'closetime' => '1234567893',
            ),
            (object) array(
                'id' => '203',
                'name' => 'Workshop 3',
                'idnumber' => null,
                'opentime' => '1234567894',
                'closetime' => '0',
            ),
        );
        $this->moodle->shouldReceive('get_workshops')->with(101)->andReturn($records);

        $result = $this->operations->get_workshops('course1');

        $this->assertThat($result, $this->identicalTo(array(
            array('idnumber' => 'W1',
                  'name' => 'Workshop 1',
                  'opentime' => 1234567891,
                  'closetime' => 1234567892),
            array('idnumber' => 'W2',
                  'name' => 'Workshop 2',
                  'opentime' => null,
                  'closetime' => 1234567893),
            array('idnumber' => '',
                  'name' => 'Workshop 3',
                  'opentime' => 1234567894,
                  'closetime' => null),
        )));
    }

    public function test_unknown_course() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');
        $this->operations->get_workshops('course1');
    }
}

/* Mail */

class SendMailTest extends OperationTest {

    public function setUp() {
        parent::setUp();
        $this->message = array(
            'sender' => 'user1',
            'course' => 'course1',
            'subject' => 'subject text',
            'content' => 'content text',
            'to' => array('user2'),
        );
    }

    public function test() {
        $this->message['cc'] = array('user3', 'user4');
        $this->message['bcc'] = array('user5');
        $this->having_course_id('course1', 201);
        for ($i = 1; $i <= 5; $i++) {
            $this->having_user_id('user' . $i, 300 + $i);
        }
        $this->moodle->shouldReceive('send_mail')->with(301, 201, 'subject text', 'content text', array(302), array(303, 304), array(305))->once();

        $this->operations->send_mail($this->message);
    }

    public function test_unknown_course() {
        $this->having_user_id('user1', 301);
        $this->having_user_id('user2', 302);
        $this->setExpectedException('local_secretaria_exception', 'Unknown course');

        $this->operations->send_mail($this->message);
    }

    public function test_unknown_user() {
        $this->having_course_id('course1', 201);
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->send_mail($this->message);
    }

    public function test_duplicate_user() {
        $this->message['cc'] = array('user1');
        $this->having_course_id('course1', 201);
        $this->having_user_id('user1', 301);
        $this->having_user_id('user2', 302);

        $this->setExpectedException('local_secretaria_exception', 'Invalid recipient or duplicated recipient');

        $this->operations->send_mail($this->message);
    }

    public function test_no_recipient() {
        $this->message['to'] = array();
        $this->having_course_id('course1', 201);
        $this->having_user_id('user1', 301);

        $this->setExpectedException('local_secretaria_exception', 'Invalid recipient or duplicated recipient');

        $this->operations->send_mail($this->message);
    }
}

class GetMailStatsTest extends OperationTest {

    public function test() {
        $recordsreceived = array(
            (object) array(
                'id' => '201',
                'course' => 'course1',
                'messages' => '17',
            ),
            (object) array(
                'id' => '202',
                'course' => 'course2',
                'messages' => '23',
            ),
            (object) array(
                'id' => '203',
                'course' => 'course3',
                'messages' => '15',
            ),
        );
        $recordssent = array(
            (object) array(
                'id' => '201',
                'course' => 'course1',
                'messages' => '14',
            ),
            (object) array(
                'id' => '202',
                'course' => 'course2',
                'messages' => '19',
            ),
            (object) array(
                'id' => '203',
                'course' => 'course3',
                'messages' => '9',
            ),
        );
        $this->having_user_id('user1', 201);
        $this->moodle->shouldReceive('get_mail_stats_received')->with(201, 1e10, 2e10)->andReturn($recordsreceived);
        $this->moodle->shouldReceive('get_mail_stats_sent')->with(201, 1e10, 2e10)->andReturn($recordssent);

        $result = $this->operations->get_mail_stats('user1', 1e10, 2e10);

        $this->assertThat($result, $this->identicalTo(array(
             array('course' => 'course1', 'received' => 17, 'sent' => 14),
             array('course' => 'course2', 'received' => 23, 'sent' => 19),
             array('course' => 'course3', 'received' => 15, 'sent' => 9),
        )));
    }

    public function test_unknown_user() {
        $this->setExpectedException('local_secretaria_exception', 'Unknown user');

        $this->operations->get_mail_stats('user1', 1e10, 2e10);
    }
}

class CalcFormula extends OperationTest {

    public function test() {
        $formula = "=1+2";
        $params = array();
        $variables = array();
        $values = array();

        $this->moodle->shouldReceive('calc_formula')->with($formula, $params)->andReturn(3);
        $result = $this->operations->calc_formula($formula, $variables, $values);
        $this->assertEquals(3, $result);
    }

    public function test_empty_formula() {
        $formula = "";
        $variables = array();
        $values = array();

        $this->setExpectedException('local_secretaria_exception', 'Empty formula');
        $result = $this->operations->calc_formula($formula, $variables, $values);
    }

    public function test_different_number_elements() {
        $formula = "=a+2";
        $variables = array('a');
        $values = array('1', '2');

        $this->setExpectedException('local_secretaria_exception', 'Not equal number of elements in arrays');
        $result = $this->operations->calc_formula($formula, $variables, $values);
    }

    public function test_invalid_formula() {
        $formula = "=(a+2";
        $variables = array('a');
        $values = array('1');
        $params = array_combine($variables, $values);

        $this->setExpectedException('local_secretaria_exception', 'Invalid formula');
        $this->moodle->shouldReceive('calc_formula')->with($formula, $params)->andReturn(false);
        $result = $this->operations->calc_formula($formula, $variables, $values);
    }
}
