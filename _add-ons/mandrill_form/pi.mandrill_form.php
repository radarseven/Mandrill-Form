<?php

/**
 * Require plugin helper
 */
require_once( 'pluginhelper.php' );
use Mandrill_form_plugin_helper as Helper;

/**
 * Require Mandrill API class
 */
require_once( 'lib/Mandrill.php' );


class Plugin_mandrill_form extends Plugin {

	/**
	 * Meta data
	 * @var array
	 */
	public $meta = array(
		'name'				=> 'Mandrill Form',
		'version'			=> '0.9',
		'author'			=> 'Michael Reiner | Chad Clark',
		'author_url'		=> 'http://radarseven.com | http://chadjclark.com/',
	);

	function __construct()
	{
		parent::__construct();

		/**
		 * Get the environment
		 */
		$this->env = $env = Environment::detect();

		/**
		 * Get add-on config
		 */
		$this->config = $this->getConfig();

		/**
		 * Filter the POST data
		 */	
		$this->post = Helper::getPost();

		/**
		 * Output buffer
		 */
		$this->output = '';

		/**
		 * Layout vars
		 */
		$this->vars = false;
	}

	/**
	 * Index
	 * @return none
	 */
	public function index() 
	{
		/**
		 * Fetch Tag Parameters
		 */
		$this->params = $this->fetchParams();

		/**
		 * Check for POST
		 */
		if( Helper::isPost() )
		{
			/**
			 * POST, pass to handler
			 */
			$this->handlePost();
		}

		/**
		 * Check for session `status`
		 * This would only be set if there were a successfull submission and redirected to self without POST
		 */
		if( $this->session->exists( 'mandrill_form_status' ) && $this->session->get( 'mandrill_form_status' ) )
		{
			/**
			 * Set the template var `success` to true
			 */
			$this->vars['success'] = true;

			/**
			 * Delete the `mandrill_form_status` session key
			 */
			$this->session->delete( 'mandrill_form_status' );
		}

		/**
		 * Get and return the output
		 */
		$output = $this->getOutput();
		return $output;
	}

	/**
	 * If form is POSTed, handle it.
	 * @return none
	 */
	protected function handlePost()
	{
		/**
		 * Assign POST data to the template tag pair `post`
		 * @var $this->post
		 * 
		 * Usage:
		 * <pre>
		 * {{ post }}
		 * 	{{ input_name }}
		 *  {{ another_input name }}
		 * {{ /post }}
		 * </pre>
		 * 
		 * Can be used to pre-populate a form field from the POST data.
		 * <pre>
		 * <input type="text" name="first_name" value="{{ post }}{{ first_name }}{{ /post }}" />
		 * </pre>
		 */
		foreach( $this->post as $k => $v )
		{
			$this->vars['post'][] = array( $k => $v );
		}
		
		/**
		 * Non-production logging of POST data
		 */
		if( $this->env != 'live' )
		{
			Log::info( Environment::detect(), 'add-on', 'mandrill' );
			Log::info( json_encode( $this->post ), 'add-on', 'mandrill' );
		}

		/**
		 * Try to block some SPAM
		 */
		if( isset( $this->post['sillySpammer'] ) && $this->post['sillySpammer'] != '' )
		{
			if( $this->params['spam_killah_redirect'] != '' )
			{
				/**
				 * Redirect, it's a SPAM bot!
				 */
				Url::redirect( $this->params['spam_killah_redirect'] );
			}

			echo 'Silly Spammer!';
			exit;
		}

		/**
		 * Validation
		 * @param array Required fields array from config?
		 */
		if( isset( $this->params['required_fields'] ) && is_array( $this->params['required_fields'] ) )
		{
			/**
			 * Loop through POST data
			 * @var (array) $this->post
			 */
			foreach( $this->post as $key => $value )
			{
				/**
				 * Check if field is required by params
				 * Simple validation for non-empty fields only
				 * @todo Add more sohpisticated validation
				 */
				if( in_array( $key, $this->params['required_fields'] ) && ! Helper::isValid( $value ) )
				{
					/**
					 * Look for a corresponding required field message in params
					 */
					$index = array_search( $key, $this->params['required_fields'] );
					$msg = isset( $this->params['required_fields_messages'][$index] ) ? $this->params['required_fields_messages'][$index] : $key . ' is a required field.';

					/**
					 * Push to `errors` array
					 */
					$errors[]['error'] = $msg;
				}
			}

			/**
			 * If there are errors, set the vars array, build and return output
			 */
			if( isset( $errors ) && is_array( $errors ) )
			{
				/**
				 * Set the template `error` var to true
				 */
				$this->vars['error'] = true;

				/**
				 * Set the template `errors` var
				 */
				$this->vars['errors'] = $errors;

				return false;
			}
		}

		/**
		 * No errors, let's try to send the email!
		 */
		$this->sendEmail();
	}


	/**
	 * Send the email through the interwebs
	 * @param  (array) $this->post   	Filtered POST data
	 * @param  (array) $params 			Params from tag pair
	 * @return (array) $response 		Response from Mandrill API
	 */
	protected function sendEmail()
	{
		/**
		 * Send message via Mandrill API
		 * @var API method
		 * @var data
		 */
		$mandrill      = new Mandrill( $this->config['api_key'] );
		$plain_text    = $this->getFile( $this->params['plain_text_template'] );
		$html          = $this->getFile( $this->params['html_template'] );

		/**
		 * Check if the to_email parameter is an array
		 * for sending to multiple email addresses
		 */
		if( is_array( $this->params['to_email'] ) )
		{
			foreach( $this->params['to_email'] as $i => $email )
			{
				if( is_array( $this->params['to_name'] ) && array_key_exists( $i, $this->params['to_name'] ) )
				{
					$name = $this->params['to_name'][$i];
				}
				else
				{
					$name = $this->params['to_name'];
				}

				$to[] = array(
					'name'  => $name,
					'email' => $email,
				);
			}
		}
		else
		{
			$to[] = array(
				'name'  => $this->params['to_name'],
				'email' => $this->params['to_email'],
			);
		}

		/**
		 * Messages/send 
		 * @var array Params
		 */
		$params = array(
			'text'       => $plain_text,
			'html'       => $html,
			'subject'    => $this->params['subject'],
			'from_email' => $this->params['from_email'],
			'from_name'  => $this->params['from_name'],
			'to'         => $to
		);

		/**
		 * Need to replace dashes with underscores for Mandrill merge tags
		 * @var array
		 */
		$underscore_post = Helper::replaceDashes( $this->post );

		/**
		 * Build out the merge_vars array
		 * @var array
		 */
		foreach( $underscore_post as $k => $v )
		{
			$_global_merge_vars[] = array(
				'name'    => $k,
				'content' => $v
			);
		}

		/**
		 * Global Merge Vars
		 */
		$params['global_merge_vars'] = $_global_merge_vars;

		/**
		 * Make API Call
		 * @var array 		Messages/send params
		 * @var async 		Boolean
		 */
		try
		{
			$response = $mandrill->messages->send( $params, true );
			$this->handleSuccess( $response );
		}
		catch( Mandrill_Error $e )
		{
			$this->handleError( $e );
		}
	}

	/**
	 * Get a file from the filesystem
	 * @param  (string) $file   Filename to get.
	 * @return (mixed)        	Returns file if exists, otherwse false.
	 */
	protected function getFile( $file = null )
	{
		if( is_null( $file ) || is_array( $file ) ) return false;

		/**
		 * Set the filepath from config and argument
		 */
		$path = Path::tidy( $this->config['file_path'] );
		$file = $path . $file;

		/**
		 * Check if file exists
		 */
		if( File::exists( $file ) )
		{
			return File::get( $file );
		}
		else
		{
			return false;
		}
	}


	/**
	 * If Manndrill API call successful, do some further handling
	 * @param  (array) $response The raw API response
	 * @return none
	 */
	protected function handleSuccess( $response = null )
	{
		if( is_null( $response ) || ! is_array( $response ) || ! isset( $response[0]) ) return false;

		/**
		 * API success response is a zero-indexed array
		 */
		$response = $response[0];

		/**
		 * Success! - Redirect to thanks page
		 */
		if( isset( $response['status'] ) && ( $response['status'] === 'sent' || $response['status'] === 'queued' ) )
		{
			/**
			 * Success!
			 * Log it!
			 */
			Log::info( json_encode( $response ), 'mandrill', 'handleApiResponse::success' );
			$this->log( $response, 'success', $this->params, $this->post );
			
			/**
			 * Check for redirect on success
			 */
			if( isset( $this->params['success_redirect'] ) && $this->params['success_redirect'] != '' )
			{
				Url::redirect( $this->params['success_redirect'] );
			}

			/**
			 * Set the template var `success` to true
			 */
			$this->vars['success'] = true;

			/**
			 * Set session key `mandrill_form_status` to true
			 */
			$this->session->set( 'mandrill_form_status', true );

			/**
			 * Redirect to self without POST data to prevent multiple form submissions after success
			 */
			Url::redirect( url::getCurrent() );
		}
	}


	/**
	 * Handle Mandrill Exceptions
	 * @param  Exception $error 	Exception object
	 * @return none
	 */
	protected function handleError( Exception $error )
	{
		if( is_null( $error ) || ! is_object( $error ) )
		{
			/**
			 * Set template var `error` to true
			 */
		 	$this->vars['error'] = true;

		 	/**
		 	 * Populate template tag pair `errors` with a generic message
		 	 */
		 	$this->vars['errors'][]['error'] = 'There was a problem sending your email. Please try again later.';

		 	return false;
		}

		/**
		 * Set template var `error` to true
		 */
		$this->vars['error'] = true;

		/**
		 * Populate template tag pair`errors` with the exception message
		 */
		$this->vars['errors'][]['error'] = $error->getMessage();

		/**
		 * Log error
		 */
		$error_msg = array(
			'code'    => $error->getCode(),
			'message' => $error->getMessage(),
		);

		Log::info( json_encode( $error_msg ), 'mandrill', 'sendEmail::error' );
		$this->log( $error_msg, 'error', $this->params, $this->post );

		/**
		 * Check if redirect on error is set
		 */
		if( isset( $this->params['error_redirect'] ) && $this->params['error_redirect'] != '' )
		{
			Url::redirect( $this->params['error_redirect'] );
		}
	}

	/**
	 * Log the Mandrill API response and form data in CSV format
	 * @param  (array) $response Response from Mandrill API.
	 * @param  (string) $status   Should be either 'success' or 'error'
	 * @param  (array) $params Params from tag pair
	 * @param  (array) $post POST data from form
	 * @return none
	 */

	/**
	 * Write to log(s)
	 * @param  (array) $response Response from Mandrill API
	 * @param  (bool) $status
	 * @param  (array) $params   Params from tag pair
	 * @param  (array) $post     POST data.
	 * @return none
	 */
	protected function log( $response = null, $status = null, $params = null, $post = null )
	{
		if( is_null( $response ) || is_null( $status ) || is_null( $params ) || is_null( $post ) || ! $params['enable_logging'] )
		{
			return false;
		}

		/**
		 * Log file path
		 * Use the default `_logs` directory.
		 */
		$path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . '_logs/';
		$file = '';

		/**
		 * Determine which log file to use
		 */
		switch( $status )
		{
			case 'success':
				$file = $path . 'mandrill_success.csv';
				break;
			case 'error':
				$file = $path . 'mandrill_error.csv';
				break;
			default:
				return false;
		}

		/**
		 * Check first if a log file already exists.
		 * If not, create it.
		 */
		
		if( ! File::exists( $file ) )
		{
			File::put( $file, 'Email,Status,ID' . PHP_EOL );
		}

		/**
		 * Open file, append, and close
		 */
		
		$fh = fopen( $file, 'a' );
		fputcsv( $fh, $response );
		fclose( $fh );

		unset( $file );
		unset( $fh );

		/**
		 * Set filename to the `form_name` from the the tag pair
		 */
		
		$filename = isset( $params['form_name'] ) ? $params['form_name'] : 'mandrill_form';
		$file = $path . $filename . '.' . 'csv';

		/**
		 * Create file if it doesn't exist
		 */
		
		if( ! File::exists( $file ) )
		{
			$file_header = implode( ',', array_keys( $post ) );
			File::put( $file, $file_header . PHP_EOL );
		}

		/**
		 * Open file, append, and close
		 */
		
		$fh = fopen( $file, 'a' );
		fputcsv( $fh, array_values( $post ) );
		fclose( $fh );

		unset( $file );
		unset( $fh );
	}


	/**
	 * Build and return the tag pair output
	 * @return (string) Tag pair output
	 */
	protected function getOutput()
	{
		/**
		 * Process template vars
		 */
		if( ! $this->vars )
		{
			/**
			 * If false, set to an array with an empty array
			 */
			$this->vars = array( array() );
		}
		else
		{
			$_vars = array();

			/**
			 * Loop through template vars and setup for parsing
			 * @var [type]
			 */
			foreach( $this->vars as $name => $arr )
			{
				$_vars[0][$name] = $arr;
			}

			$this->vars = $_vars;
			unset( $_vars );
		}

		/**
		 * Output buffer
		 * @var string
		 */
		$output = '';

		/**
		 * Open form tag
		 */
		$output .= '<form method="post"';

		/**
		 * Set the action to the current URL
		 */
		$output .= ' action="' . url::getCurrent() . '"';

		/**
		 * Add class name if present in params
		 */
		if( $this->params['form_class'] != '') {
			$output .= ' class="' . $this->params['form_class'] . '"';
		}

		/**
		 * Add ID if present in params
		 */
		if( $this->params['form_id'] != '') {
			$output .= ' id="' . $this->params['form_id'] . '"';
		}

		/**
		 * Close opening form tag
		 */
		$output .= '>';

		/**
		 * Parse the tag pair, replacing with vars
		 */
		$output .= Parse::tagLoop( $this->content, $this->vars );

		/**
		 * SPAM Killah
		 */
		if ( $this->params['enable_spam_killah'] ) {
			$output .= '<input type="text" name="sillySpammer" value="" style="display:none" />';
		}

		/**
		 * Close the form
		 */
		$output .= '</form>';

		/**
		 * Return the output string
		 */
		return $output;
	}
	

	/**
	 * Fetch tag pair parameters
	 * @return (array)
	 */
	
	protected function fetchParams()
	{
		/**
		 * Tag parameters
		 * $this->fetchParam()
		 * 
		 * @param $key
		 * @param $default=null
		 * @param $validity_check=null A callback function to return validity of parameter. Must be boolean.
		 * @param $is_boolean=false
		 * @param $force_lowercase=true
		 * @return (array) $params
		 */

		$params['form_name']                        = $this->fetchParam('form_name', false, false, false, true) ? $this->fetchParam('form_name', false, false, false, true) : Url::getCurrent();
		$params['to_email']                         = $this->fetchParam('to_email', '') != '' ? $this->fetchParam('to_email', '') : $this->config['email_options']['to_email'];
		$params['to_email']                         = Helper::pipedStringToArray( $params['to_email'] );
		$params['to_name']                          = $this->fetchParam('to_name', '', false, false, false) != '' ? $this->fetchParam('to_name', '', false, false, false) : $this->config['email_options']['to_name'];
		$params['to_name']                          = Helper::pipedStringToArray( $params['to_name'] );
		$params['cc']                               = $this->fetchParam('cc', '') != '' ? $this->fetchParam('cc', '') : $this->config['email_options']['cc'];
		$params['bcc']                              = $this->fetchParam('bcc', '') != '' ? $this->fetchParam('bcc', '') : $this->config['email_options']['bcc'];
		$params['from_email']                       = $this->fetchParam('from_email', '') != '' ? $this->fetchParam('from_email', '') : $this->config['email_options']['from_email'];
		$params['from_name']                        = $this->fetchParam('from_name', '', false, false, false) != '' ? $this->fetchParam('from_name', '', false, false, false) : $this->config['email_options']['from_name'];
		$params['subject']                          = $this->fetchParam('subject', '', false, false, false) != '' ? $this->fetchParam('subject', '', false, false, false) : $this->config['email_options']['subject'];
		$params['form_id']                          = $this->fetchParam('form_id', '') != '' ? $this->fetchParam('form_id', '') : $this->config['form_attributes']['id'];
		$params['form_class']                       = $this->fetchParam('form_class', '') != '' ? $this->fetchParam('form_class', '') : $this->config['form_attributes']['class'];
		$params['html_template']                    = $this->fetchParam('html_template', '', false, false, false) != '' ? $this->fetchParam('html_template', '', false, false, false) : $this->config['email_templates']['html'];
		$params['plain_text_template']              = $this->fetchParam('plain_text_template', '', false, false, false) != '' ? $this->fetchParam('plain_text_template', '', false, false, false) : $this->config['email_templates']['plain_text'];
		$params['required_fields']                  = $this->fetchParam('required_fields', false, false, false, true); // Pipe separated
		$params['required_fields']                  = Helper::pipedStringToArray( $params['required_fields'] );
		$params['required_fields_messages']         = $this->fetchParam('required_fields_messages', false, false, false, false); // Pipe separated
		$params['required_fields_messages']         = Helper::pipedStringToArray( $params['required_fields_messages'] );
		$params['use_merge_vars']                   = $this->fetchParam('use_merge_vars', false, false, true, false); // Use Mandrill merge_vars (bool) flag. Default = false
		$params['send_user_email']                  = $this->fetchParam('send_user_email', false, false, true, false); // Send user email flag? Default = false
		$params['enable_spam_killah']               = $this->fetchParam('enable_spam_killah', false, false, true, false); // Enable SPAM Killah?
		$params['spam_killah_redirect']             = $this->fetchParam('spam_killah_redirect', false, false, false, true);
		$params['success_redirect']                 = $this->fetchParam('success_redirect', false, false, false, true);
		$params['error_redirect']                   = $this->fetchParam('error_redirect', false, false, false, true);
		$params['enable_logging']                   = $this->fetchParam('enable_logging', true, false, true, true );

		return $params;
	}
}
/**
 * END of file
 */