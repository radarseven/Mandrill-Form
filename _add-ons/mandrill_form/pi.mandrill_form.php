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
		'version'			=> '0.5',
		'author'			=> 'Chad Clark | The cRUSHer',
		'author_url'		=> 'http://chadjclark.com/ | http://thecrusherbynight.com'
	);

	/**
	 * Index
	 * @return string [description]
	 */

	public function index() {

		/**
		 * Get the environment
		 */
		$env = Environment::detect();

		/**
		 * Fetch Tag Parameters
		 */
		$params = $this->fetchParams();

		/**
		 * Required fields
		 * @var string  	Pipe delimited string
		 */	
		$required 						= $this->fetchParam('required');

		/**
		 * Also know as the SPAM killah
		 * @var (boolean)
		 */
		$honeypot 						= $this->fetchParam('honeypot', false, false, true); #boolen param

		/**
		 * Output buffer
		 * @var string
		 */
		$output = '';

		/**
		 * Layout vars
		 * @var array
		 */
		$vars = array( array() );

		/**
		 * Check for POST
		 */
		if( PluginHelper::isPost() )
		{
			/**
			 * Filter the POST data
			 */	
			$post = PluginHelper::filterPost();

			/**
			 * Non-production logging
			 */
			if( $env != 'live' )
			{
				Log::info( Environment::detect(), 'add-on', 'mandrill' );
				Log::info( json_encode( $post ), 'add-on', 'mandrill' );
			}

			/**
			 * Try to block some SPAM
			 */
			if( isset( $post['sillySpammer'] ) && $post['sillySpammer'] != '' )
			{
				if( $params['spam_killah_redirect'] != '' )
				{
					/**
					 * Redirect, it's a SPAM bot!
					 */
					Url::redirect( $params['spam_killah_redirect'] );
				}

				echo 'Silly Spammer!';
				exit;
			}

			/**
			 * Validation
			 * @todo Should do some validation here
			 * @param array 	Rules array in config?
			 */
			if( isset( $params['required_fields'] ) && is_array( $params['required_fields'] ) )
			{
				foreach( $post as $key => $value )
				{
					if( array_key_exists( $key, $post ) )
					{
						$errors[] = '{$key} is a required field.';
					}
				}

				if( isset( $errors ) )
				{
					$vars[] = array(
						'error'  => true,
						'errors' => $errors,
					);

					$output = $this->buildForm( $params, $vars );

					return $output;
				}
			}

			/**
			 * Send the email!
			 * @var (array) $post 		Filtered POSt araay
			 * @var (array) $options 	Options
			 */
			$response = $this->sendEmail( $post, $params );

			/**
			 * Handle yo biz-naz
			 * @var array
			 */
			$status = $this->handleApiResponse( $response, $params, $post );

			/**
			 * Set some key/value pairs to send to the template
			 */
			if ( $status['success'] ) 
			{
				$vars = array( array( 'success' => true ) );
			 } 
			 else
			 {
		 		$vars = array( array( 'error' => true, 'errors' => $status['message'] ) );
			}
		}

		/**
		 * Buid out the form
		 * @var (array) $params 	User-defined tag parameters 
		 * @var (array) $vars 		Key/value pairs returned to the template
		 */
		$output = $this->buildForm( $params, $vars );

		return $output;
	}

	/**
	 * Send that shiz
	 * @param  (array) $post   		Filtered POST data
	 * @param  (array) $params 	
	 * @return (array) $response 	Response from Mandrill API
	 */
	protected function sendEmail( $post, $params  )
	{
		/**
		 * Get add-on config
		 */
		$config = $this->getConfig();

		/**
		 * Send message via Mandrill API
		 * @var API method
		 * @var data
		 */
		$mandrill      = new Mandrill( $config['api_key'] );
		$plain_text    = $this->buildPlainText( $post, $params['plain_text_template'] );
		$html          = $this->buildHtml( $post, $params['html_template'] );
		//print_r( $params ); exit;
		/**
		 * Check if the to_email parameter is an array
		 * for sending to multiple email addresses
		 */
		if( is_array( $params['to_email'] ) )
		{
			foreach( $params['to_email'] as $i => $email )
			{
				if( is_array( $params['to_name'] ) && array_key_exists( $i, $params['to_name'] ) )
				{
					$name = $params['to_name'][$i];
				}
				else
				{
					$name = $params['to_name'];
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
				'name'  => $params['to_name'],
				'email' => $params['to_email'],
			);
		}

		/**
		 * Messages/send 
		 * @var array Params
		 */
		$params = array(
			'text'       => $plain_text,
			'html'       => $html,
			'subject'    => $params['subject'],
			'from_email' => $params['from_email'],
			'from_name'  => $params['from_name'],
			'to'         => $to
		);

		/**
		 * Need to replace dashes with underscores for Mandrill merge tags
		 * @var array
		 */
		$underscore_post = PluginHelper::replaceDashes( $post );

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
		$response = $mandrill->messages->send( $params, true );

		//$this->log( $response );

		return $response;

	}

	protected function sendUserEmail( $post, $params )
	{

	}

	protected function buildPlainText( $post, $file = null )
	{
		if( is_null( $file ) )
		{
			return false;
		}

		/**
		 * Get add-on config
		 */
		$config = $this->getConfig();

		/**
		 * Build the plain text version here
		 * @todo Make this a parameter in the tag pair
		 */
		$path = Path::tidy( $config['file_path'] );
		$plain_text = $path . $file;

		if( File::exists( $plain_text ) )
		{
			/**
			 * We should parse some tag action here
			 */
			return File::get( $plain_text );
		}
		else
		{
			return false;
		}
	}

	protected function buildHtml( $post, $file = null )
	{
		if( is_null( $file ) )
		{
			return false;
		}

		/**
		 * Get add-on config
		 */
		$config = $this->getConfig();

		/**
		 * Build the html version
		 * @todo Make this a parameter in the tag pair
		 */
		$path = Path::tidy( $config['file_path'] );
		$html = $path . $file;

		if( File::exists( $html ) )
		{
			/**
			 * We should parse some tag action here
			 * @todo Parse tags for POST data
			 */
			return File::get( $html );
		}
		else
		{
			return false;
		}
	}

	protected function buildForm( $params, $vars )
	{

		$output = '';

		$output .= '<form method="post"';

		if( $params['form_class'] != '') {
			$output .= ' class="' . $params['form_class'] . '"';
		}

		if( $params['form_id'] != '') {
			$output .= ' id="' . $params['form_id'] . '"';
		}

		$output .= '>';

		$output .= Parse::tagLoop( $this->content, $vars );

		/**
		 * SPAM Killah
		 */
		if ( $params['enable_spam_killah'] ) {
			$output .= '<input type="text" name="sillySpammer" value="" style="display:none" />';
		}

		$output .= '</form>';

		return $output;
	}

	protected function handleApiResponse( $response = null, $params, $post )
	{
		if( is_null( $response ) )
		{
			return false;
		}

		/**
		 * API success repsonse is a zero-indexed array
		 * @var [type]
		 */
		
		$response = $response[0];

		/**
		 * Success! - Redirect to thanks page
		 * @var [type]
		 */
		
		if( isset( $response['status'] ) && ( $response['status'] === 'sent' || $response['status'] === 'queued' ) )
		{
			/**
			 * Success!
			 * Log that shiz
			 */
			Log::info( json_encode( $response ), 'mandrill', 'handleApiResponse::success' );
			$this->log( $response, 'success', $params, $post );
			
			/**
			 * Check for redirect on success
			 */
			if( isset( $params['success_redirect'] ) && $params['success_redirect'] != '' )
			{
				Url::redirect( $params['success_redirect'] );
			}

			/**
			 * Need to do something else here for the layout
			 * @todo Return success status and message to layout
			 */
			return array(
				'success'	=> true,
				'message'	=> 'Booyah',
			);
		}
		else
		{
			/**
			 * Boo )-:
			 */
			Log::warn( $response, 'mandrill', 'handleApiResponse::error' );
			$this->log( $response, 'error', $params, $post );

			/**
			 * Check if redirect on error is set
			 */
			if( isset( $params['error_redirect'] ) && $params['error_redirect'] != '' )
			{
				Url::redirect( $params['error_redirect'] );
			}

			/**
			 * Need to do something else here so layout can handle error gracefully
			 * @todo Return error status and message to layout
			 */
			return array(
				'success'	=> false,
				'message'	=> 'Whoops, something went wrong.',
			);
		}
	}

	/**
	 * Log the Mandrill API response in CSV format
	 * @param  (array) $response Response from Mandrill API.
	 * @param  (string) $status   Should be either 'success' or 'error'
	 * @return none
	 */
	protected function log( $response, $status = null, $params, $post )
	{
		if( is_null( $status ) )
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
				$file = $path . 'mandrill_success.log';
				break;
			case 'error':
				$file = $path . 'mandrill_error.log';
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
			File::put( $file, 'LOG START' . PHP_EOL . 'Email,Status,ID' . PHP_EOL );
		}

		/**
		 * Write that Shiz
		 */
		$fh = fopen( $file, 'a' );
		fputcsv( $fh, $response );
		fclose( $fh );

		unset( $file );
		unset( $fh );

		$file = $path . $params['form_name'];

		if( ! File::exists( $file ) )
		{
			$file_header = implode( ',', array_keys( $post ) );
			File::put( $file, $file_header . PHP_EOL );
		}

		$fh = fopen( $file, 'a' );
		fputcsv( $fh, array_values( $post ) );
		fclose( $fh );
	}

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
		$params['form_name']                      = $this->fetchParam('form_name', false, false, false, true) ? $this->fetchParam('form_name', false, false, false, true) : Url::getCurrent();
		$params['to_email']                       = $this->fetchParam('to_email', '') != '' ? $this->fetchParam('to_email', '') : $config['email_options']['to_email'];
		$params['to_email']                       = PluginHelper::pipedStringToArray( $params['to_email'] );
		$params['to_name']                        = $this->fetchParam('to_name', '', false, false, false) != '' ? $this->fetchParam('to_name', '', false, false, false) : $config['email_options']['to_name'];
		$params['to_name']                        = PluginHelper::pipedStringToArray( $params['to_name'] );
		$params['cc']                             = $this->fetchParam('cc', '') != '' ? $this->fetchParam('cc', '') : $config['email_options']['cc'];
		$params['bcc']                            = $this->fetchParam('bcc', '') != '' ? $this->fetchParam('bcc', '') : $config['email_options']['bcc'];
		$params['from_email']                     = $this->fetchParam('from_email', '') != '' ? $this->fetchParam('from_email', '') : $config['email_options']['from_email'];
		$params['from_name']                      = $this->fetchParam('from_name', '', false, false, false) != '' ? $this->fetchParam('from_name', '', false, false, false) : $config['email_options']['from_name'];
		$params['msg_header']                     = $this->fetchParam('msg_header', 'New Message', false, false, false);
		$params['msg_footer']                     = $this->fetchParam('msg_footer', '', false, false, false);
		$params['subject']                        = $this->fetchParam('subject', '', false, false, false) != '' ? $this->fetchParam('subject', '', false, false, false) : $config['email_options']['subject'];
		$params['form_id']                        = $this->fetchParam('form_id', '') != '' ? $this->fetchParam('form_id', '') : $config['form_attributes']['id'];
		$params['form_class']                     = $this->fetchParam('form_class', '') != '' ? $this->fetchParam('form_class', '') : $config['form_attributes']['class'];
		$params['html_template']                  = $this->fetchParam('html_template', '', false, false, false) != '' ? $this->fetchParam('html_template', '', false, false, false) : $config['email_templates']['html'];
		$params['plain_text_template']            = $this->fetchParam('plain_text_template', '', false, false, false) != '' ? $this->fetchParam('plain_text_template', '', false, false, false) : $config['email_templates']['plain_text'];
		$params['required_fields']                = $this->fetchParam('required_fields', false, false, false, true); // Pipe separated
		$params['required_fields']                = PluginHelper::pipedStringToArray( $params['required_fields'] );
		$params['use_merge_vars']                 = $this->fetchParam('use_merge_vars', false, false, true, false); // Use Mandrill merge_vars (bool) flag. Default = false
		$params['send_user_email']                = $this->fetchParam('send_user_email', false, false, true, false); // Send user email flag? Default = false
		$params['user_email_template_plain_text'] = $this->fetchParam('user_email_template_plain_text', null, null, false, true);
		$params['user_email_template_html']       = $this->fetchParam('user_email_template_html', null, null, false, true);
		$params['enable_spam_killah']             = $this->fetchParam('enable_spam_killah', false, false, true, false); // Enable SPAM Killah?
		$params['spam_killah_redirect']           = $this->fetchParam('spam_killah_redirect', false, false, false, true);
		$params['success_redirect']               = $this->fetchParam('success_redirect', false, false, false, true);
		$params['error_redirect']                 = $this->fetchParam('error_redirect', false, false, false, true);
		if( PluginHelper::isPost() )
		{
			//print_r($params);
			//exit;
		}
		return $params;
	}
}