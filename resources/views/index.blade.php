@extends('frame')

@section('js')

@endsection

@section('content')
	<div id="index" class="row">
		<div class="fm-thumb-container col-md-12">

			@foreach($files as $file)

				<icon 
					href="{{ $file->fileurl() }}" 
					src="{{ $file->icon() }}" 
					width="{{ $file->getMeta('width') }}"
					height="{{ $file->getMeta('height') }}"
					alt="{{ $file->Original }}"
					data-fileid="{{ $file->getKey() }}" 
	                data-file="{{ $file->fileurl() }}" 
	                data-icon="{{ $file->icon() }}" 
	                data-downurl="{{ $file->downurl() }}"
					file-data-url="{{ $file->fileDataUrl() }}"
	                data-description="{{ $file->Original }}"
	                :data-type="{{ $file->Type }}"
					media-type="{{ $file->getTypeName($file->Type) }}"
	                instext="{{ trans('filemanager.index.insert') }}"
	                deltext="{{ trans('filemanager.index.delete') }}"
	                delurl="{{ $file->deleteurl() }}"
	                :insertcallback="insert"
	                :responsecallback="response"
	                context="{{ $context }}"
					file-state="{{ $file->State }}"
				></icon>

			@endforeach
			
		</div>
	</div>

	<!-- pagination -->
	<nav aria-label="Page navigation" class="navbar-fixed-bottom">
		<div class="text-center">
			{{ $files->appends(['type' => $filetype])->links() }}
		</div>
	</nav>

@endsection

@section('script')
	<script></script>
@endsection

@section('message')

	@include('message')

@endsection

@section('modal')

	@include('viewupload')

@endsection