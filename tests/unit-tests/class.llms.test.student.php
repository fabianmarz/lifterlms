<?php
/**
 * Tests for LifterLMS Student Functions
 * @since    3.3.1
 * @version  3.3.1
 */
class LLMS_Test_Student extends LLMS_UnitTestCase {


	/**
	 * Test whether a user is_enrolled() in a course or membership
	 * @return   void
	 * @since    3.3.1
	 * @version  3.3.1
	 */
	function test_enrollment() {

		// Create new user
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		// Create new course
		$course_id = $this->factory->post->create( array( 'post_type' => 'course' ) );

		// Create new membership
		$memb_id = $this->factory->post->create( array( 'post_type' => 'llms_membership' ) );

		// Student shouldn't be enrolled in newly created course/membership
		$this->assertFalse( llms_is_user_enrolled( $user_id, $course_id ) );
		$this->assertFalse( llms_is_user_enrolled( $user_id, $memb_id ) );

		// Enroll Student in newly created course/membership
		llms_enroll_student( $user_id, $course_id, 'test_is_enrolled' );
		llms_enroll_student( $user_id, $memb_id, 'test_is_enrolled' );

		// Student should be enrolled in course/membership
		$this->assertTrue( llms_is_user_enrolled( $user_id, $course_id ) );
		$this->assertTrue( llms_is_user_enrolled( $user_id, $memb_id ) );

		// Wait 1 second before unenrolling Student
		// otherwise, enrollment and unenrollment postmeta will have identical timestamps
		sleep( 1 );

		// Unenroll Student in newly created course/membership
		llms_unenroll_student( $user_id, $course_id, 'cancelled', 'test_is_enrolled');
		llms_unenroll_student( $user_id, $memb_id, 'cancelled', 'test_is_enrolled' );

		// Student should be not enrolled in newly created course/membership
		$this->assertFalse( llms_is_user_enrolled( $user_id, $course_id ) );
		$this->assertFalse( llms_is_user_enrolled( $user_id, $memb_id ) );

	}

	/**
	 * Test mark_complete() and mark_incomplete() on a lesson, section, course, and track
	 *
	 * This test creates a course with two sections.  The first section has two lessons and
	 * the second section has one lesson.  mark_complete() is called on all three lessons
	 * in order to test lesson, section, and course completion.
	 *
	 * When the whole course is complete, mark_incomplete() is called on the three lessons
	 * in the opposite order to test 'incompletion' for a three post types
	 *
	 * @return   void
	 * @since    3.3.1
	 * @version  3.3.1
	 */
	function test_completion() {

		// Create new user
		$user = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		// Create new course
		$course = $this->factory->post->create( array( 'post_type' => 'course' ) );

		// Create two sections assigned to course
		$section1 = LLMS_POST_Handler::create_section( $course, 'test-section' );
		$section2 = LLMS_POST_Handler::create_section( $course, 'test-section2' );

		// Create two lessons assigned to section 1 so we can test if each type is complete
		$lesson1_section1 = LLMS_POST_Handler::create_lesson( $course, $section1, 'test-lesson' );
		$lesson2_section1 = LLMS_POST_Handler::create_lesson( $course, $section1, 'test-lesson' );

		// Create one lesson for section 2
		$lesson1_section2 = LLMS_POST_Handler::create_lesson( $course, $section2, 'test-lesson' );

		// Course, Sections, and Lessons should all be incomplete
		$this->assertFalse( llms_is_complete( $user, $lesson1_section1, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $lesson2_section1, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $section1, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $lesson1_section2, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $section2, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $course, 'course' ) );

		// Mark lesson 1 section 1 complete
		llms_mark_complete( $user, $lesson1_section1, 'lesson', 'test-mark-complete' );

		// Only first lesson should be complete
		$this->assertTrue(  llms_is_complete( $user, $lesson1_section1, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $lesson2_section1, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $section1, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $lesson1_section2, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $section2, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $course, 'course' ) );

		// Mark lesson 2 section 1 complete
		llms_mark_complete( $user, $lesson2_section1, 'lesson', 'test-mark-complete' );

		// Section 1 now complete
		$this->assertTrue(  llms_is_complete( $user, $lesson1_section1, 'lesson' ) );
		$this->assertTrue(  llms_is_complete( $user, $lesson2_section1, 'lesson' ) );
		$this->assertTrue(  llms_is_complete( $user, $section1, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $lesson1_section2, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $section2, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $course, 'course' ) );

		// Mark lesson 1 section 2 complete
		llms_mark_complete( $user, $lesson1_section2, 'lesson', 'test-mark-complete' );

		// Everthing should be complete now
		$this->assertTrue( llms_is_complete( $user, $lesson1_section1, 'lesson' ) );
		$this->assertTrue( llms_is_complete( $user, $lesson2_section1, 'lesson' ) );
		$this->assertTrue( llms_is_complete( $user, $section1, 'section' ) );
		$this->assertTrue( llms_is_complete( $user, $lesson1_section2, 'lesson' ) );
		$this->assertTrue( llms_is_complete( $user, $section2, 'section' ) );
		$this->assertTrue( llms_is_complete( $user, $course, 'course' ) );

		// Mark lesson 1 section 2 INcomplete
		llms_mark_incomplete( $user, $lesson1_section2, 'lesson', 'test-mark-incomplete' );

		// Only section 1 now complete
		$this->assertTrue(  llms_is_complete( $user, $lesson1_section1, 'lesson' ) );
		$this->assertTrue(  llms_is_complete( $user, $lesson2_section1, 'lesson' ) );
		$this->assertTrue(  llms_is_complete( $user, $section1, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $lesson1_section2, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $section2, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $course, 'course' ) );

		// Mark lesson 2 section 1 INcomplete
		llms_mark_incomplete( $user, $lesson2_section1, 'lesson', 'test-mark-incomplete' );

		// Only first lesson should be complete
		$this->assertTrue(  llms_is_complete( $user, $lesson1_section1, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $lesson2_section1, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $section1, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $lesson1_section2, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $section2, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $course, 'course' ) );

		// Mark lesson 1 section 1 INcomplete
		llms_mark_incomplete( $user, $lesson1_section1, 'lesson', 'test-mark-incomplete' );

		// Course, Sections, and Lessons should all be incomplete
		$this->assertFalse( llms_is_complete( $user, $lesson1_section1, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $lesson2_section1, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $section1, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $lesson1_section2, 'lesson' ) );
		$this->assertFalse( llms_is_complete( $user, $section2, 'section' ) );
		$this->assertFalse( llms_is_complete( $user, $course, 'course' ) );
	}
}
