<?php

// Do the needful
require 'vendor/autoload.php';
use OpenCloud\Rackspace;
use OpenCloud\Compute\Constants\Network;

// Set the timezone
date_default_timezone_set('America/Chicago');


// Get the Credentials and pull them into a var
$auth_file = getenv('HOME') . "/.rackspace_cloud_credentials";
$auth_config = parse_ini_file($auth_file);

// Instantiate the Rackspace Object with a us endpoint
$client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
    'username' => $auth_config['username'],
    'apiKey' => $auth_config['api_key']
));

// Get variables to use for server creation
$datacenter = "ORD";
$imagename = "Fedora 19 (Schrodinger's Cat)";
$flavorname = "512MB Standard Instance";
$servername = "challenge1";

// Get the compute object
$compute = $client->computeService('cloudServersOpenStack', $datacenter);

// Get the image
$images = $compute->imageList();
while ($image = $images->next()) {
    if($image->name == $imagename) {
        printf("%s\n", $image->name);
	$foundimage = $image;
    }
}

// Get the flavor
$flavors = $compute->flavorList();
while ($flavor = $flavors->next()) {
    if($flavor->name == $flavorname) {
        printf("%s\n", $flavor->name);
	$foundflavor = $flavor;
    }
}

// Create the serever
$server = $compute->server();

try {
    $response = $server->create(array(
        'name'     => $servername,
        'image'    => $foundimage,
        'flavor'   => $foundflavor,
        'networks' => array(
            $compute->network(Network::RAX_PUBLIC),
            $compute->network(Network::RAX_PRIVATE)
        )
    ));
} catch (\Guzzle\Http\Exception\BadResponseException $e) {
    $responseBody = (string) $e->getResponse()->getBody();
    $statusCode = $e->getResponse()->getStatusCode();
    $headers = $e->getResponse()->getHeaderLines();

    printf('Status: %s\nBody: %s\nHeaders: %s\n', $statusCode, $responseBody, implode(', ', $headers));
}
?>
