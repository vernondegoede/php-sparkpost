<?php
namespace SparkPost;
use Ivory\HttpAdapter\Configuration;
use Ivory\HttpAdapter\HttpAdapterInterface;
use Ivory\HttpAdapter\HttpAdapterException;

/**
 * @desc SDK interface for managing SparkPost API endpoints
 */
class APIResource {

	/**
	 * @desc name of the API endpoint, mainly used for URL construction.
   * This is public to provide an interface
   *
	 * @var string
	 */
	public $endpoint;

	/**
	 * @desc Mapping for values passed into the send method to the values needed for the respective API
	 * @var array
	 */
	protected static $parameterMappings = [];

	/**
	 * @desc Sets up default structure and default values for the model that is acceptable by the API
	 * @var array
	 */
	protected static $structure = [];

  /**
   * @dec connection config for making requests.
   */
  private $config;

  /**
   * @desc Ivory\HttpAdapter\HttpAdapterInterface to make requests through.
   */
  protected $httpAdapter;

  /**
   * @desc Default config values. Passed in values will override these.
   */
  private static $apiDefaults = [
		'host'=>'api.sparkpost.com',
		'protocol'=>'https',
		'port'=>443,
		'strictSSL'=>true,
		'key'=>'',
		'version'=>'v1'
	];

	/**
	 * @desc Initializes config and httpAdapter for use later.
   * @param $httpAdapter Ivory\HttpAdapter\HttpAdapterInterface to make requests through.
   * @param $config connection config for making requests.
	 */
	public function __construct($httpAdapter, $config) {
    //config needs to be setup before adapter because of default adapter settings
    $this->setConfig($config);
    $this->setHttpAdapter($httpAdapter);
  }

  /**
	 * @desc Merges passed in headers with default headers for http requests
	 * @return Array - headers to be set on http requests
	 */
	private function getHttpHeaders(Array $headers = null) {
		$defaultOptions = [
			'Authorization' => $this->config['key'],
			'Content-Type' => 'application/json',
		];

		// Merge passed in headers with defaults
		if (!is_null($headers)) {
			foreach ($headers as $header => $value) {
				$defaultOptions[$header] = $value;
			}
		}
		return $defaultOptions;
	}


  /**
	 * @desc Helper function for getting the configuration for http requests
	 * @return \Ivory\HttpAdapter\Configuration
	 */
  private function getHttpConfig($config) {
		// get composer.json to extract version number
		$composerFile = file_get_contents(dirname(__FILE__) . "/../../composer.json");
		$composer = json_decode($composerFile, true);

		// create Configuration for http adapter
		$httpConfig = new Configuration();
		$baseUrl = $config['protocol'] . '://' . $config['host'] . ($config['port'] ? ':' . $config['port'] : '') . '/api/' . $config['version'];
		$httpConfig->setBaseUri($baseUrl);
		$httpConfig->setUserAgent('php-sparkpost/' . $composer['version']);
		return $httpConfig;
	}

  /**
   * @desc Validates and sets up the httpAdapter
   * @param $httpAdapter Ivory\HttpAdapter\HttpAdapterInterface to make requests through.
   * @throws \Exception
   */
  public function setHttpAdapter($httpAdapter) {
  	if (!$httpAdapter instanceOf HttpAdapterInterface) {
			throw new \Exception('$httpAdapter paramter must be a valid Ivory\HttpAdapter');
		}

    $this->httpAdapter = $httpAdapter;
    $this->httpAdapter->setConfiguration($this->getHttpConfig($this->config));
  }

	/**
	 * Allows the user to pass in values to override the defaults and set their API key
	 * @param Array $settingsConfig - Hashmap that contains config values for the SDK to connect to SparkPost
	 * @throws \Exception
	 */
	public function setConfig(Array $settingsConfig) {
		// Validate API key because its required
    if (!isset($settingsConfig['key']) || empty(trim($settingsConfig['key']))){
			throw new \Exception('You must provide an API key');
    }

		$this->config = self::$apiDefaults;

    // set config, overriding defaults
		foreach ($settingsConfig as $configOption => $configValue) {
			if(key_exists($configOption, $this->config)) {
				$this->config[$configOption] = $configValue;
			}
		}
	}

	/**
	 * @desc Private Method helper to reference parameter mappings and set the right value for the right parameter
   *
   * @param array $model (pass by reference) the set of values to map
   * @param string $mapKey a dot syntax path determining which value to set
   * @param mixed $value value for the given path
	 */
	protected function setMappedValue (&$model, $mapKey, $value) {
		//get mapping
		if( empty(static::$parameterMappings) ) {
			// if parameterMappings is empty we can assume that no wrapper is defined
			// for the current endpoint and we will use the mapKey to define the mappings directly
			$mapPath = $mapKey;
		}elseif(array_key_exists($mapKey, static::$parameterMappings)) {
			// use only defined parameter mappings to construct $model
			$mapPath = static::$parameterMappings[$mapKey];
		} else {
			return;
		}

		$path = explode('.', $mapPath);
		$temp = &$model;
		foreach( $path as $key ) {
			if( !isset($temp[$key]) ){
				$temp[$key] = null;
			}
			$temp = &$temp[$key];
		}
		$temp = $value;

	}

  /**
   * @desc maps values from the passed in model to those needed for the request
   * @param $requestConfig the passed in model
   * @param $model the set of defaults
   * @return array A model ready for the body of a request
   */
	protected function buildRequestModel(Array $requestConfig, Array $model=[] ) {
		foreach($requestConfig as $key => $value) {
			$this->setMappedValue($model, $key, $value);
		}
		return $model;
	}

	/**
	 * @desc posts to the api with a supplied body
   * @param body post body for the request
   * @return array Result of the request
	 */
	public function create(Array $body=[]) {
		return $this->callResource( 'post', null, ['body'=>$body]);
	}

  /**
   * @desc Makes a put request to the api with a supplied body
   * @param body Put body for the request
   * @return array Result of the request
	 */
	public function update( $resourcePath, Array $body=[]) {
		return $this->callResource( 'put', $resourcePath, ['body'=>$body]);
	}

	/**
	 * @desc Wrapper method for issuing GET request to current API endpoint
	 *
	 * @param string $resourcePath (optional) string resource path of specific resource
	 * @param array $options (optional) query string parameters
	 * @return array Result of the request
	 */
	public function get( $resourcePath=null, Array $query=[] ) {
		return $this->callResource( 'get', $resourcePath, ['query'=>$query] );
	}

	/**
	 * @desc Wrapper method for issuing DELETE request to current API endpoint
	 *
	 * @param string $resourcePath (optional) string resource path of specific resource
	 * @param array $options (optional) query string parameters
	 * @return array Result of the request
	 */
	public function delete( $resourcePath=null, Array $query=[] ) {
		return $this->callResource( 'delete', $resourcePath, ['query'=>$query] );
	}


  /**
   * @desc assembles a URL for a request
   * @param string $resourcePath path after the initial endpoint
   * @param array options array with an optional value of query with values to build a querystring from.
   * @return string the assembled URL
   */
  private function buildUrl($resourcePath, $options) {
    $url = join(['/', $this->endpoint, '/']);
    if (!is_null($resourcePath)){
      $url .= $resourcePath;
    }

    if( !empty($options['query'])) {
      $queryString = http_build_query($options['query']);
      $url .= '?'.$queryString;
    }

    return $url;
  }


  /**
   * @desc Prepares a body for put and post requests
   * @param array options array with an optional value of body with values to build a request body from.
   * @return string|null A json encoded string or null if no body was provided
   */
  private function buildBody($options) {
    $body = null;
    if( !empty($options['body']) ) {
			$model = static::$structure;
			$requestModel = $this->buildRequestModel( $options['body'], $model );
			$body = json_encode($requestModel);
		}
    return $body;
  }


	/**
	 * @desc Private Method for issuing GET and DELETE request to current API endpoint
	 *
	 *  This method is responsible for getting the collection _and_
	 *  a specific entity from the API endpoint
	 *
	 *  If resourcePath parameter is omitted, then we fetch the collection
	 *
	 * @param string $action HTTP method type
	 * @param string $resourcePath (optional) string resource path of specific resource
	 * @param array $options (optional) query string parameters
	 * @return array Result set of action performed on resource
	 */
	private function callResource( $action, $resourcePath=null, $options=[] ) {
		$action = strtoupper($action); // normalize

		if( !in_array($action, ['POST', 'PUT', 'GET', 'DELETE'])) {
			throw new \Exception('Invalid resource action');
		}

		$url = $this->buildUrl($resourcePath, $options);
		$body = $this->buildBody($options);

		//make request
		try {
			$response = $this->httpAdapter->send($url, $action, $this->getHttpHeaders(), $body);
			return json_decode($response->getBody()->getContents(), true);
		}
		/*
		 * Handles 4XX responses
     */
    catch (HttpAdapterException $exception) {
    	$response = $exception->getResponse();
    	$statusCode = $response->getStatusCode();
			if($statusCode === 404) {
				throw new \Exception("The specified resource does not exist", 404);
			}
			throw new \Exception("Received bad response from ".ucfirst($this->endpoint)." API: ". $statusCode );
		}
		/*
		 * Handles 5XX Errors, Configuration Errors, and a catch all for other errors
		 */
		catch (\Exception $exception) {
			throw new \Exception("Unable to contact ".ucfirst($this->endpoint)." API: ". $exception->getMessage());
		}
	}

}
