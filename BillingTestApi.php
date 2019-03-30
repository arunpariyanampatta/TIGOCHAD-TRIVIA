<?php
$service ='HALOSOKA';
$sub='SOKA_LIVE_LL';
$date=date("Ymdhis");
$str = "SUB=".$sub."&CATE=&ITEM=&SUB_CP=&CONT=&PRICE=0&REQ=111123456&MOBILE=255629577233&TYPE=MOBILE&SOURCE=CLIENT&IMEI=";
$key = "0dded43371cd3150953b256eb3ae4bc8";



$xml = '<?xml version="1.0" encoding="ISO-8859-1"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"><SOAP-ENV:Body>
    <encodingRequest xmlns="http://encoding.greentelecom.org/">
    <SERVICE xmlns="">SOKA_LIVE_LL</SERVICE>
    <MOBILE xmlns="">628121372</MOBILE>
    <PRICE xmlns="">70</PRICE>
    <KEY xmlns="">'.$key.'</KEY>
    <REQ xmlns="">111222</REQ>
    <TYPE xmlns="">MOBILE</TYPE>
    </encodingRequest>
    </SOAP-ENV:Body></SOAP-ENV:Envelope>';


        $headers = array(
           "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",

             "Content-length : ".strlen($xml)

        );
        $ch = curl_init();
       curl_setopt($ch, CURLOPT_URL, "http://192.168.168.2:8216/HalotelEncoding-Decoding/EncodingDecoding?wsdl");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 160);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $encryptresponse  = curl_exec($ch);

$encryptData = get_string_between($encryptresponse,"<return>","</return");


$newdata = "value=".$encryptData."&key=".$key;
$encryptkey = file_get_contents('http://localhost/halotel-billing/public_vt_cp.key');
$encrypted = encryptdata($newdata,$encryptkey);
$sig = generatesignature($encrypted);
$encryptedData = urlencode($encrypted);
$sig = urlencode($sig);
//$response = file_get_contents("http://10.225.198.54:9181/MPS/charge.html?PRO=TtVang&SER=HALOSOKA&SUB=SOKA_LIVE_LL&CMD=CHARGE&DATA=$encryptedData&SIG=$sig");
$sendRequest = "http://10.225.198.54:9181/MPS/charge.html?PRO=TtVang&SER=HALOSOKA&SUB=SOKA_LIVE_LL&CMD=CHARGE&DATA=".$encryptedData."&SIG=".$sig;
echo $sendRequest;
$response = file_get_contents($sendRequest);
$data = get_string_between($response, "DATA=", "&SIG");

$decrypted =  decryptdata($data);


$decrypted = $decrypted.";";
$decryptedValue = get_string_between($decrypted, "VALUE=", "&KEY=");
$decryptedKey   = get_string_between($decrypted, "&KEY=", ";");

$xml = '<?xml version="1.0" encoding="ISO-8859-1"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"><SOAP-ENV:Body>
    <decodingRequest xmlns="http://encoding.greentelecom.org/">
    <KEY xmlns="">'.$decryptedKey.'</KEY>
    <DATA xmlns="">'.$decryptedValue.'</DATA>
    </decodingRequest>
    </SOAP-ENV:Body></SOAP-ENV:Envelope>';

        $headers = array(
           "Content-type: text/xml;charset=\"utf-8\"",
            "Accept: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
             "SOAPAction:decodingRequest",
             "Content-length : ".strlen($xml)

        );
        $ch = curl_init();
       curl_setopt($ch, CURLOPT_URL, "http://192.168.168.2:8216/HalotelEncoding-Decoding/EncodingDecoding?wsdl");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 160);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $decryptResponse = curl_exec($ch);
echo $decryptResponse;

//$responseData = decrypt($decryptedValue,$decryptedKey);
//echo $responseData;
return TRUE;




   function encrypt($str, $key){
       $key = pack('H*',$key);
     $block = mcrypt_get_block_size('rijndael_256', 'ecb');


   $str .= pkcs7pad($str, $block);


     return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $str, MCRYPT_MODE_ECB));
}

function pkcs7pad($plaintext, $blocksize)
{
    $padsize = $blocksize - (strlen($plaintext) % $blocksize);
    return $plaintext . str_repeat(chr($padsize), $padsize);
}

function decrypt($encryptedresponseString,$encryptedresponseKey){





    $key_size =  strlen($encryptedresponseKey);
    $encryptedresponseKey = pack("H*",$encryptedresponseKey);
   $encryptedresponseString = urldecode($encryptedresponseString);
   $ciphertext_dec = $encryptedresponseString;
   $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
   $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
   $iv_dec = substr($ciphertext_dec, 0, $iv_size);
   $ciphertext_dec = substr($ciphertext_dec, $iv_size);
   $decodedString  = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $encryptedresponseKey,$ciphertext_dec, MCRYPT_MODE_CBC,$iv);
   return $decodedString;
}

function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}
function encryptdata($data,$key)
{
        if (openssl_public_encrypt($data, $encrypted, $key))
            $data = base64_encode($encrypted);
        else
            throw new Exception('Unable to encrypt data. Perhaps it is bigger than the key size?');

        return $data;
    }

    function decryptdata($data){
        $key = file_get_contents('http://localhost/halotel-billing/private_cp.key');
        try{
            openssl_private_decrypt(base64_decode($data), $decrypted, $key);

        return $decrypted;
        }
        catch(\AES\Exception $ex){
            echo $ex->getMessage();

        return false ;
        }
    }


   function generatesignature($toSign){
$key = file_get_contents('http://localhost/halotel-billing/private_cp.key');
$pkeyid = openssl_get_privatekey($key);
openssl_sign($toSign, $signature, $pkeyid);
openssl_free_key($pkeyid);
$base64 = base64_encode($signature);
return $base64;
}

   function getSignedData($signed){
$key = file_get_contents('http://localhost/halotel-billing/private_cp.key');
$pkeyid = openssl_get_privatekey($key);
openssl_sign($signed, $signature, $pkeyid);
openssl_free_key($pkeyid);
$base64 = base64_decode($signature);
return $base64;
}
