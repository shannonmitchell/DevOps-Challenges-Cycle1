<?php

// Set the default region just in case
$datacenter = "ORD";
define('RAXSDK_VOLUME_REGION',$datacenter);

// Do the needful
require 'vendor/autoload.php';
use OpenCloud\Rackspace;
use OpenCloud\Database\Service;
use OpenCloud\Database\Resource\Instance;

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

// Set up the db service object to use
$dbobj = $client->databaseService('cloudDatabases', $datacenter, 'publicURL');

// Ask the customer some information about the instance
printf("\n\nEnter a cloud database instance name: ");
$instance_name = rtrim(fgets(STDIN));

// Enter loop to get instance info or existing instance
$myinstance = 0;
$useexisting = 0;
$instance_finished = 0;
while($instance_finished = 0) {

    // Check for existing instance
    $instances = $dbobj->instanceList();
    while ($curinstance = $instances->next()) {
	printf("SLM: curinstance %s\n\n", $curinstance->getName());
        if($curinstance->getName() == $instance_name) {
            $instanceexists = 1;
	    $suggested = sprintf("%s1", $instance_name);
            printf("Instance by that name exists. Re-Enter name(q(exit), u(use existing): %s\n", $suggested);
            $instance_name = rtrim(fgets(STDIN));
	    if($instance_name == '') {
		$instance_finished = 1;
                $instance_name = $suggested;
		break;
	    }
	    if($instance_name == 'u') {
		$instance_finished = 1;
                $myinstance = $curinstance;
		$useexisting = 1;
		break;
	    }
	    if($instance_name == 'q') {
                exit(255);
	    }
        }
    } // End instances collection parsing loop
} // End create instance loop

// Get flavor size info and create a new instance if needed
$myflavor = 0;
if(!$useexisting) {
    $flavors = $dbobj->flavorList();
    printf("\n\nSelect a Flavor:\n");
    $x = 1;
    while($curflavor = $flavors->next()) {
        printf("\t%d -> Flavor: %s\n", $x, $curflavor->getName());
	$selflavor[$x] = $curflavor;
	$x++;
    } 
    $validanswer = 0;
    while($validanswer == 0) {

        printf("Enter flavor selection: ");
        $flavorindex = intval(rtrim(fgets(STDIN)));
        if($flavorindex < 1 || $flavorindex >= $x) {
            printf("Value must be between 0 and %d\n", $x);
	} else {
            $validanswer = 1;
	    $myflavor = $selflavor[$flavorindex];
	}
    }

    // Get the disk size (between 1 and 150 in GB)
    $validanswer = 0;
    $instance_size = 0;
    while($validanswer == 0) {
        printf("Enter disk size in GB between 1 and 150: ");
        $instance_size = intval(rtrim(fgets(STDIN)));
        if($instance_size < 1 || $instance_size > 150) {
            printf("Value must be between 1 and 150\n");
        } else {
            $validanswer = 1;
        }
    }

    printf("Creating instance with name of '%s' and flavor of '%s' and size of '%dGB'\n", $instance_name, $myflavor->getName(), $instance_size);
    $myinstance = $dbobj->Instance();
    $myinstance->name = $instance_name;
    $myinstance->flavor = $myflavor;
    $myinstance->volume->size = $instance_size;
    $myinstance->Create();

} // End if(!$useexisting) block


printf("Using instance %s\n", $myinstance->name);



