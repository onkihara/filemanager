<?php

namespace App\Exceptions;

use View;
use Illuminate\Http\Request;

class AvatarException extends \Exception {

	/**
	 * Render the Exception
	 *
	 * @param Requesst $request
	 * @return Response
	 */
	 public function render($request)
	 {
		// api-request
        return response()->json(['error'=> $this->getMessage()], 422);
	 }
}