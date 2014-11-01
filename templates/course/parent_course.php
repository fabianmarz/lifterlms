<?php
/**
 * @author 		codeBOX
 * @package 	lifterLMS/Templates
 */

if ( ! defined( 'ABSPATH' ) ) exit; 

global $post, $course, $lesson;

printf( __('<p class="llms-parent-course-link">Back to: <a class="llms-lesson-link" href="%s">%s</a></p>', 'lifterlms' ), get_permalink( $course->id ), get_the_title( $course->id ) );
?>
	