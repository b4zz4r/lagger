<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\idkRequest;
use App\Http\Resources\SwaggerResource;


class RequestController extends Controller
{
    public function something(idkRequest $request): SwaggerResource
    {

            return new SwaggerResource([]);
    } 

    public function CreateJsonFile()
    {
        set_include_path("/var/www/html/config");
        $jsonData = include "swagger.php";
        file_put_contents(config('laravel-swagger.outputFile') . 'swagger_data.json', json_encode($jsonData));
    } 
}
