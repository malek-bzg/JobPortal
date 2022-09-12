<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Field_Editor_Conditionals
 *
 * @since 1.7.10
 *
 */
class WP_Job_Manager_Field_Editor_Conditionals {

	/**
	 * @var \WP_Job_Manager_Field_Editor
	 */
	private $core;

	/**
	 * @var string Slug representing type (job/resume)
	 */
	public $slug;
	/**
	 * @var array|boolean Logic configuration
	 */
	public $logic = null;

	/**
	 * @var array|boolean Listing fields
	 */
	public $fields;

	public $js_config = array();

	/**
	 * WP_Job_Manager_Field_Editor_Conditionals constructor.
	 *
	 * @param $core \WP_Job_Manager_Field_Editor
	 */
	public function __construct( $core ) {

		$this->core = $core;
		$this->slug = $this->get_slug();
		$this->hooks();

		add_action( 'wp', array( $this, 'add_fields_filter' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

	}

	/**
	 *  Form Output
	 *
	 *
	 * @since 1.7.10
	 *
	 */
	public function form(){

		if( ! $logic = $this->get_logic() ){
			return;
		}

		$this->output_hidden_fields();

		$this->localize( $logic, $this->get_fields() );
		wp_enqueue_script( 'jmfe-conditionals' );

		if( get_option( 'jmfe_logic_show_use_velocity', false ) || get_option( 'jmfe_logic_hide_use_velocity', false ) ){
			wp_enqueue_script( 'jmfe-vendor-velocity' );
		}
	}

	/**
	 * Output hidden inputs for repeatable fields
	 *
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function output_hidden_fields() {

		$repeatables = $this->get_repeatable_fields();

		if( empty( $repeatables ) ){
			return;
		}

		foreach ( (array) $repeatables as $repeatable ) {
			$id = esc_attr( $repeatable ) . '-is-visible';
			// Everything is considered "visible" at first, once page is loaded then logic is applied, and will update this value (even if hidden by default)
			echo '<input type="hidden" name="' . $id . '" id="' . $id . '" value="yes" />';
		}

	}

	/**
	 * Check if meta key value exists on form submit
	 *
	 * To check the conditional logic on the frontend, fields will have values present (even if empty) in $_POST, otherwise if they are hidden,
	 * there will be no values (at least key) in POST or FILES.  This also checks for repeatable fields, which are the exception to this rule,
	 * and as such, there are hidden inputs that are added to handle these.
	 *
	 *
	 * @since 1.8.0
	 *
	 * @param $meta_key
	 * @param $config
	 * @param $logic_config
	 *
	 * @return bool
	 */
	public function field_present_in_submit( $meta_key, $config, $logic_config ){

		// Standard fields will be in $_POST under meta key as the key
		if ( array_key_exists( $meta_key, $_POST ) ) {
			return true;
		}

		// File fields will be in $_FILES not $_POST
		if( is_array( $_FILES ) && array_key_exists( $meta_key, $_FILES ) && $config['type'] === 'file' ){
			return true;
		}

		// Repeatable fields will have a value in $_POST under "repeated-row-METAKEY" signifying the index for each repeatable item
		// We're not really concerned with those values as we will just let core handle validation, we just check if the key exists that means
		$repeatable_fields = $this->get_repeatable_fields();

		if( ! empty( $repeatable_fields ) && in_array( $meta_key, $repeatable_fields ) ){

			if( $this->repeatable_is_visible( $meta_key ) ){
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if repeatable field is visible or not
	 *
	 *
	 * @since 1.8.0
	 *
	 * @param $meta_key
	 *
	 * @return bool
	 */
	public function repeatable_is_visible( $meta_key ){

		// Repeatable is visible as at least one entry was showing
		if( array_key_exists( "repeated-row-{$meta_key}", $_POST ) ){
			return true;
		}

		// If repeatable is visible, but no repeatable fields have been added/exist, we have to check for our hidden
		// field to see if the logic resulted in the field being visible or not.
		if( array_key_exists( "{$meta_key}-is-visible", $_POST ) && $_POST["{$meta_key}-is-visible"] == 'yes' ){
			return true;
		}

		return false;
	}

	/**
	 * Check if logic should set field as not required
	 *
	 * This method should check if, based on evaluated logic, a field should be set as not required,
	 * meaning it may be a required field, that is not showing based on logic.
	 *
	 * @since 1.7.10
	 *
	 * @param $meta_key
	 * @param $config
	 * @param $logic
	 *
	 * @return bool
	 */
	public function logic_not_required( $meta_key, $config, $logic ){

		$not_required = false;

		$js_config = $this->get_js_config( $logic );
		$default_hidden = $js_config['default_hidden'];

		foreach( (array) $logic as $slug => $lcfg ){

			if( ! in_array( $meta_key, $lcfg['fields'] ) ){
				continue;
			}

			$hide_by_default = is_array( $default_hidden ) && in_array( $meta_key, $default_hidden );

			// Now we need to check if any logic evaluates to true, and "shows" this field

			// Nothing will be set in POST if field is ACTUALLY hidden (whereas it would be empty string if nothing was entered) -- meaning logic resulted in hiding that field
			// Only caveat is with file uploads, which will be under $_FILES instead of $_POST

			if( ! $this->field_present_in_submit( $meta_key, $config, $lcfg ) ){

				// If meta key is found in a group with an action/type that would hide the field, set required false in listing field config
				if ( ( $hide_by_default && $lcfg['type'] === 'show' ) || ( ! $hide_by_default && $lcfg['type'] === 'hide' ) ) {
					$not_required = true;
					break;
				}

			}

		}

		return $not_required;

	}

	/**
	 * Set Required Fields False
	 *
	 *
	 * @since 1.7.10
	 *
	 * @param $listing_fields
	 *
	 * @return mixed
	 */
	public function set_required_false( $listing_fields ){

		if( ! $logic = $this->get_logic() ){
			return $listing_fields;
		}

		$active_fields = $this->get_group_fields();

		// Loop through groups (job, company, resume_fields)
		foreach( (array) $listing_fields as $group => $fields ){

			// Loop through meta keys
			foreach( (array) $fields as $meta_key => $config ){

				$required = array_key_exists( 'required', $config ) && $config['required'] === true;

				// If this field is configured as a REQUIRED field,
				// Set required false if one of our meta keys is in active logic configuration (to prevent core from handling validation)
				if( $required && in_array( $meta_key, $active_fields ) && $this->logic_not_required( $meta_key, $config, $logic ) ){
					$listing_fields[ $group ][ $meta_key ][ 'required' ] = false;
				}

			}


		}

		return $listing_fields;
	}

	/**
	 * Get Conditional Group Fields
	 *
	 *
	 * @since 1.7.10
	 *
	 * @param bool $logic
	 * @param bool $type_only
	 *
	 * @return array
	 */
	public function get_group_fields( $logic = false, $type_only = false ){

		if( ! $logic ){
			$logic = $this->get_logic();
		}

		$group_fields = array();

		foreach ( (array) $logic as $group => $gcfg ) {

			if( ! $type_only || ( $type_only && $gcfg['type'] === $type_only ) ){
				$group_fields = array_merge( $group_fields, $gcfg['fields'] );
			}

		}

		return $group_fields;
	}

	/**
	 * Get Fields to Hide by Default
	 *
	 * By default, if a field has "show" configuration, it will be added to the list of
	 * default hidden fields.  When using conditional logic, the majority of the time it will be
	 * to "show" fields under certain situations, that is why by default fields are hidden if they
	 * have logic configuration.  You can return false to the filter to fields as shown by default.
	 *
	 * @since 1.7.10
	 *
	 * @param $logic
	 *
	 * @return array|bool      An array of fields to hide by default, or false to show fields by default
	 */
	public function default_hidden( $logic ){

		$hidden_fields = $this->get_group_fields( $logic, 'show' );

		return apply_filters( 'field_editor_conditionals_default_hidden_fields', $hidden_fields, $this );
	}

	/**
	 * Get Velocity.JS Show Config
	 *
	 *
	 * @since 1.8.1
	 *
	 * @return bool|mixed|void
	 */
	public function get_show_method(){

		if( ! get_option( 'jmfe_logic_show_use_velocity', false ) ){
			return false;
		}

		$show_method = array(
			'duration' => (int) get_option( 'jmfe_logic_show_method_duration', 400 ),
			'easing'   => get_option( 'jmfe_logic_show_method_easing', 'spring' ),
			'method'   => get_option( 'jmfe_logic_show_method', 'slideDown' )
		);

		return apply_filters( 'field_editor_conditionals_get_show_method_config', $show_method, $this );
	}

	/**
	 * Get Velocity.JS Hide Config
	 *
	 *
	 * @since 1.8.1
	 *
	 * @return bool|mixed|void
	 */
	public function get_hide_method() {

		if ( ! get_option( 'jmfe_logic_hide_use_velocity', false ) ) {
			return false;
		}

		$hide_method = array(
			'duration' => (int) get_option( 'jmfe_logic_hide_method_duration', 400 ),
			'easing'   => get_option( 'jmfe_logic_hide_method_easing', 'spring' ),
			'method'   => get_option( 'jmfe_logic_hide_method', 'slideUp' )
		);

		return apply_filters( 'field_editor_conditionals_get_hide_method_config', $hide_method, $this );
	}

	/**
	 * Get JS Conditional Config
	 *
	 *
	 * @since 1.7.10
	 *
	 * @param bool|array $logic
	 * @param bool|array $meta_keys
	 *
	 * @return array
	 */
	public function get_js_config( $logic = false, $meta_keys = false ){

		if( $logic ){
			$logic = $this->get_logic();
		}

		$default_hidden = $this->default_hidden( $logic );
		$case_sensitive = get_option( 'jmfe_logic_case_sensitive', false ) == 1 ? true : false;

		$this->js_config = array(
			'delay'          => get_option( 'jmfe_logic_debounce_delay', 250 ), // debounce delay on input (amount of time to wait on each input change before checking logic) -- should be in milliseconds (1000ms = 1s)
			'group_types'    => self::get_group_types( $default_hidden ),
			'case_sensitive' => $case_sensitive,
			'chosen_fields'  => $this->get_chosen_fields( $meta_keys ),
			'default_hidden' => $default_hidden,
			'repeatables'    => $this->get_repeatable_fields(),
			'show_method'    => $this->get_show_method(),
			'hide_method'    => $this->get_hide_method(),
			'custom_values'  => $this->get_custom_values()
		);

		return apply_filters( 'field_editor_conditionals_front_js_config', $this->js_config, $this );
	}

	/**
	 * Get Custom Values to use in Logic Configuration
	 *
	 * This method pulls custom values to use in logic on frontend, when an input element may not be available to obtain a value
	 * from.  See below for example array format that should be returned in filter called.
	 *
	 *      $custom_values = array(
	 *          'meta_key_for_check' => array(
	 *              'value' => 'some_static_value'
	 *          ),
	 *          'admin_meta_key_check' => array(
	 *              'value'  => 'some default value if not available in listing meta',
	 *              'source' => 'listing'
	 *          )
	 *      );
	 *
	 * @since 1.8.1
	 *
	 */
	public function get_custom_values(){

		$admin_values = $this->get_admin_only_values();

		// For now only admin values are automatically included, but this could be changed later on
		$custom_values = apply_filters( 'field_editor_conditionals_front_end_custom_values', $admin_values, $this->slug, $this );

		foreach( (array) $custom_values as $meta_key => $config ){

			if( array_key_exists( 'source', $config ) ){

				// Attempt to pull value from listing, or use default value passed (empty string if not passed)
				if( $config['source'] === 'listing' ){
					$default_value = array_key_exists( 'value', $config ) ? $config['value'] : '';
					$custom_values[ $meta_key ]['value'] = $this->get_custom_value_from_listing( $meta_key, $default_value );
				}

			}

		}

		return apply_filters( 'field_editor_conditionals_front_end_custom_values_processed', $custom_values, $this->slug, $this );
	}

	/**
	 * Get admin only field values
	 *
	 * This method calls the same filter that is called in the admin area to allow including admin only fields in logic.  This is
	 * done here for frontend to automatically use the same filter, to determine what those meta keys are, and automatically try
	 * and get the value to set in a javascript object so the JS can check the value on the frontend logic.
	 *
	 *
	 * @since 1.8.1
	 *
	 * @return array
	 */
	public function get_admin_only_values(){

		/**
		 * Single or multi-dimensional arrays can be passed to this filter (this is the same filter from admin area).
		 *
		 * Value passed back can be simple flat array: array( 'some_admin_meta_key' )
		 * OR
		 * Value passed can be multi-dimensional array, specifying a default value to use if nothing set on listing yet: array( 'some_admin_meta_key' => array( 'default' => 'xxx' ) );
		 *
		 * The DEFAULT value will be used whenever there is a new listing, or there is no value saved on existing ones
		 */
		$admin_only_fields = apply_filters( "field_editor_conditional_logic_custom_value_{$this->slug}_admin_fields", array(), $this );

		$admin_custom_values = array();

		foreach( (array) $admin_only_fields as $maybe_index => $maybe_config ){

			// Multi-dimensional array passed
			if( is_string( $maybe_index ) ){

				$admin_custom_values[ $maybe_index ] = array(
					'source' => 'listing',
				);

				// If default was passed in array, set the value to that initially (for use as default)
				if( array_key_exists( 'default', $maybe_config ) ){
					$admin_custom_values[ $maybe_index ][ 'value' ] = $maybe_config['default'];
				}

			} else {

				// Flat array was passed, all we set is source, no default
				$admin_custom_values[ $maybe_config ] = array( 'source' => 'listing' );

			}

		}

		return $admin_custom_values;
	}

	/**
	 * Attempt to get custom value from listing meta
	 *
	 *
	 * @since 1.8.1
	 *
	 * @param        $meta_key
	 * @param string $default
	 *
	 * @return mixed|string
	 */
	public function get_custom_value_from_listing( $meta_key, $default = '' ){

		$listing_id = $this->get_listing_id();

		if( ! empty( $listing_id ) ){

			$custom_value = get_post_meta( $listing_id, $meta_key, true );

			if ( empty( $custom_value ) ) {
				// If no value pulled from listing, try prepending underscore (as user may have entered meta key without underscore)
				$custom_value = get_post_meta( $listing_id, "_{$meta_key}", true );
			}

			// If still unable to get any type of value from listing, and the default value is not empty string, use that value
			if( empty( $custom_value ) && ! empty( $default ) ){
				$custom_value = $default;
			}

		} else {

			$custom_value = $default;

		}

		return apply_filters( 'field_editor_conditionals_front_end_get_custom_value', $custom_value, $meta_key, $default, $this->slug, $this );
	}

	/**
	 * Attempt to get an existing listing ID
	 *
	 *
	 * @since 1.8.1
	 *
	 * @return bool
	 */
	public function get_listing_id() {

		$listing_id = false;

		// Check if action is to edit a listing
		$is_edit_action = ( array_key_exists( 'action', $_GET ) && $_GET['action'] === 'edit' );

		if ( $is_edit_action ) {
			$listing_id = $this->get_edit_listing_id();
		}

		return $listing_id;
	}

	/**
	 * Return All Meta Keys that are Chosen Field Types
	 *
	 *
	 * @since 1.7.10
	 *
	 * @param bool $meta_keys
	 *
	 * @return array|bool
	 */
	public function get_chosen_fields( $meta_keys = false ){

		$chosen_enabled = apply_filters( 'job_manager_chosen_enabled', true );

		if( ! $chosen_enabled ){
			return false;
		}

		if( ! $meta_keys ){
			$meta_keys = $this->get_fields();
		}

		$chosen_field_types = array( 'term-multiselect', 'multiselect' );
		$addon_chosen_fields = array( 'job_region', 'resume_region' );

		$chosen_fields = array();

		foreach( (array) $meta_keys as $meta_key => $config ){

			if( in_array( $meta_key, $addon_chosen_fields ) || in_array( $config['type'], $chosen_field_types ) ){
				$chosen_fields[] = $meta_key;
			}

		}

		return apply_filters( 'field_editor_conditionals_get_chosen_fields', $chosen_fields, $meta_keys, $this );
	}

	/**
	 * Localize JS
	 *
	 *
	 * @since 1.7.10
	 *
	 * @param $logic
	 * @param $meta_keys
	 */
	public function localize( $logic, $meta_keys ){

		wp_localize_script( 'jmfe-conditionals', 'jmfe_js_logic_config', $this->get_js_config( $logic, $meta_keys ) );

		wp_localize_script( 'jmfe-conditionals', 'jmfe_conditional_logic', $logic );
		wp_localize_script( 'jmfe-conditionals', 'jmfe_logic_meta_keys', $this->build_meta_keys_js( $logic, $meta_keys ) );

	}

	/**
	 * Build Meta Key JS Configurations
	 *
	 * This method will loop through all group/logic configuration, and build an array of data using the
	 * structure below, which will be converted to JSON for use in the javascript on the frontend.
	 *
	 * This is used for handling jQuery callbacks on input changes for meta keys, which is built using
	 * the configuration returned from this method.
	 *
	 * Example:
	 *
	 * 'meta_key' => array(
	 *     'type' => 'text',
	 *     'logic' => array(
	 *          array(
	 *              'group' => 'logic_group',
	 *              'section' => section_array_index,
	 *              'row' => row_array_index
	 *          ),
	 *          array(
	 *              'group' => 'logic_group_2',
	 *              'section' => section_array_index_2,
	 *              'row' => row_array_index_2
	 *          ),
	 *      )
	 * )
	 *
	 * @since 1.7.10
	 *
	 * @param array $config             Group logic configuration array
	 * @param array $meta_keys_config   Meta key configuration array (should be array with meta keys as array keys)
	 *
	 * @return array    Will return array with meta key as array key, and logic under logic key in array (see doc for example)
	 */
	public function build_meta_keys_js( $config , $meta_keys_config ){

		$mk_js = array();
		$custom_values = ! empty( $this->js_config ) && array_key_exists( 'custom_values', $this->js_config ) ? (array) $this->js_config['custom_values'] : (array) $this->get_custom_values();

		// Loop through each group configuration
		foreach( (array) $config as $group => $gcfg ){

			// Group doesn't have any logic configuration
			if( ! array_key_exists( 'logic', $gcfg ) || empty( $gcfg['logic'] ) ){
				continue;
			}

			// Loop through each logic section
			foreach( (array) $gcfg['logic'] as $section_id => $rows ){

				// Loop through each logic row
				foreach( (array) $rows as $row_id => $logic ){

					$meta_key = $logic['check'];

					// May not be a meta key logic, or meta key may no longer exist (removed, etc)
					if( ! array_key_exists( $meta_key, $meta_keys_config ) ){
						continue;
					}

					// If meta key not already setup by previous logic config, set defaults now
					if( ! array_key_exists( $meta_key, $mk_js ) ){

						$mk_js[ $meta_key ] = array(
							'type' => str_replace( '-', '_', $meta_keys_config[ $meta_key ]['type'] ),
							'logic' => array(),
						);

						// Set value in array if this is a custom value (non-input pulled value)
						if( array_key_exists( $meta_key, $custom_values ) ){
							$mk_js[ $meta_key ][ 'type' ] = 'custom_value';
						}

					}

					// Add logic config for meta key to logic array of arrays
					$mk_js[ $meta_key ][ 'logic' ][] = array(
						'group' => $group,
						'section' => $section_id,
						'row' => $row_id
					);

				} // close each logic row


			} // close each logic section


		} // close each group config

		return apply_filters( 'field_editor_conditionals_front_meta_keys_js', $mk_js, $this );
	}

	/**
	 * Register Scripts and Styles
	 *
	 *
	 * @since 1.7.10
	 *
	 */
	public function register_assets(){

		if ( defined( 'WPJMFE_DEBUG' ) && WPJMFE_DEBUG == true ) {

			$cjs = 'build/conditionals.js';

		} else {

			$cjs = 'conditionals.min.js';

		}

		wp_register_script( 'jmfe-conditionals', WPJM_FIELD_EDITOR_PLUGIN_URL . "/assets/js/{$cjs}", array( 'jquery' ), WPJM_FIELD_EDITOR_VERSION, true );
		wp_register_script( 'jmfe-vendor-velocity', WPJM_FIELD_EDITOR_PLUGIN_URL . '/assets/js/velocity.min.js', array( 'jquery' ), WPJM_FIELD_EDITOR_VERSION, true );

	}

	/**
	 * Get Group Types
	 *
	 *
	 * @since 1.7.10
	 *
	 * @param bool $default_hidden
	 *
	 * @return mixed|void
	 */
	public static function get_group_types( $default_hidden = false ){

		$types = apply_filters( 'field_editor_conditionals_group_types', array(
			'show'    => array(
				'label' => __( 'Show', 'wp-job-manager-field-editor' ),
				'icon'  => 'unhide',
				'opposite' => 'hide',
			),
			'hide'    => array(
				'label'   => __( 'Hide', 'wp-job-manager-field-editor' ),
				'icon'    => 'hide',
				'opposite' => 'show',
			),
			'disable' => array(
				'label'   => __( 'Disable', 'wp-job-manager-field-editor' ),
				'icon'    => 'lock',
				'default' => 'enable',
				'opposite' => 'enable'
			),
			'enable'  => array(
				'label' => __( 'Enable', 'wp-job-manager-field-editor' ),
				'icon'  => 'unlock',
				'opposite' => 'disable'
			),
		));

		if( $default_hidden ){
			$types[ 'show' ][ 'default' ] = 'hide';
		} else {
			$types[ 'hide' ][ 'default' ] = 'show';
		}

		return $types;
	}

	/**
	 * Get Fields Placeholder
	 *
	 *
	 * @since 1.7.10
	 *
	 * @return bool
	 */
	public function get_fields(){ return false; }

	/**
	 * Get listing ID when editing listing placeholder
	 *
	 *
	 * @since 1.8.1
	 *
	 * @return bool
	 */
	public function get_edit_listing_id(){ return false; }

	/**
	 * Get Logic Placeholder
	 *
	 *
	 * @since 1.7.10
	 *
	 * @return null
	 */
	public function get_logic(){ return null; }

	/**
	 * Get Repeatable Fields Placeholder
	 *
	 *
	 * @since 1.8.0
	 *
	 * @return array
	 */
	public function get_repeatable_fields() { return array(); }

	/**
	 * Get Slug (job/resume)
	 *
	 *
	 * @since 1.8.1
	 *
	 * @return string
	 */
	public function get_slug(){
		return 'unknown';
	}
}