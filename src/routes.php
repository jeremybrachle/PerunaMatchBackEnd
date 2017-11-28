<?php

use Slim\Http\Request;
use Slim\Http\Response;

//projects
$app->get('/projects', function ($request, $response, $args) {
	try {
		//querry DB for project info
		$sth = $this->db->prepare("SELECT projectID, name, tag, posterID FROM Projects");
   		$sth->execute();
   		$result = $sth->fetchAll();

		//cast querry results as the appropriate data types
		foreach($result as &$curr) {
			$curr['projectID'] = (int) $curr['projectID'];
			$curr['tag'] = json_decode($curr['tag']);
			$curr['posterID'] = (int) $curr['posterID'];
		}

	        return $response->withJson(["projects" => $result], 201)
				->withHeader('Content-Type', 'application/json')
                        	->withHeader('Location', '/projects');
	}
	catch(Exception $e) {
                return $response->withJson(["error" => "error.unauthorized"], 400)
                                ->withHeader('Content-Type', 'application/json');
        }
});

//Listings
$app->get('/listing/{projectId}', function($request, $response, $args) {
	$sql = $this->db->prepare("SELECT id, projectID, title, description, start, end, majors, contactName, contactEmail FROM Listings WHERE projectId = " . $args['projectId']);


	//SQL error handling (for if non-integer is passed in as Project ID)
	try{
		$sql->execute();
		$listings = $sql->fetchAll();
		foreach($listings as &$curr){
			$curr['id'] = (int) $curr['id'];
			$curr['projectID'] = (int) $curr['projectID'];
			$curr['majors'] = json_decode($curr['majors']);
		}
	}
	catch(Exception $e) {
		return $response->withJson(["error" => "error.unauthorized"], 400)
				->withHeader('Content-Type', 'application/Json');
	}


	//Listings and header
	return $this->response->withJson($listings, 201)
			      ->withHeader('Content-Type', 'application/Json')
			      ->withHeader('Location', '/listing/:id');
});

//listing/listingID - DELETE
$app->delete('/listing/{listingID}', function($request, $response, $args) {
	try {
		$sql = $this->db->prepare("DELETE FROM Listings WHERE id = {$args['listingID']}");
		$sql->execute();

		return $response->withStatus(200)
                                ->withHeader('Content-Type', 'application/json');
	}
	catch(Exception $e) {
		return $response->withJson(["error" => "error"], 401)
                                ->withHeader('Content-Type', 'application/json');
	}
});

//signup
$app->put('/signup', function($request, $response, $args) {
	//retrive data from request body
	$parsedBody = $request->getParsedBody();
	$userID = $parsedBody['userID'];
        $username = $parsedBody['username'];
        $password = $parsedBody['password'];
        $email = $parsedBody['email'];
	$isProfessor = ($parsedBody['isProfessor']) ? 'true' : 'false';

	//prepare SQL statement for inserting a new user into DB
	$sql = $this->db->prepare(
		"INSERT INTO Users (userID, username, password, email, isProfessor)
		 VALUES ('{$userID}', '{$username}', '{$password}', '{$email}', '{$isProfessor}')"
	);

	try {
		$sql->execute();
	}
	catch(Exception $e) {
		return $response->withJson(["error" => "error"], 401)
				->withHeader('Content-Type', 'application/json');
	}

	$responseBody = ["token" => 12343, "userID" => $userID, "isProfessor" => $parsedBody['isProfessor']];
	return $response->withJson($responseBody, 201)
			->withHeader('Content-Type', 'application/json')
			->withHeader('Location', '/signup');
});

//welcome
$app->get('/welcome', function ($request, $response, $args) {
    return $response->withStatus(200)->write('Hello!');
});

//student
$app->get('/student/{name}', function($request, $response, $args) {
	return $response->write("Student Profile for: " . $args['name']);
});

//professor
$app->get('/professor/{name}', function($request, $response, $args) {
        return $response->write("Professor Profile for: " . $args['name']);
});

//settings
$app->get('/{name}/settings', function($request, $response, $args) {
        return $response->write("Settings for user: " . $args['name']);
});

//log in
$app->post('/login', function($request, $response, $args) {
	//get the body
	$data = $request->getParsedBody();
	//make variables for username and password
	$userID = filter_var($data['username'], FILTER_SANITIZE_STRING);
	$passCode = filter_var($data['password'], FILTER_SANITIZE_STRING);
	
	//check mysql for the username and password
	$query = $this->db->prepare("SELECT * FROM Users WHERE username = '".$userID."' AND password = '".$passCode."'");
	$query->execute();

	//get the count of the numebr of rows affected by the query
	$count = $query->rowCount();

	//now check if the number of rows is 1 (match) or 0 (no match)
	if($count == 1)
	{
		//get the values from the array:
		$uToken = 1234; //default for now

		//get the user's id:
		$queryID = $this->db->prepare("SELECT * FROM Users WHERE username = '".$userID."' AND password = '".$passCode."'");
		$queryID->execute();
		$uId = $queryID->fetchColumn(); //get the first column for the user id

		//get the boolean for whether the user is a professor
		$queryIsProf = $this->db->prepare("SELECT * FROM Users WHERE username = '".$userID."' AND password = '".$passCode."'");
		$queryIsProf->execute();
                $isProf = $queryIsProf->fetchColumn(4); //get the professor boolean

		$isProfBool = $isProf === 'true'? true: false;

		//make an array for outputting to json
		$success = array("token" => $uToken, "userId" =>(int)$uId, "isProfessor" => $isProfBool);
		//return $response->withJson($success, 201);

		return $response->withJson($success, 201)
                                ->withHeader('Content-Type', 'application/json')
                                ->withHeader('Location', '/login');

	}
	else //no match
	{
		$error = array("error" => "error.unauthorized");
		return $response->withJson($error, 401);
	}

});
