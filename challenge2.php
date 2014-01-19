<?php

// Do the needful
require 'vendor/autoload.php';
use OpenCloud\Rackspace;
use OpenCloud\Compute\Constants\Network;
use OpenCloud\Compute\Constants\ServerState;
use OpenCloud\Compute\Resource\KeyPair;

// Set the timezone
date_default_timezone_set('America/Chicago');


// Get the Credentials and pull them into a var
$auth_file = getenv('HOME') . "/.rackspace_cloud_credentials";
$auth_config = parse_ini_file($auth_file);

// Get the ssh keyfile
$key_file = getenv('HOME') . "/.ssh/id_rsa.pub";

// Prompt for the key file
if (file_exists($key_file)) {
    printf("Please enter your public ssh key file[%s](enter if unchanged): ", $key_file);
    $mykeyfile = rtrim(fgets(STDIN));
    if($mykeyfile == '') {
	    $mykeyfile = $key_file;
    }
    
} else {
    printf("Please enter your public ssh key file location: ");
    $mykeyfile = rtrim(fgets(STDIN));
}

// Make sure the file exists and get its contents
if(!file_exists($mykeyfile)) {
    printf("Public key file (%s) doesn't exist\n", $mykeyfile);
    exit(0);
} else {
    $keytext = file_get_contents($mykeyfile);
}



// Instantiate the Rackspace Object with a us endpoint
$client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
    'username' => $auth_config['username'],
    'apiKey' => $auth_config['api_key']
));

// Get variables to use for server creation
$datacenter = "ORD";
$imagename = "Fedora 19 (Schrodinger's Cat)";
$flavorname = "512MB Standard Instance";
$servername = "challenge2";
$keyname = "challenge2";

// Get the compute object
$compute = $client->computeService('cloudServersOpenStack', $datacenter);

// Create a Keypair
//$keypair = $compute->keypair();
//$keypair->setName($keyname);
//$keypair->create(array('data' => $keytext));

// Get the image
$images = $compute->imageList();
while ($image = $images->next()) {
    if($image->name == $imagename) {
        printf("Found Image (%s)\n", $image->name);
	$foundimage = $image;
    }
}

// Get the flavor
$flavors = $compute->flavorList();
while ($flavor = $flavors->next()) {
    if($flavor->name == $flavorname) {
        printf("Found Flavor (%s)\n", $flavor->name);
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
        ),
	'keypair' => array(
	    'name'  => "challenge2",
        ),
    ));
} catch (\Guzzle\Http\Exception\BadResponseException $e) {
    $responseBody = (string) $e->getResponse()->getBody();
    $statusCode = $e->getResponse()->getStatusCode();
    $headers = $e->getResponse()->getHeaderLines();

    printf('Status: %s\nBody: %s\nHeaders: %s\n', $statusCode, $responseBody, implode(', ', $headers));
}


// Wait for the server to finish
$callback = function($server) {
    if(!empty($server->error)) {
        var_dump($server->error);
	exit;
    } else {
        echo sprintf("Waiting on %s/%-12s %4s%%\n", 
                     $server->name(), 
		     $server->status(), 
		     isset($server->progress) ? $server->progress : 0 
	     );
    }
};
$newserver = $server->waitFor(ServerState::ACTIVE, 600, $callback);


// Print out the results
printf("Server name: %s\n", $server->name);
printf("Server ip address: %s\n", $server->accessIPv4);
printf("Server admin password: %s\n", $server->adminPass);

?>
