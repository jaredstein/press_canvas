<?php
//get the q parameter from URL
$url=$_GET["url"];
$token=$_GET["token"];


//curl function to get resource
function curlAPI($url, $method, $params = NULL) {
	//print the https request for sanity check
	//print "\n\n".$url."?".$params;
	
	//initialize handle
	$chandle = curl_init();
	
	//set URL
	curl_setopt($chandle, CURLOPT_URL, $url);
	
	//return results as string
	curl_setopt($chandle, CURLOPT_RETURNTRANSFER, 1);
	
	//set the method
	curl_setopt($chandle, CURLOPT_CUSTOMREQUEST, $method);
	
	//header ok!?!
	curl_setopt($chandle, CURLOPT_HEADER, FALSE);
	
	//pass in parameters if needed
	if($params!==NULL){
		curl_setopt($chandle, CURLOPT_POSTFIELDS, $params);
		}
	
	//the call
	$result = curl_exec($chandle);
	return $result;
	
	//close it out
	curl_close($chandle);
	unset($params);
	}

//check the JSON syntax
function json_check($json_string){
	json_decode($json_string);
	switch(json_last_error()) {
	    case JSON_ERROR_DEPTH:
	        echo ' - Maximum stack depth exceeded';
	    break;
	    case JSON_ERROR_CTRL_CHAR:
	        echo ' - Unexpected control character found';
	    break;
	    case JSON_ERROR_SYNTAX:
	        echo ' - Syntax error, malformed JSON';
	    break;
	    case JSON_ERROR_NONE:
	        print_r($creation);
	    break;
	}
}

//lookup all hints from array if length of q>0
if (strlen($token) > 0)
  {
  $result="";
  $assignmentList = json_decode(curlAPI("https://".$url."/api/v1/courses","GET","access_token=".$token), true);
	
  var_dump($assignmentList);
  }

?>