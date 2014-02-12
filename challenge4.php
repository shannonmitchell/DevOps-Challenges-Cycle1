<?php

// Set the default region just in case
$datacenter = "ORD";
define('RAXSDK_VOLUME_REGION',$datacenter);

// Do the needful
require 'vendor/autoload.php';
use OpenCloud\Rackspace;
use OpenCloud\Compute\Constants\Network;
use OpenCloud\Compute\Constants\ServerState;

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


// Create the cloud files object
printf("Creating cloud files object\n");
$cfobj = $client->objectStoreService('cloudfiles', $datacenter);

// Create the container 
$containername = "challenge4";
$containers = $cfobj->listContainers();
$containerexists = 0;
while($curcontainer = $containers->next()) {
    if($curcontainer->name == $containername) {
	// Comment out the next two lines if you want to use the same directory
	// if it exists.  I found it helpful for testing.
        printf("Container with name of %s already exists. Exiting.\n", $containername);
	exit(255); 
        printf("Container with name of %s already exists. Skipping creation.", $containername);
	$containerexists = 1;
	$container = $curcontainer;
    }
}

if($containerexists == 0) {
    printf("Creating container with the name of %s\n", $containername);
    $container = $cfobj->createContainer($containername);
}


// Upload a directory to the container
printf("Enter in a directory to upload to the new container %s: ", $containername);
$uploaddir = rtrim(fgets(STDIN));
if (file_exists($uploaddir)) {
    printf("Uploading directory %s to container %s\n", $uploaddir, $containername);
    $container->uploadDirectory($uploaddir);
} else {
    printf("Upload Directory %s does not exist. Exiting.\n", $uploaddir);
    exit(255);
}


// Enable CDN for the container.
printf("Enabling CDN for container %s\n", $containername);
$container->enableCdn();

$cdn = $container->getCdn();
printf("The URI's for the container are: \n");
printf("\t http: %s\n", $cdn->getCdnUri());
printf("\t https: %s\n", $cdn->getCdnSslUri());
printf("\t streaming: %s\n", $cdn->getCdnStreamingUri());
printf("\t ios streaming: %s\n", $cdn->getIosStreamingUri());
