<!-- Modal upload with dropzone -->
<modal v-model="open" ref="modal" @opened="" @closed="reloadList" @cancel="" class="apicontainer">

	<div slot="title">{{ trans('filemanager.layout.upload') }}</div>

	<transition name="fade">
		<div :class="'alert alert-'+alerttype" role="alert" v-if="alerton" v-html="alert"></div>
	</transition>

    <vue-dropzone 
   		ref="drz" 
   		id="dropzone" 
   		@vdropzone-success="drsuccess" 
   		@vdropzone-error="drerror"
   		:options="{
			url: '{{ url('request/upload') }}',
          	thumbnailWidth: {{ config('filemanager.thumbnail.width') }},
          	thumbnailHeight: {{ config('filemanager.thumbnail.height') }},
          	dictDefaultMessage : '{{ trans('filemanager.index.dragdrop')}}',
          	maxFilesize : '{{ config('filemanager.maxfilesize')}}',
          	headers : { 'X-CSRF-TOKEN' : '{{ csrf_token() }}' }
   		}">
   	</vue-dropzone>

   	<div slot="modal-footer"></div>
            
</modal>
