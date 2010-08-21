<?php

/**
 * @Project NUKEVIET 3.0
 * @Author VINADES.,JSC (contact@vinades.vn)
 * @Copyright (C) 2010 VINADES.,JSC. All rights reserved
 * @Createdate 17/8/2010, 0:16
 */

/**********************************************************************
**
** A class to download files
** Version 1.0
** Features : 
**      - hide the real path to the file
**      - allow / disallow download resuming
**      - partial download (useful for download managers)
**      - rename the file on the fly
**      - limit download speed
**
** Author: Mourad Boufarguine / EPT <mourad.boufarguine@gmail.com>
**
** License: Public Domain
** Warranty: None
**
***********************************************************************/

/**
 * include("download.class.php");       // load the class file

 * $fichier = new download("example.zip");                          // use the original file name, disallow resuming, no speed limit                  
 * $fichier = new download("example.zip","My Example.zip") ;        // rename the file, disallow resuming, no speed limit
 * $fichier = new download("example.zip","My Example.zip",true) ;   // rename the file, allow resuming, no speed limit 
 * $fichier = new download("example.zip","My Example.zip",true,80) ;   // rename the file, allow resuming, speed limit 80ko/s

 * $fichier->download_file();
 */

if ( ! defined( 'NV_MAINFILE' ) ) die( 'Stop!!!' );

if ( ! defined( 'NV_MIME_INI_FILE' ) )
{
    define( "NV_MIME_INI_FILE", str_replace( "\\", "/", realpath( dirname( __file__ ) . "/.." ) . '/ini/mime.ini' ) );
}

if ( ! defined( 'ALLOWED_SET_TIME_LIMIT' ) )
{
    if ( $sys_info['allowed_set_time_limit'] )
    {
        define( 'ALLOWED_SET_TIME_LIMIT', true );
    }
}

/**
 * download
 * 
 * @package   
 * @author NUKEVIET 3.0
 * @copyright VINADES.,JSC
 * @version 2010
 * @access public
 */
class download
{
    private $properties = array( //
        "path" => "", //
        "name" => "", //
        "extension" => "", //
        "type" => "", //
        "size" => "", //
        "mtime" => 0, //
        "resume" => "", //
        "max_speed" => "" //
        );

    /**
     * download::__construct()
     * 
     * @param mixed $path
     * @param string $name
     * @param bool $resume
     * @param integer $max_speed
     * @return
     */
    public function __construct( $path, $name = '', $resume = false, $max_speed = 0 )
    {
        $this->properties = array( //
            "path" => $path, //
            "name" => ( $name == "" ) ? substr( strrchr( "/" . $path, "/" ), 1 ) : $name, //
            "extension" => strtolower( array_pop( explode( '.', $path ) ) ), //
            "type" => $this->my_mime_content_type( $path ), //
            "size" => intval( sprintf( "%u", filesize( $path ) ) ), //
            "mtime" => ( $mtime = filemtime( $path ) ) > 0 ? $mtime : time(), //
            "resume" => $resume, //
            "max_speed" => $max_speed //
            );
    }

    /**
     * download::my_mime_content_type()
     * 
     * @param mixed $path
     * @return
     */
    private function my_mime_content_type( $path )
    {
        if ( function_exists( 'mime_content_type' ) )
        {
            return mime_content_type( $path );
        }

        if ( function_exists( 'finfo_open' ) )
        {
            $finfo = finfo_open( FILEINFO_MIME );
            $mimetype = finfo_file( $finfo, $path );
            finfo_close( $finfo );
            return $mimetype;
        }

        $mime_types = nv_parse_ini_file( NV_MIME_INI_FILE );

        if ( array_key_exists( $this->properties['extension'], $mime_types ) )
        {
            if ( is_string( $mime_types[$ext] ) ) return $mime_types[$ext];
            else  return $mime_types[$ext][0];
        }

        return 'application/force-download';
    }

    /**
     * download::nv_getenv()
     * 
     * @param mixed $key
     * @return
     */
    private function nv_getenv( $key )
    {
        if ( isset( $_SERVER[$key] ) )
        {
            return $_SERVER[$key];
        } elseif ( isset( $_ENV[$key] ) )
        {
            return $_ENV[$key];
        } elseif ( @getenv( $key ) )
        {
            return @getenv( $key );
        } elseif ( function_exists( 'apache_getenv' ) && apache_getenv( $key, true ) )
        {
            return apache_getenv( $key, true );
        }
        return "";
    }

    /**
     * download::get_property()
     * 
     * @param mixed $property
     * @return
     */
    public function get_property( $property )
    {
        if ( array_key_exists( $property, $this->properties ) ) return $this->properties[$property];

        else  return null;

    }

    /**
     * download::set_property()
     * 
     * @param mixed $property
     * @param mixed $value
     * @return
     */
    public function set_property( $property, $value )
    {
        if ( array_key_exists( $property, $this->properties ) )
        {

            $this->properties[$property] = $value;

            return true;
        }
        else  return false;
    }

    /**
     * download::download_file()
     * 
     * @return
     */
    public function download_file()
    {
        if ( ! is_readable( $this->properties['path'] ) or ! is_file( $this->properties['path'] ) )
        {
            die( "Nothing to download!" );
        }

        $seek_start = 0;
        $seek_end = -1;
        $data_section = false;

        if ( ( $http_range = nv_getenv( 'HTTP_RANGE' ) ) != "" )
        {
            $seek_range = substr( $http_range, strlen( 'bytes=' ) );

            $range = explode( '-', $seek_range );

            if ( ! empty( $range[0] ) )
            {
                $seek_start = intval( $range[0] );
            }

            if ( isset( $range[1] ) and ! empty( $range[1] ) )
            {
                $seek_end = intval( $range[1] );
            }

            if ( ! $this->properties['resume'] )
            {
                $seek_start = 0;
            }
            else
            {
                $data_section = true;
            }
        }

        @ob_end_clean();
        $old_status = ignore_user_abort( true );
        if ( defined( 'ALLOWED_SET_TIME_LIMIT' ) )
        {
            set_time_limit( 0 );
        }

        if ( $seek_start > ( $this->properties['size'] - 1 ) )
        {
            $seek_start = 0;
        }

        $res = fopen( $this->properties['path'], 'rb' );

        if ( ! $res )
        {
            die( 'File error' );
        }

        if ( $seek_start ) fseek( $res, $seek_start );
        if ( $seek_end < $seek_start )
        {
            $seek_end = $this->properties['size'] - 1;
        }

        header( "Pragma: public" );
        header( "Expires: 0" );
        header( "Cache-Control:" );
        header( "Cache-Control: public" );
        header( "Content-Description: File Transfer" );
        header( "Content-Type: " . $this->properties['type'] );
        if ( strstr( $this->nv_getenv( 'HTTP_USER_AGENT' ), "MSIE" ) != false )
        {
            header( 'Content-Disposition: attachment; filename="' . urlencode( $this->properties['name'] ) . '";' );
        }
        else
        {
            header( 'Content-Disposition: attachment; filename="' . $this->properties['name'] . '";' );
        }
        header( 'Last-Modified: ' . date( 'D, d M Y H:i:s \G\M\T', $this->properties['mtime'] ) );

        if ( $data_section and $this->properties['resume'] )
        {
            header( "HTTP/1.1 206 Partial Content" );
            header( "Status: 206 Partial Content" );
            header( 'Accept-Ranges: bytes' );
            header( "Content-Range: bytes " . $seek_start . "-" . $seek_end . "/" . $this->properties['size'] );
            header( "Content-Length: " . ( $seek_end - $seek_start + 1 ) );
        }
        else
        {
            header( "Content-Length: " . $this->properties['size'] );
        }

        while ( ! ( connection_aborted() or connection_status() == 1 ) and ! feof( $res ) )
        {
            if ( ( $speed = $this->properties['max_speed'] ) > 0 )
            {
                $sleep_time = ( 8 / $speed ) * 1e6;
            }
            else
            {
                $sleep_time = 0;
            }

            print ( fread( $res, 1024 * 8 ) );
            flush();
            usleep( $sleep_time );
        }

        fclose( $res );

        ignore_user_abort( $old_status );
        if ( defined( 'ALLOWED_SET_TIME_LIMIT' ) )
        {
            set_time_limit( ini_get( "max_execution_time" ) );
        }
        exit;
    }
}

?>