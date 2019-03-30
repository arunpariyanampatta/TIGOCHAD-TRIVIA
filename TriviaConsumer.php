<?php

set_time_limit(0);
ini_set('memory_limit', '-1');
date_default_timezone_set('Africa/Brazzaville');
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
  .php'; */
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once 'config.php';
require_once 'SokaLib.php';
require_once 'SokaServices.php';


try {
    $connection = new AMQPStreamConnection("localhost", 5672, "trivia_manage", "manage@CNu8Ut");
    $channel = $connection->channel();
} catch (Exception $ex) {
	sleep(5);
    //$ex->getMessage();
    //echo $ex;
}

$callback = function($data) {
    $data->delivery_info['channel']->basic_ack($data->delivery_info['delivery_tag']); //acknowledge the message 
    $msg = json_decode($data->body, TRUE);
    $msisdn = $msg['msisdn'];
    $moID = $msg['id'];
    $message = $msg['message'];
    processMessage($msisdn, $message, $moID);
};
$channel->exchange_declare("trivia_exchange", "direct", false, true, false);
$channel->queue_declare("trivia_queue", false, true, false, false);
$channel->queue_bind('trivia_queue', 'trivia_exchange', 'tigochad.trivia.key');
$channel->basic_qos(null, QOS_LIMIT, null);
$channel->basic_consume("trivia_queue", "", false, false, false, false, $callback);


while (count($channel->callbacks)) {
    $channel->wait();
}

$connection->close();
$channel->close();

function processMessage($msisdn, $sms, $moID) {
    $sokaServicesObj = new SokaServices("consumer");
    $transactionID = date("YmdHis") . $msisdn;
    $sokaLib = new SokaLib($msisdn, 60);
    $subDetails = $sokaServicesObj->getSubscriberDetails($msisdn);
    if (empty($subDetails)) {//send message to subscribe
        if (strtolower($sms) == "jeu") {//record to subscription       
            $sokaServicesObj->addSubscriber($msisdn);
            $smsStatus = $sokaLib->sendSms(0,$moID,"SUB_SMS",SUB_SMS);
            $smsInfo['STATUS_CODE'] = $smsStatus['response'];
            $smsID = $smsStatus['ID'];
            $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
            
            $data = $sokaLib->sendCharging($transactionID, $moID);
            $istransactionProcessed = $data['isResponse'];
            $responseData = $data['response'];
            $txnID = $data['txnID'];
            if ($istransactionProcessed) {
                $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $responseData);
                $xml = new SimpleXMLElement($response);

                $body = $xml->xpath('//soapenvBody')[0];
                $array = json_decode(json_encode((array) $body), TRUE); if(isset($array['v11AdjustAccountResponse']['v31ResponseHeader']['v31GeneralResponse']['v31description'])){
                 $responseStatus = $array['v11AdjustAccountResponse']['v31ResponseHeader']['v31GeneralResponse']['v31description'];
                 
                 }
                 else{
                     $responseStatus = "FAILED";
                 }
                
//update Trnasaction details
                if ($responseStatus == "The request has been processed successfully.") {
                    
                    $updatePaymentArray = array();
                    $updatePaymentArray['RESPONSE_RECEIVED'] = $responseData;
                    $updatePaymentArray['TXN_STATUS'] = 'SUCCESS';
                    $updatePaymentArray['RESP_TIME'] = date("Y-m-d H:i:s");
                    $sokaServicesObj->updatePaymentTransaction($txnID,$updatePaymentArray);
                    $nextQnID = mt_rand(3215, 3808);
                    $txnID = $sokaServicesObj->recordPaymentTransaction($msisdn, $transactionID, $requestData, $moID, $responseData, "SUCCESS");
                    
                    
                    
                    $questionData = $sokaServicesObj->getQuestion($nextQnID);
                    $question = $questionData['QUESTION'];
                    $sms = FIRST_APPEND . ". " . $question;
                    
                    
                    $subscriberInfo = array();
                    
                    $subscriberInfo['LAST_CHARGING'] = date("Y-m-d H:i:s");
                    $subscriberInfo['LAST_ACTIVE'] = date("Y-m-d H:i:s");
                    $subscriberInfo['_STATUS'] = 'QN_SENT';
                    $subscriberInfo['LAST_QN_ID'] = $questionData['ID'];
                    
                    $sokaServicesObj->updatesubscriberInformation($msisdn,$subscriberInfo);
                    
                    $smsStatus = $sokaLib->sendSms($txnID,$moID,"QUESTION",$sms);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
                    $sokaServicesObj->closeDB();
                    return TRUE;
                } else {
                    $updatePaymentArray = array();
                    $updatePaymentArray['RESPONSE_RECEIVED'] = $responseData;
                    $updatePaymentArray['TXN_STATUS'] = 'FAILED';
                    $updatePaymentArray['RESP_TIME'] = date("Y-m-d H:i:s");
                    $sokaServicesObj->updatePaymentTransaction($txnID,$updatePaymentArray);
                    $nextQnID = mt_rand(3215, 3808);
                    $questionData = $sokaServicesObj->getQuestion($nextQnID);
                    $question = $questionData['QUESTION'];
                    $sms = FIRST_APPEND . ". " . $question; 
                    $subscriberInfo = array();
                    $subscriberInfo['_STATUS'] = 'QN_SENT';
                    $subscriberInfo['LAST_QN_ID'] = $questionData['ID'];
                    $sokaServicesObj->updatesubscriberInformation($msisdn,$subscriberInfo);
                    
                    
                    $smsStatus = $sokaLib->sendSms($txnID,$moID,"QUESTION",$sms);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
                    $sokaServicesObj->closeDB();
                    return true;
                }
            } else {
                $smsStatus = $sokaLib->sendSms($txnID,$moID,"ERROR_MESSAGE",ERROR_MSG);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
                $sokaServicesObj->closeDB();
                return true;
            }
        } else {
                    $smsStatus = $sokaLib->sendSms(0,$moID,"NON_CUSTOMER",NON_CUSTOMER);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
                    $sokaServicesObj->closeDB();
                    
            return true;
        }
    } else {//consider user response
        if (strtolower($sms) == "help") {
             $smsStatus = $sokaLib->sendSms(0,$moID,"HELP_MSG",HELP_MSG);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
            return true;
        } elseif (strtolower($sms) == "point" || strtolower($sms) == "pointi"||strtolower($sms) == "points") {
            $pointSMS = str_replace("%s", $subDetails['TOTAL_POINTS'], POINT_MSG);
             $smsStatus = $sokaLib->sendSms(0,$moID,"POINT_SMS",$pointSMS);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
            $sokaServicesObj->closeDB();
            return true;
        } elseif (strtolower($sms) == "stop") {
           $subscriberInfo = array();
                    
                    
                    $subscriberInfo['ACTIVITY_STATUS'] = 'INACTIVE';
                    
                    $sokaServicesObj->updatesubscriberInformation($msisdn,$subscriberInfo);
                    
             $smsStatus = $sokaLib->sendSms(0,$moID,"UNSUB_SMS",UNSUB_SMS);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
            
            
            $sokaServicesObj->closeDB();
            return true;
        } else {
            if ($subDetails['ACTIVITY_STATUS'] == "INACTIVE") { //if inactive make the subscriber active
                $subscriberInfo = array();
                $subscriberInfo['ACTIVITY_STATUS'] = "ACTIVE";
                $sokaServicesObj->updatesubscriberInformation($msisdn,$subscriberInfo);    
                $smsStatus = $sokaLib->sendSms(0,$moID,"SUB_SMS",SUB_SMS);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
            }
            $transactionID = date("YmdHis") . $msisdn;
            //send for charging
            $data = $sokaLib->sendCharging($transactionID, $moID); // this will send charging request
            $response = $data['response'];
            $istransactionProcessed = $data['isResponse'];
            $txnID = $data['txnID'];
            if ($istransactionProcessed) {//check if there is any response
                $responseData = $response;
                $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $responseData);
                $xml = new SimpleXMLElement($response);
                $body = $xml->xpath('//soapenvBody')[0];
                $array = json_decode(json_encode((array) $body), TRUE);
                 $lastQnID = $subDetails['LAST_QN_ID'];
                 $answer = $sms;
                 if(isset($array['v11AdjustAccountResponse']['v31ResponseHeader']['v31GeneralResponse']['v31description'])){
                 $responseStatus = $array['v11AdjustAccountResponse']['v31ResponseHeader']['v31GeneralResponse']['v31description'];
                 
                 }
                 else{
                     $responseStatus = "FAILED";
                 }
                if ($responseStatus == "The request has been processed successfully.") {//
                   
                     $updatePaymentArray = array();
                    $updatePaymentArray['RESPONSE_RECEIVED'] = $responseData;
                    $updatePaymentArray['TXN_STATUS'] = 'SUCCESS';
                    $updatePaymentArray['RESP_TIME'] = date("Y-m-d H:i:s");
                    $sokaServicesObj->updatePaymentTransaction($txnID,$updatePaymentArray);
                    
                    
                    $nextQnID = mt_rand(3215, 3808);
                    //1.check Answer Status
                    $answerStatus = $sokaServicesObj->getAnswerStatus($lastQnID);//check answer status
                    if (strtolower($answer) == strtolower($answerStatus['ANSWER'])) {//correct Answer
                        $points = $answerStatus['POINTS'];
                        $sokaServicesObj->recordAnswer($lastQnID, $msisdn, $answer, "CORRECT", $points, "CHARGING_SUCCESS");
                        $questionData = $sokaServicesObj->getQuestion($nextQnID);
                        $question = $questionData['QUESTION'];
                        $qnsms = str_replace("%s", $subDetails['TOTAL_POINTS'] + $points, MESSAGE_TO_APPEND_NEXT_CORRECT);
                        $qnsms .= ". " . $question;
                       
                         $smsStatus = $sokaLib->sendSms($txnID,$moID,"QUESTION",$qnsms);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
                    $subscriberInfo = array();
                    $subscriberInfo['LAST_CHARGING'] = date("Y-m-d H:i:s");
                    $subscriberInfo['LAST_ACTIVE'] = date("Y-m-d H:i:s");
                    $subscriberInfo['_STATUS'] = 'QN_SENT';
                    $subscriberInfo['TOTAL_POINTS'] = $subDetails['TOTAL_POINTS']+$points;
                    $subscriberInfo['LAST_QN_ID'] = $questionData['ID'];
                    $sokaServicesObj->updatesubscriberInformation($msisdn,$subscriberInfo);
                    
                        $sokaServicesObj->closeDB();
                        return true;
                    } else {//wrong Answer
                        $points = "25";
                        $sokaServicesObj->recordAnswer($lastQnID, $msisdn, $answer, "WRONG", $points, "CHARGING_SUCCESS");
                        $questionData = $sokaServicesObj->getQuestion($nextQnID);
                        $question = $questionData['QUESTION'];
                        $qnsms = str_replace("%s", $subDetails['TOTAL_POINTS'] + $points, MESSAGE_TO_APPEND_NEXT_WRONG);
                        $qnsms .= ". " . $question;
                        $subscriberInfo = array();
                    $subscriberInfo['LAST_CHARGING'] = date("Y-m-d H:i:s");
                    $subscriberInfo['LAST_ACTIVE'] = date("Y-m-d H:i:s");
                    $subscriberInfo['_STATUS'] = 'QN_SENT';
                    $subscriberInfo['TOTAL_POINTS'] = $subDetails['TOTAL_POINTS']+$points;
                    $subscriberInfo['LAST_QN_ID'] = $questionData['ID'];
                    $sokaServicesObj->updatesubscriberInformation($msisdn,$subscriberInfo);
                        
                         $smsStatus = $sokaLib->sendSms($txnID,$moID,"QUESTION",$qnsms);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
                        
                        $sokaServicesObj->closeDB();
                        return true;
                    }
                } else {//send message
                     $updatePaymentArray = array();
                    $updatePaymentArray['RESPONSE_RECEIVED'] = $responseData;
                    $updatePaymentArray['TXN_STATUS'] = 'FAILED';
                    $updatePaymentArray['RESP_TIME'] = date("Y-m-d H:i:s");
                    $sokaServicesObj->updatePaymentTransaction($txnID,$updatePaymentArray);
                    $sokaServicesObj->recordAnswer($lastQnID, $msisdn, $answer, "FAILED", "0", "CHARGING_FAILED");
                    $subscriberInfo = array();
                    $subscriberInfo['_STATUS'] = 'CHARGED_FAILED';
                    $sokaServicesObj->updatesubscriberInformation($msisdn,$subscriberInfo);
                    $smsStatus = $sokaLib->sendSms($txnID,$moID,"LOW_BALANCE",LOW_BALANCE);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
                    $sokaServicesObj->closeDB();
                    return true;
                }
            } else {//send error message
                $sokaServicesObj->recordAnswer($lastQnID, $msisdn, $answer, "FAILED", "0", "CHARGING_ERROR");
                 $smsStatus = $sokaLib->sendSms($txnID,$moID,"ERROR_MSG",ERROR_MSG);
                    $smsInfo['STATUS_CODE'] = $smsStatus['response'];
                    $smsID = $smsStatus['ID'];
                    $sokaServicesObj->updateSMSStatus($smsID,$smsInfo);
                     $subscriberInfo = array();
                    $subscriberInfo['_STATUS'] = 'CHARGED_FAILED';
                    $sokaServicesObj->updatesubscriberInformation($msisdn,$subscriberInfo);
                $sokaServicesObj->closeDB();
                return true;
            }
        }
    }
}
