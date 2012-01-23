# What is provisional?

Provisional is a very simple Silex based implementation of the API that service
providers have to implement in order to join the cloudControl Add-on market.
<https://www.cloudcontrol.com/add-ons>

## How to use it?

It's very easy to use provisional to build a proxy between your service's
existing API and the cloudControl Add-on market API.

### 1. Add provisional as a submodule

This of course requires that you're using Git.
    
    $ git submodule add git://github.com/cloudControl/provisional.git api/
    
### 2. Create .ccconfig.yaml

We need to point the document root to the api subdirectory. For this put the
following code into a file called .cconfig.yaml in your repository's root.

    BaseConfig:
      WebContent: /api
      AdminEmail: [YOUR_ADMIN_EMAIL]

### 3. Create controller.php

The file controller.php is where you implement the provisioning, deprovisioning
and upgrade, downgrade code for your service.

    <?php
    
        require_once(__DIR__.'/api/base_controller.php');
        
        class Controller extends BaseController {
            
            public function create($data) {
                // your provisioning code here
                // see the examples below for what to expect and return
            }
            
            public function update($id, $data) {
                // your code for plan changes here
            }
            
            public function delete($id) {
                // your code for deprovisioning calls here
            }
            
            public static function get_hc_secret() {
                // return the secret used for /health-check?s=S3CR37
            }
            
            public function health_check() {
                // implement your health_check code here
            }
        
        }
    
    ?>

#### create($data)

Create is called on each provisioning request and get's passed an array parsed
from the JSON sent in the request body. Looking something like this:

    <?php
        
        $data = array( 
            "cloudcontrol_id" => "dep1a2b3c4d@cloudcontrolled.com",
            "plan" => "basic",
            "callback_url" => "https://api.cloudcontrol.com/vendor/apps/dep1a2b3c4d%40cloudcontrolled.com",
            "options": {}
        );
    
    ?>

Use this data to provision your service and then return an array like this:

    <?php
        
        $response = array(
            "id" => "YOUR_INTERNAL_ID",
            "config" => array(
                "MYADDON_VAR" => "VALUE" // specify these in the manifest
            ),
            "message" => "A friendly success message."
        );
        
        // both config and message are optional
    
    ?>

The response array is sent back as a JSON string in the HTTP response body
automatically.

#### update($id, $data)

Update get's called on each plan change request and get's passed the $id you
returned in the provisioning call and once again an array parsed from the JSON
sent in the request body.

    <?php
        
        $data = array( 
            "cloudcontrol_id" => "dep1a2b3c4d@cloudcontrolled.com",
            "plan" => "basic"
        );
    
    ?>

The response is similar to the provisioning one but does not include an id.

    <?php
        
        $response = array(
            "config" => array(
                "MYADDON_VAR" => "VALUE" // specify these in the manifest
            ),
            "message" => "A friendly success message."
        );
        
        // both config and message are optional
    
    ?>

#### delete($id)

Delete is called on each deprovisioning call and get's passed the $id you
returned upon provisioning. Delete the ressource and return true on success or
false if an error occured.

#### get_hc_secret()

Provisional supports a /health-check URL which expects a ?s=S3CR37. Use this
method to return the expected secret.

#### health_check()

The health_check method is called upon every authenticated (see get_hc_secret)
call to /health-check. If this method returns true provisional responds with a
200 Ok. If false, it returns 503 Temporarily Unavailable.

### 4. Become an Add-on provider

For details on how to become an Add-on provider and how to obtain and upload
the manifest needed for provisional to work as expected please see <https://www.cloudcontrol.com/add-ons>.

