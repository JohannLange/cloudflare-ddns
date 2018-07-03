#!/usr/bin/php5
<?php
# Script for updating Dynamic IP on CloudFlare
# M Hughes <hello@msh100.uk>
function tolog ($text) {
    echo $text . "\n";
}
$cf_url = "https://api.cloudflare.com/client/v4/zones/";
$cf_email = getenv('CF_EMAIL');
$cf_api = getenv('CF_API');
#$cf_zone_id = getenv('CF_ZONE_ID'); #no longer needed
$cf_host = getenv('CF_HOST');
if (!isset($cf_email, $cf_api, $cf_host)) {
    tolog("CF_EMAIL, CF_API, and CF_HOST must be set");
	tolog ("Dying...");
    die();
}
else {
	#########################
	# Determine DNS zone ID #
	#########################
	tolog("Contacting the Cloudflare API to determine DNS zone id");
	
	# Start curl
	$ch = curl_init();
	
	# Define header
	$headers = array(
                 "X-Auth-Email: $cf_email",
                 "X-Auth-Key: $cf_api",
                 "Content-Type: application/json",
                  );
    
	# Define curl url	
	$churl = "$cf_url";
	
	#Set curl options
	curl_setopt($ch, CURLOPT_URL, $churl);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	#Execute curl and save response
	$response = curl_exec($ch);
	
	#Close curl
	curl_close($ch);

	# Decode response and get the data
	$response = json_decode($response);
	# Get the result array from the response
	$result = $response->result;
	# Get the size of the result array
	$i_result = count($result);
	
	# Determine the zone id
	# Check wether there are mor than one entries
	if(1 == $i_result){
		$cf_zone_id = $result[0]->id;
	}
	else{
		for($i = 0; $i < $i_result; $i++){
			$temp_entry = $result[0]->name;
			if($cf_host == $temp_entry){
				$cf_zone_id = $result[$i]->id;
			}
		}
	}
	
	if (!isset($cf_zone_id)) {
        tolog ("Could not determine zone id");
		tolog ("Dying...");
        die();
    }
	
	#############################
    # Determine the DNS host ID #
	#############################
    tolog("Contacting the Cloudflare API to determine DNS id");
	
	# Start curl
	$ch = curl_init();
	
	# Define header
	$headers = array(
                 "X-Auth-Email: $cf_email",
                 "X-Auth-Key: $cf_api",
                 "Content-Type: application/json",
                  );
    
	# Define curl url	
	$churl = "$cf_url$cf_zone_id/dns_records?type=A&name=$cf_host&math=all";
	
	#Set curl options
	curl_setopt($ch, CURLOPT_URL, $churl);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	#Execute curl and save response
	$response = curl_exec($ch);
	
	#Close curl
	curl_close($ch);

	# Decode response and get the data
	$response = json_decode($response);
	# Host ID
    $cf_id = $response->result[0]->id;
	# Type
	$cf_type = $response->result[0]->type;
	# Old IP
	$cf_ip_old = $response->result[0]->content;
	
    if (!isset($cf_id)) {
        tolog ("Could not determine id");
		tolog ("Dying...");
        die();
    }
	
	##############
	# Begin loop #
	##############
	tolog ("Beginning loop to compare external IP and DNS entry");
	while (true) {
		# Loop DNS resolve and IP compare
		$ip_api = trim(file_get_contents('http://icanhazip.com/'));
		if (!isset($ip_api)) {
			tolog ("Invalid IP received from API");
		}
		else {			
			
			# Start curl
			$ch = curl_init();
			
			# Define header
			$headers = array(
						 "X-Auth-Email: $cf_email",
						 "X-Auth-Key: $cf_api",
						 "Content-Type: application/json",
						  );
			
			#Define data array
			$data = array(
              "type" => "$cf_type",
              "name" => "$cf_host",
              "content" => "$ip_api",
              "ttl" => 120,
               );
			$json = json_encode($data);
			
			# Define curl url
			$churl = "$cf_url$cf_zone_id/dns_records/$cf_id";
			
			#Set curl options
			curl_setopt($ch, CURLOPT_URL, $churl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			
			#Execute curl and save response
			$response = curl_exec($ch);
			
			#Close curl
			curl_close($ch);
			
			# Decode response
			$response = json_decode($response);
			# Check wether the update was successful
			$cf_success = $response->success;
			# Get the new IP (no idea why not result[0]...)
			$cf_ip = $response->result->content;

			
			if(true == $cf_success){
				if($cf_ip_old != $cf_ip){
					tolog("IP changed to $cf_ip");
					$cf_ip_old = $cf_ip;
				}
			}
			else{
				tolog("Error! The update was NOT successful!");
			}
		}
		sleep (30);
	}
}