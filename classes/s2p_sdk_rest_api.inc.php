<?php

namespace S2P_SDK;

if( !defined( 'S2P_SDK_DIR_CLASSES' ) or !defined( 'S2P_SDK_DIR_METHODS' ) )
    die( 'Something went bad' );

include_once( S2P_SDK_DIR_CLASSES.'s2p_sdk_rest_api_request.inc.php' );
include_once( S2P_SDK_DIR_CLASSES.'s2p_sdk_rest_api_codes.inc.php' );
include_once( S2P_SDK_DIR_METHODS.'s2p_sdk_method.inc.php' );

if( !defined( 'S2P_SDK_REST_TEST' ) )
    define( 'S2P_SDK_REST_TEST', 'test' );
if( !defined( 'S2P_SDK_REST_LIVE' ) )
    define( 'S2P_SDK_REST_LIVE', 'live' );

class S2P_SDK_Rest_API extends S2P_SDK_Module
{
    const ENV_TEST = S2P_SDK_REST_TEST, ENV_LIVE = S2P_SDK_REST_LIVE;

    const ERR_ENVIRONMENT = 100, ERR_METHOD = 101, ERR_METHOD_FUNC = 102, ERR_PREPARE_REQUEST = 103, ERR_URL = 104, ERR_HTTP_METHOD = 105,
          ERR_APIKEY = 106, ERR_CURL_CALL = 107, ERR_PARSE_RESPONSE = 108, ERR_VALIDATE_RESPONSE = 109, ERR_CALL_RESULT = 110;

    const TEST_BASE_URL = 'https://paytest.smart2pay.com',
          LIVE_BASE_URL = 'https://pay.smart2pay.com';

    const TEST_RESOURCE_URL = 'https://apitest.smart2pay.com',
          LIVE_RESOURCE_URL = 'https://api.smart2pay.com';

    /** @var bool $_test_mode */
    private $_environment = self::ENV_LIVE;

    /** @var string $_base_url */
    private $_base_url = '';

    /** @var string $_resource_url */
    private $_resource_url = '';

    /** @var S2P_SDK_Method $_method */
    private $_method = null;

    /** @var S2P_SDK_Rest_API_Request $_request */
    private $_request = null;

    /** @var string $_api_key */
    private $_api_key = '';

    /** @var array $_call_result */
    private $_call_result = null;

    /**
     * This method is called right after module instance is created
     *
     * @param bool|array $module_params
     *
     * @return mixed If this function returns false it will consider module is not initialized correctly and will not return class instance
     */
    public function init( $module_params = false )
    {
        $this->reset_api();

        if( empty( $module_params ) or ! is_array( $module_params ) )
            $module_params = array();

        if( ! empty( $module_params['method'] ) )
        {
            if( !$this->method( $module_params['method'], $module_params ) )
                return false;
        }

        if( empty( $module_params['api_key'] ) and defined( 'S2P_SDK_API_KEY' ) and constant( 'S2P_SDK_API_KEY' ) )
            $module_params['api_key'] = constant( 'S2P_SDK_API_KEY' );
        if( empty( $module_params['environment'] ) and defined( 'S2P_SDK_ENVIRONMENT' ) and constant( 'S2P_SDK_ENVIRONMENT' ) )
            $module_params['environment'] = constant( 'S2P_SDK_ENVIRONMENT' );

        if( !empty( $module_params['environment'] ) )
        {
            if( !$this->environment( $module_params['environment'] ) )
                return false;
        }

        if( !empty( $module_params['api_key'] ) )
        {
            if( !$this->set_api_key( $module_params['api_key'] ) )
                return false;
        }

        return true;
    }

    /**
     * This method is called when destroy_instances() method is called inside any module.
     * This is ment to be destructor of instances.
     * Make sure you call destroy_instances() when you don't need any data held in any instances of modules
     *
     * @see destroy_instances()
     */
    public function destroy()
    {
        $this->reset_api();
    }

    function __construct( $params = false )
    {
        parent::__construct( $params );
    }

    private function reset_api()
    {
        $this->_method = null;
        $this->_request = null;
        $this->_base_url = '';
        $this->_resource_url = '';
        $this->_api_key = '';
        $this->reset_call_result();
    }

    private function reset_call_result()
    {
        $this->_call_result = null;
    }

    public static function default_call_result()
    {
        return array(
            'final_url' => '',
            'request' => array(),
            'response' => array(),
        );
    }

    public static function validate_call_result( $result )
    {
        $default_result = self::default_call_result();
        if( empty( $result ) or !is_array( $result ) )
            return $default_result;

        $new_result = array();
        foreach( $default_result as $key => $def_val )
        {
            if( !array_key_exists( $key, $result ) )
                $new_result[$key] = $def_val;
            else
                $new_result[$key] = $result[$key];
        }

        $new_result['request'] = S2P_SDK_Rest_API_Request::validate_request_array( $new_result['request'] );
        $new_result['response'] = S2P_SDK_Method::validate_response_data( $new_result['response'] );

        return $new_result;
    }

    public function environment( $env = null )
    {
        if( $env === null )
            return $this->_environment;

        if( !in_array( $env, array( self::ENV_TEST, self::ENV_LIVE ) ) )
        {
            $this->set_error( self::ERR_ENVIRONMENT,
                                  self::s2p_t( 'Unknown environment' ),
                                  sprintf( 'Unknown environment [%s]', $env ) );
            return false;
        }

        $this->_environment = $env;
        return true;
    }

    /**
     * Return last call result
     *
     * @return array Returns last call result or null in case no call was made
     */
    public function get_call_result()
    {
        return $this->_call_result;
    }

    /**
     * Return current request object
     *
     * @return S2P_SDK_Rest_API_Request Returns request object
     */
    public function get_request_obj()
    {
        return $this->_request;
    }

    /**
     * Get current base URL
     *
     * @return string Returns base URL
     */
    public function get_base_url()
    {
        return $this->_base_url;
    }

    /**
     * Set base URL
     *
     * @param string $url
     *
     * @return bool Returns true on success or false on fail
     */
    public function set_base_url( $url )
    {
        if( !is_string( $url ) )
            return false;

        $this->_base_url = $url;
        return true;
    }

    /**
     * Get current resource URL
     *
     * @return string Returns resource URL
     */
    public function get_resource_url()
    {
        return $this->_resource_url;
    }

    /**
     * Set resource URL
     *
     * @param string $url
     *
     * @return bool Returns true on success or false on fail
     */
    public function set_resource_url( $url )
    {
        if( !is_string( $url ) )
            return false;

        $this->_resource_url = $url;
        return true;
    }

    /**
     * Get api key
     *
     * @return string Returns api key
     */
    public function get_api_key()
    {
        return $this->_api_key;
    }

    /**
     * Set base URL
     *
     * @param string $url
     *
     * @return bool Returns true on success or false on fail
     */
    public function set_api_key( $api_key )
    {
        if( !is_string( $api_key ) )
            return false;

        $this->_api_key = $api_key;
        return true;
    }

    private function validate_base_url()
    {
        $env = $this->environment();

        switch( $env )
        {
            default:
                $this->set_error( self::ERR_ENVIRONMENT,
                    self::s2p_t( 'Unknown environment' ),
                    sprintf( 'Unknown environment [%s]', $env ) );

                return false;

            case self::ENV_TEST:
                if( empty( $this->_base_url ) )
                    $this->_base_url = self::TEST_BASE_URL;
                if( empty( $this->_resource_url ) )
                    $this->_resource_url = self::TEST_RESOURCE_URL;
            break;

            case self::ENV_LIVE:
                if( empty( $this->_base_url ) )
                    $this->_base_url = self::LIVE_BASE_URL;
                if( empty( $this->_resource_url ) )
                    $this->_resource_url = self::LIVE_RESOURCE_URL;
            break;
        }

        return true;
    }

    public function do_finalize( $params = false )
    {
        if( empty( $this->_method ) )
        {
            $this->set_error( self::ERR_METHOD, self::s2p_t( 'Method not set' ) );
            return false;
        }

        if( !($call_result = $this->get_call_result()) )
        {
            $this->set_error( self::ERR_CALL_RESULT, self::s2p_t( 'Invalid call result or previous call failed.' ) );
            return false;
        }

        if( !($finalize_result = $this->_method->finalize( $call_result, $params )) )
        {
            if( $this->_method->has_error() )
                $this->copy_error( $this->_method );
            else
                $this->set_error( self::ERR_VALIDATE_RESPONSE, self::s2p_t( 'Couldn\'t finalize request after call.' ) );

            return false;
        }

        return S2P_SDK_Method::validate_finalize_result( $finalize_result );
    }

    public function do_call( $params = false )
    {
        $this->reset_call_result();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['quick_return_request'] ) )
            $params['quick_return_request'] = false;

        if( empty( $this->_method ) )
        {
            $this->set_error( self::ERR_METHOD, self::s2p_t( 'Method not set' ) );
            return false;
        }

        if( !($api_key = $this->get_api_key()) )
        {
            $this->set_error( self::ERR_APIKEY, self::s2p_t( 'API Key not set.' ) );
            return false;
        }

        if( !$this->validate_base_url()
         or empty( $this->_base_url ) )
        {
            $this->set_error( self::ERR_URL, self::s2p_t( 'Couldn\'t obtain base URL.' ) );
            return false;
        }

        if( !($request_data = $this->_method->prepare_for_request( $params )) )
        {
            if( $this->_method->has_error() )
                $this->copy_error( $this->_method );
            else
                $this->set_error( self::ERR_PREPARE_REQUEST, self::s2p_t( 'Couldn\'t prepare request data.' ) );

            return false;
        }

        if( ($hook_result = $this->trigger_hooks( 'rest_api_prepare_request_after', array( 'api_obj' => $this, 'request_data' => $request_data ) ))
        and is_array( $hook_result ) )
        {
            if( array_key_exists( 'request_data', $hook_result ) )
                $request_data = $hook_result['request_data'];
        }

        $final_url = $this->_base_url.$request_data['full_query'];

        if( !empty( $params['quick_return_request'] ) )
        {
            $return_arr = array();
            $return_arr['final_url'] = $final_url;
            $return_arr['request_data'] = $request_data;

            return $return_arr;
        }

        if( !($this->_request = new S2P_SDK_Rest_API_Request()) )
        {
            $this->set_error( self::ERR_PREPARE_REQUEST, self::s2p_t( 'Couldn\'t prepare API request.' ) );
            return false;
        }

        if( empty( $request_data['http_method'] )
         or !$this->_request->set_http_method( $request_data['http_method'] ) )
        {
            $this->set_error( self::ERR_HTTP_METHOD, self::s2p_t( 'Couldn\'t set HTTP method.' ) );
            return false;
        }

        if( !$this->_request->set_url( $final_url ) )
        {
            $this->set_error( self::ERR_URL, self::s2p_t( 'Couldn\'t set final URL.' ) );
            return false;
        }

        if( !empty( $request_data['request_body'] ) )
            $this->_request->set_body( $request_data['request_body'] );

        $this->_request->add_header( 'Content-Type', 'application/json; charset=utf-8' );

        $call_params = array();
        $call_params['user_agent'] = 'APISDK_'.S2P_SDK_VERSION.'/PHP_'.phpversion().'/'.php_uname('s').'_'.php_uname('r');
        $call_params['userpass'] = array( 'user' => $api_key, 'pass' => '' );

        $this->trigger_hooks( 'rest_api_call_before', array( 'api_obj' => $this ) );

        if( !($request_result = $this->_request->do_curl( $call_params )) )
            $request_result = null;

        if( ($hook_result = $this->trigger_hooks( 'rest_api_request_result', array( 'api_obj' => $this, 'request_result' => $request_result ) ))
        and is_array( $hook_result ) )
        {
            if( array_key_exists( 'request_result', $hook_result ) )
                $request_result = S2P_SDK_Rest_API_Request::validate_request_array( $hook_result['request_result'] );
        }

        if( empty( $request_result ) )
        {
            if( $this->_request->has_error() )
                $this->copy_error( $this->_request );
            else
                $this->set_error( self::ERR_CURL_CALL, self::s2p_t( 'Error sending API call' ) );

            return false;
        }

        if( !in_array( $request_result['http_code'], S2P_SDK_Rest_API_Codes::success_codes() ) )
        {
            $code_str = $request_result['http_code'];
            if( ($code_details = S2P_SDK_Rest_API_Codes::valid_code( $request_result['http_code'] )) )
                $code_str .= ' ('.$code_details.')';

            // Set a generic error as maybe we will get more specific errors later when parsing response. Don't throw this error yet...
            $this->set_error( self::ERR_CURL_CALL, self::s2p_t( 'Request responded with error code %s', $code_str ), '', array( 'prevent_throwing_errors' => true ) );

            if( empty( $request_result['response_buffer'] ) )
            {
                // In case there's nothing to parse, throw generic error...
                if( $this->throw_errors() )
                    $this->throw_error();
                return false;
            }
        }

        if( !($response_data = $this->_method->parse_response( $request_result )) )
        {
            if( $this->has_error() )
            {
                if( $this->throw_errors() )
                    $this->throw_error();
                return false;
            }

            if( $this->_method->has_error() )
                $this->copy_error( $this->_method );
            else
                $this->set_error( self::ERR_PARSE_RESPONSE, self::s2p_t( 'Error parsing server response.' ) );

            return false;
        }

        if( ($hook_result = $this->trigger_hooks( 'rest_rest_api_response_data', array( 'api_obj' => $this, 'response_data' => $response_data ) ))
        and is_array( $hook_result ) )
        {
            if( array_key_exists( 'response_data', $hook_result ) )
                $response_data = $hook_result['response_data'];
        }

        if( !$this->_method->validate_response( $response_data ) )
        {
            if( $this->_method->has_error() )
                $this->copy_error( $this->_method );
            else
                $this->set_error( self::ERR_VALIDATE_RESPONSE, self::s2p_t( 'Error validating server response.' ) );

            return false;
        }

        // Make sure errors get thrown if any...
        if( $this->has_error()
        and $this->throw_errors() )
        {
            $this->throw_error();
            return false;
        }

        $return_arr = self::default_call_result();
        $return_arr['final_url'] = $final_url;
        $return_arr['request'] = $request_result;
        $return_arr['response'] = $response_data;

        $this->_call_result = $return_arr;

        return $return_arr;
    }

    public function method( $method, $params = null )
    {
        $this->_method = null;

        $method = 'S2P_SDK_Meth_'.ucfirst( strtolower( $method ) );

        /** @var S2P_SDK_Method $method */
        if( !($method_obj = self::get_instance( $method )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();
            else
                $this->set_error( self::ERR_METHOD, self::s2p_t( 'Error instantiating method.' ) );

            return false;
        }

        $this->_method = $method_obj;

        if( !empty( $params ) and is_array( $params ) )
        {
            $this->method_request_parameters( $params );

            if( !empty( $params['func'] ) )
            {
                if( !$this->method_functionality( $params['func'] ) )
                {
                    $this->reset_api();
                    return false;
                }
            }
        }

        return true;
    }

    public function method_request_parameters( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return true;

        if( empty( $this->_method ) )
        {
            $this->set_error( self::ERR_METHOD, self::s2p_t( 'Method not set' ) );
            return false;
        }

        $this->_method->request_parameters( $params );

        return true;
    }

    public function method_functionality( $func )
    {
        if( empty( $this->_method ) )
        {
            $this->set_error( self::ERR_METHOD, self::s2p_t( 'Method not set' ) );
            return false;
        }

        if( !$this->_method->method_functionality( $func ) )
        {
            if( $this->_method->has_error() )
                $this->copy_error( $this->_method );
            else
                $this->set_error( self::ERR_METHOD_FUNC,
                                    self::s2p_t( 'Invalid method functionality' ),
                                    sprintf( 'Invalid method functionality [%s]', $func ) );

            return false;
        }

        return true;
    }

}
