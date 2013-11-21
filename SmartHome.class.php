<?php

class SmartHome
{
	private $host;
	private $username;
	private $password;
	private $sessionId = false;
	private $configurationVersion = false;

	function __construct($host, $username, $password)
	{
		$this->host = $host;
		$this->username = $username;
		$this->password = $password;
	}

	function login()
	{
		$data = '<BaseRequest
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xsi:type="LoginRequest"
		RequestId="33300000-2200-1000-0000-'.substr(md5(uniqid()), 0, 12).'"
		Version="1.50"
		UserName="'.$this->username.'"
		Password="'.base64_encode(hash('sha256', utf8_encode($this->password), true)).'"
		/>';
		$response = $this->doRequest($data);
		$a = $response->attributes();
		$this->sessionId = $a->SessionId;
		$this->configurationVersion = $a->CurrentConfigurationVersion;
	}

	function doRequest($data)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://'.$this->host.'/cmd');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		$xml = new SimpleXMLElement($output);
		return $xml;
	}

	function getInformation()
	{
		$data = '<BaseRequest
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xsi:type="ProbeShcRequest"
		RequestId="33300000-2200-1000-0000-'.substr(md5(uniqid()), 0, 12).'"
		Version="1.50"
		SessionId="'.$this->sessionId.'"
		/>';
		$response = $this->doRequest($data);
		return $response->ShcInformation;
	}

	function getAllLogicalDeviceStates()
	{
		$data = '<BaseRequest
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xsi:type="GetAllLogicalDeviceStatesRequest"
		RequestId="33300000-2200-1000-0000-'.substr(md5(uniqid()), 0, 12).'"
		Version="1.50"
		SessionId="'.$this->sessionId.'"
		BasedOnConfigVersion="'.$this->configurationVersion.'"
		/>';
		$response = $this->doRequest($data);
		return $response->States;
	}

	function getConfiguration()
	{
		$data = '<BaseRequest
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xsi:type="GetEntitiesRequest"
		RequestId="33300000-2200-1000-0000-'.substr(md5(uniqid()), 0, 12).'"
		Version="1.50"
		SessionId="'.$this->sessionId.'"
		BasedOnConfigVersion="'.$this->configurationVersion.'"
		EntityType="Configuration"
		/>';
		$response = $this->doRequest($data);

		return $response;
	}

	function switchActuator($logicalDeviceId, $on)
	{
		$data = '<BaseRequest
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xsi:type="SetActuatorStatesRequest"
		RequestId="33300000-2200-1000-0000-'.substr(md5(uniqid()), 0, 12).'"
		Version="1.50"
		SessionId="'.$this->sessionId.'"
		BasedOnConfigVersion="'.$this->configurationVersion.'"
		>
		    <ActuatorStates>
			<LogicalDeviceState xsi:type="SwitchActuatorState">
			    <LogicalDeviceId>'.$logicalDeviceId.'</LogicalDeviceId>
			    <IsOn>'.($on?'true':'false').'</IsOn>
			</LogicalDeviceState>
		    </ActuatorStates>
		</BaseRequest>
		';
		$response = $this->doRequest($data);
	}

	function setPointTemperature($logicalDeviceId, $pointTemperature)
	{
		$data = '<BaseRequest
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xsi:type="SetActuatorStatesRequest"
		RequestId="33300000-2200-1000-0000-'.substr(md5(uniqid()), 0, 12).'"
		Version="1.50"
		SessionId="'.$this->sessionId.'"
		BasedOnConfigVersion="'.$this->configurationVersion.'"
		>
		    <ActuatorStates>
			<LogicalDeviceState xsi:type="RoomTemperatureActuatorState">
			    <LogicalDeviceId>'.$logicalDeviceId.'</LogicalDeviceId>
			    <PointTemperature>'.$pointTemperature.'</PointTemperature>
			</LogicalDeviceState>
		    </ActuatorStates>
		</BaseRequest>
		';
		$response = $this->doRequest($data);
	}
}
