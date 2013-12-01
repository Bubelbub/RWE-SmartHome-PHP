<?php
/**
 * Created with IntelliJ IDEA.
 * Project: RWE-SmartHome
 * User: Bubelbub <bubelbub@gmail.com>
 * Date: 01.12.13
 * Time: 12:18
 */

require_once __DIR__ . '/../SmartHome.class.php';

$shc = new SmartHome();
$shc->getAllLogicalDeviceStates();
var_dump($shc->getResponse(true));
