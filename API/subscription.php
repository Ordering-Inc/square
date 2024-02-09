<?php
require_once 'utils.php';

$responseSquare = json_decode(file_get_contents('php://input'));

$responseSquare->params = $_GET;

file_put_contents('responseSquare.json', json_encode($responseSquare));

