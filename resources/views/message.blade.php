<!-- Modal Messages -->
<modal 
  v-model="messageon"
  ref="message" 
  class="apicontainer" 
  cancel-text="{{ trans('filemanager.delete.deletecancel') }}" 
  ok-text="{{ trans('filemanager.delete.deleteok') }}" 
  :callback="deleteFile"
  @closed="onClosingMessage"
>

  <div v-if="messagetype=='danger'" slot="title" class="text-danger"><span class="glyphicon glyphicon-exclamation-sign"></span> @{{ file.Original }}</div>
  <div v-if="messagetype=='danger'" class="text-danger" v-html="message"></div>
  <div v-if="messagetype=='danger'" slot="modal-footer"></div>
            
  <div v-if="messagetype=='success'" slot="title" class="text-success"><span class="glyphicon glyphicon-ok"></span> @{{ file.Original }}</div>
  <div v-if="messagetype=='success'" class="text-success" v-html="message"></div>
  <div v-if="messagetype=='success'" slot="modal-footer"></div>
            
	<div v-if="messagetype=='warning'" slot="title" class="text-warning"><span class="glyphicon glyphicon-question-sign"></span> @{{ file.Original }}</div>
  <div v-if="messagetype=='warning'" class="text-warning" v-html="message"></div>
  
            
</modal>
