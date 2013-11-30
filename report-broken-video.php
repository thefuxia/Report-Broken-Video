<?php # -*- coding: utf-8 -*-
declare( encoding = 'UTF-8' );
/**
 * Plugin Name: Report Broken Video
 * Text Domain: plugin_rbv
 * Domain Path: /lang
 * Description: Adds a button to report broken videos
 * Version:     2013.11.30
 * Required:    3.3
 * Author:      Thomas Scholz
 * Author URI:  http://toscho.de
 * License:     GPL
 *
 * @todo remove button for already reported posts.
 *
 * Report Broken Video, Copyright (C) 2012 Thomas Scholz
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

! defined( 'ABSPATH' ) and exit;

add_action( 'after_setup_theme', array ( 'Report_Broken_Video', 'get_instance' ) );

class Report_Broken_Video
{
	/**
	 * Internal variables prefix.
	 *
	 * @type string
	 */
	protected $prefix = 'rbv';

	/**
	 * Name for a field that is hidden per CSS and filled by spammers only.
	 *
	 * @type string
	 */
	protected $hidden_field = 'no_fill';

	/**
	 * URL of the current page.
	 *
	 * @see __construct()
	 * @type string
	 */
	protected $current_url = '';

	/**
	 * nonce = number used once, unique identifier for request validation.
	 *
	 * @type string
	 */
	protected $nonce_name = 'rbv_nonce';

	/**
	 * On which post types do we want to show the form?
	 *
	 * @type array
	 */
	protected $post_types = array ( 'post' );

	/**
	 * Creates a new instance. Called on 'after_setup_theme'.
	 *
	 * @see    __construct()
	 * @return void
	 */
	public static function get_instance()
	{
		static $instance = NULL;

		NULL === $instance and $instance = new self;

		return $instance;
	}

	/**
	 * Set actions, filters and basic variables, load language.
	 *
	 * @uses apply_filters() 'rbv_button' as action name
	 * 		'rbv_auto_add_button' to append the form automatically
	 * 		'rbv_post_types' for supported post types (default: 'post')
	 */
	public function __construct()
	{
		// Use
		// do_action( 'rbv_button' );
		// in your theme to show the button.
		add_action( $this->prefix . '_button', array ( $this, 'print_button' ) );

		// Use
		// add_filter( 'rbv_auto_add_button', '__return_false' );
		// to turn automatic buttons off.
		if ( apply_filters( $this->prefix . '_auto_add_button', TRUE ) )
			add_filter( 'the_content', array ( $this, 'append_button' ), 50 );

		$this->current_url = $_SERVER['REQUEST_URI'];

		// Use
		// add_filter( 'rbv_post_types', 'your_callback' );
		// to add more post types.
		$this->post_types  = apply_filters(
			$this->prefix . '_post_types',
			$this->post_types
		);

		load_plugin_textdomain(
			'plugin_rbv',
			FALSE,
			basename( __DIR__ ) . '/lang'
		);
	}

	/**
	 * Handler for the action 'rbv_button'. Prints the button.
	 *
	 * @return void
	 */
	public function print_button()
	{
		print $this->button_form();
	}

	/**
	 * Handler for content filter.
	 *
	 * @param  string $content Existing content
	 * @return string
	 */
	public function append_button( $content )
	{
		if ( is_feed() )
			return $content;

		return $content . $this->button_form();
	}

	/**
	 * Returns the button form or a feedback message after submit.
	 *
	 * @return string
	 */
	public function button_form()
	{
		if ( 'POST' != $_SERVER['REQUEST_METHOD'] )
			return $this->get_form();

		return $this->handle_submit();
	}

	/**
	 * Returns the form or an empty string.
	 *
	 * @uses apply_filters() 'rbv_show_form' to suppress form output.
	 * @return string
	 */
	public function get_form()
	{
		global $post;

		if ( empty ( $post )
			or ! in_array( get_post_type( $post ), $this->post_types )
			// You may disable the form conditionally. For example: restrict it
			// to posts with post-format 'video'.
			or ! apply_filters( $this->prefix . '_show_form', TRUE, $post )
		)
			return '';

		$post_id      = (int) $post->ID;
		$url          = esc_attr( $this->current_url );
		$hidden       = $this->get_hidden_field();
		$nonce        = wp_create_nonce( __FILE__ );
		$button_text  = __( 'Report broken video', 'plugin_rbv' );

		$form = <<<RBVFORM
<form method='post' action='$url' class='{$this->prefix}_form'>
	<input type='hidden' name='{$this->prefix}[$this->nonce_name]' value='$nonce' />
	<input type='hidden' name='{$this->prefix}[post_id]' value='$post_id' />
	<input type='submit' name='{$this->prefix}[report]' value='$button_text' />
</form>
RBVFORM;

		return $form;
	}

	/**
	 * Hidden text field as spam protection.
	 *
	 * @return string
	 */
	protected function get_hidden_field()
	{
		// prevent doubled IDs if you use the_content() on archive pages.
		static $counter = 0;

		$field    = $this->hidden_field . "_$counter";
		$counter += 1;
		$title    = esc_attr__( 'Leave this empty', 'plugin_rbv' );

		return "<style scoped>#$field{display:none}</style>
			<input name='{$this->prefix}[$field]' title='$title' />";
	}

	/**
	 * Handle form submission.
	 *
	 * @uses apply_filters() 'rbv_recipient' to set the mail recipient.
	 * 		'rbv_from' to set the 'From' header.
	 * @return string
	 */
	protected function handle_submit()
	{
		if ( ! isset ( $_POST[ $this->prefix ] )
			or '' == trim( implode( '', $_POST[ $this->prefix ] ) )
			or ! wp_verify_nonce( $_POST[ $this->prefix ][ $this->nonce_name ], __FILE__ )
			or ! empty ( $_POST[ $this->prefix ][ $this->hidden_field ] )
			or   empty ( $_POST[ $this->prefix ][ 'post_id' ] )
		)
			return $this->get_form();

		$blog_name = get_bloginfo( 'name' );

		// Pro tempore. You may add an option for this in 'wp-admin'.
		$recipient = get_option( 'admin_email' );
		$recipient = apply_filters( $this->prefix . '_recipient', $recipient );

		$subject   = sprintf(
			__( 'Broken video on %s', 'plugin_rbv' ),
			$blog_name
		);
		$message  = sprintf(
			__( "There is a broken video on:\n <%s>", 'plugin_rbv' ),
			get_permalink( (int) $_POST[ $this->prefix ][ 'post_id' ] )
		);
		$from     = "From: [RBV] $blog_name <$recipient>";
		$from     = apply_filters( $this->prefix . '_from', $from );
		$send     = wp_mail( $recipient, $subject, $message, $from );

		$error    = __(
			'Sorry, we could not send the report. May we ask you to use the contact page instead?',
			'plugin_rbv'
		);
		$success  = __( "Thank you! We will take a look.", 'plugin_rbv' );
		$feedback = $send ? $success : $error;

		return "<p class='{$this->prefix}_result'>$feedback</p>";
	}
}