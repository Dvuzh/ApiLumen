<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use App\ResponseHelper;

use Aws\S3\S3Client;
use App\S3Provider;
use Validator;

class UploadsController extends Controller
{
    public function uploadFile(Request $request){ 
        

        // Perform validation checks.
        // Maximum size is imposed of 5MB and only images should be allowed.
        $validatedData = Validator::make(
            $request->all(),
            [
                'image_file' => 'required|image|max:5120',
                'type' => 'required|string'
            ],
            [
                'image_file.image' => 'File uploaded is not a valid image',
                'image_file.max' => 'Image file should be less than 5MB',
                'image_file.required' => 'One or more of the required fields were not provided',
                'type.required' => 'One or more of the required fields were not provided'
            ]
         );
         if ($validatedData->errors()->messages()) {
            return ResponseHelper::validationError($validatedData->errors()->first());
        }

        $image = $request->image_file;
        $pathToFile = $image->getPathName();
        $optimizerChain = OptimizerChainFactory::create();
        $optimizerChain->optimize($pathToFile);

        // The file_name will be the type+randomstring.
        $fileName = $request->type.'_'.str_random(30);
        
        //  Save the file in a S3 bucket
        $file = S3Provider::putFile($pathToFile, $fileName);         
        
        $responseData = [
            'link' => env('UPLOAD_HOST') . '/' . $fileName
        ];
        
        return response()->json($responseData);
    }
}
