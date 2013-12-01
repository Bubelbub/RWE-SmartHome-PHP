<?php

/**
 * Class SmartHome
 *
 * @author ansgr <http://www.rwe-smarthome-forum.de/user-ansgr> / First version
 * @author donenik <http://www.rwe-smarthome-forum.de/user-donenik> / Second version
 * @author Bubelbub <bubelbub@gmail.com> / Latest version
 * @version 0.1.0
 */
class SmartHome
{
	/**
	 * @var array the configuration
	 */
	private $config;

	/**
	 * @var array a array with some cached things
	 */
	private $cache;

	/**
	 * @var \SimpleXMLElement the latest response
	 */
	private $response;

	/**
	 * @param string $host the hostname or ip address of the central control unit
	 * @param string $username the username of a user which can use the central control unit (shc)
	 * @param string $password the password of the user with username $username
	 */
	public function __construct($host = null, $username = null, $password = null)
	{
		$this->cache = file_exists(__FILE__ . '.cache') && is_writeable(__FILE__ . '.cache') ? json_decode(file_get_contents(__FILE__ . '.cache'), true) : array();
		$configFile = __DIR__ . '/config.ini';

		if(!file_exists($configFile))
		{
			throw new Exception('[0c800] Configuration file not found: ' . $configFile);
		}
		else if(!is_readable($configFile))
		{
			throw new Exception('[0c801] Cant read configuration file: ' . $configFile);
		}

		$this->config = null;
		if(($this->config = @parse_ini_file($configFile)) === false)
		{
			throw new Exception('[0c802] Error while read configuration file: ' . $configFile);
		}

		if(!array_key_exists('Host', $this->config))
		{
			throw new Exception('[0c803] Cant find variable "Host" in file ' . $configFile);
		}

		if(!array_key_exists('Username', $this->config))
		{
			throw new Exception('[0c804] Cant find variable "Username" in file ' . $configFile);
		}

		if(!array_key_exists('Password', $this->config))
		{
			throw new Exception('[0c805] Cant find variable "Password" in file ' . $configFile);
		}

		if($host !== null)
		{
			$this->config['Host'] = $host;
		}

		if($username !== null)
		{
			$this->config['Username'] = $username;
		}

		if($password !== null)
		{
			$this->config['Password'] = $password;
		}
	}

	/**
	 * Log in the central control unit (shc)
	 */
	public function login()
	{
		$data = array('UserName' => $this->config['Username'], 'Password' => base64_encode(hash('sha256', utf8_encode($this->config['Password']), true)));
		$this->doRequest('LoginRequest', $data);
		$this->cache['sessionID'] = (string) $this->getResponse()->attributes()->SessionId;
		$this->cache['configurationVersion'] = (string) $this->getResponse()->attributes()->CurrentConfigurationVersion;
	}

	/**
	 * Sends a request to the central control unit (shc)
	 * @param array $data the data which would be sended to central control unit (shc)
	 * @return SimpleXMLElement the response of the central control unit (shc)
	 */
	public function doRequest($type, $data = array(), $content = '')
	{
		$preparedData = array('RequestId' => '33300000-2200-1000-0000-' . substr(md5(uniqid()), 0, 12));
		if(array_key_exists('version', $this->cache))
		{
			$preparedData['Version'] = $this->cache['version'];
		}
		if(array_key_exists('sessionID', $this->cache))
		{
			$preparedData['SessionId'] = $this->cache['sessionID'];
		}
		if(array_key_exists('configurationVersion', $this->cache))
		{
			$preparedData['BasedOnConfigVersion'] = $this->cache['configurationVersion'];
		}
		$data = is_array($data) ? array_merge($preparedData, $data) : $preparedData;
		if(preg_match('#LoginRequest#i', $type))
		{
			unset($data['SessionId']);
		}

		$xml = new SimpleXMLElement('<BaseRequest xmlns:xsd="http://www.w3.org/2001/XMLSchema">' . $content . '</BaseRequest>');
		$xml->addAttribute('xsi:type', $type, 'http://www.w3.org/2001/XMLSchema-instance');
		foreach($data as $attribute => $value)
		{
			$xml->addAttribute($attribute, $value);
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://' . $this->config['Host'] . '/cmd');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSLVERSION, 3);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml->asXML());
		$this->setResponse(curl_exec($ch));
		curl_close($ch);

		$type = (string) $this->getResponse()->attributes('xsi', true)->type;
		if($type === 'GenericSHCErrorResponse' || $type === 'AuthenticationErrorResponse')
		{
			$this->login();
			return $this->doRequest($type, $data);
		}
		else if($type === 'VersionMismatchErrorResponse')
		{
			$this->cache['version'] = (string) $this->getResponse()->attributes()->ExpectedVersion;
		}

		return $this->getResponse();
	}

	/**
	 * Get the information's of the central control unit
	 *
	 * @return SimpleXMLElement[] the information's of the central control unit
	 */
	public function getInformation()
	{
		return $this->doRequest('ProbeShcRequest')->ShcInformation;
	}

	/**
	 * Get all logical devices with their state(s)
	 *
	 * @return SimpleXMLElement[] all logical devices with their state(s)
	 */
	public function getAllLogicalDeviceStates()
	{
		return $this->doRequest('GetAllLogicalDeviceStatesRequest')->States;
	}

	/**
	 * Get the configuration of the central control unit (shc)
	 *
	 * @return SimpleXMLElement the configuration of the central control unit (shc)
	 */
	public function getConfiguration()
	{
		return $this->doRequest('GetEntitiesRequest', array('EntityType' => 'Configuration'));
	}

	/**
	 * Switches an actuator state
	 *
	 * @param string $logicalDeviceId the logical device id
	 * @param boolean $on state to switch on
	 * @return SimpleXMLElement
	 */
	public function switchActuator($logicalDeviceId, $on)
	{
		return $this->doRequest('SetActuatorStatesRequest', null, '<ActuatorStates>
	            <LogicalDeviceState xsi:type="SwitchActuatorState"
	                LID="' . $logicalDeviceId . '"
	                IsOn="' . ($on ? 'true' : 'false') . '"
	            />
            </ActuatorStates>');
	}

	/**
	 * Set the temperature of an actuator
	 *
	 * @param string $logicalDeviceId the logical device id
	 * @param float $pointTemperature the temperature to set
	 * @return SimpleXMLElement
	 */
	public function setPointTemperature($logicalDeviceId, $pointTemperature)
	{
		return $this->doRequest('SetActuatorStatesRequest', null, '<ActuatorStates>
				<LogicalDeviceState xsi:type="RoomTemperatureActuatorState"
					LID="' . $logicalDeviceId . '"
					PtTmp="' . $pointTemperature . '"
					OpnMd="Auto"
					WRAc="False"
				/>
			</ActuatorStates>');
	}

	/**
	 * Set the state of an logical device
	 *
	 * @param string $logicalDeviceId the logical device id
	 * @param boolean $pointTemperature the new state of the device / true = on, false = off
	 * @return SimpleXMLElement
	 */
	function setLogicalDeviceState($logicalDeviceId, $on)
	{
		return $this->doRequest('SetActuatorStatesRequest', null, '<ActuatorStates>
				<LogicalDeviceState xsi:type="GenericDeviceState" LID="' . $logicalDeviceId . '">
					<Ppts>
						<Ppt xsi:type="BooleanProperty" Name="Value" Value="' . ($on ? 'true' : 'false') . '" />
					</Ppts>
				</LogicalDeviceState>
			</ActuatorStates>');
	}

	/**
	 * Get the last response of an request
	 *
	 * @return \SimpleXMLElement the last response of shc
	 */
	public function getResponse($asXML = false)
	{
		return $asXML ? $this->response->asXML() : $this->response;
	}

	/**
	 * Get the last response of an request
	 *
	 * @param string $response the response of shc
	 */
	public function setResponse($response)
	{
		$xml = new SimpleXMLElement('<BaseResponse />');
		try
		{
			$xml = new SimpleXMLElement($response);
		}
		catch(Exception $ex){}
		$xml->registerXPathNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$this->response = $xml;
	}

	/**
	 * Write the cache to file
	 */
	function __destruct()
	{
		file_put_contents(__FILE__ . '.cache', json_encode(is_array($this->cache) ? $this->cache : array()));
	}
}
