<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Gate;
use Image;
use Input;
use Storage;
use Validator;
use App\Models\File;
use App\Models\Fileinstance;
use Carbon\Carbon;
use App\Exceptions\FileinstanceException;

class FilesController extends Controller
{
    /**
     * Display File Listing and Menu
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // save optional request->parameters in session for further use
        if ( ! $request->has('view')) {
            foreach (File::$sessionable as $parname) {
                if ($request->has($parname)) {
                    $request->session()->put($parname,$request->input($parname));
                } else {
                    $request->session()->forget($parname);
                }
            }
        }

        $scopedata = [
            'scope' => $scope = $request->scope ?? $request->session()->get('scope') ?? '',
            'description' => __('filemanager.scopes.'.last(explode('.',$scope)))
        ];

        // return main view
        return view('index')
            ->with(['files' => $this->getFiles($request), 'scopedata' => $scopedata, 'view' => $request->view, 'context' => $request->context, 'filetype' => $request->type]);
    }

    /**
     * Get the Files for the user in Question
     *
     * @param integer $user_id (optional)
     * @param Request $request
     * @return File-Collection
     */
    public function getFiles(Request $request)
    {
        // set auth-user
        $user_id = session('userdata')['ID'];

        // basic query
        $query = (new File)->where('UserID',$user_id)->where('Visibility','auth');

        // @todo: Filtering via Filter Objects

        // type-filter
        $types = $this->getFileType($request->type ?? $request->session()->get('type') ?? '');
        if ($types[0] < 255) {
            $query = $query->where(function($q) use ($types) {
                foreach($types as $type) {
                    $q->orWhere('Type',$type);
                }
            });
        }

        // scope-filter
        $scope = $request->scope ?? $request->session()->get('scope') ?? '';
        if ( ! empty($scope) && $request->view !== 'all') {
            $query = $query->where('Scope',$scope);
        }

        // retrieve DB-infos
        return $query->latest('CreationDate')->paginate(config('filemanager.numberperpage'));
    }


    /**
     * Delivers a file:
     *          - image
     *          - thumbnail
     *          - download
     *
     * @param integer $id File-Id
     * @param Request $request
     * @param File $file
     * @return Response
     */
    public function getFile($id, Request $request, File $file)
    {
        // get the file entry
        $file = $file->findOrFail($id);
        $filepath = $file->getFilepath();

        // @TODO: Accesscontrol!

        // thumbnail
        if ($request->is('*thumbnail*')) {
            return Image::make(
                Storage::disk($file->getThumbdisk())->get($file->getThumbpath())
            )->response();
        }

        // image
        if ($request->is('*image*')) {
            return Image::make(
                Storage::disk($file->getFiledisk())->get($file->getFilepath())
            )->response();
        }

        // media (mit "streaming")
        if ($request->is('*media*')) {
            return $file->streamMedia();
        }

        // download als default (streaming for large downloads)
        if ($request->is('*download*')) {
            return $file->download();    
        }

    }

    /**
     * Upload-Management for the Filemanager
     *
     * @param Request $request
     * @return Response
     */
    public function upload(Request $request)
    {
        // validation
        $validator = Validator::make($request->all(), [
            'file' => 'required',
        ]);

        // detect error
        if ($validator->fails()) {
            return response($validator->errors()->first('file'),415);
        }

        // get an UploadedFile-Object
        $file = $request->file('file');

        // check upload
        if ( ! $file->isValid() ) {
            return response(trans('filemanager.upload.uploaderror'),415);  
        }

        // move File to users folder and add to DB
        if ( ! $filemodel = (new File)->addFile($file, $request) ) {
            return response(trans('filemanager.upload.workerror'),500);
        }

        return response()->json([
            'id' => $filemodel->getKey(),
            'success' => trans('filemanager.upload.success')]);
    }

    /**
     * Delete a File
     *
     * @param Request $request
     * @return bool true on success
     */
    public function delete(Request $request)
    {
        // file finden
        $file = (new File)->findOrFail($request->id);

        // permissions ok?
        if (Gate::denies('delete-file',$file)) {
            //return response()->json(['error' => trans('filemanager.delete.deniederror'), 'file' => $file]);
        }

        // Are you sure?
        if ( ! $request->ays) {
            return response()->json(['html' => trans('filemanager.delete.deleteays'), 'file' => $file]);
        }

        // yes we delete (with all dependencies)
        if ($file->delete()) {
            return response()->json(['success' => trans('filemanager.delete.deletesuccess'), 'file' => $file, 'reload' => true]);
        }

        // error redirect
        return response()->json(['error' => trans('filemanager.delete.deleteerror'), 'file' => $file]);
    }

    /**
     * Gets the Filetype from Tinymce-Filetypes (image, media, file)
     *
     * @param string $mcetype
     * @return array of filetypes (e.g. [1] for viewable, [2,4] for mediable, [255] for all)
     */
    private function getFileType($mcetype)
    {
       $typefunc = function($typename)
        {
            foreach (config('filemanager.filetypes') as $type => $meta) {
                if (key($meta) == $typename) {
                    return $type;
                }
            }
        };

        if ($mcetype == 'image') {
            return [$typefunc('viewable')];
        }

        if ($mcetype == 'media') {
            return [$typefunc('audible'), $typefunc('videable')];
        }

        //if ($mcetype == 'file') {
            return [$typefunc('all')];
        //}

     }

     /**
      * API: get fileinstaces for a given file
      */
    public function getFileinstances(File $file)
    {
        // return fresh relationship
        $file->unsetRelation('fileinstances');
        return $file->fileinstances;
    }

     /**
      * API: put a fileinstance
      * Fileinstances with same Unique set and same Scope and Target will be replaced
      */
    public function putFileinstance(Request $request)
    {
        $request->validate([
            'UserID' => 'required|numeric',
            'FileID' => 'required|numeric',
            'Scope' => 'required|string'
        ]);

        // testing uniqness in Scope and Target
        if ( $request->input('Unique') == 1) {
            $fileinstance = Fileinstance::where('Scope', $request->input('Scope'))
                ->where('Target', $request->input('Target'))->first();
        }

        if ( ! isset($fileinstance)) {
            $fileinstance = new Fileinstance;
        }

        $fileinstance->fill([
            'UserID' => $request->input('UserID'),
            'FileID' => $request->input('FileID'),
            'Unique' => $request->input('Unique') ?? 0,
            'Scope' => $request->input('Scope'),
            'Target' => $request->input('Target') ?? '',
            'Link' => $request->input('Link') ?? '',
            'CreationDate' => Carbon::now()
        ])->save();

        return response()->json($fileinstance,201);
    }

     /**
      * API: delete a fileinstance
      */
    public function deleteFileinstance(Fileinstance $fileinstance)
    {
        if ( ! $fileinstance->delete()) {
            throw new FileinstanceException(__('Der Eintrag kann nicht gelöscht werden!'));
        }

        return response()->json(['success' => true, 'message' => 'deleted'],200);
    }

     /**
      * API: delete a fileinstance by Parameters
      */
    public function deleteFileinstanceByParams(Request $request, Fileinstance $fileinstance)
    {
        $params = ['UserID','FileID','Scope','Target'];

        $hasParams = false;
        foreach($params as $param) {
            if (! empty($request->input($param))) {
                $hasParams = true;
                break;
            }
        }

        if( ! $hasParams) {
            throw new FileinstanceException(__('Erforderliche Parameter fehlen!'));
        }

        foreach($params as $param) {
            if ($request->has($param) && ! empty($request->input($param))) {
                $fileinstance = $fileinstance->where($param,$request->input($param));
            }
        }    

        // delete
        $count = $fileinstance->get()->count();
        if ($count >= 1) {
            $fileinstance->delete();
        }
        
        return response()->json(['success' => true, 'message' => 'deleted', 'count' => $count],200);
    }


    public function fileData(int $id)
    {
        $file = (new File)->findOrFail($id);
        return $file->getAttributes();
    }


}
