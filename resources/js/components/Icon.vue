<template>
    <div class="fm-thumbnail">

        <a :href="href" class="thumbnail"><img :src="filesrc" :alt="alt" :title="alt" /></a>

        <button
            class="btn btn-link btn-sm"
            :data-fileid="data.fileid"
            :data-file="data.file"
            :data-icon="data.icon"
            :data-fileurl="data.fileurl"
            :data-description="dataDescription"
            v-html="instext"
            @click="insertImage"
        >
        </button>

        <a @click="deleteImage" class="btn btn-link btn-sm" v-html="deltext"></a>

    </div>
</template>

<script>

    import _ from 'lodash';
    import axios from 'axios';

    export default {

        mounted : function() {
            if (this.state != '1') {
                this.refreshTimer = setInterval(() => {
                    this.refresh();
                }, this.refreshCycle);
            }
        },

         props : {
            href : { type : String, default : '' },
            src : { type : String, default : '' },
            width : { type : String, default : '' },
            height : { type : String, default : '' },
            alt : { type : String, default : 'Icon' },
            dataFileid : { type : String, default : '' },
            dataFile : { type : String, default : '' },
            dataIcon : { type : String, default : '' },
            dataFileurl : { type : String, default : '' },
            dataDownurl : { type : String, default : '' },
            fileDataUrl : { type : String, default : '' },
            dataDescription : { type : String, default : '' },
            dataType : { type : Number, default : 255 },
            mediaType : { type : String, default : '' },
            deltext : { type : String, default : 'delete' },
            delurl : { type : String, default : '' },
            delmethod : { type : String, default : 'DELETE' },
            instext : { type : String, default : 'insert' },
            insertcallback : { type : Function, default : function(){} },
            responsecallback : { type : Function, default : function(){} },
            errorcallback : { type : Function, default : function(){} },
            scope : { type : String, default : 'modal' },
            fileState : { type : String, default : '1' },
            refreshCycle : { type : Number, default : 3000 }, // refresh media in ms, if state is not 1 (ready)
         },

        data : function() {
            return {
                data : {
                    fileid : this.dataFileid,
                    file : this.dataFile,
                    icon : this.dataIcon,
                    description : this.dataDescription,
                    downurl : this.dataDownurl,
                    delurl : this.delurl,
                    type : this.dataType,
                    width : this.width,
                    height : this.height,
                    typeName : this.mediaType, 
                },
                state : this.fileState,
                filesrc : this.src,
                callcontext : this.scope,
                refreshTimer : null,
                refreshDones : 3,
            }
        },

        methods : {

            deleteImage() {
                //console.log('Delete: '+this.data.fileid);
                var me = this;
                axios({
                    method : this.delmethod,
                    url : this.data.delurl,
                    data : this.data})
                    .then(function (response) {
                       if (_.isFunction(me.responsecallback)) {
                            me.responsecallback(response, me);
                            return;
                        }
                        console.log(response);
                      })
                      .catch(function (error) {
                       if (_.isFunction(me.errorcallback)) {
                            me.errorcallback(error, me);
                            return;
                        }
                        console.log(error);
                      });
            },

            insertImage() {
                console.log(this.data)
                if (_.isFunction(this.insertcallback)) {
                    this.insertcallback(this);
                    return;
                }
                console.log('No callback defined');
            },

            refresh() {
                var me = this;
                axios.get(this.fileDataUrl)
                    .then(function (response) {
                        //console.log(response.data.State);
                        if (response?.data?.State == 1) {
                            if (me.refreshDones <= 0) {
                                clearInterval(me.refreshTimer);
                            }
                            me.refreshDones--;
                        } else if(response?.data?.State == 0) {
                            me.filesrc = me.data.icon + '?t=' + new Date().getTime(); 
                        }   
                    }).catch(function (error) {
                        clearInterval(me.refreshTimer);
                        //console.log(error);
                    });
            },
        },
    };

</script>

<style lang="scss">

    .fm-thumbnail {
        width: 180px;
        min-height:150px;
        margin:10px;
        text-align:center;
        .thumbnail {
            margin:auto auto 5px;
            width:80%;
            img {
                width:100%;
                height:auto;
            }
        }
    }

 </style>
