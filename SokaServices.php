<?php

setlocale(LC_ALL, 'fr_FR');
date_default_timezone_set("Africa/Brazzaville");
error_reporting(E_ALL);
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of HalotelServices
 *
 * @author DELL
 */
require_once 'DbConfig.php';

class SokaServices {

    private $dbConn;
    private $userType;

    public function __construct($userType) {
        $this->userType = $userType;
        $this->dbConn = $this->connect_db();
    }

    function getMessageLogs() :array{
        
        $sql = "SELECT id,sender,message from message_logs_tigochad WHERE outgoing_status = '1'";
        $result = $this->dbConn->query($sql);
        $resultArray = array();
        while ($row = $result->fetch_assoc()) {
            $resultArray[] = $row;
        }
        if(empty($resultArray)){
            
            return array();
        }
        else{
        return $resultArray;
        }
    }

    function updateMessageLogStatus($status, $id):int {
        $sql = "UPDATE message_logs_tigochad SET outgoing_status = " . $status . " WHERE ID = " . $id;
       $this->dbConn->query($sql);
       return $this->dbConn->affected_rows;
    }

    function getSubscriberDetails($msisdn) :array{
        $sql = "SELECT * FROM  tbl_qn_subscribers where MSISDN = '" . $msisdn . "'";
        $result = $this->dbConn->query($sql);
        $resultarray = $result->fetch_assoc();
        if(empty($resultarray)){
            return array();
        }
        else{
        return $resultarray;
        }
    }

    function updatesubscriberInformation($msisdn, $updateArray = array()):int{
        $updateString = $this->arrayToString($updateArray);
        $sql = "UPDATE tbl_qn_subscribers SET {$updateString} WHERE MSISDN = '".$msisdn."'";
        
        $this->dbConn->query($sql);
        return $this->dbConn->affected_rows;
    }

    function getAnswerStatus($lastQnID) :array{
        $sql = "SELECT ANSWER,POINTS FROM tbl_qn_questions where ID = " . $lastQnID;
        $resultArray = $this->dbConn->query($sql);
        $result = $resultArray>fetch_assoc();
        if(empty($result)){
            
            return array();
        }
        else{
        return $result;
        }
    }

    function recordAnswer($qnID, $msisdn,$answer,$answerStatus, $point, $billingStatus) :int{                    
        $sql = "INSERT INTO tbl_qn_answers (`QN_ID`,`MSISDN`,`ANSWER`,`ANSWER_STATUS`,`EARNED_POINTS`,`BILLING_STATUS`)VALUES (" . $qnID . ",'".$msisdn."','" . $answer . "','" . $answerStatus . "'," . $point . ",'" . $billingStatus . "')";
        $this->dbConn->query($sql);
        return $this->dbConn->insert_id;
    }

    function recordPaymentTransaction($msisdn, $transactionID, $request, $moID, $status, $amount):int {
        $table = "vas_transaction_trivia_" . date("Ymd");
        $sql = "INSERT INTO " . $table . "(`MSISDN`,`TXN_ID`,`REQUEST_SENT`,`MO_ID`,`TXN_STATUS`,`AMOUNT`) VALUES('" . $msisdn . "','" . $transactionID . "','" . $request . "',$moID,'" . $status . "'," . $amount . ")";
        
        $this->dbConn->query($sql);
        return $this->dbConn->insert_id;
    }

    function getQuestion($qnID):array {
        $sql = "SELECT ID,QUESTION FROM tbl_qn_questions where ID = " . $qnID;
        $resultArray =$this->dbConn->query($sql);
        $result =$resultArray->fetch_assoc();
        if(empty($result)){   
            return array();
        }
        else{
        return $result;
        }
    }

    function updatePaymentTransaction($txnID,$paymentArray=array()):int {
        $table = "vas_transaction_trivia_" .date("Ymd");
        $updateString = $this->arrayToString($paymentArray);
        $sql = "UPDATE {$table} SET  {$updateString} WHERE ID = " . $txnID;
       $this->dbConn->query($sql);
        return $this->dbConn->insert_id;
    }

    function recordSms($msisdn, $txnID, $moID, $type, $text) {
        $sql = "INSERT INTO trivia_sms_logs_" . date("Ymd") . " (`MO_ID`,`SMS_TYPE`,`TEXT`,`MSISDN`,`STATUS_CODE`,`TXN_ID`) VALUES(" . $moID . ",'" . $type . "','" . $text . "','" . $msisdn . "','SENT',".$txnID.")";

      $this->dbConn->query($sql);
    }

    function updateSMSStatus($smsID,$smsInfo=array()):int {
        $updateString = $this->arrayToString($smsInfo);
        $tbl = "trivia_sms_logs_".date("Ymd");
        $sql = "UPDATE {$tbl} SET {$updateString} WHERE ID =  ".$smsID;
        $this->dbConn->query($sql);
        return $this->dbConn->affected_rows;
        
    }

    function connect_db() {
        if ($this->userType == "consumer") {
            $conn = new mysqli(DB_HOST, DB_CONSUMER_USER, PASSWORD, TRIVIA_DB);
        } else {
            $conn = new mysqli(DB_HOST, PUBLISHER_USER, PASSWORD, TRIVIA_DB);
        }
        if ($conn) {
            return $conn;
        } else {
            return false;
        }
}

    function closeDB() {
            $this->dbConn->close();
    }

    function addSubscriber($msisdn):int {
        $sql = "INSERT INTO tbl_qn_subscribers(`MSISDN`) VALUES('" . $msisdn . "')";
        $this->dbConn->query($sql);
        return $this->dbConn->insert_id;
    }
function arrayToString($arrayData):string{
array_walk($arrayData, function(&$value, $key){
        $value = "{$key} = '{$value}'";
    });
$string = implode(',',array_values($arrayData)); 
    
return $string;
        
    }
}
