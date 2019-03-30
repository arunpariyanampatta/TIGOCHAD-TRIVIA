<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of SokaLib
 *
 * @author DELL
 */
error_reporting(E_ALL);
//require_once 'SokaServices';
date_default_timezone_set("Africa/Brazzaville");
require_once 'SokaServices.php';
class TigoChadLib {

var $amount,$msisdn;
public function __construct($msisdn,$amount) {   
    
    $this->amount = $amount;
    $this->msisdn = $msisdn;

    
}    //put your code here






function sendCharging($transactionID,$moID){ 

$sokaServicesObj = new SokaServices("consumer");  
    
$url = 'http://192.168.50.68:8002/osb/services/AdjustAccount_1_0?wsdl';
$date = date("YmdHis");
 $username = "live_tigo_quiz";
 $password = "Qu1z@#123";
 $expDateTime = "2020-01-01 00:00:00";
$xmlPost = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="http://xmlns.tigo.com/AdjustAccountRequest/V1" xmlns:v3="http://xmlns.tigo.com/RequestHeader/V3" xmlns:v2="http://xmlns.tigo.com/ParameterType/V2">
<soapenv:Header xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
<cor:debugFlag xmlns:cor="http://soa.mic.co.af/coredata_1">true</cor:debugFlag>
<wsse:Security>
<wsse:UsernameToken>
<wsse:Username>live_tigo_quiz</wsse:Username>
<wsse:Password>Qu1z@#123</wsse:Password>
</wsse:UsernameToken>
</wsse:Security>
</soapenv:Header>
<soapenv:Body>
<v1:AdjustAccountRequest>
<v3:RequestHeader>
<v3:GeneralConsumerInformation>
<v3:consumerID>TIGO_QUIZ</v3:consumerID>
<v3:transactionID>'.$transactionID.'</v3:transactionID>
<v3:country>TCD</v3:country>
<v3:correlationID>33</v3:correlationID>
</v3:GeneralConsumerInformation>
</v3:RequestHeader>
<v1:RequestBody>
<v1:customerID>'.$this->msisdn.'</v1:customerID>
<v1:walletID>2000</v1:walletID>
<v1:adjustmentValue>-'.$this->amount.'</v1:adjustmentValue>
<v1:externalTransactionID>'.$transactionID.'</v1:externalTransactionID>
<v1:comment>Charging</v1:comment>
<v1:additionalParameters>
<v2:ParameterType>
<v2:parameterName>ExpiryDateTime</v2:parameterName>
<v2:parameterValue>'.$expDateTime.'</v2:parameterValue>
</v2:ParameterType>
</v1:additionalParameters>
</v1:RequestBody>
</v1:AdjustAccountRequest>
</soapenv:Body>
</soapenv:Envelope>';
        $headers = array(
            "Method:POST",
            "Content-type: application/soap+xml;charset=\"utf-8\"",
            "Content-length: " . strlen($xmlPost),
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Accept: application/soap+xml",
            "Content-Encoding:gzip,compress",
            "SOAPAction:AdjustAccount",
        );

        
    $txnID = $sokaServicesObj->recordPaymentTransaction($this->msisdn,$transactionID, $xmlPost, $moID,"SENT",$this->amount);    
        
$ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password); // username and password - declared at the top of the doc
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_TIMEOUT, 160);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlPost); // the SOAP request
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $soapResponse = curl_exec($ch);
        curl_close($ch);
        if($soapResponse){
        $data['isResponse'] = TRUE;    
        $data['response'] = $soapResponse;
        $data['txnID'] = $txnID;
        }
        else{    
        $data['isResponse'] = FALSE;    
        $data['response'] = "PROCESS_FAILED";
        $data['txnID'] = $txnID;
        }
       return $data;
    
    
}





function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
} 

function sendSms($sms){
$mobile = $this->msisdn;    
$sms = urlencode($sms);    
$result = file_get_contents("http://localhost:13013/cgi-bin/sendsms?username=gtlChad2018TX&password=gtlChad2018TX&to=".$mobile."&text=".$sms."&from=9999&dlr-mask=31");
return $result;
}
}
