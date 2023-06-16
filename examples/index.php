<?php
require __DIR__.'/../vendor/autoload.php';

use Solic\SolicService;
use Solic\View;

echo "<h1>Solic Implementation Example</h1>";

// Define your token
$token = 'YJaFTMH5nF0tOcNytQpPsS7pF69QZdtxCC4EZFN7';

// Create the service instance
$service = new SolicService($token);

// Get a list of forms
$listResponse = $service->listForms();
$formsContent = json_decode($listResponse->getContents());

// Call the getForm endpoint passing in the form id
$response = $service->getForm($formsContent->data[0]->id);
$contents = $response->getContents();

// Take the content and spit it out in the form view
$view = new View();
echo $view->render($contents, 'index.php');

// if the form is submitted, push the data to the storeForm endpoint
// the response will tell you if the form submission is considered spam.
if (!empty($_POST))
{
    $postResponse = $service->checkForm($_POST);
    echo $postResponse->getContents();
}