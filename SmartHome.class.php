<?php

/**
 * Class SmartHome
 *
 * @author ansgr <http://www.rwe-smarthome-forum.de/user-ansgr> / First version
 * @author donenik <http://www.rwe-smarthome-forum.de/user-donenik> / Second version
 * @author Bubelbub <bubelbub@gmail.com> / Latest version
 */
class SmartHome
{
	/**
	 * @var string the hostname or ip address of the central control unit
	 */
	private $host;

	/**
	 * @var string the username of a user which can use the central control unit (shc)
	 */
	private $username;

	/**
	 * @var string the password of the user with username $username
	 */
	private $password;

	/**
	 * @var boolean the session id of the user above
	 */
	private $sessionId = false;

	/**
	 * @var string the configuration version which readed from central control unit (shc)
	 */
	private $configurationVersion;

	/**
	 * @var \SimpleXMLElement the latest response
	 */
	private $response;

	/**
	 * @var string|integer|float the version of the central control unit (shc)
	 */
	private $version = '1.60';

	/**
	 * @param string $host the hostname or ip address of the central control unit
	 * @param string $username the username of a user which can use the central control unit (shc)
	 * @param string $password the password of the user with username $username
	 */
	public function __construct($host, $username, $password)
	{
		$this->host = $host;
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Log in the central control unit (shc)
	 */
	public function login()
	{
		$data = '<BaseRequest
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xsi:type="LoginRequest"
		RequestId="33300000-2200-1000-0000-' . substr(md5(uniqid()), 0, 12) . '"
		Version="' . $this->version . '"
		UserName="' . $this->username . '"
		Password="' . base64_encode(hash('sha256', utf8_encode($this->password), true)) . '"
		/>';
		$this->response = $this->doRequest($data);
		$a = $this->getResponse()->attributes();
		$this->sessionId = $a->SessionId;
		$this->configurationVersion = $a->CurrentConfigurationVersion;
	}

	/**
	 * @param string $data the data which would be sended to central control unit (shc)
	 * @return SimpleXMLElement the response of the central control unit (shc)
	 */
	public function doRequest($data)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://' . $this->host . '/cmd');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSLVERSION, 3);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$output = curl_exec($ch);
		curl_close($ch);
		$xml = new SimpleXMLElement($output);
		return $xml;
	}

	/**
	 * Get the information's of the central control unit
	 *
	 * @return SimpleXMLElement[] the information's of the central control unit
	 */
	public function getInformation()
	{
		$data = '<BaseRequest
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xsi:type="ProbeShcRequest"
		RequestId="33300000-2200-1000-0000-' . substr(md5(uniqid()), 0, 12) . '"
		Version="' . $this->version . '"
		SessionId="' . $this->sessionId . '"
		/>';
		$this->response = $this->doRequest($data);

		return $this->getResponse()->ShcInformation;
	}

	/**
	 * Get all logical devices with their state(s)
	 *
	 * @return SimpleXMLElement[] all logical devices with their state(s)
	 */
	public function getAllLogicalDeviceStates()
	{
		$data = '<BaseRequest
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xsi:type="GetAllLogicalDeviceStatesRequest"
		RequestId="33300000-2200-1000-0000-' . substr(md5(uniqid()), 0, 12) . '"
		Version="' . $this->version . '"
		SessionId="' . $this->sessionId . '"
		BasedOnConfigVersion="' . $this->configurationVersion . '"
		/>';
		$this->response = $this->doRequest($data);

		return $this->getResponse()->States;
	}

	/**
	 * Get the configuration of the central control unit (shc)
	 *
	 * @return SimpleXMLElement the configuration of the central control unit (shc)
	 */
	public function getConfiguration()
	{
		$data = '<BaseRequest
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xsi:type="GetEntitiesRequest"
		RequestId="33300000-2200-1000-0000-' . substr(md5(uniqid()), 0, 12) . '"
		Version="' . $this->version . '"
		SessionId="' . $this->sessionId . '"
		BasedOnConfigVersion="' . $this->configurationVersion . '"
		EntityType="Configuration"
		/>';
		$this->response = $this->doRequest($data);

		return $this->getResponse();
	}

	/**
	 * Switches an actuator state
	 *
	 * @param string $logicalDeviceId the logical device id
	 * @param boolean $on state to switch on
	 */
	public function switchActuator($logicalDeviceId, $on)
	{
		$data = '<BaseRequest
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"        
        xsi:type="SetActuatorStatesRequest"
        Version="' . $this->version . '"
        RequestId="33300000-2200-1000-0000-' . substr(md5(uniqid()), 0, 12) . '"
        
        SessionId="' . $this->sessionId . '"
        BasedOnConfigVersion="' . $this->configurationVersion . '"
        >
            <ActuatorStates>
            <LogicalDeviceState xsi:type="SwitchActuatorState"
                LID="' . $logicalDeviceId . '"
                IsOn="' . ($on ? 'true' : 'false') . '"
            />
            
            </ActuatorStates>
        </BaseRequest>
        ';
		$this->response = $this->doRequest($data);
	}

	/**
	 * Set the temperature of an actuator
	 *
	 * @param string $logicalDeviceId the logical device id
	 * @param float $pointTemperature the temperature to set
	 */
	public function setPointTemperature($logicalDeviceId, $pointTemperature)
	{
		$data = '<BaseRequest
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"        
        xsi:type="SetActuatorStatesRequest"
        Version="' . $this->version . '"
        RequestId="33300000-2200-1000-0000-' . substr(md5(uniqid()), 0, 12) . '"
        
        SessionId="' . $this->sessionId . '"
        BasedOnConfigVersion="' . $this->configurationVersion . '"
        >
            <ActuatorStates>
            <LogicalDeviceState xsi:type="RoomTemperatureActuatorState"
                LID="' . $logicalDeviceId . '"
                PtTmp="' . $pointTemperature . '"
               OpnMd="Auto"
                WRAc="False"
            />
            
            </ActuatorStates>
        </BaseRequest>
        ';
		$this->response = $this->doRequest($data);
	}

	/**
	 * Set the state of an logical device
	 *
	 * @param string $logicalDeviceId the logical device id
	 * @param boolean $pointTemperature the new state of the device / true = on, false = off
	 */
	function setLogicalDeviceState($logicalDeviceId, $on)
	{
		$data = '<BaseRequest
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"        
        xsi:type="SetActuatorStatesRequest"
        Version="' . $this->version . '"
        RequestId="33300000-2200-1000-0000-' . substr(md5(uniqid()), 0, 12) . '"
        
        SessionId="' . $this->sessionId . '"
        BasedOnConfigVersion="' . $this->configurationVersion . '"
        >
            <ActuatorStates>
                <LogicalDeviceState xsi:type="GenericDeviceState" 
                    LID="' . $logicalDeviceId . '"
                    >
                    <Ppts>
                        <Ppt xsi:type="BooleanProperty" 
                            Name="Value" 
                            Value="' . ($on ? 'true' : 'false') . '" 
                        />
                    </Ppts>
                </LogicalDeviceState>
            </ActuatorStates>
        </BaseRequest>
        ';
		$this->response = $this->doRequest($data);
	}

	/**
	 * Get the last response of an request
	 *
	 * @return \SimpleXMLElement the last response of shc
	 */
	public function getResponse()
	{
		return $this->response;
	}
}
