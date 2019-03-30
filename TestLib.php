<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once 'TigoChadLib.php';
$msisdn = "23599047088";
$moID = 0;
$trasnactionID = date("YmdHis").$msisdn;
$sokaLib = new TigoChadLib($msisdn, 60);

$result = $sokaLib->sendCharging($transactionID,$moID);

var_dump($result);