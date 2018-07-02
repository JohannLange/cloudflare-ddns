#!/usr/bin/php5
<?php
# Script for updating Dynamic IP on CloudFlare
# M Hughes <hello@msh100.uk>
function tolog ($text) {
    echo $text . "\n";
}
$api_url = "https://api.cloudflare.com/client/v4/zones/";
$cf_email = getenv('CF_EMAIL');
$cf_api = getenv('CF_API');
$cf_zone_id = getenv('CF_ZONE_ID');
$dns_entry = getenv('CF_HOST');
if (!isset($cf_email, $cf_api, $dns_entry)) {
    tolog('CF_EMAIL, CF_API, and CF_HOST must be set');
}
else {
    # Determine the DNS host ID
    tolog('Contacting the Cloudflare API to determine DNS id');
	
	$ch = curl_init();
	$headers = array(
                 "X-Auth-Email: $cf_email",
                 "X-Auth-Key: $cf_api",
                 "Content-Type: application/json",
                  );
                  
	$churl = "$api_url$cf_zone_id/dns_records?type=A&name=$dns_entry&math=all";
	
	curl_setopt($ch, CURLOPT_URL, $churl);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	curl_close($ch);

	$response = json_decode($response);
	
    $id = $response->result[0]->id;
	
    if (!isset($id)) {
        tolog ('Bad API credentials');
        die();
    }
	tolog ('Beginning loop to compare external IP and DNS entry');
	while (true) {
		# Loop DNS resolve and IP compare
		$ip_api = trim(file_get_contents('http://icanhazip.com/'));
		if (!isset($ip_api)) {
			tolog ('Invalid IP received from API');
		}
		else {			
			
			$ch = curl_init();
			$headers = array(
						 "X-Auth-Email: $cf_email",
						 "X-Auth-Key: $cf_api",
						 "Content-Type: application/json",
						  );
			$data = array(
              "type" => "A",
              "name" => "$dns_entry",
              "content" => "$ip_api",
              "ttl" => 120,
               );
			$json = json_encode($data);
			$churl = "$api_url$cf_zone_id/dns_records/$id";
			curl_setopt($ch, CURLOPT_URL, $churl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$response = curl_exec($ch);
			curl_close($ch);
			
			$response = json_decode($response);
			var_dump($response);
		}
		sleep (30);
	}
}
