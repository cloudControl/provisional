<?php

/*
*  Copyright 2012 cloudControl GmbH
*
*  Licensed under the Apache License, Version 2.0 (the "License");
*  you may not use this file except in compliance with the License.
*
*  You may obtain a copy of the License at
*
*  http://www.apache.org/licenses/LICENSE-2.0
*
*  Unless required by applicable law or agreed to in writing, software
*  distributed under the License is distributed on an "AS IS" BASIS,
*  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
*  See the License for the specific language governing permissions and
*  limitations under the License.
*/

require_once(__DIR__.'/silex.phar');
require_once(__DIR__.'/../controller.php');

$app = new Silex\Application();
$app->controller = new Controller();

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

function read_credentials() {
    $creds = array();
    // read the credentials from the manifest
    $parts = explode('/', $_ENV["DEP_NAME"]);
    $manifest_path = sprintf('%s/cloudcontrol-addon-manifest.%s.json', __DIR__, $parts[0]);
    $file = file_get_contents($manifest_path, false);
    if (is_string($file)) {
        $json = json_decode($file, true);
        $creds[$json['id']] = $json['api']['password'];
    };
    return $creds;
}

$app->before(function (Request $request) use ($app) {
    // for the health check just require a shared secret
    if ($request->getPathInfo() == '/health-check' and
        $request->getMethod() == 'GET'
    ) {
        if ($request->query->get('s', '') == Controller::get_hc_secret()) {
            return;
        }
    }
    // check for credentials
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        $user = $_SERVER['PHP_AUTH_USER'];
        $pswd = $_SERVER['PHP_AUTH_PW'];
        $creds = read_credentials();
        // if credentials match return true
        if (array_key_exists($user, $creds) && $pswd == $creds[$user]) {
            return;
        }
    }
    // in any other case return not authorized
    $app->abort(401, 'Not Authorized');
});

// special case: health check
$app->get('/health-check', function () use ($app) {
        $success = $app->controller->health_check();
        if ($success) {
            return new Response('Ok', 200);
        }

        $app->abort(503, 'Not Available');
    }
);

$app->post('/cloudcontrol/resources',
    function (Request $request) use ($app) {
        $json = json_decode($request->getContent(), true);
        try {
            $ressource = $app->controller->create($json);
        } catch (Exception $e) {
            $app->abort($e->getCode(), $e->getMessage());
        }
        if ($ressource == false) {
            $app->abort(500, 'Internal Server Error');
        }
        $response = array(
            'id' => $ressource['id']
        );
        if (array_key_exists('config', $ressource)) {
            $response['config'] = $ressource['config'];
        }
        if (array_key_exists('message', $ressource)) {
            $response['message'] = $ressource['message'];
        }
        return new Response(
            json_encode($response),
            201,
            array('Content-Type' => 'application/json')
        );
    }
);

$app->put('/cloudcontrol/resources/{id}',
    function (Request $request, $id) use ($app) {
        $json = json_decode($request->getContent(), true);
        try {
            $ressource = $app->controller->update($app->escape($id), $json);
        } catch (Exception $e) {
            $app->abort($e->getCode(), $e->getMessage());
        }
        if ($ressource == false) {
            $app->abort(404, 'Not Found');
        }
        $response = array();
        if (array_key_exists('config', $ressource)) {
            $response['config'] = $ressource['config'];
        }
        if (array_key_exists('message', $ressource)) {
            $response['message'] = $ressource['message'];
        }
        if (count($response_array) == 0) $response = '';
        return new Response(
            json_encode($response),
            200,
            array('Content-Type' => 'application/json')
        );
    }
);

$app->delete('/cloudcontrol/resources/{id}',
    function ($id) use ($app) {
        $id = $app->escape($id);
        try {
            $ressource = $app->controller->delete($id);
        } catch (Exception $e) {
            $app->abort($e->getCode(), $e->getMessage());
        }
        if ($ressource == false) {
            $app->abort(404, 'Not Found');
        }
        return new Response('Ok', 204);
    }
);

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }
    $msg = $e->getMessage();
    // log errors to error_log
    $ignored_codes = array(401, 404);
    if (!array_key_exists($code, $ignored_codes)) {
        error_log($msg);
    }
    // we want our errors to be returned as json
    $headers = array();
    $headers['Content-Type'] = 'application/json';
    if ($code == 401) $headers['WWW-Authenticate'] = 'Basic realm="Add-on API"';
    return new Response(
        json_encode(array('message' => $msg)),
        $code,
        $headers
    );
});

$app->run();

