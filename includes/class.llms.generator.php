<?php
/**
 * Generate LMS Content from export files or raw arrays of data
 * @since    3.3.0
 * @version  3.3.0
 *
 * @todo  how do we handle prerequisites?
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LLMS_Generator {

	/**
	 * Instance of WP_Error
	 * @var  obj
	 */
	public $error;

	/**
	 * Name of the Generator to use for generation
	 * @var  string
	 */
	private $generator = '';

	/**
	 * Raw contents passed into the generator's constructor
	 * @var  array
	 */
	private $raw = array();

	/**
	 * Type of data to work from
	 * bulk|single
	 * @var  string
	 */
	private $raw_type = '';

	/**
	 * Associate raw tempids with actual created ids
	 * @var  array
	 */
	private $tempids = array(
		'course' => array(),
		'lesson' => array(),
	);

	/**
	 * Array of Stats
	 * @var  array
	 */
	private $stats = array(
		'authors' => 0,
		'courses' => 0,
		'sections' => 0,
		'lessons' => 0,
		'plans' => 0,
		'quizzes' => 0,
		'questions' => 0,
		'terms' => 0,
	);

	/**
	 * Construct a new generator instance with data
	 * @param    array|string   $raw   array or json sring of raw content
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function __construct( $raw ) {

		if ( ! is_array( $raw ) ) {

			$raw = json_decode( $raw, true );

		}

		$this->error = new WP_Error();
		$this->raw = $raw;

		// for featured image creation via `media_sideload_image()`
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

	}

	/**
	 * Add taxonomy terms to a course
	 * @param    obj      $course_id   WP Post ID of a Course
	 * @param    array    $raw_terms   array of raw term arrays
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function add_course_terms( $course_id, $raw_terms ) {

		$taxes = array(
			'course_cat' => 'categories',
			'course_difficulty' => 'difficulty',
			'course_tag' => 'tags',
			'course_track' => 'tracks',
		);

		foreach ( $taxes as $tax => $key ) {

			if ( ! empty( $raw_terms[ $key ] ) && is_array( $raw_terms[ $key ] ) ) {

				// we can only have one difficulte at a time
				$append = ( 'difficulty' === $key ) ? false : true;

				$terms = array();

				// find term id or create it
				foreach ( $raw_terms[ $key ] as $term_name ) {

					if ( empty( $term_name ) ) {
						continue;
					}

					if ( $term_id = $this->get_term_id( $term_name, $tax ) ) {
						$terms[] = $term_id;
					}
				}

				wp_set_post_terms( $course_id, $terms, $tax, $append );

			}

		}

	}

	/**
	 * When called, generates raw content based on the defined generator
	 * @return   void
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function generate() {

		if ( ! empty( $this->generator ) ) {

			global $wpdb;

			$wpdb->hide_errors();

			$wpdb->query( 'START TRANSACTION' );

			try {

				call_user_func( $this->generator );

			} catch ( Exception $e ) {

				$this->error->add( 'exception', $e->getMessage() );

			}

			if ( $this->is_error() ) {
				$wpdb->query( 'ROLLBACK' );
			} else {
				$wpdb->query( 'COMMIT' );
			}

		} else {

			return $this->error->add( 'missing-generator', __( 'No generator supplied.', 'lifterlms' ) );

		}

	}

	/**
	 * Generator called for single course imports
	 * converts the single course into a format that can be handled by the bulk courses generator
	 * and invokes that generator
	 * @return   void
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function generate_course() {

		$temp = array();

		foreach ( array( '_generator', '_version', '_source' ) as $meta ) {
			if ( isset( $this->raw[ $meta ] ) ) {
				$temp[ $meta ] = $this->raw[ $meta ];
				unset( $this->raw[ $meta ] );
			}
		}

		$temp['courses'] = array( $this->raw );

		$this->raw = $temp;

		$this->generate_courses();

	}

	/**
	 * Generator called for bulk course imports
	 * @return   void
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function generate_courses() {

		if ( empty( $this->raw['courses'] ) ) {
			$this->error->add( 'required', __( 'Missing required "courses" array', 'lifterlms' ) );
		} elseif ( ! is_array( $this->raw['courses'] ) ) {
			$this->error->add( 'format', __( '"courses" must be an array', 'lifterlms' ) );
		} else {

			foreach ( $this->raw['courses'] as $raw_course ) {

				unset( $raw_course['_generator'], $raw_course['_version'] );

				$course = $this->create_course( $raw_course );

			}

		}

		$this->handle_prerequisites();

	}

	/**
	 * Create a new access plan
	 * @param    array     $raw                 Raw Access Plan Data
	 * @param    int       $course_id           WP Post ID of a LLMS Course to assign the access plan to
	 * @param    int       $fallback_author_id  WP User ID to use for the access plan author if no author is supplied in the raw data
	 * @return   int
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function create_access_plan( $raw, $course_id, $fallback_author_id = null ) {

		$author_id = $this->get_author_id_from_raw( $raw, $fallback_author_id );
		if ( isset( $raw['author'] ) ) {
			unset( $raw['author'] );
		}

		// insert the plan
		$plan = new LLMS_Access_Plan( 'new', array(
			'post_author' => $author_id,
			'post_content' => isset( $raw['content'] ) ? $raw['content'] : null,
			'post_date' => isset( $raw['date'] ) ? $this->format_date( $raw['date'] ) : null,
			'post_modified' => isset( $raw['modified'] ) ? $this->format_date( $raw['modified'] ) : null,
			'post_status' => isset( $raw['status'] ) ? $raw['status'] : null,
			'post_title' => $raw['title'],
		) );

		$this->increment( 'plans' );

		unset( $raw['content'], $raw['date'], $raw['modified'], $raw['name'], $raw['status'], $raw['title'] );

		unset( $raw['product_id'] );
		$plan->set( 'product_id', $course_id );

		// store the from the import if there is one
		if ( isset( $raw['id'] ) ) {
			$plan->set( 'generated_from_id', $raw['id'] );
			unset( $raw['id'] );
		}

		foreach ( $raw as $key => $val ) {
			$plan->set( $key, $val );
		}

		return $plan->get( 'id' );

	}

	/**
	 * Create a new course
	 * @param    array     $raw  raw course data
	 * @return   void
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function create_course( $raw ) {

		$author_id = $this->get_author_id_from_raw( $raw );

		// insert the course
		$course = new LLMS_Course( 'new', array(
			'post_author' => $author_id,
			'post_content' => isset( $raw['content'] ) ? $raw['content'] : null,
			'post_date' => isset( $raw['date'] ) ? $this->format_date( $raw['date'] ) : null,
			'post_excerpt' => isset( $raw['excerpt'] ) ? $raw['excerpt'] : null,
			'post_modified' => isset( $raw['modified'] ) ? $this->format_date( $raw['modified'] ) : null,
			'post_status' => apply_filters( 'llms_generator_course_status', 'draft', $raw, $this ),
			'post_title' => $raw['title'],
		) );

		if ( ! $course->get( 'id' ) ) {
			return $this->error->add( 'course_creation', __( 'Error creating course', 'lifterlms' ) );
		}

		$this->increment( 'courses' );

		// save the tempid
		$tempid = $this->store_temp_id( $raw, $course );

		// set all metadata
		foreach ( array_keys( $course->get_properties() ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$course->set( $key, $raw[ $key ] );
			}
		}

		// set featured image
		$this->set_featured_image( $raw['featured_image'], $course->get( 'id' ) );

		// add terms to our course
		$terms = array();
		if ( isset( $raw['difficulty'] ) ) {
			$terms['difficulty'] = array( $raw['difficulty'] );
		}
		foreach ( array( 'categories', 'tags', 'tracks' ) as $t ) {
			if ( isset( $raw[ $t ] ) ) {
				$terms[ $t ] = $raw[ $t ];
			}
		}
		$this->add_course_terms( $course->get( 'id' ), $terms );

		// create all access plans
		if ( isset( $raw['access_plans'] ) ) {
			foreach ( $raw['access_plans'] as $plan ) {
				$this->create_access_plan( $plan, $course->get( 'id' ), $author_id );
			}
		}

		// create all sections
		if ( isset( $raw['sections'] ) ) {
			foreach ( $raw['sections'] as $order => $section ) {
				$this->create_section( $section, $order + 1, $course->get( 'id' ), $author_id );
			}
		}

	}

	/**
	 * Create a new lesson
	 * @param    array     $raw                 raw lesson data
	 * @param    int       $order               lesson order within the section (starts at 1)
	 * @param    int       $section_id          WP Post ID of the lesson's parent section
	 * @param    int       $course_id           WP Post ID of the lesson's parent course
	 * @param    int       $fallback_author_id  optional author ID to use as a fallback if no raw author data supplied for the lesson
	 * @return   mixed                          lesson id or WP_Error
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function create_lesson( $raw, $order, $section_id, $course_id, $fallback_author_id = null ) {

		$author_id = $this->get_author_id_from_raw( $raw, $fallback_author_id );

		// insert the course
		$lesson = new LLMS_lesson( 'new', array(
			'post_author' => $author_id,
			'post_content' => isset( $raw['content'] ) ? $raw['content'] : null,
			'post_date' => isset( $raw['date'] ) ? $this->format_date( $raw['date'] ) : null,
			'post_excerpt' => isset( $raw['excerpt'] ) ? $raw['excerpt'] : null,
			'post_modified' => isset( $raw['modified'] ) ? $this->format_date( $raw['modified'] ) : null,
			'post_status' => isset( $raw['status'] ) ? $raw['status'] : null,
			'post_title' => $raw['title'],
		) );

		if ( ! $lesson->get( 'id' ) ) {
			return $this->error->add( 'lesson_creation', __( 'Error creating lesson', 'lifterlms' ) );
		}

		$this->increment( 'lessons' );

		// save the tempid
		$tempid = $this->store_temp_id( $raw, $lesson );

		// set featured image
		$this->set_featured_image( $raw['featured_image'], $lesson->get( 'id' ) );

		$lesson->set( 'parent_course', $course_id );
		$lesson->set( 'parent_section', $section_id );
		$lesson->set( 'order', $order );

		// cant trust these if they exist
		if ( isset( $raw['parent_course'] ) ) {
			unset( $raw['parent_course'] );
		}
		if ( isset( $raw['parent_section'] ) ) {
			unset( $raw['parent_section'] );
		}

		if ( ! empty( $raw['assigned_quiz'] ) ) {
			$raw['assigned_quiz'] = $this->create_quiz( $raw['assigned_quiz'], $author_id );
		}

		// set all metadata
		foreach ( array_keys( $lesson->get_properties() ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$lesson->set( $key, $raw[ $key ] );
			}
		}

		return $lesson->get( 'id' );

	}

	/**
	 * Creates a new quiz
	 * Creates all questions within the quiz as well
	 * @param    array     $raw                 raw quiz data
	 * @param    int       $fallback_author_id  optional author ID to use as a fallback if no raw author data supplied for the lesson
	 * @return   int                            WP Post ID of the Quiz
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function create_quiz( $raw, $fallback_author_id = null ) {

		$author_id = $this->get_author_id_from_raw( $raw, $fallback_author_id );

		// insert the course
		$quiz = new LLMS_QQuiz( 'new', array(
			'post_author' => $author_id,
			'post_content' => isset( $raw['content'] ) ? $raw['content'] : null,
			'post_date' => isset( $raw['date'] ) ? $this->format_date( $raw['date'] ) : null,
			'post_modified' => isset( $raw['modified'] ) ? $this->format_date( $raw['modified'] ) : null,
			'post_status' => isset( $raw['status'] ) ? $raw['status'] : null,
			'post_title' => $raw['title'],
		) );

		if ( ! $quiz->get( 'id' ) ) {
			return $this->error->add( 'quiz_creation', __( 'Error creating quiz', 'lifterlms' ) );
		}

		$this->increment( 'quizzes' );

		// set all metadata
		foreach ( array_keys( $quiz->get_properties() ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$quiz->set( $key, $raw[ $key ] );
			}
		}

		if ( isset( $raw['questions'] ) ) {
			$qarr = array();
			foreach ( $raw['questions'] as $q ) {
				$qarr[] = array(
					'id' => $this->create_question( $q, $author_id ),
					'points' => isset( $q['value'] ) ? $q['value'] : 1,
				);
			}
			update_post_meta( $quiz->get( 'id' ), '_llms_questions', $qarr );
		}

		return $quiz->get( 'id' );

	}

	/**
	 * Creates a new questino
	 * @param    array      $raw        raw question data
	 * @param    int        $author_id  optional author ID to use as a fallback if no raw author data supplied for the lesson
	 * @return   int                    WP Post ID of the question
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function create_question( $raw, $author_id ) {

		$question = new LLMS_Question( 'new', array(
			'post_author' => $author_id,
			'post_content' => isset( $raw['content'] ) ? $raw['content'] : null,
			'post_date' => isset( $raw['date'] ) ? $this->format_date( $raw['date'] ) : null,
			'post_modified' => isset( $raw['modified'] ) ? $this->format_date( $raw['modified'] ) : null,
			'post_status' => isset( $raw['status'] ) ? $raw['status'] : null,
			'post_title' => $raw['title'],
		) );

		if ( ! $question->get( 'id' ) ) {
			return $this->error->add( 'question_creation', __( 'Error creating question', 'lifterlms' ) );
		}

		$this->increment( 'questions' );

		if ( isset( $raw['options'] ) ) {
			update_post_meta( $question->get( 'id' ), '_llms_question_options', $raw['options'] );
		}

		return $question->get( 'id' );

	}

	/**
	 * Creates a new section
	 * Creates all lessons within the section data
	 * @param    array      $raw                 raw section data
	 * @param    int        $order               order within the course (starts at 1)
	 * @param    int        $course_id           WP Post ID of the parent course
	 * @param    int        $fallback_author_id  optional author ID to use as a fallback if no raw author data supplied for the lesson
	 * @return   int                             WP Post ID of the Section
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function create_section( $raw, $order, $course_id, $fallback_author_id = null ) {

		$author_id = $this->get_author_id_from_raw( $raw, $fallback_author_id );

		// insert the course
		$section = new LLMS_Section( 'new', array(
			'post_author' => $author_id,
			'post_date' => isset( $raw['date'] ) ? $this->format_date( $raw['date'] ) : null,
			'post_modified' => isset( $raw['modified'] ) ? $this->format_date( $raw['modified'] ) : null,
			'post_status' => isset( $raw['status'] ) ? $raw['status'] : null,
			'post_title' => $raw['title'],
		) );

		if ( ! $section->get( 'id' ) ) {
			return $this->error->add( 'section_creation', __( 'Error creating section', 'lifterlms' ) );
		}

		$this->increment( 'sections' );

		$section->set( 'parent_course', $course_id );
		$section->set( 'order', $order );

		if ( isset( $raw['lessons'] ) ) {
			foreach ( $raw['lessons'] as $lesson_order => $lesson ) {
				$this->create_lesson( $lesson, $lesson_order + 1, $section->get( 'id' ), $course_id, $author_id );
			}
		}

		return $section->get( 'id' );

	}

	/**
	 * Ensure raw dates are correctly formatted to create a post date
	 * falls back to current date if no date is supplied
	 * @param    string     $raw_date  raw date from raw object
	 * @return   string
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function format_date( $raw_date = null ) {

		if ( ! $raw_date ) {
			return current_time( 'mysql' );
		} else {
			return date( 'Y-m-d H:i:s' , strtotime( $raw_date ) );
		}

	}

	/**
	 * Accepts raw author data and locates an existing author by email or id or creates one
	 * @param    array     $raw  author data
	 *                           if id and email are provided will use id only if it matches the email for user matching that id in the database
	 *                           if no id found, attempts to locate by email
	 *                           if no author found and email provided, creates new user using email
	 *                           falls back to current user id
	 *                           first_name, last_name, and description can be optionally provided
	 *                           when provided will be used only when creating a new user
	 *
	 * @return   int|void        WP User ID or void when error encountered
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function get_author_id( $raw ) {

		$author_id = 0;

		// if raw is missing an ID and Email, use current user id
		if ( ! isset( $raw['id'] ) && ! isset( $raw['email'] ) ) {
			$author_id = get_current_user_id();
		} else {

			// if id is set, check if the id matches a user in the DB
			if ( isset( $raw['id'] ) && is_numeric( $raw['id'] ) ) {

				$user = get_user_by( 'ID', $raw['id'] );

				// user exists
				if ( $user ) {

					// we have a raw email
					if ( isset( $raw['email'] ) ) {

						// raw email matches found user's email
						if ( $user->user_email == $raw['email'] ) {
							$author_id = $user->ID;
						}

					} // use the author id
					else {
						$author_id = $user->ID;
					}

				}

			}

			if ( ! $author_id ) {

				if ( isset( $raw['email'] ) ) {

					// see if we have a user that matches by email
					$user = get_user_by( 'email', $raw['email'] );

					// user exists, use this user
					if ( $user ) {
						$author_id = $user->ID;
					}

				}

			}

			// no author id, create a new one using the email
			if ( ! $author_id && isset( $raw['email'] ) ) {

				$data = array(
					'role' => 'administrator',
					'user_email' => $raw['email'],
					'user_login' => LLMS_Person_Handler::generate_username( $raw['email'] ),
					'user_pass' => wp_generate_password(),
				);

				if ( isset( $raw['first_name'] ) && isset( $raw['last_name'] ) ) {
					$data['display_name'] = $raw['first_name'] . ' ' . $raw['last_name'];
					$data['first_name'] = $raw['first_name'];
					$data['last_name'] = $raw['last_name'];
				}

				if ( isset( $raw['description'] ) ) {
					$data['description'] = $raw['description'];
				}

				$author_id = wp_insert_user( apply_filters( 'llms_generator_new_author_data', $data ), $raw );

				// increment stats
				if ( ! is_wp_error( $author_id ) ) {
					$this->increment( 'authors' );
				}

			}

		}

		if ( is_wp_error( $author_id ) ) {
			return $this->error->add( $author_id->get_error_code(), $author_id->get_error_message() );
		}

		return apply_filters( 'llms_generator_get_author_id', $author_id, $raw );

	}

	/**
	 * Recieves a raw array of course, plan, section, lesson, etc data and gets an author id
	 * falls back to optionally supplied fallback id
	 * falls back to current user id
	 * @param    array     $raw                 raw data
	 * @param    int       $fallback_author_id  WP User ID
	 * @return   int|WP_Error
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function get_author_id_from_raw( $raw, $fallback_author_id = null ) {

		// if author is set, get the author id
		if ( isset( $raw['author'] ) ) {
			$author_id = $this->get_author_id( $raw['author'] );
		}

		// fallback to current user
		if ( empty( $author_id ) ) {
			$author_id = ! empty( $fallback_author_id ) ? $fallback_author_id : get_current_user_id();
		}

		return $author_id;

	}

	/**
	 * Get an array of valid LifterLMS generators
	 * @return   array
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function get_generators() {
		return apply_filters( 'llms_generators', array(
			'LifterLMS/BulkCourseExporter' => array( $this, 'generate_courses' ),
			'LifterLMS/SingleCourseCloner' => array( $this, 'generate_course' ),
			'LifterLMS/SingleCourseExporter' => array( $this, 'generate_course' ),
		) );
	}

	/**
	 * Get the results of the generate function
	 * @return   mixed       array of stats or WP_Error
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function get_results() {

		if ( $this->is_error() ) {
			return $this->error;
		} else {
			return $this->stats;
		}

	}

	/**
	 * Get a WP Term ID for a term by taxonomy and term name
	 * attempts to find a given term by name first to pervent duplicates during imports
	 * @param    string     $term_name  term name
	 * @param    string     $tax        taxonomy slug
	 * @return   int|void              term id or void when error
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function get_term_id( $term_name, $tax ) {

		$term = get_term_by( 'name', $term_name, $tax, ARRAY_A );

		// not found, create it
		if ( ! $term ) {

			$term = wp_insert_term( $term_name, $tax );

			if ( is_wp_error( $term ) ) {
				return $this->error->add( 'term-creation', sprintf( __( 'Error creating new term "%s"', 'lifterlms' ), $term_name ) );
			} else {
				$this->increment( 'terms' );
			}

		}

		return $term['term_id'];

	}

	/**
	 * Updates course and lesson prerequisites
	 * If the prerequisite was included in the import, updates to the new imported version
	 * If the prereq is not included but the source matches, leaves the prereq intact as long as the prereq exists
	 * Otherwise removes prerequisite data from the new course / lesson
	 *
	 * Removes prereq track associations if there's no source or source doesn't match
	 * or if the track doesn't exist
	 *
	 * @return   void
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function handle_prerequisites() {

		foreach ( array( 'course', 'lesson' ) as $obj_type ) {

			$ids = $this->tempids[ $obj_type ];

			// courses have two kinds of prereqs
			$has_prereq_param = ( 'course' === $obj_type ) ? 'course' : null;

			// loop through all then created lessons
			foreach ( $ids as $old_id => $new_id ) {

				// instantiate the new instance of the object
				$obj = llms_get_post( $new_id );

				// if this is a course and there isn't a source or the source doesn't match the current site
				// we should remove the track prerequisites
				if ( 'course' === $obj_type && ( ! isset( $raw['_source'] ) || $raw['_source'] !== get_site_url() ) ) {

					// remove prereq track settings
					if ( $obj->has_prerequisite( 'track' ) ) {
						$obj->set( 'prerequisite_track', 0 );
						if ( ! $obj->has_prerequisite( 'course' ) ) {
							$obj->set( 'has_prerequisite', 'no' );
						}
					}

				}

				// if the object has a prereq
				if ( $obj->has_prerequisite( $has_prereq_param ) ) {

					// get the old preqeq's id
					$old_prereq = $obj->get( 'prerequisite' );

					// if the old prereq is a key in the array of created objects
					// we can replace it with the new id
					if ( in_array( $old_prereq, array_keys( $ids ) ) ) {

						$obj->set( 'prerequisite', $ids[ $old_prereq ] );

					} // sources do not match so we'll clear the prereq
					elseif ( ! isset( $raw['_source'] ) || $raw['_source'] !== get_site_url() ) {

						$obj->set( 'has_prerequisite', 'no' );
						$obj->set( 'prerequisite', 0 );

					} // check to be sure old prereq still exists
					else {
						$post = get_post( $old_prereq );
						// post doesn't exist or the post type doesn't match, get rid of it...
						if ( ! $post || $obj_type !== $post->post_type ) {

							$obj->set( 'has_prerequisite', 'no' );
							$obj->set( 'prerequisite', 0 );

						}

					}

				}

			}

		}

	}

	/**
	 * Increments a stat in the stats object
	 * @param    string     $type  key of the stat to increment
	 * @return   void
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function increment( $type ) {
		if ( isset( $this->stats[ $type ] ) ) {
			$this->stats[ $type ]++;
		}
	}

	/**
	 * Determines if there was an error during the running of the generator
	 * @return   boolean     true when there was an error, false otherwise
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function is_error() {
		return ( $this->error->get_error_messages() );
	}

	/**
	 * Saves an image (from URL) to the media library and sets it as the featured image for a given post
	 * @param    string     $url_or_raw  array of raw data or URL to an image
	 * @param    int        $post_id     WP Post ID
	 * @return   void
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function set_featured_image( $url_or_raw, $post_id ) {

		$image_url = '';

		if ( is_array( $url_or_raw ) && isset( $url_or_raw['featured_image'] ) ) {
			$image_url = $url_or_raw['featured_image'];
		} elseif ( is_string( $url_or_raw ) ) {
			$image_url = $url_or_raw;
		}

		if ( ! empty( $image_url ) ) {

			global $wpdb;

			// save the image in the medialib
			$img_src = media_sideload_image( $image_url, $post_id, null, 'src' );

			if ( ! is_wp_error( $img_src ) ) {
				$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE guid = %s", array( $img_src ) ) );
				set_post_thumbnail( $post_id, $id );
			}

		}

	}

	/**
	 * Sets the generator to use for the current instance
	 * @param    string     $generator  generator string, eg: "LifterLMS/SingleCourseExporter"
	 * @return   WP_Error|void
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	public function set_generator( $generator = null ) {

		if ( empty( $generator ) ) {

			// raw is missing a generator... oh noes...
			if ( ! isset( $this->raw['_generator'] ) ) {

				return $this->error->add( 'missing-generator', __( 'The supplied file cannot be processed by the importer.', 'lifterlms' ) );

			} else {

				return $this->set_generator( $this->raw['_generator'] );

			}

		}

		$generators = $this->get_generators();

		// invalid generator
		if ( ! in_array( $generator, array_keys( $generators ) ) ) {
			return $this->error->add( 'invalid-generator', __( 'Invalid generator supplied', 'lifterlms' ) );
		} else {
			$this->generator = $generators[ $generator ];
		}

	}

	/**
	 * Accepts a raw object, finds the raw id and stores it
	 * @param    array     $raw  array of raw data
	 * @param    obj       $obj  the LLMS Post Object generated from the raw data
	 * @return   mixed           raw id when present, false if no raw id was found
	 * @since    3.3.0
	 * @version  3.3.0
	 */
	private function store_temp_id( $raw, $obj ) {

		if ( isset( $raw['id'] ) ) {

			// store the id on the meta table
			$obj->set( 'generated_from_id', $raw['id'] );

			// store it in the object for prereq handling later
			$this->tempids[ $obj->get( 'type' ) ][ $raw['id'] ] = $obj->get( 'id' );

			return $raw['id'];

		}

		return false;

	}

}
