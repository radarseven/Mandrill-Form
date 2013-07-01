<?php

class PluginHelper
{

	/**
	 * Check if request is a POST request
	 * @param  array
	 * @return boolean
	 */
	
	public static function isPost()
	{
		/**
		 * Verify request method and that it's not an empty request
		 */
		if( strtoupper( $_SERVER['REQUEST_METHOD'] ) == 'POST' && ! Helper::isEmptyArray( $_POST )  )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Filter POST array and return array with no empty values.
	 * @param  array       Raw $_POST array
	 * @return array       Filtered $_POST array
	 */
	
	public static function filterPost( $post = null )
	{
		$post = is_null( $post ) ? $_POST : $post;

		if( ! PluginHelper::isNotEmptyArray( $post ) )
		{
			return FALSE;
		}

		foreach( $post as $k => $v )
		{
			if( ! empty( $v ) )
			{
				/**
				 * Populate $post with non-empty key/value pairs
				 */
				$post[$k] = Request::post( $k );
			}
		}

		return $post;
	}

	public static function isNotEmptyArray( $array )
	{
		if( is_array( $array ) && ! Helper::isEmptyArray( $array ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Replace dashes with underscores in an array
	 * @param  array $arr
	 * @return (array)      Array with replace values
	 */
	
	public static function replaceDashes( $arr = null )
	{
		if( is_null( $arr ) || ! is_array( $arr ) )
		{
			return array();
		}
		
	    foreach ( $arr as $key => $val ) {
	        if ( strpos( $key, "-" ) !== FALSE )
	        {
	            $newKey = str_replace( "-", "_", $key );
	            $arr[$newKey] = $val;
	            unset( $arr[$key] );
	        }
	    }

	    return $arr;
	}

	/**
	 * Converted a pipe delimited string to an array
	 * @param (string) $string 
	 * @param (array) $params Tag params
	 * @return (array)
	 */
	
	public static function pipedStringToArray( $string )
	{
		if( ! is_string( $string ) || is_array( $string ) )
		{
			return $string;
		}

		if( strpos( $string, '|' ) !== false )
		{
			$array = explode( '|', $string );
			return $array;
		}

		return $string;
	}
}