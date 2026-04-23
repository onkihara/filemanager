<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Custom\JWT;
use Storage;
use Validator;
use App\Models\File;
use App\Models\Avatar;
use App\Models\Fileinstance;
use Carbon\Carbon;
//use App\Exceptions\FileinstanceException;

class AvatarController extends Controller
{

    /**
     * Get the avatar data
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getAvatarData(Request $request)
    {
        $decoded = JWT::process($request);

        // validation
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'workspace' => 'required|numeric',
            // userid in url must match the one in jwt-token
            'userid' => 'required|in:'.$decoded->userid
        ]);

        // detect error
        if ($validator->fails()) {
            return response($validator->errors(),415);
        }

        $avatar = Avatar::where('UserID',$request->userid)
            ->where('Scope',$request->workspace)
            ->where('Descriptor',$request->type)
            ->first();

        // no entry found
        if ( ! $avatar) {
            return response()->json(['result' => 'error', 'message' => trans('avatar.exceptions.avatarnotfound')],204); // no content
        }

        $avdata = json_decode($avatar->Content,true);
        $urlfile = '';
        if ($avdata['file'] != 0) {
            $urlfile = route('image',['id' => $avdata['file']]);
        }

        return response()->json(['result' => 'success', 'file' => $urlfile, 'av' => $avdata]);
    } 



    /**
     * create the avatar and store in profiles
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $decoded = JWT::process($request);

        // validation
        $validator = Validator::make($request->all(), [
            'avdata' => 'required',
            'workspace' => 'required|numeric',
            // userid in url must match the one in jwt-token
            'userid' => 'required|in:'.$decoded->userid
        ]);

        // detect error
        if ($validator->fails()) {
            return response($validator->errors(),415);
        }

        // find or new
        $avatar = Avatar::firstOrNew(
            ['UserID' => $request->userid, 'Scope' => $request->workspace, 'Descriptor' => 'avatar']
        );
        $avatar->Content = $request->avdata;

        // create avatar image from data
        $avatarpath = $avatar->createAvatar(json_decode($request->avdata,true),$request->userid,$request->auth_token);

        // persist
        $avatar->save(); // vcard

        return response()->json(['result' => 'success', 'avatarpath' => $avatarpath, 'avatarid' => $avatar->getKey()]);
    }



    /**
     * delete the avatardata and the stored picture
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deleteAvatar(Request $request)
    {
        $decoded = JWT::process($request);

        // validation
        $validator = Validator::make($request->all(), [
            'avatarid' => 'required',
            // userid in url must match the one in jwt-token
            'userid' => 'required|in:'.$decoded->userid
        ]);

        // detect error
        if ($validator->fails()) {
            return response($validator->errors(),415);
        }

        // find or new
        $avatar = Avatar::find($request->input('avatarid'));

        // delete
        $avatar->delete(); // vcard
        $avatar->deleteProfile($decoded->userid,$request->auth_token, 'avatar');
        

        return response()->json(['result' => 'success']);
    }



   /**
     * upload the avatar background image
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {

        $decoded = JWT::process($request);

        // validation
        $validator = Validator::make($request->all(), [
            'file' => 'required|image',
            // userid in url must match the one in jwt-token
            'userid' => 'required|in:'.$decoded->userid
        ]);

        // detect error
        if ($validator->fails()) {
            return response($validator->errors()->first('file'),415);
        }

        // get an UploadedFile-Object
        $uploadedfile = $request->file('file');

        // check upload
        if ( ! $uploadedfile->isValid() ) {
            return response(trans('filemanager.upload.uploaderror'),415);  
        }

        // move File to users folder and add to DB
        if ( ! $filemodel = (new File)->addFile($uploadedfile, $request) ) {
            return response()->json(['result' => 'error', 'message' => trans('filemanager.upload.workerror')]);
        }

        return response()->json([
            'result' => 'success', 
            'url' => route('image',['id' => $filemodel['ID']]), 
            'id' => $filemodel['ID'],
            'filename' => $filemodel['Filepath'].$filemodel['Filename']
        ]);

    }



   /**
     * delete the avatar background image
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {

        $decoded = JWT::process($request);

        // validation
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            // userid in url must match the one in jwt-token
            'userid' => 'required|in:'.$decoded->userid
        ]);

        // detect error
        if ($validator->fails()) {
            return response($validator->errors(),415);
        }

        // find file
        $file = (new File)->findOrFail($request->id);

        // delete (with all dependencies)
        if ($file->delete()) {

            // adjust avatar-table entry
            // @TODO:

            return response()->json([
                'result' => 'success',
                'id' => $file['ID'],
            ]);
        }

        // error deleting file
        $message = trans('filemanager.delete.deleteerror');
        return response()->json(['result' => 'error', 'message' => $message],415);
    }

    

}
