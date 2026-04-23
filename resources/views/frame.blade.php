<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>@yield('title')</title>
	<meta name="description" content="{{ trans('auth.blikk_desc') }}" />
	<meta name="keywords" content="{{ trans('auth.blikk_ext') }}" />

	<link href="{{ url('css/bootstrap.css') }}" rel="stylesheet" type="text/css" />
	<link href="{{ url('css/files.css') }}" rel="stylesheet" type="text/css" />

	@yield('css')

</head>

  <body>

  	<div id="app">
  
	    <!-- main area -->
		<div class="main-container container-fluid">

		  	<!-- nav area -->
		    <nav class="navbar navbar-light bg-light">
				<a class="btn btn-outline-secondary" href="" @click.prevent="open=true">
					<span class="glyphicon glyphicon-open"></span>  {{ trans('filemanager.layout.upload') }}
				</a>
				@if ( ! empty($scopedata['scope']) )
					@if ( empty($view) || $view == 'scope')
						<a class="btn btn-outline-secondary" href="" @click.prevent="reloadList('{{ route('list').'?view=all' }}')">
							<span class="glyphicon glyphicon-eye-open"></span>  {{ trans('filemanager.layout.viewall') }}
						</a>
					@endif
					@if ($view == 'all')
						<a class="btn btn-outline-secondary" href="" @click.prevent="reloadList('{{ route('list').'?view=scope' }}')">
							<span class="glyphicon glyphicon-eye-open"></span>  {{ $scopedata['description'] }}
						</a>
					@endif
				@endif
		    </nav>
		    
		    <!-- content -->
		    @yield('content')

		</div>
	
	    <!-- messages -->
		@yield('message')

		<!-- Dropzone -->
		@yield('modal')

	</div>
 
 	<!-- Vue and other stuff -->
	<script src="{{ url('/js/manifest.js') }}"></script>
	<script src="{{ url('/js/vendor.js') }}"></script>
	<script src="{{ url('/js/app.js') }}"></script>

	@yield('js')

    @yield('script')

   </body>
</html>
