<?php

namespace App\Http\Controllers;

use App\Models\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class ImageController extends Controller
{
    protected $key;
    private $aws_secret_key;
    private $aws_access_key;
    protected $picsart;
    private $client;
    public function __construct()
    {
        $keys = Key::where('api','vanceai')->get();
        $keys1 = Key::where('api','picsart')->get();
        foreach ($keys1 as $key) {
            // Send a request to check the balance
            $response = Http::withHeaders([
                'X-Picsart-API-Key' => $key->token,
                'Accept' => 'application/json',
            ])->get('https://api.picsart.io/tools/1.0/balance');

            if ($response->successful()) {
                $data = $response->json();

                // Check if the 'credits' value is less than 5
                if (isset($data['credits']) && $data['credits'] < 5) {
                    // Delete the key if credits are less than 5
                    $key->delete();
                }
            }
        }

        foreach ($keys as $key) {
            $response = Http::get("https://api-service.vanceai.com/web_api/v1/point", [
                'api_token' => $key->token
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['max_num'], $data['data']['used_num'])) {
                    $maxNum = (float) $data['data']['max_num'];
                    $usedNum = (float) $data['data']['used_num'];
                    if ($maxNum - $usedNum < 10) {
                        $key->delete();
                    }
                }
            }
        }
        $result =  Key::where('api','vanceai')->orderBy('id', 'asc')->first()?->token;
        $this->key=$result;
        $result1 =  Key::where('api','picsart')->orderBy('id', 'asc')->first()?->token;
        $this->picsart=$result1;
        $this->aws_secret_key = 'b52dcdbea046cc2cc13a5b767a1c71ea8acbe96422b3e45525d3678ce2b5ed3e';
        $this->aws_access_key = 'cbb3e2fea7c7f3e7af09b67eeec7d62c';

        $this->client = new Client();
    }
    public function getGenerate(Request $request){
        if(!$request->has('id')){
            return response()->json(['check'=>false,'msg'=>'id is required']);
        }
        while (true) { // Infinite loop to check status
        $getResponse = Http::withHeaders([
            'X-Picsart-API-Key' => $this->picsart,
            'accept' => 'application/json',
        ])->get("https://genai-api.picsart.io/v1/text2image/inferences/{$request->id}");

        $data = $getResponse->json();

        if ($data['status'] === "FINISHED") {
            // Extract the first URL from the data array
            $url = $data['data'][0]['url'] ?? null;

            if ($url) {
                return response()->json([
                    'check' => true,
                    'url' => $url,
                ]);
            }

            return response()->json([
                'check' => false,
                'message' => 'No URL found in the response data.',
            ]);
        }

        // Optional: Add a sleep to avoid spamming the API too frequently
        sleep(2); // Wait 2 seconds before the next request
    }
    }

    public function generateImage(Request $request)
    {
        if(!$request->has('prompt')){
            return response()->json(['check'=>false,'msg'=>'Prompt is required']);
        }

        $response = Http::withHeaders([
            'X-Picsart-API-Key' => $this->picsart,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://genai-api.picsart.io/v1/text2image', [
            'prompt' => $request->prompt,
            'count' => 1,

        ]);
        $data = $response->json();
            $inferenceId=$data['inference_id'];
            sleep(3);
            while (true) { // Infinite loop to check status
                $getResponse = Http::withHeaders([
                    'X-Picsart-API-Key' => $this->picsart,
                    'accept' => 'application/json',
                ])->get("https://genai-api.picsart.io/v1/text2image/inferences/{$inferenceId}");
                $data = $getResponse->json();
                if ($data['status'] === "FINISHED") {
                    // Extract the first URL from the data array
                    $url = $data['data'][0]['url'] ?? null;

                    if ($url) {
                        return response()->json([
                            'check' => true,
                            'url' => $url,
                        ]);
                    }

                    return response()->json([
                        'check' => false,
                        'message' => 'No URL found in the response data.',
                    ]);
                }

                // Optional: Add a sleep to avoid spamming the API too frequently
                sleep(2); // Wait 2 seconds before the next request
            }
        if ($response->successful()) {


        }

    }
    /**
     * Display a listing of the resource.
     */
    public function RemoveBackground(Request $request){
        if (!$request->hasFile('image') || !$request->file('image')->isValid()) {
            return response()->json(['error' => 'No valid file uploaded'], 400);
        }

        // Retrieve the uploaded file
        $image = $request->file('image');

        // Create a temporary file path
        $tempFilePath = $image->getPathname();

        // Send the API request
        $response = Http::withHeaders([
            'X-Picsart-API-Key' => $this->picsart,
            'Accept' => 'application/json',
        ])->attach(
            'image', // The name of the file field expected by the API
            file_get_contents($tempFilePath), // File content
            $image->getClientOriginalName() // Original file name
        )->post('https://api.picsart.io/tools/1.0/removebg', [
            [
                'name' => 'output_type',
                'contents' => 'cutout',
            ],
            [
                'name' => 'bg_blur',
                'contents' => '0',
            ],
            [
                'name' => 'scale',
                'contents' => 'fit',
            ],
            [
                'name' => 'auto_center',
                'contents' => 'false',
            ],
            [
                'name' => 'stroke_size',
                'contents' => '0',
            ],
            [
                'name' => 'stroke_color',
                'contents' => 'FFFFFF',
            ],
            [
                'name' => 'stroke_opacity',
                'contents' => '100',
            ],
            [
                'name' => 'shadow',
                'contents' => 'disabled',
            ],
            [
                'name' => 'shadow_opacity',
                'contents' => '20',
            ],
            [
                'name' => 'shadow_blur',
                'contents' => '50',
            ],
            [
                'name' => 'format',
                'contents' => 'PNG',
            ],
        ]);

        // Handle the API response
        if ($response->successful()) {
            $data = $response->json();
            $processedImageUrl = $data['data']['url'];
            $filename='file'.time();
            $image = $this->uploadToCloudFlareFromCdn($processedImageUrl,$filename,'removeBackground');
            return response()->json(['check'=>true,'url'=>$image]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $response->body(),
            ], $response->status());
        }
    }
    private function uploadToCloudFlareFromCdn($image_url, $filename, $folder)
    {
        try {
            // Step 1: Prepare Cloudflare R2 credentials and settings
            $accountid = '453d5dc9390394015b582d09c1e82365';
            $r2bucket = 'artapp';  // Updated bucket name
            $accessKey = $this->aws_access_key;
            $secretKey = $this->aws_secret_key;
            $region = 'auto';
            $endpoint = "https://$accountid.r2.cloudflarestorage.com";

            // Set up the S3 client with Cloudflare's endpoint
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $accessKey,
                    'secret' => $secretKey,
                ],
                'endpoint' => $endpoint,
                'use_path_style_endpoint' => true,
            ]);

            // Step 2: Stream image directly from CDN
            $imageData = file_get_contents($image_url);

            if ($imageData === false) {
                // Handle download error
                Log::error('Failed to retrieve image from CDN URL: ' . $image_url);
                return 'error';
            }

            // Step 3: Define the object path and name in R2
            $r2object = $folder . '/' . $filename . '.jpg';

            // Step 4: Upload the file to Cloudflare R2
            try {
                $result = $s3Client->putObject([
                    'Bucket' => $r2bucket,
                    'Key' => $r2object,
                    'Body' => $imageData,  // Pass the image content directly from CDN
                    'ContentType' => 'image/jpeg',
                ]);

                // Generate the CDN URL using the custom domain
                $cdnUrl = "https://artapp.promptme.info/$folder/$filename.jpg";
                return $cdnUrl;
            } catch (S3Exception $e) {
                Log::error("Error uploading file: " . $e->getMessage());
                return 'error: ' . $e->getMessage();
            }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return 'error';
        }
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }
    private function uploadToCloudFlareFromFile($filePath, $folder, $filename)
{
    try {
        // Step 1: Prepare Cloudflare R2 credentials and settings
        $accountid = '453d5dc9390394015b582d09c1e82365';
        $r2bucket = 'artapp';  // Updated bucket name
        $accessKey = $this->aws_access_key;
        $secretKey = $this->aws_secret_key;
        $region = 'auto';
        $endpoint = "https://$accountid.r2.cloudflarestorage.com";

        // Set up the S3 client with Cloudflare's endpoint
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey,
            ],
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
        ]);

        // Step 2: Define the object path and name in R2
        $r2object = $folder . '/' . $filename; // Filename already includes the extension

        // Step 3: Upload the file to Cloudflare R2
        try {
            $result = $s3Client->putObject([
                'Bucket' => $r2bucket,
                'Key' => $r2object,
                'Body' => file_get_contents($filePath), // Get the file content
                'ContentType' => mime_content_type($filePath), // Automatically detect MIME type
            ]);

            // Generate the CDN URL using the custom domain
            $cdnUrl = "https://artapp.promptme.info/$folder/$filename";
            return $cdnUrl;
        } catch (S3Exception $e) {
            Log::error("Error uploading file: " . $e->getMessage());
            return 'error: ' . $e->getMessage();
        }
    } catch (\Throwable $th) {
        Log::error($th->getMessage());
        return 'error: ' . $th->getMessage();
    }
}

    public function image(Request $request){
         // Validate if the file is uploaded and is valid
         if (!$request->hasFile('image') || !$request->file('image')->isValid()) {
            return response()->json(['error' => 'No valid file uploaded'], 400);
        }

        // Get the uploaded file
        $file = $request->file('image');

        // Get the file path and original filename
        $filename = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->getPathname();

        // API Token (assuming it's set on $this->key)
        $apiToken = $this->key;

        // Make the request to the API to upload the image
        $response = Http::attach('file', file_get_contents($filePath), $filename)
            ->post('https://api-service.vanceai.com/web_api/v1/upload', [
                'api_token' => $apiToken,
            ]);

        // Check if the request was successful
        if ($response->successful()) {
            // Get the response data
            $data = $response->json();

            // Retrieve the 'uid' from the response data
            $uid = $data['data']['uid'];
            $transformResponse = Http::post('https://api-service.vanceai.com/web_api/v1/transform', [
                'api_token' => $this->key,  // Use your token here
                'uid' => $uid,
                'jconfig' => json_encode([
                    'name' => 'img2anime',
                    'config' => [
                        'module' => 'img2anime',
                        'module_params' => [
                            'model_name' => 'style4',
                            'prompt' => '',
                            'overwrite' => false,
                            'denoising_strength' => 0.75
                        ]
                    ]
                ])
            ]);

            // Check if the transform request was successful
            if ($transformResponse->successful()) {
                // Get the response data from the transform API
                $transformData = $transformResponse->json();
                // dd($transformData)
                $transId = $transformData['data']['trans_id'];

                // Step 3: Request to download the transformed image using trans_id
                $downloadResponse = Http::post('https://api-service.vanceai.com/web_api/v1/download', [
                    'api_token' => $this->key,
                    'trans_id' => $transId,
                ]);

                // Check if the download request was successful
                if ($downloadResponse->successful()) {
                    $fileContent = $downloadResponse->body();

                    // Temporary local storage path for the image
                    $filename = time() . '.jpg'; // Dynamic filename based on the current timestamp
                    $storagePath = 'transformed_images/' . $filename;

                    // Save the file temporarily to the local disk
                    Storage::disk('public')->put($storagePath, $fileContent);

                    // Define the folder in Cloudflare where the file will be uploaded
                    $folder = 'uploadcartoon';

                    // Full path of the local file to pass to Cloudflare upload
                    $localFilePath = Storage::disk('public')->path($storagePath);

                    // Upload the file to Cloudflare
                    $cloudflareLink = $this->uploadToCloudFlareFromFile($localFilePath, $folder, $filename);

                    // Delete the temporary local file
                    Storage::disk('public')->delete($storagePath);

                    // Return the response with details
                    return response()->json([
                        'message' => 'AI-generated image uploaded and successfully stored in Cloudflare',
                        'uid' => $uid,
                        'trans_id' => $transId,
                        'url' => $cloudflareLink, // Cloudflare URL of the uploaded file
                    ]);
                } else {
                    // Handle error if transform API request fails
                    return response()->json(['error' => 'Failed to transform image'], 500);
                }
                // Return a success message with the UID
                // return response()->json([
                //     'message' => 'Image uploaded successfully',
                //     'uid' => $uid,
                //     'name' => $data['data']['name'],
                //     'thumbnail' => $data['data']['thumbnail'],
                //     'w' => $data['data']['w'],
                //     'h' => $data['data']['h'],
                //     'filesize' => $data['data']['filesize'],
                // ]);
            } else {
                // Return an error if the API request failed
                return response()->json(['error' => 'Failed to upload image'], 500);
            }
        }
    }
    /**
     * Show the form for creating a new resource.
     */
   /**
    * Show the form for creating a new resource.
    */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Key $key)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Key $key)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Key $key)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Key $key)
    {
        //
    }
}
