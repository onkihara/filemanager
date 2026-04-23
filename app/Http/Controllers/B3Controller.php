<?php

namespace App\Http\Controllers;

use Image;
use Storage;
use App\Models\File;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class B3Controller extends Controller
{

    /**
     * Retreive File-Data
     * @see routes/b3.php
     *
     * @return Response
     */
    public function retreive(Request $request, File $file)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        $path = urldecode($request->input('path'));
        $files = $file->getOriginalFromVirtualpath($path);

        if ($files->isEmpty()) {
            return response()->json(['files' => []],404);
        }

        // we download only the first of multiple files
        // @TODO: ZIP for multiple files
        $file = $files->first();

        if ($file->isViewable()) {
            try {

                $resp = Image::make(
                    Storage::disk($file->getFiledisk())->get($file->getFilepath())
                )->response();
                return $resp;

            } catch (\Throwable $e) {
                info($e->getMessage());
            }    
        }

        // @TODO other file-types

        return response()->json(['success' => false, 'message' => 'File not retreivable!'],204);
    }


    /**
     * Retreive File-Data
     * @see routes/b3.php
     *
     * @return Response
     */
    public function exists(Request $request, File $file)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        $path = urldecode($request->input('path'));
        $files = $file->getOriginalFromVirtualpath($path);

        if ($files->isEmpty()) {
            return response()->json(['success' => false]);
        }
        return response()->json(['success' => true]);
    }


    /**
     * Retreive File-Data
     * @see routes/b3.php
     *
     * @return Response
     */
    public function isdir(Request $request, File $file)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        $path = urldecode($request->input('path'));
        $path = rtrim($path,'/').'/';
        return response()->json(['success' => $file->where('Virtualpath',$path)->count() > 0]);
    }


    /**
     * Download-Management for the Filemanager
     * @see routes/b3.php
     *
     * @return Response
     */
    public function downloadUrls(Request $request, File $file)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        $path = urldecode($request->input('path'));
        $files = $file->getOriginalFromVirtualpath($path);

        if ($files->isEmpty()) {
            return response()->json(['files' => []]);
        }

        $downurls = $files->map(function($file) {
            return $file->downurl();
        });
        return response()->json(['files' => $downurls]);
    }


   /**
     * Upload-Management for the Filemanager
     * @see routes/b3.php
     *
     * @return Response
     */
    public function upload(Request $request, File $file)
    {
        $request->validate([
            'path' => 'required|string',
            'file' => 'required|file',
            'replace' => 'sometimes|required|boolean'
        ]);

        $uploaded_file = $request->file('file');
        $path = urldecode($request->input('path'));
        $vpath = dirname($path) == '.' ? '' : dirname($path).'/';

        $payload = $this->getPayloadFromToken($request);
        $request->merge(['userid' => $payload->userid ?? 0]);
        $request->merge(['username' => $payload->username ?? 'unknown']);
        $request->merge(['vpath' => $vpath]);
        $request->merge(['to_replace' => $request->input('replace',true)]);
        if ( ! $request->has('scope')) {
            $request->merge(['scope' => $payload->scope ?? '']);
        }

        if ( ! $uploaded_file->isValid() ) {
            return response()->json(['success' => false, 'message' => 'filemanager.upload.notvalid'],415);  
        }

        // move File to users folder and add to DB
        if ( ! $filemodel = (new File)->addFile($uploaded_file, $request) ) {
            return response()->json(['success' => false, 'message' => 'filemanager.upload.notaddable'],500); 
        }

       return response()->json([
            'success' => true,
            'url' => $filemodel->fileurl(),
            'thumb_url' => $filemodel->icon(),
            'path' => $filemodel->getFilepath(),
            'vpath' => $filemodel->getVirtualpath(),
            'id' => $filemodel->getKey()
       ]);

    }


    // private -------------------------------



    private function getPayloadFromToken(Request $request)
    {
        if ($request->has('token')) {
            $token = $request->input('token');
        } elseif ($request->has('jwt')) {
            $token = $request->input('jwt');
        } elseif ($request->header('authorization')) {
            preg_match('/Bearer\s*(.*)/i',$request->header('authorization'),$matches);
            $token = $matches[1];
        } else {
            return null;
        }

        JWT::$leeway = config('auth.jwt_leeway');
        return JWT::decode($token, new Key(config('auth.api.v1.secret'), config('auth.api.v1.algo')));
    }




}
