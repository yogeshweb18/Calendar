<template>
  <div>
    <Head title="Calendar" />

    <Heading class="mb-6">Calendar</Heading>

    <div class="total-container">
      <Card class="calendar-wrapper" style="min-height: 300px">
        <div class="event-filter">
          <button v-for="source, index in eventSources" :key="index" :class="{ active: source.enable }"
            @click="handleFilterButtonClick(index)">{{ `${source.name} (${source.events.length})` }}</button>
        </div>
        <div class="calendar-content">
          <FullCalendar class='demo-app-calendar' :options='calendarOptions' />
        </div>
      </Card>

      <div class="event-info-wrapper">
        <div class="event-info-content" v-if="selectedData.selected">
            <Card class="event-info">
            <h4 class="compliancedetails">Compliance Details</h4>
            <div class="info-container">
              <div class="info-item">
                <h4>CL Code:</h4>
                <p>{{ selectedData.clcode }}</p>
              </div>

              <div class="info-item">
                <h4>Start Date:</h4>
                <p>{{ selectedData.startDate }}</p>
              </div>

              <div class="info-item">
                <h4>Status:</h4>
                <p>{{ selectedData.statusDisplay }}</p>
              </div>

              <div class="info-item">
                <h4>End Date:</h4>
                <p>{{ selectedData.endDate }}</p>
              </div>

              <div class="info-item">
                <h4>Files:</h4>
              <span class="answer" v-for="uploads in selectedData.uploads">
                      <button @click.prevent="getAttachment(selectedData.id,uploads)"><span style="text-decoration: underline;">{{uploads}}</span></button>
                    </span>
              </div>
            </div>
            </Card>

            <div v-if="selectedData.status == 3 && viewOnly != 1">
              <button class="shadow relative bg-primary-500 hover:bg-primary-400 text-white dark:text-gray-900 cursor-pointer rounded text-sm font-bold focus:outline-none focus:ring ring-primary-200 dark:ring-gray-600 inline-flex items-center justify-center h-9 px-3"><Link class="white" size="md" href="/admin/compliance-overview/add-tracking" method="post" :data="{complianceId:selectedData.complianceId,covenantId:selectedData.covenantId,action:'add-tracking'}">Add More Tracking</Link></button>
            </div>

            <Card v-if="selectedData.status != 2  && selectedData.status != 3 && viewOnly != 1">
            <form id="compliance_form" enctype="multipart/form-data" @submit.prevent="handleSubmit()">
            <div class="info-container upload-controller">
              <div class="info-item">
                <h4>Resolution:</h4>
                <input name="resolution" v-model="resolution" v-on:focusout="notifyIfFail" placeholder="Enter Value" required="required" />
              </div>              
              <div class="info-item">
                <button @click.prevent="handleUploadFileClick" class="shadow relative bg-primary-500 hover:bg-primary-400 text-white dark:text-gray-900 cursor-pointer rounded text-sm font-bold focus:outline-none focus:ring ring-primary-200 dark:ring-gray-600 inline-flex items-center justify-center h-9 px-3">Upload File</button>
                <input type="file" id="file" name="file" style="display: none;" ref="file" multiple="multiple" @change="handleSelectedFilesChange">
              </div>
            </div>

            <div class="info-item1 error-message">{{this.failureMessage}}</div>
            
            <div class="selected-file-list">
              <p>{{ selectedFiles.length }} files selected</p>
              <div class="selected-files-container">
                <div v-for="f, index in selectedFiles" :key="index">
                  {{ f.name }}
                  <img src="/img/cross1.png" alt="cross" @click="handleRemoveSelectedFile(index)">
                </div>
              </div>
            </div>

            <div class="text-area-container">
              <textarea v-model="comments" maxlength="300" placeholder="Add Comments">
              </textarea>
            </div>
            
            <div class="info-result">
              <h4>Result:</h4>
              <div
                class="radio-button" id="pass"
                :class="{ active: infoResult === 'pass' }"
                @click="infoResult = 'pass'"
              >Pass</div>
              <div
                class="radio-button" id="fail" 
                :class="{ active: infoResult === 'fail' }"
                @click="infoResult = 'fail'"
              >Fail</div>
              <!-- <input type="checkbox" name="" id="" v-if="infoResult=='fail'"> <span>Notify Customer</span> -->
              <div
                class="notify-check"
                :class="{active: notifyCheck}"
                v-if="infoResult === 'fail'"
                @click="notifyCheck = !notifyCheck"
              ><img src="/img/check.png" alt="check" /></div>
              <span v-if="infoResult === 'fail'">Notify Customer</span>
            </div>
            <div class="button-group">
              <button class="flex-shrink-0 shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm flex-shrink-0" type="submit">Submit</button>        
              <Link size="md" href="/admin/compliance-overview/add-tracking" method="post" :data="{complianceId:selectedData.complianceId,covenantId:selectedData.covenantId,action:'add-tracking'}" v-if="tracking_on == 1"><button class="flex-shrink-0 shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm flex-shrink-0">Add More Tracking</button></Link>
            </div>
            </form>       

            <div class="info-container" v-if="failed_covenant.type == 'covenant'">

              <Link size="md" href="/admin/compliance-overview/add-tracking" method="post" :data="{complianceId:selectedData.complianceId,covenantId:selectedData.covenantId,action:'trigger-covenant',data:failed_covenant.key}"><button>Apply {{failed_covenant.label}}</button></Link>

            </div>
            <form :action="form.action" class="formaction" v-else-if="is_defined_failed == 1">
              <div class="info-container" v-if="failed_covenant.type == 'date'">
                <label class="label">{{failed_covenant.label}} :</label>
                <input type="date" class="form-control form-input form-input-bordered" v-model="failed_covenant.value" />
              </div>
              <div class="line-height3" v-for="param in failed_covenant.parameters">
                <select class="w-full form-input-bordered select-box" v-model="param.value" 
                  v-if="param.type=='dropdown'" required="required">
                    <option value="" selected>{{param.label}}</option>
                    <option v-for="data in param.option" :value="data">{{data}}</option>
                </select>
                  <div class="line-height3" v-if="param.type=='text'">
                    <label class="label">{{param.label}}</label> :
                    <input type="text" placeholder="" name="paramValue[]" class="field form-control form-input form-input-bordered" id="name-create-paramValue-text-field" dusk="frequency" v-model="param.value" required="required"/>
                  </div>
                  <div class="line-height3" v-if="param.type=='date'">
                    <label class="label">{{param.label}}</label> :
                    <input type="date" placeholder="" name="paramValue[]" class="field form-control form-input form-input-bordered" id="name-create-paramValue-text-field" dusk="frequency" v-model="param.value" required="required"/>
                  </div>
                  <div class="line-height3" v-if="param.type=='optional'">
                    <label class="label">{{param.label}}</label> :
                    <input type="text" placeholder="" name="paramValue[]" class="field form-control form-input form-input-bordered" id="name-create-paramValue-text-field" dusk="frequency"  v-model="param.value" required="required"/>
                  </div>
              </div>
              <button class="flex-shrink-0 shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm flex-shrink-0" @click.prevent="saveFailCovenant">Submit</button>
          </form>
        </Card>
        </div>
      </div>



      <div class="spinner" v-if="uploading">
        <img src="/img/spinner.gif" alt="spinner" />
      </div>
    </div>
  </div>
</template>

<script>
import { Link } from '@inertiajs/inertia-vue3';  
import '@fullcalendar/core/vdom' // solves problem with Vite
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import timeGridPlugin from '@fullcalendar/timegrid';
import moment from 'moment'

export default {
  components: { FullCalendar },
  data() {
    return {
      calendarOptions: {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        eventSources: [],
        headerToolbar: {
          center: 'dayGridMonth,timeGridFourDay' // buttons for switching between views
        },
        views: {
          dayGridMonth: {
            buttonText: "Month"
          },
          timeGridFourDay: {
            type: 'timeGrid',
            duration: { days: 7 },
            buttonText: 'Week',
          }
        },
        eventClick: this.handleEventClick,
        displayEventTime: false,
      },
      eventSources: [],
      tracking_on : 0,
      resolution: '',
      commets: '',
      selectedData: {selected: false},
      uploading: false,
      failureMessage: '',
      selectedFiles: [],
      infoResult: 'pass',
      notifyCheck: false,
      is_defined_failed: 0,
      failed_covenant: [],
      viewOnly: 1,
      form:{
        action: '',
      }
    }
  },

  methods: {
    fetchData() {
      let colors = ['red', 'blue', 'green', 'yellow', 'purple', 'pink', 'sky', 'orange', 'gold', 'silver']

      Nova.request()
        .get('/nova-vendor/calendar/calendar')
        .then((res) => {
          this.viewOnly = res.data.viewOnly;
          for(let i = 0; i < res.data.tracking_data.length; i ++) {
            let srcId;
            for(srcId = 0; srcId < this.eventSources.length; srcId ++) {
              if(this.eventSources[srcId].name === res.data.tracking_data[i].type) {
                break;
              }
            }
            if(srcId === this.eventSources.length) {
              this.eventSources.push({
                id: srcId,
                color: colors[srcId % 10],
                name: res.data.tracking_data[i].type,
                enable: true,
                events: []
              })
            }

            this.eventSources[srcId].events.push({
              id: i,
              title: `${res.data.tracking_data[i].clcode}`,
              date: res.data.tracking_data[i].trackingDate,
              allDay: false,
              dataId: i,
              data: {...res.data.tracking_data[i]}
            })
          }
          this.calendarOptions.eventSources = this.eventSources
        });
    },

    handleFilterButtonClick(index) {
      this.eventSources[index].enable = !(this.eventSources[index].enable)
      let tmp = []
      this.eventSources.forEach(item => {
        if(item.enable) {
          tmp.push(item)
        }
      })
      this.calendarOptions.eventSources = tmp
    },

    handleEventClick(e) {
      let temp = document.getElementsByClassName('fc-daygrid-event'); 
      for (let i = 0 ; i < temp.length; i++) {  
        temp[i].classList.remove('selected'); 
      } 
      e.el.classList.add('selected');
      this.selectedData = {selected: true, ...(e.el.fcSeg.eventRange.def.extendedProps.data)}
      this.comments = ''
      this.resolution = ''
      this.infoResult = 'pass'
      this.failed_covenant = [];
      this.is_defined_failed = 0;
      this.failureMessage = '';
      if(this.$refs.file && this.$refs.file.files.length > 0)
        this.$refs.file.value = null
    },

    handleUploadFileClick() {
      this.$refs.file.value = null
      this.$refs.file.click()
    },

    handleSelectedFilesChange() {
      this.selectedFiles = []
      for(let i = 0; i < this.$refs.file.files.length; i ++) {
        this.selectedFiles.push(this.$refs.file.files[i])
      }
    },
    
    handleRemoveSelectedFile(index) {
      let tmp = []
      
      this.selectedFiles.forEach((file, i) => {
        if(i !== index) {
          tmp.push(file)
        }
      })
      this.selectedFiles = tmp;
    },

    async handleSubmit() {
      if(this.resolution === '') {
        return;
      }

      let formData = new FormData()
      formData.append('resolutionStatus', this.infoResult)
      formData.append('status', this.selectedData.status)
      formData.append('resolution', this.resolution)
      formData.append('comments', this.comments)
      formData.append('instanceId', this.selectedData.id)
      formData.append('covenantId', this.selectedData.covenantId)
      formData.append('is_child', this.selectedData.is_child)
      formData.append('is_fail', this.selectedData.is_fail)
      formData.append('dueDate', this.selectedData.dueDate)
      formData.append('type', this.selectedData.type)
      formData.append('subType', this.selectedData.subType)
      formData.append('notifyCheck', this.notifyCheck)  
      //formData.append('mailCC', encryptedData)
      //formData.append('encryptKey', encryptKey)
      //formData.append('uuid', uuid)

      for (let i = 0; i < this.selectedFiles.length; i++) {
        formData.append('files[]', this.selectedFiles[i])
      }

      this.uploading = true

      Nova.request().post('/nova-vendor/calendar/submitresult', formData).then((response) => {
        this.uploading = false
        if(response.data.status) {
          var result = response.data;
          this.tracking_on = result.tracking_on;
          if(result.is_defined_failed == 1) {
            this.is_defined_failed = result.is_defined_failed;
            this.failed_covenant = result.failed_covenant;
          }
          else if(result.is_defined_failed == 0 && result.tracking_on == 0 && result.status == true) {
            location.reload();
          }
        }
        console.log(response.data.result);
      })

      
    },

    saveFailCovenant(){
      console.log(this.selectedData);

     var data = {
        'complianceId' : this.selectedData.complianceId,
        'status' : this.selectedData.status,
        'instanceId' : this.selectedData.id,
        'covenantId' : this.selectedData.covenantId,
        'reminderBefore' : this.selectedData.reminderBefore,
        'reminderInterval' : this.selectedData.reminderInterval,
        'dueDate' : this.selectedData.dueDate,
        'failed_covenant' : this.failed_covenant
      }

      Nova.request().post('/nova-vendor/calendar/saveFailCovenant', data).then((response) => {
          console.log(response.data);
          this.uploading = false
          if(!response.data) {

          }
        });
    },

    getAttachment(id,upload) {
        window.open("/admin/compliance-overview/attachment?id="+id+"&file="+upload);
      },

    notifyIfFail() {
      if(this.resolution !='' && this.selectedData.type == 'Financial') { 
        this.uploading = true;
        Nova.request().post('/nova-vendor/calendar/notifyIfFail', {'resolution':this.resolution,'covenantId':this.selectedData.covenantId}).then((response) => {
          this.uploading = false;
          this.failureMessage = '';
          if(!response.data) {
            this.failureMessage = "This Covenant is failed.";
            document.getElementById("fail").classList.add('active');
            document.getElementById("pass").classList.remove('active');
            this.infoResult = "fail";
          }
        });
      }
    },
  },

  created: function () {
    this.fetchData()
  }
}
</script>

<style>
.card-container {
  padding: 14px;
}


@import '@fullcalendar/common/main.css';
@import '@fullcalendar/daygrid/main.css';
@import '@fullcalendar/timegrid/main.css';
</style>