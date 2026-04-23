
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */
require('./bootstrap');

import vue2Dropzone from 'vue2-dropzone';
import 'vue2-dropzone/dist/vue2Dropzone.min.css';
import Modal from './components/Modal.vue';
import Icon from './components/Icon.vue';

const TIMEOUT = 4000;

const filepicker = new Vue({

    el: '#app',

    components : {
    	modal : Modal,
    	vueDropzone: vue2Dropzone,
    	icon : Icon
    },

    data : function() {
    	return {
    		open : false,
    		alert : '',
    		alerton : false,
    		alerttype : '',
    		message : '',
    		messageon : false,
    		messagetype : '',
    		file : {},
    		icon : null,
    		reload : null,
            insertTarget : null,
    	}
    },

    methods : {
        // setting the insertTarget-function viia postMessage-Event from parent-window
        setInsertcallback() {
            let url = new URL(location.href);
            let context = url.searchParams.get('context') || 'parent';
            this.insertTarget = context;
        },
    	// reload view
    	reloadList(url) {
            if (url) {
                location.href = url;
                return;
            }
    		location.reload();
    	},
        viewAll() {
            let href = location.href + '&withoutscope=1';
            //console.log(href);
            location.href = href;
        },
    	drsuccess(file, response) {
    		//console.log(file);
    		this.alert = response.success + ' - ' + file.name;
    		this.alerton = true;
    		this.alerttype = 'success';
    		this.hideMessage();
    	},
    	drerror(file,message,xhr) {
    		this.alert = message + ' - ' + file.name;
    		this.alerton = true;
    		this.alerttype = 'danger';
    		this.hideMessage();
    	},
    	hideMessage() {
    		setTimeout(() => {
    			this.alerton = false;
    			this.messageon = false;
    		}, TIMEOUT);
    	},
    	onClosingMessage() {
    		if (this.reload) {
    			if (this.reload === true) {
    				location.reload();
    			} else {
    				location.href = this.reload;
    			}
    		}
    	},
    	deleteFile() {
    		if (this.icon) {
    			this.icon.data.delurl += '?ays=yes';
    			this.icon.deleteImage();
    		}
    	},
    	// icon: referenz auf das Icon-vue-objekt, das eingefügt werden soll
    	insert(icon) {
            if ( ! this.insertTarget ) {
       			this.message = 'Eingefügt: ' + icon.data.description;
    			this.messagetype = 'success';
    			this.messageon = true;
            } else {
                // post to given target (e. g. parent)
                let target = eval(this.insertTarget);
                target.postMessage({ type: 'Filemanager-insert', icon : icon.data},'*');
            }
    	},
    	// icon: referenz auf das Icon-vue-objekt, von dem die Response stammt
    	response(response, icon) {
    		this.messageon = true;
    		this.file = response.data.file;
    		this.icon = icon;
    		this.reload = response.data.reload || false;
    		//console.log(this.file)
    		if (response.data.error) {
    			this.message = response.data.error;
    			this.messagetype = 'danger';
				this.hideMessage();
    			return;
    		}
     		if (response.data.success) {
	   			this.message = response.data.success;
				this.messagetype = 'success';
				this.hideMessage();
    			return;
    		}
    		if (response.data.html) {
	   			this.message = response.data.html;
				this.messagetype = 'warning';
    			return;
    		}
    		console.log(response);
    	},
        // display a message from iframe (via message-event)
        receiveMessage(event) {
            // message command
            if (event.data.type != 'Filemanager-message') {
                return;
            }
            console.log(event.data);
        }
    },

    watch : {
    	
    },

    created()  {
        // add message-event-listener
        window.addEventListener("message", this.receiveMessage, false);
        // init insertcallback
        this.setInsertcallback();
    }
});
