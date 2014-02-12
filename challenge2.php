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
    exit(255);
} else {
    $keytext = file_get_contents($mykeyfile);
}


// Get the servername prefix and number of servers
printf("Please enter a servername: ");
$servername = rtrim(fgets(STDIN));

printf("Please enter in a value from 1 to 3 for the number of servers you would like to spin up: ");
$servercount = intval(rtrim(fgets(STDIN)));
if($servercount < 1 || $servercount > 3) {
    printf("Server count must be a value from 1 to 3.\n");
    exit(255);
}


//return 1;



// Instantiate the Rackspace Object with a us endpoint
$client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
    'username' => $auth_config['username'],
    'apiKey' => $auth_config['api_key']
));

// Get variables to use for server creation
$datacenter = "ORD";
$imagename = "Fedora 19 (Schrodinger's Cat)";
$flavorname = "512MB Standard Instance";
//$servername = "challenge2";
$keyname = "challenge2";

// Get the compute object
$compute = $client->computeService('cloudServersOpenStack', $datacenter);

// Update or create a new keypair
$keypairs = $compute->listKeypairs();
$keypairexists = 0;
while ($curkeypair = $keypairs->next()) {
    if($curkeypair->getName() == $keyname) {
	$keypairexists = 1;
	printf("Removing conflicting keypair with name of %s\n", $keyname);
	$curkeypair->delete();
    }
}

// Create a new Keypair
printf("Creating new keypair with name of %s and value of %s\n", $keyname, $keytext);
$keypair = $compute->keypair();
$keypair->setName($keyname);
$keypair->create(array('publicKey' => $keytext));


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

// Start server creation loop
for($x = 1; $x <= $servercount; $x++) {

    // Set the name
    $curname = sprintf("%s%d", $servername, $x);

    // Create the serever
    $server[$x] = $compute->server();

    try {
        $response = $server[$x]->create(array(
            'name'     => $curname,
            'image'    => $foundimage,
            'flavor'   => $foundflavor,
            'networks' => array(
                $compute->network(Network::RAX_PUBLIC),
                $compute->network(Network::RAX_PRIVATE)
            ),
	    'keypair' => array(
	        'name'  => $keyname,
            ),
        ));
    } catch (\Guzzle\Http\Exception\BadResponseException $e) {
        $responseBody = (string) $e->getResponse()->getBody();
        $statusCode = $e->getResponse()->getStatusCode();
        $headers = $e->getResponse()->getHeaderLines();

        printf('Status: %s\nBody: %s\nHeaders: %s\n', $statusCode, $responseBody, implode(', ', $headers));
    }

} // end server creation loop




// Start server wait loop
for($x = 1; $x <= $servercount; $x++) {

    // Wait for the server to finish
    $callback = function($server) {
        if(!empty($server->error)) {
            var_dump($server->error);
    	    exit(255);
        } else {
            echo sprintf("Waiting on %s/%-12s %4s%%\n", 
                         $server->name(), 
	    	         $server->status(), 
		         isset($server->progress) ? $server->progress : 0 
	         );
        }
    };
    $newserver[$x] = $server[$x]->waitFor(ServerState::ACTIVE, 600, $callback);
} // end server wait loop


// Start server print loop
for($x = 1; $x <= $servercount; $x++) {
    // Print out the results
    printf("Server name: %s\n", $server[$x]->name);
    printf("Server ip address: %s\n", $server[$x]->accessIPv4);
    printf("Server admin password: %s\n", $server[$x]->adminPass);
} // end server print loopo

?>
