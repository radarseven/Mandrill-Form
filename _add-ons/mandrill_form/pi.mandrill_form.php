<?php

/**
 * Require plugin helper
 */
require_once( 'pluginhelper.php' );

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
		'version'			=> '0.7',
		'author'			=> 'Chad Clark | The cRUSHer',
		'author_url'		=> 'http://chadjclark.com/ | http://thecrusherbynight.com'
	);

	protected $params;

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
		$this->post = PluginHelper::getPost();

		/**
		 * Output buffer
		 * @var string
		 */
		$this->output = '';

		/**
		 * Layout vars
		 * @var array
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
		if( PluginHelper::isPost() )
		{
			$this->handlePost();
		}

		/**
		 * Check for session status
		 * This would only be set if there is a successfull submission and redirected to self
		 * without POST.
		 */
		if( $this->session->exists( 'mandrill_form_status' ) && $this->session->get( 'mandrill_form_status' ) )
		{
			// Set the success template var to true
			$this->vars['success'] = true;
			// Remove the session status
			$this->session->delete( 'mandrill_form_status' );
		}

		/**
		 * Return output
		 */
		$output = $this->getOutput();
		return $output;
	}

	protected function handlePost()
	{
		// Assign the POST data to the vars
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
		 * @todo Should do some validation here
		 * @param array 	Rules array in config?
		 */
		if( isset( $this->params['required_fields'] ) && is_array( $this->params['required_fields'] ) )
		{
			// Loop through POST data
			foreach( $this->post as $key => $value )
			{
				// Check if field is required by params
				// Simple validation for non empty fields
				if( in_array( $key, $this->params['required_fields'] ) && ! PluginHelper::isValid( $value ) )
				{
					// Find the required field message if exists
					$index = array_search( $key, $this->params['required_fields'] );
					$msg = isset( $this->params['required_fields_messages'][$index] ) ? $this->params['required_fields_messages'][$index] : $key . ' is a required field.';
					// Populate the $errors var
					$errors[]['error'] = $msg;
				}
			}

			/**
			 * If errors, set the vars array, build and return output
			 */
			if( isset( $errors ) )
			{
				$this->vars['error'] = true;
				$this->vars['errors'] = $errors;

				return false;
			}
		}

		/**
		 * Send the email!
		 */
		$this->sendEmail();
	}

	/**
	 * Send that shiz
	 * @param  (array) $this->post   		Filtered POST data
	 * @param  (array) $params 	
	 * @return (array) $response 	Response from Mandrill API
	 */
	protected function sendEmail()
	{
		/**
		 * Send message via Mandrill API
		 * @var API method
		 * @var data
		 */
		$mandrill      = new Mandrill( $this->config['api_key'] );
		$plain_text    = $this->buildPlainText();
		$html          = $this->buildHtml();

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
		$underscore_post = PluginHelper::replaceDashes( $this->post );

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
		$this->params['global_merge_vars'] = $_global_merge_vars;

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

	protected function sendUserEmail( $post, $params )
	{

	}

	protected function buildPlainText()
	{
		/**
		 * Build the plain text version here
		 * @todo Make this a parameter in the tag pair
		 */
		$path = Path::tidy( $this->config['file_path'] );
		$plain_text = $path . $this->params['plain_text_template'];

		if( File::exists( $plain_text ) )
		{
			return File::get( $plain_text );
		}
		else
		{
			return false;
		}
	}

	protected function buildHtml()
	{
		/**
		 * Build the html version
		 */
		$path = Path::tidy( $this->config['file_path'] );
		$html = $path . $this->params['html_template'];

		if( File::exists( $html ) )
		{
			return File::get( $html );
		}
		else
		{
			return false;
		}
	}

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
			 * Need to do something else here for the layout
			 * @todo Return success status and message to layout
			 */
			$this->vars['success'] = true;
			// Setting a session variable for status
			$this->session->set( 'mandrill_form_status', true );
			// Redirect to self to prevent multiple form submissions
			Url::redirect( url::getCurrent() );
		}
	}

	/**
	 * Handle Mandrill Exceptions
	 * @param  Exception $error [description]
	 * @return [type]           [description]
	 */
	protected function handleError( Exception $error )
	{
		if( is_null( $error ) )
		{
			// Set vars
		 	$this->vars['error'] = true;
		 	$this->vars['errors'][]['error'] = 'There was a problem sending your email. Please try again later.';

		 	return false;
		}

		/**
		 * Set template vars
		 */
		$this->vars['error'] = true;
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

	protected function getOutput()
	{
		/**
		 * Process template vars
		 */
		if( ! $this->vars )
		{
			$this->vars = array( array() );
		}
		else
		{
			$_vars = array();

			foreach( $this->vars as $name => $arr )
			{
				$_vars[0][$name] = $arr;
			}
			$this->vars = $_vars;
			unset( $_vars );
		}

		$output = '';

		// Form tag
		$output .= '<form method="post"';

		// Set the action
		$output .= ' action="' . url::getCurrent() . '"';

		// Add a class?
		if( $this->params['form_class'] != '') {
			$output .= ' class="' . $this->params['form_class'] . '"';
		}

		// Add an ID?
		if( $this->params['form_id'] != '') {
			$output .= ' id="' . $this->params['form_id'] . '"';
		}

		// Close the opening form tag
		$output .= '>';

		// Parse the tag content, replacing vars
		$output .= Parse::tagLoop( $this->content, $this->vars );

		/**
		 * SPAM Killah
		 */
		if ( $this->params['enable_spam_killah'] ) {
			$output .= '<input type="text" name="sillySpammer" value="" style="display:none" />';
		}

		$output .= '</form>';

		return $output;
	}
	
	/**
	 * Fetch tag pair parameters
	 * @return (array)
	 */
	
	protected function fetchParams()
	{
		/**
		 * Get add-on config
		 */
		$config = $this->getConfig();

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
		$params['to_email']                         = $this->fetchParam('to_email', '') != '' ? $this->fetchParam('to_email', '') : $config['email_options']['to_email'];
		$params['to_email']                         = PluginHelper::pipedStringToArray( $params['to_email'] );
		$params['to_name']                          = $this->fetchParam('to_name', '', false, false, false) != '' ? $this->fetchParam('to_name', '', false, false, false) : $config['email_options']['to_name'];
		$params['to_name']                          = PluginHelper::pipedStringToArray( $params['to_name'] );
		$params['cc']                               = $this->fetchParam('cc', '') != '' ? $this->fetchParam('cc', '') : $config['email_options']['cc'];
		$params['bcc']                              = $this->fetchParam('bcc', '') != '' ? $this->fetchParam('bcc', '') : $config['email_options']['bcc'];
		$params['from_email']                       = $this->fetchParam('from_email', '') != '' ? $this->fetchParam('from_email', '') : $config['email_options']['from_email'];
		$params['from_name']                        = $this->fetchParam('from_name', '', false, false, false) != '' ? $this->fetchParam('from_name', '', false, false, false) : $config['email_options']['from_name'];
		$params['subject']                          = $this->fetchParam('subject', '', false, false, false) != '' ? $this->fetchParam('subject', '', false, false, false) : $config['email_options']['subject'];
		$params['form_id']                          = $this->fetchParam('form_id', '') != '' ? $this->fetchParam('form_id', '') : $config['form_attributes']['id'];
		$params['form_class']                       = $this->fetchParam('form_class', '') != '' ? $this->fetchParam('form_class', '') : $config['form_attributes']['class'];
		$params['html_template']                    = $this->fetchParam('html_template', '', false, false, false) != '' ? $this->fetchParam('html_template', '', false, false, false) : $config['email_templates']['html'];
		$params['plain_text_template']              = $this->fetchParam('plain_text_template', '', false, false, false) != '' ? $this->fetchParam('plain_text_template', '', false, false, false) : $config['email_templates']['plain_text'];
		$params['required_fields']                  = $this->fetchParam('required_fields', false, false, false, true); // Pipe separated
		$params['required_fields']                  = PluginHelper::pipedStringToArray( $params['required_fields'] );
		$params['required_fields_messages']         = $this->fetchParam('required_fields_messages', false, false, false, false); // Pipe separated
		$params['required_fields_messages']         = PluginHelper::pipedStringToArray( $params['required_fields_messages'] );
		$params['use_merge_vars']                   = $this->fetchParam('use_merge_vars', false, false, true, false); // Use Mandrill merge_vars (bool) flag. Default = false
		$params['send_user_email']                  = $this->fetchParam('send_user_email', false, false, true, false); // Send user email flag? Default = false
		//$params['user_email_template_plain_text'] = $this->fetchParam('user_email_template_plain_text', null, null, false, true);
		//$params['user_email_template_html']       = $this->fetchParam('user_email_template_html', null, null, false, true);
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