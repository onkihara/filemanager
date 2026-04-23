<?php

namespace App\Exceptions;

use View;
use Illuminate\Http\Request;

class AuthorizationException extends \Exception {

	/**
	 * Render the Exception
	 *
	 * @param Requesst $request
	 * @return Response
	 */
	 public function render($message)
	 {
		// api-request
        return response()->json(['error'=> $message], 422);
	 }
}