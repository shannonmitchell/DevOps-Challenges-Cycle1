<?php

// Set the default region just in case
define('RAXSDK_VOLUME_REGION','ORD');

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


printf("Please enter in a value from 100-1024 GB for the size of block storage you would like to allocate: ");
$bssize = intval(rtrim(fgets(STDIN)));
if($bssize < 100 || $bssize > 1024) {
    printf("Block storage size must be a value from 100 to 1024.\n");
    exit(255);
}




// Instantiate the Rackspace Object with a us endpoint
$client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
    'username' => $auth_config['username'],
    'apiKey' => $auth_config['api_key']
));

// Get variables to use for server creation
$datacenter = "ORD";
$imagename = "Fedora 19 (Schrodinger's Cat)";
$flavorname = "1 GB Performance";
$servername = "challenge3";
$keyname = "challenge3";

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


// Check for existing server by that name(easier for testing cbs issues as it doesn't have to create the server everytime)

$servers = $compute->serverList();
$serverexists = 0;
while($myserver = $servers->next()) {
    if($myserver->name == $servername) {
	$serverexists = 1;
	printf("Server by the name of %s exists. Skipping creation\n", $servername);
        $server = $myserver;
    }
}

if($serverexists == 0) {

    // Create the server
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
                'name'  => $keyname,
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
            exit(255);
        } else {
            echo sprintf("Waiting on %s/%-12s %4s%%\n", 
                         $server->name(), 
     	                 $server->status(), 
	    	         isset($server->progress) ? $server->progress : 0 
            );
        }
    };
    $newserver = $server->waitFor(ServerState::ACTIVE, 600, $callback);

} // end server creation block


// Create cloud block storage volume
$cbsobj = $client->VolumeService('cloudBlockStorage', 'ORD');
$volumes = $cbsobj->VolumeList();
$volexists = 0;
while($vol = $volumes->Next()) {
    if($vol->Name() == $servername) {
	printf("Found existing volume with name of %s. Skipping creation\n", $servername);
	$newvol = $vol;
        $volexists = 1;
    }
}
if($volexists ==  0) {
    printf("Creating new volume\n");
    $newvol = $cbsobj->Volume();
    $response = $newvol->Create(array(
        'size' => $bssize,
	'volume_type' => $cbsobj->VolumeType("9fcafb45-56f3-4936-94dd-8eff44cd0938"),
	'display_name' => $servername,
	'display_description' => "Challenge 3 cloud block storage"
    ));
}

// Attach the volume
printf("Attaching new volume to server %s\n", $servername);
$server->AttachVolume($newvol, 'auto');


// Print out the results
printf("Server name: %s\n", $server->name);
printf("Server ip address: %s\n", $server->accessIPv4);
printf("Server admin password: %s\n", $server->adminPass);

?>
